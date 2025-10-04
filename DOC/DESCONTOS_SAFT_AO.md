# Descontos SAFT-AO 2025 - Angola

## 📋 Tipos de Desconto

De acordo com o SAFT-AO 2025, existem **2 tipos de descontos** que devem ser tratados separadamente:

### **1. Desconto Comercial (Commercial Discount)**
- **Quando**: Aplicado **ANTES** do cálculo do IVA
- **Natureza**: Desconto sobre o preço do produto/serviço
- **Exemplos**:
  - Desconto por quantidade
  - Desconto promocional
  - Desconto por acordo comercial
  - Desconto de catálogo

### **2. Desconto Financeiro (Financial Discount)**
- **Quando**: Aplicado **APÓS** o cálculo do IVA
- **Natureza**: Desconto sobre a forma de pagamento
- **Exemplos**:
  - Desconto por pronto pagamento
  - Desconto por pagamento antecipado
  - Desconto por meio de pagamento específico

## 🧮 Ordem de Cálculo SAFT-AO

```
1. SUBTOTAL (Soma dos produtos)
   └─ Exemplo: 100,000.00 Kz

2. - DESCONTO COMERCIAL (antes IVA)
   └─ Exemplo: -10,000.00 Kz

3. = BASE DE IVA
   └─ Exemplo: 90,000.00 Kz

4. + IVA (14% em Angola)
   └─ Exemplo: +12,600.00 Kz (14% de 90,000)

5. = TOTAL COM IVA
   └─ Exemplo: 102,600.00 Kz

6. - DESCONTO FINANCEIRO (após IVA)
   └─ Exemplo: -2,600.00 Kz

7. = TOTAL A PAGAR
   └─ Exemplo: 100,000.00 Kz
```

## 💻 Implementação

### **Campos no Banco de Dados:**

#### **Tabelas Principais (Proformas/Faturas):**
```sql
discount_commercial DECIMAL(15,2) -- Desconto Comercial (antes IVA)
discount_financial  DECIMAL(15,2) -- Desconto Financeiro (após IVA)
discount_amount     DECIMAL(15,2) -- Desconto legado (mantido para compatibilidade)
```

#### **Tabelas de Itens:**
```sql
discount_commercial_percent DECIMAL(5,2) -- % Desconto Comercial por linha
discount_commercial_amount  DECIMAL(15,2) -- Valor Desconto Comercial por linha
```

### **Cálculo no Backend (ProformaCreate.php):**

```php
// 1. Subtotal dos produtos
$subtotal = Cart::session($instance)->getSubTotal();

// 2. Aplicar Desconto Comercial (antes IVA)
$subtotal_after_commercial = $subtotal - $discount_commercial - $discount_amount;

// 3. Calcular IVA sobre base com desconto comercial
$tax_amount = 0;
foreach ($cartItems as $item) {
    $itemSubtotal = $item->price * $item->quantity;
    // Desconto proporcional por item
    $itemDiscountRatio = $subtotal > 0 
        ? ($discount_commercial + $discount_amount) / $subtotal 
        : 0;
    $itemSubtotalAfterDiscount = $itemSubtotal * (1 - $itemDiscountRatio);
    $tax_amount += $itemSubtotalAfterDiscount * ($item->attributes['tax_rate'] / 100);
}

// 4. Total com IVA
$total_with_tax = $subtotal_after_commercial + $tax_amount;

// 5. Aplicar Desconto Financeiro (após IVA)
$total = $total_with_tax - $discount_financial;
```

## 📊 Interface de Usuário

### **Card de Resumo:**

```
┌────────────────────────────────┐
│ Resumo                         │
├────────────────────────────────┤
│ Subtotal:      100,000.00 Kz   │
│                                │
│ ▼ Descontos antes do IVA       │
│   🎯 Comercial:  -10,000.00 Kz │
│   📋 Legado:          -0.00 Kz │
│                                │
│ Base IVA:       90,000.00 Kz   │
│ 📄 IVA (14%):  +12,600.00 Kz   │
│                                │
│ ▼ Descontos após IVA           │
│   💰 Financeiro:  -2,600.00 Kz │
│                                │
│ ═══════════════════════════════│
│ TOTAL A PAGAR: 100,000.00 Kz   │
└────────────────────────────────┘
```

### **Cores por Tipo:**
- 🟠 **Desconto Comercial**: Laranja (`orange-600`)
- 🟢 **Desconto Financeiro**: Verde (`green-600`)
- 🔵 **IVA**: Azul (`blue-600`)

## 📄 Estrutura XML SAFT-AO

```xml
<Invoice>
    <Line>
        <LineNumber>1</LineNumber>
        <!-- Desconto Comercial na linha -->
        <Settlement>
            <SettlementAmount>10.00</SettlementAmount>
        </Settlement>
        <ProductCode>PROD001</ProductCode>
        <Quantity>1.00</Quantity>
        <UnitPrice>100.00</UnitPrice>
        <!-- Base após desconto comercial -->
        <TaxBase>90.00</TaxBase>
        <TaxPointDate>2025-10-03</TaxPointDate>
        <Description>Produto Exemplo</Description>
        <CreditAmount>90.00</CreditAmount>
        <Tax>
            <TaxType>IVA</TaxType>
            <TaxCountryRegion>AO</TaxCountryRegion>
            <TaxCode>NOR</TaxCode>
            <TaxPercentage>14.00</TaxPercentage>
            <TaxAmount>12.60</TaxAmount>
        </Tax>
    </Line>
    <DocumentTotals>
        <TaxPayable>12.60</TaxPayable>
        <NetTotal>90.00</NetTotal>
        <GrossTotal>102.60</GrossTotal>
        <!-- Desconto Financeiro no total -->
        <Settlement>
            <SettlementAmount>2.60</SettlementAmount>
        </Settlement>
        <PayableAmount>100.00</PayableAmount>
    </DocumentTotals>
</Invoice>
```

## ✅ Conformidade AGT

### **Requisitos SAFT-AO 2025:**
- ✅ Desconto comercial aplicado antes do IVA
- ✅ Desconto financeiro aplicado após o IVA
- ✅ Base de IVA calculada corretamente
- ✅ Campos separados no XML
- ✅ Total a pagar líquido de todos os descontos

### **Validações:**
1. Descontos não podem ser negativos
2. Desconto comercial não pode exceder subtotal
3. Desconto financeiro não pode exceder total com IVA
4. Todos os valores com 2 casas decimais
5. IVA calculado sobre base já descontada

## 🔄 Migração de Dados Antigos

Para documentos criados antes desta implementação:
- `discount_amount` (legado) é tratado como desconto comercial
- `discount_commercial` e `discount_financial` iniciam com 0
- Cálculo de IVA mantém retrocompatibilidade

## 📚 Referências

- **SAFT-AO 1.01_01** - Portaria AGT
- **Código do IVA Angola** - Lei 7/19
- **Guia de Implementação SAFT-AO 2025** - AGT

## 🎯 Exemplo Prático

**Cenário:** Venda de notebook

```
Produto: Notebook Dell
Preço: 150,000.00 Kz
Quantidade: 1

Desconto Comercial (10%): -15,000.00 Kz
Base IVA: 135,000.00 Kz
IVA (14%): +18,900.00 Kz
Total com IVA: 153,900.00 Kz
Desconto Financeiro (pronto): -3,900.00 Kz

TOTAL A PAGAR: 150,000.00 Kz
```

## 🚀 Próximos Passos

1. ✅ Implementar nos modelos de faturas
2. ✅ Adicionar ao XML SAFT-AO export
3. ✅ Incluir em relatórios fiscais
4. ✅ Treinar usuários na diferença
