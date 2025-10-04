# Sistema de Faturação SOSERP - Conformidade SAFT-AO 2025

## ✅ Estrutura Implementada

### **1. Tabelas de Banco de Dados**

#### **Vendas (Sales)**
- ✅ `invoicing_sales_proformas` - Proformas de Venda
- ✅ `invoicing_sales_proforma_items` - Itens das Proformas de Venda
- ✅ `invoicing_sales_invoices` - Faturas de Venda (com campos SAFT-AO)
- ✅ `invoicing_sales_invoice_items` - Itens das Faturas de Venda

#### **Compras (Purchases)**
- ✅ `invoicing_purchase_proformas` - Proformas de Compra
- ✅ `invoicing_purchase_proforma_items` - Itens das Proformas de Compra
- ✅ `invoicing_purchase_invoices` - Faturas de Compra (com campos SAFT-AO)
- ✅ `invoicing_purchase_invoice_items` - Itens das Faturas de Compra

### **2. Campos SAFT-AO Adicionados**

Todos os campos obrigatórios para conformidade com SAFT-AO 2025:

```php
// Identificação
'atcud'                  // Código Único do Documento (AGT)
'invoice_type'           // FT, FR, FS, NC, ND
'invoice_number'         // Número sequencial

// Estado
'invoice_status'         // N (Normal), A (Anulado), F (Finalizado)
'invoice_status_date'    // Data/hora do estado

// Assinatura Digital
'hash'                   // SHA-256 do documento
'hash_control'           // Versão (sempre "1")
'hash_previous'          // Hash do documento anterior

// Controle
'source_id'              // ID do utilizador
'source_billing'         // Software (SOSERP/1.0)
'system_entry_date'      // Data de entrada no sistema

// Totais
'net_total'              // Total sem IVA
'tax_payable'            // IVA a pagar
'gross_total'            // Total com IVA
```

### **3. Models Criados/Atualizados**

#### **Vendas**
- ✅ `App\Models\Invoicing\SalesProforma`
- ✅ `App\Models\Invoicing\SalesProformaItem`
- ✅ `App\Models\Invoicing\SalesInvoice` (atualizado com SAFT-AO)
- ✅ `App\Models\Invoicing\SalesInvoiceItem`

#### **Compras**
- ✅ `App\Models\Invoicing\PurchaseProforma` (NOVO)
- ✅ `App\Models\Invoicing\PurchaseProformaItem` (NOVO)
- ✅ `App\Models\Invoicing\PurchaseInvoice` (precisa atualização SAFT-AO)
- ✅ `App\Models\Invoicing\PurchaseInvoiceItem`

## 🔧 Métodos Importantes Implementados

### **SalesInvoice**

```php
// Gerar hash SHA-256 para SAFT-AO
$invoice->generateHash();

// Finalizar fatura (calcula totais, gera hash)
$invoice->finalizeInvoice();

// Anular fatura
$invoice->cancelInvoice('Motivo do cancelamento');

// Validar NIF Angola (9 ou 14 dígitos)
SalesInvoice::validateNIF('123456789');
```

### **PurchaseProforma**

```php
// Converter proforma em fatura
$invoice = $proforma->convertToInvoice();

// Verificar expiração
$proforma->checkExpiration();

// Calcular totais
$proforma->calculateTotals();
```

## 📋 Tipos de Documentos (invoice_type)

Conforme SAFT-AO 2025:

| Código | Descrição | Uso |
|--------|-----------|-----|
| **FT** | Fatura | Documento fiscal completo |
| **FR** | Fatura-Recibo | Fatura com recibo de pagamento |
| **FS** | Fatura Simplificada | Vendas a retalho < 100.000 Kz |
| **NC** | Nota de Crédito | Devolução/anulação parcial |
| **ND** | Nota de Débito | Correção a maior |
| **GT** | Guia de Transporte | Movimentação de mercadorias |
| **PP** | Pagamento Prévio | Proforma (não fiscal) |

## 💰 Taxas de IVA em Angola (2025)

| Tipo | Taxa | Aplicação |
|------|------|-----------|
| **Regime Geral** | 14% | Produtos e serviços padrão |
| **Isento** | 0% | Bens de primeira necessidade |
| **Exportação** | 0% | Vendas para fora de Angola |

## 🔢 Numeração de Documentos

### **Vendas**
- **Proformas**: `PP-V 2025/000001`
- **Faturas**: `FT 2025/000001`
- **Faturas-Recibo**: `FR 2025/000001`
- **Notas de Crédito**: `NC 2025/000001`

### **Compras**
- **Proformas**: `PP-C 2025/000001`
- **Faturas**: `FT-C 2025/000001`

## 🚀 Próximos Passos

### **1. Backend (Prioritário)**

- [ ] Atualizar `PurchaseInvoice` model com campos SAFT-AO
- [ ] Criar Service `SaftAoExportService` para gerar XML
- [ ] Criar Controller `InvoicingController`
- [ ] Criar API endpoints para:
  - Listar proformas/faturas
  - Criar nova proforma
  - Converter proforma em fatura
  - Finalizar fatura
  - Anular fatura
  - Exportar SAFT-AO XML

### **2. Frontend (Livewire)**

- [ ] Criar componente `SalesProformas` (lista + CRUD)
- [ ] Criar componente `SalesInvoices` (lista + CRUD)
- [ ] Criar componente `PurchaseProformas` (lista + CRUD)
- [ ] Criar componente `PurchaseInvoices` (lista + CRUD)
- [ ] Criar modal de seleção de produtos com grid
- [ ] Criar modal de visualização de documento (PDF preview)
- [ ] Implementar botão "Exportar SAFT-AO"

### **3. Validações**

- [ ] Validação de NIF (9 ou 14 dígitos)
- [ ] Validação de numeração sequencial
- [ ] Validação de datas retroativas (máx 30 dias)
- [ ] Validação de anulação (apenas não pagas)
- [ ] Validação de conversão proforma (não duplicada)

### **4. Integrações**

- [ ] Integração com AGT para obter ATCUD
- [ ] Geração de PDF dos documentos
- [ ] Envio de email com documento anexo
- [ ] Dashboard de faturação (gráficos)

### **5. Relatórios**

- [ ] Relatório de vendas por período
- [ ] Relatório de compras por período
- [ ] Relatório de IVA a pagar/receber
- [ ] Extrato de cliente/fornecedor
- [ ] Análise de margem de lucro

## 📂 Estrutura de Arquivos Sugerida

```
app/Livewire/Invoicing/
├── Sales/
│   ├── Proformas.php                    # Lista de proformas de venda
│   ├── ProformaCreate.php               # Criar/editar proforma
│   ├── Invoices.php                     # Lista de faturas de venda
│   └── InvoiceCreate.php                # Criar/editar fatura
├── Purchase/
│   ├── Proformas.php                    # Lista de proformas de compra
│   ├── ProformaCreate.php               # Criar/editar proforma
│   ├── Invoices.php                     # Lista de faturas de compra
│   └── InvoiceCreate.php                # Criar/editar fatura
└── Reports/
    ├── SalesReport.php                  # Relatório de vendas
    ├── PurchaseReport.php               # Relatório de compras
    └── TaxReport.php                    # Relatório de impostos

resources/views/livewire/invoicing/
├── sales/
│   ├── proformas.blade.php
│   ├── proforma-create.blade.php
│   ├── invoices.blade.php
│   ├── invoice-create.blade.php
│   └── partials/
│       ├── product-selector-modal.blade.php
│       ├── items-table.blade.php
│       └── document-preview-modal.blade.php
└── purchase/
    └── (mesma estrutura)

app/Services/
├── SaftAoExportService.php              # Geração de XML SAFT-AO
├── InvoicePdfService.php                # Geração de PDF
└── InvoiceEmailService.php              # Envio de emails
```

## 🎯 Fluxo de Trabalho

### **Vendas**
1. **Criar Proforma** → Cliente aprova
2. **Converter em Fatura** → Sistema gera numeração
3. **Finalizar Fatura** → Gera hash e ATCUD
4. **Registar Pagamento** → Atualiza status
5. **Exportar SAFT-AO** → XML para AGT

### **Compras**
1. **Criar Proforma** (Pedido) → Enviar ao fornecedor
2. **Receber Fatura do Fornecedor** → Registar no sistema
3. **Validar e Aprovar** → Conferir valores
4. **Registar Pagamento** → Atualiza status
5. **Dar Entrada no Stock** → Se aplicável

## ⚠️ Regras de Negócio Importantes

1. **Numeração Sequencial**: Não pode haver gaps
2. **Hash Obrigatório**: Todas as faturas finalizadas devem ter hash
3. **Data Limite**: Documentos retroativos até 30 dias
4. **Anulação**: Apenas faturas não pagas (parcial ou total)
5. **ATCUD**: Obrigatório para documentos fiscais a partir de 2025
6. **IVA**: Taxa padrão 14% em Angola
7. **Conversão Única**: Proforma só pode ser convertida uma vez

## 📖 Documentação de Referência

- **SAFT-AO 2025**: Ver `DOC/SAFT_AO_2025.md`
- **UI/UX Patterns**: Ver `DOC/UI_UX_PATTERNS.md`
- **AGT Website**: https://www.agt.minfin.gov.ao/
