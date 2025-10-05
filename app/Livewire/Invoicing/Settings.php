<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\Warehouse;
use App\Models\Invoicing\Tax;
use App\Models\Client;
use App\Models\Supplier;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Configurações de Faturação')]
class Settings extends Component
{
    public $settings;
    
    // Padrões
    public $default_warehouse_id;
    public $default_client_id;
    public $default_supplier_id;
    public $default_tax_id;
    public $default_currency = 'AOA';
    public $default_exchange_rate = 1.0000;
    public $default_payment_method = 'dinheiro';
    
    // Séries (deprecated - agora usa invoicing_series table)
    public $proforma_series = 'PRF';
    public $invoice_series = 'FT';
    public $receipt_series = 'RC';
    
    // Numeração (deprecated - agora usa invoicing_series table)
    public $proforma_next_number = 1;
    public $invoice_next_number = 1;
    public $receipt_next_number = 1;
    
    // Impostos
    public $default_tax_rate = 14.00;
    public $default_irt_rate = 6.50;
    public $apply_irt_services = true;
    
    // Descontos
    public $allow_line_discounts = true;
    public $allow_commercial_discount = true;
    public $allow_financial_discount = true;
    public $max_discount_percent = 100.00;
    
    // Validade
    public $proforma_validity_days = 30;
    public $invoice_due_days = 30;
    
    // Impressão
    public $auto_print_after_save = false;
    public $show_company_logo = true;
    public $invoice_footer_text;
    
    // SAFT
    public $saft_software_cert;
    public $saft_product_id;
    public $saft_version = '1.0.0';
    
    // Observações
    public $default_notes;
    public $default_terms;
    
    // Gestão de Séries
    public $showSeriesModal = false;
    public $editingSeriesId = null;
    public $seriesDocumentType = null;
    public $seriesCode = 'A';
    public $seriesName = '';
    public $seriesPrefix = '';
    public $seriesDescription = '';
    
    public function mount()
    {
        $this->settings = InvoicingSettings::forTenant(activeTenantId());
        
        // Carregar valores
        foreach ($this->settings->toArray() as $key => $value) {
            if (property_exists($this, $key) && $key !== 'settings') {
                $this->$key = $value;
            }
        }
    }
    
    public function save()
    {
        $this->validate([
            'default_currency' => 'required|in:AOA,USD,EUR',
            'default_exchange_rate' => 'required|numeric|min:0',
            'proforma_series' => 'required|max:10',
            'invoice_series' => 'required|max:10',
            'receipt_series' => 'required|max:10',
            'default_tax_rate' => 'required|numeric|min:0|max:100',
            'default_irt_rate' => 'required|numeric|min:0|max:100',
            'max_discount_percent' => 'required|numeric|min:0|max:100',
            'proforma_validity_days' => 'required|integer|min:1',
            'invoice_due_days' => 'required|integer|min:1',
        ]);
        
        $this->settings->update([
            'default_warehouse_id' => $this->default_warehouse_id,
            'default_client_id' => $this->default_client_id,
            'default_supplier_id' => $this->default_supplier_id,
            'default_tax_id' => $this->default_tax_id,
            'default_currency' => $this->default_currency,
            'default_exchange_rate' => $this->default_exchange_rate,
            'default_payment_method' => $this->default_payment_method,
            'proforma_series' => $this->proforma_series,
            'invoice_series' => $this->invoice_series,
            'receipt_series' => $this->receipt_series,
            'proforma_next_number' => $this->proforma_next_number,
            'invoice_next_number' => $this->invoice_next_number,
            'receipt_next_number' => $this->receipt_next_number,
            'default_tax_rate' => $this->default_tax_rate,
            'default_irt_rate' => $this->default_irt_rate,
            'apply_irt_services' => $this->apply_irt_services,
            'allow_line_discounts' => $this->allow_line_discounts,
            'allow_commercial_discount' => $this->allow_commercial_discount,
            'allow_financial_discount' => $this->allow_financial_discount,
            'max_discount_percent' => $this->max_discount_percent,
            'proforma_validity_days' => $this->proforma_validity_days,
            'invoice_due_days' => $this->invoice_due_days,
            'auto_print_after_save' => $this->auto_print_after_save,
            'show_company_logo' => $this->show_company_logo,
            'invoice_footer_text' => $this->invoice_footer_text,
            'saft_software_cert' => $this->saft_software_cert,
            'saft_product_id' => $this->saft_product_id,
            'saft_version' => $this->saft_version,
            'default_notes' => $this->default_notes,
            'default_terms' => $this->default_terms,
        ]);
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Configurações salvas com sucesso!'
        ]);
    }
    
    // Gestão de Séries
    public function openNewSeriesModal($documentType, $prefix)
    {
        $this->reset(['editingSeriesId', 'seriesCode', 'seriesName', 'seriesDescription']);
        $this->seriesDocumentType = $documentType;
        $this->seriesPrefix = $prefix;
        $this->showSeriesModal = true;
    }
    
    public function editSeries($seriesId)
    {
        $series = InvoicingSeries::find($seriesId);
        
        if ($series && $series->tenant_id == activeTenantId()) {
            $this->editingSeriesId = $series->id;
            $this->seriesDocumentType = $series->document_type;
            $this->seriesCode = $series->series_code;
            $this->seriesName = $series->name;
            $this->seriesPrefix = $series->prefix;
            $this->seriesDescription = $series->description;
            $this->showSeriesModal = true;
        }
    }
    
    public function saveSeries()
    {
        $this->validate([
            'seriesCode' => 'required|max:10',
            'seriesName' => 'nullable|max:100',
            'seriesDescription' => 'nullable|max:500',
        ]);
        
        if ($this->editingSeriesId) {
            // Editar série existente
            $series = InvoicingSeries::find($this->editingSeriesId);
            $series->update([
                'series_code' => $this->seriesCode,
                'name' => $this->seriesName ?: "Série {$this->seriesPrefix} {$this->seriesCode}",
                'description' => $this->seriesDescription,
            ]);
            
            $message = 'Série atualizada com sucesso!';
        } else {
            // Criar nova série
            InvoicingSeries::create([
                'tenant_id' => activeTenantId(),
                'document_type' => $this->seriesDocumentType,
                'series_code' => $this->seriesCode,
                'name' => $this->seriesName ?: "Série {$this->seriesPrefix} {$this->seriesCode}",
                'prefix' => $this->seriesPrefix,
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                'is_default' => false,
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => $this->seriesDescription,
            ]);
            
            $message = 'Nova série criada com sucesso!';
        }
        
        $this->showSeriesModal = false;
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message
        ]);
    }
    
    public function setDefaultSeries($seriesId)
    {
        $series = InvoicingSeries::find($seriesId);
        
        if ($series && $series->tenant_id == activeTenantId()) {
            // Remover padrão de todas as séries do mesmo tipo
            InvoicingSeries::where('tenant_id', activeTenantId())
                ->where('document_type', $series->document_type)
                ->update(['is_default' => false]);
            
            // Definir nova série como padrão
            $series->update(['is_default' => true]);
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Série padrão definida com sucesso!'
            ]);
        }
    }
    
    public function render()
    {
        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();
            
        $clients = Client::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
            
        $suppliers = Supplier::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        $taxes = Tax::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
        
        return view('livewire.invoicing.settings', [
            'warehouses' => $warehouses,
            'clients' => $clients,
            'suppliers' => $suppliers,
            'taxes' => $taxes,
        ]);
    }
}

