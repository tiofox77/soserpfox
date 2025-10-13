# ⚙️ Configurações de Software - Sistema de Bloqueio

## 📋 Visão Geral

Sistema de configurações globais do software que permite ao Super Admin controlar funcionalidades e restrições por módulo, incluindo bloqueio de eliminação de documentos fiscais.

---

## 🎯 Funcionalidades

### **Módulo de Faturação**

**Bloqueio de Eliminação de Documentos:**
- ✅ Faturas de Venda
- ✅ Proformas
- ✅ Recibos
- ✅ Notas de Crédito
- ✅ Faturas Recibo
- ✅ Faturas POS

**Comportamento:**
- Quando bloqueado → Apenas **anulação** permitida
- Quando permitido → **Eliminação** e anulação permitidas

---

## 🔧 Acesso

### **URL:**
```
/superadmin/software-settings
```

### **Permissão:**
- ⚠️ **Super Admin** apenas
- Middleware: `auth`, `superadmin`

### **Navegação:**
```
Super Admin → Configurações do Software
```

---

## 📊 Estrutura do Sistema

### **1. Tabela de Configurações**

**Tabela:** `software_settings`

```sql
- id (PK)
- module (invoicing, events, inventory, etc)
- setting_key (block_delete_sales_invoice, etc)
- setting_value (true/false)
- setting_type (boolean, string, json, integer)
- description
- is_active
- timestamps
```

**Índice único:** `(module, setting_key)`

### **2. Model**

**Classe:** `App\Models\SoftwareSetting`

**Métodos principais:**
```php
SoftwareSetting::get($module, $key, $default)
SoftwareSetting::set($module, $key, $value, $type, $description)
SoftwareSetting::isDeleteBlocked($documentType)
SoftwareSetting::getModuleSettings($module)
SoftwareSetting::clearCache()
```

### **3. Helpers**

**Funções disponíveis:**
```php
canDeleteDocument($documentType)     // true se pode eliminar
isDeleteBlocked($documentType)       // true se bloqueado
getBlockedDocuments()                // array de bloqueados
softwareSetting($module, $key, $default)  // obter valor
```

---

## 💻 Como Usar

### **1. Verificar se Pode Eliminar**

#### **Em Livewire Component:**
```php
use App\Models\SoftwareSetting;

class InvoiceManagement extends Component
{
    public function deleteInvoice($invoiceId)
    {
        // Verificar bloqueio
        if (SoftwareSetting::isDeleteBlocked('sales_invoice')) {
            session()->flash('error', 'Eliminação de faturas bloqueada. Use anulação.');
            return;
        }
        
        // Ou usando helper
        if (!canDeleteDocument('sales_invoice')) {
            session()->flash('error', 'Eliminação bloqueada pelo sistema.');
            return;
        }
        
        // Prosseguir com eliminação
        $invoice = SalesInvoice::findOrFail($invoiceId);
        $invoice->delete();
        
        session()->flash('success', 'Fatura eliminada com sucesso!');
    }
}
```

#### **Em Controller:**
```php
use App\Models\SoftwareSetting;

public function destroy($id)
{
    if (!canDeleteDocument('sales_invoice')) {
        return redirect()->back()->with('error', 'Eliminação bloqueada. Use anulação.');
    }
    
    $invoice = SalesInvoice::findOrFail($id);
    $invoice->delete();
    
    return redirect()->back()->with('success', 'Eliminado com sucesso!');
}
```

### **2. Mostrar/Ocultar Botão de Eliminar**

#### **Em Blade:**
```blade
@if(canDeleteDocument('sales_invoice'))
    <button wire:click="deleteInvoice({{ $invoice->id }})" 
            class="btn btn-danger btn-sm">
        <i class="fas fa-trash"></i> Eliminar
    </button>
@else
    <button disabled class="btn btn-secondary btn-sm" 
            title="Eliminação bloqueada pelo sistema">
        <i class="fas fa-lock"></i> Bloqueado
    </button>
@endif

{{-- Botão de anular sempre disponível --}}
<button wire:click="cancelInvoice({{ $invoice->id }})" 
        class="btn btn-warning btn-sm">
    <i class="fas fa-ban"></i> Anular
</button>
```

### **3. Mensagem Personalizada**

```blade
@if(isDeleteBlocked('sales_invoice'))
    <div class="alert alert-warning">
        <i class="fas fa-info-circle"></i>
        <strong>Informação:</strong> A eliminação de faturas está bloqueada pelo administrador do sistema.
        Use a opção <strong>"Anular"</strong> para invalidar documentos.
    </div>
@endif
```

---

## 🎨 Interface de Configuração

### **Layout:**

```
┌─────────────────────────────────────────────────────┐
│ ⚙️ Configurações do Software        🛡️ Super Admin │
├─────────────────────────────────────────────────────┤
│                                                     │
│  📋 MÓDULOS          │  ⚙️ CONFIGURAÇÕES            │
│  ─────────────────   │  ─────────────────────────   │
│  ✓ Faturação        │  🔒 Bloqueio de Eliminação   │
│  ○ Inventário       │                              │
│  ○ Eventos          │  ☑ Faturas de Venda          │
│  ○ Utilizadores     │  ☐ Proformas                 │
│                     │  ☑ Recibos                   │
│                     │  ☐ Notas de Crédito          │
│                     │  ☐ Faturas Recibo            │
│                     │  ☐ Faturas POS               │
│                     │                              │
│                     │  [Resetar] [Salvar]          │
└─────────────────────────────────────────────────────┘
```

### **Cards de Documento:**

Cada tipo de documento tem um card visual:

```
┌──────────────────────────────────┐
│ ☑ 📄 Faturas de Venda            │
│                                  │
│ Bloquear eliminação de faturas   │
│ de venda (Sales Invoices)        │
│                                  │
│ 🔴 BLOQUEADO                     │
└──────────────────────────────────┘
```

---

## 📝 Tipos de Documentos

| Código | Nome | Descrição |
|--------|------|-----------|
| `sales_invoice` | Faturas de Venda | Faturas emitidas para clientes |
| `proforma` | Proformas | Proformas de venda |
| `receipt` | Recibos | Recibos de pagamento |
| `credit_note` | Notas de Crédito | Notas de crédito |
| `invoice_receipt` | Faturas Recibo | Faturas que incluem recibo |
| `pos_invoice` | Faturas POS | Faturas de ponto de venda |

---

## 🔄 Fluxo de Uso

### **1. Super Admin Configura:**
```
Super Admin → Software Settings → Faturação
→ Ativar "Bloquear Faturas de Venda"
→ Salvar
```

### **2. Sistema Armazena:**
```sql
INSERT INTO software_settings
(module, setting_key, setting_value)
VALUES ('invoicing', 'block_delete_sales_invoice', 'true')
```

### **3. Cache Atualizado:**
```php
Cache::remember('software_setting_invoicing_block_delete_sales_invoice')
```

### **4. Usuário Tenta Eliminar:**
```php
canDeleteDocument('sales_invoice') // → false
```

### **5. Sistema Bloqueia:**
```
❌ Eliminação bloqueada pelo sistema
💡 Use a opção "Anular"
```

---

## 🧪 Exemplos Práticos

### **Exemplo 1: Componente Livewire Completo**

```php
<?php

namespace App\Livewire\Invoicing;

use Livewire\Component;
use App\Models\SalesInvoice;
use App\Models\SoftwareSetting;

class InvoiceList extends Component
{
    public function deleteInvoice($id)
    {
        // Verificar bloqueio global
        if (isDeleteBlocked('sales_invoice')) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Eliminação bloqueada. Use anulação.'
            ]);
            return;
        }
        
        // Verificar permissões do usuário
        if (!auth()->user()->can('delete invoices')) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Sem permissão para eliminar.'
            ]);
            return;
        }
        
        $invoice = SalesInvoice::findOrFail($id);
        
        // Verificar se já tem pagamentos
        if ($invoice->payments()->exists()) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Fatura tem pagamentos. Anule primeiro.'
            ]);
            return;
        }
        
        $invoice->delete();
        
        $this->dispatch('show-toast', [
            'type' => 'success',
            'message' => 'Fatura eliminada com sucesso!'
        ]);
    }
    
    public function cancelInvoice($id)
    {
        // Anulação sempre permitida
        $invoice = SalesInvoice::findOrFail($id);
        
        $invoice->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
        ]);
        
        $this->dispatch('show-toast', [
            'type' => 'success',
            'message' => 'Fatura anulada com sucesso!'
        ]);
    }
    
    public function render()
    {
        return view('livewire.invoicing.invoice-list', [
            'canDelete' => canDeleteDocument('sales_invoice'),
            'blockedDocs' => getBlockedDocuments(),
        ]);
    }
}
```

### **Exemplo 2: Blade Template**

```blade
<div>
    {{-- Avisos --}}
    @if(isDeleteBlocked('sales_invoice'))
        <div class="alert alert-info mb-3">
            <i class="fas fa-lock me-2"></i>
            <strong>Modo Bloqueado:</strong> 
            Eliminação de faturas desativada. Use anulação.
        </div>
    @endif
    
    {{-- Lista de Faturas --}}
    @foreach($invoices as $invoice)
        <div class="card mb-2">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <strong>{{ $invoice->invoice_number }}</strong>
                        <br>{{ $invoice->client_name }}
                    </div>
                    <div>
                        {{-- Anular (sempre disponível) --}}
                        @if($invoice->status !== 'cancelled')
                            <button wire:click="cancelInvoice({{ $invoice->id }})"
                                    class="btn btn-warning btn-sm">
                                <i class="fas fa-ban"></i> Anular
                            </button>
                        @endif
                        
                        {{-- Eliminar (se permitido) --}}
                        @if(canDeleteDocument('sales_invoice'))
                            <button wire:click="deleteInvoice({{ $invoice->id }})"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Confirma eliminação?')">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        @else
                            <span class="badge bg-secondary">
                                <i class="fas fa-lock"></i> Bloqueado
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
```

---

## 🔒 Segurança

### **1. Apenas Super Admin**
```php
Route::middleware(['auth', 'superadmin'])
```

### **2. Validação no Backend**
```php
// Sempre verificar no servidor, não só no frontend
if (!canDeleteDocument('sales_invoice')) {
    abort(403, 'Operação bloqueada');
}
```

### **3. Cache com TTL**
```php
Cache::remember('software_setting_...', 3600) // 1 hora
```

### **4. Logs de Alterações**
```php
// Implementar log de quem alterou configurações
Log::info('Software setting changed', [
    'admin' => auth()->user()->name,
    'module' => 'invoicing',
    'setting' => 'block_delete_sales_invoice',
    'old_value' => 'false',
    'new_value' => 'true',
]);
```

---

## 📊 Estatísticas e Monitoramento

### **Dashboard do Super Admin:**

```php
// Obter estatísticas de bloqueios
$blockedCount = count(getBlockedDocuments());
$totalSettings = SoftwareSetting::where('is_active', true)->count();
$invoicingSettings = SoftwareSetting::where('module', 'invoicing')->count();
```

### **Auditoria:**

```php
// Criar tabela de auditoria (opcional)
Schema::create('software_settings_audit', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id');
    $table->string('module');
    $table->string('setting_key');
    $table->string('old_value')->nullable();
    $table->string('new_value');
    $table->string('action'); // created, updated, deleted
    $table->timestamps();
});
```

---

## 🎯 Próximas Expansões

### **Módulos Futuros:**

1. **Inventário:**
   - Bloquear eliminação de produtos
   - Bloquear eliminação de categorias
   - Bloquear ajustes de stock

2. **Eventos:**
   - Bloquear eliminação de eventos confirmados
   - Bloquear alteração de eventos passados

3. **Utilizadores:**
   - Bloquear eliminação de admins
   - Requerer aprovação para alterações

### **Configurações Adicionais:**

```php
// Futuras configurações
'require_approval_for_cancellation' => boolean
'max_invoice_edit_days' => integer
'auto_backup_before_delete' => boolean
'send_notification_on_delete' => boolean
```

---

## ✅ Checklist de Implementação

- [x] Migration criada
- [x] Model SoftwareSetting criado
- [x] Livewire component criado
- [x] View criada
- [x] Rota adicionada
- [x] Helpers criados
- [x] Composer atualizado
- [x] Documentação completa
- [ ] Migration executada
- [ ] Composer dump-autoload
- [ ] Testar configurações
- [ ] Aplicar em controllers/livewire

---

## 🚀 Instalação

```bash
# 1. Executar migration
php artisan migrate

# 2. Atualizar autoload
composer dump-autoload

# 3. Limpar cache
php artisan cache:clear
php artisan config:clear

# 4. Acessar
/superadmin/software-settings
```

---

## 📚 Referências

**Arquivos Principais:**
- `database/migrations/2025_01_11_214700_create_software_settings_table.php`
- `app/Models/SoftwareSetting.php`
- `app/Livewire/SuperAdmin/SoftwareSettings.php`
- `resources/views/livewire/super-admin/software-settings.blade.php`
- `app/Helpers/SoftwareSettingsHelper.php`
- `routes/web.php`

**Documentação:**
- `docs/SOFTWARE-SETTINGS.md` (este arquivo)

---

**Última atualização:** 11/01/2025  
**Versão:** 1.0
