<?php

namespace App\Livewire\Invoicing\Reports;

use App\Models\Invoicing\ProductBatch;
use App\Models\Product;
use App\Models\Invoicing\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

#[Layout('layouts.app')]
#[Title('Relatório de Validade de Produtos')]
class ExpiryReport extends Component
{
    use WithPagination;

    public $reportType = 'expiring_soon'; // expiring_soon, expired, all
    public $daysFilter = 30;
    public $warehouseFilter = '';
    public $categoryFilter = '';
    public $searchFilter = '';

    // O $exportFormat = 'pdf' saiu daqui: nunca chegou à vista, nunca foi
    // lido por ninguém, e prometia um PDF que não existe. Uma propriedade
    // pública do Livewire viaja no payload de cada pedido — não é grátis.

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
     * A consulta filtrada, sem paginação.
     *
     * Vivia dentro do render(). Saiu para aqui porque a exportação tem de
     * levar EXACTAMENTE o que está no ecrã — se os filtros forem escritos
     * duas vezes, mais dia menos dia divergem, e o ficheiro que a pessoa
     * levar para a reunião deixa de bater certo com o que ela viu.
     */
    private function consulta()
    {
        $query = ProductBatch::with(['product.category', 'warehouse'])
            ->where('invoicing_product_batches.tenant_id', activeTenantId())
            ->where('quantity_available', '>', 0);

        switch ($this->reportType) {
            case 'expiring_soon':
                $query->where('status', 'active')
                    ->whereDate('expiry_date', '<=', Carbon::now()->addDays($this->daysFilter))
                    ->whereDate('expiry_date', '>=', Carbon::now());
                break;

            case 'expired':
                $query->whereDate('expiry_date', '<', Carbon::now());
                break;

            case 'all':
                $query->whereNotNull('expiry_date');
                break;
        }

        if ($this->warehouseFilter) {
            $query->where('warehouse_id', $this->warehouseFilter);
        }

        if ($this->categoryFilter) {
            $query->whereHas('product', function ($q) {
                $q->where('category_id', $this->categoryFilter);
            });
        }

        if ($this->searchFilter) {
            $query->where(function ($q) {
                $q->where('batch_number', 'like', '%' . $this->searchFilter . '%')
                  ->orWhereHas('product', function ($p) {
                      $p->where('name', 'like', '%' . $this->searchFilter . '%');
                  });
            });
        }

        return $query->orderBy('expiry_date', 'asc');
    }

    /**
     * Exporta para CSV o que está no ecrã.
     *
     * O botão existia e dizia "em desenvolvimento" — é a lista que se leva
     * para o armazém a decidir o que abater, e não havia forma de a tirar
     * de lá.
     */
    public function exportReport()
    {
        $lotes = (clone $this->consulta())->get();

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
        $batches = $this->consulta()->paginate(20);

        // Estatísticas
        $stats = $this->getStatistics();

        // Dados para filtros
        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = DB::table('invoicing_categories')
            ->where('tenant_id', activeTenantId())
            ->orderBy('name')
            ->get();

        return view('livewire.invoicing.reports.expiry-report', compact(
            'batches',
            'stats',
            'warehouses',
            'categories'
        ));
    }

    private function getStatistics()
    {
        $tenantId = activeTenantId();

        // Total de produtos com validade
        $totalWithExpiry = ProductBatch::where('tenant_id', $tenantId)
            ->where('quantity_available', '>', 0)
            ->whereNotNull('expiry_date')
            ->count();

        // Expirando em 7 dias
        $expiring7Days = ProductBatch::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '<=', Carbon::now()->addDays(7))
            ->whereDate('expiry_date', '>=', Carbon::now())
            ->count();

        // Expirando em 30 dias
        $expiring30Days = ProductBatch::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '<=', Carbon::now()->addDays(30))
            ->whereDate('expiry_date', '>=', Carbon::now())
            ->count();

        // Já expirados
        $expired = ProductBatch::where('tenant_id', $tenantId)
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '<', Carbon::now())
            ->count();

        // Valor total em risco (produtos expirando)
        $valueAtRisk = ProductBatch::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '<=', Carbon::now()->addDays(30))
            ->whereDate('expiry_date', '>=', Carbon::now())
            ->sum(DB::raw('quantity_available * cost_price'));

        // Valor perdido (produtos expirados)
        $valueLost = ProductBatch::where('tenant_id', $tenantId)
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '<', Carbon::now())
            ->sum(DB::raw('quantity_available * cost_price'));

        return [
            'total_with_expiry' => $totalWithExpiry,
            'expiring_7_days' => $expiring7Days,
            'expiring_30_days' => $expiring30Days,
            'expired' => $expired,
            'value_at_risk' => $valueAtRisk,
            'value_lost' => $valueLost,
        ];
    }
}
