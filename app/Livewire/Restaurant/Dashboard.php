<?php

namespace App\Livewire\Restaurant;

use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard - Restaurante')]
class Dashboard extends Component
{
    /** Quantos dias o painel olha para trás. */
    public int $dias = 14;

    /**
     * A venda por dia, para se ver a forma da semana.
     *
     * Uma agregação e não uma consulta por dia: catorze consultas para
     * desenhar uma linha é o tipo de coisa que ninguém nota até o
     * restaurante ter dois anos de comandas.
     *
     * Os dias SEM VENDA têm de aparecer a zero. Sem isto, a linha saltava por
     * cima da segunda-feira fechada e dava-lhe a venda do domingo — um
     * gráfico que mente sobre o dia mais importante de todos, o que não
     * vendeu nada.
     */
    private function vendaPorDia(int $tenantId): array
    {
        $desde = today()->subDays($this->dias - 1);

        $porDia = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled'])
            ->where('created_at', '>=', $desde->copy()->startOfDay())
            ->selectRaw('DATE(created_at) as dia, SUM(grand_total) as total, COUNT(*) as comandas')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $etiquetas = [];
        $valores = [];

        for ($d = 0; $d < $this->dias; $d++) {
            $data = $desde->copy()->addDays($d);
            $chave = $data->format('Y-m-d');

            $etiquetas[] = $data->format('d/m');
            $valores[] = (float) ($porDia[$chave] ?? 0);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /**
     * A que horas se vende. É a pergunta que decide escalas de pessoal, e não
     * havia forma de a responder sem exportar comandas para uma folha.
     */
    private function vendaPorHora(int $tenantId): array
    {
        $desde = today()->subDays($this->dias - 1)->startOfDay();

        $porHora = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled'])
            ->where('created_at', '>=', $desde)
            ->selectRaw('HOUR(created_at) as hora, SUM(grand_total) as total')
            ->groupBy('hora')
            ->pluck('total', 'hora');

        $etiquetas = [];
        $valores = [];

        // Das 6 à 1 da manhã: um restaurante que fecha às 23h não tem por que
        // mostrar dezoito horas vazias, e um que serve ceia não pode perder a
        // última hora dela.
        foreach ([6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 0, 1] as $hora) {
            $etiquetas[] = sprintf('%02dh', $hora);
            $valores[] = (float) ($porHora[$hora] ?? 0);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /** Os pratos que mais saem, por quantidade. */
    private function pratosMaisVendidos(int $tenantId): array
    {
        $desde = today()->subDays($this->dias - 1)->startOfDay();

        $linhas = DB::table('restaurant_order_items as i')
            ->join('restaurant_orders as o', 'o.id', '=', 'i.order_id')
            ->join('invoicing_products as p', 'p.id', '=', 'i.product_id')
            ->where('i.tenant_id', $tenantId)
            ->whereNotIn('o.status', ['cancelled'])
            ->where('i.kitchen_status', '!=', 'voided')
            ->where('o.created_at', '>=', $desde)
            ->groupBy('p.id', 'p.name')
            ->selectRaw('p.name as nome, SUM(i.quantity) as quantidade')
            ->orderByDesc('quantidade')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores'   => $linhas->pluck('quantidade')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    /** Quanto rende cada mesa. Uma mesa que nunca aparece aqui é uma mesa mal colocada. */
    private function receitaPorMesa(int $tenantId): array
    {
        $desde = today()->subDays($this->dias - 1)->startOfDay();

        $linhas = Order::withoutGlobalScopes()
            ->where('restaurant_orders.tenant_id', $tenantId)
            ->whereNotIn('restaurant_orders.status', ['cancelled'])
            ->where('restaurant_orders.created_at', '>=', $desde)
            ->whereNotNull('restaurant_orders.table_id')
            ->join('restaurant_tables as m', 'm.id', '=', 'restaurant_orders.table_id')
            ->groupBy('m.id', 'm.name', 'm.code')
            ->selectRaw('COALESCE(m.name, m.code) as nome, SUM(restaurant_orders.grand_total) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores'   => $linhas->pluck('total')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $ontem = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [today()->subDay()->startOfDay(), today()->subDay()->endOfDay()])
            ->sum('grand_total');

        $hoje = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
            ->sum('grand_total');

        $comandasHoje = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
            ->count();

        return view('livewire.restaurant.dashboard', [
            'tablesTotal' => DiningTable::where('is_active', true)->count(),
            'tablesOccupied' => DiningTable::whereIn('status', ['occupied', 'waiting_kitchen', 'served', 'billing'])->count(),
            'openOrders' => Order::open()->count(),
            'todaySales' => $hoje,
            'ontemSales' => (float) $ontem,
            'comandasHoje' => $comandasHoje,

            // O ticket médio: o número que diz se se está a vender mais ou só
            // a atender mais gente. Duas leituras muito diferentes.
            'ticketMedio' => $comandasHoje > 0 ? ((float) $hoje / $comandasHoje) : 0.0,

            'recentOrders' => Order::with(['table', 'waiter'])
                ->where('tenant_id', $tenantId)
                ->latest()
                ->limit(8)
                ->get(),
            'statusCounts' => DiningTable::where('is_active', true)
                ->selectRaw('status, COUNT(*) total')
                ->groupBy('status')
                ->pluck('total', 'status'),

            'porDia'    => $this->vendaPorDia($tenantId),
            'porHora'   => $this->vendaPorHora($tenantId),
            'topPratos' => $this->pratosMaisVendidos($tenantId),
            'porMesa'   => $this->receitaPorMesa($tenantId),
        ]);
    }
}
