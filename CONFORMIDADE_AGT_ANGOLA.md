# ✅ CONFORMIDADE AGT ANGOLA - DECRETO PRESIDENCIAL 312/18

## 📋 CHECKLIST DE CONFORMIDADE FISCAL

**Data:** 05/10/2025  
**Sistema:** SOSERP v1.0  
**Legislação:** Decreto Presidencial 312/18 de 21 de Dezembro

---

## 🎯 OBJETIVO

Validar conformidade do sistema SOSERP com a legislação fiscal angolana (AGT) para certificação fiscal.

---

## 📄 DOCUMENTOS OBRIGATÓRIOS PARA VALIDAÇÃO

### **1. Fatura com NIF do Cliente** 
- [ ] Documento criado
- [ ] Cliente com NIF válido
- [ ] NIF exibido no documento
- [ ] Campos SAFT corretos
- [ ] Hash gerado corretamente
- [ ] Mensagem AGT no rodapé
- [ ] PDF assinado
- [ ] **Arquivo:** `fatura_com_nif.pdf`

**Campos SAFT Obrigatórios:**
```xml
<Customer>
    <CustomerID>...</CustomerID>
    <CustomerTaxID>...</CustomerTaxID>
</Customer>
<Hash>XXXX-YYYY-ZZZZ-WWWW</Hash>
<Period>YYYY-MM</Period>
```

---

### **2. Fatura Anulada**
- [ ] Fatura original criada
- [ ] Fatura anulada no sistema
- [ ] Indicação visual "ANULADO" no PDF
- [ ] Marca d'água ou texto grande
- [ ] Registrado no banco de dados
- [ ] Campo SourceID preenchido no SAFT
- [ ] Status no SAFT: "A" (Anulado)
- [ ] **Arquivos:** `fatura_original.pdf` + `fatura_anulada.pdf`

**Campos SAFT Obrigatórios:**
```xml
<InvoiceStatus>A</InvoiceStatus>
<InvoiceStatusDate>YYYY-MM-DDThh:mm:ss</InvoiceStatusDate>
<SourceID>...</SourceID>
```

---

### **3. Proforma (Documento de Conferência)**
- [ ] Proforma criada
- [ ] Tipo documento: "Proforma"
- [ ] Número sequencial
- [ ] Validade indicada
- [ ] Não afeta stock
- [ ] Não afeta contabilidade
- [ ] **Arquivo:** `proforma.pdf`

**Observação:** Documento susceptível de entrega ao cliente para conferência.

---

### **4. Fatura Baseada na Proforma**
- [ ] Proforma convertida em fatura
- [ ] Elemento `<OrderReferences>` gerado
- [ ] Referência à proforma original
- [ ] Data de referência
- [ ] **Arquivo:** `fatura_de_proforma.pdf`

**Campos SAFT Obrigatórios:**
```xml
<OrderReferences>
    <OriginatingON>PRF A 2025/000001</OriginatingON>
    <OrderDate>YYYY-MM-DD</OrderDate>
</OrderReferences>
```

---

### **5. Nota de Crédito Baseada na Fatura**
- [ ] Nota de crédito criada
- [ ] Referência à fatura original
- [ ] Elemento `<OrderReferences>` gerado
- [ ] Valores negativos
- [ ] Motivo da devolução
- [ ] **Arquivo:** `nota_credito.pdf`

**Campos SAFT Obrigatórios:**
```xml
<OrderReferences>
    <OriginatingON>FT A 2025/000001</OriginatingON>
    <OrderDate>YYYY-MM-DD</OrderDate>
</OrderReferences>
<DocumentTotals>
    <TaxPayable>-XXX.XX</TaxPayable>
    <NetTotal>-XXX.XX</NetTotal>
    <GrossTotal>-XXX.XX</GrossTotal>
</DocumentTotals>
```

---

### **6. Fatura com 2 Linhas (IVA e Isento)**
- [ ] Linha 1: Produto com IVA 14% ou 5%
- [ ] Linha 2: Produto isento com código de isenção
- [ ] Código de isenção válido (M00-M38)
- [ ] Elemento `<TaxExemptionReason>` gerado
- [ ] Descrição da isenção
- [ ] **Arquivo:** `fatura_iva_isento.pdf`

**Códigos de Isenção AGT:**
```
M00 - Regime Transitório
M02 - Não sujeita
M04 - Regime de não Sujeição
M11 - Isento Art. 12.º b)
M12 - Isento Art. 12.º c)
M13 - Isento Art. 12.º d)
M14 - Isento Art. 12.º e)
M15 - Isento Art. 12.º f)
M17 - Isento Art. 12.º h)
M18 - Isento Art. 12.º i)
M19 - Isento Art. 12.º j)
M20 - Isento Art. 12.º k)
M30 - Isento Art. 15.º 1 a)
M31 - Isento Art. 15.º 1 b)
M32 - Isento Art. 15.º 1 c)
M33 - Isento Art. 15.º 1 d)
M34 - Isento Art. 15.º 1 e)
M35 - Isento Art. 15.º 1 f)
M36 - Isento Art. 15.º 1 g)
M37 - Isento Art. 15.º 1 h)
M38 - Isento Art. 15.º 1 i)
```

**Campos SAFT Obrigatórios:**
```xml
<Line>
    <TaxExemptionReason>M11</TaxExemptionReason>
    <TaxExemptionCode>M11</TaxExemptionCode>
</Line>
```

---

### **7. Fatura com Descontos (Linha e Global)**
- [ ] Linha 1: Qtd 100, Preço 0.55, Desconto linha 8.8%
- [ ] Desconto global aplicado
- [ ] Elemento `<SettlementAmount>` gerado
- [ ] UnitPrice sem imposto
- [ ] Descontos refletidos no UnitPrice
- [ ] 4 casas decimais no UnitPrice
- [ ] **Arquivo:** `fatura_com_descontos.pdf`

**Cálculo Exemplo:**
```
Qtd: 100
Preço Unit: 0.5500
Desconto Linha: 8.8% = 0.0484
Preço após desc linha: 0.5016
Subtotal: 50.16
Desconto Global: X%
```

**Campos SAFT Obrigatórios:**
```xml
<Line>
    <UnitPrice>0.5500</UnitPrice>
    <SettlementAmount>4.8400</SettlementAmount>
</Line>
<DocumentTotals>
    <Settlement>
        <SettlementAmount>XX.XX</SettlementAmount>
    </Settlement>
</DocumentTotals>
```

---

### **8. Documento em Moeda Estrangeira**
- [ ] Documento criado em USD/EUR
- [ ] Taxa de câmbio aplicada
- [ ] Valores em moeda estrangeira
- [ ] Valores em AOA
- [ ] Campo Currency preenchido
- [ ] CurrencyAmount correto
- [ ] **Arquivo:** `fatura_moeda_estrangeira.pdf`

**Campos SAFT Obrigatórios:**
```xml
<Currency>
    <CurrencyCode>USD</CurrencyCode>
    <CurrencyAmount>XXX.XX</CurrencyAmount>
    <ExchangeRate>XXX.XXXX</ExchangeRate>
</Currency>
```

---

### **9. Fatura Cliente Sem NIF (< 50 AOA, antes 10h)**
- [ ] Cliente sem NIF
- [ ] Total < 50.00 AOA
- [ ] SystemEntryDate <= 10:00
- [ ] Cliente identificado
- [ ] **Arquivo:** `fatura_sem_nif_menor_50.pdf`

**Campos SAFT Obrigatórios:**
```xml
<SystemEntryDate>YYYY-MM-DDThh:mm:ss</SystemEntryDate> <!-- hh < 10 -->
<GrossTotal>XX.XX</GrossTotal> <!-- < 50.00 -->
<CustomerTaxID>999999999</CustomerTaxID> <!-- Consumidor Final -->
```

---

### **10. Fatura Cliente Sem NIF (Normal)**
- [ ] Cliente sem NIF
- [ ] Cliente identificado
- [ ] Valor qualquer
- [ ] **Arquivo:** `fatura_sem_nif.pdf`

---

### **11. Guias de Remessa (2)**
- [ ] Guia de remessa 1 criada
- [ ] Guia de remessa 2 criada
- [ ] Tipo documento: "Guia de Remessa"
- [ ] Movimento de stock
- [ ] Endereço de entrega
- [ ] **Arquivos:** `guia_remessa_1.pdf` + `guia_remessa_2.pdf`

---

### **12. Orçamento/Proforma**
- [ ] Orçamento criado
- [ ] Validade indicada
- [ ] Não afeta finanças
- [ ] **Arquivo:** `orcamento.pdf`

---

### **13. Fatura Genérica e Auto-Facturação**
- [ ] Fatura genérica criada (se aplicável)
- [ ] Auto-facturação criada (se aplicável)
- [ ] **Arquivos:** `fatura_generica.pdf` + `auto_facturacao.pdf`
- [ ] Se não aplicável: indicar "Não aplicável"

---

### **14. Fatura Global**
- [ ] Fatura global criada
- [ ] Agrupamento de vendas
- [ ] Período indicado
- [ ] **Arquivo:** `fatura_global.pdf`

---

### **15. Outros Tipos de Documentos**
- [ ] Recibo
- [ ] Nota de débito
- [ ] Devolução
- [ ] Outros (listar)
- [ ] **Arquivos:** `recibo.pdf`, `nota_debito.pdf`, etc.

---

## 🔐 REQUISITOS TÉCNICOS OBRIGATÓRIOS

### **Hash no Documento**
- [ ] 4 caracteres do Hash visíveis
- [ ] Formato: XXXX-YYYY-ZZZZ-WWWW
- [ ] Mensagem obrigatória no rodapé:
  ```
  [4 caracteres do Hash] - Processado por programa válido n31.1/AGT2025
  ```
- [ ] Exemplo: `A1B2 - Processado por programa válido n31.1/AGT2025`

### **Período Contabilístico**
- [ ] Campo Period preenchido (YYYY-MM)
- [ ] Documentos de 2 meses diferentes
- [ ] Campos 4.1.4.5, 4.2.3.5 ou 4.3.4.5 do SAFT

### **Assinatura Digital**
- [ ] PDFs assinados digitalmente
- [ ] Certificado válido
- [ ] Assinatura visível ou invisível

### **HashControl**
- [ ] Campo HashControl preenchido
- [ ] Campos 4.1.4.4, 4.2.3.4 ou 4.3.4.4 do SAFT
- [ ] Algoritmo conforme Decreto 312/18

---

## 📦 ARQUIVO SAF-T XML

### **Estrutura Obrigatória**
- [ ] Arquivo XML único gerado
- [ ] Todos os documentos incluídos
- [ ] Header preenchido corretamente
- [ ] Formato conforme Decreto 312/18
- [ ] Validação XSD passou
- [ ] **Arquivo:** `SAFT_SOSERP_2025.xml`

### **Campos Header Obrigatórios:**
```xml
<Header>
    <AuditFileVersion>1.01_01</AuditFileVersion>
    <CompanyID>...</CompanyID>
    <TaxRegistrationNumber>...</TaxRegistrationNumber>
    <TaxAccountingBasis>F</TaxAccountingBasis>
    <CompanyName>...</CompanyName>
    <BusinessName>...</BusinessName>
    <CompanyAddress>...</CompanyAddress>
    <FiscalYear>2025</FiscalYear>
    <StartDate>2025-01-01</StartDate>
    <EndDate>2025-12-31</EndDate>
    <CurrencyCode>AOA</CurrencyCode>
    <DateCreated>YYYY-MM-DD</DateCreated>
    <TaxEntity>Global</TaxEntity>
    <ProductCompanyTaxID>...</ProductCompanyTaxID>
    <SoftwareCertificateNumber>AGT/2025/XXXX</SoftwareCertificateNumber>
    <ProductID>SOSERP/v1.0</ProductID>
    <ProductVersion>1.0</ProductVersion>
</Header>
```

---

## 📊 TABELA DE DOCUMENTOS DE EXEMPLO

| # | Tipo Documento | Cliente | Valor | Moeda | Status | Arquivo |
|---|----------------|---------|-------|-------|--------|---------|
| 1 | Fatura | Com NIF | 100.00 | AOA | ✅ | fatura_com_nif.pdf |
| 2a | Fatura | Com NIF | 150.00 | AOA | ✅ Original | fatura_original.pdf |
| 2b | Fatura | Com NIF | 150.00 | AOA | ❌ Anulada | fatura_anulada.pdf |
| 3 | Proforma | Com NIF | 200.00 | AOA | ✅ | proforma.pdf |
| 4 | Fatura | Com NIF | 200.00 | AOA | ✅ | fatura_de_proforma.pdf |
| 5 | Nota Crédito | Com NIF | -200.00 | AOA | ✅ | nota_credito.pdf |
| 6 | Fatura | Com NIF | 85.00 | AOA | ✅ | fatura_iva_isento.pdf |
| 7 | Fatura | Com NIF | 50.16 | AOA | ✅ | fatura_com_descontos.pdf |
| 8 | Fatura | Com NIF | 100.00 | USD | ✅ | fatura_moeda_estrangeira.pdf |
| 9 | Fatura | Sem NIF | 45.00 | AOA | ✅ | fatura_sem_nif_menor_50.pdf |
| 10 | Fatura | Sem NIF | 120.00 | AOA | ✅ | fatura_sem_nif.pdf |
| 11a | Guia Remessa | Com NIF | - | AOA | ✅ | guia_remessa_1.pdf |
| 11b | Guia Remessa | Com NIF | - | AOA | ✅ | guia_remessa_2.pdf |
| 12 | Orçamento | Com NIF | 300.00 | AOA | ✅ | orcamento.pdf |
| 13a | Fatura Genérica | - | 500.00 | AOA | ⚠️ N/A | - |
| 13b | Auto-Facturação | Com NIF | 250.00 | AOA | ⚠️ N/A | - |
| 14 | Fatura Global | - | 1500.00 | AOA | ✅ | fatura_global.pdf |
| 15a | Recibo | Com NIF | 100.00 | AOA | ✅ | recibo.pdf |
| 15b | Nota Débito | Com NIF | 50.00 | AOA | ✅ | nota_debito.pdf |

---

## 🧪 PLANO DE TESTES

### **Fase 1: Criação de Dados de Teste**
```
✅ Criar cliente com NIF válido
✅ Criar cliente sem NIF (Consumidor Final)
✅ Criar produtos com IVA 14%
✅ Criar produtos com IVA 5%
✅ Criar produtos isentos com códigos M00-M38
✅ Configurar séries AGT (FT, FR, NC, ND, GR, PR)
✅ Configurar certificado SAFT
```

### **Fase 2: Geração de Documentos**
```
⏳ Gerar cada documento do checklist
⏳ Validar campos obrigatórios
⏳ Verificar cálculos
⏳ Conferir Hash gerado
⏳ Validar PDF gerado
```

### **Fase 3: Exportação SAFT**
```
⏳ Gerar XML SAFT
⏳ Validar estrutura XML
⏳ Validar contra XSD
⏳ Verificar todos os documentos incluídos
⏳ Conferir HashControl
```

### **Fase 4: Conformidade AGT**
```
⏳ Checkbox conformidade em cada documento
⏳ Preview de documento antes de aprovar
⏳ Validação automática de campos
⏳ Relatório de conformidade
```

---

## 🚨 PONTOS CRÍTICOS DE ATENÇÃO

### **UnitPrice**
```
❗ DEVE ser sem imposto
❗ DEVE refletir descontos de linha
❗ DEVE refletir descontos globais
❗ DEVE ter 4 casas decimais mínimo
```

### **Hash**
```
❗ Algoritmo SHA-1 ou SHA-256
❗ Incluir: Data, Hora, Número Doc, Total, Hash Anterior
❗ 4 primeiros caracteres visíveis no PDF
❗ Mensagem AGT obrigatória
```

### **Anulação**
```
❗ Marca visual CLARA no PDF
❗ Status "A" no SAFT
❗ SourceID preenchido
❗ Manter na base de dados
```

---

## 📧 SUBMISSÃO À AGT

### **Documentos para Enviar:**
1. ✅ Todos os PDFs listados
2. ✅ Arquivo SAFT XML único
3. ✅ Tabela de correspondência
4. ✅ Documentos de 2 meses diferentes
5. ✅ Documentos assinados

### **Email:**
```
Para: produtos.dfe.dcrr.agt@minfin.gov.ao
Assunto: Pedido de Certificação - SOSERP v1.0
Anexos: [Todos os PDFs + XML SAFT + Tabela]
```

### **Prazo:**
```
⏰ 15 dias úteis após notificação
⚠️ Prazo de certificação SUSPENSO até conclusão dos testes
```

---

## ✅ STATUS GERAL

```
Documentos Criados:    ░░░░░░░░░░░░░░░░░░░░   0/17
PDFs Gerados:          ░░░░░░░░░░░░░░░░░░░░   0/17
SAFT XML:              ░░░░░░░░░░░░░░░░░░░░   0%
Conformidade:          ░░░░░░░░░░░░░░░░░░░░   0%
                       ────────────────────
PRONTO PARA AGT:       ░░░░░░░░░░░░░░░░░░░░   0%
```

---

## 📝 OBSERVAÇÕES FINAIS

1. **NÃO aplicável:** Quando o sistema não produz um tipo de documento, indicar claramente.
2. **Dois meses diferentes:** Garantir que os documentos cubram pelo menos 2 meses.
3. **Campos obrigatórios:** TODOS os campos do SAFT devem estar corretos.
4. **Validação contínua:** Testar cada documento antes de submeter.

---

**ÚLTIMA ATUALIZAÇÃO:** 05/10/2025 21:48  
**RESPONSÁVEL:** Sistema SOSERP  
**LEGISLAÇÃO:** Decreto Presidencial 312/18
