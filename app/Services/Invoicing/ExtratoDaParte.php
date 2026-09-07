<?php

namespace App\Services\Invoicing;

use App\Models\Client;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * O EXTRATO DE UM CLIENTE OU DE UM FORNECEDOR — o que se fez com ele.
 *
 * O ecrã de sempre tinha-o num modal com quatro separadores: a ficha, as contas,
 * as últimas facturas, os artigos mais comprados e a frequência mês a mês. São
 * as duas perguntas que se fazem antes de dar crédito ou negociar um preço —
 * «quanto é que ele já nos comprou» e «de quanto em quanto tempo volta» — e a
 * migração para React não trouxe nem o ecrã nem as contas.
 *
 * CLIENTE E FORNECEDOR SÃO A MESMA PERGUNTA VISTA DOS DOIS LADOS: um compra-nos
 * e o outro vende-nos. As contas são as mesmas sobre tabelas diferentes, e por
 * isso vivem juntas — escritas duas vezes, divergiriam à primeira correcção.
 *
 * O ESCOPO DO AUTOR É RESPEITADO. Sem `invoicing.documents.all`, o extrato conta
 * o que ESTE utilizador facturou a este cliente e mais nada: era a última porta
 * por onde um vendedor via o trabalho dos colegas — e aqui SOMADO, o que é pior,
 * porque lhe dizia quanto o cliente vale à casa inteira.
 */
class ExtratoDaParte
{
    /** Quantos documentos se mostram na lista. Acima disto ninguém lê. */
    private const ULTIMAS = 20;

    /** Quantos artigos entram no ranking. */
    private const TOP = 10;

    /** Quantos meses tem o gráfico de frequência. */
    private const MESES = 12;

    /** O extrato de um cliente: o que ele nos comprou. */
    public function doCliente(Client $cliente): array
    {
        $empresa = (int) $cliente->tenant_id;

        $documentos = fn () => escopoDoAutor(
            SalesInvoice::where('tenant_id', $empresa)->where('client_id', $cliente->id)
        );

        $extrato = $this->contas($documentos, 'invoice_date', 'total', 'paid_amount');

        $extrato['artigos'] = $this->artigosDaVenda($empresa, $cliente->id);

        // As notas de crédito ABATEM ao que se facturou: uma devolução não é
        // uma venda a menos no histórico, é dinheiro que voltou.
        $notas = escopoDoAutor(CreditNote::where('tenant_id', $empresa)->where('client_id', $cliente->id));

        $recibos = escopoDoAutor(Receipt::where('tenant_id', $empresa)->where('client_id', $cliente->id));

        $extrato['extras'] = [
            ['chave' => 'notas_credito', 'rotulo' => __('Notas Crédito'), 'quantos' => (clone $notas)->count(), 'total' => round((float) (clone $notas)->sum('total'), 2)],
            ['chave' => 'recibos', 'rotulo' => __('Recibos'), 'quantos' => (clone $recibos)->count(), 'total' => round((float) (clone $recibos)->sum('amount_paid'), 2)],
        ];

        return $extrato;
    }

    /** O extrato de um fornecedor: o que lhe comprámos. */
    public function doFornecedor(Supplier $fornecedor): array
    {
        $empresa = (int) $fornecedor->tenant_id;

        $documentos = fn () => escopoDoAutor(
            PurchaseInvoice::where('tenant_id', $empresa)->where('supplier_id', $fornecedor->id)
        );

        $extrato = $this->contas($documentos, 'invoice_date', 'total', 'paid_amount');

        $extrato['artigos'] = $this->artigosDaCompra($empresa, $fornecedor->id);

        // O fornecedor não tem notas de crédito nossas nem recibos nossos: o
        // que há é o que já lhe pagámos, e isso já está nas contas.
        $extrato['extras'] = [];

        return $extrato;
    }

    /* ─── As contas, iguais dos dois lados ────────────────────────────── */

    /**
     * @param  callable(): Builder  $documentos  a consulta, já com o escopo
     */
    private function contas(callable $documentos, string $colunaData, string $colunaTotal, string $colunaPago): array
    {
        $quantos = $documentos()->count();
        $facturado = (float) $documentos()->sum($colunaTotal);
        $pago = (float) $documentos()->sum($colunaPago);

        /*
         * A DÍVIDA NUNCA É NEGATIVA.
         *
         * Um adiantamento pode deixar `paid_amount` acima do total — e «−4 000
         * em dívida» não quer dizer nada a quem olha. O que se deve é zero, e o
         * excesso lê-se no que está pago.
         */
        $pendente = max(0.0, $facturado - $pago);

        $datas = $documentos()->orderBy($colunaData)->pluck($colunaData);

        return [
            'resumo' => [
                'documentos' => $quantos,
                'facturado' => round($facturado, 2),
                'pago' => round($pago, 2),
                'pendente' => round($pendente, 2),
                // O TICKET MÉDIO só existe com documentos: dividir por zero
                // devolvia INF, que sai no JSON como `null` e no ecrã como nada.
                'ticket_medio' => $quantos > 0 ? round($facturado / $quantos, 2) : 0.0,
                'primeira' => $datas->first() ? Carbon::parse($datas->first())->toDateString() : null,
                'ultima' => $datas->last() ? Carbon::parse($datas->last())->toDateString() : null,
                'dias_entre' => $this->diasEntre($datas),
            ],

            'documentos' => $documentos()
                ->orderByDesc($colunaData)
                ->limit(self::ULTIMAS)
                ->get()
                ->map(fn (Model $d) => [
                    'id' => $d->id,
                    'numero' => method_exists($d, 'numeroInterno') ? $d->numeroInterno() : $d->invoice_number,
                    'data' => optional($d->{$colunaData})->toDateString(),
                    'vencimento' => optional($d->due_date)->toDateString(),
                    'total' => round((float) $d->{$colunaTotal}, 2),
                    'pago' => round((float) ($d->{$colunaPago} ?? 0), 2),
                    'saldo' => round((float) $d->{$colunaTotal} - (float) ($d->{$colunaPago} ?? 0), 2),
                    'estado' => $d->status,
                    'estado_rotulo' => $d->status_label ?? $d->status,
                    'estado_cor' => $d->status_color ?? 'gray',
                ])->values()->all(),

            'frequencia' => $this->porMes($documentos, $colunaData, $colunaTotal),
        ];
    }

    /**
     * A MÉDIA DE DIAS ENTRE DOCUMENTOS — «de quanto em quanto tempo volta».
     *
     * Com um documento só não há intervalo nenhum, e zero é a resposta honesta:
     * inventar uma média de uma compra dava um número que parece informação e
     * não é.
     */
    private function diasEntre($datas): float
    {
        if ($datas->count() < 2) {
            return 0.0;
        }

        $intervalos = [];

        for ($i = 1; $i < $datas->count(); $i++) {
            /*
             * `absolute: true`, e é por uma razão que já se viu no ecrã.
             *
             * O `diffInDays` do Carbon 3 devolve valor COM SINAL: com as datas
             * por ordem crescente, comparar cada uma com a anterior dá sempre
             * negativo, e a média saía «−0,1 dias» — que foi o que a ficha de
             * um cliente com 52 facturas mostrou. Era o cálculo que vinha do
             * ecrã em Livewire, escrito quando o Carbon 2 devolvia o módulo.
             */
            $intervalos[] = Carbon::parse($datas[$i])->diffInDays(Carbon::parse($datas[$i - 1]), absolute: true);
        }

        return round(array_sum($intervalos) / count($intervalos), 1);
    }

    /**
     * Os últimos doze meses com movimento, do mais antigo para o mais recente.
     *
     * Pedem-se os doze MAIS RECENTES (`orderByDesc` + `limit`) e inverte-se: um
     * `orderBy` ascendente com limite traria os doze primeiros meses de sempre,
     * que é o contrário do que um gráfico de tendência mostra.
     */
    private function porMes(callable $documentos, string $colunaData, string $colunaTotal): array
    {
        return $documentos()
            ->selectRaw("DATE_FORMAT({$colunaData}, '%Y-%m') as periodo, COUNT(*) as quantos, SUM({$colunaTotal}) as total")
            ->groupBy('periodo')
            ->orderByDesc('periodo')
            ->limit(self::MESES)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($l) => [
                'periodo' => $l->periodo,
                'quantos' => (int) $l->quantos,
                'total' => round((float) $l->total, 2),
            ])->all();
    }

    /** O que este cliente mais comprou. */
    private function artigosDaVenda(int $empresa, int $clienteId): array
    {
        $linhas = DB::table('invoicing_sales_invoice_items as it')
            ->join('invoicing_sales_invoices as f', 'f.id', '=', 'it.sales_invoice_id')
            ->where('f.tenant_id', $empresa)
            ->where('f.client_id', $clienteId)
            // Os artigos saem das facturas, e as facturas seguem a regra do autor.
            ->tap(fn ($q) => escopoDoAutor($q, 'f.created_by'))
            ->selectRaw('it.product_name as nome, SUM(it.quantity) as quantidade, SUM(it.total) as total, COUNT(DISTINCT f.id) as documentos')
            ->groupBy('nome')
            ->orderByDesc('quantidade')
            ->limit(self::TOP)
            ->get();

        return $this->paraLista($linhas);
    }

    /** O que se comprou mais a este fornecedor. */
    private function artigosDaCompra(int $empresa, int $fornecedorId): array
    {
        $linhas = DB::table('invoicing_purchase_invoice_items as it')
            ->join('invoicing_purchase_invoices as f', 'f.id', '=', 'it.purchase_invoice_id')
            ->where('f.tenant_id', $empresa)
            ->where('f.supplier_id', $fornecedorId)
            ->tap(fn ($q) => escopoDoAutor($q, 'f.created_by'))
            ->selectRaw('it.product_name as nome, SUM(it.quantity) as quantidade, SUM(it.total) as total, COUNT(DISTINCT f.id) as documentos')
            ->groupBy('nome')
            ->orderByDesc('quantidade')
            ->limit(self::TOP)
            ->get();

        return $this->paraLista($linhas);
    }

    private function paraLista($linhas): array
    {
        return $linhas->map(fn ($l) => [
            'nome' => $l->nome ?? __('Artigo removido'),
            'quantidade' => round((float) $l->quantidade, 3),
            'total' => round((float) $l->total, 2),
            'documentos' => (int) $l->documentos,
        ])->values()->all();
    }
}
