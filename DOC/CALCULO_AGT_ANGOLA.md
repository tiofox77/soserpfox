# 🎩 CÁLCULO AGT ANGOLA - IMPLEMENTADO
## Decreto Presidencial 312/18

---

## ✅ IMPLEMENTAÇÃO COMPLETA

### Localização do Código
**Arquivo:** `app/Livewire/Invoicing/Sales/ProformaCreate.php`  
**Linhas:** 226-310

---

## 📊 SEQUÊNCIA DE CÁLCULO IMPLEMENTADA

### PASSO 1: TOTAL BRUTO (Valor Ilíquido)
```php
$total_bruto = 0;
$desconto_comercial_linhas = 0;

foreach ($cartItems as $item) {
    $valorBrutoLinha = $item->price * $item->quantity;
    $total_bruto += $valorBrutoLinha;
}
```
**Fórmula:** `Σ (Quantidade × Preço Unitário)`

---

### PASSO 2: DESCONTO COMERCIAL POR LINHA
```php
foreach ($cartItems as $item) {
    $descontoPercent = $item->attributes['discount_percent'] ?? 0;
    $descontoLinha = $valorBrutoLinha * ($descontoPercent / 100);
    $desconto_comercial_linhas += $descontoLinha;
}
```
**Aplicado:** PRIMEIRO na sequência  
**Base:** Valor bruto de cada linha

---

### PASSO 3: VALOR LÍQUIDO
```php
$valor_liquido = $total_bruto - $desconto_comercial_linhas;
```
**Fórmula:** `Total Bruto - Desconto Comercial Linhas`

---

### PASSO 4: DESCONTO COMERCIAL ADICIONAL
```php
$desconto_comercial_total = $desconto_comercial_linhas 
    + $this->discount_commercial 
    + $this->discount_amount;

$valor_apos_desc_comercial = $total_bruto - $desconto_comercial_total;
```
**Inclui:** Descontos por linha + Desconto comercial global

---

### PASSO 5: DESCONTO FINANCEIRO
```php
$desconto_financeiro = $this->discount_financial;
```
**Aplicado:** APÓS desconto comercial  
**Base:** Valor já com desconto comercial

---

### PASSO 6: INCIDÊNCIA IVA (Base de IVA)
```php
$incidencia_iva = $valor_apos_desc_comercial - $desconto_financeiro;
```
**Fórmula:** `Valor após Desc. Comercial - Desc. Financeiro`  
**Importante:** É a BASE para cálculo de IVA e Retenção

---

### PASSO 7: CÁLCULO DO IVA
```php
$tax_amount = 0;
foreach ($cartItems as $item) {
    // Valor bruto da linha
    $valorBrutoLinha = $item->price * $item->quantity;
    
    // Desconto comercial da linha
    $descontoPercent = $item->attributes['discount_percent'] ?? 0;
    $descontoLinha = $valorBrutoLinha * ($descontoPercent / 100);
    $valorLiquidoLinha = $valorBrutoLinha - $descontoLinha;
    
    // Proporção da linha no valor líquido
    $proporcao = $valor_liquido > 0 ? $valorLiquidoLinha / $valor_liquido : 0;
    
    // Desconto comercial adicional proporcional
    $descComercialAdicionalLinha = ($this->discount_commercial + $this->discount_amount) * $proporcao;
    
    // Desconto financeiro proporcional
    $descFinanceiroLinha = $desconto_financeiro * $proporcao;
    
    // Base IVA da linha
    $baseIVALinha = $valorLiquidoLinha - $descComercialAdicionalLinha - $descFinanceiroLinha;
    
    // IVA da linha
    $taxRate = $item->attributes['tax_rate'] ?? 14;
    $tax_amount += $baseIVALinha * ($taxRate / 100);
}
```
**Distribuição:** Proporcional por linha  
**Taxa padrão:** 14% (Angola)

---

### PASSO 8: RETENÇÃO IRT (6,5%)
```php
$irt_amount = 0;
if ($this->is_service) {
    $irt_amount = $incidencia_iva * 0.065; // 6.5% IRT Angola
}
```
**Base:** Incidência IVA  
**Taxa:** 6,5% (serviços)  
**Quando:** Apenas se `is_service = true`

---

### PASSO 9: TOTAL A PAGAR
```php
$total = $incidencia_iva + $tax_amount - $irt_amount;
```
**Fórmula Final:** `Incidência IVA + IVA - Retenção`

---

## 📋 EXEMPLO PRÁTICO

### Dados de Entrada
```
Produto A: 1 × 39.999,00 Kz (desc. 20%)
Produto B: 1 × 54.999,00 Kz (desc. 20%)
Desconto Financeiro: 20%
IVA: 14%
Retenção: 6,5%
```

### Cálculo Sequencial

1. **Total Bruto:** 94.998,00 Kz
2. **Desc. Comercial Linhas:** 18.999,60 Kz
3. **Valor Líquido:** 75.998,40 Kz
4. **Desc. Financeiro (20%):** 15.199,68 Kz
5. **Incidência IVA:** 60.798,72 Kz
6. **IVA (14%):** 8.511,82 Kz
7. **Retenção (6,5%):** 3.951,92 Kz
8. **TOTAL A PAGAR:** 65.358,62 Kz

---

## 🎯 RESUMO NO SISTEMA

```
┌────────────────────────────────────┐
│ 📊 Resumo                          │
├────────────────────────────────────┤
│ ☑️ É Prestação de Serviço          │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   │
│                                    │
│ Total líquido        94.998,00 Kz │
│ Desconto Comercial   18.999,60 Kz │
│ Desconto Financeiro  15.199,68 Kz │
│ Total De Imposto      8.511,82 Kz │
│ Retenção (6,5%)       3.951,92 Kz │
│ ══════════════════════════════════ │
│ Total (AOA)          65.358,62 Kz │
│                                    │
│ ℹ️ Incidência IVA: 60.798,72 Kz   │
│ Decreto Presidencial 312/18       │
└────────────────────────────────────┘
```

---

## 🔍 DEBUG E VALIDAÇÃO

### Ativar Log de Debug
No arquivo `ProformaCreate.php`, descomente as linhas 298-310:

```php
\Log::info('🎩 AGT ANGOLA SEQUENCIAL', [
    '1_total_bruto' => $total_bruto,
    '2_desc_comercial_linhas' => $desconto_comercial_linhas,
    '3_desc_comercial_adicional' => ($this->discount_commercial + $this->discount_amount),
    '4_desc_comercial_total' => $desconto_comercial_total,
    '5_valor_apos_comercial' => $valor_apos_desc_comercial,
    '6_desc_financeiro' => $desconto_financeiro,
    '7_incidencia_iva' => $incidencia_iva,
    '8_iva_14pct' => $tax_amount,
    '9_retencao_6_5pct' => $irt_amount,
    '10_total_final' => $total,
]);
```

Ver logs em: `storage/logs/laravel.log`

---

## ✅ CONFORMIDADE AGT

### Normas Atendidas
- ✅ Decreto Presidencial 312/18
- ✅ Portaria nº 31.1/AGT/2020
- ✅ Cálculo sequencial (não simultâneo)
- ✅ Desconto comercial antes de financeiro
- ✅ Incidência IVA correta
- ✅ Retenção sobre base correta
- ✅ Distribuição proporcional de descontos

### Campos Obrigatórios
- ✅ Total Bruto (Valor Ilíquido)
- ✅ Desconto Comercial
- ✅ Desconto Financeiro
- ✅ Incidência IVA (Base)
- ✅ Total De Imposto (IVA)
- ✅ Retenção (se aplicável)
- ✅ Total a Pagar

---

## 📝 OBSERVAÇÕES IMPORTANTES

### ⚠️ Ordem SEMPRE Sequencial
Os descontos são aplicados em **sequência**, nunca simultaneamente:
1. Primeiro: Desconto Comercial (por linha e global)
2. Segundo: Desconto Financeiro (sobre valor com desc. comercial)
3. Terceiro: IVA (sobre base com todos os descontos)
4. Quarto: Retenção (sobre base IVA)

### ⚠️ Distribuição Proporcional
Descontos globais (comercial adicional e financeiro) são distribuídos **proporcionalmente** entre as linhas para cálculo correto do IVA quando há produtos com taxas diferentes.

### ⚠️ Incidência IVA
É o valor **mais importante** do cálculo, pois:
- É a base para IVA
- É a base para Retenção
- Aparece obrigatoriamente na fatura AGT
- Deve ser claramente discriminado

---

## 🎓 REFERÊNCIAS

- **Decreto Presidencial 312/18** - Normas AGT Angola
- **Portaria nº 31.1/AGT/2020** - Software de Faturação
- **Código Implementado:** `app/Livewire/Invoicing/Sales/ProformaCreate.php`
- **View:** `resources/views/livewire/invoicing/sales/proforma-create.blade.php`

---

**Implementado em:** 2025-01-03  
**Versão:** 1.0.0  
**Status:** ✅ Conforme AGT Angola
