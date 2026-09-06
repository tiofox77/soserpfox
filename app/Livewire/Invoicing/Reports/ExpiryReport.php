<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\UsaRelatorio;
use App\Services\Invoicing\Relatorios\Validades;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * O mapa de validades, o ecrã de sempre. A consulta e as estatísticas vêm
 * do `Validades`, partilhado com o React; aqui fica a paginação e o CSV.
 */
#[Layout('layouts.app')]
#[Title('Relatório de Validade de Produtos')]
class ExpiryReport extends Component
{
    use WithPagination;
    use UsaRelatorio;

    public const RELATORIO = 'expiry-report';

    public $reportType = 'expiring_soon'; // expiring_soon, expired, all
    public $daysFilter = 30;
    public $warehouseFilter = '';
    public $categoryFilter = '';
    public $searchFilter = '';

    public function mount()
    {
        $this->daysFilter = 30;
    }

    public function updatedReportType()
    {
        $this->resetPage();
    }

    public function updatedDaysFilter()
    {
        $this->resetPage();
    }

    public function generateReport()
    {
        $this->resetPage();
    }

    /**
     * Exporta para CSV o que está no ecrã — a lista que se leva para o
     * armazém a decidir o que abater. A consulta é a mesma da página, para
     * o ficheiro bater certo com o que a pessoa viu.
     */
    public function exportReport()
    {
        $lotes = (new Validades())->consulta((int) activeTenantId(), $this->filtrosDoRelatorio())->get();
        $ficheiro = 'validades_' . $this->reportType . '_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($lotes) {
            $saida = fopen('php://output', 'w');
            // BOM: sem ele o Excel em português abre os acentos trocados.
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, [
                'Lote', 'Código', 'Produto', 'Categoria', 'Armazém',
                'Fabricação', 'Validade', 'Dias', 'Disponível',
                'Custo unit.', 'Valor', 'Estado',
            ], ';');
            foreach ($lotes as $lote) {
                $valor = (float) $lote->quantity_available * (float) $lote->cost_price;
                fputcsv($saida, [
                    $lote->batch_number ?? '',
                    $lote->product->code ?? '',
                    $lote->product->name ?? '(artigo removido)',
                    $lote->product->category->name ?? '',
                    $lote->warehouse->name ?? '',
                    $lote->manufacturing_date?->format('d/m/Y') ?? '',
                    $lote->expiry_date?->format('d/m/Y') ?? '',
                    $lote->days_until_expiry ?? '',
                    number_format((float) $lote->quantity_available, 2, ',', ''),
                    number_format((float) $lote->cost_price, 2, ',', ''),
                    number_format($valor, 2, ',', ''),
                    $lote->status_label,
                ], ';');
            }
            fclose($saida);
        }, $ficheiro, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        return view('livewire.invoicing.reports.expiry-report', $this->dadosDoRelatorio(['paginar' => true]));
    }
}
