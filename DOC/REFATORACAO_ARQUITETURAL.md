# 🏗️ REFATORAÇÃO ARQUITETURAL - Sistema de Documentos

**Data:** 03 de Outubro de 2025  
**Versão:** 4.4.0  
**Tipo:** Breaking Change (Major Refactoring)

---

## 🎯 Objetivo

Refatorar o sistema de documentos de faturação de uma **tabela única com coluna `type`** para **tabelas separadas por tipo de documento**, seguindo as melhores práticas de arquitetura de banco de dados.

---

## ❌ Problema Anterior

### Arquitetura Antiga (Anti-Pattern)
```
invoicing_invoices
├─ id
├─ document_type (fatura_venda, fatura_compra, proforma_venda, proforma_compra)
├─ client_id (NULL para compras)
├─ supplier_id (NULL para vendas)
└─ ... muitos campos NULL irrelevantes
```

### Problemas:
- ❌ **Class Table Inheritance** (anti-pattern conhecido)
- ❌ Muitos campos NULL (desperdício de espaço)
- ❌ Lógica condicional complexa nos models
- ❌ Performance degradada (WHERE type = ?)
- ❌ Difícil manter integridade referencial
- ❌ Impossível ter constraints específicas por tipo

---

## ✅ Solução Implementada

### Nova Arquitetura (Tabelas Separadas)
```
VENDAS:
├─ invoicing_sales_proformas       → Proformas de Venda
│  └─ invoicing_sales_proforma_items
└─ invoicing_sales_invoices         → Faturas de Venda
   └─ invoicing_sales_invoice_items

COMPRAS:
├─ invoicing_purchase_orders        → Pedidos de Compra
│  └─ invoicing_purchase_order_items
└─ invoicing_purchase_invoices      → Faturas de Compra
   └─ invoicing_purchase_invoice_items
```

---

## 📦 Estrutura das Tabelas

### Tabelas de Cabeçalho (4)

#### 1. `invoicing_sales_proformas`
```sql
- id, tenant_id
- proforma_number (auto-gerado: PF 2025/000001)
- client_id (FK → invoicing_clients)
- warehouse_id (FK → invoicing_warehouses, nullable)
- proforma_date, valid_until
- status (draft, sent, accepted, rejected, expired, converted)
- subtotal, tax_amount, discount_amount, total
- currency, exchange_rate
- notes, terms
- created_by (FK → users)
- timestamps, soft_deletes
```

#### 2. `invoicing_sales_invoices`
```sql
- id, tenant_id
- proforma_id (FK → invoicing_sales_proformas, nullable)
- invoice_number (auto-gerado: FT 2025/000001)
- client_id (FK → invoicing_clients)
- warehouse_id (FK → invoicing_warehouses, nullable)
- invoice_date, due_date
- status (draft, sent, paid, partial, overdue, cancelled)
- subtotal, tax_amount, discount_amount, total, paid_amount
- currency, exchange_rate
- notes, terms
- created_by (FK → users)
- timestamps, soft_deletes
```

#### 3. `invoicing_purchase_orders`
```sql
- id, tenant_id
- order_number (auto-gerado: PC 2025/000001)
- supplier_id (FK → invoicing_suppliers)
- warehouse_id (FK → invoicing_warehouses, nullable)
- order_date, expected_date
- status (draft, sent, confirmed, received, cancelled)
- subtotal, tax_amount, discount_amount, total
- currency, exchange_rate
- notes, terms
- created_by (FK → users)
- timestamps, soft_deletes
```

#### 4. `invoicing_purchase_invoices`
```sql
- id, tenant_id
- purchase_order_id (FK → invoicing_purchase_orders, nullable)
- invoice_number (auto-gerado: FC 2025/000001)
- supplier_id (FK → invoicing_suppliers)
- warehouse_id (FK → invoicing_warehouses, nullable)
- invoice_date, due_date
- status (draft, pending, paid, overdue, cancelled)
- subtotal, tax_amount, discount_amount, total, paid_amount
- currency, exchange_rate
- notes, terms
- created_by (FK → users)
- timestamps, soft_deletes
```

### Tabelas de Items (4)

Todas seguem o mesmo padrão:
```sql
- id
- {document}_id (FK para documento pai)
- product_id (FK → invoicing_products)
- product_name (snapshot)
- description
- quantity, unit
- unit_price
- discount_percent, discount_amount
- subtotal
- tax_rate_id (FK → invoicing_tax_rates, nullable)
- tax_rate, tax_amount
- total
- order (para ordenação)
- timestamps
```

---

## 🎨 Models Criados

### Namespace: `App\Models\Invoicing`

#### 1. SalesProforma.php
```php
- Trait: BelongsToTenant, SoftDeletes
- Auto-gera: proforma_number (PF 2025/000001)
- Relacionamentos: client, items, creator, invoices
- Método: convertToInvoice() - converte para fatura
```

#### 2. SalesProformaItem.php
```php
- Auto-calcula totais no evento saving
- Relacionamentos: proforma, product, taxRate
- Método: calculateTotals()
```

#### 3. SalesInvoice.php
```php
- Trait: BelongsToTenant, SoftDeletes
- Auto-gera: invoice_number (FT 2025/000001)
- Relacionamentos: client, proforma, items, creator
- Método: calculateTotals()
- Accessor: balance (total - paid_amount)
```

#### 4. SalesInvoiceItem.php
```php
- Auto-calcula totais no evento saving
- Relacionamentos: invoice, product, taxRate
- Método: calculateTotals()
```

#### 5. PurchaseOrder.php
```php
- Trait: BelongsToTenant, SoftDeletes
- Auto-gera: order_number (PC 2025/000001)
- Relacionamentos: supplier, items, creator, invoices
- Método: calculateTotals()
```

#### 6. PurchaseOrderItem.php
```php
- Auto-calcula totais no evento saving
- Relacionamentos: purchaseOrder, product, taxRate
- Método: calculateTotals()
```

#### 7. PurchaseInvoice.php
```php
- Trait: BelongsToTenant, SoftDeletes
- Auto-gera: invoice_number (FC 2025/000001)
- Relacionamentos: supplier, purchaseOrder, items, creator
- Método: calculateTotals()
- Accessor: balance (total - paid_amount)
```

#### 8. PurchaseInvoiceItem.php
```php
- Auto-calcula totais no evento saving
- Relacionamentos: invoice, product, taxRate
- Método: calculateTotals()
```

---

## 📊 Cálculos Automáticos

### Nos Items (ao salvar):
```php
1. subtotal = quantity × unit_price
2. discount_amount = (subtotal × discount_percent) / 100
3. subtotal_after_discount = subtotal - discount_amount
4. tax_amount = (subtotal_after_discount × tax_rate) / 100
5. total = subtotal_after_discount + tax_amount
```

### Nos Documentos:
```php
1. subtotal = SUM(items.subtotal)
2. tax_amount = SUM(items.tax_amount)
3. total = subtotal + tax_amount - discount_amount
```

---

## 🔄 Fluxo de Conversão

### Proforma → Fatura
```php
$proforma = SalesProforma::find(1);
$invoice = $proforma->convertToInvoice();

// O que acontece:
1. Cria SalesInvoice vinculada à proforma
2. Copia todos os campos relevantes
3. Copia todos os items (produtos, quantidades, preços)
4. Marca proforma como 'converted'
5. Retorna a nova fatura
```

---

## 🎯 Vantagens

### 1. Performance
- ✅ Queries diretas sem WHERE type
- ✅ Índices mais eficientes
- ✅ Menos dados por tabela

### 2. Manutenção
- ✅ Código limpo e explícito
- ✅ Models específicos com lógica própria
- ✅ Fácil adicionar campos específicos

### 3. Integridade
- ✅ Constraints específicas por tipo
- ✅ Sem campos NULL desnecessários
- ✅ Relacionamentos claros

### 4. Escalabilidade
- ✅ Fácil adicionar novos tipos de documento
- ✅ Pode particionar/otimizar cada tabela
- ✅ Sistema cresce organizado

### 5. Clareza
- ✅ Nomenclatura auto-explicativa
- ✅ Relacionamentos óbvios
- ✅ Código autocomentado

---

## 📋 Migrations

### Criadas:
1. `2025_10_03_173854_create_invoicing_purchase_orders_table.php`
2. `2025_10_03_173855_create_invoicing_purchase_order_items_table.php`
3. `2025_10_03_173856_create_invoicing_purchase_invoice_items_table.php`
4. `2025_10_03_173857_create_invoicing_sales_proforma_items_table.php`
5. `2025_10_03_173858_create_invoicing_sales_invoice_items_table.php`

### Existentes (mantidas):
- `2025_10_03_173655_create_invoicing_purchase_invoices_table.php`
- `2025_10_03_173657_create_invoicing_sales_proformas_table.php`
- `2025_10_03_173659_create_invoicing_sales_invoices_table.php`

### Removidas:
- `2025_10_02_234000_create_invoicing_invoices_table.php` ❌
- `2025_10_02_234001_create_invoicing_invoice_items_table.php` ❌

---

## 🗑️ Limpeza Realizada

### Tabelas Removidas do BD:
```sql
DROP TABLE invoicing_invoice_items;
DROP TABLE invoicing_invoices;
```

### Models Removidos:
- `app/Models/InvoicingInvoice.php` ❌
- `app/Models/InvoicingInvoiceItem.php` ❌

### Migrations Duplicadas Removidas:
- Warehouses (2 duplicatas)
- Stock Movements (2 duplicatas)
- Purchases (2 duplicatas)

---

## 🚀 Próximos Passos

### 1. Interface (Livewire Components)
- [ ] `SalesProformas.php` - Gestão de Proformas
- [ ] `SalesInvoices.php` - Gestão de Faturas de Venda
- [ ] `PurchaseOrders.php` - Gestão de Pedidos de Compra
- [ ] `PurchaseInvoices.php` - Gestão de Faturas de Compra

### 2. Views
- [ ] Forms para criar/editar cada tipo
- [ ] Listagens com filtros avançados
- [ ] Botão de conversão Proforma → Fatura
- [ ] Preview antes de salvar

### 3. Funcionalidades
- [ ] Sistema de aprovação de pedidos
- [ ] Workflow de estados
- [ ] Notificações por email
- [ ] Histórico de alterações

### 4. Integração
- [ ] Vincular com Pagamentos (Treasury)
- [ ] Atualizar stock automaticamente
- [ ] Gerar movimentos contábeis
- [ ] Exportar para AGT Angola

### 5. Relatórios
- [ ] Relatório de Proformas
- [ ] Relatório de Faturas
- [ ] Análise de Compras
- [ ] Contas a Pagar/Receber

---

## 📖 Documentação Relacionada

- `DOC/ROADMAP.md` - Histórico completo do projeto
- `DOC/ISOLAMENTO_TENANT.md` - Multi-tenancy
- `DOC/MODALS_IMPLEMENTATION.md` - Padrões de UI

---

**Refatoração completa! Sistema pronto para próxima fase de desenvolvimento.**
