# SAFT-AO 2025 - Standard Audit File for Tax Angola

## 📋 Visão Geral

O SAFT-AO (Standard Audit File for Tax - Angola) é o padrão XML exigido pela AGT (Administração Geral Tributária) para exportação de documentos fiscais em Angola.

## 🔑 Campos Obrigatórios para Faturas

### **1. Identificação do Documento**
- `invoice_number` - Número único do documento (ex: FT 2025/000001)
- `atcud` - Código Único do Documento (gerado pela AGT)
- `invoice_type` - Tipo de documento:
  - **FT** - Fatura
  - **FR** - Fatura-Recibo
  - **FS** - Fatura Simplificada
  - **NC** - Nota de Crédito
  - **ND** - Nota de Débito
  - **GT** - Guia de Transporte
  - **VD** - Venda a Dinheiro
  - **PP** - Pagamento Prévio (Proforma)

### **2. Estado do Documento**
- `invoice_status` - Estado atual:
  - **N** - Normal
  - **A** - Anulado
  - **F** - Finalizado
- `invoice_status_date` - Data e hora do estado
- `source_id` - ID do utilizador que criou/modificou
- `source_billing` - Software de faturação (ex: SOSERP/1.0)

### **3. Datas**
- `invoice_date` - Data de emissão
- `system_entry_date` - Data de entrada no sistema

### **4. Assinatura Digital**
- `hash` - Assinatura digital do documento
- `hash_control` - Controlo (versão: 1)
- `hash_previous` - Hash do documento anterior (para cadeia de integridade)

### **5. Cliente**
- `customer_id` - NIF do cliente
- `customer_tax_id` - Número de identificação fiscal
- Campos do cliente (nome, morada, etc.)

### **6. Totais**
- `net_total` - Total sem impostos
- `gross_total` - Total com impostos
- `tax_payable` - Imposto a pagar

### **7. Impostos (IVA)**
Taxas de IVA em Angola (2025):
- **Regime Geral**: 14%
- **Regime Simplificado**: Isento
- **Bens de Primeira Necessidade**: Isento
- **Exportação**: 0%

## 📝 Séries de Documentos

Cada tipo de documento deve ter sua própria série:
- **FT 2025/** - Faturas
- **FR 2025/** - Faturas-Recibo
- **NC 2025/** - Notas de Crédito
- **PP 2025/** - Proformas

## 🔐 Hash e Assinatura

O hash é calculado usando a fórmula:
```
hash = RSA-SHA256(
    invoice_date +
    system_entry_date +
    invoice_number +
    gross_total +
    hash_previous
)
```

## 📊 Estrutura XML SAFT-AO

```xml
<AuditFile>
    <Header>
        <AuditFileVersion>1.01_01</AuditFileVersion>
        <CompanyID>NIF_EMPRESA</CompanyID>
        <TaxRegistrationNumber>NIF_EMPRESA</TaxRegistrationNumber>
        <TaxAccountingBasis>F</TaxAccountingBasis>
        <CompanyName>Nome da Empresa</CompanyName>
        <BusinessName>Nome Comercial</BusinessName>
        <CompanyAddress>...</CompanyAddress>
        <FiscalYear>2025</FiscalYear>
        <StartDate>2025-01-01</StartDate>
        <EndDate>2025-12-31</EndDate>
        <CurrencyCode>AOA</CurrencyCode>
        <DateCreated>2025-10-03</DateCreated>
        <TaxEntity>Global</TaxEntity>
        <ProductCompanyTaxID>NIF_SOFTWARE</ProductCompanyTaxID>
        <SoftwareCertificateNumber>0</SoftwareCertificateNumber>
        <ProductID>SOSERP/1.0</ProductID>
        <ProductVersion>1.0</ProductVersion>
    </Header>
    <MasterFiles>
        <Customer>...</Customer>
        <Supplier>...</Supplier>
        <Product>...</Product>
        <TaxTable>...</TaxTable>
    </MasterFiles>
    <SourceDocuments>
        <SalesInvoices>
            <NumberOfEntries>100</NumberOfEntries>
            <TotalDebit>1000000.00</TotalDebit>
            <TotalCredit>0.00</TotalCredit>
            <Invoice>
                <InvoiceNo>FT 2025/000001</InvoiceNo>
                <ATCUD>ATCUD123456789</ATCUD>
                <DocumentStatus>
                    <InvoiceStatus>N</InvoiceStatus>
                    <InvoiceStatusDate>2025-10-03T10:30:00</InvoiceStatusDate>
                    <SourceID>User001</SourceID>
                    <SourceBilling>SOSERP/1.0</SourceBilling>
                </DocumentStatus>
                <Hash>HASH_VALUE</Hash>
                <HashControl>1</HashControl>
                <InvoiceDate>2025-10-03</InvoiceDate>
                <InvoiceType>FT</InvoiceType>
                <SystemEntryDate>2025-10-03T10:30:00</SystemEntryDate>
                <CustomerID>999999999</CustomerID>
                <Line>...</Line>
                <DocumentTotals>
                    <TaxPayable>14000.00</TaxPayable>
                    <NetTotal>100000.00</NetTotal>
                    <GrossTotal>114000.00</GrossTotal>
                </DocumentTotals>
            </Invoice>
        </SalesInvoices>
    </SourceDocuments>
</AuditFile>
```

## ✅ Campos a Adicionar nas Migrations

### invoicing_sales_invoices:
```php
$table->string('atcud')->nullable(); // Código Único
$table->string('invoice_type', 2)->default('FT'); // FT, FR, FS, etc.
$table->enum('invoice_status', ['N', 'A', 'F'])->default('N'); // Normal, Anulado, Finalizado
$table->timestamp('invoice_status_date')->nullable();
$table->string('source_id')->nullable(); // User ID
$table->string('source_billing')->default('SOSERP/1.0');
$table->text('hash')->nullable(); // Assinatura digital
$table->string('hash_control')->default('1');
$table->string('hash_previous')->nullable();
$table->timestamp('system_entry_date')->nullable();
$table->decimal('net_total', 15, 2)->default(0); // Total sem IVA
$table->decimal('gross_total', 15, 2)->default(0); // Total com IVA
$table->decimal('tax_payable', 15, 2)->default(0); // IVA a pagar
```

### invoicing_purchase_invoices:
```php
// Mesmos campos acima
```

## 📦 Campos a Adicionar nos Models

```php
protected $fillable = [
    // Campos existentes +
    'atcud',
    'invoice_type',
    'invoice_status',
    'invoice_status_date',
    'source_id',
    'source_billing',
    'hash',
    'hash_control',
    'hash_previous',
    'system_entry_date',
    'net_total',
    'gross_total',
    'tax_payable',
];

protected $casts = [
    // Casts existentes +
    'invoice_status_date' => 'datetime',
    'system_entry_date' => 'datetime',
    'net_total' => 'decimal:2',
    'gross_total' => 'decimal:2',
    'tax_payable' => 'decimal:2',
];
```

## 🔧 Métodos Importantes

### Gerar Hash
```php
public function generateHash()
{
    $previousHash = self::where('tenant_id', $this->tenant_id)
        ->where('id', '<', $this->id)
        ->orderBy('id', 'desc')
        ->value('hash') ?? '';
    
    $dataToHash = sprintf(
        "%s;%s;%s;%.2f;%s",
        $this->invoice_date->format('Y-m-d'),
        $this->system_entry_date->format('Y-m-dTH:i:s'),
        $this->invoice_number,
        $this->gross_total,
        $previousHash
    );
    
    // Implementar assinatura RSA-SHA256
    $this->hash = hash('sha256', $dataToHash);
    $this->hash_previous = $previousHash;
}
```

### Validar NIF Angola
```php
public static function validateNIF($nif)
{
    // NIF em Angola tem 9 ou 14 dígitos
    return preg_match('/^\d{9}(\d{5})?$/', $nif);
}
```

## 📅 Regras de Negócio

1. **Numeração Sequencial**: Não pode haver gaps na numeração
2. **Data Retroativa**: Limitada a 30 dias
3. **Anulação**: Apenas faturas não pagas podem ser anuladas
4. **Hash**: Deve ser calculado antes de gravar
5. **ATCUD**: Deve ser obtido da AGT antes de emitir

## 🚀 Próximos Passos

1. ✅ Criar migration para adicionar campos SAFT-AO
2. ✅ Atualizar models com novos campos
3. ✅ Criar service para geração de SAFT-AO XML
4. ✅ Criar validações de NIF
5. ✅ Implementar geração de hash
6. ✅ Criar interface de faturação
