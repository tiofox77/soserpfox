# Guia de Implementação - Descontos SAFT-AO em Todos os Componentes

## ✅ Status de Implementação

| Componente | Backend | Frontend | Status |
|-----------|---------|----------|--------|
| **Proforma Venda** | ✅ | ✅ | Completo |
| **Proforma Compra** | ⏳ | ⏳ | Aguardando criação |
| **Fatura Venda** | ⏳ | ⏳ | Aguardando criação |
| **Fatura Compra** | ⏳ | ⏳ | Aguardando criação |

## 📋 Checklist de Implementação

Ao criar qualquer novo componente de faturação/proforma, seguir:

### **1. Migration (Banco de Dados)**
✅ **Já Implementado** - Migration `add_saft_discounts_to_proformas_and_invoices` inclui:
- `invoicing_sales_proformas` ✅
- `invoicing_sales_proforma_items` ✅
- `invoicing_sales_invoices` ✅
- `invoicing_sales_invoice_items` ✅
- `invoicing_purchase_proformas` ✅
- `invoicing_purchase_proforma_items` ✅
- `invoicing_purchase_invoices` ✅
- `invoicing_purchase_invoice_items` ✅

**Campos Adicionados:**
```sql
-- Tabelas principais
discount_commercial DECIMAL(15,2) DEFAULT 0
discount_financial  DECIMAL(15,2) DEFAULT 0

-- Tabelas de itens
discount_commercial_percent DECIMAL(5,2) DEFAULT 0
discount_commercial_amount  DECIMAL(15,2) DEFAULT 0
```

---

### **2. Model Eloquent**

**Adicionar aos $fillable:**
```php
protected $fillable = [
    // ... campos existentes
    'discount_amount',        // Desconto legado
    'discount_commercial',    // Desconto Comercial (antes IVA)
    'discount_financial',     // Desconto Financeiro (após IVA)
];
```

**Adicionar aos $casts:**
```php
protected $casts = [
    // ... casts existentes
    'discount_amount' => 'decimal:2',
    'discount_commercial' => 'decimal:2',
    'discount_financial' => 'decimal:2',
];
```

**Exemplo de Modelo Completo:**
```php
// app/Models/Invoicing/PurchaseProforma.php
class PurchaseProforma extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'invoicing_purchase_proformas';

    protected $fillable = [
        'tenant_id',
        'proforma_number',
        'supplier_id',
        'warehouse_id',
        'proforma_date',
        'valid_until',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'discount_commercial',     // SAFT-AO
        'discount_financial',      // SAFT-AO
        'total',
        'currency',
        'exchange_rate',
        'notes',
        'terms',
        'created_by',
    ];

    protected $casts = [
        'proforma_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_commercial' => 'decimal:2',
        'discount_financial' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];
}
```

---

### **3. Componente Livewire**

**Propriedades Públicas:**
```php
public $discount_amount = 0;        // Desconto legado
public $discount_commercial = 0;    // Desconto Comercial (antes IVA)
public $discount_financial = 0;     // Desconto Financeiro (após IVA)
```

**Validação:**
```php
protected $rules = [
    // ... outras regras
    'discount_amount' => 'nullable|numeric|min:0',
    'discount_commercial' => 'nullable|numeric|min:0',
    'discount_financial' => 'nullable|numeric|min:0',
];
```

**Cálculo de Totais SAFT-AO:**
```php
public function render()
{
    // Get cart items
    $cartItems = Cart::session($this->cartInstance)->getContent();
    
    // Calculate totals SAFT-AO 2025
    $subtotal = Cart::session($this->cartInstance)->getSubTotal();
    
    // 1. Aplicar Desconto Comercial (antes do IVA)
    $subtotal_after_commercial = $subtotal - $this->discount_commercial - $this->discount_amount;
    
    // 2. Calcular IVA sobre o subtotal com desconto comercial
    $tax_amount = 0;
    foreach ($cartItems as $item) {
        $itemSubtotal = $item->price * $item->quantity;
        // Desconto proporcional por item
        $itemDiscountRatio = $subtotal > 0 
            ? ($this->discount_commercial + $this->discount_amount) / $subtotal 
            : 0;
        $itemSubtotalAfterDiscount = $itemSubtotal * (1 - $itemDiscountRatio);
        $tax_amount += $itemSubtotalAfterDiscount * ($item->attributes['tax_rate'] / 100);
    }
    
    // 3. Total com IVA
    $total_with_tax = $subtotal_after_commercial + $tax_amount;
    
    // 4. Aplicar Desconto Financeiro (após IVA)
    $total = $total_with_tax - $this->discount_financial;

    return view('...', [
        'subtotal' => $subtotal,
        'tax_amount' => $tax_amount,
        'total' => $total,
    ]);
}
```

**Salvar no Banco:**
```php
public function save($status = 'draft')
{
    $this->validate();
    
    DB::beginTransaction();
    try {
        $document->discount_amount = $this->discount_amount;
        $document->discount_commercial = $this->discount_commercial;
        $document->discount_financial = $this->discount_financial;
        $document->save();
        
        DB::commit();
    } catch (\Exception $e) {
        DB::rollback();
        session()->flash('error', $e->getMessage());
    }
}
```

**Carregar do Banco:**
```php
public function loadDocument($id)
{
    $document = Model::findOrFail($id);
    
    $this->discount_amount = $document->discount_amount;
    $this->discount_commercial = $document->discount_commercial ?? 0;
    $this->discount_financial = $document->discount_financial ?? 0;
}
```

---

### **4. View Blade - Card de Resumo**

**Template Completo:**
```blade
<div class="bg-white rounded-2xl shadow-xl overflow-hidden sticky top-6">
    <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
        <h3 class="text-white font-bold text-lg flex items-center">
            <i class="fas fa-calculator mr-2"></i>
            Resumo
        </h3>
    </div>
    <div class="p-6 space-y-4">
        <!-- Subtotal -->
        <div class="flex justify-between items-center">
            <span class="text-gray-600 text-sm">Subtotal:</span>
            <span class="text-lg font-bold text-gray-900">
                {{ number_format($subtotal, 2) }} Kz
            </span>
        </div>
        
        <!-- Descontos SAFT-AO (antes IVA) -->
        <div class="space-y-2 pb-3 border-b border-gray-200">
            <div>
                <label class="text-gray-600 text-xs block mb-1">
                    <i class="fas fa-percentage mr-1 text-orange-600"></i>
                    Desconto Comercial (antes IVA):
                </label>
                <input type="number" step="0.01" min="0" 
                       wire:model.live="discount_commercial"
                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition text-right text-sm"
                       placeholder="0.00">
            </div>
            <div>
                <label class="text-gray-600 text-xs block mb-1">
                    Desconto (legado):
                </label>
                <input type="number" step="0.01" min="0" 
                       wire:model.live="discount_amount"
                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:border-gray-400 focus:ring-2 focus:ring-gray-200 transition text-right text-sm"
                       placeholder="0.00">
            </div>
        </div>
        
        <!-- Base IVA -->
        <div class="flex justify-between items-center text-sm">
            <span class="text-gray-600">Base IVA:</span>
            <span class="font-semibold text-gray-700">
                {{ number_format($subtotal - $discount_commercial - $discount_amount, 2) }} Kz
            </span>
        </div>
        
        <!-- IVA -->
        <div class="flex justify-between items-center">
            <span class="text-gray-600 text-sm">
                <i class="fas fa-receipt mr-1 text-blue-600"></i>
                IVA (14%):
            </span>
            <span class="text-lg font-bold text-blue-600">
                {{ number_format($tax_amount, 2) }} Kz
            </span>
        </div>
        
        <!-- Desconto Financeiro (após IVA) -->
        <div class="pb-3 border-b border-gray-200">
            <label class="text-gray-600 text-xs block mb-1">
                <i class="fas fa-hand-holding-usd mr-1 text-green-600"></i>
                Desconto Financeiro (após IVA):
            </label>
            <input type="number" step="0.01" min="0" 
                   wire:model.live="discount_financial"
                   class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-2 focus:ring-green-200 transition text-right text-sm"
                   placeholder="0.00">
        </div>
        
        <!-- Total Final -->
        <div class="pt-4 border-t-2 border-green-600">
            <div class="flex justify-between items-center">
                <span class="text-lg font-bold text-gray-700">TOTAL A PAGAR:</span>
                <span class="text-3xl font-bold text-green-600">
                    {{ number_format($total, 2) }} Kz
                </span>
            </div>
        </div>
    </div>
</div>
```

---

## 🎨 Cores Padronizadas

| Tipo | Cor | Classe Tailwind |
|------|-----|-----------------|
| Desconto Comercial | 🟠 Laranja | `orange-600` |
| Desconto Legado | ⚫ Cinza | `gray-400` |
| IVA | 🔵 Azul | `blue-600` |
| Desconto Financeiro | 🟢 Verde | `green-600` |
| Total | 🟢 Verde | `green-600` |

---

## 📊 Ordem de Implementação Recomendada

1. **Fatura de Venda** (prioritário)
   - Componente: `app/Livewire/Invoicing/Sales/Invoices.php`
   - View: `resources/views/livewire/invoicing/sales/invoices.blade.php`
   - Model já tem os campos ✅

2. **Proforma de Compra**
   - Componente: `app/Livewire/Invoicing/Purchase/Proformas.php`
   - View: `resources/views/livewire/invoicing/purchase/proformas.blade.php`
   - Model já tem os campos ✅

3. **Fatura de Compra**
   - Componente: `app/Livewire/Invoicing/Purchase/Invoices.php`
   - View: `resources/views/livewire/invoicing/purchase/invoices.blade.php`
   - Model já tem os campos ✅

---

## ✅ Validações Necessárias

```php
// Validar que descontos não excedem valores
if ($this->discount_commercial > $subtotal) {
    throw new \Exception('Desconto comercial não pode exceder o subtotal');
}

if ($this->discount_financial > $total_with_tax) {
    throw new \Exception('Desconto financeiro não pode exceder o total com IVA');
}

// Validar valores positivos
if ($this->discount_commercial < 0 || $this->discount_financial < 0) {
    throw new \Exception('Descontos não podem ser negativos');
}
```

---

## 📖 Referências

- **Documento Principal**: `DOC/DESCONTOS_SAFT_AO.md`
- **SAFT-AO 2025**: `DOC/SAFT_AO_2025.md`
- **Exemplo Completo**: `app/Livewire/Invoicing/Sales/ProformaCreate.php`
- **View Exemplo**: `resources/views/livewire/invoicing/sales/proforma-create.blade.php`

---

## 🚀 Próximos Passos

1. ✅ Migration executada
2. ✅ Models atualizados (SalesProforma, PurchaseProforma)
3. ✅ Proforma Venda implementada
4. ⏳ Aguardar criação de Fatura Venda
5. ⏳ Aguardar criação de Proforma Compra
6. ⏳ Aguardar criação de Fatura Compra

**Quando criar novos componentes, seguir este guia para garantir conformidade SAFT-AO! 📋✅**
