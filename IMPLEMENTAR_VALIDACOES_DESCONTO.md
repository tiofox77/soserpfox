# 🚨 VALIDAÇÕES DE DESCONTO - NÃO IMPLEMENTADAS

## ❌ PROBLEMA IDENTIFICADO

As configurações de política de descontos estão sendo **salvas** mas **NÃO estão sendo aplicadas** nos documentos de vendas/compras.

**Configurações existentes:**
- ✅ `allow_line_discounts` - Salvo no banco
- ✅ `allow_commercial_discount` - Salvo no banco
- ✅ `allow_financial_discount` - Salvo no banco
- ✅ `max_discount_percent` - Salvo no banco

**Status:** ❌ Não validadas nos formulários de documentos

---

## ✅ SOLUÇÃO CRIADA

**Arquivo:** `app/Helpers/DiscountHelper.php`

**Métodos disponíveis:**
```php
DiscountHelper::isLineDiscountAllowed()           // bool
DiscountHelper::isCommercialDiscountAllowed()     // bool
DiscountHelper::isFinancialDiscountAllowed()      // bool
DiscountHelper::getMaxDiscountPercent()           // float
DiscountHelper::isDiscountValid($percent)         // bool
DiscountHelper::validateDiscount($percent, $type) // array
DiscountHelper::getDiscountErrorMessage($percent, $type) // string|null
```

---

## 📋 ONDE IMPLEMENTAR

### **1. Faturas de Venda** 📄
**Arquivo:** `app/Livewire/Invoicing/Sales/InvoiceCreate.php`

**No método de adicionar item:**
```php
use App\Helpers\DiscountHelper;

public function addItem()
{
    // ... código existente ...
    
    // VALIDAR DESCONTO POR LINHA
    if ($this->discount > 0) {
        $validation = DiscountHelper::validateDiscount($this->discount, 'line');
        
        if (!$validation['valid']) {
            $this->dispatch('error', message: $validation['message']);
            return;
        }
    }
    
    // ... resto do código ...
}
```

**No método de calcular totais (desconto comercial):**
```php
public function updatedCommercialDiscount()
{
    if ($this->commercial_discount > 0) {
        $validation = DiscountHelper::validateDiscount($this->commercial_discount, 'commercial');
        
        if (!$validation['valid']) {
            $this->dispatch('error', message: $validation['message']);
            $this->commercial_discount = 0;
            return;
        }
    }
    
    $this->calculateTotals();
}
```

**No método de desconto financeiro:**
```php
public function updatedFinancialDiscount()
{
    if ($this->financial_discount > 0) {
        $validation = DiscountHelper::validateDiscount($this->financial_discount, 'financial');
        
        if (!$validation['valid']) {
            $this->dispatch('error', message: $validation['message']);
            $this->financial_discount = 0;
            return;
        }
    }
    
    $this->calculateTotals();
}
```

---

### **2. Na View (Blade)** 🎨

**Ocultar campos se não permitidos:**

```blade
{{-- Desconto por Linha --}}
@if(App\Helpers\DiscountHelper::isLineDiscountAllowed())
<div>
    <label>Desconto (%)</label>
    <input type="number" wire:model="discount" max="{{ App\Helpers\DiscountHelper::getMaxDiscountPercent() }}">
    <p class="text-xs text-gray-500">
        Máximo: {{ App\Helpers\DiscountHelper::getMaxDiscountPercent() }}%
    </p>
</div>
@else
<p class="text-xs text-red-500">
    <i class="fas fa-ban mr-1"></i>
    Desconto por linha não permitido
</p>
@endif

{{-- Desconto Comercial --}}
@if(App\Helpers\DiscountHelper::isCommercialDiscountAllowed())
<div>
    <label>Desconto Comercial (%)</label>
    <input type="number" wire:model="commercial_discount" max="{{ App\Helpers\DiscountHelper::getMaxDiscountPercent() }}">
</div>
@else
<div class="bg-red-50 border border-red-200 rounded p-2 text-xs text-red-600">
    Desconto comercial desativado nas configurações
</div>
@endif

{{-- Desconto Financeiro --}}
@if(App\Helpers\DiscountHelper::isFinancialDiscountAllowed())
<div>
    <label>Desconto Financeiro (%)</label>
    <input type="number" wire:model="financial_discount" max="{{ App\Helpers\DiscountHelper::getMaxDiscountPercent() }}">
</div>
@else
<div class="bg-red-50 border border-red-200 rounded p-2 text-xs text-red-600">
    Desconto financeiro desativado nas configurações
</div>
@endif
```

---

### **3. Validação no Save** 💾

**Antes de salvar o documento:**

```php
public function save()
{
    // ... validações existentes ...
    
    // VALIDAR DESCONTOS ANTES DE SALVAR
    foreach ($this->items as $item) {
        if (isset($item['discount']) && $item['discount'] > 0) {
            $validation = DiscountHelper::validateDiscount($item['discount'], 'line');
            if (!$validation['valid']) {
                $this->dispatch('error', message: 'Item com desconto inválido: ' . $validation['message']);
                return;
            }
        }
    }
    
    if ($this->commercial_discount > 0) {
        $validation = DiscountHelper::validateDiscount($this->commercial_discount, 'commercial');
        if (!$validation['valid']) {
            $this->dispatch('error', message: $validation['message']);
            return;
        }
    }
    
    if ($this->financial_discount > 0) {
        $validation = DiscountHelper::validateDiscount($this->financial_discount, 'financial');
        if (!$validation['valid']) {
            $this->dispatch('error', message: $validation['message']);
            return;
        }
    }
    
    // ... continuar com save ...
}
```

---

## 📝 ARQUIVOS QUE PRECISAM SER MODIFICADOS

```
❌ NÃO IMPLEMENTADO:
├── app/Livewire/Invoicing/Sales/InvoiceCreate.php
├── app/Livewire/Invoicing/Sales/ProformaCreate.php
├── app/Livewire/Invoicing/Purchases/InvoiceCreate.php
├── app/Livewire/POS/POSSystem.php
└── Suas views correspondentes (.blade.php)

✅ CRIADO:
└── app/Helpers/DiscountHelper.php (HELPER PRONTO)
```

---

## 🎯 EXEMPLO COMPLETO DE USO

### **No Livewire Component:**

```php
<?php

namespace App\Livewire\Invoicing\Sales;

use App\Helpers\DiscountHelper;
use Livewire\Component;

class InvoiceCreate extends Component
{
    public $discount = 0;
    public $commercial_discount = 0;
    public $financial_discount = 0;
    
    // Validar quando usuário digita desconto por linha
    public function updatedDiscount($value)
    {
        if ($value > 0) {
            $validation = DiscountHelper::validateDiscount($value, 'line');
            if (!$validation['valid']) {
                $this->dispatch('error', message: $validation['message']);
                $this->discount = 0;
            }
        }
    }
    
    // Validar desconto comercial
    public function updatedCommercialDiscount($value)
    {
        if ($value > 0) {
            $validation = DiscountHelper::validateDiscount($value, 'commercial');
            if (!$validation['valid']) {
                $this->dispatch('error', message: $validation['message']);
                $this->commercial_discount = 0;
            }
        }
        $this->calculateTotals();
    }
    
    // Validar desconto financeiro
    public function updatedFinancialDiscount($value)
    {
        if ($value > 0) {
            $validation = DiscountHelper::validateDiscount($value, 'financial');
            if (!$validation['valid']) {
                $this->dispatch('error', message: $validation['message']);
                $this->financial_discount = 0;
            }
        }
        $this->calculateTotals();
    }
    
    public function render()
    {
        return view('livewire.invoicing.sales.invoice-create', [
            'maxDiscountAllowed' => DiscountHelper::getMaxDiscountPercent(),
            'lineDiscountAllowed' => DiscountHelper::isLineDiscountAllowed(),
            'commercialDiscountAllowed' => DiscountHelper::isCommercialDiscountAllowed(),
            'financialDiscountAllowed' => DiscountHelper::isFinancialDiscountAllowed(),
        ]);
    }
}
```

### **Na View:**

```blade
<div>
    {{-- Desconto por Linha --}}
    @if($lineDiscountAllowed)
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-percentage mr-1"></i>
                Desconto (%)
            </label>
            <input type="number" 
                   wire:model.blur="discount" 
                   max="{{ $maxDiscountAllowed }}"
                   step="0.01"
                   class="w-full px-3 py-2 border rounded-lg">
            <p class="text-xs text-gray-500 mt-1">
                <i class="fas fa-info-circle mr-1"></i>
                Máximo permitido: {{ $maxDiscountAllowed }}%
            </p>
        </div>
    @else
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-sm text-red-600">
                <i class="fas fa-ban mr-1"></i>
                Desconto por linha desativado nas configurações
            </p>
        </div>
    @endif
    
    {{-- Desconto Comercial --}}
    @if($commercialDiscountAllowed)
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-handshake mr-1"></i>
                Desconto Comercial (%)
            </label>
            <input type="number" 
                   wire:model.blur="commercial_discount" 
                   max="{{ $maxDiscountAllowed }}"
                   step="0.01"
                   class="w-full px-3 py-2 border rounded-lg">
        </div>
    @endif
    
    {{-- Desconto Financeiro --}}
    @if($financialDiscountAllowed)
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-dollar-sign mr-1"></i>
                Desconto Financeiro (%)
            </label>
            <input type="number" 
                   wire:model.blur="financial_discount" 
                   max="{{ $maxDiscountAllowed }}"
                   step="0.01"
                   class="w-full px-3 py-2 border rounded-lg">
        </div>
    @endif
</div>
```

---

## ⚠️ IMPORTANTE

### **Atualmente:**
```
❌ Configurações são IGNORADAS
❌ Qualquer desconto é aceito
❌ Não há validação
❌ Limite máximo não é verificado
```

### **Após Implementar:**
```
✅ Campos desabilitados se configuração = false
✅ Validação em tempo real
✅ Mensagem de erro clara
✅ Limite máximo respeitado
✅ Impedimento de salvar se inválido
```

---

## 🚀 PRÓXIMOS PASSOS

1. ✅ **Helper criado** - `DiscountHelper.php`
2. ⏳ **Implementar nos componentes Livewire**
3. ⏳ **Atualizar views para usar helper**
4. ⏳ **Adicionar validações no save**
5. ⏳ **Testar com diferentes configurações**

---

## 📊 IMPACTO

**Componentes afetados:**
- 🔴 Faturas de Venda
- 🔴 Proformas de Venda
- 🔴 Faturas de Compra
- 🔴 POS (Ponto de Venda)
- 🔴 Recibos
- 🔴 Notas de Crédito/Débito

**Todos precisam implementar as validações!**

---

**STATUS ATUAL: ⚠️ HELPER CRIADO, FALTA IMPLEMENTAR NOS COMPONENTES**
