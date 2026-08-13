<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\InvoicingSeries;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Gestão de Séries de Documentos')]
class SeriesManagement extends Component
{
    use WithPagination;

    // Filtros
    public $filterType = '';
    public $search = '';
    
    // Modal
    public $showModal = false;
    public $seriesId = null;
    public $isEdit = false;
    
    // Form fields
    public $document_type = 'invoice';
    public $series_code = '';
    public $name = '';
    public $prefix = 'FT';
    public $include_year = true;
    public $next_number = 1;
    public $number_padding = 6;
    public $is_default = false;
    public $is_active = true;
    public $reset_yearly = true;
    public $description = '';

    // === Campos AGT (DS.120 §4.5/4.6) ===
    public ?int $series_year = null;
    public string $establishment_number = 'SEDE';
    public ?string $invoicing_method = null; // FEPC, FESF, SF
    
    // Delete modal
    public $showDeleteModal = false;
    public $seriesToDelete = null;

    protected $rules = [
        'document_type' => 'required|in:invoice,proforma,receipt,credit_note,debit_note,pos,purchase,advance,transport',
        'series_code' => 'required|max:10',
        'name' => 'required|max:100',
        'prefix' => 'required|max:10',
        'next_number' => 'required|integer|min:1',
        'number_padding' => 'required|integer|min:1|max:10',
        'series_year' => 'nullable|integer|min:2024|max:2099',
        'establishment_number' => 'nullable|string|max:200',
        'invoicing_method' => 'nullable|in:FEPC,FESF,SF',
    ];

    public function openCreateModal()
    {
        $this->reset([
            'seriesId', 'document_type', 'series_code', 'name', 'prefix',
            'include_year', 'next_number', 'number_padding', 'is_default',
            'is_active', 'reset_yearly', 'description',
            'series_year', 'establishment_number', 'invoicing_method',
        ]);
        $this->isEdit = false;
        $this->document_type = 'invoice';
        $this->prefix = 'FT';
        $this->include_year = true;
        $this->next_number = 1;
        $this->number_padding = 6;
        $this->is_active = true;
        $this->reset_yearly = true;
        $this->series_year = (int) now()->year;
        $this->establishment_number = 'SEDE';
        $this->invoicing_method = InvoicingSeries::INVOICING_FEPC;
        $this->showModal = true;
    }

    public function editSeries($id)
    {
        $series = InvoicingSeries::forTenant(activeTenantId())->findOrFail($id);
        
        $this->seriesId = $series->id;
        $this->document_type = $series->document_type;
        $this->series_code = $series->series_code;
        $this->name = $series->name;
        $this->prefix = $series->prefix;
        $this->include_year = $series->include_year;
        $this->next_number = $series->next_number;
        $this->number_padding = $series->number_padding;
        $this->is_default = $series->is_default;
        $this->is_active = $series->is_active;
        $this->reset_yearly = $series->reset_yearly;
        $this->description = $series->description;
        $this->series_year = $series->series_year ?? (int) now()->year;
        $this->establishment_number = $series->establishment_number ?? 'SEDE';
        $this->invoicing_method = $series->invoicing_method ?? InvoicingSeries::INVOICING_FEPC;
        
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        // DS.120 §4.5: validar janela 15-Dez para series_year
        if ($this->series_year) {
            try {
                $settings = \App\Models\Invoicing\InvoicingSettings::where('tenant_id', activeTenantId())->first()
                    ?: new \App\Models\Invoicing\InvoicingSettings();
                (new \App\Services\AGT\SeriesService($settings))
                    ->validateSeriesYearWindow((int) $this->series_year);
            } catch (\InvalidArgumentException $e) {
                $this->addError('series_year', $e->getMessage());
                return;
            }
        }

        try {
            // Se marcar como padrão, desmarcar outras séries do mesmo tipo
            if ($this->is_default) {
                $defaultDocumentType = $this->document_type;
                if ($this->isEdit) {
                    $existingSeries = InvoicingSeries::forTenant(activeTenantId())->findOrFail($this->seriesId);
                    if ($existingSeries->isAGTRegistered()) {
                        $defaultDocumentType = $existingSeries->document_type;
                    }
                }
                InvoicingSeries::where('tenant_id', activeTenantId())
                    ->where('document_type', $defaultDocumentType)
                    ->update(['is_default' => false]);
            }

            if ($this->isEdit) {
                $series = InvoicingSeries::forTenant(activeTenantId())->findOrFail($this->seriesId);
                $data = [
                    'document_type' => $this->document_type,
                    'series_code' => $this->series_code,
                    'name' => $this->name,
                    'prefix' => $this->prefix,
                    'include_year' => $this->include_year,
                    'next_number' => $this->next_number,
                    'number_padding' => $this->number_padding,
                    'is_default' => $this->is_default,
                    'is_active' => $this->is_active,
                    'reset_yearly' => $this->reset_yearly,
                    'description' => $this->description,
                    'current_year' => now()->year,
                    'series_year' => $this->series_year,
                    'establishment_number' => $this->establishment_number,
                    'invoicing_method' => $this->invoicing_method,
                ];

                if ($series->isAGTRegistered()) {
                    $data = [
                        'name' => $this->name,
                        'description' => $this->description,
                        'is_default' => $this->is_default,
                    ];
                }

                $series->update($data);
                
                $message = $series->isAGTRegistered()
                    ? 'Série registada: apenas nome, descrição e preferência padrão foram actualizados.'
                    : 'Série atualizada com sucesso!';
            } else {
                InvoicingSeries::create([
                    'tenant_id' => activeTenantId(),
                    'document_type' => $this->document_type,
                    'series_code' => $this->series_code,
                    'name' => $this->name,
                    'prefix' => $this->prefix,
                    'include_year' => $this->include_year,
                    'next_number' => $this->next_number,
                    'number_padding' => $this->number_padding,
                    'is_default' => $this->is_default,
                    'is_active' => $this->is_active,
                    'reset_yearly' => $this->reset_yearly,
                    'description' => $this->description,
                    'current_year' => now()->year,
                    'series_year' => $this->series_year,
                    'establishment_number' => $this->establishment_number,
                    'invoicing_method' => $this->invoicing_method,
                ]);
                
                $message = 'Série criada com sucesso!';
            }

            $this->showModal = false;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro: :detalhe', ['detalhe' => $e->getMessage()])]);
        }
    }

    public function confirmDelete($id)
    {
        $this->seriesToDelete = $id;
        $this->showDeleteModal = true;
    }

    public function deleteSeries()
    {
        try {
            $series = InvoicingSeries::forTenant(activeTenantId())->findOrFail($this->seriesToDelete);
            if ($series->isAGTRegistered()) {
                $this->showDeleteModal = false;
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => __('Uma série registada na AGT não pode ser eliminada. Encerre-a pelo fluxo fiscal apropriado.'),
                ]);
                return;
            }
            $series->delete();
            
            $this->showDeleteModal = false;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Série eliminada com sucesso!')
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao eliminar série: :detalhe', ['detalhe' => $e->getMessage()])]);
        }
    }

    public function render()
    {
        $query = InvoicingSeries::forTenant(activeTenantId())
            ->orderBy('document_type')
            ->orderBy('is_default', 'desc')
            ->orderBy('series_code');

        if ($this->filterType) {
            $query->where('document_type', $this->filterType);
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('series_code', 'like', '%' . $this->search . '%');
            });
        }

        $series = $query->paginate(15);

        return view('livewire.invoicing.series-management', [
            'series' => $series,
        ]);
    }
}
