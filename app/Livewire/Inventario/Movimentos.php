<?php

namespace App\Livewire\Inventario;

use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * O histórico de movimentos de stock — a resposta a «porque é que mudou?».
 *
 * Só lê. Tudo o que mexe no stock desta casa passa por aqui (vendas, compras,
 * transferências, quebras, contagens), e este ecrã é onde essa história se
 * consulta com filtros: artigo, armazém, tipo, origem, período.
 */
#[Layout('layouts.app')]
#[Title('Movimentos de Stock - Inventário')]
class Movimentos extends Component
{
    use WithPagination;

    public string $procurar = '';

    public string $tipo = 'todos';

    public ?int $armazemId = null;

    public string $de = '';

    public string $ate = '';

    /** As origens com nome de gente — `reference_type` é código de máquina. */
    public const ORIGENS = [
        'sale' => 'Venda',
        'invoice' => 'Factura',
        'purchase' => 'Compra',
        'transfer' => 'Transferência',
        'quebra' => 'Quebra',
        'quebra_anulada' => 'Quebra anulada',
        'contagem' => 'Contagem física',
        'restaurant_waste' => 'Desperdício (restaurante)',
        'adjustment' => 'Ajuste',
    ];

    public function mount(): void
    {
        $this->de = now()->subDays(30)->format('Y-m-d');
        $this->ate = now()->format('Y-m-d');
    }

    public function updatedProcurar(): void
    {
        $this->resetPage();
    }

    public function updatedTipo(): void
    {
        $this->resetPage();
    }

    public function updatedArmazemId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $periodo = [$this->de.' 00:00:00', $this->ate.' 23:59:59'];

        $base = StockMovement::where('tenant_id', $tenantId)
            ->whereBetween('created_at', $periodo)
            ->when($this->tipo !== 'todos', fn ($q) => $q->where('type', $this->tipo))
            ->when($this->armazemId, fn ($q) => $q->where(fn ($w) => $w
                ->where('warehouse_id', $this->armazemId)
                ->orWhere('from_warehouse_id', $this->armazemId)
                ->orWhere('to_warehouse_id', $this->armazemId)))
            ->when(trim($this->procurar) !== '', function ($q) {
                $t = '%'.trim($this->procurar).'%';
                $q->whereHas('product', fn ($p) => $p->where('name', 'like', $t)->orWhere('code', 'like', $t));
            });

        $resumo = [
            'movimentos' => (clone $base)->count(),
            'entradas' => (clone $base)->where('type', 'in')->count(),
            'saidas' => (clone $base)->where('type', 'out')->count(),
        ];

        $movimentos = $base
            ->with(['product:id,name,code,unit', 'user:id,name', 'warehouse:id,name'])
            ->latest()
            ->paginate(30);

        $armazens = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('livewire.inventario.movimentos', compact('movimentos', 'resumo', 'armazens'));
    }
}
