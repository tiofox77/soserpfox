<?php

namespace App\Livewire\Invoicing;

use App\Models\Supplier;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Fornecedores')]
class Suppliers extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $showModal = false;
    public $editingSupplierId = null;
    
    // Delete confirmation
    public $showDeleteModal = false;
    public $deletingSupplierId = null;
    public $deletingSupplierName = '';
    
    // Filters
    public $typeFilter = '';
    public $cityFilter = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 15;
    
    // Form fields
    public $type = 'pessoa_juridica';
    public $name, $nif, $email, $phone, $mobile;
    public $address, $city, $province, $postal_code, $country = 'AO'; // ISO 3166-1-alpha-2
    public $logo; // Upload file
    public $currentLogo; // Existing logo path

    protected function rules()
    {
        $rules = [
            'type' => 'required|in:pessoa_juridica,pessoa_fisica',
            'name' => 'required|min:3',
            'nif' => 'nullable|string',
            'logo' => 'nullable|image|max:2048', // 2MB max
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'mobile' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'province' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'country' => 'required|string',
        ];

        return $rules;
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingTypeFilter() { $this->resetPage(); }
    public function updatingCityFilter() { $this->resetPage(); }
    public function updatingDateFrom() { $this->resetPage(); }
    public function updatingDateTo() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['typeFilter', 'cityFilter', 'dateFrom', 'dateTo', 'search']);
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $supplier = Supplier::findOrFail($id);
        
        if ($supplier->tenant_id !== activeTenantId()) {
            abort(403);
        }
        
        $this->editingSupplierId = $id;
        $this->type = $supplier->type;
        $this->name = $supplier->name;
        $this->nif = $supplier->nif;
        $this->currentLogo = $supplier->logo; // Store current logo path
        $this->email = $supplier->email;
        $this->phone = $supplier->phone;
        $this->mobile = $supplier->mobile;
        $this->address = $supplier->address;
        $this->city = $supplier->city;
        $this->province = $supplier->province;
        $this->postal_code = $supplier->postal_code;
        $this->country = $supplier->country ?? 'Angola';
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'type' => $this->type,
            'name' => $this->name,
            'nif' => $this->nif,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
        ];

        if ($this->editingSupplierId) {
            $supplier = Supplier::findOrFail($this->editingSupplierId);
            
            if ($supplier->tenant_id !== activeTenantId()) {
                abort(403);
            }
            
            // Handle logo upload with organized path
            if ($this->logo) {
                $supplierFolder = 'suppliers/' . $supplier->id;
                $fileName = 'logo_' . \Str::slug($supplier->name) . '.' . $this->logo->getClientOriginalExtension();
                $logoPath = $this->logo->storeAs($supplierFolder, $fileName, 'public');
                $data['logo'] = $logoPath;
                
                // Delete old logo if exists
                if ($supplier->logo && \Storage::disk('public')->exists($supplier->logo)) {
                    \Storage::disk('public')->delete($supplier->logo);
                }
            }
            
            $supplier->update($data);
            $this->dispatch('success', message: 'Fornecedor atualizado com sucesso!');
        } else {
            // Create supplier first to get ID
            $newSupplier = Supplier::create($data);
            
            // Handle logo upload with supplier ID
            if ($this->logo) {
                $supplierFolder = 'suppliers/' . $newSupplier->id;
                $fileName = 'logo_' . \Str::slug($newSupplier->name) . '.' . $this->logo->getClientOriginalExtension();
                $logoPath = $this->logo->storeAs($supplierFolder, $fileName, 'public');
                $newSupplier->update(['logo' => $logoPath]);
            }
            
            $this->dispatch('success', message: 'Fornecedor criado com sucesso!');
        }

        $this->closeModal();
    }

    public function confirmDelete($id)
    {
        $supplier = Supplier::findOrFail($id);
        
        if ($supplier->tenant_id !== activeTenantId()) {
            abort(403);
        }
        
        $this->deletingSupplierId = $id;
        $this->deletingSupplierName = $supplier->name;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        try {
            $supplier = Supplier::findOrFail($this->deletingSupplierId);
            
            if ($supplier->tenant_id !== activeTenantId()) {
                abort(403);
            }
            
            // Delete supplier folder and all files
            $supplierFolder = 'suppliers/' . $supplier->id;
            if (\Storage::disk('public')->exists($supplierFolder)) {
                \Storage::disk('public')->deleteDirectory($supplierFolder);
            }
            
            $supplier->delete();
            $this->showDeleteModal = false;
            $this->reset(['deletingSupplierId', 'deletingSupplierName']);
            $this->dispatch('success', message: 'Fornecedor excluído com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir fornecedor!');
        }
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->reset(['deletingSupplierId', 'deletingSupplierName']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['name', 'nif', 'logo', 'currentLogo', 'email', 'phone', 'mobile', 'address', 'city', 'province', 'postal_code', 'editingSupplierId']);
        $this->type = 'pessoa_juridica';
        $this->country = 'Angola';
    }

    public function render()
    {
        $suppliers = Supplier::where('tenant_id', activeTenantId())
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('nif', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->typeFilter, function ($query) {
                $query->where('type', $this->typeFilter);
            })
            ->when($this->cityFilter, function ($query) {
                $query->where('city', 'like', '%' . $this->cityFilter . '%');
            })
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('created_at', '<=', $this->dateTo);
            })
            ->latest()
            ->paginate($this->perPage);

        $cities = Supplier::where('tenant_id', activeTenantId())
            ->whereNotNull('city')
            ->distinct()
            ->pluck('city')
            ->sort();

        return view('livewire.invoicing.suppliers.suppliers', compact('suppliers', 'cities'));
    }
}
