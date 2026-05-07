# SAFT-AO — Guia Completo de Conformidade

**Data:** 2026-02-28  
**Responsável:** Equipa SOSERP  
**Escopo:** Faturação Eletrónica, Assinatura Digital, Exportação SAFT-AO  
**Legislação:** Decreto Presidencial n.º 71/25 (20 Mar 2025) — Regime Jurídico das Faturas (RJF)

---

## 1. Enquadramento Legal

### 1.1 Decreto Presidencial n.º 71/25

| Fase | Data | Abrangência |
|------|------|-------------|
| Entrada em vigor | 20 Set 2025 | Legislação publicada |
| **Fase 1** | 1 Jan 2026 | Grandes contribuintes + fornecedores do Estado |
| **Fase 2** | 21 Set 2026 | Todos os contribuintes do regime geral/simplificado |

### 1.2 Obrigações Principais

1. **Assinatura digital** de todos os documentos fiscais (hash RSA-SHA256)
2. **QR Code** em cada documento emitido
3. **ATCUD** (Código Único de Documento) obrigatório
4. **Comunicação à AGT** dos documentos emitidos
5. **Exportação SAFT-AO** para auditorias fiscais
6. **Certificação do software** pela AGT
7. **Séries documentais** registadas na AGT

### 1.3 Prazos Obrigatórios

- Emissão da fatura: até **5 dias** após disponibilização dos bens/serviços (Art. 6º RJF)
- Anulação de documento: até **45 dias** após emissão (depois, apenas via Nota de Crédito)
- Comunicação à AGT: em **tempo real** ou no prazo regulamentado

---

## 2. Tipos de Documentos SAFT-AO

| Código | Tipo | Descrição | Modelo |
|--------|------|-----------|--------|
| **FT** | Fatura | Fatura normal de venda | `SalesInvoice` |
| **FR** | Fatura-Recibo | Fatura com pagamento imediato | `SalesInvoice` (invoice_type=FR) |
| **FS** | Fatura Simplificada | Até 25.000 AOA sem NIF | `SalesInvoice` (invoice_type=FS) |
| **NC** | Nota de Crédito | Anulação/rectificação de fatura | `CreditNote` |
| **ND** | Nota de Débito | Cobranças adicionais (juros, multas) | `DebitNote` |
| **RC** | Recibo | Comprovativo de pagamento | `Receipt` |
| **GR** | Guia de Remessa | Transporte de mercadorias | `StockMovement` |
| **PR** | Proforma | Orçamento (não fiscal) | `SalesProforma` |

### 2.1 Formato de Numeração AGT

```
[TIPO] [SÉRIE] [ANO]/[NÚMERO]
```

**Exemplos:**
- `FT A 2026/000001` — Fatura série A, nº 1 de 2026
- `NC A 2026/000045` — Nota de Crédito série A, nº 45
- `ND A 2026/000003` — Nota de Débito série A, nº 3

**Regras:**
- Numeração **sequencial**, sem lacunas
- Reset anual (configurável via `InvoicingSeries.reset_yearly`)
- Cada tipo de documento tem sua própria série
- Séries devem ser registadas na AGT antes de utilização

---

## 3. Assinatura Digital — Fluxo Completo

### 3.1 Arquitectura

```
┌──────────────────────────────────────────────────────────┐
│                    FLUXO DE ASSINATURA                    │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  1. GERAR CHAVES RSA-2048                                │
│     └─ SuperAdmin > SAFT Configuration                   │
│     └─ storage/app/saft/private_key.pem                  │
│     └─ storage/app/saft/public_key.pem                   │
│                                                          │
│  2. CRIAR DOCUMENTO                                      │
│     └─ Calcular totais (net_total, tax_payable,          │
│        gross_total)                                      │
│                                                          │
│  3. GERAR HASH SAFT-AO (RSA-SHA256)                      │
│     └─ Dados: Data;SysDate;Numero;GrossTotal[;HashAnt]   │
│     └─ Assinar com chave privada RSA                     │
│     └─ Codificar em Base64                               │
│                                                          │
│  4. GERAR ASSINATURA JWS                                 │
│     └─ Header: {"alg":"RS256","typ":"JWS"}               │
│     └─ Payload: dados do documento                       │
│     └─ Signature: RSA-SHA256                             │
│                                                          │
│  5. GERAR QR CODE                                        │
│     └─ Formato: A:NIF*B:NIF_CLI*D:TIPO*...              │
│                                                          │
│  6. SUBMETER À AGT (opcional, automático)                 │
│     └─ API REST OAuth 2.0                                │
│     └─ Obter ATCUD e referência AGT                      │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

### 3.2 Hash SAFT-AO — Especificação

**Dados a assinar (concatenados com `;`):**

```
InvoiceDate;SystemEntryDate;InvoiceNo;GrossTotal[;PreviousHash]
```

| Campo | Formato | Exemplo |
|-------|---------|---------|
| InvoiceDate | `YYYY-MM-DD` | `2026-01-15` |
| SystemEntryDate | `YYYY-MM-DD HH:MM:SS` | `2026-01-15 14:30:22` |
| InvoiceNo | Número completo | `FT A 2026/000001` |
| GrossTotal | Decimal 2 casas | `11400.00` |
| PreviousHash | Base64 do hash anterior | (vazio no 1º doc) |

**Algoritmo:**
1. Concatenar dados: `2026-01-15;2026-01-15 14:30:22;FT A 2026/000001;11400.00`
2. Assinar com **RSA-SHA256** usando chave privada
3. Codificar resultado em **Base64**
4. Guardar nos campos `hash` e `saft_hash`

**Campos obrigatórios no documento:**

| Campo | Descrição | Valor |
|-------|-----------|-------|
| `hash` | Hash completo Base64 | `aBcDeFg...` |
| `saft_hash` | Cópia do hash | Igual ao `hash` |
| `hash_previous` | Hash do documento anterior | Vazio no 1º |
| `hash_control` | Versão da chave usada | `1` |

### 3.3 Assinatura JWS (JSON Web Signature)

```json
// Header (Base64URL)
{"alg": "RS256", "typ": "JWS"}

// Payload (Base64URL)
{
  "iss": "NIF_EMISSOR",
  "iat": 1706000000,
  "doc": {
    "type": "FT",
    "number": "FT A 2026/000001",
    "date": "2026-01-15",
    "system_date": "2026-01-15T14:30:22",
    "customer_nif": "000000000",
    "gross_total": 11400.00,
    "net_total": 10000.00,
    "tax_amount": 1400.00
  },
  "hash": "aBcDeFg...",
  "hash_previous": ""
}

// Resultado: header.payload.signature
```

### 3.4 QR Code — Campos AGT

```
A:NIF_EMISSOR*B:NIF_CLIENTE*C:AO*D:FT*E:N*F:20260115*G:FTA2026/000001*
H:0-1*I1:AO*I2:10000.00*I3:1400.00*N:1400.00*O:11400.00*Q:aBcD*R:CERT
```

| Campo | Chave | Descrição |
|-------|-------|-----------|
| NIF Emissor | A | NIF da empresa (9 ou 14 dígitos) |
| NIF Cliente | B | NIF do cliente (`999999999` se anónimo) |
| País Cliente | C | Código país (`AO`) |
| Tipo Doc | D | FT, NC, ND, RC, etc. |
| Estado | E | N=Normal, A=Anulado, F=Facturado |
| Data | F | YYYYMMDD |
| Número | G | Número único sem espaços |
| ATCUD | H | Código único do documento |
| Espaço Fiscal | I1 | `AO` |
| Base IVA Normal | I2 | Base tributável a 14% |
| Total IVA Normal | I3 | IVA a 14% |
| Base Isenta | I4 | Base tributável isenta |
| Base Reduzida | I5 | Base tributável a 5%/7% |
| IVA Reduzido | I6 | Total IVA reduzido |
| Não Sujeito | I7 | Não sujeito a IVA |
| Retenção | I8 | IRT/Retenção na fonte |
| Total Impostos | N | Soma de todos os impostos |
| Total Doc | O | Total bruto do documento |
| Hash | Q | 4 primeiros caracteres do hash |
| Certificado | R | Nº certificado do software |

---

## 4. Exportação SAFT-AO (XML)

### 4.1 Estrutura do Ficheiro

```xml
<?xml version="1.0" encoding="UTF-8"?>
<AuditFile xmlns="urn:OECD:StandardAuditFile-Tax:AO_1.01_01">
  <Header>
    <AuditFileVersion>1.01_01</AuditFileVersion>
    <CompanyID>NIF_EMPRESA</CompanyID>
    <TaxRegistrationNumber>NIF_EMPRESA</TaxRegistrationNumber>
    <TaxAccountingBasis>F</TaxAccountingBasis>
    <CompanyName>Nome da Empresa</CompanyName>
    <CompanyAddress>...</CompanyAddress>
    <FiscalYear>2026</FiscalYear>
    <StartDate>2026-01-01</StartDate>
    <EndDate>2026-12-31</EndDate>
    <CurrencyCode>AOA</CurrencyCode>
    <DateCreated>2026-02-28</DateCreated>
    <TaxEntity>Global</TaxEntity>
    <ProductCompanyTaxID>NIF_SOFTWARE</ProductCompanyTaxID>
    <SoftwareCertificateNumber>CERT_NUM</SoftwareCertificateNumber>
    <ProductID>SOSERP/SOSERP</ProductID>
    <ProductVersion>1.0</ProductVersion>
  </Header>
  
  <MasterFiles>
    <TaxTable>
      <TaxTableEntry>
        <TaxType>IVA</TaxType>
        <TaxCountryRegion>AO</TaxCountryRegion>
        <TaxCode>NOR</TaxCode>
        <Description>IVA Normal 14%</Description>
        <TaxPercentage>14.00</TaxPercentage>
      </TaxTableEntry>
      <!-- ISE, RED, NS... -->
    </TaxTable>
    
    <Customer>
      <CustomerID>1</CustomerID>
      <CustomerTaxID>000000000</CustomerTaxID>
      <CompanyName>Nome do Cliente</CompanyName>
      <BillingAddress>...</BillingAddress>
    </Customer>
    
    <Supplier><!-- Fornecedores --></Supplier>
    <Product><!-- Produtos/Serviços --></Product>
  </MasterFiles>
  
  <SourceDocuments>
    <SalesInvoices>
      <NumberOfEntries>100</NumberOfEntries>
      <TotalDebit>5000.00</TotalDebit>   <!-- Soma NC -->
      <TotalCredit>95000.00</TotalCredit> <!-- Soma FT + ND -->
      
      <Invoice>
        <InvoiceNo>FT A 2026/000001</InvoiceNo>
        <DocumentStatus>
          <InvoiceStatus>N</InvoiceStatus>
          <InvoiceStatusDate>2026-01-15T14:30:22</InvoiceStatusDate>
          <SourceID>admin</SourceID>
          <SourceBilling>P</SourceBilling>
        </DocumentStatus>
        <Hash>aBcDeFgHiJkLmNoPqRs...</Hash>
        <HashControl>1</HashControl>
        <ATCUD>0-1</ATCUD>
        <InvoiceDate>2026-01-15</InvoiceDate>
        <InvoiceType>FT</InvoiceType>
        <SpecialRegimes>
          <SelfBillingIndicator>0</SelfBillingIndicator>
          <CashVATSchemeIndicator>0</CashVATSchemeIndicator>
          <ThirdPartiesBillingIndicator>0</ThirdPartiesBillingIndicator>
        </SpecialRegimes>
        <SourceID>admin</SourceID>
        <SystemEntryDate>2026-01-15T14:30:22</SystemEntryDate>
        <CustomerID>1</CustomerID>
        
        <Line>
          <LineNumber>1</LineNumber>
          <ProductCode>PROD001</ProductCode>
          <ProductDescription>Produto Exemplo</ProductDescription>
          <Quantity>10.00</Quantity>
          <UnitOfMeasure>UN</UnitOfMeasure>
          <UnitPrice>1000.00</UnitPrice>
          <TaxPointDate>2026-01-15</TaxPointDate>
          <Description>Produto Exemplo</Description>
          <CreditAmount>10000.00</CreditAmount>
          <Tax>
            <TaxType>IVA</TaxType>
            <TaxCountryRegion>AO</TaxCountryRegion>
            <TaxCode>NOR</TaxCode>
            <TaxPercentage>14.00</TaxPercentage>
          </Tax>
        </Line>
        
        <DocumentTotals>
          <TaxPayable>1400.00</TaxPayable>
          <NetTotal>10000.00</NetTotal>
          <GrossTotal>11400.00</GrossTotal>
        </DocumentTotals>
      </Invoice>
    </SalesInvoices>
    
    <Payments><!-- Recibos (RC) --></Payments>
    <MovementOfGoods><!-- Guias de Remessa --></MovementOfGoods>
  </SourceDocuments>
</AuditFile>
```

### 4.2 TotalDebit / TotalCredit (Regra SAFT-AO)

| Documento | Tipo Valor | Campo no XML |
|-----------|-----------|--------------|
| **FT** (Fatura) | CreditAmount nas linhas | Soma vai para **TotalCredit** |
| **NC** (Nota Crédito) | DebitAmount nas linhas | Soma vai para **TotalDebit** |
| **ND** (Nota Débito) | CreditAmount nas linhas | Soma vai para **TotalCredit** |

### 4.3 Campos Obrigatórios por Tipo

| Campo | FT | NC | ND | RC |
|-------|:--:|:--:|:--:|:--:|
| Hash | ✅ | ✅ | ✅ | ✅ |
| HashControl | ✅ | ✅ | ✅ | ✅ |
| ATCUD | ✅ | ✅ | ✅ | ✅ |
| InvoiceType | ✅ | ✅ | ✅ | — |
| GrossTotal | ✅ | ✅ | ✅ | ✅ |
| TaxExemptionReason | Se ISE | Se ISE | Se ISE | — |
| TaxExemptionCode | Se ISE | Se ISE | Se ISE | — |
| References (NC) | — | ✅ | ✅ | — |

---

## 5. IVA Angola — Taxas e Códigos SAFT

| Código SAFT | Taxa | Descrição |
|-------------|------|-----------|
| **NOR** | 14% | IVA Normal (taxa geral) |
| **RED** | 5% / 7% | IVA Reduzido |
| **ISE** | 0% | Isento de IVA (requer motivo) |
| **NS** | 0% | Não sujeito a IVA |

### 5.1 Códigos de Isenção (TaxExemptionCode)

| Código | Descrição |
|--------|-----------|
| M00 | Regime Transitório |
| M02 | Transmissão de bens e serviço não sujeita |
| M04 | IVA - Regime de não Sujeição |
| M11 | Isento Artigo 12.º b) do CIVA |
| M12–M20 | Isenções Art. 12.º do CIVA |
| M30–M38 | Isenções Art. 15.º do CIVA |

### 5.2 Retenção na Fonte (IRT)

- **Prestação de serviços**: 6,5% sobre o valor
- Aplicável quando `is_service = true`
- Campo `irt_amount` na fatura
- Fórmula: `GrossTotal = NetTotal + IVA - IRT`

---

## 6. Faturação Eletrónica — Integração AGT

### 6.1 Arquitectura da Comunicação

```
┌─────────┐         ┌─────────────┐         ┌─────────┐
│ SOSERP  │──OAuth──▶│  API AGT    │──────▶  │  AGT    │
│         │◀─Token──│  (REST)     │◀──────  │  Portal │
│         │         │             │         │         │
│ Criar   │──POST──▶│/invoices    │──Validar│         │
│ Fatura  │◀─ATCUD─│             │◀─Status─│         │
│         │         │             │         │         │
│ Série   │──POST──▶│/series      │──Regist.│         │
│         │◀─Code──│             │◀─ATCUD──│         │
└─────────┘         └─────────────┘         └─────────┘
```

### 6.2 Ambientes

| Ambiente | URL Base |
|----------|---------|
| **Sandbox** | `https://api-sandbox.agt.minfin.gov.ao/v1` |
| **Produção** | `https://api.agt.minfin.gov.ao/v1` |

### 6.3 Endpoints AGT

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/oauth/token` | POST | Autenticação OAuth 2.0 |
| `/series` | POST | Solicitar/registar série documental |
| `/invoices` | POST | Registar factura para validação |
| `/invoices/{ref}/status` | GET | Obter estado de documento |
| `/invoices/{ref}` | GET | Consultar factura validada |
| `/invoices` | GET | Listar facturas do período |

### 6.4 Autenticação OAuth 2.0

```http
POST /v1/oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
&client_id=CLIENT_ID
&client_secret=CLIENT_SECRET
&scope=invoicing
```

**Resposta:**
```json
{
  "access_token": "eyJhbGciOiJSUzI1NiIsInR5...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

### 6.5 Registar Série

```http
POST /v1/series
Authorization: Bearer {token}
Content-Type: application/json

{
  "document_type": "FT",
  "series_code": "A",
  "year": 2026,
  "establishment_id": "1",
  "description": "Série FT A"
}
```

**Resposta:** Retorna `series_id` e `atcud_validation_code` para gerar ATCUD.

### 6.6 Submeter Documento

```http
POST /v1/invoices
Authorization: Bearer {token}
X-Software-Certificate: CERT_NUMBER
Content-Type: application/json

{
  "software_certificate": "CERT_NUM",
  "document_type": "FT",
  "document_number": "FT A 2026/000001",
  "series_id": "AGT_SERIES_ID",
  "issue_date": "2026-01-15",
  "system_entry_date": "2026-01-15T14:30:22",
  "customer": {
    "tax_id": "000000000",
    "name": "Cliente",
    "country": "AO"
  },
  "lines": [
    {
      "line_number": 1,
      "product_code": "PROD001",
      "quantity": 10,
      "unit_price": 1000.00,
      "tax_rate": 14,
      "tax_amount": 1400.00,
      "net_total": 10000.00,
      "gross_total": 11400.00
    }
  ],
  "totals": {
    "net_total": 10000.00,
    "tax_amount": 1400.00,
    "gross_total": 11400.00
  },
  "hash": "aBcDeFg...",
  "hash_control": "1",
  "jws_signature": "eyJhbGci..."
}
```

### 6.7 ATCUD (Código Único de Documento)

```
ATCUD = [Código Validação da Série]-[Número Sequencial]
```

**Exemplo:** `ABCDEFGH-1` (série com código `ABCDEFGH`, documento nº 1)

**Regras:**
- Código de validação obtido ao registar série na AGT
- Se série não registada, usar `0` como código
- Deve aparecer no documento impresso e no QR Code

---

## 7. Implementação no SOSERP

### 7.1 Mapa de Ficheiros

```
app/
├── Helpers/
│   ├── SAFTHelper.php              # Hash RSA-SHA256 (gerar + verificar)
│   └── AGTHelper.php               # Validação conformidade, categorias teste
│
├── Services/AGT/
│   ├── AGTService.php              # Orquestrador principal (assinar, QR, submeter)
│   ├── AGTClient.php               # Cliente API REST AGT (OAuth, endpoints)
│   ├── SignatureService.php         # Assinatura JWS (JSON Web Signature)
│   └── QRCodeService.php           # Geração de QR Code conforme spec AGT
│
├── Traits/
│   └── HasAGTSignature.php         # Trait para modelos (auto-sign, protecção)
│
├── Models/Invoicing/
│   ├── SalesInvoice.php            # FT/FR/FS + HasAGTSignature
│   ├── CreditNote.php              # NC + HasAGTSignature
│   ├── DebitNote.php               # ND + HasAGTSignature
│   ├── Receipt.php                 # RC (hash manual)
│   ├── InvoicingSeries.php         # Séries documentais AGT
│   ├── InvoicingSettings.php       # Config: cert, product_id, AGT credentials
│   └── Tax.php                     # Impostos (saft_code, saft_type, exemption)
│
├── Models/AGT/
│   ├── AGTSubmission.php           # Registo de submissões à AGT
│   └── AGTCommunicationLog.php     # Log de comunicações API
│
├── Livewire/
│   ├── SuperAdmin/
│   │   └── SaftConfiguration.php   # Gestão de chaves RSA (gerar, download)
│   └── Invoicing/
│       ├── SAFTGenerator.php       # Exportação XML SAFT-AO
│       ├── AGTSettings.php         # Config API AGT por tenant
│       ├── AGTDocumentGenerator.php # Processamento em lote
│       └── AGTValidationModal.php  # Validação individual
│
└── Jobs/AGT/
    ├── SubmitDocumentToAGT.php     # Job assíncrono de submissão
    └── CheckAGTStatus.php          # Verificação periódica de estado

storage/app/saft/
├── private_key.pem                 # Chave privada RSA-2048 (NUNCA expor)
├── public_key.pem                  # Chave pública RSA-2048
└── backups/                        # Backups de chaves anteriores
```

### 7.2 Fluxo de Criação de Fatura

```
1. Utilizador preenche fatura (InvoiceCreate.php)
   │
2. Calcular totais SAFT-AO obrigatórios:
   │  ├─ net_total = subtotal - descontos
   │  ├─ tax_payable = IVA total
   │  └─ gross_total = net_total + tax_payable
   │
3. Gerar número via InvoicingSeries::getNextNumber()
   │  └─ Formato: "FT A 2026/000001"
   │
4. save() — Cria registo na BD
   │  └─ HasAGTSignature::creating → verifica se tem totais
   │     └─ Se sim: gera hash + JWS automaticamente
   │     └─ Se não: pula (hash gerado manualmente depois)
   │
5. Gerar hash SAFT-AO manualmente (se não gerado no passo 4):
   │  ├─ Buscar hash do documento anterior (mesma série)
   │  ├─ SAFTHelper::generateHash(date, sysDate, number, grossTotal, prevHash)
   │  ├─ Guardar: saft_hash, hash, hash_previous, hash_control
   │  └─ save()
   │
6. [Opcional] Processar para AGT:
   │  ├─ AGTService::processDocument()
   │  ├─ Gerar QR Code
   │  └─ Submeter via API (se auto_submit ativo)
   │
7. Redirecionar para preview/impressão
```

### 7.3 Trait HasAGTSignature — Protecções

```php
// Documentos com JWS ou status validated/submitted são BLOQUEADOS
// Campos protegidos contra alteração:
'invoice_number', 'credit_note_number', 'debit_note_number',
'invoice_date', 'issue_date', 'gross_total', 'total',
'net_total', 'subtotal', 'tax_amount', 'client_id'

// Para rectificar: emitir Nota de Crédito (NC)
```

### 7.4 Configurações por Tenant

| Campo (`invoicing_settings`) | Descrição |
|------------------------------|-----------|
| `saft_software_cert` | Nº certificado do software |
| `saft_product_id` | ID do produto (ex: SOSERP) |
| `saft_version` | Versão SAFT (1.01_01) |
| `agt_client_id` | Client ID OAuth AGT |
| `agt_client_secret` | Client Secret OAuth AGT (encriptado) |
| `agt_environment` | `sandbox` ou `production` |
| `agt_api_base_url` | URL base customizada (opcional) |
| `agt_access_token` | Token OAuth actual (encriptado) |
| `agt_token_expires_at` | Expiração do token |

---

## 8. Nota de Crédito (NC) — Regras Especiais

### 8.1 Expressão Obrigatória (Art. 12º RJF)

| Tipo | Expressão | Campo |
|------|-----------|-------|
| Anulação total | "Anulação" | `reason_text` |
| Rectificação parcial | "Rectificação" | `reason_text` |

### 8.2 Referência Obrigatória

Cada NC **deve** referenciar a fatura original:
```xml
<References>
  <Reference>FT A 2026/000001</Reference>
  <Reason>Rectificação</Reason>
</References>
```

### 8.3 Campos SAFT-AO (NC)

| Campo | Obrigatório | Descrição |
|-------|:-----------:|-----------|
| `gross_total` | ✅ | net_total + tax_payable |
| `net_total` | ✅ | Subtotal líquido |
| `tax_payable` | ✅ | IVA a pagar |
| `system_entry_date` | ✅ | Data/hora de criação no sistema |
| `invoice_status` | ✅ | `F` (Facturado) ou `A` (Anulado) |
| `hash` / `saft_hash` | ✅ | Hash RSA-SHA256 com gross_total |

---

## 9. Categorias de Teste AGT (Decreto 312/18)

Para certificação do software, a AGT exige testes em 15 categorias:

| Nº | Categoria | Descrição |
|----|-----------|-----------|
| 1 | NIF cliente | Fatura com NIF do cliente |
| 2 | Anulação | Fatura anulada |
| 3 | Proforma | Documento proforma |
| 4 | Proforma → Fatura | Fatura baseada em proforma |
| 5 | Nota de crédito | NC referenciando fatura |
| 6 | IVA misto | Fatura com itens IVA + isentos |
| 7 | Descontos | Fatura com descontos aplicados |
| 8 | Moeda estrangeira | Documento em EUR/USD |
| 9 | Sem NIF (< 50 AOA) | Fatura simplificada antes 10h |
| 10 | Sem NIF (normal) | Consumidor final (999999999) |
| 11 | Guia remessa | Documento de transporte |
| 12 | Proforma/Orçamento | Documento não fiscal |
| 13 | Auto-facturação | Facturação pelo adquirente |
| 14 | Fatura global | Fatura com múltiplos clientes |
| 15 | Outros documentos | Documentos especiais |

---

## 10. Checklist de Conformidade

### 10.1 Configuração Inicial

- [ ] Gerar chaves RSA-2048 (SuperAdmin > SAFT Configuration)
- [ ] Configurar `saft_software_cert` em Invoicing Settings
- [ ] Configurar `saft_product_id` e `saft_version`
- [ ] Criar séries documentais para cada tipo (FT, NC, ND, RC)
- [ ] Configurar taxas de IVA (NOR=14%, RED=7%, ISE=0%)
- [ ] Definir códigos de isenção para produtos isentos

### 10.2 Integração AGT (quando disponível)

- [ ] Obter credenciais OAuth (client_id, client_secret)
- [ ] Configurar ambiente (sandbox → production)
- [ ] Registar séries na AGT (obter ATCUD validation codes)
- [ ] Testar submissão em sandbox
- [ ] Executar 15 categorias de teste
- [ ] Migrar para produção

### 10.3 Verificação Diária

- [ ] Todos os documentos emitidos têm hash válido
- [ ] Numeração sequencial sem lacunas
- [ ] QR Code presente em documentos impressos
- [ ] NC referenciam fatura original
- [ ] SAFT exportável sem erros de validação

---

## 11. Comandos Úteis

```bash
# Gerar chaves SAFT (via SuperAdmin UI)
# URL: /superadmin/saft-configuration

# Exportar SAFT-AO (via Invoicing UI)
# URL: /invoicing/saft-generator

# Verificar conformidade de um documento (tinker)
php artisan tinker
>>> $inv = App\Models\Invoicing\SalesInvoice::find(1);
>>> App\Helpers\AGTHelper::validateAGT($inv);
>>> App\Helpers\SAFTHelper::verifyHash(
...   $inv->invoice_date->format('Y-m-d') . ';' .
...   $inv->system_entry_date->format('Y-m-d H:i:s') . ';' .
...   $inv->invoice_number . ';' .
...   number_format($inv->gross_total, 2, '.', ''),
...   $inv->saft_hash,
...   $inv->hash_previous ?? ''
... );

# Testar conexão AGT
>>> $svc = new App\Services\AGT\AGTService($tenantId);
>>> $svc->testConnection();

# Relatório de conformidade
>>> $svc->getComplianceReport();
```

---

## 12. Segurança

| Item | Implementação |
|------|--------------|
| Chave privada | `storage/app/saft/private_key.pem` — nunca expor publicamente |
| Credenciais AGT | Encriptadas com `Crypt::encryptString()` na BD |
| Campos protegidos | Trait `HasAGTSignature` bloqueia alteração pós-assinatura |
| Backup de chaves | Automático ao regenerar (`storage/app/saft/backups/`) |
| Logs de comunicação | `AGTCommunicationLog` regista todas as chamadas API |
| Token OAuth | Renovado automaticamente quando expira |

---

*Última actualização: 2026-02-28 — SOSERP v1.0*
