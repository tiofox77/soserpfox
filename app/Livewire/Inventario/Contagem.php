<?php

namespace App\Livewire\Inventario;

use App\Models\Invoicing\StockCount;
use App\Models\Invoicing\StockCountItem;
use App\Models\Invoicing\Warehouse;
use App\Services\Invoicing\ContagemFisica;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A contagem física: contar a prateleira, comparar com o sistema, acertar.
 *
 * O ecrã vive em dois modos: sem contagem aberta mostra o histórico e o botão
 * de abrir; com uma aberta mostra a lista de artigos para escrever o contado
 * — a diferença aparece na hora, e o fecho transforma-a em movimentos.
 */
#[Layout('layouts.app')]
#[Title('Contagem Física - Inventário')]
class Contagem extends Component
{
    use WithPagination;

    public ?int $contagemId = null;

    public ?int $armazemId = null;

    public string $procurar = '';

    public string $filtro = 'todos'; // todos|por_contar|com_diferenca

    /* ── Modais, no padrão da página de Produtos ──────────────────────
     |
     | O fluxo inteiro passa por janelas: abrir pergunta o armazém e explica
     | o congelamento; fechar mostra O RESUMO do que vai acontecer (quantos
     | acertos, quanto custa) antes do botão — um wire:confirm nativo não
     | tem como dizer isso; e uma contagem antiga abre-se para ver as
     | diferenças linha a linha.
     */
    public bool $showAbrir = false;

    public string $notaDeAbertura = '';

    public bool $showFechar = false;

    public bool $showCancelar = false;

    public ?int $verContagemId = null;

    public function mount(): void
    {
        $this->armazemId = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)->orderBy('id')->value('id');

        // Uma contagem aberta retoma-se sozinha: a meio de contar 500
        // artigos, sair e voltar não pode recomeçar do zero.
        $this->contagemId = StockCount::where('tenant_id', activeTenantId())
            ->where('status', 'open')->value('id');
    }

    public function abrir(ContagemFisica $servico): void
    {
        try {
            $contagem = $servico->abrir(
                (int) $this->armazemId,
                activeTenantId(),
                auth()->id(),
                trim($this->notaDeAbertura) ?: null
            );

            $this->contagemId = $contagem->id;
            $this->showAbrir = false;
            $this->notaDeAbertura = '';
            $this->dispatch('notify', type: 'success',
                message: 'Contagem aberta — o esperado ficou congelado agora. Boa contagem.');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function contar(int $productId, $valor, ContagemFisica $servico): void
    {
        $contagem = StockCount::where('tenant_id', activeTenantId())->findOrFail($this->contagemId);

        $cru = trim((string) $valor);

        try {
            $servico->contar(
                $contagem,
                $productId,
                $cru === '' ? null : (float) str_replace(',', '.', str_replace('.', '', $cru)),
                activeTenantId(),
                auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * O resumo que o modal de fecho mostra ANTES do botão: quantos acertos
     * vão acontecer e quanto custam — para ninguém fechar às cegas.
     */
    public function getResumoDoFechoProperty(): ?array
    {
        if (! $this->contagemId) {
            return null;
        }

        $linhas = StockCountItem::where('invoicing_stock_count_items.tenant_id', activeTenantId())
            ->where('stock_count_id', $this->contagemId)
            ->whereNotNull('counted_quantity')
            ->get(['expected_quantity', 'counted_quantity', 'unit_cost']);

        $comDiferenca = $linhas->filter(fn ($l) => abs((float) $l->counted_quantity - (float) $l->expected_quantity) > 0.0001);

        return [
            'contados' => $linhas->count(),
            'acertos' => $comDiferenca->count(),
            'custo' => round($comDiferenca->sum(fn ($l) => abs((float) $l->counted_quantity - (float) $l->expected_quantity) * (float) $l->unit_cost), 2),
        ];
    }

    public function fechar(ContagemFisica $servico): void
    {
        $contagem = StockCount::where('tenant_id', activeTenantId())->findOrFail($this->contagemId);

        try {
            $fechada = $servico->fechar($contagem, activeTenantId(), auth()->id());

            $this->contagemId = null;
            $this->showFechar = false;
            $this->dispatch('notify', type: 'success', message: sprintf(
                'Fechada: %d contado(s), %d acerto(s), %s Kz de diferença.',
                $fechada->items_counted,
                $fechada->items_adjusted,
                number_format((float) $fechada->adjustment_cost, 2, ',', '.')
            ));
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function cancelar(ContagemFisica $servico): void
    {
        $contagem = StockCount::where('tenant_id', activeTenantId())->findOrFail($this->contagemId);

        $servico->cancelar($contagem, activeTenantId());
        $this->contagemId = null;
        $this->showCancelar = false;
        $this->dispatch('notify', type: 'info', message: 'Cancelada — nada foi mexido no stock.');
    }

    /** As diferenças de uma contagem fechada, linha a linha, no modal. */
    public function getDetalheProperty(): ?array
    {
        if (! $this->verContagemId) {
            return null;
        }

        $contagem = StockCount::where('tenant_id', activeTenantId())
            ->with(['warehouse:id,name', 'opener:id,name'])
            ->find($this->verContagemId);

        if (! $contagem) {
            return null;
        }

        $ajustadas = StockCountItem::where('invoicing_stock_count_items.tenant_id', activeTenantId())
            ->where('stock_count_id', $contagem->id)
            ->whereNotNull('adjustment_movement_id')
            ->with('product:id,name,unit')
            ->get();

        return ['contagem' => $contagem, 'ajustadas' => $ajustadas];
    }

    public function updatedProcurar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltro(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $contagem = $this->contagemId
            ? StockCount::where('tenant_id', $tenantId)->with('warehouse:id,name')->find($this->contagemId)
            : null;

        $linhas = null;
        $progresso = null;

        if ($contagem && $contagem->aberta()) {
            // Qualificado, porque a lista junta invoicing_products (para
            // ordenar por nome) e as duas tabelas têm tenant_id.
            $base = StockCountItem::where('invoicing_stock_count_items.tenant_id', $tenantId)
                ->where('stock_count_id', $contagem->id);

            $progresso = [
                'total' => (clone $base)->count(),
                'contados' => (clone $base)->whereNotNull('counted_quantity')->count(),
                'diferencas' => (clone $base)->whereNotNull('counted_quantity')
                    ->whereColumn('counted_quantity', '!=', 'expected_quantity')->count(),
            ];

            $linhas = (clone $base)
                ->with('product:id,name,code,unit')
                ->when($this->filtro === 'por_contar', fn ($q) => $q->whereNull('counted_quantity'))
                ->when($this->filtro === 'com_diferenca', fn ($q) => $q->whereNotNull('counted_quantity')
                    ->whereColumn('counted_quantity', '!=', 'expected_quantity'))
                ->when(trim($this->procurar) !== '', function ($q) {
                    $t = '%'.trim($this->procurar).'%';
                    $q->whereHas('product', fn ($p) => $p->where('name', 'like', $t)->orWhere('code', 'like', $t)->orWhere('barcode', 'like', $t));
                })
                ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stock_count_items.product_id')
                ->orderBy('invoicing_products.name')
                ->select('invoicing_stock_count_items.*')
                ->paginate(40);
        }

        $historico = StockCount::where('tenant_id', $tenantId)
            ->whereIn('status', ['closed', 'cancelled'])
            ->with(['warehouse:id,name', 'opener:id,name'])
            ->latest()->limit(12)->get();

        $armazens = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('livewire.inventario.contagem', compact('contagem', 'linhas', 'progresso', 'historico', 'armazens'));
    }
}
