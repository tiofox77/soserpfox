# Sistema de Recibos - Resumo de Implementação

**Data:** 04/10/2025  
**Status:** 70% Completo  
**Funcional:** ✅ Listagem funcionando

---

## ✅ ARQUIVOS CRIADOS (6 de 9)

### 1. **Migration** `2025_10_04_202704_create_invoicing_receipts_table.php`
**Status:** ✅ Rodada com sucesso  
**Localização:** `database/migrations/`

**Campos principais:**
- `receipt_number` - Número único (RV/2025/001 para vendas, RC/2025/001 para compras)
- `type` - sale ou purchase
- `invoice_id` - FK para fatura (opcional)
- `client_id` / `supplier_id` - FK para cliente ou fornecedor
- `payment_date` - Data do pagamento
- `payment_method` - Método (cash, transfer, multicaixa, tpa, etc)
- `amount_paid` - Valor pago
- `reference` - Referência bancária
- `status` - issued ou cancelled
- `saft_hash` - Hash AGT Angola

---

### 2. **Model** `app/Models/Invoicing/Receipt.php`
**Status:** ✅ Completo com todas funcionalidades  
**Linhas:** 206

**Recursos implementados:**
- ✅ Trait `BelongsToTenant` para multi-tenancy
- ✅ Relacionamentos: client, supplier, invoice, creator
- ✅ Geração automática de número (RV/RC + Ano + Sequencial)
- ✅ Método `updateInvoiceStatus()` - Atualiza status da fatura automaticamente
- ✅ Método `cancel()` - Cancela recibo e reverte status da fatura
- ✅ Scopes úteis: `sales()`, `purchases()`, `issued()`, `cancelled()`
- ✅ Accessors: `entity_name`, `payment_method_label`, `status_label`, `status_color`
- ✅ Observer no boot: Gera número e atualiza fatura ao criar

**Regras de negócio:**
```php
// Ao criar recibo:
1. Gera número automaticamente (RV/2025/0001)
2. Se tiver invoice_id, atualiza status da fatura:
   - Total pago >= Total fatura → status = 'paid'
   - Total pago parcial → status = 'partially_paid'
   - Sem pagamento → status = 'pending'

// Ao cancelar recibo:
1. Status = cancelled
2. Recalcula status da fatura
```

---

### 3. **Componente Livewire** `app/Livewire/Invoicing/Receipts/Receipts.php`
**Status:** ✅ Completo  
**Linhas:** 141

**Funcionalidades:**
- ✅ Listagem paginada de recibos
- ✅ Filtros: search, tipo, status, data de/até
- ✅ Stats: total, vendas, compras, valor total
- ✅ Ações: visualizar, eliminar, cancelar
- ✅ Modais: delete, view
- ✅ Notificações toastr

**Query otimizada:**
```php
Receipt::with(['client', 'supplier', 'invoice', 'creator'])
    ->where('tenant_id', activeTenantId())
    ->orderBy('payment_date', 'desc')
    ->paginate()
```

---

### 4. **View Blade** `resources/views/livewire/invoicing/receipts/receipts.blade.php`
**Status:** ✅ Completa e funcional  
**Linhas:** 134

**Elementos:**
- ✅ Header com título e descrição
- ✅ 4 Stats cards (Total, Vendas, Compras, Valor)
- ✅ Filtros (pesquisa, tipo, status, datas)
- ✅ Tabela responsiva com colunas:
  - Número
  - Tipo (badge colorido)
  - Cliente/Fornecedor
  - Valor
  - Data
  - Status
  - Ações (visualizar, eliminar)
- ✅ Modal de confirmação delete
- ✅ Paginação
- ✅ Estado vazio (quando não há recibos)

---

### 5. **Rotas** `routes/web.php`
**Status:** ✅ Rota index funcionando

```php
Route::prefix('receipts')->name('receipts.')->group(function () {
    Route::get('/', \App\Livewire\Invoicing\Receipts\Receipts::class)->name('index');
    // TODO: Descomentar quando ReceiptCreate estiver pronto
    // Route::get('/create', ...);
    // Route::get('/{id}/edit', ...);
    // Route::get('/{id}/pdf', ...);
    // Route::get('/{id}/preview', ...);
});
```

**URL Funcional:** `http://soserp.test/invoicing/receipts`

---

### 6. **Menu** `resources/views/layouts/app.blade.php`
**Status:** ✅ Link adicionado ao submenu Documentos

**Localização no menu:**
```
📄 Faturação
  └── 📋 Documentos
      ├── Proformas Venda
      ├── Faturas Venda
      ├── Proformas Compra
      ├── Faturas Compra
      └── 🧾 Recibos ⭐ NOVO
```

**Cor tema:** Azul (`text-blue-400`)  
**Ícone:** `fa-receipt`

---

## ⏳ ARQUIVOS PENDENTES (3 de 9)

### 1. **Componente ReceiptCreate.php** ❌
**Localização:** `app/Livewire/Invoicing/Receipts/ReceiptCreate.php`  
**Status:** NÃO CRIADO

**Estrutura sugerida:**
```php
class ReceiptCreate extends Component
{
    public $receiptId = null;
    public $isEdit = false;
    
    // Campos do formulário
    public $type = 'sale';
    public $client_id = '';
    public $supplier_id = '';
    public $invoice_id = '';
    public $payment_date;
    public $payment_method = 'cash';
    public $amount_paid = 0;
    public $reference = '';
    public $notes = '';
    
    protected $rules = [
        'type' => 'required|in:sale,purchase',
        'client_id' => 'required_if:type,sale',
        'supplier_id' => 'required_if:type,purchase',
        'payment_date' => 'required|date',
        'payment_method' => 'required',
        'amount_paid' => 'required|numeric|min:0',
    ];
    
    public function save() {
        $this->validate();
        
        Receipt::create([
            'tenant_id' => activeTenantId(),
            'type' => $this->type,
            'client_id' => $this->client_id,
            'supplier_id' => $this->supplier_id,
            'invoice_id' => $this->invoice_id,
            'payment_date' => $this->payment_date,
            'payment_method' => $this->payment_method,
            'amount_paid' => $this->amount_paid,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'status' => 'issued',
            'created_by' => auth()->id(),
        ]);
        
        return redirect()->route('invoicing.receipts.index');
    }
}
```

---

### 2. **View create.blade.php** ❌
**Localização:** `resources/views/livewire/invoicing/receipts/create.blade.php`  
**Status:** NÃO CRIADA

**Estrutura sugerida:**
```html
<div class="p-6">
    <h1>Novo Recibo</h1>
    
    <form wire:submit.prevent="save">
        <!-- Tipo (Venda/Compra) -->
        <select wire:model.live="type">
            <option value="sale">Venda</option>
            <option value="purchase">Compra</option>
        </select>
        
        <!-- Cliente (se venda) -->
        @if($type === 'sale')
            <select wire:model="client_id">
                @foreach($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
            </select>
        @endif
        
        <!-- Fornecedor (se compra) -->
        @if($type === 'purchase')
            <select wire:model="supplier_id">
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
        @endif
        
        <!-- Fatura Relacionada (opcional) -->
        <select wire:model="invoice_id">
            <option value="">Sem fatura associada</option>
            @foreach($invoices as $invoice)
                <option value="{{ $invoice->id }}">
                    {{ $invoice->invoice_number }} - {{ number_format($invoice->total, 2) }} AOA
                </option>
            @endforeach
        </select>
        
        <!-- Valor Pago -->
        <input type="number" wire:model="amount_paid" step="0.01" placeholder="Valor">
        
        <!-- Método de Pagamento -->
        <select wire:model="payment_method">
            <option value="cash">Dinheiro</option>
            <option value="transfer">Transferência</option>
            <option value="multicaixa">Multicaixa</option>
            <option value="tpa">TPA</option>
            <option value="check">Cheque</option>
            <option value="mbway">MB Way</option>
        </select>
        
        <!-- Data do Pagamento -->
        <input type="date" wire:model="payment_date">
        
        <!-- Referência (ex: nº transferência) -->
        <input type="text" wire:model="reference" placeholder="Referência">
        
        <!-- Observações -->
        <textarea wire:model="notes" placeholder="Observações"></textarea>
        
        <button type="submit">Salvar Recibo</button>
    </form>
</div>
```

---

### 3. **Controller ReceiptController.php** ❌
**Localização:** `app/Http/Controllers/Invoicing/ReceiptController.php`  
**Status:** NÃO CRIADO

**Estrutura sugerida (copiar de SalesInvoiceController):**
```php
<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\Receipt;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    public function generatePdf($id)
    {
        $receipt = Receipt::with(['client', 'supplier', 'invoice'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        $tenant = Tenant::find(activeTenantId());
        
        $pdf = Pdf::loadView('pdf.invoicing.receipt', [
            'receipt' => $receipt,
            'tenant' => $tenant,
        ]);
        
        $pdf->setPaper('A4', 'portrait');
        
        $filename = 'recibo_' . str_replace(['/', '\\'], '_', $receipt->receipt_number) . '.pdf';
        return $pdf->stream($filename);
    }
    
    public function previewHtml($id)
    {
        $receipt = Receipt::with(['client', 'supplier', 'invoice'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        $tenant = Tenant::find(activeTenantId());
        
        return view('pdf.invoicing.receipt', [
            'receipt' => $receipt,
            'tenant' => $tenant,
        ]);
    }
}
```

---

### 4. **Template PDF** ❌
**Localização:** `resources/views/pdf/invoicing/receipt.blade.php`  
**Status:** NÃO CRIADO

**Estrutura sugerida:**
- Dados da empresa (logotipo, nome, NIF)
- Título: "RECIBO" em destaque
- Número do recibo
- Dados do cliente/fornecedor
- Dados da fatura relacionada (se houver)
- Valor pago (destaque)
- Método de pagamento
- Data do pagamento
- Referência
- Observações
- Assinatura/Carimbo

---

## 🧪 TESTE RÁPIDO

**URL:** `http://soserp.test/invoicing/receipts`

**O que você verá:**
- ✅ Tela de listagem completa
- ✅ Stats cards (vazios se não houver recibos)
- ✅ Filtros funcionais
- ✅ Tabela responsiva
- ⚠️ Botão "Novo Recibo" ainda não funciona (falta ReceiptCreate)

---

## 📋 PRÓXIMOS PASSOS

### **Opção A - Completar Manualmente:**
1. Criar `ReceiptCreate.php` baseado em `InvoiceCreate.php` (simplificado)
2. Criar `create.blade.php` com formulário simples
3. Criar `ReceiptController.php` copiando de `SalesInvoiceController.php`
4. Criar template PDF simples
5. Descomentar rotas em `web.php`

### **Opção B - Próxima Sessão:**
Eu crio os 3 arquivos faltantes na próxima conversa.

---

## 💡 FUNCIONALIDADES IMPLEMENTADAS

✅ **Listagem de Recibos** com filtros avançados  
✅ **Stats em tempo real** (total, vendas, compras, valor)  
✅ **Integração automática com Faturas** (atualiza status)  
✅ **Geração automática de números** (RV/RC + Ano + Seq)  
✅ **Cancelamento** com reversão de status  
✅ **Multi-tenancy** completo  
✅ **Soft deletes** implementado  
✅ **AGT Angola** SAFT Hash preparado  

---

## 🎯 STATUS FINAL

| Item | Status | Pronto para Uso |
|------|--------|----------------|
| Database | ✅ 100% | ✅ Sim |
| Model | ✅ 100% | ✅ Sim |
| Listagem | ✅ 100% | ✅ Sim |
| Criação | ⚠️ 0% | ❌ Não |
| Edição | ⚠️ 0% | ❌ Não |
| PDF | ⚠️ 0% | ❌ Não |
| Menu | ✅ 100% | ✅ Sim |
| Rotas | ⚠️ 50% | ✅ Parcial |

**Progresso Geral:** 70% ✅

---

## 📝 COMANDOS ÚTEIS

```bash
# Limpar cache
php artisan optimize:clear

# Ver rotas de recibos
php artisan route:list | grep receipts

# Testar Model no tinker
php artisan tinker
>>> Receipt::count()
>>> Receipt::generateReceiptNumber('sale')
```

---

**Criado em:** 04/10/2025 21:35  
**Próxima ação:** Criar arquivos faltantes ou completar manualmente
