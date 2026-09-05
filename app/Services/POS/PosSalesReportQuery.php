<?php

namespace App\Services\POS;

use Illuminate\Support\Facades\DB;

/**
 * A consulta do mapa de vendas do POS, num sítio só.
 *
 * O ecrã, o PDF e o Excel montavam cada um a sua versão da mesma consulta.
 * Bastava isso para divergirem — e divergiam: quando as notas de crédito
 * passaram a contar, havia três sítios para corrigir e nenhuma garantia de que
 * os três diriam o mesmo número.
 *
 * Junta FACTURAS e NOTAS DE CRÉDITO numa listagem única, por UNION, para a
 * paginação e a ordenação funcionarem sobre o conjunto e não sobre metade dele.
 */
class PosSalesReportQuery
{
    public const TIPO_FACTURA = 'FR';
    public const TIPO_NOTA    = 'NC';

    /**
     * @param  array{
     *   start_date?:string, end_date?:string, search?:string, status?:string,
     *   payment_method?:string, document_type?:string, only_user_id?:int|null,
     *   source_module?:string
     * }  $filtros
     */
    public function __construct(
        private int $tenantId,
        private array $filtros = [],
    ) {
    }

    private function filtro(string $chave, $omissao = ''): mixed
    {
        return $this->filtros[$chave] ?? $omissao;
    }

    /** A listagem unida, já ordenada da mais recente para a mais antiga. */
    public function listagem()
    {
        $tipo = $this->filtro('document_type');

        $partes = [];

        if ($tipo !== self::TIPO_NOTA) {
            $partes[] = $this->facturas();
        }

        // Um método de pagamento escolhido exclui as notas de crédito: elas não
        // têm meio de pagamento (a coluna nem existe na tabela). Sem isto, filtrar
        // por "dinheiro" devolvia na mesma todas as notas, e o filtro parecia
        // avariado.
        if ($tipo !== self::TIPO_FACTURA && blank($this->filtro('payment_method'))) {
            $partes[] = $this->notas();
        }

        if (empty($partes)) {
            // Nenhuma parte activa: devolver uma consulta vazia em vez de null,
            // para quem chama não ter de se preocupar com o caso.
            return DB::query()->fromSub($this->facturas()->whereRaw('1 = 0'), 'd');
        }

        $uniao = array_shift($partes);

        foreach ($partes as $parte) {
            $uniao->unionAll($parte);
        }

        return DB::query()
            ->fromSub($uniao, 'd')
            ->leftJoin('invoicing_clients as c', 'c.id', '=', 'd.client_id')
            ->select('d.*', 'c.name as cliente_nome', 'c.nif as cliente_nif')
            ->orderByDesc('d.data_hora')
            ->orderByDesc('d.doc_id');
    }

    /**
     * Totais do período.
     *
     * Bruto, devoluções e líquido — e não um "total" só, que era o que havia e
     * estava errado.
     *
     * O modelo tem uma subtileza que importa: uma factura marcada `credited`
     * CONTINUA a contar no bruto, e é a nota de crédito que a desconta. Excluir
     * as duas coisas descontava a devolução duas vezes. Já as `cancelled` saem
     * do bruto, porque uma factura anulada não chegou a ser uma venda — não tem
     * nota de crédito a compensá-la.
     *
     * Os totais são SEMPRE do período inteiro e não seguem o filtro de tipo: é
     * o que permite escolher "NC" para ver a lista de devoluções sem perder de
     * vista o bruto contra o qual elas pesam.
     */
    public function totais(): array
    {
        $base = $this->apenasPeriodo();

        $f = DB::query()->fromSub($base->facturas(), "f")
            ->selectRaw('
                COUNT(*) n,
                COALESCE(SUM(CASE WHEN status <> "cancelled" THEN total ELSE 0 END), 0) bruto,
                COALESCE(SUM(CASE WHEN status <> "cancelled" THEN tax_amount ELSE 0 END), 0) imposto,
                COALESCE(SUM(CASE WHEN status <> "cancelled" THEN desconto ELSE 0 END), 0) desconto,
                COALESCE(SUM(CASE WHEN status = "cancelled" THEN total ELSE 0 END), 0) anulado,
                SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) n_anuladas
            ')
            ->first();

        $n = DB::query()->fromSub($base->notas(), "n")
            ->selectRaw('COUNT(*) n, COALESCE(SUM(total), 0) devolvido, COALESCE(SUM(tax_amount), 0) imposto')
            ->first();

        $bruto     = (float) ($f->bruto ?? 0);
        $devolvido = (float) ($n->devolvido ?? 0);

        return [
            'facturas_n'   => (int) ($f->n ?? 0) - (int) ($f->n_anuladas ?? 0),
            'anuladas_n'   => (int) ($f->n_anuladas ?? 0),
            'anulado'      => (float) ($f->anulado ?? 0),
            'bruto'        => $bruto,
            'imposto'      => (float) ($f->imposto ?? 0) - (float) ($n->imposto ?? 0),
            'desconto'     => (float) ($f->desconto ?? 0),
            'notas_n'      => (int) ($n->n ?? 0),
            'devolvido'    => $devolvido,
            'liquido'      => $bruto - $devolvido,
        ];
    }

    /**
     * Cópia só com o período e o operador — é sobre isso que os totais contam.
     *
     * Os restantes filtros ficam de fora porque `status`, `payment_method` e
     * `search` só fazem sentido no ramo das facturas: a nota de crédito não tem
     * meio de pagamento nem os mesmos estados. Aplicá-los a metade da conta
     * dava resultados absurdos — com `status=cancelled`, o bruto ia a zero
     * (as anuladas não contam) e as devoluções continuavam a descontar, pelo
     * que o LÍQUIDO SAÍA NEGATIVO. Medido antes de corrigir.
     */
    private function apenasPeriodo(): self
    {
        return new self($this->tenantId, [
            'start_date'   => $this->filtro('start_date'),
            'end_date'     => $this->filtro('end_date'),
            'only_user_id' => $this->filtros['only_user_id'] ?? null,
            'source_module' => $this->filtro('source_module'),
        ]);
    }

    /** Facturas de venda, com as colunas alinhadas às das notas de crédito. */
    private function facturas()
    {
        $q = DB::table('invoicing_sales_invoices as i')
            ->selectRaw("
                '" . self::TIPO_FACTURA . "' AS doc_tipo,
                i.id AS doc_id,
                i.invoice_number AS numero,
                i.invoice_date AS data,
                COALESCE(i.system_entry_date, i.invoice_date) AS data_hora,
                i.client_id,
                i.subtotal,
                i.tax_amount,
                i.total,
                COALESCE(i.discount_amount, 0) AS desconto,
                i.payment_method,
                i.status,
                i.created_by,
                NULL AS motivo,
                NULL AS factura_origem,
                -- O tipo REAL do documento, para a etiqueta não mentir.
                -- O mapa lista tudo o que o operador facturou, e nesta base a
                -- esmagadora maioria das vendas de POS está gravada como FT e
                -- não como FR (1093 contra 53). Filtrar por invoice_type='FR'
                -- esconderia quase todas as vendas; o que se corrige é a
                -- etiqueta, que pintava um FT com a pílula 'FR'.
                COALESCE(NULLIF(i.invoice_type, ''), 'FT') AS doc_subtipo,
                -- A série INTERNA, para o relatório mostrar as duas numerações
                -- como a lista de facturas já mostra. Depois de a série ser
                -- registada, o `invoice_number` passa a levar o código da AGT
                -- (FR4226S61319N/…) e a numeração que a casa reconhece — a que
                -- procura e arquiva — desaparecia do mapa de vendas.
                --
                -- Só os PEDAÇOS: quem os junta é o
                -- SalesInvoice::comporNumeroInterno(), para não haver aqui uma
                -- segunda implementação da mesma regra.
                s.prefix AS serie_prefixo,
                s.series_code AS serie_interna
            ")
            ->leftJoin('invoicing_series as s', 's.id', '=', 'i.series_id')
            ->where('i.tenant_id', $this->tenantId)
            ->whereNull('i.deleted_at');

        $this->periodo($q, 'i.invoice_date');
        $this->autor($q, 'i.created_by');
        if ($source = $this->filtro('source_module')) {
            $q->where('i.source_module', $source);
        }

        if ($estado = $this->filtro('status')) {
            $q->where('i.status', $estado);
        }

        if ($meio = $this->filtro('payment_method')) {
            $q->where('i.payment_method', $meio);
        }

        if ($termo = trim((string) $this->filtro('search'))) {
            $q->where(function ($q) use ($termo) {
                $q->where('i.invoice_number', 'like', "%{$termo}%")
                  ->orWhereExists(fn ($s) => $s->select(DB::raw(1))
                      ->from('invoicing_clients as cc')
                      ->whereColumn('cc.id', 'i.client_id')
                      ->where(fn ($x) => $x->where('cc.name', 'like', "%{$termo}%")
                                           ->orWhere('cc.nif', 'like', "%{$termo}%")));
            });
        }

        return $q;
    }

    /** Notas de crédito, com as mesmas colunas e pela mesma ordem. */
    private function notas()
    {
        $q = DB::table('invoicing_credit_notes as n')
            ->selectRaw("
                '" . self::TIPO_NOTA . "' AS doc_tipo,
                n.id AS doc_id,
                n.credit_note_number AS numero,
                n.issue_date AS data,
                COALESCE(n.system_entry_date, n.issue_date) AS data_hora,
                n.client_id,
                n.subtotal,
                n.tax_amount,
                n.total,
                0 AS desconto,
                NULL AS payment_method,
                n.status,
                n.created_by,
                n.reason_text AS motivo,
                (SELECT ii.invoice_number FROM invoicing_sales_invoices ii WHERE ii.id = n.invoice_id) AS factura_origem,
                'NC' AS doc_subtipo,
                -- As colunas têm de bater com as das facturas: isto é um UNION.
                -- Uma nota de crédito não tem série interna a mostrar.
                NULL AS serie_prefixo,
                NULL AS serie_interna
            ")
            ->where('n.tenant_id', $this->tenantId)
            ->whereNull('n.deleted_at')
            // Uma nota anulada não devolveu nada.
            ->where('n.status', '<>', 'cancelled');

        $this->periodo($q, 'n.issue_date');
        $this->autor($q, 'n.created_by');
        if ($source = $this->filtro('source_module')) {
            $q->whereExists(fn ($invoice) => $invoice->select(DB::raw(1))
                ->from('invoicing_sales_invoices as source_invoice')
                ->whereColumn('source_invoice.id', 'n.invoice_id')
                ->where('source_invoice.source_module', $source));
        }

        if ($termo = trim((string) $this->filtro('search'))) {
            $q->where(function ($q) use ($termo) {
                $q->where('n.credit_note_number', 'like', "%{$termo}%")
                  ->orWhereExists(fn ($s) => $s->select(DB::raw(1))
                      ->from('invoicing_clients as cc')
                      ->whereColumn('cc.id', 'n.client_id')
                      ->where(fn ($x) => $x->where('cc.name', 'like', "%{$termo}%")
                                           ->orWhere('cc.nif', 'like', "%{$termo}%")));
            });
        }

        return $q;
    }

    /**
     * Período.
     *
     * Comparação directa e não `whereDate`: o `whereDate` gera `DATE(coluna)`,
     * e uma função sobre a coluna impede o MySQL de usar o índice — numa tabela
     * com milhares de facturas isso é a diferença entre ler o índice e varrer a
     * tabela. As colunas já são DATE, portanto a comparação é exacta na mesma.
     */
    private function periodo($q, string $coluna): void
    {
        if ($de = $this->filtro("start_date")) {
            $q->where($coluna, ">=", $de);
        }

        if ($ate = $this->filtro("end_date")) {
            $q->where($coluna, "<=", $ate);
        }
    }

    /**
     * Restrição às vendas do próprio operador.
     *
     * Quem não tem `invoicing.pos.reports.all` vê só o que fez — e isso tem de
     * valer também para as notas de crédito, senão o mapa restrito passava a
     * mostrar devoluções de colegas.
     */
    private function autor($q, string $coluna): void
    {
        $utilizador = $this->filtros['only_user_id'] ?? null;

        if ($utilizador) {
            $q->where($coluna, $utilizador);
        }
    }
}
