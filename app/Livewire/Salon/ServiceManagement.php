<?php

namespace App\Livewire\Salon;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Salon\Service;
use App\Models\Salon\ServiceCategory;

#[Layout('layouts.app')]
#[Title('Serviços - Salão de Beleza')]
class ServiceManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $categoryFilter = '';
    public $perPage = 15;
    public $showModal = false;
    public $showCategoryModal = false;
    public $showDeleteModal = false;
    public $showViewModal = false;
    public $editingId = null;
    public $editingCategoryId = null;
    public $deletingId = null;
    public $deletingName = '';
    public $viewingService = null;

    // Stats
    public $totalServices = 0;
    public $totalActive = 0;
    public $totalCategories = 0;

    // Service form
    public $category_id, $name, $code, $description, $duration = 30, $price = 0;
    public $cost = 0, $commission_percent = 0, $is_active = true, $online_booking = true;

    // Category form
    public $cat_name, $cat_icon, $cat_color = '#6366f1', $cat_description, $cat_order = 0;

    protected function rules()
    {
        return [
            'category_id' => 'required|exists:salon_service_categories,id',
            'name' => 'required|min:2',
            'duration' => 'required|integer|min:5',
            'price' => 'required|numeric|min:0',
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'categoryFilter']);
        $this->resetPage();
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function view($id)
    {
        $this->viewingService = Service::with('professionals')->find($id);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingService = null;
    }

    public function openDeleteModal($id)
    {
        $service = Service::find($id);
        $this->deletingId = $id;
        $this->deletingName = $service->name;
        $this->showDeleteModal = true;
    }

    public function confirmDelete()
    {
        Service::find($this->deletingId)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Serviço removido!']);
        $this->cancelDelete();
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
    }

    public function openModal($id = null)
    {
        $this->resetForm();
        if ($id) {
            $service = Service::find($id);
            $this->editingId = $id;
            $this->category_id = $service->category_id;
            $this->name = $service->name;
            $this->code = $service->code;
            $this->description = $service->text_description; // Usar text_description em vez de description (JSON)
            $this->duration = $service->duration;
            $this->price = $service->price;
            $this->cost = $service->cost;
            $this->commission_percent = $service->commission_percent;
            $this->is_active = $service->is_active;
            $this->online_booking = $service->online_booking;
        }
        $this->showModal = true;
    }

    public function openCategoryModal($id = null)
    {
        $this->resetCategoryForm();
        if ($id) {
            $category = ServiceCategory::find($id);
            $this->editingCategoryId = $id;
            $this->cat_name = $category->name;
            $this->cat_icon = $category->icon;
            $this->cat_color = $category->color;
            $this->cat_description = $category->description;
            $this->cat_order = $category->order;
        }
        $this->showCategoryModal = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->editingId) {
            $service = Service::find($this->editingId);
            $service->update([
                'name' => $this->name,
                'price' => $this->price,
                'cost' => $this->cost,
                'is_active' => $this->is_active,
            ]);
            $service->updateSalonData([
                'category_id' => $this->category_id,
                'duration' => $this->duration,
                'commission_percent' => $this->commission_percent,
                'online_booking' => $this->online_booking,
            ]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Serviço atualizado!']);
        } else {
            Service::createService([
                'name' => $this->name,
                'price' => $this->price,
                'cost' => $this->cost,
                'is_active' => $this->is_active,
                'category_id' => $this->category_id,
                'duration' => $this->duration,
                'commission_percent' => $this->commission_percent,
                'online_booking' => $this->online_booking,
                'text_description' => $this->description,
            ]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Serviço criado!']);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function saveCategory()
    {
        $this->validate([
            'cat_name' => 'required|min:2',
        ]);

        $data = [
            'name' => $this->cat_name,
            'icon' => $this->cat_icon,
            'color' => $this->cat_color,
            'description' => $this->cat_description,
            'order' => $this->cat_order,
        ];

        if ($this->editingCategoryId) {
            ServiceCategory::find($this->editingCategoryId)->update($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Categoria atualizada!']);
        } else {
            ServiceCategory::create($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Categoria criada!']);
        }

        $this->showCategoryModal = false;
        $this->resetCategoryForm();
    }

    public function toggleStatus($id)
    {
        $service = Service::find($id);
        $service->update(['is_active' => !$service->is_active]);
    }

    public function delete($id)
    {
        Service::find($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Serviço removido!']);
    }

    public function deleteCategory($id)
    {
        $category = ServiceCategory::find($id);
        if ($category->services()->count() > 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Categoria tem serviços vinculados!']);
            return;
        }
        $category->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Categoria removida!']);
    }

    private function resetForm()
    {
        $this->reset(['editingId', 'category_id', 'name', 'code', 'description', 'cost', 'commission_percent']);
        $this->duration = 30;
        $this->price = 0;
        $this->is_active = true;
        $this->online_booking = true;
    }

    private function resetCategoryForm()
    {
        $this->reset(['editingCategoryId', 'cat_name', 'cat_icon', 'cat_description']);
        $this->cat_color = '#6366f1';
        $this->cat_order = 0;
    }

    public function render()
    {
        $categories = ServiceCategory::forTenant()->orderBy('order')->get();

        // Stats
        $this->totalServices = Service::forTenant()->count();
        $this->totalActive = Service::forTenant()->active()->count();
        $this->totalCategories = $categories->count();

        $services = Service::forTenant()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->categoryFilter, fn($q) => $q->forCategory($this->categoryFilter))
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.salon.services.services', compact('categories', 'services'));
    }
}
