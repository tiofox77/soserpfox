# Análise: Proformas e Faturas de Compras

## 📊 Status Atual da Implementação

### ✅ **O Que Existe:**

#### **1. Models (100% Completos)**

```
app/Models/Invoicing/
├── PurchaseProforma.php         ✅ Modelo completo (187 linhas)
├── PurchaseProformaItem.php     ✅ Modelo completo (82 linhas)
├── PurchaseInvoice.php          ✅ Modelo completo (129 linhas)
├── PurchaseInvoiceItem.php      ✅ Modelo completo
├── PurchaseOrder.php            ✅ Modelo completo
└── PurchaseOrderItem.php        ✅ Modelo completo
```

#### **2. Componentes Livewire (Vazios)**

```
app/Livewire/Invoicing/Purchases/
├── Proformas.php                ⚠️ Apenas esqueleto (14 linhas)
├── ProformaCreate.php           ⚠️ Apenas esqueleto (14 linhas)
└── InvoiceCreate.php            ⚠️ Apenas esqueleto (14 linhas)
```

#### **3. Views (Vazias)**

```
resources/views/livewire/invoicing/purchases/
├── proformas.blade.php          ⚠️ Vazia (4 linhas)
├── proformas/                   📁 Pasta vazia
└── invoices/                    📁 Pasta vazia
```

---

## 📋 Detalhamento dos Models

### **PurchaseProforma.php** ✅

**Funcionalidades:**
- ✅ Geração automática de número: `PP-C 2025/000001`
- ✅ Relacionamento com `Supplier` (fornecedor)
- ✅ Relacionamento com `Warehouse` (armazém)
- ✅ Relacionamento com `PurchaseProformaItem` (itens)
- ✅ Relacionamento com `PurchaseInvoice` (fatura)
- ✅ Método `convertToInvoice()` - Converte para fatura
- ✅ Método `calculateTotals()` - Calcula totais
- ✅ Método `checkExpiration()` - Verifica expiração

**Campos:**
```php
'tenant_id'              // Multi-tenant
'proforma_number'        // PP-C 2025/000001
'supplier_id'            // FK para suppliers
'warehouse_id'           // FK para warehouses
'proforma_date'          // Data da proforma
'valid_until'            // Válida até (30 dias)
'status'                 // draft, sent, accepted, rejected, expired, converted
'is_service'             // Tipo: serviço ou produto
'subtotal'               // Subtotal
'tax_amount'             // IVA
'irt_amount'             // Retenção 6,5%
'discount_amount'        // Desconto
'discount_commercial'    // Desconto comercial
'discount_financial'     // Desconto financeiro
'total'                  // Total
'currency'               // Moeda (AOA)
'exchange_rate'          // Taxa de câmbio
'notes'                  // Notas
'terms'                  // Termos e condições
'created_by'             // Criado por
```

---

### **PurchaseProformaItem.php** ✅

**Funcionalidades:**
- ✅ Cálculo automático de subtotal
- ✅ Cálculo automático de desconto
- ✅ Cálculo automático de IVA
- ✅ Recalcula totais da proforma ao salvar/deletar

**Campos:**
```php
'purchase_proforma_id'   // FK para purchase_proformas
'product_id'             // FK para products
'description'            // Descrição do produto
'quantity'               // Quantidade
'unit'                   // Unidade (Unid, Kg, etc)
'unit_price'             // Preço unitário
'discount_percent'       // % desconto
'discount_amount'        // Valor desconto
'subtotal'               // Subtotal
'tax_rate'               // Taxa IVA
'tax_amount'             // Valor IVA
'total'                  // Total
'order'                  // Ordem
```

---

### **PurchaseInvoice.php** ✅

**Funcionalidades:**
- ✅ Geração automática de número: `FC 2025/000001`
- ✅ Relacionamento com `Supplier`
- ✅ Relacionamento com `PurchaseOrder`
- ✅ Relacionamento com itens

**Campos:**
```php
'tenant_id'
'purchase_order_id'      // FK para purchase_orders
'invoice_number'         // FC 2025/000001
'supplier_id'            // FK para suppliers
'warehouse_id'           // FK para warehouses
'invoice_date'           // Data fatura
'due_date'               // Data vencimento
'status'                 // draft, pending, paid, cancelled
'is_service'
'subtotal'
'tax_amount'
'irt_amount'
'discount_amount'
'discount_commercial'
'discount_financial'
'total'
'paid_amount'            // Valor pago
'currency'
'exchange_rate'
'notes'
'terms'
'created_by'
```

---

## ❌ O Que Falta Implementar

### **1. Componentes Livewire**

#### **Proformas.php** (Listagem)
```php
// FALTA IMPLEMENTAR:
- Listagem de proformas de compras
- Filtros (pesquisa, status, data, fornecedor)
- Paginação
- Estatísticas (total, draft, sent, etc)
- Ações: visualizar, editar, eliminar, converter
- Modals: delete, view, history
```

#### **ProformaCreate.php** (Criar/Editar)
```php
// FALTA IMPLEMENTAR:
- Formulário de criação
- Seleção de fornecedor (supplier)
- Seleção de armazém
- Carrinho de produtos
- Cálculo automático de totais
- Validações
- Salvar/atualizar
```

#### **InvoiceCreate.php** (Criar/Editar Fatura)
```php
// FALTA IMPLEMENTAR:
- Formulário de criação de fatura
- Similar ao ProformaCreate
- Campos adicionais: due_date, payment_method
```

---

### **2. Views Blade**

#### **Proformas de Compra:**
```
resources/views/livewire/invoicing/proformas-compra/
├── proformas.blade.php          ❌ Precisa criar
├── create.blade.php             ❌ Precisa criar
├── delete-modal.blade.php       ❌ Precisa criar
├── view-modal.blade.php         ❌ Precisa criar
└── history-modal.blade.php      ❌ Precisa criar
```

#### **Faturas de Compra:**
```
resources/views/livewire/invoicing/faturas-compra/
├── faturas.blade.php            ❌ Precisa criar
├── create.blade.php             ❌ Precisa criar
├── delete-modal.blade.php       ❌ Precisa criar
├── view-modal.blade.php         ❌ Precisa criar
└── history-modal.blade.php      ❌ Precisa criar
```

---

## 🔄 Diferenças: Vendas vs Compras

### **Vendas (Sales):**
```php
client_id              → Cliente
SalesProforma          → Proforma de venda
SalesInvoice           → Fatura de venda
proforma_number: PRF A/2025/00001
invoice_number: FT A/2025/00001
```

### **Compras (Purchases):**
```php
supplier_id            → Fornecedor
PurchaseProforma       → Proforma de compra
PurchaseInvoice        → Fatura de compra
proforma_number: PP-C 2025/000001
invoice_number: FC 2025/000001
```

---

## 📊 Comparação: Vendas (Pronto) vs Compras (Falta)

| Componente | Vendas | Compras |
|---|---|---|
| **Models** | ✅ 100% | ✅ 100% |
| **Proforma Listing** | ✅ Pronto | ❌ Vazio |
| **Proforma Create** | ✅ Pronto | ❌ Vazio |
| **Invoice Listing** | ⚠️ Parcial | ❌ Vazio |
| **Invoice Create** | ⚠️ Esqueleto | ❌ Vazio |
| **Modals** | ✅ Separadas | ❌ Não existem |
| **PDFs** | ✅ Pronto | ❌ Falta |
| **Rotas** | ✅ Configuradas | ❌ Falta |

---

## 🚀 Plano de Implementação

### **Fase 1: Proformas de Compra** 🔄

1. **Copiar estrutura de Vendas**
   - Copiar `app/Livewire/Invoicing/Sales/Proformas.php`
   - Ajustar para `Purchases/Proformas.php`
   - Trocar `Client` → `Supplier`
   - Trocar `SalesProforma` → `PurchaseProforma`

2. **Copiar views**
   - Copiar `proformas-venda/` → `proformas-compra/`
   - Ajustar textos (Cliente → Fornecedor)
   - Ajustar cores (roxo → laranja?)

3. **Copiar ProformaCreate**
   - Copiar componente Livewire
   - Copiar view create.blade.php
   - Ajustar campos e validações

---

### **Fase 2: Faturas de Compra** 🔄

1. **Copiar estrutura de Vendas**
   - Similar à Fase 1
   - Adicionar campos de pagamento

2. **Copiar views**
   - Estrutura similar

---

### **Fase 3: PDFs e Relatórios** 📄

1. **Templates PDF**
   - Copiar templates de vendas
   - Ajustar layout para compras

2. **Controllers PDF**
   - Criar controllers para gerar PDFs

---

### **Fase 4: Rotas e Menu** 🧭

1. **Rotas**
```php
Route::prefix('invoicing/purchases')->group(function () {
    Route::get('/proformas', Proformas::class)->name('purchases.proformas');
    Route::get('/proformas/create', ProformaCreate::class)->name('purchases.proformas.create');
    Route::get('/invoices', Invoices::class)->name('purchases.invoices');
    Route::get('/invoices/create', InvoiceCreate::class)->name('purchases.invoices.create');
});
```

2. **Menu**
```blade
{{-- COMPRAS --}}
<div class="px-3 mt-6 mb-2">
    <p class="text-xs font-semibold text-blue-300">COMPRAS</p>
</div>
<a href="{{ route('purchases.proformas') }}">
    <i class="fas fa-file-alt text-orange-400"></i> Proformas
</a>
<a href="{{ route('purchases.invoices') }}">
    <i class="fas fa-file-invoice text-red-400"></i> Faturas
</a>
```

---

## 🎯 Prioridades

### **Alta Prioridade:**
1. ✅ Models já prontos
2. 🔄 Proformas de Compra (listing + create)
3. 🔄 Faturas de Compra (listing + create)

### **Média Prioridade:**
4. 🔄 PDFs de compras
5. 🔄 Relatórios

### **Baixa Prioridade:**
6. ⏳ Integrações avançadas
7. ⏳ Dashboard específico

---

## 📝 Resumo Executivo

### **✅ Pronto:**
- Models completos com todas funcionalidades
- Relacionamentos configurados
- Métodos de cálculo automático
- Conversão de proforma → fatura

### **❌ Falta:**
- Componentes Livewire (lógica)
- Views Blade (interface)
- Templates PDF
- Rotas
- Menu

### **📊 Complexidade:**
- **Baixa** → Copiar de vendas e ajustar
- **Tempo estimado:** 4-6 horas
- **Arquivos a criar:** ~15 arquivos

---

## 🔗 Relacionamentos do Sistema

```
┌─────────────────────────────────────────────┐
│         PROFORMAS DE COMPRA                 │
├─────────────────────────────────────────────┤
│                                             │
│  PurchaseProforma                           │
│  ├── supplier_id → Supplier                 │
│  ├── warehouse_id → Warehouse               │
│  ├── items → PurchaseProformaItem[]         │
│  └── convertToInvoice() → PurchaseInvoice  │
│                                             │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│          FATURAS DE COMPRA                  │
├─────────────────────────────────────────────┤
│                                             │
│  PurchaseInvoice                            │
│  ├── proforma_id → PurchaseProforma         │
│  ├── supplier_id → Supplier                 │
│  ├── warehouse_id → Warehouse               │
│  ├── items → PurchaseInvoiceItem[]          │
│  └── purchaseOrder → PurchaseOrder          │
│                                             │
└─────────────────────────────────────────────┘
```

---

**Base sólida (Models) 100% pronta! Falta apenas a camada de apresentação (Livewire + Views). 🎯**
