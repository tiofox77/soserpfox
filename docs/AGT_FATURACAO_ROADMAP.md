# 🇦🇴 Roadmap de Conformidade AGT Angola
## Decreto Presidencial n.º 71/25 - Sistema de Faturação

**Data de Criação**: 11/12/2025  
**Última Atualização**: 11/12/2025  
**Status**: 🟢 Fase 1-4 Implementadas

---

## 🎉 IMPLEMENTAÇÃO CONCLUÍDA (11/12/2025)

### Ficheiros Criados
| Ficheiro | Descrição |
|----------|-----------|
| `database/migrations/2025_12_11_120000_add_agt_fields_to_existing_tables.php` | Campos AGT + tabelas novas |
| `app/Models/AGT/AGTSubmission.php` | Modelo submissões AGT |
| `app/Models/AGT/AGTCommunicationLog.php` | Logs de comunicação API |
| `app/Services/AGT/AGTClient.php` | Cliente API AGT (OAuth 2.0) |
| `app/Services/AGT/SignatureService.php` | Assinatura JWS + Hash SAFT |
| `app/Services/AGT/QRCodeService.php` | QR Code conforme AGT |
| `app/Services/AGT/AGTService.php` | Orquestrador principal |
| `app/Traits/HasAGTSignature.php` | Trait para modelos documentos |
| `app/Jobs/AGT/SubmitDocumentToAGT.php` | Job submissão assíncrona |
| `app/Jobs/AGT/CheckAGTStatus.php` | Job verificação estado |
| `app/Livewire/Invoicing/AGTSettings.php` | Componente configurações |
| `resources/views/livewire/invoicing/agt-settings.blade.php` | Interface UI |

### Modelos Atualizados com Trait HasAGTSignature
- ✅ `SalesInvoice` - Campos AGT + trait
- ✅ `CreditNote` - Campos AGT + trait
- ✅ `DebitNote` - Campos AGT + trait
- ✅ `InvoicingSeries` - Campos registro AGT
- ✅ `InvoicingSettings` - Configurações API AGT

### Rota Adicionada
- `GET /invoicing/agt-settings` (permissão: `invoicing.agt.view`)

---

## 📊 ANÁLISE COMPLETA DO SISTEMA EXISTENTE (11/12/2025)

### 🗄️ ESTRUTURA DE BANCO DE DADOS EXISTENTE

#### Tabela `invoicing_sales_invoices`
```
id, tenant_id, series_id, proforma_id, invoice_number, atcud, invoice_type, 
client_id, warehouse_id, invoice_date, system_entry_date, due_date, status, 
is_service, invoice_status, invoice_status_date, source_id, source_billing, 
hash, hash_control, hash_previous, subtotal, net_total, tax_amount, tax_payable, 
irt_amount, discount_amount, discount_commercial, discount_financial, total, 
gross_total, paid_amount, currency, exchange_rate, notes, saft_hash, terms, 
created_by, created_at, updated_at, deleted_at
```

#### Tabela `invoicing_series`
```
id, tenant_id, document_type, series_code, name, prefix, include_year, 
next_number, number_padding, is_default, is_active, current_year, 
reset_yearly, description, created_at, updated_at
```

#### Tabela `invoicing_settings`
```
id, tenant_id, default_warehouse_id, default_client_id, default_supplier_id, 
default_tax_id, default_currency, default_exchange_rate, default_payment_method, 
number_format, decimal_places, rounding_mode, proforma_series, invoice_series, 
receipt_series, pos_series, pos_next_number, proforma_next_number, 
invoice_next_number, receipt_next_number, default_tax_rate, default_irt_rate, 
apply_irt_services, allow_line_discounts, allow_commercial_discount, 
allow_financial_discount, max_discount_percent, proforma_validity_days, 
invoice_due_days, auto_print_after_save, show_company_logo, invoice_footer_text, 
saft_software_cert, saft_product_id, saft_version, default_notes, default_terms, 
pos_auto_print, pos_play_sounds, pos_validate_stock, pos_allow_negative_stock, 
pos_show_product_images, pos_products_per_page, pos_auto_complete_sale, 
pos_require_customer, pos_default_payment_method_id, created_at, updated_at
```

---

### 📁 FICHEIROS EXISTENTES RELACIONADOS COM AGT/SAFT

| Ficheiro | Função | Status |
|----------|--------|--------|
| `app/Helpers/SAFTHelper.php` | Geração hash RSA-SHA256, verificação | ✅ Completo |
| `app/Helpers/AGTHelper.php` | Validação AGT, códigos isenção, footer | ✅ Completo |
| `app/Livewire/SuperAdmin/SaftConfiguration.php` | Geração chaves RSA-2048 | ✅ Completo |
| `app/Livewire/Invoicing/SAFTGenerator.php` | Exportação SAFT-AO XML | ✅ Completo |
| `app/Livewire/Invoicing/AGTDocumentGenerator.php` | Gerador docs teste AGT (17 tipos) | ✅ Completo |
| `app/Livewire/Invoicing/SeriesManagement.php` | CRUD séries documentos | ✅ Completo |
| `app/Livewire/Invoicing/Settings.php` | Configurações faturação + SAFT | ✅ Completo |
| `app/Models/Invoicing/InvoicingSeries.php` | Modelo séries (formato AGT) | ✅ Completo |
| `app/Models/Invoicing/InvoicingSettings.php` | Modelo configurações | ✅ Completo |
| `app/Models/Invoicing/SalesInvoice.php` | Modelo fatura com hash | ✅ Completo |
| `app/Models/Invoicing/CreditNote.php` | Notas de crédito | ✅ Completo |
| `app/Models/Invoicing/DebitNote.php` | Notas de débito | ✅ Completo |
| `app/Models/Invoicing/Receipt.php` | Recibos | ✅ Completo |

---

### ✅ O QUE JÁ EXISTE

| Funcionalidade | Status | Localização |
|----------------|--------|-------------|
| **Tipos de Documentos** | ✅ Parcial | `InvoicingSeries.php` |
| FT - Fatura | ✅ | SalesInvoice |
| FR - Fatura-Recibo | ✅ | POS |
| NC - Nota de Crédito | ✅ | CreditNote |
| ND - Nota de Débito | ✅ | DebitNote |
| RC - Recibo | ✅ | Receipt |
| PR - Proforma | ✅ | SalesProforma |
| FA - Fatura de Adiantamento | ✅ | Advance |
| TV - Talão de Venda | ⏳ | Não implementado |
| **Sistema de Séries** | ✅ | `InvoicingSeries.php` |
| Numeração contínua | ✅ | getNextNumber() |
| Reset anual | ✅ | reset_yearly |
| Múltiplas séries | ✅ | series_code (A, B, C) |
| Formato AGT (FT A 2025/000001) | ✅ | formatNumber() |
| **Campos SAFT-AO** | ✅ Parcial | |
| Hash SHA256 | ✅ | generateHash() |
| Hash em cadeia | ✅ | hash_previous |
| ATCUD | ⚠️ Campo existe, não calculado | |
| System Entry Date | ✅ | system_entry_date |
| Invoice Status (N/A/F) | ✅ | invoice_status |
| Hash Control | ✅ | hash_control |
| **Exportação SAFT** | ✅ | `SAFTGenerator.php` |
| Header XML | ✅ | |
| MasterFiles | ✅ | Customers, Suppliers, Products |
| SourceDocuments | ✅ Parcial | SalesInvoices |
| **Validação NIF** | ✅ | validateNIF() |
| **Códigos de Isenção IVA** | ✅ | `AGTHelper.php` |
| **Helper AGT** | ✅ | `AGTHelper.php` |
| Validação conformidade | ✅ | validateAGT() |
| Footer message | ✅ | getFooterMessage() |
| Relatório conformidade | ✅ | getConformityReport() |

### ❌ O QUE FALTA IMPLEMENTAR

| Funcionalidade | Prioridade | Decreto 71/25 |
|----------------|------------|---------------|
| **Assinatura Digital RSA** | 🔴 CRÍTICO | Art. 9º, 10º |
| Chave privada RSA-2048 | 🔴 | |
| JWS Document Signature | 🔴 | |
| Certificado AGT | 🔴 | |
| **Integração API AGT** | 🔴 CRÍTICO | Capítulo III |
| SolicitarSerie | 🔴 | |
| RegistarFactura | 🔴 | |
| ObterEstado | 🔴 | |
| ConsultarFactura | 🔴 | |
| ListarFacturas | 🔴 | |
| **Anulação (45 dias)** | 🟡 ALTO | Art. 8º |
| Validação prazo 45 dias | 🟡 | |
| Motivos permitidos | 🟡 | |
| **QR Code AGT** | 🟡 ALTO | PDF Técnico |
| Geração QR | 🟡 | |
| Dados obrigatórios | 🟡 | |
| **Retenções na Fonte** | 🟡 ALTO | |
| IRT (Imposto Rendimento) | ⚠️ Campo existe | |
| II (Imposto Industrial) | ❌ | |
| IS (Imposto Selo) | ❌ | |
| **Logs Imutáveis** | 🟢 MÉDIO | Certificação |
| Auditoria de alterações | 🟢 | |
| Backup automático | 🟢 | |
| **Documento Tipo C** | 🟢 MÉDIO | Correção |

---

## 🎯 ROADMAP DE IMPLEMENTAÇÃO

### FASE 1: ASSINATURA DIGITAL (CRÍTICO) ✅ CONCLUÍDA
> Implementado em: 11/12/2025

#### 1.1 Infraestrutura de Chaves
- [x] Criar tabela `agt_certificates` para armazenar certificados
- [x] Implementar geração de par de chaves RSA-2048
- [x] Armazenamento seguro das chaves (encrypted)
- [x] Interface para upload de certificado AGT

#### 1.2 Serviço de Assinatura
```php
// App\Services\AGT\SignatureService.php ✅
- [x] Método signDocument($document): string
- [x] Método verifySignature($document, $signature): bool
- [x] Algoritmo RS256 (RSA-SHA256)
- [x] Formato JWS (JSON Web Signature)
```

#### 1.3 Integração nos Documentos
- [x] Adicionar campo `jws_signature` nas tabelas
- [x] Hook `creating` para assinar automaticamente (via Trait)
- [x] Validação de assinatura no `updating` (bloquear)

#### 1.4 Campos Obrigatórios da Assinatura
```
- documentNo
- NIF emissor
- invoice_date
- system_entry_date
- gross_total
- hash_previous
```

---

### FASE 2: API AGT (CRÍTICO) ✅ CONCLUÍDA
> Implementado em: 11/12/2025

#### 2.1 Configuração Base
```php
// App\Services\AGT\AGTClient.php ✅
- [x] Credenciais API (client_id, client_secret)
- [x] URLs ambiente (sandbox/produção)
- [x] Token OAuth 2.0
- [x] Retry logic e error handling
```

#### 2.2 Serviços Implementados ✅

| Serviço | Método | Status |
|---------|--------|--------|
| SolicitarSerie | `requestSeries()` | ✅ |
| RegistarFactura | `registerInvoice()` | ✅ |
| ObterEstado | `getStatus()` | ✅ |
| ConsultarFactura | `getInvoice()` | ✅ |
| ListarFacturas | `listInvoices()` | ✅ |

#### 2.3 Modelo de Dados API ✅
```php
// App\Models\AGT\AGTSubmission.php ✅
- [x] document_type
- [x] document_id
- [x] agt_reference
- [x] status (pending, validated, rejected)
- [x] response_data (JSON)
- [x] submitted_at
- [x] validated_at
- [x] error_message
```

#### 2.4 Jobs de Sincronização
- [x] Job `SubmitDocumentToAGT`
- [x] Job `CheckAGTStatus`
- [ ] Scheduler para verificar estados pendentes
- [ ] Notificações de rejeição

---

### FASE 3: REGRAS DE ANULAÇÃO ✅ CONCLUÍDA
> Implementado em: 11/12/2025

#### 3.1 Validação de Prazo
```php
// App\Services\AGT\AGTService.php ✅
- [x] Verificar se documento tem < 45 dias (canCancelDocument())
- [x] Bloquear anulação após 45 dias
- [x] Forçar emissão de Nota de Crédito
```

#### 3.2 Motivos de Anulação Permitidos ✅
```php
// Implementado em AGTService.php
const ALLOWED_CANCELLATION_REASONS = [
    'client_error' => 'Erro na identificação do cliente',
    'not_sent' => 'Documento não enviado ao cliente',
];
```

#### 3.3 Fluxo de Anulação
- [x] Validação de prazo 45 dias
- [x] Validação de motivo obrigatório
- [ ] Interface UI para solicitar anulação
- [ ] Submissão à AGT para anulação

---

### FASE 4: QR CODE AGT ✅ CONCLUÍDA
> Implementado em: 11/12/2025

#### 4.1 Estrutura do QR Code ✅
```
// App\Services\AGT\QRCodeService.php
A:[NIF_EMITENTE]*
B:[NIF_CLIENTE]*
C:[PAIS_CLIENTE]*
D:[TIPO_DOCUMENTO]*
E:[STATUS_DOCUMENTO]*
F:[DATA_DOCUMENTO]*
G:[NUMERO_DOCUMENTO]*
H:[ATCUD]*
I1:[ESPACO_FISCAL]*
I7:[BASE_IVA_14]*
I8:[IVA_14]*
N:[TOTAL_IMPOSTOS]*
O:[TOTAL_DOCUMENTO]*
Q:[HASH_4_CARACTERES]*
R:[NUMERO_CERTIFICADO]*
```

#### 4.2 Implementação
- [x] Serviço `QRCodeService`
- [x] Geração automática (via Trait HasAGTSignature)
- [x] Inclusão no PDF da fatura (13/12/2025)
- [x] Posição conforme especificação AGT

**Documentos com QR Code AGT:**
- ✅ Fatura de Venda (sales-invoice.blade.php)
- ✅ Nota de Crédito (credit-note.blade.php)
- ✅ Nota de Débito (debit-note.blade.php)
- ✅ Proforma (proforma.blade.php)
- ✅ Recibo (receipt.blade.php)

---

### FASE 5: RETENÇÕES NA FONTE 🟡
> Prazo sugerido: 2 semanas

#### 5.1 Tabela de Retenções
```php
// App\Models\Invoicing\Withholding.php
- [ ] type (IRT, II, IS, IVA)
- [ ] rate (decimal)
- [ ] base_amount
- [ ] withheld_amount
- [ ] document_id
- [ ] document_type
```

#### 5.2 Taxas Angola 2025
| Tipo | Taxa | Base Legal |
|------|------|------------|
| IRT | 6.5% | Serviços |
| II | 2.5% | Produtos |
| IVA | 14% | Geral |
| IS | Variável | Seguros/Financeiro |

#### 5.3 Integração
- [ ] Campo retenção nos itens da fatura
- [ ] Cálculo automático por tipo de produto/serviço
- [ ] Relatório de retenções para declaração
- [ ] Exportação SAFT com retenções

---

### FASE 6: AUDITORIA E LOGS 🟢
> Prazo sugerido: 1 semana

#### 6.1 Sistema de Logs Imutáveis
```php
// App\Models\AuditLog.php
- [ ] action (create, update, delete, cancel)
- [ ] model_type
- [ ] model_id
- [ ] old_values (JSON)
- [ ] new_values (JSON)
- [ ] user_id
- [ ] ip_address
- [ ] user_agent
- [ ] created_at (imutável)
```

#### 6.2 Proteções
- [ ] Trigger de banco para impedir UPDATE/DELETE
- [ ] Hash de integridade por linha
- [ ] Backup automático diário
- [ ] Exportação para arquivo externo

---

### FASE 7: CERTIFICAÇÃO AGT 🟢
> Prazo sugerido: 4 semanas (após implementação)

#### 7.1 Testes Obrigatórios
```
1. Fatura com NIF do cliente
2. Fatura anulada (dentro de 45 dias)
3. Proforma
4. Fatura baseada em proforma
5. Nota de crédito
6. Fatura com IVA e isento
7. Fatura com descontos
8. Documento em moeda estrangeira
9. Fatura sem NIF (< 50 AOA, antes 10h)
10. Fatura sem NIF (normal)
11. Guia de remessa
12. Orçamento/Proforma
13. Auto-faturação
14. Fatura global
15. Outros documentos
```

#### 7.2 Documentação para Certificação
- [ ] Manual do utilizador
- [ ] Manual técnico
- [ ] Declaração de conformidade
- [ ] Ambiente de testes configurado

---

## ✅ CHECKLIST DE CONFORMIDADE

### Documentos Fiscais (Decreto 71/25 Art. 2º-7º)
- [x] Fatura (FT)
- [x] Fatura-Recibo (FR)
- [x] Nota de Crédito (NC)
- [x] Nota de Débito (ND)
- [x] Recibo (RC)
- [x] Proforma/Orçamento (PR)
- [x] Adiantamento (AD)
- [ ] Talão de Venda (TV)
- [ ] Faturação via Portal AGT

### Conteúdo Obrigatório (Art. 4º)
- [x] Número sequencial único
- [x] Data de emissão
- [x] NIF do emitente
- [x] Nome/Denominação do emitente
- [x] Endereço do emitente
- [x] NIF do cliente (quando aplicável)
- [x] Nome do cliente
- [x] Descrição dos bens/serviços
- [x] Quantidade
- [x] Preço unitário
- [x] Taxa de IVA
- [x] Valor do IVA
- [x] Total do documento
- [ ] ATCUD (calculado)
- [ ] QR Code AGT
- [ ] Código de validação AGT

### Numeração (Art. 5º)
- [x] Sequencial por série
- [x] Sem lacunas
- [x] Não reinicia durante o ano
- [x] Formato AGT (TIPO SERIE ANO/NUMERO)
- [ ] Série registada na AGT

### Anulação (Art. 8º)
- [ ] Prazo máximo 45 dias
- [ ] Apenas motivos permitidos
- [ ] Nota de crédito para outros casos
- [ ] Comunicação à AGT

### Faturação Electrónica (Art. 9º-10º)
- [ ] Integração com Portal AGT
- [ ] Submissão em tempo real
- [ ] Validação pela AGT
- [ ] Armazenamento do estado

### Assinatura Digital (Certificação)
- [ ] Chave RSA-2048
- [ ] Algoritmo RS256
- [ ] JWS format
- [ ] Hash em cadeia
- [ ] Certificado AGT

### Exportação SAF-T (AO)
- [x] Header com dados empresa
- [x] MasterFiles (Customers, Suppliers, Products)
- [x] SourceDocuments (SalesInvoices)
- [ ] SourceDocuments (Purchases, Payments)
- [ ] Schema XSD oficial
- [ ] Validação estrutura

### Segurança e Auditoria
- [ ] Logs imutáveis
- [ ] Backup automático
- [ ] Proibição de edição pós-assinatura
- [ ] Rastreabilidade completa

---

## 🚫 PROIBIÇÕES (NÃO FAZER)

| Ação | Motivo | Consequência |
|------|--------|--------------|
| Editar documento assinado | Quebra integridade | Certificação negada |
| Apagar faturas | Ilegal | Multa + processo |
| Mudar série após emissão | Fraude fiscal | Multa grave |
| Reiniciar numeração | Documentos duplicados | Rejeição AGT |
| Campos imposto em branco | Incompleto | Rejeição |
| Valores negativos (exceto NC) | Inválido | Rejeição |
| Anular após 45 dias | Fora do prazo | Bloqueado |

---

## 📅 CRONOGRAMA SUGERIDO

```
Semana 1-2:   FASE 1 - Assinatura Digital
Semana 3-5:   FASE 2 - Integração API AGT
Semana 6:     FASE 3 - Regras de Anulação
Semana 7:     FASE 4 - QR Code AGT
Semana 8-9:   FASE 5 - Retenções na Fonte
Semana 10:    FASE 6 - Auditoria e Logs
Semana 11-14: FASE 7 - Certificação AGT
```

**Total estimado: 3-4 meses**

---

## 📚 REFERÊNCIAS

1. **Decreto Presidencial n.º 71/25** (20 de Março) - Novo Regime Jurídico de Fatura
2. **Regras de Validação de Software** - AGT (minfin055809)
3. **Estrutura de Dados & Serviços AGT** - Versão 1.4 (Agosto 2025)
4. **CIVA Angola** - Código do Imposto sobre o Valor Acrescentado

---

> 📝 **Nota**: Este roadmap é um guia técnico. A implementação deve ser validada com a AGT e assessoria fiscal qualificada antes da entrada em produção.

**Responsável**: Equipa de Desenvolvimento  
**Aprovado por**: [Pendente]
