<?php

namespace App\Livewire\Workshop;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Product;
use App\Models\Invoicing\ProductCategory;

#[Layout('layouts.app')]
class PartManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $categoryFilter = '';
    public $typeFilter = 'product'; // Apenas produtos físicos (peças)
    
    // Modals
    public $showModal = false;
    public $showViewModal = false;
    public $editMode = false;
    public $productId;
    public $viewingProduct;
    
    // Form Fields
    public $name = '';
    public $sku = '';
    public $description = '';
    public $category_id = '';
    public $price = 0;
    public $cost = 0;
    public $stock = 0;
    public $min_stock = 0;
    public $track_inventory = true;
    public $is_active = true;
    public $barcode = '';
    public $supplier = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'sku' => 'required|string|max:100',
        'price' => 'required|numeric|min:0',
        'cost' => 'nullable|numeric|min:0',
        'category_id' => 'nullable|exists:invoicing_product_categories,id',
        'description' => 'nullable|string',
        'stock' => 'nullable|numeric|min:0',
        'min_stock' => 'nullable|numeric|min:0',
    ];
    
    protected $messages = [
        'name.required' => 'O nome da peça é obrigatório.',
        'sku.required' => 'O código SKU é obrigatório.',
        'price.required' => 'O preço é obrigatório.',
        'price.numeric' => 'O preço deve ser um número.',
        'category_id.exists' => 'Categoria inválida.',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $product = Product::where('tenant_id', auth()->user()->activeTenantId())
            ->findOrFail($id);
        
        $this->productId = $product->id;
        $this->name = $product->name;
        $this->sku = $product->sku;
        $this->description = $product->description;
        $this->category_id = $product->category_id;
        $this->price = $product->price;
        $this->cost = $product->cost;
        $this->stock = $product->stock;
        $this->min_stock = $product->min_stock;
        $this->track_inventory = $product->track_inventory;
        $this->is_active = $product->is_active;
        $this->barcode = $product->barcode;
        $this->supplier = $product->supplier;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => auth()->user()->activeTenantId(),
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'category_id' => $this->category_id ?: null,
            'type' => 'product', // Sempre produto físico (peça)
            'price' => $this->price,
            'cost' => $this->cost,
            'stock' => $this->stock,
            'min_stock' => $this->min_stock,
            'track_inventory' => $this->track_inventory,
            'is_active' => $this->is_active,
            'barcode' => $this->barcode,
            'supplier' => $this->supplier,
        ];

        if ($this->editMode) {
            $product = Product::where('tenant_id', auth()->user()->activeTenantId())
                ->findOrFail($this->productId);
            $product->update($data);
            $this->dispatch('success', message: 'Peça atualizada com sucesso!');
        } else {
            Product::create($data);
            $this->dispatch('success', message: 'Peça criada com sucesso!');
        }

        $this->closeModal();
    }

    public function view($id)
    {
        $this->viewingProduct = Product::where('tenant_id', auth()->user()->activeTenantId())
            ->with('category')
            ->findOrFail($id);
        $this->showViewModal = true;
    }
    
    public function delete($id)
    {
        $product = Product::where('tenant_id', auth()->user()->activeTenantId())
            ->findOrFail($id);
        $product->delete();
        
        $this->dispatch('success', message: 'Peça removida com sucesso!');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }
    
    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingProduct = null;
    }

    private function resetForm()
    {
        $this->productId = null;
        $this->name = '';
        $this->sku = '';
        $this->description = '';
        $this->category_id = '';
        $this->price = 0;
        $this->cost = 0;
        $this->stock = 0;
        $this->min_stock = 0;
        $this->track_inventory = true;
        $this->is_active = true;
        $this->barcode = '';
        $this->supplier = '';
    }

    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();
        
        $products = Product::where('tenant_id', $tenantId)
            ->where('type', 'product') // Apenas produtos físicos (peças)
            ->when($this->search, function($query) {
                $query->where(function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('sku', 'like', '%' . $this->search . '%')
                      ->orWhere('barcode', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->categoryFilter, function($query) {
                $query->where('category_id', $this->categoryFilter);
            })
            ->orderBy('name')
            ->paginate(15);
        
        $categories = ProductCategory::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        return view('livewire.workshop.parts.parts', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }
}
