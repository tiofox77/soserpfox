# Arquivos Usados: Proformas de Venda

## 📁 Lista Completa de Arquivos

### **1. Models (Eloquent ORM)** 📊

```
app/Models/Invoicing/
├── SalesProforma.php                    ✅ 191 linhas
│   ├── Gera número automático (PRF A/2025/00001)
│   ├── Relacionamentos: client, warehouse, items, creator, invoices
│   ├── Método: convertToInvoice()
│   ├── Método: calculateTotals()
│   └── SoftDeletes + BelongsToTenant
│
└── SalesProformaItem.php                ✅ 78 linhas
    ├── Cálculos automáticos (subtotal, desconto, IVA, total)
    ├── Relacionamentos: proforma, product
    └── Recalcula totais ao salvar/deletar
```

**Relacionamentos Externos:**
```
app/Models/
├── Client.php                           (Cliente - hasMany proformas)
├── Product.php                          (Produto - hasMany items)
├── Tenant.php                           (Multi-tenant)
└── User.php                             (Criador - hasMany proformas)

app/Models/Invoicing/
├── Warehouse.php                        (Armazém - hasMany proformas)
├── InvoicingSeries.php                  (Séries - hasMany proformas)
├── SalesInvoice.php                     (Fatura - belongsTo proforma)
└── SalesInvoiceItem.php                 (Item fatura)

app/Models/Treasury/
└── Account.php                          (Contas bancárias - exibidas no PDF)
```

---

### **2. Componentes Livewire** ⚡

```
app/Livewire/Invoicing/Sales/
├── Proformas.php                        ✅ 218 linhas
│   ├── Listagem com paginação
│   ├── Filtros: search, status, warehouse, dateFrom, dateTo
│   ├── Estatísticas (total, draft, sent, accepted, total_amount)
│   ├── Métodos: confirmDelete, deleteProforma, convertToInvoice
│   ├── Métodos: showHistory, closeHistoryModal
│   ├── Métodos: viewProforma, closeViewModal
│   ├── Propriedades: showDeleteModal, showViewModal, showHistoryModal
│   └── Propriedades: selectedProforma, proformaHistory, relatedInvoices
│
└── ProformaCreate.php                   ✅ 352 linhas
    ├── Formulário de criação/edição
    ├── Carrinho de produtos
    ├── Cálculos automáticos (IVA, Descontos, IRT)
    ├── Validações
    ├── Método: save() - Criar/atualizar proforma
    ├── Método: addToCart() - Adicionar produto ao carrinho
    ├── Método: removeFromCart() - Remover produto
    ├── Método: updateQuantity() - Atualizar quantidade
    ├── Método: applyDiscount() - Aplicar desconto
    └── Propriedades: client_id, warehouse_id, proforma_date, valid_until, etc.
```

---

### **3. Views Blade** 🎨

#### **Pasta: `resources/views/livewire/invoicing/proformas-venda/`**

```
proformas.blade.php                      ✅ 293 linhas (arquivo principal)
├── Header com título e botão "Nova Proforma"
├── Flash messages (sucesso/erro)
├── Cards de estatísticas (5 cards)
├── Filtros (pesquisa, status, warehouse, datas)
├── Tabela de listagem
│   ├── Colunas: Número, Cliente, Data, Validade, Estado, Total, Ações
│   └── Ações: Ver, Preview PDF, Editar, Converter, Histórico, Eliminar
├── Paginação
└── Includes das modais:
    ├── @include('...delete-modal')
    ├── @include('...view-modal')
    └── @include('...history-modal')

create.blade.php                         ✅ 39.769 bytes
├── Formulário completo de criação/edição
├── Seleção de cliente e armazém
├── Data proforma e validade
├── Pesquisa de produtos
├── Carrinho de produtos
│   ├── Tabela com itens
│   ├── Quantidade, preço, desconto, IVA
│   └── Ações: remover, atualizar
├── Resumo de totais
│   ├── Total Bruto
│   ├── Desconto Comercial
│   ├── Valor Líquido
│   ├── Incidência IVA
│   ├── IVA
│   ├── IRT (Retenção)
│   └── Total Geral
├── Campos: Notas, Termos
└── Botões: Salvar, Cancelar

delete-modal.blade.php                   ✅ 22 linhas
├── Modal de confirmação de eliminação
├── Ícone vermelho (fa-trash)
├── Mensagem de aviso
└── Botões: Cancelar, Eliminar

view-modal.blade.php                     ✅ 167 linhas
├── Modal de visualização completa
├── Header roxo com número da proforma
├── Informações do cliente
│   ├── Nome, NIF, Email, Telefone
│   └── Endereço
├── Datas e Status
│   ├── Data da Proforma
│   ├── Válida Até
│   └── Status (badge colorido)
├── Lista de Produtos
│   ├── Tabela com todos os itens
│   └── Colunas: Produto, Qtd, Preço, Desc%, IVA, Total
├── Totais
│   ├── Subtotal
│   ├── Descontos
│   ├── IVA
│   ├── Retenção
│   └── TOTAL
├── Notas (se existir)
└── Botões: Fechar, Preview PDF

history-modal.blade.php                  ✅ 140 linhas
├── Modal de histórico de conversões
├── Header roxo com ícone de histórico
├── Info da Proforma Original
│   ├── Cliente, Data, Total, Status
│   └── Card roxo
├── Lista de Faturas Geradas
│   ├── Número da fatura
│   ├── Data de criação e criador
│   ├── Data fatura e vencimento
│   ├── Total
│   ├── Status (badge colorido)
│   └── Botão "Ver" (link para fatura)
├── Estado vazio (se sem faturas)
└── Botões: Converter Novamente, Fechar
```

---

### **4. Templates PDF** 📄

```
resources/views/pdf/invoicing/
├── proforma.blade.php                   ✅ Template principal
│   ├── Cabeçalho com logo e dados da empresa
│   ├── Dados do cliente
│   ├── Informações da proforma (número, data, validade)
│   ├── Tabela de produtos
│   │   ├── Descrição, Qtd, Preço, Desc%, IVA, Total
│   │   └── Totalizadores por linha
│   ├── Resumo de totais
│   │   ├── Subtotal, Descontos, IVA, IRT, Total
│   │   └── Total por extenso
│   ├── Notas e observações
│   ├── Contas bancárias (máx 4)
│   └── Rodapé com assinatura
│
├── proforma_test.blade.php              ⚠️ Template de teste
└── proforma.blade.php.bak               🗄️ Backup
```

---

### **5. Controllers** 🎮

```
app/Http/Controllers/Invoicing/
└── ProformaController.php               ✅ 91 linhas
    ├── generatePdf($id)
    │   ├── Busca proforma com relacionamentos
    │   ├── Busca tenant e contas bancárias
    │   ├── Gera PDF com DomPDF
    │   ├── Configurações: A4, portrait
    │   └── Retorna stream (visualização no navegador)
    │
    └── previewHtml($id)
        ├── Busca proforma com relacionamentos
        ├── Busca tenant e contas bancárias
        └── Retorna view HTML (sem gerar PDF)
```

---

### **6. Rotas** 🛣️

```
routes/web.php
└── Route::prefix('invoicing')->name('invoicing.')->group(function () {
    Route::prefix('sales')->name('sales.')->group(function () {
        
        // Listagem
        Route::get('/proformas', Proformas::class)
            ->name('proformas');
        
        // Criar
        Route::get('/proformas/create', ProformaCreate::class)
            ->name('proformas.create');
        
        // Editar
        Route::get('/proformas/{id}/edit', ProformaCreate::class)
            ->name('proformas.edit');
        
        // PDF (download)
        Route::get('/proformas/{id}/pdf', [ProformaController::class, 'generatePdf'])
            ->name('proformas.pdf');
        
        // Preview HTML
        Route::get('/proformas/{id}/preview', [ProformaController::class, 'previewHtml'])
            ->name('proformas.preview');
        
        // Teste
        Route::get('/proformas/{id}/pdf-test', function($id) { ... })
            ->name('proformas.pdf-test');
    });
});
```

**URLs Geradas:**
- `http://soserp.test/invoicing/sales/proformas` - Lista
- `http://soserp.test/invoicing/sales/proformas/create` - Criar
- `http://soserp.test/invoicing/sales/proformas/1/edit` - Editar
- `http://soserp.test/invoicing/sales/proformas/1/pdf` - PDF
- `http://soserp.test/invoicing/sales/proformas/1/preview` - Preview HTML

---

### **7. Migrations** 🗄️

```
database/migrations/
├── 2025_10_03_173657_create_invoicing_sales_proformas_table.php
│   ├── Cria tabela: invoicing_sales_proformas
│   ├── Campos principais: tenant_id, proforma_number, client_id, etc.
│   └── Índices e foreign keys
│
├── 2025_10_03_173857_create_invoicing_sales_proforma_items_table.php
│   ├── Cria tabela: invoicing_sales_proforma_items
│   ├── Campos: purchase_proforma_id, product_id, quantity, etc.
│   └── Foreign keys
│
├── 2025_10_04_112804_add_hash_to_sales_proformas_table.php
│   └── Adiciona coluna: saft_hash (SAFT-AO)
│
├── 2025_10_04_121750_fix_saft_hash_column_in_sales_proformas_table.php
│   └── Corrige tipo de coluna saft_hash
│
└── 2025_10_04_123433_add_series_id_to_invoicing_sales_proformas_table.php
    ├── Adiciona coluna: series_id
    └── Foreign key para invoicing_series
```

**Tabelas Criadas:**
- `invoicing_sales_proformas` - Proformas de venda
- `invoicing_sales_proforma_items` - Itens das proformas

---

### **8. Traits** 🔧

```
app/Traits/
└── BelongsToTenant.php
    ├── Usado em SalesProforma
    ├── Scope automático por tenant
    └── Filtro global de multi-tenancy
```

---

### **9. Helpers/Funções** 🛠️

```
Funções usadas:
├── activeTenantId()                     (Helper global - retorna tenant_id ativo)
├── number_format()                      (PHP nativo - formatação de números)
├── str_pad()                            (PHP nativo - padding de strings)
├── str_replace()                        (PHP nativo - substituição)
└── now()                                (Laravel - Carbon date helper)
```

---

### **10. Packages/Dependências** 📦

```
composer.json:
├── "barryvdh/laravel-dompdf": "^2.0"   → Geração de PDFs
├── "livewire/livewire": "^3.0"          → Framework Livewire
└── "laravel/framework": "^11.0"         → Framework Laravel

Pacotes JS/CSS:
├── TailwindCSS                          → Estilos/UI
├── Alpine.js                            → Interatividade frontend
└── FontAwesome                          → Ícones
```

---

## 📊 Resumo Quantitativo

| Tipo | Quantidade | Total Linhas |
|------|------------|--------------|
| **Models** | 2 | ~269 linhas |
| **Livewire Components** | 2 | ~570 linhas |
| **Views Blade** | 5 | ~40.400 bytes |
| **PDF Templates** | 1 | ~500 linhas |
| **Controllers** | 1 | 91 linhas |
| **Migrations** | 4 | ~200 linhas |
| **Rotas** | 6 | - |
| **TOTAL** | **21 arquivos** | **~42.000 linhas** |

---

## 🔗 Fluxo de Dados

```
┌─────────────────────────────────────────────────────────┐
│                  USUÁRIO ACESSA                         │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│         routes/web.php (Rotas Laravel)                  │
│  /invoicing/sales/proformas                             │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│    app/Livewire/Invoicing/Sales/Proformas.php          │
│    (Componente Livewire - Lógica)                       │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│         app/Models/Invoicing/SalesProforma.php          │
│         (Eloquent Model - Banco de Dados)               │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│  resources/views/livewire/invoicing/proformas-venda/    │
│  proformas.blade.php (View - Interface)                 │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│              Modals Separadas (Includes)                │
│  ├── delete-modal.blade.php                             │
│  ├── view-modal.blade.php                               │
│  └── history-modal.blade.php                            │
└─────────────────────────────────────────────────────────┘
```

### **Fluxo de Geração de PDF:**

```
Usuário clica "Preview" ou "PDF"
           ↓
routes/web.php → /proformas/{id}/pdf
           ↓
ProformaController@generatePdf()
           ↓
SalesProforma::with(['client', 'items', 'warehouse'])
           ↓
resources/views/pdf/invoicing/proforma.blade.php
           ↓
Barryvdh\DomPDF (Geração PDF)
           ↓
Retorna PDF para navegador
```

### **Fluxo de Conversão para Fatura:**

```
Usuário clica "Converter em Fatura"
           ↓
Proformas.php → convertToInvoice($id)
           ↓
SalesProforma::convertToInvoice()
           ↓
Cria SalesInvoice + SalesInvoiceItem
           ↓
Notifica usuário (sucesso)
           ↓
Redireciona para lista de faturas
```

---

## 🎯 Dependências Entre Arquivos

### **SalesProforma.php depende de:**
- ✅ Client (Model)
- ✅ Warehouse (Model)
- ✅ User (Model)
- ✅ SalesProformaItem (Model)
- ✅ SalesInvoice (Model)
- ✅ InvoicingSeries (Model)
- ✅ BelongsToTenant (Trait)

### **Proformas.php (Livewire) depende de:**
- ✅ SalesProforma (Model)
- ✅ Warehouse (Model)
- ✅ Client (Model)
- ✅ proformas.blade.php (View)
- ✅ delete-modal.blade.php (View)
- ✅ view-modal.blade.php (View)
- ✅ history-modal.blade.php (View)

### **ProformaCreate.php (Livewire) depende de:**
- ✅ SalesProforma (Model)
- ✅ SalesProformaItem (Model)
- ✅ Client (Model)
- ✅ Warehouse (Model)
- ✅ Product (Model)
- ✅ TaxRate (Model)
- ✅ create.blade.php (View)

### **ProformaController.php depende de:**
- ✅ SalesProforma (Model)
- ✅ Tenant (Model)
- ✅ Account (Treasury Model)
- ✅ Barryvdh\DomPDF
- ✅ proforma.blade.php (PDF Template)

---

## 📁 Estrutura Completa de Arquivos

```
soserp/
├── app/
│   ├── Http/Controllers/Invoicing/
│   │   └── ProformaController.php
│   ├── Livewire/Invoicing/Sales/
│   │   ├── Proformas.php
│   │   └── ProformaCreate.php
│   ├── Models/
│   │   ├── Client.php
│   │   ├── Product.php
│   │   ├── Tenant.php
│   │   ├── User.php
│   │   └── Invoicing/
│   │       ├── SalesProforma.php
│   │       ├── SalesProformaItem.php
│   │       ├── SalesInvoice.php
│   │       ├── SalesInvoiceItem.php
│   │       ├── Warehouse.php
│   │       └── InvoicingSeries.php
│   └── Traits/
│       └── BelongsToTenant.php
├── database/migrations/
│   ├── 2025_10_03_173657_create_invoicing_sales_proformas_table.php
│   ├── 2025_10_03_173857_create_invoicing_sales_proforma_items_table.php
│   ├── 2025_10_04_112804_add_hash_to_sales_proformas_table.php
│   ├── 2025_10_04_121750_fix_saft_hash_column_in_sales_proformas_table.php
│   └── 2025_10_04_123433_add_series_id_to_invoicing_sales_proformas_table.php
├── resources/views/
│   ├── livewire/invoicing/proformas-venda/
│   │   ├── proformas.blade.php
│   │   ├── create.blade.php
│   │   ├── delete-modal.blade.php
│   │   ├── view-modal.blade.php
│   │   └── history-modal.blade.php
│   └── pdf/invoicing/
│       └── proforma.blade.php
└── routes/
    └── web.php (seção de proformas)
```

---

**Total: 21 arquivos principais + dependências (Models externos, Traits, etc.) = ~30 arquivos envolvidos** 📊✨
