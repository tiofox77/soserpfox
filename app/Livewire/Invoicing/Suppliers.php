<?php

namespace App\Livewire\Invoicing;

use App\Models\Supplier;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
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
    
    // View details modal
    public $showViewModal = false;
    public $viewingSupplier = null;
    public $supplierStats = [];
    public $supplierInvoices = [];
    public $supplierTopProducts = [];
    public $supplierPurchaseFrequency = [];
    
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
        
        if ((int) $supplier->tenant_id !== (int) activeTenantId()) {
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
            
            if ((int) $supplier->tenant_id !== (int) activeTenantId()) {
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
            $this->dispatch('success', message: __('Fornecedor atualizado com sucesso!'));
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
            
            $this->dispatch('success', message: __('Fornecedor criado com sucesso!'));
        }

        $this->closeModal();
    }

    public function viewSupplier($id)
    {
        $supplier = Supplier::where('tenant_id', activeTenantId())->findOrFail($id);
        
        $tenantId = activeTenantId();
        
        // Estatisticas gerais
        $invoicesQ = PurchaseInvoice::where('tenant_id', $tenantId)->where('supplier_id', $id);
        $totalInvoices = (clone $invoicesQ)->count();
        $totalRevenue = (clone $invoicesQ)->sum('total');
        $totalPaid = (clone $invoicesQ)->sum('paid_amount');
        $totalPending = max(0, $totalRevenue - $totalPaid);
        $lastPurchase = (clone $invoicesQ)->latest('invoice_date')->value('invoice_date');
        $firstPurchase = (clone $invoicesQ)->oldest('invoice_date')->value('invoice_date');
        $avgTicket = $totalInvoices > 0 ? $totalRevenue / $totalInvoices : 0;
        
        // Frequencia de compra
        $monthlyFreq = (clone $invoicesQ)
            ->selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') as period, COUNT(*) as count, SUM(total) as total")
            ->groupBy('period')
            ->orderByDesc('period')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();
        
        // Frequencia em dias entre compras
        $purchaseDates = (clone $invoicesQ)->orderBy('invoice_date')->pluck('invoice_date');
        $avgDaysBetween = 0;
        if ($purchaseDates->count() > 1) {
            $diffs = [];
            for ($i = 1; $i < $purchaseDates->count(); $i++) {
                $diffs[] = \Carbon\Carbon::parse($purchaseDates[$i])->diffInDays(\Carbon\Carbon::parse($purchaseDates[$i - 1]));
            }
            $avgDaysBetween = count($diffs) > 0 ? round(array_sum($diffs) / count($diffs), 1) : 0;
        }
        
        // Produtos mais comprados ao fornecedor
        $topProducts = \DB::table('invoicing_purchase_invoice_items as items')
            ->join('invoicing_purchase_invoices as inv', 'inv.id', '=', 'items.purchase_invoice_id')
            ->leftJoin('invoicing_products as products', 'products.id', '=', 'items.product_id')
            ->where('inv.tenant_id', $tenantId)
            ->where('inv.supplier_id', $id)
            ->selectRaw('products.id, products.name, products.sku, SUM(items.quantity) as total_qty, SUM(items.subtotal) as total_value, COUNT(DISTINCT inv.id) as invoices_count')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();
        
        // Recibos / pagamentos ao fornecedor
        $receiptsTotal = Receipt::where('tenant_id', $tenantId)->where('supplier_id', $id)->sum('amount_paid');
        $receiptsCount = Receipt::where('tenant_id', $tenantId)->where('supplier_id', $id)->count();
        
        // Lista das ultimas faturas (extrato)
        $recentInvoices = (clone $invoicesQ)
            ->orderByDesc('invoice_date')
            ->limit(20)
            ->get(['id', 'invoice_number', 'invoice_date', 'due_date', 'total', 'paid_amount', 'status'])
            ->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'invoice_date' => $inv->invoice_date?->format('d/m/Y'),
                    'due_date' => $inv->due_date?->format('d/m/Y'),
                    'total' => (float) $inv->total,
                    'paid_amount' => (float) ($inv->paid_amount ?? 0),
                    'balance' => (float) ($inv->total - ($inv->paid_amount ?? 0)),
                    'status' => $inv->status,
                ];
            })->toArray();
        
        $this->viewingSupplier = $supplier->toArray();
        $this->supplierStats = [
            'total_invoices' => $totalInvoices,
            'total_revenue' => (float) $totalRevenue,
            'total_paid' => (float) $totalPaid,
            'total_pending' => (float) $totalPending,
            'avg_ticket' => (float) $avgTicket,
            'first_purchase' => $firstPurchase ? \Carbon\Carbon::parse($firstPurchase)->format('d/m/Y') : null,
            'last_purchase' => $lastPurchase ? \Carbon\Carbon::parse($lastPurchase)->format('d/m/Y') : null,
            'avg_days_between' => $avgDaysBetween,
            'receipts_total' => (float) $receiptsTotal,
            'receipts_count' => $receiptsCount,
        ];
        $this->supplierInvoices = $recentInvoices;
        $this->supplierTopProducts = $topProducts->map(fn($p) => (array) $p)->toArray();
        $this->supplierPurchaseFrequency = $monthlyFreq->map(fn($p) => (array) $p)->toArray();
        $this->showViewModal = true;
    }
    
    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->reset(['viewingSupplier', 'supplierStats', 'supplierInvoices', 'supplierTopProducts', 'supplierPurchaseFrequency']);
    }

    public function confirmDelete($id)
    {
        $supplier = Supplier::findOrFail($id);
        
        if ((int) $supplier->tenant_id !== (int) activeTenantId()) {
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
            
            if ((int) $supplier->tenant_id !== (int) activeTenantId()) {
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
            $this->dispatch('success', message: __('Fornecedor excluído com sucesso!'));
        } catch (\Exception $e) {
            $this->dispatch('error', message: __('Erro ao excluir fornecedor!'));
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
