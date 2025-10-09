<?php

namespace App\Livewire\Events\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Client;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Gestão de Equipamentos')]
class EquipmentManager extends Component
{
    use WithPagination, WithFileUploads;

    // Propriedades
    public $viewMode = 'grid'; // 'grid' ou 'list'
    public $search = '';
    public $categoryFilter = '';
    public $statusFilter = [];
    public $locationFilter = '';
    
    // Modal
    public $showModal = false;
    public $editMode = false;
    public $equipmentId = null;
    
    // Formulário
    public $name = '';
    public $category = '';
    public $serial_number = '';
    public $location = '';
    public $description = '';
    public $status = 'disponivel';
    public $acquisition_date = '';
    public $purchase_price = '';
    public $current_value = '';
    public $image = null;
    
    // Empréstimo
    public $showBorrowModal = false;
    public $borrowEquipmentId = null;
    public $borrowed_to_client_id = '';
    public $borrow_date = '';
    public $return_due_date = '';
    public $rental_price_per_day = '';
    public $borrow_notes = '';
    
    // Manutenção
    public $showMaintenanceModal = false;
    public $maintenanceEquipmentId = null;
    public $maintenance_notes = '';
    public $next_maintenance_date = '';
    
    // Criar Categoria
    public $showCategoryModal = false;
    public $newCategoryName = '';
    public $newCategoryIcon = '📦';
    public $newCategoryColor = '#8b5cf6';

    public function mount()
    {
        $this->statusFilter = ['disponivel', 'reservado', 'em_uso'];
    }

    public function render()
    {
        $equipment = Equipment::where('tenant_id', activeTenantId())
            ->when($this->search, function($query) {
                $query->where(function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('serial_number', 'like', '%' . $this->search . '%')
                      ->orWhere('location', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->categoryFilter, fn($q) => $q->where('category_id', $this->categoryFilter))
            ->when(!empty($this->statusFilter), fn($q) => $q->whereIn('status', $this->statusFilter))
            ->when($this->locationFilter, fn($q) => $q->where('location', 'like', '%' . $this->locationFilter . '%'))
            ->with(['borrowedToClient', 'createdBy', 'category'])
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        $stats = $this->getStatistics();
        $categories = EquipmentCategory::where('tenant_id', activeTenantId())->active()->ordered()->get();
        $clients = Client::where('tenant_id', activeTenantId())->where('is_active', true)->get();
        $locations = Equipment::where('tenant_id', activeTenantId())
            ->whereNotNull('location')
            ->distinct()
            ->pluck('location');
        
        // Alertas
        $alerts = $this->getAlerts();

        return view('livewire.events.equipment.equipment-manager', compact('equipment', 'stats', 'categories', 'clients', 'locations', 'alerts'));
    }

    private function getStatistics()
    {
        $baseQuery = Equipment::where('tenant_id', activeTenantId());
        
        return [
            'total' => $baseQuery->count(),
            'disponivel' => (clone $baseQuery)->where('status', 'disponivel')->count(),
            'em_uso' => (clone $baseQuery)->where('status', 'em_uso')->count(),
            'emprestado' => (clone $baseQuery)->where('status', 'emprestado')->count(),
            'manutencao' => (clone $baseQuery)->where('status', 'manutencao')->count(),
            'avariado' => (clone $baseQuery)->where('status', 'avariado')->count(),
            'total_value' => (clone $baseQuery)->sum('current_value') ?? 0,
        ];
    }

    private function getAlerts()
    {
        $alerts = [];
        
        // Equipamentos atrasados
        $overdue = Equipment::where('tenant_id', activeTenantId())->overdue()->get();
        foreach ($overdue as $eq) {
            $alerts[] = [
                'type' => 'error',
                'icon' => '⏰',
                'message' => "{$eq->name} está {$eq->days_overdue} dias atrasado",
                'equipment_id' => $eq->id,
            ];
        }
        
        // Manutenção próxima
        $maintenance = Equipment::where('tenant_id', activeTenantId())->needsMaintenance()->get();
        foreach ($maintenance as $eq) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => '🔧',
                'message' => "{$eq->name} precisa de manutenção em breve",
                'equipment_id' => $eq->id,
            ];
        }
        
        return $alerts;
    }

    public function openModal()
    {
        $this->reset(['name', 'category', 'serial_number', 'location', 'description', 'status', 
                     'acquisition_date', 'purchase_price', 'current_value', 'image', 'equipmentId']);
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $equipment = Equipment::where('tenant_id', activeTenantId())->findOrFail($id);
        
        $this->equipmentId = $equipment->id;
        $this->name = $equipment->name;
        $this->category = $equipment->category;
        $this->serial_number = $equipment->serial_number;
        $this->location = $equipment->location;
        $this->description = $equipment->description;
        $this->status = $equipment->status;
        $this->acquisition_date = $equipment->acquisition_date?->format('Y-m-d');
        $this->purchase_price = $equipment->purchase_price;
        $this->current_value = $equipment->current_value;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'serial_number' => 'nullable|string|unique:equipment,serial_number,' . $this->equipmentId,
            'location' => 'nullable|string|max:255',
            'status' => 'required|in:disponivel,reservado,em_uso,avariado,manutencao,emprestado,descartado',
            'acquisition_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = [
            'tenant_id' => activeTenantId(),
            'name' => $this->name,
            'category_id' => $this->category,
            'serial_number' => $this->serial_number ?: null,
            'location' => $this->location ?: null,
            'description' => $this->description ?: null,
            'status' => $this->status,
            'acquisition_date' => $this->acquisition_date ?: null,
            'purchase_price' => $this->purchase_price ?: null,
            'current_value' => $this->current_value ?: null,
        ];

        if ($this->image) {
            $data['image_path'] = $this->image->store('equipment', 'public');
        }

        if ($this->editMode) {
            $equipment = Equipment::findOrFail($this->equipmentId);
            $equipment->update($data);
            $equipment->addToHistory('transferencia', ['notes' => 'Equipamento atualizado']);
            
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Equipamento atualizado com sucesso!']);
        } else {
            $data['created_by'] = auth()->id();
            Equipment::create($data);
            
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Equipamento criado com sucesso!']);
        }

        $this->closeModal();
        $this->reset(['name', 'category', 'serial_number', 'location', 'description', 'status', 
                     'acquisition_date', 'purchase_price', 'current_value', 'image', 'equipmentId']);
    }

    public function openBorrowModal($id)
    {
        $this->reset(['borrowed_to_client_id', 'borrow_date', 'return_due_date', 'rental_price_per_day', 'borrow_notes']);
        $this->borrowEquipmentId = $id;
        $this->borrow_date = now()->format('Y-m-d');
        $this->showBorrowModal = true;
    }

    public function saveBorrow()
    {
        $this->validate([
            'borrowed_to_client_id' => 'required|exists:clients,id',
            'borrow_date' => 'required|date',
            'return_due_date' => 'required|date|after:borrow_date',
            'rental_price_per_day' => 'nullable|numeric|min:0',
        ]);

        $equipment = Equipment::findOrFail($this->borrowEquipmentId);
        $equipment->update([
            'status' => 'emprestado',
            'borrowed_to_client_id' => $this->borrowed_to_client_id,
            'borrow_date' => $this->borrow_date,
            'return_due_date' => $this->return_due_date,
            'rental_price_per_day' => $this->rental_price_per_day ?: null,
            'actual_return_date' => null,
        ]);

        $equipment->addToHistory('emprestimo', [
            'client_id' => $this->borrowed_to_client_id,
            'start_datetime' => $this->borrow_date,
            'notes' => $this->borrow_notes ?: 'Equipamento emprestado'
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => '✅ Equipamento emprestado com sucesso!']);
        $this->showBorrowModal = false;
        $this->reset(['borrowed_to_client_id', 'borrow_date', 'return_due_date', 'rental_price_per_day', 'borrow_notes', 'borrowEquipmentId']);
    }

    public function returnEquipment($id)
    {
        $equipment = Equipment::findOrFail($id);
        $equipment->update([
            'status' => 'disponivel',
            'actual_return_date' => now(),
        ]);

        $equipment->addToHistory('devolucao', [
            'client_id' => $equipment->borrowed_to_client_id,
            'end_datetime' => now(),
            'notes' => 'Equipamento devolvido'
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => '✅ Equipamento devolvido com sucesso!']);
    }

    public function delete($id)
    {
        Equipment::where('tenant_id', activeTenantId())->findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Equipamento excluído!']);
    }

    /**
     * Abrir modal de criar categoria
     */
    public function openCategoryModal()
    {
        $this->reset(['newCategoryName', 'newCategoryIcon', 'newCategoryColor']);
        $this->newCategoryIcon = '📦';
        $this->newCategoryColor = '#8b5cf6';
        $this->showCategoryModal = true;
    }
    
    /**
     * Fechar modal de criar categoria
     */
    public function closeCategoryModal()
    {
        $this->showCategoryModal = false;
        $this->reset(['newCategoryName', 'newCategoryIcon', 'newCategoryColor']);
    }
    
    /**
     * Salvar nova categoria
     */
    public function saveCategory()
    {
        $this->validate([
            'newCategoryName' => 'required|string|max:100',
            'newCategoryIcon' => 'required|string|max:10',
            'newCategoryColor' => 'required|string|max:7',
        ], [
            'newCategoryName.required' => 'O nome da categoria é obrigatório',
        ]);
        
        $category = EquipmentCategory::create([
            'tenant_id' => activeTenantId(),
            'name' => $this->newCategoryName,
            'icon' => $this->newCategoryIcon,
            'color' => $this->newCategoryColor,
            'sort_order' => EquipmentCategory::where('tenant_id', activeTenantId())->max('sort_order') + 1,
            'is_active' => true,
        ]);
        
        $this->category = $category->id;
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Categoria criada: ' . $category->icon . ' ' . $category->name
        ]);
        
        $this->closeCategoryModal();
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->showBorrowModal = false;
        $this->showMaintenanceModal = false;
        $this->showCategoryModal = false;
    }

    public function switchView($mode)
    {
        $this->viewMode = $mode;
    }
}
