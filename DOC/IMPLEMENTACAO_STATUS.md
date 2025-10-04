# Status da Implementação - Faturas e Compras

## ✅ Concluído

### **1. Estrutura de Banco de Dados** ✅
```
✅ Migrations criadas e executadas:
   - add_series_and_hash_to_sales_invoices_table
   - add_series_and_hash_to_purchase_proformas_table
   - add_series_and_hash_to_purchase_invoices_table

✅ Campos adicionados:
   - series_id (relacionamento com invoicing_series)
   - saft_hash (TEXT para hash SAFT-AO)
```

### **2. Componentes Livewire** ✅
```
✅ Criados:
   app/Livewire/Invoicing/Sales/InvoiceCreate.php
   app/Livewire/Invoicing/Purchases/ProformaCreate.php
   app/Livewire/Invoicing/Purchases/InvoiceCreate.php

✅ Views criadas:
   resources/views/livewire/invoicing/sales/invoice-create.blade.php
   resources/views/livewire/invoicing/purchases/proforma-create.blade.php
   resources/views/livewire/invoicing/purchases/invoice-create.blade.php
```

### **3. Documentação** ✅
```
✅ FATURAS_E_COMPRAS_IMPLEMENTACAO.md - Arquitetura completa
✅ IMPLEMENTACAO_STATUS.md - Este arquivo
```

---

## 🔄 Pendente (Para Você Completar)

### **1. Implementar Lógica dos Componentes**

#### **InvoiceCreate (Vendas)**
Copiar de `ProformaCreate.php` e ajustar:

```php
// Principais ajustes:
- Trocar SalesProforma por SalesInvoice
- Trocar SalesProformaItem por SalesInvoiceItem  
- Adicionar campo: due_date (data vencimento)
- Adicionar campo: payment_method
- Status: 'draft', 'pending', 'paid', 'cancelled'
```

#### **PurchaseProformaCreate**
Copiar de `Sales/ProformaCreate.php` e ajustar:

```php
// Principais ajustes:
- Trocar client_id por supplier_id
- Trocar $clients por $suppliers
- Model: PurchaseProforma
- Model Item: PurchaseProformaItem
- Série padrão: 'purchase_proforma'
```

#### **PurchaseInvoiceCreate**
Copiar de `Sales/ProformaCreate.php` e ajustar:

```php
// Principais ajustes:
- Trocar client_id por supplier_id
- Model: PurchaseInvoice
- Model Item: PurchaseInvoiceItem
- Adicionar: due_date, payment_method
- Série padrão: 'purchase_invoice'
```

### **2. Criar Template PDF para Faturas**

Criar: `resources/views/pdf/invoicing/invoice.blade.php`

**Copiar de:** `proforma.blade.php` e adicionar:
- Selo "FATURA" mais destacado
- Campo "Data Vencimento"
- Campo "Método de Pagamento"
- Informações bancárias destacadas

### **3. Criar Controllers PDF**

```php
// app/Http/Controllers/Invoicing/InvoiceController.php
class InvoiceController extends Controller
{
    public function generatePdf($id) {
        // Similar ao ProformaController
        // Usar template: pdf.invoicing.invoice
    }
    
    public function previewHtml($id) {
        // Retornar view HTML
    }
}

// app/Http/Controllers/Invoicing/PurchaseProformaController.php
// app/Http/Controllers/Invoicing/PurchaseInvoiceController.php
```

### **4. Adicionar Rotas**

Adicionar em `routes/web.php`:

```php
// Faturas de Venda
Route::prefix('invoicing/sales')->name('invoicing.sales.')->group(function () {
    Route::get('/invoices', \App\Livewire\Invoicing\Sales\Invoices::class)->name('invoices');
    Route::get('/invoices/create', \App\Livewire\Invoicing\Sales\InvoiceCreate::class)->name('invoices.create');
    Route::get('/invoices/{id}/edit', \App\Livewire\Invoicing\Sales\InvoiceCreate::class)->name('invoices.edit');
    Route::get('/invoices/{id}/pdf', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'generatePdf'])->name('invoices.pdf');
    Route::get('/invoices/{id}/preview', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'previewHtml'])->name('invoices.preview');
});

// Proformas de Compra
Route::prefix('invoicing/purchases')->name('invoicing.purchases.')->group(function () {
    Route::get('/proformas', \App\Livewire\Invoicing\Purchases\Proformas::class)->name('proformas');
    Route::get('/proformas/create', \App\Livewire\Invoicing\Purchases\ProformaCreate::class)->name('proformas.create');
    Route::get('/proformas/{id}/edit', \App\Livewire\Invoicing\Purchases\ProformaCreate::class)->name('proformas.edit');
    Route::get('/proformas/{id}/pdf', [\App\Http\Controllers\Invoicing\PurchaseProformaController::class, 'generatePdf'])->name('proformas.pdf');
    Route::get('/proformas/{id}/preview', [\App\Http\Controllers\Invoicing\PurchaseProformaController::class, 'previewHtml'])->name('proformas.preview');
});

// Faturas de Compra
Route::prefix('invoicing/purchases')->name('invoicing.purchases.')->group(function () {
    Route::get('/invoices', \App\Livewire\Invoicing\Purchases\Invoices::class)->name('invoices');
    Route::get('/invoices/create', \App\Livewire\Invoicing\Purchases\InvoiceCreate::class)->name('invoices.create');
    Route::get('/invoices/{id}/edit', \App\Livewire\Invoicing\Purchases\InvoiceCreate::class)->name('invoices.edit');
    Route::get('/invoices/{id}/pdf', [\App\Http\Controllers\Invoicing\PurchaseInvoiceController::class, 'generatePdf'])->name('invoices.pdf');
    Route::get('/invoices/{id}/preview', [\App\Http\Controllers\Invoicing\PurchaseInvoiceController::class, 'previewHtml'])->name('invoices.preview');
});
```

### **5. Atualizar Menu**

Editar: `resources/views/layouts/app.blade.php`

```blade
{{-- VENDAS --}}
<div class="px-3 mt-6 mb-2">
    <p x-show="sidebarOpen" class="text-xs font-semibold text-blue-300 uppercase">Vendas</p>
</div>

<a href="{{ route('invoicing.sales.proformas') }}" class="flex items-center px-4 py-3...">
    <i class="fas fa-file-alt w-6 text-purple-400"></i>
    <span x-show="sidebarOpen" class="ml-3">Proformas</span>
</a>

<a href="{{ route('invoicing.sales.invoices') }}" class="flex items-center px-4 py-3...">
    <i class="fas fa-file-invoice w-6 text-blue-400"></i>
    <span x-show="sidebarOpen" class="ml-3">Faturas</span>
</a>

{{-- COMPRAS --}}
<div class="px-3 mt-6 mb-2">
    <p x-show="sidebarOpen" class="text-xs font-semibold text-blue-300 uppercase">Compras</p>
</div>

<a href="{{ route('invoicing.purchases.proformas') }}" class="flex items-center px-4 py-3...">
    <i class="fas fa-file-alt w-6 text-orange-400"></i>
    <span x-show="sidebarOpen" class="ml-3">Proformas</span>
</a>

<a href="{{ route('invoicing.purchases.invoices') }}" class="flex items-center px-4 py-3...">
    <i class="fas fa-file-invoice w-6 text-red-400"></i>
    <span x-show="sidebarOpen" class="ml-3">Faturas</span>
</a>
```

### **6. Atualizar Modelos**

#### **SalesInvoice.php**
```php
protected $fillable = [
    'series_id',     // ← Adicionar
    'saft_hash',     // ← Adicionar
    // ... resto dos campos
];

public function series() {
    return $this->belongsTo(InvoicingSeries::class, 'series_id');
}

protected static function boot() {
    parent::boot();
    
    static::creating(function ($invoice) {
        // Gerar número usando série (tipo: 'invoice')
        // Gerar hash SAFT-AO
    });
}
```

#### **PurchaseProforma.php e PurchaseInvoice.php**
Similar ao acima, ajustando tipo de série.

---

## 📋 Checklist de Implementação

### Componentes Livewire:
- [ ] Copiar lógica para InvoiceCreate (Sales)
- [ ] Copiar lógica para ProformaCreate (Purchases)
- [ ] Copiar lógica para InvoiceCreate (Purchases)
- [ ] Ajustar campos (client_id vs supplier_id)
- [ ] Ajustar modelos (Sales vs Purchase)

### PDFs:
- [ ] Criar template invoice.blade.php
- [ ] Adicionar campo data vencimento
- [ ] Adicionar campo método pagamento
- [ ] Destacar informações bancárias

### Controllers:
- [ ] Criar InvoiceController
- [ ] Criar PurchaseProformaController
- [ ] Criar PurchaseInvoiceController

### Rotas:
- [ ] Adicionar rotas de vendas/faturas
- [ ] Adicionar rotas de compras/proformas
- [ ] Adicionar rotas de compras/faturas

### Menu:
- [ ] Adicionar seção VENDAS
- [ ] Adicionar seção COMPRAS
- [ ] Separar visualmente

### Modelos:
- [ ] Atualizar SalesInvoice (series + hash)
- [ ] Atualizar PurchaseProforma (series + hash)
- [ ] Atualizar PurchaseInvoice (series + hash)

### Séries:
- [ ] Criar série padrão: Fatura Venda (FT)
- [ ] Criar série padrão: Proforma Compra (PRC)
- [ ] Criar série padrão: Fatura Compra (FTC)

---

## 🎯 Resultado Final

Quando concluído, terá:

```
VENDAS:
├── Proformas (PRF A/2025/00001) ✅
└── Faturas (FT A/2025/00001)    🔄

COMPRAS:
├── Proformas (PRC A/2025/00001) 🔄
└── Faturas (FTC A/2025/00001)   🔄
```

Todos com:
- ✅ Séries configuráveis
- ✅ Hash SAFT-AO automático
- ✅ PDFs profissionais
- ✅ Numeração sequencial

---

## 📝 Próximo Passo Imediato

1. **Copiar e adaptar** `ProformaCreate.php` para os 3 novos componentes
2. **Criar** template PDF de fatura
3. **Adicionar** rotas
4. **Atualizar** menu
5. **Testar** criação de documentos

---

**Base estrutural 100% pronta! Agora é copiar lógica e ajustar campos.** 🚀
