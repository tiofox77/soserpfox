<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\SalesInvoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * OS CARTÕES DO TOPO DA LISTA DE FACTURAS — a conta, num sítio só.
 *
 * O ecrã em Blade somava os valores no próprio componente Livewire. Ao passar
 * para React a lista ficou só com a contagem (`meta.total`) e os cartões
 * desapareceram. Repô-los dentro do controlador era escrever a mesma conta uma
 * segunda vez — que é exactamente como o painel da facturação e a lista
 * passariam a dizer números diferentes da mesma empresa.
 *
 * DUAS REGRAS QUE ISTO GUARDA:
 *
 * 1. CONTA-SE PELO SALDO, NUNCA PELO NOME DO ESTADO. É a mesma regra do
 *    `PainelDaFacturacao`: nomear os estados que contam deixa de fora tudo o
 *    que ainda não tem nome. O que está liquidado sai; o resto está por
 *    receber, e entra pelo que FALTA e não pelo total.
 *
 * 2. A SOMA TEM DE BATER CERTO COM AS LINHAS. Cada linha da lista mostra o seu
 *    «falta X», decidido pelo `SalesInvoiceResource::porReceber()`. Um cartão
 *    que somasse por outra regra dizia um número que não sai de nenhuma das
 *    linhas à vista. Por isso a expressão daqui é a MESMA, em SQL: uma FR está
 *    paga por definição (não tem recibo, e o `paid_amount` fica em zero para
 *    sempre), e o que está pago, anulado ou creditado não deve nada.
 *
 * E soma-se sobre a consulta JÁ FILTRADA — mesmos filtros da lista, mesmo
 * escopo por autor. Quem só vê os documentos que emitiu vê cartões só com os
 * seus; um total que incluísse as vendas dos colegas é a mesma fuga de
 * informação que a lista teria.
 */
class SomasDasFacturas
{
    /**
     * O QUE NÃO SE COBRA — a mesma lista do `SalesInvoiceResource`.
     *
     * O RASCUNHO está aqui e não é engano: uma factura em rascunho ainda não
     * foi emitida a ninguém. Não é dinheiro em falta, é um documento por
     * acabar — e a API dos recibos recusa-o, por isso contá-lo como
     * receita a haver era prometer uma cobrança impossível.
     */
    public const SEM_NADA_A_RECEBER = ['draft', 'paid', 'cancelled', 'credited'];

    /**
     * As somas de uma consulta de facturas de venda.
     *
     * UMA consulta com três somas, e não três consultas: numa empresa com
     * dezenas de milhares de facturas a diferença é entre uma varredura e três.
     *
     * @param  Builder  $query  a consulta da lista, já com filtros e escopo
     * @return array{facturado: float, por_receber: float, vencido: float}
     */
    public function de(Builder $query): array
    {
        $t = $query->getModel()->getTable();
        $porReceber = self::sqlPorReceber($t);

        /*
         * A CONSULTA DA LISTA NÃO SE SOMA COMO ESTÁ.
         *
         * Traz `with()`, `withSum()` e uma ordem para desenhar as linhas. O
         * subselect do creditado deixa ligações (`bindings`) no grupo dos
         * selects: trocar as colunas por agregados sem as limpar dá um número
         * de ligações diferente do número de interrogações, e o MySQL recusa a
         * consulta inteira. É a mesma limpeza que o Laravel faz para contar as
         * linhas de uma página.
         */
        $soma = $query->clone()
            ->toBase()
            ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order'])
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN {$t}.status = 'cancelled' THEN 0 ELSE COALESCE({$t}.total, 0) END), 0) AS facturado, "
                . "COALESCE(SUM({$porReceber}), 0) AS por_receber, "
                . "COALESCE(SUM(CASE WHEN {$t}.due_date IS NOT NULL AND {$t}.due_date < ? THEN {$porReceber} ELSE 0 END), 0) AS vencido",
                [Carbon::today()->toDateString()]
            )
            ->first();

        return [
            'facturado' => round((float) ($soma->facturado ?? 0), 2),
            'por_receber' => round((float) ($soma->por_receber ?? 0), 2),
            'vencido' => round((float) ($soma->vencido ?? 0), 2),
        ];
    }

    /**
     * O «falta receber» de uma factura, em SQL.
     *
     * Traduz, linha a linha, o `SalesInvoiceResource::porReceber()`. Se um dia
     * um deles mudar sem o outro, o ensaio que compara a soma do cartão com a
     * soma dos saldos das linhas cai — e é para isso que ele existe.
     *
     * Os estados são constantes escritas aqui, nunca coisa que venha do
     * pedido: não há aqui nada por onde entre um valor de fora.
     */
    public static function sqlPorReceber(string $tabela): string
    {
        $semDivida = "'" . implode("', '", self::SEM_NADA_A_RECEBER) . "'";
        $notas = self::sqlDasNotas($tabela);

        return "CASE WHEN COALESCE({$tabela}.invoice_type, 'FT') = 'FR'"
            . " OR {$tabela}.status IN ({$semDivida}) THEN 0"
            . " ELSE GREATEST(COALESCE({$tabela}.total, 0) - COALESCE({$tabela}.paid_amount, 0){$notas}, 0) END";
    }

    /**
     * O QUE AS NOTAS TIRAM E PÕEM, em SQL.
     *
     * Traduz o `SalesInvoice::getBalanceAttribute()` — e o extracto de conta
     * corrente, que sempre contou as duas. A nota de crédito ABATE, a de
     * débito ACRESCE, e o que está em rascunho ou anulado não conta nem numa
     * nem noutra.
     *
     * As notas apontam para facturas de VENDA: numa consulta de compras não há
     * nada a somar, e juntar os subselects só faria a base procurar por ids que
     * nunca lá estão.
     *
     * Não há aqui nada que venha do pedido — os nomes das tabelas e os estados
     * são constantes escritas neste ficheiro.
     */
    private static function sqlDasNotas(string $tabela): string
    {
        if ($tabela !== (new SalesInvoice())->getTable()) {
            return '';
        }

        $vivas = fn (string $a) => "{$a}.status NOT IN ('draft', 'cancelled') AND {$a}.deleted_at IS NULL";

        return " - COALESCE((SELECT SUM(nc.total) FROM invoicing_credit_notes nc"
            . " WHERE nc.invoice_id = {$tabela}.id AND " . $vivas('nc') . "), 0)"
            . " + COALESCE((SELECT SUM(nd.total) FROM invoicing_debit_notes nd"
            . " WHERE nd.invoice_id = {$tabela}.id AND " . $vivas('nd') . "), 0)";
    }
}
