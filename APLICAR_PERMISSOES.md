# 🚀 GUIA RÁPIDO - APLICAR PERMISSÕES NAS VIEWS

## ⚠️ STATUS ATUAL

**AS PERMISSÕES ESTÃO CRIADAS MAS AINDA NÃO APLICADAS NAS VIEWS!**

Para usar o sistema de permissões, você precisa adicionar as verificações nas views e componentes.

---

## ✅ PASSO A PASSO PARA APLICAR

### **1. PROTEGER BOTÕES E AÇÕES EM BLADE**

#### **ANTES (Sem Permissão):**
```blade
<button wire:click="openCreateModal" class="btn btn-primary">
    <i class="fas fa-plus mr-2"></i>Novo Cliente
</button>
```

#### **DEPOIS (Com Permissão):**
```blade
@can('invoicing.clients.create')
    <button wire:click="openCreateModal" class="btn btn-primary">
        <i class="fas fa-plus mr-2"></i>Novo Cliente
    </button>
@endcan
```

---

### **2. PROTEGER AÇÕES EM TABELAS**

#### **ANTES:**
```blade
<td class="text-center">
    <button wire:click="editClient({{ $client->id }})">Editar</button>
    <button wire:click="deleteClient({{ $client->id }})">Eliminar</button>
</td>
```

#### **DEPOIS:**
```blade
<td class="text-center">
    @can('invoicing.clients.edit')
        <button wire:click="editClient({{ $client->id }})">Editar</button>
    @endcan
    
    @can('invoicing.clients.delete')
        <button wire:click="deleteClient({{ $client->id }})">Eliminar</button>
    @endcan
</td>
```

---

### **3. PROTEGER MÉTODOS EM LIVEWIRE**

#### **ANTES:**
```php
public function deleteClient($clientId)
{
    $client = Client::findOrFail($clientId);
    $client->delete();
}
```

#### **DEPOIS:**
```php
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
}
```

---

### **4. PROTEGER ROTAS**

#### **ANTES:**
```php
Route::get('/invoicing/clients', \App\Livewire\Invoicing\Clients::class)
    ->name('invoicing.clients');
```

#### **DEPOIS:**
```php
Route::middleware(['auth', 'permission:invoicing.clients.view'])
    ->get('/invoicing/clients', \App\Livewire\Invoicing\Clients::class)
    ->name('invoicing.clients');
```

---

## 📋 LISTA DE PERMISSÕES POR MÓDULO

### **CLIENTES:**
- `invoicing.clients.view` - Ver listagem
- `invoicing.clients.create` - Botão "Novo Cliente"
- `invoicing.clients.edit` - Botão "Editar"
- `invoicing.clients.delete` - Botão "Eliminar"
- `invoicing.clients.export` - Botão "Exportar"

### **FORNECEDORES:**
- `invoicing.suppliers.view`
- `invoicing.suppliers.create`
- `invoicing.suppliers.edit`
- `invoicing.suppliers.delete`

### **PRODUTOS:**
- `invoicing.products.view`
- `invoicing.products.create`
- `invoicing.products.edit`
- `invoicing.products.delete`
- `invoicing.products.import`

### **FATURAS VENDA:**
- `invoicing.sales.invoices.view`
- `invoicing.sales.invoices.create`
- `invoicing.sales.invoices.edit`
- `invoicing.sales.invoices.delete`
- `invoicing.sales.invoices.pdf`
- `invoicing.sales.invoices.cancel`

### **POS:**
- `invoicing.pos.access` - Acesso ao POS
- `invoicing.pos.sell` - Realizar vendas
- `invoicing.pos.refund` - Fazer devoluções
- `invoicing.pos.reports` - Ver relatórios
- `invoicing.pos.settings` - Configurar

### **DASHBOARD:**
- `invoicing.dashboard.view`

### **TESOURARIA:**
- `treasury.accounts.view`
- `treasury.accounts.create`
- `treasury.transactions.view`
- `treasury.transactions.create`

---

## 🎯 ARQUIVOS PRINCIPAIS PARA MODIFICAR

### **1. Clientes:**
- `resources/views/livewire/invoicing/clients.blade.php`
- `app/Livewire/Invoicing/Clients.php`

### **2. Produtos:**
- `resources/views/livewire/invoicing/products/products.blade.php`
- `app/Livewire/Invoicing/Products/Products.php`

### **3. Faturas:**
- `resources/views/livewire/invoicing/sales/invoices.blade.php`
- `app/Livewire/Invoicing/Sales/Invoices.php`

### **4. POS:**
- `resources/views/livewire/pos/possystem.blade.php`
- `app/Livewire/Pos/POSSystem.php`

### **5. Dashboard:**
- `resources/views/livewire/invoicing/invoicing-dashboard.blade.php`
- `app/Livewire/Invoicing/InvoicingDashboard.php`

---

## 💡 DICAS PRÁTICAS

### **Dica 1: Super Admin Sempre Passa**
O Super Admin (`is_super_admin = true`) sempre tem todas as permissões automaticamente.

### **Dica 2: Verificação Múltipla**
```blade
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
```

### **Dica 3: Esconder Menu Inteiro**
```blade
@can('invoicing.dashboard.view')
    <a href="{{ route('invoicing.dashboard') }}">Dashboard</a>
@endcan
```

### **Dica 4: Passar para JS**
```blade
<script>
    const canCreate = @json(auth()->user()->can('invoicing.clients.create'));
    
    if (canCreate) {
        // Mostrar botão
    }
</script>
```

---

## ⚡ APLICAÇÃO RÁPIDA

### **Opção 1: Aplicar Manualmente** (Recomendado)
1. Abra cada arquivo blade
2. Adicione `@can` nos botões de ação
3. Teste no navegador

### **Opção 2: Aplicar em Lote** (Mais Rápido)
1. Use busca/substituição em massa
2. Busque padrões comuns
3. Adicione verificações

### **Opção 3: Progressiva** (Mais Seguro)
1. Comece pelos módulos críticos (Faturas, POS)
2. Depois secundários (Clientes, Produtos)
3. Por último configurações

---

## 🧪 TESTAR PERMISSÕES

### **1. Criar Utilizador Teste:**
1. Criar novo utilizador
2. Atribuir role "Vendedor"
3. Fazer login
4. Verificar se vê apenas o permitido

### **2. Verificar no Código:**
```php
// No tinker
User::find(2)->can('invoicing.clients.create'); // true/false
User::find(2)->getAllPermissions(); // Lista todas
```

### **3. Ver no Navegador:**
- Fazer login com utilizador limitado
- Botões não autorizados devem sumir
- Tentar acessar rota protegida = 403

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

### **Clientes:**
- [ ] Botão "Novo Cliente" com `@can('invoicing.clients.create')`
- [ ] Botão "Editar" com `@can('invoicing.clients.edit')`
- [ ] Botão "Eliminar" com `@can('invoicing.clients.delete')`
- [ ] Método deleteClient() com verificação

### **Produtos:**
- [ ] Botão "Novo Produto" com `@can('invoicing.products.create')`
- [ ] Botão "Editar" com `@can('invoicing.products.edit')`
- [ ] Botão "Eliminar" com `@can('invoicing.products.delete')`
- [ ] Botão "Importar" com `@can('invoicing.products.import')`

### **Faturas:**
- [ ] Botão "Nova Fatura" com `@can('invoicing.sales.invoices.create')`
- [ ] Botão "Editar" com `@can('invoicing.sales.invoices.edit')`
- [ ] Botão "PDF" com `@can('invoicing.sales.invoices.pdf')`
- [ ] Botão "Cancelar" com `@can('invoicing.sales.invoices.cancel')`

### **POS:**
- [ ] Acesso à página com `@can('invoicing.pos.access')`
- [ ] Botão "Finalizar Venda" com `@can('invoicing.pos.sell')`
- [ ] Botão "Devolução" com `@can('invoicing.pos.refund')`

### **Dashboard:**
- [ ] Rota protegida com `permission:invoicing.dashboard.view`
- [ ] Link no menu com `@can('invoicing.dashboard.view')`

---

## 🚀 APLICAR AGORA - EXEMPLO PRÁTICO

### **Arquivo: clients.blade.php**

```blade
<div>
    {{-- Header com Botão --}}
    <div class="flex justify-between items-center mb-4">
        <h2>Clientes</h2>
        
        @can('invoicing.clients.create')
            <button wire:click="openCreateModal" class="btn-primary">
                <i class="fas fa-plus"></i> Novo Cliente
            </button>
        @endcan
    </div>

    {{-- Tabela --}}
    <table>
        @foreach($clients as $client)
            <tr>
                <td>{{ $client->name }}</td>
                <td>
                    @can('invoicing.clients.edit')
                        <button wire:click="edit({{ $client->id }})">
                            <i class="fas fa-edit"></i>
                        </button>
                    @endcan
                    
                    @can('invoicing.clients.delete')
                        <button wire:click="delete({{ $client->id }})">
                            <i class="fas fa-trash"></i>
                        </button>
                    @endcan
                </td>
            </tr>
        @endforeach
    </table>
</div>
```

### **Arquivo: Clients.php (Livewire)**

```php
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
    
    // Lógica de eliminação
    $client = Client::findOrFail($clientId);
    $client->delete();
    
    $this->dispatch('notify', [
        'type' => 'success',
        'message' => 'Cliente eliminado com sucesso'
    ]);
}
```

---

## 📞 SUPORTE

**Documentação Completa:** `PERMISSIONS_GUIDE.md`

**Gestão de Permissões:** `http://soserp.test/users/roles-permissions`

**Tinker para Testes:**
```bash
php artisan tinker
>>> auth()->user()->can('invoicing.clients.create');
>>> auth()->user()->getAllPermissions();
```

---

**PRÓXIMO PASSO: APLICAR NAS SUAS VIEWS! 🚀**
