<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use App\Livewire\Invoicing\Reports\Concerns\UsaRelatorio;
use App\Services\Invoicing\Relatorios\AjustesDeStock;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Seguimento dos ajustes de stock, o ecrã de sempre.
 *
 * Cobre só o que foi mexido À MÃO; a consulta, o resumo e a tabela por
 * operador vêm do `AjustesDeStock`, partilhado com o React. Aqui fica a
 * paginação, os filtros e o CSV.
 */
#[Layout('layouts.app')]
#[Title('Ajustes de Stock')]
class StockAdjustmentsReport extends Component
{
    use HasReportFilters;
    use WithPagination;
    use UsaRelatorio;

    public const RELATORIO = 'stock-adjustments';

    public string $warehouseFilter = '';
    public string $typeFilter = '';
    public string $userFilter = '';
    public string $search = '';
    public int $perPage = 25;

    public function mount(): void
    {
        $this->initFilters('month');
    }

    public function updatingWarehouseFilter(): void { $this->resetPage(); }
    public function updatingTypeFilter(): void      { $this->resetPage(); }
    public function updatingUserFilter(): void      { $this->resetPage(); }
    public function updatingSearch(): void          { $this->resetPage(); }
    public function updatingDateFrom(): void        { $this->resetPage(); }
    public function updatingDateTo(): void          { $this->resetPage(); }

    public function limparFiltros(): void
    {
        $this->warehouseFilter = '';
        $this->typeFilter = '';
        $this->userFilter = '';
        $this->search = '';
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.invoicing.reports.stock-adjustments-report', $this->dadosDoRelatorio(['paginar' => true]));
    }

    /** Exporta o período filtrado, para conferência fora do sistema. */
    public function exportarCsv()
    {
        $linhas = (new AjustesDeStock())->consulta((int) activeTenantId(), $this->filtrosDoRelatorio())
            ->with(['product' => fn ($p) => $p->withTrashed(), 'user', 'warehouse'])
            ->orderBy('invoicing_stock_movements.created_at')
            ->get();

        $ficheiro = 'ajustes-stock_' . $this->dateFrom . '_a_' . $this->dateTo . '.csv';

        return response()->streamDownload(function () use ($linhas) {
            $saida = fopen('php://output', 'w');
            // BOM: sem ele o Excel em português abre os acentos trocados.
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, [
                'Data', 'Hora', 'Tipo', 'Armazém', 'Código', 'Produto',
                'Quantidade', 'Saldo após', 'Custo unit.', 'Valor',
                'Lote', 'Nota', 'Operador',
            ], ';');
            foreach ($linhas as $m) {
                // Mesmas regras do ecrã, para os dois baterem certo.
                $valor = AjustesDeStock::valorDoMovimento($m);
                fputcsv($saida, [
                    $m->created_at?->format('d/m/Y'),
                    $m->created_at?->format('H:i'),
                    $this->rotuloTipo($m->type),
                    $m->warehouse->name ?? '—',
                    $m->product->code ?? '',
                    $m->product->name ?? '(artigo removido)',
                    number_format((float) $m->quantity, 2, ',', ''),
                    $m->balance_after !== null ? number_format((float) $m->balance_after, 2, ',', '') : '',
                    $m->unit_cost !== null ? number_format((float) $m->unit_cost, 2, ',', '') : '',
                    $valor !== null ? number_format($valor, 2, ',', '') : '',
                    $m->batch_reference ?? '',
                    $m->notes ?? '',
                    $m->user->name ?? '(sistema)',
                ], ';');
            }
            fclose($saida);
        }, $ficheiro, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function rotuloTipo(?string $tipo): string
    {
        return AjustesDeStock::rotuloTipo($tipo);
    }
}
