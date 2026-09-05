<?php

namespace App\Services\Invoicing\Analytics;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Os números por trás dos gráficos de facturação.
 *
 * Vive fora dos componentes porque o mesmo dado alimenta o painel e o
 * relatório: duas consultas escritas à parte acabam sempre por divergir num
 * filtro, e depois o painel diz um total e o relatório diz outro.
 *
 * REGRA QUE ATRAVESSA TUDO: facturas anuladas nunca contam. Uma anulação
 * existe para desfazer o documento, e somá-la num gráfico faria a linha de
 * vendas subir com dinheiro que ninguém recebeu.
 *
 * Os totais somam `total` (com imposto) porque é o valor que o cliente deve.
 * Onde interessa a receita da empresa — margem, impostos — usa-se o líquido,
 * e está dito em cada sítio.
 */
class GraficosDeFacturacao
{
    /** Uma paleta estável: a mesma série sai sempre da mesma cor. */
    public const CORES = [
        '#4f46e5', '#0891b2', '#16a34a', '#db2777', '#ea580c',
        '#7c3aed', '#0d9488', '#ca8a04', '#dc2626', '#2563eb',
    ];

    /**
     * A partir de quantos dias se passa a agrupar por mês.
     *
     * 45 dias e não 90: um trimestre em barras diárias são noventa riscos
     * colados, ilegíveis. Abaixo disto o dia a dia ainda diz alguma coisa.
     */
    private const DIAS_ATE_AGRUPAR_POR_MES = 45;

    public function __construct(
        private int $tenantId,
        private Carbon $de,
        private Carbon $ate,
    ) {
    }

    public static function para(int $tenantId, $de, $ate): self
    {
        return new self(
            $tenantId,
            Carbon::parse($de)->startOfDay(),
            Carbon::parse($ate)->endOfDay(),
        );
    }

    /**
     * SÓ AS MINHAS, quando a regra da empresa é essa.
     *
     * `escopoDoAutor()` não faz nada a quem vê tudo; a quem está limitado ao
     * que emitiu, corta. Passa-se a coluna qualificada porque metade destas
     * consultas tem junções, e um `created_by` à solta é ambíguo em SQL.
     */
    private function soAsMinhas(string $coluna = 'invoicing_sales_invoices.created_by'): \Closure
    {
        return fn ($q) => escopoDoAutor($q, $coluna);
    }
    /** Este período mostra-se por mês ou por dia? */
    private function porMes(): bool
    {
        return $this->de->diffInDays($this->ate) > self::DIAS_ATE_AGRUPAR_POR_MES;
    }

    // ── Evolução ─────────────────────────────────────────────────────────

    /**
     * Vendas ao longo do tempo, agrupadas por dia ou por mês conforme o
     * período: 365 pontos diários num gráfico de um ano é uma mancha, e 2
     * pontos mensais num período de 40 dias não é uma linha.
     */
    public function evolucaoDeVendas(): array
    {
        $porMes = $this->porMes();
        $formato = $porMes ? '%Y-%m' : '%Y-%m-%d';

        $linhas = SalesInvoice::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_sales_invoices.created_by'))
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw("DATE_FORMAT(invoice_date, '{$formato}') as periodo, SUM(total) as total, COUNT(*) as documentos")
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get()
            ->keyBy('periodo');

        // Preencher os buracos: sem isto, um mês sem vendas some do eixo e a
        // linha liga Janeiro a Março como se Fevereiro não tivesse existido.
        $rotulos = [];
        $valores = [];
        $contagens = [];

        foreach ($this->pontosDoPeriodo($porMes) as $chave => $rotulo) {
            $rotulos[] = $rotulo;
            $valores[] = round((float) ($linhas[$chave]->total ?? 0), 2);
            $contagens[] = (int) ($linhas[$chave]->documentos ?? 0);
        }

        return ['rotulos' => $rotulos, 'valores' => $valores, 'documentos' => $contagens, 'porMes' => $porMes];
    }

    /** Vendas contra compras, para se ver a folga entre o que entra e o que sai. */
    public function vendasContraCompras(): array
    {
        $porMes = $this->porMes();
        $formato = $porMes ? '%Y-%m' : '%Y-%m-%d';

        $vendas = SalesInvoice::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_sales_invoices.created_by'))
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw("DATE_FORMAT(invoice_date, '{$formato}') as periodo, SUM(total) as total")
            ->groupBy('periodo')->pluck('total', 'periodo');

        $compras = PurchaseInvoice::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_purchase_invoices.created_by'))
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw("DATE_FORMAT(invoice_date, '{$formato}') as periodo, SUM(total) as total")
            ->groupBy('periodo')->pluck('total', 'periodo');

        $rotulos = [];
        $serieVendas = [];
        $serieCompras = [];

        foreach ($this->pontosDoPeriodo($porMes) as $chave => $rotulo) {
            $rotulos[] = $rotulo;
            $serieVendas[] = round((float) ($vendas[$chave] ?? 0), 2);
            $serieCompras[] = round((float) ($compras[$chave] ?? 0), 2);
        }

        return ['rotulos' => $rotulos, 'vendas' => $serieVendas, 'compras' => $serieCompras];
    }

    /** Facturado contra recebido: a distância entre os dois é a cobrança por fazer. */
    public function facturadoContraRecebido(): array
    {
        $porMes = $this->porMes();
        $formato = $porMes ? '%Y-%m' : '%Y-%m-%d';

        $facturado = SalesInvoice::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_sales_invoices.created_by'))
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw("DATE_FORMAT(invoice_date, '{$formato}') as periodo, SUM(total) as total")
            ->groupBy('periodo')->pluck('total', 'periodo');

        $recebido = Receipt::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_receipts.created_by'))
            ->whereBetween('payment_date', [$this->de, $this->ate])
            ->where('status', 'issued')
            ->selectRaw("DATE_FORMAT(payment_date, '{$formato}') as periodo, SUM(amount_paid) as total")
            ->groupBy('periodo')->pluck('total', 'periodo');

        $rotulos = [];
        $a = [];
        $b = [];

        foreach ($this->pontosDoPeriodo($porMes) as $chave => $rotulo) {
            $rotulos[] = $rotulo;
            $a[] = round((float) ($facturado[$chave] ?? 0), 2);
            $b[] = round((float) ($recebido[$chave] ?? 0), 2);
        }

        return ['rotulos' => $rotulos, 'facturado' => $a, 'recebido' => $b];
    }

    // ── Rankings ─────────────────────────────────────────────────────────

    public function topClientes(int $quantos = 8): array
    {
        $linhas = SalesInvoice::where('invoicing_sales_invoices.tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_sales_invoices.created_by'))
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->where('invoicing_sales_invoices.status', '!=', 'cancelled')
            ->leftJoin('invoicing_clients', 'invoicing_clients.id', '=', 'invoicing_sales_invoices.client_id')
            ->selectRaw('COALESCE(invoicing_clients.name, "Consumidor final") as nome, SUM(invoicing_sales_invoices.total) as total')
            ->groupBy('nome')
            ->orderByDesc('total')
            ->limit($quantos)
            ->get();

        return $this->paraGrafico($linhas, 'nome', 'total');
    }

    public function topProdutos(int $quantos = 8): array
    {
        $linhas = DB::table('invoicing_sales_invoice_items as it')
            ->join('invoicing_sales_invoices as f', 'f.id', '=', 'it.sales_invoice_id')
            ->where('f.tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('f.created_by'))
            ->whereBetween('f.invoice_date', [$this->de, $this->ate])
            ->where('f.status', '!=', 'cancelled')
            ->selectRaw('it.product_name as nome, SUM(it.total) as total')
            ->groupBy('nome')
            ->orderByDesc('total')
            ->limit($quantos)
            ->get();

        return $this->paraGrafico($linhas, 'nome', 'total');
    }

    /** Quem vendeu. Sem vendedor associado cai em "Sem vendedor" em vez de sumir. */
    public function vendasPorVendedor(int $quantos = 8): array
    {
        $linhas = SalesInvoice::where('invoicing_sales_invoices.tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_sales_invoices.created_by'))
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->where('invoicing_sales_invoices.status', '!=', 'cancelled')
            ->leftJoin('users', 'users.id', '=', 'invoicing_sales_invoices.created_by')
            ->selectRaw('COALESCE(users.name, "Sem vendedor") as nome, SUM(invoicing_sales_invoices.total) as total')
            ->groupBy('nome')
            ->orderByDesc('total')
            ->limit($quantos)
            ->get();

        return $this->paraGrafico($linhas, 'nome', 'total');
    }

    // ── Composição ───────────────────────────────────────────────────────

    /**
     * Estado das facturas do período, em valor.
     *
     * "Vencida" não é um estado guardado: é uma pendente cuja data de
     * vencimento já passou. Contá-la à parte é o que faz este gráfico servir
     * para alguma coisa — juntar tudo em "pendente" esconde o problema.
     */
    public function estadoDasFacturas(): array
    {
        $facturas = SalesInvoice::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_sales_invoices.created_by'))
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->where('status', '!=', 'cancelled')
            ->get(['status', 'total', 'due_date']);

        $hoje = Carbon::today();
        $baldes = ['Pagas' => 0.0, 'Parcialmente pagas' => 0.0, 'Por cobrar' => 0.0, 'Vencidas' => 0.0];

        foreach ($facturas as $f) {
            $valor = (float) $f->total;

            if ($f->status === 'paid') {
                $baldes['Pagas'] += $valor;
            } elseif (in_array($f->status, ['partial', 'partially_paid'], true)) {
                // O enum tem os dois: `partial` é herança e `partially_paid` é
                // o que se escreve hoje. Tratar só um deixava facturas meio
                // pagas a aparecer como se ninguém tivesse pago nada.
                $baldes['Parcialmente pagas'] += $valor;
            } elseif ($f->status === 'overdue'
                || ($f->due_date && Carbon::parse($f->due_date)->lt($hoje))) {
                $baldes['Vencidas'] += $valor;
            } else {
                $baldes['Por cobrar'] += $valor;
            }
        }

        $baldes = array_filter($baldes, fn ($v) => $v > 0);

        return [
            'rotulos' => array_keys($baldes),
            'valores' => array_map(fn ($v) => round($v, 2), array_values($baldes)),
            'cores'   => ['#16a34a', '#0891b2', '#f59e0b', '#dc2626'],
        ];
    }

    /** Por onde entra o dinheiro. */
    public function recebimentosPorMeio(): array
    {
        $linhas = Receipt::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_receipts.created_by'))
            ->whereBetween('payment_date', [$this->de, $this->ate])
            ->where('status', 'issued')
            ->selectRaw('COALESCE(payment_method, "Não indicado") as nome, SUM(amount_paid) as total')
            ->groupBy('nome')
            ->orderByDesc('total')
            ->get();

        return $this->paraGrafico($linhas, 'nome', 'total');
    }

    /**
     * Em que dias da semana se vende.
     *
     * DAYOFWEEK do MySQL começa no domingo (1). Reordena-se para segunda a
     * domingo, que é como se lê uma semana de trabalho.
     */
    public function vendasPorDiaDaSemana(): array
    {
        $linhas = SalesInvoice::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_sales_invoices.created_by'))
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DAYOFWEEK(invoice_date) as dia, SUM(total) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $nomes = [2 => 'Segunda', 3 => 'Terça', 4 => 'Quarta', 5 => 'Quinta',
                  6 => 'Sexta', 7 => 'Sábado', 1 => 'Domingo'];

        $rotulos = [];
        $valores = [];

        foreach ($nomes as $n => $nome) {
            $rotulos[] = $nome;
            $valores[] = round((float) ($linhas[$n] ?? 0), 2);
        }

        return ['rotulos' => $rotulos, 'valores' => $valores];
    }

    /**
     * IVA liquidado (nas vendas) contra IVA suportado (nas compras).
     *
     * A diferença é o que se entrega ao Estado. Não substitui o Mapa de IVA —
     * serve para ver a tendência de relance.
     */
    public function ivaLiquidadoContraSuportado(): array
    {
        $porMes = $this->porMes();
        $formato = $porMes ? '%Y-%m' : '%Y-%m-%d';

        $liquidado = SalesInvoice::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_sales_invoices.created_by'))
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw("DATE_FORMAT(invoice_date, '{$formato}') as periodo, SUM(tax_amount) as total")
            ->groupBy('periodo')->pluck('total', 'periodo');

        $suportado = PurchaseInvoice::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_purchase_invoices.created_by'))
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw("DATE_FORMAT(invoice_date, '{$formato}') as periodo, SUM(tax_amount) as total")
            ->groupBy('periodo')->pluck('total', 'periodo');

        $rotulos = [];
        $a = [];
        $b = [];

        foreach ($this->pontosDoPeriodo($porMes) as $chave => $rotulo) {
            $rotulos[] = $rotulo;
            $a[] = round((float) ($liquidado[$chave] ?? 0), 2);
            $b[] = round((float) ($suportado[$chave] ?? 0), 2);
        }

        return ['rotulos' => $rotulos, 'liquidado' => $a, 'suportado' => $b];
    }

    /** Os totais de topo do período, para os cartões acima dos gráficos. */
    public function resumo(): array
    {
        $vendas = SalesInvoice::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_sales_invoices.created_by'))
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->where('status', '!=', 'cancelled');

        $total = (float) (clone $vendas)->sum('total');
        $documentos = (clone $vendas)->count();

        return [
            'vendas'      => $total,
            'documentos'  => $documentos,
            'ticket'      => $documentos > 0 ? $total / $documentos : 0.0,
            'compras'     => (float) PurchaseInvoice::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_purchase_invoices.created_by'))
                ->whereBetween('invoice_date', [$this->de, $this->ate])
                ->where('status', '!=', 'cancelled')->sum('total'),
            'recebido'    => (float) Receipt::where('tenant_id', $this->tenantId)
            ->tap($this->soAsMinhas('invoicing_receipts.created_by'))
                ->whereBetween('payment_date', [$this->de, $this->ate])
                ->where('status', 'issued')->sum('amount_paid'),
        ];
    }

    /** Tudo de uma vez — é assim que o ecrã o pede. */
    public function tudo(): array
    {
        return [
            'resumo'        => $this->resumo(),
            'evolucao'      => $this->evolucaoDeVendas(),
            'vendasCompras' => $this->vendasContraCompras(),
            'cobranca'      => $this->facturadoContraRecebido(),
            'topClientes'   => $this->topClientes(),
            'topProdutos'   => $this->topProdutos(),
            'vendedores'    => $this->vendasPorVendedor(),
            'estados'       => $this->estadoDasFacturas(),
            'meiosPagamento'=> $this->recebimentosPorMeio(),
            'diasDaSemana'  => $this->vendasPorDiaDaSemana(),
            'iva'           => $this->ivaLiquidadoContraSuportado(),
        ];
    }

    // ── Auxiliares ───────────────────────────────────────────────────────

    /**
     * Todos os pontos do eixo, mesmo os que não têm vendas.
     *
     * @return array<string,string> chave técnica => rótulo legível
     */
    private function pontosDoPeriodo(bool $porMes): array
    {
        $pontos = [];

        if ($porMes) {
            $cursor = $this->de->copy()->startOfMonth();
            $fim = $this->ate->copy()->startOfMonth();

            while ($cursor->lte($fim)) {
                $pontos[$cursor->format('Y-m')] = $cursor->format('m/Y');
                $cursor->addMonthNoOverflow();
            }

            return $pontos;
        }

        foreach (CarbonPeriod::create($this->de->copy()->startOfDay(), $this->ate->copy()->startOfDay()) as $dia) {
            $pontos[$dia->format('Y-m-d')] = $dia->format('d/m');
        }

        return $pontos;
    }

    /** Uma colecção de {nome,total} no formato que o Chart.js quer. */
    private function paraGrafico($linhas, string $campoNome, string $campoValor): array
    {
        $rotulos = [];
        $valores = [];

        foreach ($linhas as $i => $linha) {
            $nome = (string) ($linha->{$campoNome} ?? '—');
            // Nomes de produto longos rebentam a legenda e empurram o gráfico
            // para fora do cartão.
            $rotulos[] = mb_strlen($nome) > 28 ? mb_substr($nome, 0, 27) . '…' : $nome;
            $valores[] = round((float) $linha->{$campoValor}, 2);
        }

        return [
            'rotulos' => $rotulos,
            'valores' => $valores,
            'cores'   => array_slice(array_merge(self::CORES, self::CORES), 0, max(1, count($valores))),
        ];
    }
}
