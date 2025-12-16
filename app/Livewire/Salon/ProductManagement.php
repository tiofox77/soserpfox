<?php

namespace App\Livewire\Salon;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Salon\Product;
use App\Models\Category;

#[Layout('layouts.app')]
#[Title('Produtos - Salão de Beleza')]
class ProductManagement extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $perPage = 15;
    public $categoryFilter = '';
    public $stockFilter = '';
    
    public $showModal = false;
    public $showViewModal = false;
    public $showDeleteModal = false;
    public $editingId = null;
    public $viewingProduct = null;
    public $deletingId = null;
    public $deletingName = '';

    // Stats
    public $totalProducts = 0;
    public $totalActive = 0;
    public $totalLowStock = 0;
    public $totalValue = 0;

    // Form fields
    public $name, $code, $barcode, $description;
    public $category_id, $price, $cost;
    public $manage_stock = true, $stock_quantity = 0, $minimum_stock = 5;
    public $is_active = true;
    public $featured_image;

    protected function rules()
    {
        return [
            'name' => 'required|string|min:2|max:255',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:invoicing_product_categories,id',
            'stock_quantity' => 'nullable|integer|min:0',
            'minimum_stock' => 'nullable|integer|min:0',
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openModal($id = null)
    {
        $this->resetForm();
        if ($id) {
            $product = Product::find($id);
            $this->editingId = $id;
            $this->name = $product->name;
            $this->code = $product->code;
            $this->barcode = $product->barcode;
            $this->description = $product->description;
            $this->category_id = $product->category_id;
            $this->price = $product->price;
            $this->cost = $product->cost;
            $this->manage_stock = $product->manage_stock;
            $this->stock_quantity = $product->stock_quantity;
            $this->minimum_stock = $product->minimum_stock;
            $this->is_active = $product->is_active;
        }
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function view($id)
    {
        $this->viewingProduct = Product::with('category')->find($id);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingProduct = null;
    }

    public function openDeleteModal($id)
    {
        $product = Product::find($id);
        $this->deletingId = $id;
        $this->deletingName = $product->name;
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
    }

    public function confirmDelete()
    {
        Product::find($this->deletingId)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Produto eliminado!']);
        $this->cancelDelete();
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'name' => $this->name,
            'code' => $this->code,
            'barcode' => $this->barcode,
            'category_id' => $this->category_id,
            'price' => $this->price,
            'cost' => $this->cost ?? 0,
            'manage_stock' => $this->manage_stock,
            'stock_quantity' => $this->stock_quantity ?? 0,
            'minimum_stock' => $this->minimum_stock ?? 5,
            'is_active' => $this->is_active,
            'type' => 'produto',
        ];

        if ($this->editingId) {
            $product = Product::find($this->editingId);
            $product->update($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Produto atualizado!']);
        } else {
            Product::create($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Produto criado!']);
        }

        $this->closeModal();
    }

    public function adjustStock($id, $amount)
    {
        $product = Product::find($id);
        $product->increment('stock_quantity', $amount);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Stock ajustado!']);
    }

    public function clearFilters()
    {
        $this->reset(['search', 'categoryFilter', 'stockFilter']);
    }

    private function resetForm()
    {
        $this->reset([
            'editingId', 'name', 'code', 'barcode', 'description',
            'category_id', 'price', 'cost', 'stock_quantity', 'minimum_stock', 'featured_image'
        ]);
        $this->manage_stock = true;
        $this->is_active = true;
    }

    public function render()
    {
        // Stats
        $this->totalProducts = Product::forTenant()->count();
        $this->totalActive = Product::forTenant()->active()->count();
        $this->totalLowStock = Product::forTenant()->lowStock()->count();
        $this->totalValue = Product::forTenant()->sum(\DB::raw('price * stock_quantity'));

        $products = Product::forTenant()
            ->with('category')
            ->when($this->search, fn($q) => $q->where(function($sub) {
                $sub->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                    ->orWhere('barcode', 'like', "%{$this->search}%");
            }))
            ->when($this->categoryFilter, fn($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->stockFilter === 'low', fn($q) => $q->lowStock())
            ->when($this->stockFilter === 'out', fn($q) => $q->where('stock_quantity', 0))
            ->orderBy('name')
            ->paginate($this->perPage);

        $categories = Category::where('tenant_id', activeTenantId())->orderBy('name')->get();

        return view('livewire.salon.products.products', compact('products', 'categories'));
    }
}
