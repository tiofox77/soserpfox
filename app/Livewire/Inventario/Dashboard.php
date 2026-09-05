<?php

namespace App\Livewire\Inventario;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockCount;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Waste;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O painel do inventário: quanto vale, o que está errado, o que se perdeu.
 *
 *   1. VALOR — o stock a custo (o que está parado nas prateleiras);
 *   2. ERRADO — negativos (impossíveis físicos) e geridos-a-zero;
 *   3. PERDIDO — as quebras do mês (liga ao ecrã de Quebras);
 *   4. QUANDO — a última contagem física por armazém, porque um inventário
 *      nunca contado é um número em que ninguém deve confiar.
 */
#[Layout('layouts.app')]
#[Title('Inventário')]
class Dashboard extends Component
{
    public function render()
    {
        $tenantId = activeTenantId();

        // O valor do stock a custo — juntando ao produto para o custo actual.
        $valorDoStock = (float) Stock::where('invoicing_stocks.tenant_id', $tenantId)
            ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stocks.product_id')
            ->where('invoicing_stocks.quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(invoicing_stocks.quantity * invoicing_products.cost), 0) v')
            ->value('v');

        $resumo = [
            'valor' => $valorDoStock,
            'artigos_geridos' => Product::where('tenant_id', $tenantId)
                ->where('is_active', true)->where('manage_stock', true)->count(),
            'negativos' => Stock::where('tenant_id', $tenantId)->where('quantity', '<', 0)->count(),
            'a_zero' => Stock::where('invoicing_stocks.tenant_id', $tenantId)
                ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stocks.product_id')
                ->where('invoicing_products.manage_stock', true)
                ->where('invoicing_products.is_active', true)
                ->where('invoicing_stocks.quantity', '=', 0)->count(),
            'quebras_mes' => (float) Waste::where('tenant_id', $tenantId)
                ->whereNull('annulled_at')
                ->where('created_at', '>=', now()->startOfMonth())->sum('total_cost'),
        ];

        // Os negativos por nome: são impossíveis físicos — cada um é uma
        // venda de coisa que o sistema diz não existir.
        $negativos = Stock::where('invoicing_stocks.tenant_id', $tenantId)
            ->where('quantity', '<', 0)
            ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stocks.product_id')
            ->orderBy('quantity')
            ->limit(8)
            ->get(['invoicing_stocks.quantity', 'invoicing_products.name']);

        // O que mais vale parado — onde está o dinheiro.
        $maisValiosos = Stock::where('invoicing_stocks.tenant_id', $tenantId)
            ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stocks.product_id')
            ->where('invoicing_stocks.quantity', '>', 0)
            ->where('invoicing_products.cost', '>', 0)
            ->selectRaw('invoicing_products.name, invoicing_stocks.quantity, invoicing_products.cost, (invoicing_stocks.quantity * invoicing_products.cost) valor')
            ->orderByDesc('valor')
            ->limit(8)
            ->get();

        // Entradas vs saídas dos últimos 30 dias — o pulso do armazém.
        $dias = collect(range(29, 0))->map(fn ($atras) => now()->copy()->subDays($atras)->format('Y-m-d'));
        $porDia = StockMovement::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->whereIn('type', ['in', 'out'])
            ->selectRaw('DATE(created_at) d, type, COUNT(*) c')
            ->groupBy('d', 'type')->get()->groupBy('d');

        $graficoMovimentos = [
            'etiquetas' => $dias->map(fn ($d) => substr($d, 8, 2).'/'.substr($d, 5, 2))->all(),
            'entradas' => $dias->map(fn ($d) => (int) ($porDia->get($d)?->firstWhere('type', 'in')->c ?? 0))->all(),
            'saidas' => $dias->map(fn ($d) => (int) ($porDia->get($d)?->firstWhere('type', 'out')->c ?? 0))->all(),
        ];

        // A última contagem fechada por armazém — a idade da verdade.
        $ultimasContagens = StockCount::where('tenant_id', $tenantId)
            ->where('status', 'closed')
            ->with('warehouse:id,name')
            ->latest('closed_at')
            ->get()
            ->unique('warehouse_id')
            ->take(6);

        return view('livewire.inventario.dashboard', compact(
            'resumo', 'negativos', 'maisValiosos', 'graficoMovimentos', 'ultimasContagens'
        ));
    }
}
