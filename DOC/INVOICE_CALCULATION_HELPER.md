# Invoice Calculation Helper

## 📋 Visão Geral

O `InvoiceCalculationHelper` centraliza todos os cálculos de faturação conforme o modelo AGT Angola (Decreto Presidencial 312/18). Este helper garante que todos os documentos (Proformas e Faturas de Vendas/Compras) usem a mesma lógica de cálculo.

## 🎯 Objetivo

- **Consistência:** Todos os documentos calculam da mesma forma
- **Manutenibilidade:** Alterar em um lugar atualiza todos os documentos
- **Testabilidade:** Fácil criar testes unitários
- **Reutilização:** Métodos podem ser usados em qualquer parte do sistema

---

## 📚 Métodos Disponíveis

### 1. `calculateTotals()` - Cálculo Completo do Documento

Calcula todos os valores de um documento seguindo o fluxo AGT Angola.

**Parâmetros:**
```php
calculateTotals(
    $cartItems,              // Collection de items do carrinho
    $discountCommercial,     // Desconto comercial global
    $discountAmount,         // Desconto adicional
    $discountFinancial,      // Desconto financeiro
    $isService               // Se é serviço (para IRT)
)
```

**Retorna:**
```php
[
    'subtotal_original' => 1000.00,        // Total Bruto
    'total_discount_items' => 50.00,       // Desc. por linha
    'subtotal' => 950.00,                  // Valor Líquido
    'desconto_comercial_total' => 100.00,  // Total Desc. Comercial
    'incidencia_iva' => 850.00,            // Base IVA
    'tax_amount' => 119.00,                // IVA (14%)
    'irt_amount' => 55.25,                 // IRT (6.5% se serviço)
    'total' => 913.75,                     // Total a Pagar
]
```

**Exemplo de Uso:**
```php
use App\Helpers\InvoiceCalculationHelper;

$cartItems = Cart::session($this->cartInstance)->getContent();

$totals = InvoiceCalculationHelper::calculateTotals(
    $cartItems,
    $this->discount_commercial,
    $this->discount_amount,
    $this->discount_financial,
    $this->is_service
);

// Usar os valores
$invoice->subtotal = $totals['subtotal'];
$invoice->tax_amount = $totals['tax_amount'];
$invoice->irt_amount = $totals['irt_amount'];
$invoice->total = $totals['total'];
```

---

### 2. `calculateItemTotals()` - Cálculo por Item

Calcula valores individuais de um item.

**Parâmetros:**
```php
calculateItemTotals(
    $price,                              // Preço unitário
    $quantity,                           // Quantidade
    $discountPercent,                    // Desconto percentual
    $taxRate,                            // Taxa IVA
    $proportionalCommercialDiscount,     // Desc. comercial proporcional
    $proportionalFinancialDiscount       // Desc. financeiro proporcional
)
```

**Retorna:**
```php
[
    'unit_price' => 100.00,
    'quantity' => 5,
    'discount_percent' => 10.00,
    'discount_amount' => 50.00,
    'subtotal' => 450.00,
    'tax_rate' => 14.00,
    'tax_amount' => 63.00,
    'total' => 513.00,
]
```

---

### 3. `calculateProportionalDiscounts()` - Descontos Proporcionais

Distribui descontos globais proporcionalmente entre os items.

**Uso:**
```php
$proportions = InvoiceCalculationHelper::calculateProportionalDiscounts(
    $cartItems,
    $this->discount_commercial,
    $this->discount_financial
);

// Para cada item
foreach ($cartItems as $item) {
    $proportion = $proportions[$item->id];
    $commercialDiscount = $proportion['commercial_discount'];
    $financialDiscount = $proportion['financial_discount'];
}
```

---

### 4. `calculateTax()` - Calcular IVA

Calcula apenas o IVA de um valor.

```php
$iva = InvoiceCalculationHelper::calculateTax(1000, 14); // 140.00
```

---

### 5. `calculateIRT()` - Calcular Retenção

Calcula IRT 6.5% para serviços.

```php
$irt = InvoiceCalculationHelper::calculateIRT(1000, true);  // 65.00 (serviço)
$irt = InvoiceCalculationHelper::calculateIRT(1000, false); // 0.00 (produto)
```

---

### 6. `formatCurrency()` - Formatar Moeda

Formata valor para exibição.

```php
$formatted = InvoiceCalculationHelper::formatCurrency(1234.56); 
// "1.234,56 AOA"
```

---

### 7. `validateCalculations()` - Validar Cálculos

Valida se os cálculos estão corretos (útil para testes).

```php
$isValid = InvoiceCalculationHelper::validateCalculations($totals);
```

---

## 🔄 Fluxo de Cálculo AGT Angola

```
1. TOTAL BRUTO (Ilíquido)
   └─> Σ (Quantidade × Preço)

2. DESCONTO COMERCIAL POR LINHA
   └─> Aplicado individualmente em cada item

3. VALOR LÍQUIDO
   └─> Total Bruto - Descontos por Linha

4. DESCONTO COMERCIAL ADICIONAL
   └─> Desconto global (antes do IVA)

5. DESCONTO FINANCEIRO
   └─> Desconto após comercial (raro)

6. INCIDÊNCIA IVA (Base Tributável)
   └─> Valor após todos os descontos

7. IVA (14% Angola)
   └─> Sobre a Incidência

8. RETENÇÃO IRT (6.5%)
   └─> Apenas para SERVIÇOS

9. TOTAL A PAGAR
   └─> Incidência + IVA - IRT
```

---

## 📝 Exemplo Completo de Refatoração

### ANTES (código duplicado em cada componente):
```php
public function render()
{
    // 🎩 CÁLCULO MODELO AGT ANGOLA - SEQUENCIAL
    $total_bruto = 0;
    $desconto_comercial_linhas = 0;
    
    foreach ($cartItems as $item) {
        $valorBrutoLinha = $item->price * $item->quantity;
        $total_bruto += $valorBrutoLinha;
        
        $descontoPercent = $item->attributes['discount_percent'] ?? 0;
        $descontoLinha = $valorBrutoLinha * ($descontoPercent / 100);
        $desconto_comercial_linhas += $descontoLinha;
    }
    
    // ... muitas linhas de código duplicado ...
}
```

### DEPOIS (usando Helper):
```php
use App\Helpers\InvoiceCalculationHelper;

public function render()
{
    $cartItems = Cart::session($this->cartInstance)->getContent();
    
    // ✅ Cálculo centralizado em 1 linha
    $totals = InvoiceCalculationHelper::calculateTotals(
        $cartItems,
        $this->discount_commercial,
        $this->discount_amount,
        $this->discount_financial,
        $this->is_service
    );
    
    return view('...', array_merge([
        'cartItems' => $cartItems,
    ], $totals));
}
```

---

## 🧪 Testes

Criar `tests/Unit/InvoiceCalculationHelperTest.php`:

```php
public function test_calculate_totals_without_discounts()
{
    $cartItems = collect([
        (object)[
            'price' => 100,
            'quantity' => 2,
            'attributes' => ['discount_percent' => 0, 'tax_rate' => 14]
        ]
    ]);
    
    $totals = InvoiceCalculationHelper::calculateTotals($cartItems, 0, 0, 0, false);
    
    $this->assertEquals(200.00, $totals['subtotal_original']);
    $this->assertEquals(200.00, $totals['incidencia_iva']);
    $this->assertEquals(28.00, $totals['tax_amount']);
    $this->assertEquals(0.00, $totals['irt_amount']);
    $this->assertEquals(228.00, $totals['total']);
}
```

---

## ✅ Vantagens

| Vantagem | Descrição |
|----------|-----------|
| **Consistência** | Todos os documentos calculam exatamente da mesma forma |
| **Manutenção** | Alterar em 1 lugar atualiza todos os 4 tipos de documentos |
| **Testável** | Fácil criar testes unitários isolados |
| **Documentado** | Lógica AGT Angola em um só lugar |
| **Performance** | Métodos otimizados e reutilizáveis |
| **Compliance** | Garante conformidade com AGT Angola |

---

## 🎯 Próximos Passos

1. ✅ Helper criado
2. [ ] Refatorar ProformaCreate (Vendas)
3. [ ] Refatorar ProformaCreate (Compras)
4. [ ] Refatorar InvoiceCreate (Vendas)
5. [ ] Refatorar InvoiceCreate (Compras)
6. [ ] Criar testes unitários
7. [ ] Documentar mudanças no ROADMAP

---

**Criado em:** 04/10/2025  
**Autor:** Sistema SOS ERP  
**Versão:** 1.0.0
