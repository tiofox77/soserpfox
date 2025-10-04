# Implementação de Faturas e Compras

## 📋 Estrutura Criada

### **1. Componentes Livewire**

```
app/Livewire/Invoicing/
├── Sales/
│   ├── ProformaCreate.php        ✅ Existente
│   └── InvoiceCreate.php         🆕 Criado
│
└── Purchases/
    ├── ProformaCreate.php        🆕 Criado
    └── InvoiceCreate.php         🆕 Criado
```

### **2. Modelos (Já Existentes)**

```
app/Models/Invoicing/
├── Sales
│   ├── SalesProforma.php         ✅
│   ├── SalesProformaItem.php     ✅
│   ├── SalesInvoice.php          ✅
│   └── SalesInvoiceItem.php      ✅
│
└── Purchases
    ├── PurchaseProforma.php      ✅
    ├── PurchaseProformaItem.php  ✅
    ├── PurchaseInvoice.php       ✅
    └── PurchaseInvoiceItem.php   ✅
```

### **3. PDFs a Criar**

```
resources/views/pdf/invoicing/
├── proforma.blade.php            ✅ Existente (Vendas e Compras)
├── invoice.blade.php             🆕 Criar (Vendas e Compras)
```

## 🔄 Lógica de Funcionamento

### **Proformas (Venda/Compra)**
- Usam mesmo template PDF: `proforma.blade.php`
- Diferença: Cliente vs Fornecedor
- Séries diferentes: PRF (venda) vs PRC (compra)

### **Faturas (Venda/Compra)**
- Usam mesmo template PDF: `invoice.blade.php`
- Diferença: Cliente vs Fornecedor  
- Séries diferentes: FT (venda) vs FTC (compra)

## 📊 Estrutura de Dados

### **Sales Invoice (Fatura de Venda)**
```php
invoicing_sales_invoices:
  - id
  - tenant_id
  - series_id           // Série do documento
  - invoice_number
  - client_id           // ← Cliente
  - warehouse_id
  - invoice_date
  - due_date
  - status
  - is_service
  - subtotal
  - tax_amount
  - irt_amount
  - total
  - saft_hash           // Hash SAFT-AO
  - ...
```

### **Purchase Proforma (Proforma de Compra)**
```php
invoicing_purchase_proformas:
  - id
  - tenant_id
  - series_id           // Série do documento
  - proforma_number
  - supplier_id         // ← Fornecedor
  - warehouse_id
  - proforma_date
  - delivery_date
  - status
  - subtotal
  - tax_amount
  - total
  - saft_hash           // Hash SAFT-AO
  - ...
```

### **Purchase Invoice (Fatura de Compra)**
```php
invoicing_purchase_invoices:
  - id
  - tenant_id
  - series_id           // Série do documento
  - invoice_number
  - supplier_id         // ← Fornecedor
  - warehouse_id
  - invoice_date
  - due_date
  - status
  - subtotal
  - tax_amount
  - total
  - saft_hash           // Hash SAFT-AO
  - ...
```

## 🎯 Diferenças Principais

### **Vendas vs Compras**

| Aspecto | Vendas | Compras |
|---------|--------|---------|
| **Entidade** | Cliente (`client_id`) | Fornecedor (`supplier_id`) |
| **Série Proforma** | PRF A/2025/00001 | PRC A/2025/00001 |
| **Série Fatura** | FT A/2025/00001 | FTC A/2025/00001 |
| **Fluxo** | Emissão → Cliente | Recebimento → Fornecedor |

### **Proformas vs Faturas**

| Aspecto | Proforma | Fatura |
|---------|----------|--------|
| **PDF** | `proforma.blade.php` | `invoice.blade.php` |
| **Status** | Pendente/Aprovado/Convertido | Rascunho/Pendente/Pago |
| **Campos** | Data Validade | Data Vencimento |
| **Função** | Orçamento | Documento Fiscal |

## 🚀 Rotas a Criar

```php
// Faturas de Venda
Route::get('/invoices/sales', SalesInvoices::class)->name('invoices.sales');
Route::get('/invoices/sales/create', SalesInvoiceCreate::class)->name('invoices.sales.create');
Route::get('/invoices/sales/{id}/edit', SalesInvoiceCreate::class)->name('invoices.sales.edit');
Route::get('/invoices/sales/{id}/pdf', [InvoiceController::class, 'generatePdf'])->name('invoices.sales.pdf');
Route::get('/invoices/sales/{id}/preview', [InvoiceController::class, 'previewHtml'])->name('invoices.sales.preview');

// Proformas de Compra
Route::get('/purchases/proformas', PurchaseProformas::class)->name('purchases.proformas');
Route::get('/purchases/proformas/create', PurchaseProformaCreate::class)->name('purchases.proformas.create');
Route::get('/purchases/proformas/{id}/edit', PurchaseProformaCreate::class)->name('purchases.proformas.edit');
Route::get('/purchases/proformas/{id}/pdf', [PurchaseProformaController::class, 'generatePdf'])->name('purchases.proformas.pdf');
Route::get('/purchases/proformas/{id}/preview', [PurchaseProformaController::class, 'previewHtml'])->name('purchases.proformas.preview');

// Faturas de Compra
Route::get('/purchases/invoices', PurchaseInvoices::class)->name('purchases.invoices');
Route::get('/purchases/invoices/create', PurchaseInvoiceCreate::class)->name('purchases.invoices.create');
Route::get('/purchases/invoices/{id}/edit', PurchaseInvoiceCreate::class)->name('purchases.invoices.edit');
Route::get('/purchases/invoices/{id}/pdf', [PurchaseInvoiceController::class, 'generatePdf'])->name('purchases.invoices.pdf');
Route::get('/purchases/invoices/{id}/preview', [PurchaseInvoiceController::class, 'previewHtml'])->name('purchases.invoices.preview');
```

## 📋 Menu a Atualizar

```
Faturação
├── 📊 Dashboard
├── 👥 Clientes
├── 🏭 Fornecedores
│
├── 📝 VENDAS
│   ├── Proformas        ✅ Existe
│   └── Faturas          🆕 Nova
│
├── 🛒 COMPRAS
│   ├── Proformas        🆕 Nova
│   └── Faturas          🆕 Nova
│
└── ⚙️ Configurações
```

## 🔧 Migrations Necessárias

### **1. Adicionar series_id e saft_hash**

```php
// Sales Invoices
Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
    $table->foreignId('series_id')->nullable()->after('tenant_id')
          ->constrained('invoicing_series')->nullOnDelete();
    $table->text('saft_hash')->nullable()->after('notes');
});

// Purchase Proformas  
Schema::table('invoicing_purchase_proformas', function (Blueprint $table) {
    $table->foreignId('series_id')->nullable()->after('tenant_id')
          ->constrained('invoicing_series')->nullOnDelete();
    $table->text('saft_hash')->nullable()->after('notes');
});

// Purchase Invoices
Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
    $table->foreignId('series_id')->nullable()->after('tenant_id')
          ->constrained('invoicing_series')->nullOnDelete();
    $table->text('saft_hash')->nullable()->after('notes');
});
```

## 📝 Tarefas Restantes

### ✅ Concluído:
- [x] Componentes Livewire criados
- [x] Estrutura de modelos existente
- [x] Documentação criada

### 🔄 Em Andamento:
- [ ] Implementar lógica dos componentes
- [ ] Criar templates PDF
- [ ] Adicionar rotas
- [ ] Atualizar menu
- [ ] Criar migrations
- [ ] Atualizar modelos com séries

### 📋 Próximos Passos:
1. Copiar lógica do ProformaCreate para novos components
2. Adaptar para cliente/fornecedor
3. Criar template PDF de faturas
4. Adicionar rotas
5. Atualizar menu
6. Testar fluxo completo

## 🎨 Templates PDF

### **Proforma (Compartilhado)**
- Vendas: Logo empresa + Dados cliente
- Compras: Logo empresa + Dados fornecedor
- Mesma estrutura visual

### **Fatura (Novo - Compartilhado)**
- Vendas: Logo empresa + Dados cliente
- Compras: Logo empresa + Dados fornecedor  
- Campos adicionais: Data vencimento, Método pagamento
- Selo "FATURA" mais destacado

## 🔐 Hash SAFT-AO

Todos os documentos fiscais devem ter hash:

```php
// Gerar para cada documento
$hash = SAFTHelper::generateHash(
    $document->date->format('Y-m-d'),
    $document->created_at->format('Y-m-d H:i:s'),
    $document->number,
    $document->total,
    $previousDocument->saft_hash ?? null
);

$document->saft_hash = $hash;
$document->save();
```

## 📊 Fluxo Completo

### **Vendas:**
```
1. Proforma → Cliente aprova → Converter em Fatura
2. Fatura → Emitir → Cliente paga → Marcar como pago
```

### **Compras:**
```
1. Proforma de Compra → Aprovar → Converter em Fatura
2. Fatura de Compra → Receber → Pagar → Marcar como pago
```

---

**Estrutura base criada. Próximos passos: implementar lógica e templates!** 🚀
