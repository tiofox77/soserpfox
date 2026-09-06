<?php

namespace App\Services\Invoicing\Relatorios;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Dois períodos lado a lado: quanto mudou, e em percentagem. */
class Comparativo extends Base
{
    public const MODOS = ['month' => 'Mês actual vs anterior', 'year' => 'Ano actual vs anterior', 'custom' => 'Personalizado'];

    public const INDICADORES = [
        'revenue' => 'Receita', 'invoices_count' => 'Facturas', 'clients_active' => 'Clientes activos', 'avg_ticket' => 'Ticket médio',
        'purchases' => 'Compras', 'purchases_count' => 'Nº de compras', 'cogs' => 'CMV', 'profit' => 'Lucro',
    ];

    public function esquema(): array
    {
        return [
            'slug' => 'comparative',
            'titulo' => 'Comparativo entre Períodos',
            'descricao' => 'Variação de cada indicador entre o período A e o período B.',
            'periodo' => null,
            'filtros' => [
                ['nome' => 'mode', 'rotulo' => 'Comparar', 'tipo' => 'select', 'opcoes' => self::opcoes(self::MODOS), 'omissao' => 'month'],
                ['nome' => 'periodAFrom', 'rotulo' => 'Período A, de', 'tipo' => 'date'],
                ['nome' => 'periodATo', 'rotulo' => 'Período A, até', 'tipo' => 'date'],
                ['nome' => 'periodBFrom', 'rotulo' => 'Período B, de', 'tipo' => 'date'],
                ['nome' => 'periodBTo', 'rotulo' => 'Período B, até', 'tipo' => 'date'],
            ],
            'cartoes' => [
                self::cartao('Receita A', 'a.revenue', 'dinheiro', 'green'),
                self::cartao('Receita B', 'b.revenue', 'dinheiro', 'gray'),
                self::cartao('Variação da receita', 'variance.revenue.pct', 'percentagem', 'blue'),
                self::cartao('Variação do lucro', 'variance.profit.pct', 'percentagem', 'purple'),
            ],
            'tabelas' => [[
                'chave' => 'linhas',
                'colunas' => [
                    self::col('Indicador', 'indicador'), self::col('Período A', 'a', 'numero'), self::col('Período B', 'b', 'numero'),
                    self::col('Diferença', 'diff', 'numero'), self::col('Variação %', 'pct', 'percentagem'),
                ],
            ]],
            'csv' => true,
        ];
    }

    /** As datas dos dois períodos, pelo modo — ou as que vieram escritas. */
    public static function periodos(string $mode, array $f): array
    {
        $now = Carbon::now();

        if ($mode === 'year') {
            return [
                $now->copy()->startOfYear()->format('Y-m-d'), $now->copy()->endOfYear()->format('Y-m-d'),
                $now->copy()->subYear()->startOfYear()->format('Y-m-d'), $now->copy()->subYear()->endOfYear()->format('Y-m-d'),
            ];
        }

        if ($mode === 'custom' && !empty($f['periodAFrom']) && !empty($f['periodATo']) && !empty($f['periodBFrom']) && !empty($f['periodBTo'])) {
            return [$f['periodAFrom'], $f['periodATo'], $f['periodBFrom'], $f['periodBTo']];
        }

        return [
            $now->copy()->startOfMonth()->format('Y-m-d'), $now->copy()->endOfMonth()->format('Y-m-d'),
            $now->copy()->subMonth()->startOfMonth()->format('Y-m-d'), $now->copy()->subMonth()->endOfMonth()->format('Y-m-d'),
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        $mode = $this->filtro($f, 'mode') ?? 'month';
        [$aDe, $aAte, $bDe, $bAte] = self::periodos($mode, $f);

        $a = $this->metricas($tenantId, $aDe, $aAte);
        $b = $this->metricas($tenantId, $bDe, $bAte);

        $variance = [];
        $linhas = [];
        foreach ($a as $key => $valueA) {
            $valueB = $b[$key];
            $diff = $valueA - $valueB;
            $pct = $valueB != 0 ? ($diff / abs($valueB) * 100) : ($valueA != 0 ? 100 : 0);
            $variance[$key] = ['diff' => $diff, 'pct' => $pct];
            $linhas[] = ['indicador' => self::INDICADORES[$key] ?? $key, 'a' => $valueA, 'b' => $valueB, 'diff' => $diff, 'pct' => $pct];
        }

        $periodos = ['a' => ['de' => $aDe, 'ate' => $aAte], 'b' => ['de' => $bDe, 'ate' => $bAte]];

        return compact('a', 'b', 'variance', 'linhas', 'periodos');
    }

    private function metricas(int $tenantId, $from, $to): array
    {
        $sales = DB::table('invoicing_sales_invoices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$from, $to])
            ->where('status', '!=', 'cancelled');
        $purchases = DB::table('invoicing_purchase_invoices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$from, $to])
            ->where('status', '!=', 'cancelled');
        $cogs = (float) DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->leftJoin('invoicing_products as p', 'p.id', '=', 'items.product_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$from, $to])
            ->where('inv.status', '!=', 'cancelled')
            ->sum(DB::raw('items.quantity * COALESCE(p.cost, 0)'));

        $revenue = (float) (clone $sales)->sum('subtotal');

        return [
            'revenue' => $revenue,
            'invoices_count' => (clone $sales)->count(),
            'clients_active' => (clone $sales)->distinct('client_id')->count('client_id'),
            'avg_ticket' => (float) (clone $sales)->avg('total'),
            'purchases' => (float) (clone $purchases)->sum('total'),
            'purchases_count' => (clone $purchases)->count(),
            'cogs' => $cogs,
            'profit' => $revenue - $cogs,
        ];
    }
}
