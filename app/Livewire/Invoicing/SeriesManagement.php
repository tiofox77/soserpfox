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
    
    // Delete modal
    public $showDeleteModal = false;
    public $seriesToDelete = null;

    protected $rules = [
        'document_type' => 'required|in:invoice,proforma,receipt,credit_note,debit_note',
        'series_code' => 'required|max:10',
        'name' => 'required|max:100',
        'prefix' => 'required|max:10',
        'next_number' => 'required|integer|min:1',
        'number_padding' => 'required|integer|min:1|max:10',
    ];

    public function openCreateModal()
    {
        $this->reset([
            'seriesId', 'document_type', 'series_code', 'name', 'prefix',
            'include_year', 'next_number', 'number_padding', 'is_default',
            'is_active', 'reset_yearly', 'description'
        ]);
        $this->isEdit = false;
        $this->document_type = 'invoice';
        $this->prefix = 'FT';
        $this->include_year = true;
        $this->next_number = 1;
        $this->number_padding = 6;
        $this->is_active = true;
        $this->reset_yearly = true;
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
        
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        try {
            // Se marcar como padrão, desmarcar outras séries do mesmo tipo
            if ($this->is_default) {
                InvoicingSeries::where('tenant_id', activeTenantId())
                    ->where('document_type', $this->document_type)
                    ->update(['is_default' => false]);
            }

            if ($this->isEdit) {
                $series = InvoicingSeries::forTenant(activeTenantId())->findOrFail($this->seriesId);
                $series->update([
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
                ]);
                
                $message = 'Série atualizada com sucesso!';
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
                'message' => 'Erro: ' . $e->getMessage()
            ]);
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
            $series->delete();
            
            $this->showDeleteModal = false;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Série eliminada com sucesso!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao eliminar série: ' . $e->getMessage()
            ]);
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

