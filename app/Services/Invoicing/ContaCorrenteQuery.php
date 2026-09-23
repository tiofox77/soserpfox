<?php

namespace App\Services\Invoicing;

use Illuminate\Support\Facades\DB;

/**
 * Extracto de conta corrente — de um cliente ou de um fornecedor.
 *
 * Não havia nenhum. Havia o mapa de contas a receber (quanto está por pagar,
 * hoje) e o aging (há quanto tempo), mas não havia forma de responder à
 * pergunta que um cliente faz ao telefone: "o que é que eu devo, e porquê?".
 *
 * O extracto responde a isso pela ORDEM DOS ACONTECIMENTOS: cada documento que
 * mexeu na conta, com débito, crédito e saldo acumulado, mais o saldo que
 * transitava de antes do período. É o mesmo desenho para as duas pontas — o
 * cliente deve-nos, nós devemos ao fornecedor — e por isso as duas partilham
 * este serviço.
 */
class ContaCorrenteQuery
{
    public const CLIENTE    = 'cliente';
    public const FORNECEDOR = 'fornecedor';

    public function __construct(
        private int $tenantId,
        private string $entidade,
        private ?int $entidadeId,
        private ?string $de = null,
        private ?string $ate = null,
    ) {
    }

    private function ehCliente(): bool
    {
        return $this->entidade === self::CLIENTE;
    }

    /**
     * Movimentos do período, por ordem cronológica, com saldo acumulado.
     *
     * O saldo é calculado em PHP e não em SQL: uma soma acumulada precisa de
     * ordem estável, e a ordem só existe depois de as várias origens estarem
     * juntas. São dezenas de linhas por cliente, não milhares.
     */
    public function movimentos(): \Illuminate\Support\Collection
    {
        if (!$this->entidadeId) {
            return collect();
        }

        $linhas = collect($this->uniao()->orderBy('data')->orderBy('doc_id')->get());

        $saldo = $this->saldoAnterior();
        $sinal = $this->sinal();

        return $linhas->map(function ($l) use (&$saldo, $sinal) {
            $saldo += $sinal * ((float) $l->debito - (float) $l->credito);

            $l->saldo = round($saldo, 2);

            return $l;
        });
    }

    /**
     * O sinal que torna o saldo legível para quem o lê.
     *
     * Nas contas, a conta de um fornecedor é credora: a factura de compra
     * credita-a e o pagamento debita-a. Mostrar isso em bruto dava um saldo
     * NEGATIVO de meio milhão a quem simplesmente queria saber quanto tem a
     * pagar. Inverte-se o sinal do lado do fornecedor, e nos dois casos um
     * saldo positivo quer dizer a mesma coisa: está em dívida.
     */
    private function sinal(): int
    {
        return $this->ehCliente() ? 1 : -1;
    }

    /**
     * Saldo que transitava de antes do período.
     *
     * Sem isto o extracto começa do zero e mente: um cliente com dívida antiga
     * apareceria a zero no início de Março e o saldo final ficaria errado pelo
     * mesmo valor.
     */
    public function saldoAnterior(): float
    {
        if (!$this->entidadeId || !$this->de) {
            return 0.0;
        }

        $anterior = new self($this->tenantId, $this->entidade, $this->entidadeId, null, null);

        $r = DB::query()
            ->fromSub($anterior->uniao()->where('data', '<', $this->de), 'a')
            ->selectRaw('COALESCE(SUM(debito), 0) d, COALESCE(SUM(credito), 0) c')
            ->first();

        return round($this->sinal() * ((float) $r->d - (float) $r->c), 2);
    }

    /** Totais do extracto. */
    public function resumo(): array
    {
        $movs = $this->movimentos();

        $debito  = (float) $movs->sum('debito');
        $credito = (float) $movs->sum('credito');
        $inicial = $this->saldoAnterior();

        return [
            'saldo_anterior' => $inicial,
            'debito'         => $debito,
            'credito'        => $credito,
            'saldo_final'    => round($inicial + $this->sinal() * ($debito - $credito), 2),
            'movimentos'     => $movs->count(),
        ];
    }

    /** As várias origens, com as colunas alinhadas. */
    private function uniao()
    {
        $partes = $this->ehCliente() ? $this->origensCliente() : $this->origensFornecedor();

        $uniao = array_shift($partes);

        foreach ($partes as $p) {
            $uniao->unionAll($p);
        }

        return DB::query()->fromSub($uniao, 'm');
    }

    /** @return array<int, \Illuminate\Database\Query\Builder> */
    private function origensCliente(): array
    {
        return [
            // Factura: o cliente passa a dever.
            $this->parte('invoicing_sales_invoices', 'FT', 'invoice_number', 'invoice_date', 'total', 'debito', [
                'estado_fora' => ['cancelled'],
                'so_facturas' => true,
            ]),

            // Pagamento gravado NA factura, sem recibo emitido.
            //
            // Isto tem de existir. Só o modal de pagamentos emite recibo — o POS
            // e as facturas-recibo escrevem `paid_amount` directamente. Medido
            // nesta base: 17.544.939 Kz pagos contra 13.420 Kz em recibos, ou
            // seja quatro recibos em todo o sistema. Sem esta linha, o extracto
            // mostrava a dívida INTEIRA de quem já tinha pago tudo — 7,2 milhões
            // a um cliente cujo saldo real era negativo.
            $this->pagamentosSemRecibo('invoicing_sales_invoices', 'invoice_number', 'invoice_date', 'credito'),

            // Nota de débito: deve mais.
            $this->parte('invoicing_debit_notes', 'ND', 'debit_note_number', 'issue_date', 'total', 'debito', [
                'estado_fora' => ['cancelled'],
            ]),

            // Nota de crédito: deve menos.
            $this->parte('invoicing_credit_notes', 'NC', 'credit_note_number', 'issue_date', 'total', 'credito', [
                'estado_fora' => ['cancelled'],
            ]),

            // Recibo: pagou.
            $this->parte('invoicing_receipts', 'RC', 'receipt_number', 'payment_date', 'amount_paid', 'credito', [
                'estado_fora' => ['cancelled'],
            ]),

            // Adiantamento: entregou dinheiro antes de haver factura.
            $this->parte('invoicing_advances', 'ADT', 'advance_number', 'payment_date', 'amount', 'credito', [
                'estado_fora' => ['cancelled'],
                'sem_soft_delete_status' => true,
            ]),
        ];
    }

    /** @return array<int, \Illuminate\Database\Query\Builder> */
    private function origensFornecedor(): array
    {
        return [
            // Factura de compra: passamos a dever ao fornecedor.
            $this->parte('invoicing_purchase_invoices', 'FC', 'invoice_number', 'invoice_date', 'total', 'credito', [
                'estado_fora' => ['cancelled'],
            ]),

            // Pagamento gravado na factura de compra, sem recibo. Mesmo motivo
            // do lado do cliente.
            $this->pagamentosSemRecibo('invoicing_purchase_invoices', 'invoice_number', 'invoice_date', 'debito'),

            // Recibo de pagamento a fornecedor: pagámos.
            $this->parte('invoicing_receipts', 'RC', 'receipt_number', 'payment_date', 'amount_paid', 'debito', [
                'estado_fora' => ['cancelled'],
            ]),

            $this->parte('invoicing_advances', 'ADT', 'advance_number', 'payment_date', 'amount', 'debito', [
                'estado_fora' => ['cancelled'],
            ]),
        ];
    }

    /**
     * O que a factura diz estar pago e não tem recibo a documentá-lo.
     *
     * Subtrai-se o que já entrou como recibo para o pagamento não contar duas
     * vezes: onde há recibo, é o recibo que aparece (com o seu número e a sua
     * data); onde não há, aparece esta linha. A data é a da factura porque é
     * quando o pagamento aconteceu nos casos que a geram — POS e factura-recibo
     * são pagos no acto.
     */
    private function pagamentosSemRecibo(string $tabela, string $colunaNumero, string $colunaData, string $lado)
    {
        $colunaEntidade = $this->ehCliente() ? 'client_id' : 'supplier_id';

        // Cada tipo de recibo na SUA coluna: o de compra grava a factura em
        // `purchase_invoice_id`. Procurar em `invoice_id` não encontrava nenhum,
        // e no extracto do fornecedor cada pagamento contava duas vezes (23/09/2026).
        $colunaDoRecibo = $tabela === 'invoicing_purchase_invoices' ? 'purchase_invoice_id' : 'invoice_id';

        $porRecibo = "COALESCE((
            SELECT SUM(r.amount_paid) FROM invoicing_receipts r
            WHERE r.{$colunaDoRecibo} = t.id
              AND r.tenant_id = t.tenant_id
              AND (r.status IS NULL OR r.status <> 'cancelled')
              AND r.deleted_at IS NULL
        ), 0)";

        /*
         * E O QUE SE PAGOU COM ADIANTAMENTO (23/09/2026). O adiantamento já está
         * no extracto como documento seu (ADT, na data em que o dinheiro
         * entrou); usá-lo numa factura sobe o `paid_amount` dela, e esta linha
         * voltava a creditá-lo — o cliente aparecia com o crédito que já gastou.
         */
        $tipoDaFactura = $tabela === 'invoicing_purchase_invoices' ? 'PurchaseInvoice' : 'SalesInvoice';
        $porAdiantamento = "COALESCE((
            SELECT SUM(u.amount_used) FROM invoicing_advance_usages u
            WHERE u.invoice_id = t.id
              AND u.invoice_type = '{$tipoDaFactura}'
        ), 0)";

        $valor = "(COALESCE(t.paid_amount, 0) - {$porRecibo} - {$porAdiantamento})";

        $debito  = $lado === 'debito' ? $valor : '0';
        $credito = $lado === 'credito' ? $valor : '0';

        $q = DB::table("{$tabela} as t")
            ->selectRaw("
                'PG' AS tipo,
                t.id AS doc_id,
                CONCAT('Pagamento de ', t.{$colunaNumero}) AS numero,
                t.{$colunaData} AS data,
                {$debito} AS debito,
                {$credito} AS credito
            ")
            ->where('t.tenant_id', $this->tenantId)
            ->where("t.{$colunaEntidade}", $this->entidadeId)
            ->whereNull('t.deleted_at')
            ->whereNotIn('t.status', ['cancelled'])
            // Só onde sobra alguma coisa por documentar.
            ->whereRaw("{$valor} > 0.009");

        if (\Illuminate\Support\Facades\Schema::hasColumn($tabela, 'invoice_type')) {
            $q->whereIn('t.invoice_type', ['FT', 'FS', 'FR']);
        }

        if ($this->de) {
            $q->where("t.{$colunaData}", '>=', $this->de);
        }

        if ($this->ate) {
            $q->where("t.{$colunaData}", '<=', $this->ate);
        }

        return $q;
    }

    /**
     * Uma origem do extracto.
     *
     * `$lado` diz se o documento aumenta o débito ou o crédito. Do lado do
     * FORNECEDOR os papéis trocam — uma factura de compra é crédito nosso para
     * com ele, não débito — e é por isso que o lado é parâmetro e não está
     * fixado no tipo de documento.
     */
    private function parte(
        string $tabela,
        string $sigla,
        string $colunaNumero,
        string $colunaData,
        string $colunaValor,
        string $lado,
        array $opcoes = []
    ) {
        $colunaEntidade = $this->ehCliente() ? 'client_id' : 'supplier_id';

        $debito  = $lado === 'debito' ? "COALESCE({$colunaValor}, 0)" : '0';
        $credito = $lado === 'credito' ? "COALESCE({$colunaValor}, 0)" : '0';

        // O tipo REAL quando a tabela o guarda. Há notas de crédito e de débito
        // gravadas dentro da tabela das facturas (com total negativo), herdadas
        // de antes de terem tabela própria: rotulá-las "FT" era mentir sobre o
        // que a linha é.
        $tipo = \Illuminate\Support\Facades\Schema::hasColumn($tabela, 'invoice_type')
            ? "COALESCE(NULLIF(invoice_type, ''), '{$sigla}')"
            : "'{$sigla}'";

        $q = DB::table($tabela)
            ->selectRaw("
                {$tipo} AS tipo,
                id AS doc_id,
                {$colunaNumero} AS numero,
                {$colunaData} AS data,
                {$debito} AS debito,
                {$credito} AS credito
            ")
            ->where('tenant_id', $this->tenantId)
            ->where($colunaEntidade, $this->entidadeId);

        if (\Illuminate\Support\Facades\Schema::hasColumn($tabela, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        // Um documento anulado não moveu conta nenhuma.
        if (!empty($opcoes['estado_fora']) && \Illuminate\Support\Facades\Schema::hasColumn($tabela, 'status')) {
            $q->whereNotIn('status', $opcoes['estado_fora']);
        }

        // Dentro da tabela das facturas há guias de transporte e notas de
        // crédito/débito herdadas de antes de terem tabela própria. As guias
        // não são documento de dívida, e as notas já entram pelas suas tabelas:
        // deixá-las aqui era contá-las duas vezes e cobrar transportes.
        if (!empty($opcoes['so_facturas']) && \Illuminate\Support\Facades\Schema::hasColumn($tabela, 'invoice_type')) {
            $q->whereIn('invoice_type', ['FT', 'FS', 'FR']);
        }

        if ($this->de) {
            $q->where($colunaData, '>=', $this->de);
        }

        if ($this->ate) {
            $q->where($colunaData, '<=', $this->ate);
        }

        return $q;
    }
}
