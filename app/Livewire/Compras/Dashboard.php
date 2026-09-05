<?php

namespace App\Livewire\Compras;

use App\Models\Compras\Encomenda;
use App\Models\Compras\Requisicao;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Painel das Compras — o que está parado à minha espera.
 *
 * A pergunta que este ecrã responde não é «quanto comprámos», é «o que é que
 * está à espera de mim»: requisições por decidir, encomendas atrasadas,
 * mercadoria recebida ainda por facturar.
 */
#[Layout('layouts.app')]
#[Title('Compras')]
class Dashboard extends Component
{
    public function render()
    {
        $tenantId = activeTenantId();

        $abertas = Encomenda::where('tenant_id', $tenantId)->whereIn('estado', Encomenda::ABERTOS);

        $resumo = [
            'por_decidir' => Requisicao::where('tenant_id', $tenantId)->where('estado', 'submetida')->count(),
            'por_encomendar' => Requisicao::where('tenant_id', $tenantId)->where('estado', 'aprovada')->count(),
            'em_curso' => (clone $abertas)->count(),
            'atrasadas' => (clone $abertas)->whereNotNull('entrega_prevista')
                ->whereDate('entrega_prevista', '<', now()->toDateString())->count(),
            'por_facturar' => Encomenda::where('tenant_id', $tenantId)
                ->whereIn('estado', ['parcial', 'recebida'])->whereNull('purchase_invoice_id')->count(),
            'valor_em_curso' => (float) (clone $abertas)->sum('total'),
        ];

        // Gasto encomendado por mês, últimos 6 meses. Os meses sem encomendas
        // ficam a zero em PHP — o SQL só devolve os que existem.
        $meses = collect(range(5, 0))->map(fn ($atras) => now()->copy()->subMonths($atras)->format('Y-m'));

        $porMes = Encomenda::where('tenant_id', $tenantId)
            ->whereNotIn('estado', ['rascunho', 'cancelada'])
            ->where('data_encomenda', '>=', now()->copy()->subMonths(5)->startOfMonth())
            ->selectRaw("DATE_FORMAT(data_encomenda, '%Y-%m') m, SUM(total) t")
            ->groupBy('m')->pluck('t', 'm');

        $grafico = [
            'etiquetas' => $meses->map(fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('m/Y'))->all(),
            'valores' => $meses->map(fn ($m) => (float) ($porMes[$m] ?? 0))->all(),
        ];

        // Quem nos vende mais, no ano corrente.
        $topFornecedores = Encomenda::where('compras_encomendas.tenant_id', $tenantId)
            ->whereNotIn('estado', ['rascunho', 'cancelada'])
            ->whereYear('data_encomenda', now()->year)
            ->join('invoicing_suppliers as s', 's.id', '=', 'compras_encomendas.supplier_id')
            ->selectRaw('s.name, SUM(compras_encomendas.total) valor, COUNT(*) quantas')
            ->groupBy('s.id', 's.name')
            ->orderByDesc('valor')
            ->limit(6)
            ->get();

        return view('livewire.compras.dashboard', [
            'resumo' => $resumo,
            'grafico' => $grafico,
            'topFornecedores' => $topFornecedores,
            'aDecidir' => Requisicao::where('tenant_id', $tenantId)->where('estado', 'submetida')
                ->with(['autor:id,name', 'itens:id,requisicao_id'])->latest()->limit(6)->get(),
            'atrasadas' => (clone $abertas)->whereNotNull('entrega_prevista')
                ->whereDate('entrega_prevista', '<', now()->toDateString())
                ->with('fornecedor:id,name')->orderBy('entrega_prevista')->limit(6)->get(),
        ]);
    }
}
