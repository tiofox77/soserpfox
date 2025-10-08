<?php

namespace App\Livewire\Invoicing;

use App\Models\{Product};
use App\Models\Invoicing\Tax;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Produtos')]
class Products extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $showModal = false;
    public $editingProductId = null;
    
    // View modal
    public $showViewModal = false;
    public $viewingProduct = null;
    
    // Delete confirmation
    public $showDeleteModal = false;
    public $deletingProductId = null;
    public $deletingProductName = '';
    
    // Filters
    public $typeFilter = '';
    public $stockFilter = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 15;
    
    // Form fields
    public $code, $sku, $barcode, $name, $description;
    public $price = 0, $cost = 0;
    public $unit = 'UN';
    public $type = 'produto';
    public $category_id = null;
    public $brand_id = null;
    public $supplier_id = null;
    public $manage_stock = false;
    public $stock_quantity = 0;
    public $stock_min = 0;
    public $stock_max = null;
    
    // Batch tracking fields
    public $track_batches = false;
    public $track_expiry = false;
    public $require_batch_on_purchase = false;
    public $require_batch_on_sale = false;
    
    // Tax fields
    public $tax_type = 'iva';
    public $tax_rate_id = null;
    public $exemption_reason = null;
    
    // Image fields
    public $featured_image; // Upload file
    public $currentFeaturedImage; // Existing image path
    public $gallery = []; // Upload files array
    public $currentGallery = []; // Existing gallery images

    protected function rules()
    {
        $rules = [
            'name' => 'required|min:3',
            'type' => 'required|in:produto,servico',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'featured_image' => 'nullable|image|max:2048',
            'gallery.*' => 'nullable|image|max:2048',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'unit' => 'required|string',
            'category_id' => 'required|exists:invoicing_categories,id',
            'brand_id' => 'nullable|exists:invoicing_brands,id',
            'supplier_id' => 'nullable|exists:invoicing_suppliers,id',
            'tax_type' => 'required|in:iva,isento',
            'tax_rate_id' => 'required_if:tax_type,iva|nullable|exists:invoicing_taxes,id',
            'exemption_reason' => 'required_if:tax_type,isento|nullable|string',
            'stock_min' => 'nullable|integer|min:0',
            'stock_max' => 'nullable|integer|min:0|gte:stock_min',
        ];

        // Validar código (único para criar e editar)
        if ($this->editingProductId) {
            $rules['code'] = 'required|string|max:50|unique:invoicing_products,code,' . $this->editingProductId;
        } else {
            $rules['code'] = 'required|string|max:50|unique:invoicing_products,code';
        }

        return $rules;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTypeFilter()
    {
        $this->resetPage();
    }

    public function updatingStockFilter()
    {
        $this->resetPage();
    }

    public function updatingDateFrom()
    {
        $this->resetPage();
    }

    public function updatingDateTo()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['typeFilter', 'stockFilter', 'dateFrom', 'dateTo', 'search']);
        $this->resetPage();
    }

    public function create()
    {
        if (!auth()->user()->can('invoicing.products.create')) {
            $this->dispatch('error', message: 'Sem permissão para criar produtos');
            return;
        }
        
        $this->resetForm();
        // Gerar código automaticamente para exibição
        $this->code = Product::generateProductCode(activeTenantId(), $this->type);
        $this->showModal = true;
    }
    
    // Atualizar código quando o tipo mudar
    public function updatedType($value)
    {
        if (!$this->editingProductId) {
            $this->code = Product::generateProductCode(activeTenantId(), $value);
        }
    }

    public function view($id)
    {
        $product = Product::with(['category.parent', 'brand', 'supplier', 'taxRate'])
            ->findOrFail($id);
        
        if ($product->tenant_id !== activeTenantId()) {
            abort(403);
        }
        
        $this->viewingProduct = $product;
        $this->showViewModal = true;
    }
    
    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingProduct = null;
    }
    
    public function edit($id)
    {
        if (!auth()->user()->can('invoicing.products.edit')) {
            $this->dispatch('error', message: 'Sem permissão para editar produtos');
            return;
        }
        
        $this->closeViewModal(); // Fechar modal de visualização se estiver aberta
        $product = Product::findOrFail($id);
        
        if ($product->tenant_id !== activeTenantId()) {
            abort(403);
        }
        
        $this->editingProductId = $id;
        $this->code = $product->code;
        $this->sku = $product->sku;
        $this->barcode = $product->barcode;
        $this->name = $product->name;
        $this->description = $product->description;
        $this->currentFeaturedImage = $product->featured_image;
        $this->currentGallery = $product->gallery ?? [];
        $this->price = $product->price;
        $this->cost = $product->cost;
        $this->unit = $product->unit;
        $this->type = $product->type;
        $this->category_id = $product->category_id;
        $this->brand_id = $product->brand_id;
        $this->supplier_id = $product->supplier_id;
        $this->tax_type = $product->tax_type ?? 'iva';
        $this->tax_rate_id = $product->tax_rate_id;
        $this->exemption_reason = $product->exemption_reason;
        $this->manage_stock = $product->manage_stock;
        $this->stock_quantity = $product->stock_quantity;
        $this->stock_min = $product->stock_min ?? 0;
        $this->stock_max = $product->stock_max;
        $this->track_batches = $product->track_batches ?? false;
        $this->track_expiry = $product->track_expiry ?? false;
        $this->require_batch_on_purchase = $product->require_batch_on_purchase ?? false;
        $this->require_batch_on_sale = $product->require_batch_on_sale ?? false;
        $this->showModal = true;
    }

    public function save()
    {
        // Verificar permissão apropriada
        if ($this->editingProductId) {
            if (!auth()->user()->can('invoicing.products.edit')) {
                $this->dispatch('error', message: 'Sem permissão para editar produtos');
                return;
            }
        } else {
            if (!auth()->user()->can('invoicing.products.create')) {
                $this->dispatch('error', message: 'Sem permissão para criar produtos');
                return;
            }
        }
        
        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'code' => $this->code,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'cost' => $this->cost,
            'unit' => $this->unit,
            'type' => $this->type,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'supplier_id' => $this->supplier_id,
            'tax_type' => $this->tax_type,
            'tax_rate_id' => $this->tax_type === 'iva' ? $this->tax_rate_id : null,
            'exemption_reason' => $this->tax_type === 'isento' ? $this->exemption_reason : null,
            'manage_stock' => $this->manage_stock,
            'stock_quantity' => $this->stock_quantity,
            'stock_min' => $this->stock_min,
            'stock_max' => $this->stock_max,
            'track_batches' => $this->track_batches,
            'track_expiry' => $this->track_expiry,
            'require_batch_on_purchase' => $this->require_batch_on_purchase,
            'require_batch_on_sale' => $this->require_batch_on_sale,
        ];

        if ($this->editingProductId) {
            $product = Product::findOrFail($this->editingProductId);
            
            if ($product->tenant_id !== activeTenantId()) {
                abort(403);
            }
            
            $productFolder = 'products/' . $product->id;
            
            // Handle featured image upload
            if ($this->featured_image) {
                $fileName = 'featured_' . \Str::slug($product->name) . '.' . $this->featured_image->getClientOriginalExtension();
                $imagePath = $this->featured_image->storeAs($productFolder, $fileName, 'public');
                $data['featured_image'] = $imagePath;
                
                // Delete old featured image if exists
                if ($product->featured_image && \Storage::disk('public')->exists($product->featured_image)) {
                    \Storage::disk('public')->delete($product->featured_image);
                }
            }

            // Handle gallery upload
            if (!empty($this->gallery)) {
                $galleryPaths = [];
                $galleryFolder = $productFolder . '/gallery';
                
                foreach ($this->gallery as $index => $image) {
                    $fileName = 'gallery_' . ($index + 1) . '_' . time() . '.' . $image->getClientOriginalExtension();
                    $galleryPaths[] = $image->storeAs($galleryFolder, $fileName, 'public');
                }
                
                // Merge with existing gallery
                $existingGallery = $product->gallery ?? [];
                $data['gallery'] = array_merge($existingGallery, $galleryPaths);
            }
            
            $product->update($data);
            $this->dispatch('success', message: 'Produto atualizado com sucesso!');
        } else {
            // Create product first to get ID
            $newProduct = Product::create($data);
            $productFolder = 'products/' . $newProduct->id;
            
            // Handle featured image upload
            if ($this->featured_image) {
                $fileName = 'featured_' . \Str::slug($newProduct->name) . '.' . $this->featured_image->getClientOriginalExtension();
                $imagePath = $this->featured_image->storeAs($productFolder, $fileName, 'public');
                $newProduct->update(['featured_image' => $imagePath]);
            }

            // Handle gallery upload
            if (!empty($this->gallery)) {
                $galleryPaths = [];
                $galleryFolder = $productFolder . '/gallery';
                
                foreach ($this->gallery as $index => $image) {
                    $fileName = 'gallery_' . ($index + 1) . '_' . time() . '.' . $image->getClientOriginalExtension();
                    $galleryPaths[] = $image->storeAs($galleryFolder, $fileName, 'public');
                }
                
                $newProduct->update(['gallery' => $galleryPaths]);
            }
            
            $this->dispatch('success', message: 'Produto criado com sucesso!');
        }

        $this->closeModal();
    }

    public function confirmDelete($id)
    {
        $product = Product::findOrFail($id);
        
        if ($product->tenant_id !== activeTenantId()) {
            abort(403);
        }
        
        $this->deletingProductId = $id;
        $this->deletingProductName = $product->name;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        if (!auth()->user()->can('invoicing.products.delete')) {
            $this->dispatch('error', message: 'Sem permissão para eliminar produtos');
            return;
        }
        
        try {
            $product = Product::findOrFail($this->deletingProductId);
            
            if ($product->tenant_id !== activeTenantId()) {
                abort(403);
            }
            
            // Delete product folder and all files (featured + gallery)
            $productFolder = 'products/' . $product->id;
            if (\Storage::disk('public')->exists($productFolder)) {
                \Storage::disk('public')->deleteDirectory($productFolder);
            }
            
            $product->delete();
            $this->showDeleteModal = false;
            $this->reset(['deletingProductId', 'deletingProductName']);
            $this->dispatch('success', message: 'Produto excluído com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir produto!');
        }
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->reset(['deletingProductId', 'deletingProductName']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['code', 'name', 'description', 'featured_image', 'currentFeaturedImage', 'gallery', 'currentGallery', 'editingProductId']);
        $this->price = 0;
        $this->cost = 0;
        $this->unit = 'UN';
        $this->type = 'produto';
        $this->category_id = null;
        $this->brand_id = null;
        $this->supplier_id = null;
        $this->tax_type = 'iva';
        $this->tax_rate_id = null;
        $this->exemption_reason = null;
        $this->manage_stock = false;
        $this->stock_quantity = 0;
        $this->stock_min = 0;
        $this->stock_max = null;
    }

    public function render()
    {
        $products = Product::where('tenant_id', activeTenantId())
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->typeFilter, function ($query) {
                $query->where('type', $this->typeFilter);
            })
            ->when($this->stockFilter, function ($query) {
                if ($this->stockFilter === 'com_stock') {
                    $query->where('manage_stock', true)->where('stock_quantity', '>', 0);
                } elseif ($this->stockFilter === 'sem_stock') {
                    $query->where('manage_stock', true)->where('stock_quantity', '<=', 0);
                } elseif ($this->stockFilter === 'nao_gerenciado') {
                    $query->where('manage_stock', false);
                }
            })
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('created_at', '<=', $this->dateTo);
            })
            ->latest()
            ->paginate($this->perPage);

        // Obter taxas ativas do tenant da tabela CORRETA: invoicing_taxes
        $taxRates = Tax::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('type', 'iva')
            ->orderBy('rate')
            ->get();

        return view('livewire.invoicing.products.products', compact('products', 'taxRates'));
    }
}
