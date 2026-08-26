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
    public $payment_term_id = null; // Condição de pagamento

    // Acesso ao portal do cliente
    public bool $portal_access = false;
    public $portal_password = '';       // vazio = gerada automaticamente
    public bool $portal_avisar = true;  // mandar email de boas-vindas
    public $senhaGerada = null;         // mostrada UMA vez a quem a criou
    public $clienteDaSenha = '';

    public function mount()
    {
        // Garante que a empresa tem as condições de pagamento padrão.
        \App\Models\Invoicing\PaymentTerm::provisionarPadroes(activeTenantId());
    }

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
            // O portal autentica pelo email: sem email nao ha por onde entrar.
            'email' => $this->portal_access ? 'required|email' : 'nullable|email',
            'portal_password' => 'nullable|string|min:6|max:64',
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
        $this->payment_term_id = $client->payment_term_id;
        $this->portal_access = (bool) $client->portal_access;
        $this->portal_password = '';
        $this->portal_avisar = true;
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
            'payment_term_id' => $this->payment_term_id ?: null,
        ];

        // Manter payment_term_days em sincronia com a condição escolhida
        // (código legado ainda lê os dias directamente da coluna).
        if ($this->payment_term_id) {
            $term = \App\Models\Invoicing\PaymentTerm::where('tenant_id', activeTenantId())
                ->find($this->payment_term_id);
            $data['payment_term_days'] = $term->days ?? 0;
        }

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
            $this->aplicarAcessoAoPortal($client);
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

            $this->aplicarAcessoAoPortal($newClient);
            $this->dispatch('success', message: __('Cliente criado com sucesso!'));
        }

        $this->closeModal();
    }

    /**
     * Liga (ou desliga) o acesso do cliente ao portal, conforme o formulário.
     *
     * Só cria senha nova quando é preciso: ligar o acesso pela primeira vez, ou
     * o utilizador ter escrito uma senha. Guardar a ficha de um cliente que já
     * tinha acesso não lhe pode trocar a senha por baixo dos pés.
     */
    private function aplicarAcessoAoPortal(Client $cliente): void
    {
        $servico = app(\App\Services\Clientes\AcessoAoPortal::class);

        if (!$this->portal_access) {
            if ($cliente->portal_access) {
                $servico->revogar($cliente);
                $this->dispatch('success', message: __('Acesso ao portal desactivado.'));
            }

            return;
        }

        $senhaEscrita = trim((string) $this->portal_password);
        $precisaDeSenha = $senhaEscrita !== '' || !$cliente->password || !$cliente->portal_access;

        if (!$precisaDeSenha) {
            return;   // já tinha acesso e ninguém pediu senha nova
        }

        $r = $servico->conceder($cliente, $senhaEscrita ?: null, $this->portal_avisar);

        // Mostrada UMA vez a quem a criou: sem email (ou com email a falhar),
        // é a única forma de a empresa a poder dizer ao cliente.
        $this->senhaGerada = $r['senha'];
        $this->clienteDaSenha = $cliente->name;

        if ($r['email_enviado']) {
            $this->dispatch('success', message: __('Acesso criado. Email enviado para :email', ['email' => $cliente->email]));
        } elseif ($r['erro_email']) {
            $this->dispatch('error', message: __('Acesso criado, mas o email não saiu: :erro', ['erro' => $r['erro_email']]));
        }
    }

    /**
     * Repor a senha a partir da lista, sem abrir a ficha — é o pedido mais
     * comum ("perdi a senha") e não devia obrigar a editar o cliente.
     */
    public function reporSenhaDoPortal($id)
    {
        if (!auth()->user()->can('invoicing.clients.edit')) {
            $this->dispatch('error', message: __('Sem permissão para editar clientes'));

            return;
        }

        $cliente = Client::where('tenant_id', activeTenantId())->find($id);
        if (!$cliente) {
            $this->dispatch('error', message: __('Este cliente não pertence à empresa activa.'));

            return;
        }

        $r = app(\App\Services\Clientes\AcessoAoPortal::class)->conceder($cliente, null, true);

        $this->senhaGerada = $r['senha'];
        $this->clienteDaSenha = $cliente->name;

        $this->dispatch(
            $r['erro_email'] ? 'error' : 'success',
            message: $r['email_enviado']
                ? __('Senha nova enviada para :email', ['email' => $cliente->email])
                : ($r['erro_email']
                    ? __('Senha reposta, mas o email não saiu: :erro', ['erro' => $r['erro_email']])
                    : __('Senha reposta. Este cliente não tem email — passe-lhe a senha abaixo.'))
        );
    }

    public function fecharSenha(): void
    {
        $this->senhaGerada = null;
        $this->clienteDaSenha = '';
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
        // senhaGerada/clienteDaSenha ficam de FORA: são mostradas depois de o
        // modal fechar, e o save() fecha o modal — limpá-las aqui apagava a
        // senha antes de alguém a conseguir ler.
        $this->reset(['name', 'nif', 'logo', 'currentLogo', 'email', 'phone', 'mobile', 'address', 'city', 'province', 'postal_code', 'editingClientId', 'payment_term_id', 'portal_access', 'portal_password']);
        $this->portal_avisar = true;
        $this->type = 'pessoa_juridica';
        $this->country = 'Angola';
        // Cliente novo nasce com a condição padrão da empresa (se houver).
        $this->payment_term_id = \App\Models\Invoicing\PaymentTerm::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->value('id');
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

        $paymentTerms = \App\Models\Invoicing\PaymentTerm::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('livewire.invoicing.clients.clients', compact('clients', 'cities', 'paymentTerms'));
    }
}
