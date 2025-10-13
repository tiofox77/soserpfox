<?php

namespace App\Livewire\SuperAdmin;

use Livewire\Component;
use App\Models\SoftwareSetting;
use Illuminate\Support\Facades\Cache;

class SoftwareSettings extends Component
{
    public $activeModule = 'invoicing';
    
    // Configurações do módulo de faturação
    public $block_delete_sales_invoice = false;
    public $block_delete_proforma = false;
    public $block_delete_receipt = false;
    public $block_delete_credit_note = false;
    public $block_delete_invoice_receipt = false;
    public $block_delete_pos_invoice = false;
    
    public function mount()
    {
        $this->loadSettings();
    }
    
    public function loadSettings()
    {
        if ($this->activeModule === 'invoicing') {
            $this->block_delete_sales_invoice = SoftwareSetting::get('invoicing', 'block_delete_sales_invoice', false);
            $this->block_delete_proforma = SoftwareSetting::get('invoicing', 'block_delete_proforma', false);
            $this->block_delete_receipt = SoftwareSetting::get('invoicing', 'block_delete_receipt', false);
            $this->block_delete_credit_note = SoftwareSetting::get('invoicing', 'block_delete_credit_note', false);
            $this->block_delete_invoice_receipt = SoftwareSetting::get('invoicing', 'block_delete_invoice_receipt', false);
            $this->block_delete_pos_invoice = SoftwareSetting::get('invoicing', 'block_delete_pos_invoice', false);
        }
    }
    
    public function switchModule($module)
    {
        $this->activeModule = $module;
        $this->loadSettings();
    }
    
    public function saveSettings()
    {
        try {
            if ($this->activeModule === 'invoicing') {
                SoftwareSetting::set('invoicing', 'block_delete_sales_invoice', $this->block_delete_sales_invoice);
                SoftwareSetting::set('invoicing', 'block_delete_proforma', $this->block_delete_proforma);
                SoftwareSetting::set('invoicing', 'block_delete_receipt', $this->block_delete_receipt);
                SoftwareSetting::set('invoicing', 'block_delete_credit_note', $this->block_delete_credit_note);
                SoftwareSetting::set('invoicing', 'block_delete_invoice_receipt', $this->block_delete_invoice_receipt);
                SoftwareSetting::set('invoicing', 'block_delete_pos_invoice', $this->block_delete_pos_invoice);
            }
            
            // Limpar cache
            SoftwareSetting::clearCache();
            
            session()->flash('message', 'Configurações salvas com sucesso!');
            session()->flash('message-type', 'success');
            
        } catch (\Exception $e) {
            session()->flash('message', 'Erro ao salvar configurações: ' . $e->getMessage());
            session()->flash('message-type', 'error');
        }
    }
    
    public function resetSettings()
    {
        if ($this->activeModule === 'invoicing') {
            $this->block_delete_sales_invoice = false;
            $this->block_delete_proforma = false;
            $this->block_delete_receipt = false;
            $this->block_delete_credit_note = false;
            $this->block_delete_invoice_receipt = false;
            $this->block_delete_pos_invoice = false;
        }
        
        session()->flash('message', 'Configurações resetadas. Clique em "Salvar" para aplicar.');
        session()->flash('message-type', 'info');
    }
    
    public function render()
    {
        return view('livewire.super-admin.software-settings')
            ->layout('layouts.app', ['title' => 'Configurações do Software']);
    }
}
