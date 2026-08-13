<?php

namespace App\Livewire\Invoicing;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\CreditNote;
use App\Rules\ValidateNIF;
use App\Services\NIFLookupService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Clientes')]
class Clients extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $showModal = false;
    public $editingClientId = null;
    
    // Delete confirmation
    public $showDeleteModal = false;
    public $deletingClientId = null;
    public $deletingClientName = '';
    
    // View details modal
    public $showViewModal = false;
    public $viewingClient = null;
    public $clientStats = [];
    public $clientInvoices = [];
    public $clientTopProducts = [];
    public $clientPurchaseFrequency = [];
    
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
            'logo' => 'nullable|image|max:2048', // 2MB max
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'mobile' => 'nullable|string',
            'city' => 'nullable|string',
            'province' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'country' => 'required|string',
        ];
        
        if ($this->editingClientId) {
            $rules['nif'] = ['required', new ValidateNIF(), 'unique:invoicing_clients,nif,' . $this->editingClientId];
        } else {
            $rules['nif'] = ['required', new ValidateNIF(), 'unique:invoicing_clients,nif'];
        }
        
        return $rules;
    }
    
    /**
     * Busca dados do NIF (se existir no sistema ou cache)
     */
    public function lookupNIF()
    {
        if (empty($this->nif)) {
            return;
        }
        
        $nifService = new NIFLookupService();
        $data = $nifService->lookup($this->nif);
        
        if ($data) {
            // Preenche automaticamente os campos encontrados
            $this->name = $data['name'] ?? $this->name;
            $this->type = $data['type'] ?? $this->type;
            $this->email = $data['email'] ?? $this->email;
            $this->phone = $data['phone'] ?? $this->phone;
            $this->address = $data['address'] ?? $this->address;
            $this->city = $data['city'] ?? $this->city;
            $this->province = $data['province'] ?? $this->province;
            $this->country = $data['country'] ?? $this->country;
            
            $this->dispatch('success', message: __('Dados encontrados e preenchidos automaticamente!'));
        }
    }

    public function updatingTypeFilter()
    {
        $this->resetPage();
    }

    public function updatingCityFilter()
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
        $this->reset(['typeFilter', 'cityFilter', 'dateFrom', 'dateTo', 'search']);
        $this->resetPage();
    }

    public function create()
    {
        if (!auth()->user()->can('invoicing.clients.create')) {
            $this->dispatch('error', message: __('Sem permissão para criar clientes'));
            return;
        }
        
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        if (!auth()->user()->can('invoicing.clients.edit')) {
            $this->dispatch('error', message: __('Sem permissão para editar clientes'));
            return;
        }
        
        $client = Client::findOrFail($id);
        
        if ((int) $client->tenant_id !== (int) activeTenantId()) {
            abort(403);
        }
        
        $this->editingClientId = $id;
        $this->type = $client->type;
        $this->name = $client->name;
        $this->nif = $client->nif;
        $this->currentLogo = $client->logo; // Store current logo path
        $this->email = $client->email;
        $this->phone = $client->phone;
        $this->mobile = $client->mobile;
        $this->address = $client->address;
        $this->city = $client->city;
        $this->province = $client->province;
        $this->postal_code = $client->postal_code;
        $this->country = $client->country ?? 'Angola';
        $this->showModal = true;
    }

    public function save()
    {
        // Verificar permissão apropriada
        if ($this->editingClientId) {
            if (!auth()->user()->can('invoicing.clients.edit')) {
                $this->dispatch('error', message: __('Sem permissão para editar clientes'));
                return;
            }
        } else {
            if (!auth()->user()->can('invoicing.clients.create')) {
                $this->dispatch('error', message: __('Sem permissão para criar clientes'));
                return;
            }
        }
        
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

        if ($this->editingClientId) {
            $client = Client::findOrFail($this->editingClientId);
            
            if ((int) $client->tenant_id !== (int) activeTenantId()) {
                abort(403);
            }
            
            // Handle logo upload with organized path
            if ($this->logo) {
                $clientFolder = 'clients/' . $client->id;
                $fileName = 'logo_' . \Str::slug($client->name) . '.' . $this->logo->getClientOriginalExtension();
                $logoPath = $this->logo->storeAs($clientFolder, $fileName, 'public');
                $data['logo'] = $logoPath;
                
                // Delete old logo if exists
                if ($client->logo && \Storage::disk('public')->exists($client->logo)) {
                    \Storage::disk('public')->delete($client->logo);
                }
            }
            
            $client->update($data);
            $this->dispatch('success', message: __('Cliente atualizado com sucesso!'));
        } else {
            // Create client first to get ID
            $newClient = Client::create($data);
            
            // Handle logo upload with client ID
            if ($this->logo) {
                $clientFolder = 'clients/' . $newClient->id;
                $fileName = 'logo_' . \Str::slug($newClient->name) . '.' . $this->logo->getClientOriginalExtension();
                $logoPath = $this->logo->storeAs($clientFolder, $fileName, 'public');
                $newClient->update(['logo' => $logoPath]);
            }
            
            $this->dispatch('success', message: __('Cliente criado com sucesso!'));
        }

        $this->closeModal();
    }

    public function viewClient($id)
    {
        $client = Client::where('tenant_id', activeTenantId())->findOrFail($id);
        
        $tenantId = activeTenantId();
        
        // Estatisticas gerais
        $invoicesQ = SalesInvoice::where('tenant_id', $tenantId)->where('client_id', $id);
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
        
        // Produtos mais comprados
        $topProducts = \DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->leftJoin('invoicing_products as products', 'products.id', '=', 'items.product_id')
            ->where('inv.tenant_id', $tenantId)
            ->where('inv.client_id', $id)
            ->selectRaw('products.id, products.name, products.sku, SUM(items.quantity) as total_qty, SUM(items.subtotal) as total_value, COUNT(DISTINCT inv.id) as invoices_count')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();
        
        // Notas de credito
        $creditNotesTotal = CreditNote::where('tenant_id', $tenantId)->where('client_id', $id)->sum('total');
        $creditNotesCount = CreditNote::where('tenant_id', $tenantId)->where('client_id', $id)->count();
        
        // Recibos
        $receiptsTotal = Receipt::where('tenant_id', $tenantId)->where('client_id', $id)->sum('amount_paid');
        $receiptsCount = Receipt::where('tenant_id', $tenantId)->where('client_id', $id)->count();
        
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
        
        $this->viewingClient = $client->toArray();
        $this->clientStats = [
            'total_invoices' => $totalInvoices,
            'total_revenue' => (float) $totalRevenue,
            'total_paid' => (float) $totalPaid,
            'total_pending' => (float) $totalPending,
            'avg_ticket' => (float) $avgTicket,
            'first_purchase' => $firstPurchase ? \Carbon\Carbon::parse($firstPurchase)->format('d/m/Y') : null,
            'last_purchase' => $lastPurchase ? \Carbon\Carbon::parse($lastPurchase)->format('d/m/Y') : null,
            'avg_days_between' => $avgDaysBetween,
            'credit_notes_total' => (float) $creditNotesTotal,
            'credit_notes_count' => $creditNotesCount,
            'receipts_total' => (float) $receiptsTotal,
            'receipts_count' => $receiptsCount,
        ];
        $this->clientInvoices = $recentInvoices;
        $this->clientTopProducts = $topProducts->map(fn($p) => (array) $p)->toArray();
        $this->clientPurchaseFrequency = $monthlyFreq->map(fn($p) => (array) $p)->toArray();
        $this->showViewModal = true;
    }
    
    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->reset(['viewingClient', 'clientStats', 'clientInvoices', 'clientTopProducts', 'clientPurchaseFrequency']);
    }

    public function confirmDelete($id)
    {
        $client = Client::findOrFail($id);
        
        if ((int) $client->tenant_id !== (int) activeTenantId()) {
            abort(403);
        }
        
        $this->deletingClientId = $id;
        $this->deletingClientName = $client->name;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        if (!auth()->user()->can('invoicing.clients.delete')) {
            $this->dispatch('error', message: __('Sem permissão para eliminar clientes'));
            return;
        }
        
        try {
            $client = Client::findOrFail($this->deletingClientId);
            
            if ((int) $client->tenant_id !== (int) activeTenantId()) {
                abort(403);
            }
            
            // Delete client folder and all files
            $clientFolder = 'clients/' . $client->id;
            if (\Storage::disk('public')->exists($clientFolder)) {
                \Storage::disk('public')->deleteDirectory($clientFolder);
            }
            
            $client->delete();
            $this->showDeleteModal = false;
            $this->reset(['deletingClientId', 'deletingClientName']);
            $this->dispatch('success', message: __('Cliente excluído com sucesso!'));
        } catch (\Exception $e) {
            $this->dispatch('error', message: __('Erro ao excluir cliente!'));
        }
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->reset(['deletingClientId', 'deletingClientName']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['name', 'nif', 'logo', 'currentLogo', 'email', 'phone', 'mobile', 'address', 'city', 'province', 'postal_code', 'editingClientId']);
        $this->type = 'pessoa_juridica';
        $this->country = 'Angola';
    }

    public function render()
    {
        $clients = Client::where('tenant_id', activeTenantId())
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

        // Obter cidades únicas para o filtro
        $cities = Client::where('tenant_id', activeTenantId())
            ->whereNotNull('city')
            ->distinct()
            ->pluck('city')
            ->sort();

        return view('livewire.invoicing.clients.clients', compact('clients', 'cities'));
    }
}
