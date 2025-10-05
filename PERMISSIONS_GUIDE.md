# 🔐 GUIA DE PERMISSÕES - SPATIE PERMISSION

## 📋 ÍNDICE
1. [Instalação e Configuração](#instalação-e-configuração)
2. [Estrutura de Permissões](#estrutura-de-permissões)
3. [Uso em Controllers](#uso-em-controllers)
4. [Uso em Rotas](#uso-em-rotas)
5. [Uso em Blade](#uso-em-blade)
6. [Uso em Livewire](#uso-em-livewire)
7. [Gestão de Roles](#gestão-de-roles)
8. [Exemplos Práticos](#exemplos-práticos)

---

## 📦 INSTALAÇÃO E CONFIGURAÇÃO

### 1. Executar Migrations

```bash
php artisan migrate
```

### 2. Executar Seeder de Permissões

```bash
php artisan db:seed --class=PermissionsSeeder
```

### 3. Limpar Cache de Permissões

```bash
php artisan permission:cache-reset
```

---

## 🏗️ ESTRUTURA DE PERMISSÕES

### Nomenclatura Padrão

```
modulo.recurso.acao
```

**Exemplos:**
- `invoicing.clients.view` - Ver clientes
- `invoicing.clients.create` - Criar clientes
- `invoicing.clients.edit` - Editar clientes
- `invoicing.clients.delete` - Eliminar clientes

### Módulos Disponíveis

#### **MÓDULO DE FATURAÇÃO (invoicing)**

**Dashboard:**
- `invoicing.dashboard.view`

**Clientes:**
- `invoicing.clients.view`
- `invoicing.clients.create`
- `invoicing.clients.edit`
- `invoicing.clients.delete`
- `invoicing.clients.export`

**Fornecedores:**
- `invoicing.suppliers.view`
- `invoicing.suppliers.create`
- `invoicing.suppliers.edit`
- `invoicing.suppliers.delete`

**Produtos:**
- `invoicing.products.view`
- `invoicing.products.create`
- `invoicing.products.edit`
- `invoicing.products.delete`
- `invoicing.products.import`

**Faturas de Venda:**
- `invoicing.sales.invoices.view`
- `invoicing.sales.invoices.create`
- `invoicing.sales.invoices.edit`
- `invoicing.sales.invoices.delete`
- `invoicing.sales.invoices.pdf`
- `invoicing.sales.invoices.cancel`

**POS:**
- `invoicing.pos.access`
- `invoicing.pos.sell`
- `invoicing.pos.refund`
- `invoicing.pos.reports`
- `invoicing.pos.settings`

#### **MÓDULO DE TESOURARIA (treasury)**

**Contas:**
- `treasury.accounts.view`
- `treasury.accounts.create`
- `treasury.accounts.edit`
- `treasury.accounts.delete`

**Movimentos:**
- `treasury.transactions.view`
- `treasury.transactions.create`
- `treasury.transactions.edit`
- `treasury.transactions.delete`

---

## 🎯 USO EM CONTROLLERS

### Verificar Permissão no Construtor

```php
<?php

namespace App\Http\Controllers\Invoicing;

use Illuminate\Http\Controller;

class ClientController extends Controller
{
    public function __construct()
    {
        // Verificar se tem permissão para view
        $this->middleware('permission:invoicing.clients.view')->only(['index', 'show']);
        
        // Verificar se tem permissão para create
        $this->middleware('permission:invoicing.clients.create')->only(['create', 'store']);
        
        // Verificar se tem permissão para edit
        $this->middleware('permission:invoicing.clients.edit')->only(['edit', 'update']);
        
        // Verificar se tem permissão para delete
        $this->middleware('permission:invoicing.clients.delete')->only(['destroy']);
    }
}
```

### Verificar Permissão no Método

```php
public function store(Request $request)
{
    // Verificar permissão
    if (!auth()->user()->can('invoicing.clients.create')) {
        abort(403, 'Sem permissão para criar clientes');
    }
    
    // Lógica de criação
}
```

---

## 🛣️ USO EM ROTAS

### Proteger Rotas Individuais

```php
Route::middleware(['auth', 'permission:invoicing.clients.view'])
    ->get('/invoicing/clients', [ClientController::class, 'index']);
```

### Proteger Grupo de Rotas

```php
Route::middleware(['auth'])->prefix('invoicing')->name('invoicing.')->group(function () {
    
    // Dashboard - Requer permissão específica
    Route::middleware('permission:invoicing.dashboard.view')
        ->get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
    
    // Clientes - Múltiplas permissões
    Route::middleware('permission:invoicing.clients.view')
        ->get('/clients', [ClientController::class, 'index'])
        ->name('clients.index');
        
    Route::middleware('permission:invoicing.clients.create')
        ->get('/clients/create', [ClientController::class, 'create'])
        ->name('clients.create');
});
```

---

## 🎨 USO EM BLADE

### Diretivas @can e @cannot

```blade
{{-- Mostrar apenas se tiver permissão --}}
@can('invoicing.clients.create')
    <a href="{{ route('invoicing.clients.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Novo Cliente
    </a>
@endcan

{{-- Mostrar se NÃO tiver permissão --}}
@cannot('invoicing.clients.delete')
    <p class="text-muted">Você não tem permissão para eliminar clientes.</p>
@endcannot

{{-- If / Else --}}
@can('invoicing.clients.edit')
    <button class="btn-edit">Editar</button>
@else
    <span class="text-muted">Sem permissão</span>
@endcan
```

### Múltiplas Permissões

```blade
{{-- Verificar QUALQUER permissão (OR) --}}
@canany(['invoicing.clients.edit', 'invoicing.clients.delete'])
    <div class="actions">
        @can('invoicing.clients.edit')
            <button>Editar</button>
        @endcan
        
        @can('invoicing.clients.delete')
            <button>Eliminar</button>
        @endcan
    </div>
@endcanany

{{-- Verificar TODAS as permissões (AND) - usando lógica personalizada --}}
@if(auth()->user()->can('invoicing.clients.edit') && auth()->user()->can('invoicing.clients.delete'))
    <button>Ações Avançadas</button>
@endif
```

### Menu Condicional

```blade
<ul class="sidebar-menu">
    @can('invoicing.dashboard.view')
        <li><a href="{{ route('invoicing.dashboard') }}">Dashboard</a></li>
    @endcan
    
    @can('invoicing.clients.view')
        <li><a href="{{ route('invoicing.clients') }}">Clientes</a></li>
    @endcan
    
    @can('invoicing.products.view')
        <li><a href="{{ route('invoicing.products') }}">Produtos</a></li>
    @endcan
    
    @can('invoicing.pos.access')
        <li><a href="{{ route('invoicing.pos') }}">POS</a></li>
    @endcan
</ul>
```

---

## ⚡ USO EM LIVEWIRE

### No Componente Livewire

```php
<?php

namespace App\Livewire\Invoicing;

use Livewire\Component;

class Clients extends Component
{
    public function mount()
    {
        // Verificar permissão ao carregar componente
        if (!auth()->user()->can('invoicing.clients.view')) {
            abort(403, 'Sem permissão para ver clientes');
        }
    }
    
    public function createClient()
    {
        // Verificar antes de criar
        if (!auth()->user()->can('invoicing.clients.create')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sem permissão para criar clientes'
            ]);
            return;
        }
        
        // Lógica de criação
    }
    
    public function deleteClient($clientId)
    {
        // Verificar antes de eliminar
        if (!auth()->user()->can('invoicing.clients.delete')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sem permissão para eliminar clientes'
            ]);
            return;
        }
        
        // Lógica de eliminação
    }
    
    public function render()
    {
        return view('livewire.invoicing.clients', [
            'canCreate' => auth()->user()->can('invoicing.clients.create'),
            'canEdit' => auth()->user()->can('invoicing.clients.edit'),
            'canDelete' => auth()->user()->can('invoicing.clients.delete'),
        ]);
    }
}
```

### Na View Livewire

```blade
<div>
    {{-- Botão condicional --}}
    @if($canCreate)
        <button wire:click="openCreateModal" class="btn btn-primary">
            Novo Cliente
        </button>
    @endif
    
    {{-- Tabela com ações condicionais --}}
    <table>
        @foreach($clients as $client)
            <tr>
                <td>{{ $client->name }}</td>
                <td>
                    @if($canEdit)
                        <button wire:click="editClient({{ $client->id }})">Editar</button>
                    @endif
                    
                    @if($canDelete)
                        <button wire:click="deleteClient({{ $client->id }})">Eliminar</button>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</div>
```

---

## 👥 GESTÃO DE ROLES

### Criar Role

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Criar role
$role = Role::create([
    'name' => 'Vendedor',
    'description' => 'Responsável por vendas'
]);

// Atribuir permissões
$role->givePermissionTo([
    'invoicing.dashboard.view',
    'invoicing.clients.view',
    'invoicing.clients.create',
    'invoicing.sales.invoices.view',
    'invoicing.sales.invoices.create',
]);
```

### Atribuir Role a Utilizador

```php
$user = User::find(1);

// Atribuir um role
$user->assignRole('Vendedor');

// Atribuir múltiplos roles
$user->assignRole(['Vendedor', 'Caixa']);

// Remover role
$user->removeRole('Vendedor');

// Sincronizar roles (substitui todos)
$user->syncRoles(['Vendedor']);
```

### Verificar Roles

```php
// Verificar se tem role
if ($user->hasRole('Vendedor')) {
    // ...
}

// Verificar qualquer role
if ($user->hasAnyRole(['Vendedor', 'Caixa'])) {
    // ...
}

// Verificar todos os roles
if ($user->hasAllRoles(['Vendedor', 'Caixa'])) {
    // ...
}
```

### Roles Predefinidos

1. **Super Admin** - Acesso total ao sistema
2. **Administrador Faturação** - Gestão completa do módulo de faturação
3. **Vendedor** - Vendas e atendimento ao cliente
4. **Caixa** - Gestão de pagamentos e recebimentos
5. **Contabilista** - Visualização de documentos fiscais
6. **Operador Stock** - Gestão de produtos e stock

---

## 💡 EXEMPLOS PRÁTICOS

### Exemplo 1: Botões Condicionais em Listagem

```blade
<div class="table-actions">
    @can('invoicing.sales.invoices.pdf')
        <a href="{{ route('invoicing.sales.invoices.pdf', $invoice->id) }}" 
           class="btn-pdf" target="_blank">
            <i class="fas fa-file-pdf"></i> PDF
        </a>
    @endcan
    
    @can('invoicing.sales.invoices.edit')
        <a href="{{ route('invoicing.sales.invoices.edit', $invoice->id) }}" 
           class="btn-edit">
            <i class="fas fa-edit"></i> Editar
        </a>
    @endcan
    
    @can('invoicing.sales.invoices.cancel')
        <button wire:click="cancelInvoice({{ $invoice->id }})" 
                class="btn-cancel">
            <i class="fas fa-ban"></i> Cancelar
        </button>
    @endcan
</div>
```

### Exemplo 2: Proteger Rota de API

```php
Route::middleware(['auth:sanctum', 'permission:invoicing.clients.view'])
    ->get('/api/clients', [ClientApiController::class, 'index']);
```

### Exemplo 3: Formulário com Campos Condicionais

```blade
<form wire:submit.prevent="saveClient">
    <input type="text" wire:model="name" placeholder="Nome">
    
    @can('invoicing.clients.export')
        <div class="form-group">
            <label>Permitir Exportação</label>
            <input type="checkbox" wire:model="allow_export">
        </div>
    @endcan
    
    <button type="submit">
        @can('invoicing.clients.edit')
            Salvar Alterações
        @else
            Visualizar
        @endcan
    </button>
</form>
```

### Exemplo 4: Middleware em Livewire

```php
#[Middleware('permission:invoicing.pos.access')]
class POSSystem extends Component
{
    // ...
}
```

---

## 🔧 COMANDOS ÚTEIS

```bash
# Limpar cache de permissões
php artisan permission:cache-reset

# Criar nova permissão via tinker
php artisan tinker
>>> Permission::create(['name' => 'invoicing.nova.permissao']);

# Listar todas as permissões
>>> Permission::all()->pluck('name');

# Listar todos os roles
>>> Role::with('permissions')->get();

# Ver permissões de um utilizador
>>> User::find(1)->getAllPermissions();

# Ver roles de um utilizador
>>> User::find(1)->getRoleNames();
```

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

- [x] Instalar Spatie Permission
- [x] Executar migrations
- [x] Criar seeder de permissões
- [x] Registrar middleware
- [x] Criar interface de gestão
- [x] Adicionar ao menu
- [x] Proteger rotas principais
- [x] Adicionar verificações em Blade
- [x] Adicionar verificações em Livewire
- [x] Documentar permissões

---

## 📚 RECURSOS ADICIONAIS

- [Documentação Oficial Spatie Permission](https://spatie.be/docs/laravel-permission)
- Aceder: `http://soserp.test/users/roles-permissions`
- Arquivo de Permissões: `database/seeders/PermissionsSeeder.php`

---

**Desenvolvido por:** Sistema SOSERP  
**Última Atualização:** Outubro 2025
