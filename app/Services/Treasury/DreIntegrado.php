<?php

namespace App\Services\Treasury;

use App\Models\Compras\Encomenda;
use App\Models\Invoicing\PurchaseInvoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O DRE INTEGRADO — RESULTADO DO PERÍODO (27/09/2026).
 *
 * Pedido de um cliente: «num determinado mês, o lucro gerado pelas vendas foi
 * suficiente para cobrir as despesas da empresa? Se não foi, apresentar o
 * prejuízo.» Os dois DRE que já existiam respondem a outras perguntas — o da
 * Faturação à margem das vendas, o da Tesouraria ao dinheiro que entra e sai.
 *
 *   Vendas − descontos + notas de débito − notas de crédito = receita líquida
 *   Receita líquida − CMV dos produtos vendidos            = lucro bruto
 *   Lucro bruto − despesas operacionais                     = resultado operacional
 *   ± outros rendimentos e encargos − impostos              = resultado líquido
 *
 * TUDO SEM IVA: o IVA não é receita nem custo, passa pela empresa.
 *
 * O QUE NÃO PODE CONTAR DUAS VEZES, e é o centro disto:
 *
 *  · COMPRAR MERCADORIA NÃO É DESPESA. Entra em stock e passa a custo (CMV) só
 *    quando se vende. O pagamento ao fornecedor dessa compra também não conta:
 *    o custo já está no CMV.
 *  · PAGAR UMA FACTURA DE COMPRA NÃO É DESPESA outra vez. A parte de serviços
 *    dessa factura já entrou como despesa pela data da factura; a parte de
 *    mercadoria vai pelo CMV. O movimento que a paga é só dívida a ser paga.
 *  · Transferências entre contas e caixas, recebimentos de vendas e as
 *    devoluções pagas de notas de crédito (já abatidas à receita) ficam fora.
 *
 * Cada movimento de tesouraria tem a NATUREZA da sua categoria: despesa,
 * compra de stock, activo, dívida, transferência… A casa traz as das
 * categorias de sistema; cada empresa pode classificar as suas
 * (`treasury_naturezas`). Cada valor do relatório abre os documentos e os
 * movimentos que lhe deram origem (`detalhe`).
 */
class DreIntegrado
{
    public const NATUREZAS = [
        'despesa' => 'Despesa operacional',
        'encargo_financeiro' => 'Encargo financeiro (juros, comissões bancárias)',
        'imposto' => 'Imposto sobre o resultado',
        'outro_rendimento' => 'Outro rendimento',
        'compra_stock' => 'Compra de stock (entra no CMV quando se vende)',
        'activo' => 'Aquisição de activo (equipamento, viatura…)',
        'divida' => 'Pagamento de dívida ou empréstimo',
        'transferencia' => 'Transferência entre contas e caixas',
        'devolucao' => 'Devolução a cliente (nota de crédito já abatida)',
        'recebimento' => 'Recebimento de vendas (a venda já é receita)',
        'ignorar' => 'Não entra no resultado',
    ];

    /** As que entram no resultado; as outras são dinheiro que não é custo nem proveito. */
    public const NO_RESULTADO = ['despesa', 'encargo_financeiro', 'imposto', 'outro_rendimento'];

    /** A natureza das categorias que o sistema escreve. */
    public const POR_OMISSAO = [
        'sale' => 'recebimento',
        'customer_payment' => 'recebimento',
        'purchase' => 'compra_stock',
        'supplier_payment' => 'compra_stock',
        'salary' => 'despesa',
        'rent' => 'despesa',
        'utilities' => 'despesa',
        'tax' => 'imposto',
        'transfer' => 'transferencia',
        'credit_note' => 'devolucao',
        'cash_adjustment' => 'despesa',
        'other' => 'despesa',
        // Há movimentos antigos com o MEIO de pagamento no lugar da categoria
        // — são recebimentos de vendas ao balcão, não despesas nem rendimentos.
        'cash' => 'recebimento',
        'card' => 'recebimento',
        'bank_transfer' => 'recebimento',
        'digital_payment' => 'recebimento',
        'check' => 'recebimento',
        'mobile_money' => 'recebimento',
    ];

    private ?array $naturezas = null;

    public function __construct(
        private int $tenantId,
        private string $de,
        private string $ate,
    ) {
    }

    // ═════════════════════════════════════════════════════════════════════
    //  O relatório
    // ═════════════════════════════════════════════════════════════════════

    public function dados(): array
    {
        $r = $this->receita();
        $cmv = $this->cmv();
        $despDocs = $this->despesasDeDocumentos();
        $movs = $this->movimentosPorNatureza();

        $despesasMovimentos = $movs['despesa']['liquido'] ?? 0.0;
        $despesas = round($despDocs['total'] + $despesasMovimentos, 2);

        $lucroBruto = round($r['receita_liquida'] - $cmv['liquido'], 2);
        $operacional = round($lucroBruto - $despesas, 2);

        $outrosRendimentos = round(-1 * ($movs['outro_rendimento']['liquido'] ?? 0.0), 2);
        $encargos = round($movs['encargo_financeiro']['liquido'] ?? 0.0, 2);
        $impostos = round($movs['imposto']['liquido'] ?? 0.0, 2);

        $antesDeImpostos = round($operacional + $outrosRendimentos - $encargos, 2);
        $liquido = round($antesDeImpostos - $impostos, 2);

        $linhas = [
            $this->linha('vendas', 'Vendas (sem IVA)', $r['vendas']),
            $this->linha('descontos', 'Descontos', -$r['descontos'], 1),
            $this->linha('notas_debito', 'Notas de débito', $r['notas_debito'], 1),
            $this->linha('notas_credito', 'Notas de crédito (devoluções e correcções)', -$r['notas_credito'], 1),
            $this->linha(null, 'Receita líquida', $r['receita_liquida'], 0, true),
            $this->linha('cmv', 'Custo das mercadorias vendidas (CMV)', -$cmv['liquido']),
            $this->linha(null, 'Lucro bruto', $lucroBruto, 0, true),
            $this->linha('despesas_documentos', 'Despesas de facturas de compra (serviços)', -$despDocs['total'], 1),
            $this->linha('despesas_movimentos', 'Despesas pagas pela tesouraria', -$despesasMovimentos, 1),
            $this->linha(null, 'Resultado operacional', $operacional, 0, true),
            $this->linha('outro_rendimento', 'Outros rendimentos', $outrosRendimentos, 1),
            $this->linha('encargo_financeiro', 'Encargos financeiros', -$encargos, 1),
            $this->linha(null, 'Resultado antes de impostos', $antesDeImpostos, 0, true),
            $this->linha('imposto', 'Impostos sobre o resultado', -$impostos, 1),
            $this->linha(null, $liquido < 0 ? 'Prejuízo do período' : 'Lucro do período', $liquido, 0, true, true),
        ];

        // O QUE SAIU OU ENTROU E NÃO É RESULTADO — mostrado para bater certo
        // com a tesouraria, e para se ver que não foi esquecido.
        $fora = [];
        foreach (array_diff(array_keys(self::NATUREZAS), self::NO_RESULTADO) as $n) {
            if (abs($movs[$n]['saidas'] ?? 0) + abs($movs[$n]['entradas'] ?? 0) < 0.005) {
                continue;
            }
            $fora[] = [
                'rubrica' => $n,
                'rotulo' => __(self::NATUREZAS[$n]),
                'saidas' => round($movs[$n]['saidas'], 2),
                'entradas' => round($movs[$n]['entradas'], 2),
            ];
        }
        if (($movs['facturas_de_compra']['saidas'] ?? 0) > 0) {
            $fora[] = [
                'rubrica' => 'facturas_de_compra',
                'rotulo' => __('Pagamentos de facturas de compra (o custo já entrou pela factura ou pelo CMV)'),
                'saidas' => round($movs['facturas_de_compra']['saidas'], 2),
                'entradas' => round($movs['facturas_de_compra']['entradas'], 2),
            ];
        }
        if ($despDocs['stock'] > 0) {
            $fora[] = [
                'rubrica' => 'compras_stock_documentos',
                'rotulo' => __('Mercadoria comprada no período (vai para stock)'),
                'saidas' => round($despDocs['stock'], 2),
                'entradas' => 0.0,
            ];
        }

        return [
            'linhas' => $linhas,
            'receita' => $r,
            'cmv' => $cmv,
            'despesas' => [
                'documentos' => round($despDocs['total'], 2),
                'movimentos' => round($despesasMovimentos, 2),
                'total' => $despesas,
                'por_categoria' => $movs['despesa']['por_categoria'] ?? [],
            ],
            'lucro_bruto' => $lucroBruto,
            'margem_bruta' => $r['receita_liquida'] > 0 ? round($lucroBruto / $r['receita_liquida'] * 100, 1) : 0.0,
            'resultado_operacional' => $operacional,
            'resultado_liquido' => $liquido,
            'fora_do_resultado' => $fora,
            'por_classificar' => $this->porClassificar(),
            'mensal' => $this->mensal(),
        ];
    }

    private function linha(?string $rubrica, string $rotulo, float $valor, int $nivel = 0, bool $total = false, bool $final = false): array
    {
        return [
            'rubrica' => $rubrica,
            'rotulo' => __($rotulo),
            'valor' => round($valor, 2),
            'nivel' => $nivel,
            'total' => $total,
            'final' => $final,
        ];
    }

    /** Os seis meses até ao fim do período — com as MESMAS contas do quadro principal. */
    private function mensal(): array
    {
        $fim = \Carbon\Carbon::parse($this->ate)->endOfMonth();
        $meses = [];

        for ($i = 5; $i >= 0; $i--) {
            $mes = $fim->copy()->subMonthsNoOverflow($i);
            $dre = new self($this->tenantId, $mes->copy()->startOfMonth()->toDateString(), $mes->copy()->endOfMonth()->toDateString());
            $r = $dre->receita();
            $cmv = $dre->cmv()['liquido'];
            $despesas = $dre->despesasDeDocumentos()['total'] + ($dre->movimentosPorNatureza()['despesa']['liquido'] ?? 0);
            $movs = $dre->movimentosPorNatureza();
            $liquido = $r['receita_liquida'] - $cmv - $despesas
                - ($movs['outro_rendimento']['liquido'] ?? 0)
                - ($movs['encargo_financeiro']['liquido'] ?? 0)
                - ($movs['imposto']['liquido'] ?? 0);

            $meses[] = [
                'mes' => $mes->translatedFormat('M/Y'),
                'receita_liquida' => round($r['receita_liquida'], 2),
                'cmv' => round($cmv, 2),
                'lucro_bruto' => round($r['receita_liquida'] - $cmv, 2),
                'despesas' => round($despesas, 2),
                'resultado' => round($liquido, 2),
            ];
        }

        return $meses;
    }

    // ═════════════════════════════════════════════════════════════════════
    //  A receita
    // ═════════════════════════════════════════════════════════════════════

    /**
     * VENDAS, DESCONTOS E NOTAS, sem IVA.
     *
     * A BASE de cada factura é o `net_total` (o líquido depois de TODOS os
     * descontos, de linha e globais). Onde falta (documentos antigos), é a
     * soma dos líquidos das linhas. As VENDAS são o bruto das linhas
     * (quantidade × preço) e os DESCONTOS a diferença — assim reconcilia
     * sempre: vendas − descontos = base.
     */
    public function receita(): array
    {
        $facturas = $this->facturasDoPeriodo()
            ->leftJoinSub(
                DB::table('invoicing_sales_invoice_items')
                    ->selectRaw('sales_invoice_id, SUM(quantity * unit_price) AS bruto, SUM(quantity * unit_price - COALESCE(discount_amount, 0)) AS liquido_linhas')
                    ->groupBy('sales_invoice_id'),
                'l', 'l.sales_invoice_id', '=', 'inv.id'
            )
            ->selectRaw('COALESCE(SUM(COALESCE(l.bruto, 0)), 0) AS bruto')
            ->selectRaw('COALESCE(SUM(CASE WHEN inv.net_total > 0 THEN inv.net_total ELSE COALESCE(l.liquido_linhas, inv.subtotal, 0) END), 0) AS base')
            ->first();

        $base = round((float) $facturas->base, 2);
        $bruto = round((float) $facturas->bruto, 2);
        // Dados antigos sem linhas coerentes: o desconto nunca é negativo.
        $descontos = round(max(0, $bruto - $base), 2);
        $vendas = round($base + $descontos, 2);

        $nd = round((float) $this->notasDoPeriodo('invoicing_debit_notes')
            ->sum(DB::raw('CASE WHEN n.net_total > 0 THEN n.net_total ELSE n.subtotal END')), 2);
        $nc = round((float) $this->notasDoPeriodo('invoicing_credit_notes')
            ->sum(DB::raw('CASE WHEN n.net_total > 0 THEN n.net_total ELSE n.subtotal END')), 2);

        return [
            'vendas' => $vendas,
            'descontos' => $descontos,
            'notas_debito' => $nd,
            'notas_credito' => $nc,
            'receita_liquida' => round($vendas - $descontos + $nd - $nc, 2),
        ];
    }

    /** As facturas que contam: emitidas no período, nem rascunho nem anuladas. */
    private function facturasDoPeriodo()
    {
        return DB::table('invoicing_sales_invoices as inv')
            ->where('inv.tenant_id', $this->tenantId)
            ->whereBetween('inv.invoice_date', [$this->de, $this->ate.' 23:59:59'])
            ->whereNotIn('inv.status', ['draft', 'cancelled'])
            ->whereNull('inv.deleted_at');
    }

    private function notasDoPeriodo(string $tabela)
    {
        return DB::table("{$tabela} as n")
            ->where('n.tenant_id', $this->tenantId)
            ->whereBetween('n.issue_date', [$this->de, $this->ate.' 23:59:59'])
            ->whereNotIn('n.status', ['draft', 'cancelled'])
            ->whereNull('n.deleted_at');
    }

    // ═════════════════════════════════════════════════════════════════════
    //  O CMV
    // ═════════════════════════════════════════════════════════════════════

    /**
     * O CUSTO DO QUE SE VENDEU, ao custo de compra À DATA DA VENDA.
     *
     * O custo de cada unidade vendida é o da última entrada de compra desse
     * artigo até ao dia da venda (factura de compra ou recepção de encomenda);
     * sem compras registadas, o custo que está no artigo. O custo de HOJE,
     * que o relatório antigo usava, mudava o CMV de meses passados sempre que
     * se comprava mais caro.
     *
     * As NOTAS DE CRÉDITO DE DEVOLUÇÃO (motivo «devolução») tiram ao CMV o
     * custo do que voltou; as de desconto ou correcção só mexem na receita.
     * Os serviços não têm CMV.
     */
    public function cmv(): array
    {
        $vendidas = $this->linhasVendidas()->get();
        $devolvidas = $this->linhasDevolvidas()->get();

        $precos = $this->custosDeCompra($vendidas->pluck('product_id')->merge($devolvidas->pluck('product_id'))->unique()->values()->all());

        $custo = fn ($l, $data) => $this->custoA((int) $l->product_id, (string) $data, (float) $l->custo_do_artigo, $precos);

        $vendido = round($vendidas->sum(fn ($l) => (float) $l->quantity * $custo($l, $l->data)), 2);
        $devolvido = round($devolvidas->sum(fn ($l) => (float) $l->quantity * $custo($l, $l->data)), 2);

        return [
            'vendido' => $vendido,
            'devolvido' => $devolvido,
            'liquido' => round($vendido - $devolvido, 2),
        ];
    }

    private function linhasVendidas()
    {
        return $this->facturasDoPeriodo()
            ->join('invoicing_sales_invoice_items as it', 'it.sales_invoice_id', '=', 'inv.id')
            ->join('invoicing_products as p', 'p.id', '=', 'it.product_id')
            ->where('p.type', '!=', 'servico')
            ->select('it.product_id', 'it.quantity', 'inv.invoice_date as data', 'inv.id as documento_id', 'inv.invoice_number as documento', 'p.name as artigo', 'p.cost as custo_do_artigo');
    }

    private function linhasDevolvidas()
    {
        return $this->notasDoPeriodo('invoicing_credit_notes')
            ->where('n.reason', 'return')
            ->join('invoicing_credit_note_items as it', 'it.credit_note_id', '=', 'n.id')
            ->join('invoicing_products as p', 'p.id', '=', 'it.product_id')
            ->where('p.type', '!=', 'servico')
            ->select('it.product_id', 'it.quantity', 'n.issue_date as data', 'n.id as documento_id', 'n.credit_note_number as documento', 'p.name as artigo', 'p.cost as custo_do_artigo');
    }

    /** As entradas de compra de cada artigo, por data — é daqui que sai o custo à data. */
    private function custosDeCompra(array $artigos): array
    {
        if ($artigos === []) {
            return [];
        }

        $mapa = [];

        DB::table('invoicing_stock_movements')
            ->where('tenant_id', $this->tenantId)
            ->where('type', 'in')
            ->whereIn('reference_type', [PurchaseInvoice::class, Encomenda::class])
            ->where('unit_cost', '>', 0)
            ->whereIn('product_id', $artigos)
            ->where('created_at', '<=', $this->ate.' 23:59:59')
            ->orderBy('created_at')
            ->get(['product_id', 'unit_cost', 'created_at'])
            ->each(function ($m) use (&$mapa) {
                $mapa[(int) $m->product_id][] = [substr((string) $m->created_at, 0, 19), (float) $m->unit_cost];
            });

        return $mapa;
    }

    private function custoA(int $artigo, string $data, float $doArtigo, array $precos): float
    {
        $ate = strlen($data) <= 10 ? $data.' 23:59:59' : substr($data, 0, 19);
        $custo = null;

        foreach ($precos[$artigo] ?? [] as [$quando, $valor]) {
            if ($quando > $ate) {
                break;
            }
            $custo = $valor;
        }

        return $custo ?? $doArtigo;
    }

    // ═════════════════════════════════════════════════════════════════════
    //  As despesas
    // ═════════════════════════════════════════════════════════════════════

    /**
     * AS DESPESAS QUE VÊM DE FACTURAS DE COMPRA: a parte de SERVIÇOS (e de
     * linhas sem artigo, que é assim que se lança uma despesa avulsa), pela
     * data da factura e sem IVA. A parte de mercadoria vai para stock.
     *
     * @return array{total: float, stock: float, facturas: Collection}
     */
    public function despesasDeDocumentos(): array
    {
        $facturas = DB::table('invoicing_purchase_invoices as f')
            ->where('f.tenant_id', $this->tenantId)
            ->whereBetween('f.invoice_date', [$this->de, $this->ate.' 23:59:59'])
            ->whereNotIn('f.status', ['draft', 'cancelled'])
            ->whereNull('f.deleted_at')
            ->leftJoinSub(
                DB::table('invoicing_purchase_invoice_items as i')
                    ->leftJoin('invoicing_products as p', 'p.id', '=', 'i.product_id')
                    ->selectRaw('i.purchase_invoice_id')
                    ->selectRaw('SUM(i.subtotal - COALESCE(i.discount_amount, 0)) AS linhas')
                    ->selectRaw("SUM(CASE WHEN i.product_id IS NULL OR p.type = 'servico' OR i.is_service = 1 THEN i.subtotal - COALESCE(i.discount_amount, 0) ELSE 0 END) AS servicos")
                    ->groupBy('i.purchase_invoice_id'),
                'l', 'l.purchase_invoice_id', '=', 'f.id'
            )
            ->get(['f.id', 'f.invoice_number', 'f.invoice_date', 'f.is_service', 'f.net_total', 'f.subtotal', 'f.discount_amount', 'f.total', 'f.tax_amount', 'l.linhas', 'l.servicos']);

        $linhas = $facturas->map(function ($f) {
            $base = (float) $f->net_total > 0
                ? (float) $f->net_total
                : max(0, (float) $f->total - (float) $f->tax_amount);

            $parte = $f->is_service ? 1.0
                : ((float) $f->linhas > 0 ? min(1.0, max(0.0, (float) $f->servicos / (float) $f->linhas)) : 0.0);

            return (object) [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'data' => substr((string) $f->invoice_date, 0, 10),
                'base' => round($base, 2),
                'despesa' => round($base * $parte, 2),
                'stock' => round($base * (1 - $parte), 2),
            ];
        });

        return [
            'total' => round($linhas->sum('despesa'), 2),
            'stock' => round($linhas->sum('stock'), 2),
            'facturas' => $linhas,
        ];
    }

    /**
     * OS MOVIMENTOS DA TESOURARIA, arrumados pela natureza.
     *
     * Cada saída soma e cada entrada abate à sua natureza («líquido» = saídas −
     * entradas). Um movimento que paga uma FACTURA DE COMPRA fica de fora, seja
     * qual for a categoria: o custo dessa factura já entrou, pela parte de
     * serviços ou pelo CMV.
     *
     * @return array<string, array{saidas: float, entradas: float, liquido: float, por_categoria?: list<array>}>
     */
    public function movimentosPorNatureza(): array
    {
        $linhas = $this->movimentosDoPeriodo()
            ->selectRaw('t.category, t.type, (t.purchase_id IS NOT NULL) AS de_factura, (t.invoice_id IS NOT NULL) AS de_venda, SUM(t.amount) AS total')
            ->groupBy('t.category', 't.type', 'de_factura', 'de_venda')
            ->get();

        $r = [];
        $categorias = [];

        foreach ($linhas as $l) {
            $natureza = $this->naturezaDoMovimento($l);
            $valor = (float) $l->total;

            $r[$natureza] ??= ['saidas' => 0.0, 'entradas' => 0.0, 'liquido' => 0.0];
            $l->type === 'expense' ? $r[$natureza]['saidas'] += $valor : $r[$natureza]['entradas'] += $valor;
            $r[$natureza]['liquido'] = round($r[$natureza]['saidas'] - $r[$natureza]['entradas'], 2);

            if ($natureza === 'despesa') {
                $c = (string) ($l->category ?: 'other');
                $categorias[$c] = ($categorias[$c] ?? 0) + ($l->type === 'expense' ? $valor : -$valor);
            }
        }

        if (isset($r['despesa'])) {
            arsort($categorias);
            $r['despesa']['por_categoria'] = collect($categorias)
                ->map(fn ($v, $c) => ['categoria' => $c, 'rotulo' => \App\Support\CategoriasDeTesouraria::nome($c), 'valor' => round($v, 2)])
                ->values()->all();
        }

        return $r;
    }

    private function movimentosDoPeriodo()
    {
        return DB::table('treasury_transactions as t')
            ->where('t.tenant_id', $this->tenantId)
            ->where('t.status', 'completed')
            ->whereBetween('t.transaction_date', [$this->de, $this->ate.' 23:59:59']);
    }

    // ═════════════════════════════════════════════════════════════════════
    //  A natureza das categorias
    // ═════════════════════════════════════════════════════════════════════

    /**
     * A natureza de UM movimento. Antes da categoria, o documento a que está
     * ligado: pagar uma factura de compra é dívida (o custo já entrou), e o
     * dinheiro de uma venda é recebimento ou devolução (a venda já é receita)
     * — seja qual for a categoria que alguém lhe tenha posto.
     */
    public function naturezaDoMovimento(object $m): string
    {
        if (! empty($m->de_factura) || ! empty($m->purchase_id)) {
            return 'facturas_de_compra';
        }

        if (! empty($m->de_venda) || ! empty($m->invoice_id)) {
            return $m->type === 'income' ? 'recebimento' : 'devolucao';
        }

        return $this->naturezaDe((string) $m->category);
    }

    public function naturezaDe(string $categoria): string
    {
        $this->naturezas ??= Schema::hasTable('treasury_naturezas')
            ? DB::table('treasury_naturezas')->where('tenant_id', $this->tenantId)->pluck('natureza', 'categoria')->all()
            : [];

        return $this->naturezas[$categoria]
            ?? self::POR_OMISSAO[$categoria]
            ?? 'despesa';
    }

    /**
     * As categorias que a empresa usou e nunca classificou — entram como
     * despesa, e o relatório pede que alguém confirme.
     *
     * @return list<array{categoria: string, rotulo: string}>
     */
    public function porClassificar(): array
    {
        $classificadas = Schema::hasTable('treasury_naturezas')
            ? DB::table('treasury_naturezas')->where('tenant_id', $this->tenantId)->pluck('categoria')->all()
            : [];

        return $this->movimentosDoPeriodo()
            ->whereNull('t.purchase_id')
            ->whereNull('t.invoice_id')
            ->whereNotNull('t.category')
            ->where('t.category', '<>', '')
            ->distinct()
            ->pluck('t.category')
            ->reject(fn ($c) => isset(self::POR_OMISSAO[$c]) || in_array($c, $classificadas, true))
            ->map(fn ($c) => ['categoria' => $c, 'rotulo' => \App\Support\CategoriasDeTesouraria::nome($c)])
            ->values()->all();
    }

    // ═════════════════════════════════════════════════════════════════════
    //  O detalhe: os documentos e os movimentos de cada valor
    // ═════════════════════════════════════════════════════════════════════

    /**
     * A ORIGEM DE UM VALOR: cada documento e cada movimento que o compõem, com
     * o endereço para o abrir.
     *
     * @return array{titulo: string, total: float, linhas: list<array>, limitado: bool}
     */
    public function detalhe(string $rubrica, int $limite = 500): array
    {
        $linhas = match ($rubrica) {
            'vendas', 'descontos' => $this->detalheDasVendas($rubrica),
            'notas_credito' => $this->detalheDasNotas('invoicing_credit_notes', 'credit_note_number', 'invoicing.credit-notes.preview'),
            'notas_debito' => $this->detalheDasNotas('invoicing_debit_notes', 'debit_note_number', 'invoicing.debit-notes.preview'),
            'cmv' => $this->detalheDoCmv(),
            'despesas_documentos' => $this->despesasDeDocumentos()['facturas']
                ->filter(fn ($f) => $f->despesa > 0)
                ->map(fn ($f) => [
                    'data' => $f->data, 'documento' => $f->numero,
                    'ligacao' => route('invoicing.purchases.invoices.preview', $f->id),
                    'descricao' => __('Parte de serviços da factura de compra'), 'valor' => $f->despesa,
                ])->values()->all(),
            'compras_stock_documentos' => $this->despesasDeDocumentos()['facturas']
                ->filter(fn ($f) => $f->stock > 0)
                ->map(fn ($f) => [
                    'data' => $f->data, 'documento' => $f->numero,
                    'ligacao' => route('invoicing.purchases.invoices.preview', $f->id),
                    'descricao' => __('Mercadoria — vai para stock'), 'valor' => $f->stock,
                ])->values()->all(),
            'despesas_movimentos' => $this->detalheDosMovimentos('despesa'),
            'facturas_de_compra' => $this->detalheDosMovimentos(null, true),
            default => array_key_exists($rubrica, self::NATUREZAS) ? $this->detalheDosMovimentos($rubrica) : [],
        };

        return [
            'titulo' => $this->tituloDaRubrica($rubrica),
            'total' => round(collect($linhas)->sum('valor'), 2),
            'linhas' => array_slice($linhas, 0, $limite),
            'limitado' => count($linhas) > $limite,
        ];
    }

    private function tituloDaRubrica(string $r): string
    {
        return __(match ($r) {
            'vendas' => 'Vendas (sem IVA)',
            'descontos' => 'Descontos',
            'notas_credito' => 'Notas de crédito',
            'notas_debito' => 'Notas de débito',
            'cmv' => 'Custo das mercadorias vendidas (CMV)',
            'despesas_documentos' => 'Despesas de facturas de compra (serviços)',
            'compras_stock_documentos' => 'Mercadoria comprada no período (vai para stock)',
            'despesas_movimentos' => 'Despesas pagas pela tesouraria',
            'facturas_de_compra' => 'Pagamentos de facturas de compra',
            default => self::NATUREZAS[$r] ?? $r,
        });
    }

    private function detalheDasVendas(string $rubrica): array
    {
        return $this->facturasDoPeriodo()
            ->leftJoinSub(
                DB::table('invoicing_sales_invoice_items')
                    ->selectRaw('sales_invoice_id, SUM(quantity * unit_price) AS bruto, SUM(quantity * unit_price - COALESCE(discount_amount, 0)) AS liquido_linhas')
                    ->groupBy('sales_invoice_id'),
                'l', 'l.sales_invoice_id', '=', 'inv.id'
            )
            ->orderBy('inv.invoice_date')->orderBy('inv.id')
            ->get(['inv.id', 'inv.invoice_number', 'inv.invoice_date', 'inv.net_total', 'inv.subtotal', 'l.bruto', 'l.liquido_linhas'])
            ->map(function ($f) use ($rubrica) {
                $base = (float) $f->net_total > 0 ? (float) $f->net_total : (float) ($f->liquido_linhas ?? $f->subtotal ?? 0);
                $desconto = max(0, (float) $f->bruto - $base);

                return [
                    'data' => substr((string) $f->invoice_date, 0, 10),
                    'documento' => $f->invoice_number,
                    'ligacao' => route('invoicing.sales.invoices.preview', $f->id),
                    'descricao' => null,
                    'valor' => round($rubrica === 'descontos' ? $desconto : $base + $desconto, 2),
                ];
            })
            ->filter(fn ($l) => $rubrica !== 'descontos' || $l['valor'] > 0)
            ->values()->all();
    }

    private function detalheDasNotas(string $tabela, string $coluna, string $rota): array
    {
        return $this->notasDoPeriodo($tabela)
            ->orderBy('n.issue_date')
            ->get(['n.id', "n.{$coluna} as numero", 'n.issue_date', 'n.net_total', 'n.subtotal'])
            ->map(fn ($n) => [
                'data' => substr((string) $n->issue_date, 0, 10),
                'documento' => $n->numero,
                'ligacao' => route($rota, $n->id),
                'descricao' => null,
                'valor' => round((float) $n->net_total > 0 ? (float) $n->net_total : (float) $n->subtotal, 2),
            ])->values()->all();
    }

    private function detalheDoCmv(): array
    {
        $vendidas = $this->linhasVendidas()->get();
        $devolvidas = $this->linhasDevolvidas()->get();
        $precos = $this->custosDeCompra($vendidas->pluck('product_id')->merge($devolvidas->pluck('product_id'))->unique()->values()->all());

        $linha = function ($l, int $sinal, string $rota) use ($precos) {
            $custo = $this->custoA((int) $l->product_id, (string) $l->data, (float) $l->custo_do_artigo, $precos);

            return [
                'data' => substr((string) $l->data, 0, 10),
                'documento' => $l->documento,
                'ligacao' => route($rota, $l->documento_id),
                'descricao' => sprintf('%s — %s × %s', $l->artigo, rtrim(rtrim(number_format((float) $l->quantity, 3, ',', '.'), '0'), ','), number_format($custo, 2, ',', '.')),
                'valor' => round($sinal * (float) $l->quantity * $custo, 2),
            ];
        };

        return $vendidas->map(fn ($l) => $linha($l, 1, 'invoicing.sales.invoices.preview'))
            ->merge($devolvidas->map(fn ($l) => $linha($l, -1, 'invoicing.credit-notes.preview')))
            ->sortBy('data')->values()->all();
    }

    /** Os movimentos de uma natureza (ou os que pagam facturas de compra). */
    private function detalheDosMovimentos(?string $natureza, bool $deFacturas = false): array
    {
        $movimentos = $this->movimentosDoPeriodo()
            ->when($deFacturas, fn ($q) => $q->whereNotNull('t.purchase_id'), fn ($q) => $q->whereNull('t.purchase_id'))
            ->orderBy('t.transaction_date')->orderBy('t.id')
            ->get(['t.id', 't.transaction_number', 't.transaction_date', 't.type', 't.category', 't.amount', 't.description', 't.reference', 't.purchase_id', 't.invoice_id']);

        return $movimentos
            ->filter(fn ($m) => $deFacturas || $this->naturezaDoMovimento($m) === $natureza)
            ->map(function ($m) use ($natureza) {
                // No resultado, as entradas de uma natureza de custo abatem-lhe;
                // num rendimento é ao contrário.
                $sinal = $m->type === 'expense' ? 1 : -1;
                if ($natureza === 'outro_rendimento') {
                    $sinal = -$sinal;
                }

                return [
                    'data' => substr((string) $m->transaction_date, 0, 10),
                    'documento' => $m->transaction_number ?: ('#'.$m->id),
                    'ligacao' => route('treasury.transactions').'?ver='.$m->id,
                    'descricao' => trim(\App\Support\CategoriasDeTesouraria::nome($m->category).' — '.($m->description ?: $m->reference ?: ''), ' —'),
                    'valor' => round($sinal * (float) $m->amount, 2),
                ];
            })->values()->all();
    }
}
