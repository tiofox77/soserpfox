# 🎯 EXEMPLO PRÁTICO - APLICAR PERMISSÕES

## ⚡ EXEMPLO COMPLETO: MÓDULO CLIENTES

Este é um exemplo prático de como aplicar permissões no módulo de Clientes.

---

## 📄 1. BLADE VIEW (clients.blade.php)

### **LOCALIZAÇÃO:**
```
resources/views/livewire/invoicing/clients.blade.php
```

### **CÓDIGO COM PERMISSÕES:**

```blade
<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800">Clientes</h2>
                <p class="text-gray-600 mt-1">Gestão de clientes da empresa</p>
            </div>
            
            {{-- Botão Novo Cliente - APENAS se tiver permissão --}}
            @can('invoicing.clients.create')
                <button wire:click="openCreateModal" 
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-plus mr-2"></i>Novo Cliente
                </button>
            @endcan
        </div>
    </div>

    {{-- Filtros e Pesquisa --}}
    <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="flex gap-4">
            <input type="text" 
                   wire:model.live="search" 
                   placeholder="Pesquisar clientes..." 
                   class="flex-1 rounded-lg">
            
            {{-- Botão Exportar - APENAS se tiver permissão --}}
            @can('invoicing.clients.export')
                <button wire:click="exportClients" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg">
                    <i class="fas fa-file-excel mr-2"></i>Exportar
                </button>
            @endcan
        </div>
    </div>

    {{-- Tabela de Clientes --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left">Nome</th>
                    <th class="px-6 py-3 text-left">Email</th>
                    <th class="px-6 py-3 text-left">Telefone</th>
                    <th class="px-6 py-3 text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clients as $client)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">{{ $client->name }}</td>
                        <td class="px-6 py-4">{{ $client->email }}</td>
                        <td class="px-6 py-4">{{ $client->phone }}</td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                
                                {{-- Botão Ver - Sempre visível --}}
                                <button wire:click="viewClient({{ $client->id }})" 
                                        class="p-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200"
                                        title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                {{-- Botão Editar - APENAS com permissão --}}
                                @can('invoicing.clients.edit')
                                    <button wire:click="editClient({{ $client->id }})" 
                                            class="p-2 bg-indigo-100 text-indigo-600 rounded-lg hover:bg-indigo-200"
                                            title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                @endcan
                                
                                {{-- Botão Eliminar - APENAS com permissão --}}
                                @can('invoicing.clients.delete')
                                    <button wire:click="confirmDelete({{ $client->id }})" 
                                            class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200"
                                            title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                @endcan
                                
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-gray-400">
                            Nenhum cliente encontrado
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="px-6 py-4">
            {{ $clients->links() }}
        </div>
    </div>

    {{-- Modal Criar/Editar - Exibição já controlada pelo showModal --}}
    @if($showModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full p-6">
                {{-- Conteúdo do modal --}}
            </div>
        </div>
    @endif
</div>
```

---

## 🔧 2. COMPONENTE LIVEWIRE (Clients.php)

### **LOCALIZAÇÃO:**
```
app/Livewire/Invoicing/Clients.php
```

### **CÓDIGO COM PERMISSÕES:**

```php
<?php

namespace App\Livewire\Invoicing;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Client;

class Clients extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editingClient = null;
    
    // Campos do formulário
    public $name;
    public $email;
    public $phone;

    /**
     * Verificar permissão ao carregar componente
     */
    public function mount()
    {
        // Verificar se tem permissão para ver clientes
        if (!auth()->user()->can('invoicing.clients.view')) {
            abort(403, 'Sem permissão para visualizar clientes');
        }
    }

    /**
     * Abrir modal de criação
     */
    public function openCreateModal()
    {
        // Verificar permissão
        if (!auth()->user()->can('invoicing.clients.create')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sem permissão para criar clientes'
            ]);
            return;
        }

        $this->resetForm();
        $this->showModal = true;
    }

    /**
     * Editar cliente
     */
    public function editClient($clientId)
    {
        // Verificar permissão
        if (!auth()->user()->can('invoicing.clients.edit')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sem permissão para editar clientes'
            ]);
            return;
        }

        $client = Client::findOrFail($clientId);
        
        $this->editingClient = $clientId;
        $this->name = $client->name;
        $this->email = $client->email;
        $this->phone = $client->phone;
        
        $this->showModal = true;
    }

    /**
     * Salvar cliente (criar ou atualizar)
     */
    public function saveClient()
    {
        // Verificar permissão apropriada
        if ($this->editingClient) {
            if (!auth()->user()->can('invoicing.clients.edit')) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Sem permissão para editar clientes'
                ]);
                return;
            }
        } else {
            if (!auth()->user()->can('invoicing.clients.create')) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Sem permissão para criar clientes'
                ]);
                return;
            }
        }

        // Validação
        $this->validate([
            'name' => 'required|min:3',
            'email' => 'required|email',
            'phone' => 'nullable',
        ]);

        // Salvar
        if ($this->editingClient) {
            $client = Client::findOrFail($this->editingClient);
            $client->update([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
            ]);
            $message = 'Cliente atualizado com sucesso!';
        } else {
            Client::create([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'tenant_id' => activeTenantId(),
            ]);
            $message = 'Cliente criado com sucesso!';
        }

        $this->showModal = false;
        $this->resetForm();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message
        ]);
    }

    /**
     * Eliminar cliente
     */
    public function deleteClient($clientId)
    {
        // Verificar permissão
        if (!auth()->user()->can('invoicing.clients.delete')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sem permissão para eliminar clientes'
            ]);
            return;
        }

        $client = Client::findOrFail($clientId);
        $client->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Cliente eliminado com sucesso!'
        ]);
    }

    /**
     * Exportar clientes
     */
    public function exportClients()
    {
        // Verificar permissão
        if (!auth()->user()->can('invoicing.clients.export')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sem permissão para exportar clientes'
            ]);
            return;
        }

        // Lógica de exportação
        // ...
    }

    /**
     * Resetar formulário
     */
    private function resetForm()
    {
        $this->editingClient = null;
        $this->name = '';
        $this->email = '';
        $this->phone = '';
    }

    /**
     * Renderizar componente
     */
    public function render()
    {
        $clients = Client::where('tenant_id', activeTenantId())
            ->when($this->search, function($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.invoicing.clients', [
            'clients' => $clients,
            
            // Passar permissões para a view (opcional)
            'canCreate' => auth()->user()->can('invoicing.clients.create'),
            'canEdit' => auth()->user()->can('invoicing.clients.edit'),
            'canDelete' => auth()->user()->can('invoicing.clients.delete'),
            'canExport' => auth()->user()->can('invoicing.clients.export'),
        ]);
    }
}
```

---

## 🛣️ 3. PROTEGER ROTA (web.php)

### **LOCALIZAÇÃO:**
```
routes/web.php
```

### **CÓDIGO:**

```php
// Módulo de Faturação
Route::middleware(['auth'])->prefix('invoicing')->name('invoicing.')->group(function () {
    
    // Dashboard - Requer permissão
    Route::middleware('permission:invoicing.dashboard.view')
        ->get('/dashboard', \App\Livewire\Invoicing\InvoicingDashboard::class)
        ->name('dashboard');
    
    // Clientes - Requer permissão de visualização
    Route::middleware('permission:invoicing.clients.view')
        ->get('/clients', \App\Livewire\Invoicing\Clients::class)
        ->name('clients');
    
    // Produtos - Requer permissão de visualização
    Route::middleware('permission:invoicing.products.view')
        ->get('/products', \App\Livewire\Invoicing\Products\Products::class)
        ->name('products');
    
    // POS - Requer permissão de acesso
    Route::middleware('permission:invoicing.pos.access')
        ->get('/pos', \App\Livewire\Pos\POSSystem::class)
        ->name('pos');
});
```

---

## 🎨 4. MENU LATERAL (app.blade.php)

### **LOCALIZAÇÃO:**
```
resources/views/layouts/app.blade.php
```

### **CÓDIGO:**

```blade
{{-- Módulo Faturação --}}
<div x-data="{ invoicingOpen: {{ request()->routeIs('invoicing.*') ? 'true' : 'false' }} }">
    <button @click="invoicingOpen = !invoicingOpen" 
            class="w-full flex items-center justify-between px-4 py-3">
        <div class="flex items-center">
            <i class="fas fa-file-invoice-dollar w-6 text-yellow-400"></i>
            <span>Facturação</span>
        </div>
        <i :class="invoicingOpen ? 'fa-chevron-down' : 'fa-chevron-right'" class="fas"></i>
    </button>
    
    <div x-show="invoicingOpen" x-collapse>
        
        {{-- Dashboard --}}
        @can('invoicing.dashboard.view')
            <a href="{{ route('invoicing.dashboard') }}" 
               class="flex items-center pl-8 pr-4 py-2.5">
                <i class="fas fa-chart-line w-5 text-blue-400"></i>
                <span class="ml-3">Dashboard</span>
            </a>
        @endcan
        
        {{-- Clientes --}}
        @can('invoicing.clients.view')
            <a href="{{ route('invoicing.clients') }}" 
               class="flex items-center pl-8 pr-4 py-2.5">
                <i class="fas fa-users w-5 text-green-400"></i>
                <span class="ml-3">Clientes</span>
            </a>
        @endcan
        
        {{-- Produtos --}}
        @can('invoicing.products.view')
            <a href="{{ route('invoicing.products') }}" 
               class="flex items-center pl-8 pr-4 py-2.5">
                <i class="fas fa-box w-5 text-purple-400"></i>
                <span class="ml-3">Produtos</span>
            </a>
        @endcan
        
        {{-- POS --}}
        @can('invoicing.pos.access')
            <a href="{{ route('invoicing.pos') }}" 
               class="flex items-center pl-8 pr-4 py-2.5">
                <i class="fas fa-cash-register w-5 text-emerald-400"></i>
                <span class="ml-3">POS</span>
            </a>
        @endcan
        
    </div>
</div>
```

---

## 🧪 5. TESTAR AS PERMISSÕES

### **Passo 1: Criar Utilizador de Teste**
```bash
php artisan tinker
```

```php
$user = User::create([
    'name' => 'João Vendedor',
    'email' => 'joao@test.com',
    'password' => bcrypt('password'),
    'tenant_id' => 1,
    'is_super_admin' => false,
]);

// Atribuir role de Vendedor
$user->assignRole('Vendedor');

// Verificar permissões
$user->getAllPermissions()->pluck('name');
```

### **Passo 2: Testar no Navegador**

1. **Fazer logout do Super Admin**
2. **Fazer login com João Vendedor**
3. **Acessar Clientes:**
   - ✅ Deve ver listagem
   - ✅ Deve ver botão "Novo Cliente"
   - ✅ Deve ver botão "Editar"
   - ❌ NÃO deve ver botão "Eliminar" (sem permissão)

### **Passo 3: Verificar Console**

```bash
# Ver permissões de um utilizador
php artisan tinker
>>> User::find(2)->can('invoicing.clients.create'); // true
>>> User::find(2)->can('invoicing.clients.delete'); // false
```

---

## 📊 RESULTADO ESPERADO

### **Super Admin:**
- ✅ Vê TUDO
- ✅ Pode fazer TUDO

### **Vendedor:**
- ✅ Vê clientes, produtos, faturas
- ✅ Pode criar/editar clientes e faturas
- ❌ NÃO pode eliminar
- ❌ NÃO pode acessar configurações

### **Contabilista:**
- ✅ Vê clientes, faturas, relatórios
- ❌ NÃO pode criar/editar nada
- ❌ NÃO pode eliminar

---

## ✅ CHECKLIST FINAL

- [ ] Blade tem `@can` em todos os botões críticos
- [ ] Livewire verifica permissões em todos os métodos
- [ ] Rotas protegidas com middleware `permission:`
- [ ] Menu mostra apenas itens permitidos
- [ ] Testado com utilizadores diferentes
- [ ] Super Admin continua com acesso total

---

**ESTE É O PADRÃO PARA APLICAR EM TODOS OS MÓDULOS! 🚀**

Replique esta estrutura para:
- Fornecedores
- Produtos
- Faturas
- Recibos
- POS
- Tesouraria
