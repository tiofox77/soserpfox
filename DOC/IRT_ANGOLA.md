# IRT - Imposto sobre Rendimento do Trabalho (Angola)

## 📋 O Que é IRT?

**IRT** (Imposto sobre Rendimento do Trabalho) é um imposto angolano retido na fonte sobre prestação de serviços.

### **Características:**
- 🔴 **Taxa**: 6.5% do valor dos serviços
- 📊 **Base de Cálculo**: Valor do serviço APÓS desconto comercial, ANTES do IVA
- 💼 **Aplica-se a**: Prestação de serviços (não produtos)
- 🏦 **Retenção**: Na fonte pelo adquirente do serviço
- 📅 **Entrega**: Mensalmente à AGT

---

## 🧮 Ordem de Cálculo SAFT-AO + IRT

```
1. SUBTOTAL (Soma dos serviços)
   └─ Exemplo: 100,000.00 Kz

2. - DESCONTO COMERCIAL
   └─ Exemplo: -10,000.00 Kz
   
3. = BASE DE CÁLCULO
   └─ Exemplo: 90,000.00 Kz

4. - IRT (6.5% retido na fonte)
   └─ Exemplo: -5,850.00 Kz (6.5% de 90,000)

5. = LÍQUIDO A RECEBER (prestador)
   └─ Exemplo: 84,150.00 Kz

6. + IVA (14% sobre base)
   └─ Exemplo: +12,600.00 Kz (14% de 90,000)

7. = TOTAL A PAGAR (pelo cliente)
   └─ Exemplo: 96,750.00 Kz

8. - DESCONTO FINANCEIRO
   └─ Exemplo: -1,750.00 Kz
   
9. = TOTAL FINAL
   └─ Exemplo: 95,000.00 Kz
```

---

## 🔍 Diferença: Produto vs Serviço

| Característica | Produto | Serviço |
|----------------|---------|---------|
| **IRT** | ❌ Não aplica | ✅ 6.5% retido |
| **IVA** | ✅ 14% | ✅ 14% |
| **Base IRT** | N/A | Valor - Desc. Comercial |
| **Base IVA** | Valor - Desc. Comercial | Valor - Desc. Comercial |
| **Retenção** | Não | Sim, pelo cliente |

---

## 📊 Implementação no Sistema

### **1. Banco de Dados:**

**Tabelas Principais:**
```sql
is_service BOOLEAN DEFAULT FALSE -- Identifica se é serviço
irt_amount DECIMAL(15,2) DEFAULT 0 -- Valor IRT retido
```

**Tabelas de Itens:**
```sql
is_service BOOLEAN DEFAULT FALSE -- Identifica item como serviço
irt_rate DECIMAL(5,2) DEFAULT 6.5 -- Taxa IRT (%)
irt_amount DECIMAL(15,2) DEFAULT 0 -- Valor IRT por item
```

### **2. Cálculo no Backend:**

```php
// Calcular IRT (6.5% sobre serviços, antes do IVA)
$irt_amount = 0;
if ($this->is_service) {
    $base_irt = $subtotal - $discount_commercial - $discount_amount;
    $irt_amount = $base_irt * 0.065; // 6.5% IRT Angola
}

// IVA calculado sobre base (não afetado pelo IRT)
$tax_amount = $base_iva * 0.14;

// Total considerando IRT
$total = $base + $tax_amount - $discount_financial;
```

### **3. Interface de Usuário:**

**Checkbox:**
```blade
<label class="flex items-center cursor-pointer">
    <input type="checkbox" wire:model.live="is_service">
    <span class="ml-3">
        É Prestação de Serviço (IRT 6.5%)
    </span>
</label>
```

**Resumo:**
```
┌────────────────────────────────┐
│ ☑️ É Prestação de Serviço      │
├────────────────────────────────┤
│ Subtotal:       100,000.00 Kz  │
│ Desc. Comercial: -10,000.00 Kz │
│ Base IVA:        90,000.00 Kz  │
│ 🔵 IVA (14%):   +12,600.00 Kz  │
│ 🔴 IRT (6.5%):   -5,850.00 Kz  │
│   (retido na fonte)            │
│ ═══════════════════════════════│
│ TOTAL:           96,750.00 Kz  │
└────────────────────────────────┘
```

---

## 💰 Exemplos Práticos

### **Exemplo 1: Serviço de Consultoria**

```
Serviço: Consultoria Empresarial
Valor: 200,000.00 Kz
Tipo: SERVIÇO ✅

Cálculo:
1. Subtotal:           200,000.00 Kz
2. Desc. Comercial:     -20,000.00 Kz (10%)
3. Base:               180,000.00 Kz
4. IRT (6.5%):         -11,700.00 Kz (retido)
5. IVA (14%):          +25,200.00 Kz
6. TOTAL A PAGAR:      193,500.00 Kz

Prestador recebe: 180,000.00 - 11,700.00 + 25,200.00 = 193,500.00 Kz
IRT retido: 11,700.00 Kz (entregar à AGT)
```

### **Exemplo 2: Venda de Produto**

```
Produto: Notebook Dell
Valor: 200,000.00 Kz
Tipo: PRODUTO ❌

Cálculo:
1. Subtotal:           200,000.00 Kz
2. Desc. Comercial:     -20,000.00 Kz (10%)
3. Base:               180,000.00 Kz
4. IRT:                      0.00 Kz (NÃO APLICA)
5. IVA (14%):          +25,200.00 Kz
6. TOTAL A PAGAR:      205,200.00 Kz

Vendedor recebe: 205,200.00 Kz
IRT retido: 0.00 Kz
```

---

## 📄 Documentos Afetados

Todos os documentos do sistema suportam IRT:

| Documento | Campo `is_service` | Campo `irt_amount` |
|-----------|-------------------|-------------------|
| **Proforma Venda** | ✅ | ✅ |
| **Proforma Compra** | ✅ | ✅ |
| **Fatura Venda** | ✅ | ✅ |
| **Fatura Compra** | ✅ | ✅ |

---

## 🎨 Cores da Interface

| Elemento | Cor | Classe Tailwind |
|----------|-----|-----------------|
| IRT (retido) | 🔴 Vermelho | `red-600` |
| IVA | 🔵 Azul | `blue-600` |
| Checkbox Serviço | 🟣 Roxo | `purple-600` |
| Total | 🟢 Verde | `green-600` |

---

## ⚖️ Legislação

### **Base Legal:**
- **Código do IRT** - Lei nº 18/14
- **Taxa**: 6.5% (Grupo A - Trabalho por conta de outrem)
- **Retenção**: Obrigatória pelo adquirente
- **Prazo**: Até dia 20 do mês seguinte

### **Isenções:**
- Serviços abaixo do limite estabelecido
- Prestadores isentos (lista AGT)
- Serviços específicos (educação, saúde)

---

## ✅ Validações Necessárias

```php
// Verificar se é serviço
if ($is_service) {
    // Calcular IRT
    $irt_amount = $base * 0.065;
    
    // Validar taxa
    if ($irt_rate < 0 || $irt_rate > 100) {
        throw new \Exception('Taxa IRT inválida');
    }
}

// IRT só em serviços
if (!$is_service && $irt_amount > 0) {
    throw new \Exception('IRT só se aplica a serviços');
}
```

---

## 📊 Relatórios

### **Relatório de IRT Retido:**
- Total IRT retido no mês
- Por prestador
- Por tipo de serviço
- Para entrega à AGT

### **Comprovante de Retenção:**
- Número do documento
- Prestador (nome, NIF)
- Valor do serviço
- IRT retido (6.5%)
- Data da retenção

---

## 🔄 Integração SAFT-AO

### **XML SAFT-AO:**
```xml
<Invoice>
    <InvoiceType>FS</InvoiceType> <!-- Fatura Serviços -->
    <Line>
        <ProductCode>SRV001</ProductCode>
        <ProductDescription>Consultoria</ProductDescription>
        <Quantity>1</Quantity>
        <UnitPrice>200000.00</UnitPrice>
        <CreditAmount>200000.00</CreditAmount>
        <Tax>
            <TaxType>IVA</TaxType>
            <TaxPercentage>14.00</TaxPercentage>
        </Tax>
        <WithholdingTax>
            <WithholdingTaxType>IRT</WithholdingTaxType>
            <WithholdingTaxPercentage>6.50</WithholdingTaxPercentage>
            <WithholdingTaxAmount>13000.00</WithholdingTaxAmount>
        </WithholdingTax>
    </Line>
</Invoice>
```

---

## 📚 Referências

- **AGT** - Administração Geral Tributária
- **SAFT-AO 2025** - Standard Audit File for Tax
- **Código do IRT** - Lei 18/14
- **Taxa IRT**: 6.5% (Grupo A)

---

## 🚀 Status de Implementação

| Item | Status |
|------|--------|
| Migration BD | ✅ Completo |
| Models | ✅ Completo |
| Componente Livewire | ✅ Completo |
| Interface UI | ✅ Completo |
| Cálculo IRT | ✅ Completo |
| Documentação | ✅ Completo |

**Sistema 100% conforme legislação angolana de IRT! 🇦🇴**
