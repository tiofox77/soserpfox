<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Accounting\CostCenter;

#[Layout('layouts.app')]
class CostCenterManagement extends Component
{
    public $showModal = false;
    public $centerId = null;
    public $code = '';
    public $name = '';
    public $description = '';
    public $type = 'cost';
    public $parentId = null;
    
    public function edit($id)
    {
        $center = CostCenter::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);
        $this->centerId = $center->id;
        $this->code = $center->code;
        $this->name = $center->name;
        $this->description = $center->description;
        $this->type = $center->type;
        $this->parentId = $center->parent_id;
        $this->showModal = true;
    }
    
    public function save()
    {
        $this->validate([
            'code' => 'required|max:50',
            'name' => 'required',
            'type' => 'required|in:revenue,cost,support',
        ]);
        
        try {
            $data = [
                'tenant_id' => auth()->user()->tenant_id,
                'code' => $this->code,
                'name' => $this->name,
                'description' => $this->description,
                'type' => $this->type,
                'parent_id' => $this->parentId,
                'is_active' => true,
            ];
            
            if ($this->centerId) {
                $center = CostCenter::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->centerId);
                $center->update($data);
                session()->flash('success', 'Centro de custo atualizado com sucesso!');
            } else {
                CostCenter::create($data);
                session()->flash('success', 'Centro de custo criado com sucesso!');
            }
            
            $this->reset(['centerId', 'code', 'name', 'description', 'type', 'parentId']);
            $this->showModal = false;
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro: ' . $e->getMessage());
        }
    }
    
    public function render()
    {
        $tenantId = auth()->user()->tenant_id;
        
        $costCenters = CostCenter::with('children')
            ->where('tenant_id', $tenantId)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
        
        $parentCenters = CostCenter::where('tenant_id', $tenantId)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
        
        return view('livewire.accounting.cost-centers.cost-centers', [
            'costCenters' => $costCenters,
            'parentCenters' => $parentCenters,
        ]);
    }
}
