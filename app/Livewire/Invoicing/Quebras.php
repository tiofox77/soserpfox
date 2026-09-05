<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\Warehouse;
use App\Models\Invoicing\Waste;
use App\Models\Product;
use App\Services\Invoicing\QuebraDeStock;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * As quebras de stock: expirado, estragado, partido, perdido.
 *
 * Serve a casa toda — salão, oficina e restaurante usam os mesmos artigos da
 * Facturação. O ecrã é registo + relatório no mesmo sítio: o registo é uma
 * linha; o relatório responde às duas perguntas que justificam registar —
 * QUANTO se perdeu no período, e PORQUÊ.
 */
#[Layout('layouts.app')]
#[Title('Quebras de Stock')]
class Quebras extends Component
{
    use WithPagination;

    // O registo novo.
    public string $procurarProduto = '';

    public ?int $produtoId = null;

    public ?string $quantidade = null;

    public ?int $armazemId = null;

    public string $motivo = 'estragado';

    public string $notas = '';

    // Os filtros da lista e do relatório.
    public string $filtroMotivo = 'todos';

    public string $de = '';

    public string $ate = '';

    public function mount(): void
    {
        $this->de = now()->startOfMonth()->format('Y-m-d');
        $this->ate = now()->format('Y-m-d');
        $this->armazemId = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)->orderBy('id')->value('id');
    }

    public function escolherProduto(int $id): void
    {
        $this->produtoId = $id;
        $this->procurarProduto = Product::where('tenant_id', activeTenantId())->findOrFail($id)->name;
    }

    public function registar(QuebraDeStock $servico): void
    {
        $this->validate([
            'produtoId' => ['required', 'integer'],
            'quantidade' => ['required'],
            'motivo' => ['required', 'in:'.implode(',', array_keys(Waste::MOTIVOS))],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], [], ['produtoId' => 'artigo', 'quantidade' => 'quantidade']);

        try {
            $servico->registar([
                'product_id' => $this->produtoId,
                'warehouse_id' => $this->armazemId,
                'quantity' => (float) str_replace(',', '.', str_replace('.', '', (string) $this->quantidade)),
                'reason' => $this->motivo,
                'notes' => $this->notas,
            ], activeTenantId(), auth()->id());

            $this->reset(['procurarProduto', 'produtoId', 'quantidade', 'notas']);
            $this->dispatch('notify', type: 'success', message: 'Quebra registada — o stock já desceu.');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            // O guardião do stock fala em Exception plana («Stock
            // insuficiente») — sem isto o operador levava com o ecrã de erro
            // em vez de saber que escolheu o armazém errado.
            $this->dispatch('notify', type: 'error',
                message: $e->getMessage().' — confira o armazém escolhido.');
        }
    }

    public function anular(int $id, QuebraDeStock $servico): void
    {
        $quebra = Waste::where('tenant_id', activeTenantId())->findOrFail($id);

        try {
            $servico->anular($quebra, activeTenantId(), auth()->id());
            $this->dispatch('notify', type: 'info', message: 'Anulada — o stock voltou pelo movimento contrário.');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function updatedFiltroMotivo(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $periodo = [$this->de.' 00:00:00', $this->ate.' 23:59:59'];

        $doPeriodo = Waste::where('tenant_id', $tenantId)
            ->whereNull('annulled_at')
            ->whereBetween('created_at', $periodo);

        // O RELATÓRIO: quanto, e porquê. Sempre sobre o período — e sem as
        // anuladas, que já não são perdas.
        $resumo = [
            'registos' => (clone $doPeriodo)->count(),
            'custo' => (float) (clone $doPeriodo)->sum('total_cost'),
        ];

        $porMotivo = (clone $doPeriodo)
            ->selectRaw('reason, COUNT(*) registos, COALESCE(SUM(total_cost), 0) custo')
            ->groupBy('reason')->orderByDesc('custo')->get();

        $porProduto = (clone $doPeriodo)
            ->selectRaw('product_id, COALESCE(SUM(quantity), 0) quantidade, COALESCE(SUM(total_cost), 0) custo')
            ->groupBy('product_id')->orderByDesc('custo')->limit(8)
            ->with('product:id,name')->get();

        $graficoMotivos = [
            'etiquetas' => $porMotivo->map(fn ($m) => Waste::MOTIVOS[$m->reason] ?? $m->reason)->all(),
            'valores' => $porMotivo->pluck('custo')->map(fn ($v) => (float) $v)->all(),
        ];

        $quebras = Waste::where('tenant_id', $tenantId)
            ->with(['product:id,name,unit', 'user:id,name', 'warehouse:id,name'])
            ->whereBetween('created_at', $periodo)
            ->when($this->filtroMotivo !== 'todos', fn ($q) => $q->where('reason', $this->filtroMotivo))
            ->latest()
            ->paginate(20);

        $sugestoes = trim($this->procurarProduto) !== '' && ! $this->produtoId
            ? Product::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('type', '!=', 'servico')
                ->where(function ($q) {
                    $t = '%'.trim($this->procurarProduto).'%';
                    $q->where('name', 'like', $t)->orWhere('code', 'like', $t)->orWhere('barcode', 'like', $t);
                })
                ->orderBy('name')->limit(8)->get(['id', 'name', 'code', 'unit'])
            : collect();

        $armazens = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('livewire.invoicing.quebras', compact(
            'quebras', 'resumo', 'porMotivo', 'porProduto', 'graficoMotivos', 'sugestoes', 'armazens'
        ));
    }
}
