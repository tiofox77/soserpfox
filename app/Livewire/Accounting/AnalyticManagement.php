<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Accounting\AnalyticDimension;
use App\Models\Accounting\AnalyticTag;

#[Layout('layouts.app')]
class AnalyticManagement extends Component
{
    public $showDimensionModal = false;
    public $showTagModal = false;
    public $selectedDimensionId = null;
    
    // Dimension fields
    public $dimCode = '';
    public $dimName = '';
    public $isMandatory = false;
    
    // Tag fields
    public $tagCode = '';
    public $tagName = '';
    public $tagDescription = '';
    
    public function selectDimension($id)
    {
        $this->selectedDimensionId = $id;
    }
    
    public function saveDimension()
    {
        $this->validate([
            'dimCode' => 'required|max:50',
            'dimName' => 'required',
        ]);
        
        try {
            AnalyticDimension::create([
                'tenant_id' => auth()->user()->tenant_id,
                'code' => $this->dimCode,
                'name' => $this->dimName,
                'is_mandatory' => $this->isMandatory,
            ]);
            
            session()->flash('success', 'Dimensão criada com sucesso!');
            $this->reset(['dimCode', 'dimName', 'isMandatory']);
            $this->showDimensionModal = false;
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro: ' . $e->getMessage());
        }
    }
    
    public function saveTag()
    {
        $this->validate([
            'tagCode' => 'required|max:50',
            'tagName' => 'required',
        ]);
        
        try {
            AnalyticTag::create([
                'dimension_id' => $this->selectedDimensionId,
                'code' => $this->tagCode,
                'name' => $this->tagName,
                'description' => $this->tagDescription,
                'is_active' => true,
            ]);
            
            session()->flash('success', 'Tag criada com sucesso!');
            $this->reset(['tagCode', 'tagName', 'tagDescription']);
            $this->showTagModal = false;
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro: ' . $e->getMessage());
        }
    }
    
    public function editTag($id)
    {
        $tag = AnalyticTag::findOrFail($id);
        $this->tagCode = $tag->code;
        $this->tagName = $tag->name;
        $this->tagDescription = $tag->description;
        $this->showTagModal = true;
    }
    
    public function render()
    {
        $tenantId = auth()->user()->tenant_id;
        
        $dimensions = AnalyticDimension::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
        
        $tags = [];
        if ($this->selectedDimensionId) {
            $tags = AnalyticTag::where('dimension_id', $this->selectedDimensionId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }
        
        return view('livewire.accounting.analytics.analytics', [
            'dimensions' => $dimensions,
            'tags' => $tags,
        ]);
    }
}
