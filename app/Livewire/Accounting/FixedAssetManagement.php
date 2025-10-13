<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class FixedAssetManagement extends Component
{
    public $showModal = false;
    public $showDepreciationModal = false;
    public $filterStatus = '';
    
    // Form fields
    public $assetId = null;
    public $code = '';
    public $name = '';
    public $categoryId = null;
    public $acquisitionDate = null;
    public $acquisitionValue = 0;
    public $residualValue = 0;
    public $usefulLife = 5;
    public $depreciationMethod = 'linear';
    public $location = '';
    public $serialNumber = '';
    public $description = '';
    
    public function calculateDepreciations()
    {
        session()->flash('success', 'Funcionalidade de cálculo de depreciações será implementada em breve!');
    }
    
    public function save()
    {
        $this->validate([
            'code' => 'required|max:50',
            'name' => 'required',
            'acquisitionDate' => 'required|date',
            'acquisitionValue' => 'required|numeric|min:0',
            'usefulLife' => 'required|integer|min:1',
        ]);
        
        session()->flash('success', 'Ativo salvo com sucesso! (Funcionalidade completa será implementada em breve)');
        $this->showModal = false;
        $this->reset(['assetId', 'code', 'name', 'categoryId', 'acquisitionDate', 'acquisitionValue', 'residualValue', 'usefulLife', 'location', 'serialNumber', 'description']);
    }
    
    public function edit($id)
    {
        $this->assetId = $id;
        $this->showModal = true;
    }
    
    public function viewDepreciations($id)
    {
        $this->assetId = $id;
        $this->showDepreciationModal = true;
    }
    
    public function render()
    {
        $tenantId = auth()->user()->tenant_id;
        
        // Mock paginator vazio
        $assets = new \Illuminate\Pagination\LengthAwarePaginator(
            [],
            0,
            20,
            1,
            ['path' => request()->url()]
        );
        
        $totalAssets = 0;
        $totalValue = 0;
        $totalDepreciation = 0;
        $totalBookValue = 0;
        
        return view('livewire.accounting.fixed-assets.fixed-assets', [
            'assets' => $assets,
            'totalAssets' => $totalAssets,
            'totalValue' => $totalValue,
            'totalDepreciation' => $totalDepreciation,
            'totalBookValue' => $totalBookValue,
        ]);
    }
}
