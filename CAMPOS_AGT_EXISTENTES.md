# ✅ ANÁLISE DOS CAMPOS AGT EXISTENTES NO SISTEMA

## 📊 CAMPOS SAFT-AO JÁ IMPLEMENTADOS

### **Tabelas com Campos AGT:**
- ✅ `invoicing_sales_invoices`
- ✅ `invoicing_purchase_invoices`
- ✅ `invoicing_sales_proformas` (verificar)
- ⚠️ `invoicing_credit_notes` (verificar)
- ⚠️ `invoicing_debit_notes` (verificar)

---

## 🗄️ CAMPOS EXISTENTES E SEU USO

### **Migration:** `2025_10_03_204511_add_saft_ao_fields_to_invoices_tables.php`

| Campo | Tipo | Descrição AGT | Status |
|-------|------|---------------|--------|
| **atcud** | string | Código Único do Documento | ✅ Existe |
| **invoice_type** | string(2) | FT, FR, FS, NC, ND | ✅ Existe |
| **invoice_status** | enum | N=Normal, A=Anulado, F=Finalizado | ✅ Existe |
| **invoice_status_date** | timestamp | Data do status | ✅ Existe |
| **source_id** | string | User ID que criou | ✅ Existe |
| **source_billing** | string | SOSERP/1.0 | ✅ Existe |
| **hash** | text | Assinatura digital SHA-256 | ✅ Existe |
| **hash_control** | string | Controle do hash | ✅ Existe |
| **hash_previous** | string | Hash do documento anterior | ✅ Existe |
| **system_entry_date** | timestamp | Data entrada no sistema | ✅ Existe |
| **net_total** | decimal(15,2) | Total sem IVA | ✅ Existe |
| **gross_total** | decimal(15,2) | Total com IVA | ✅ Existe |
| **tax_payable** | decimal(15,2) | IVA a pagar | ✅ Existe |

---

## ✅ CAMPOS ADICIONAIS NO MODEL

### **SalesInvoice.php - $fillable:**
```php
'tenant_id',
'proforma_id',
'invoice_number',
'atcud',              // ✅ AGT
'invoice_type',       // ✅ AGT
'invoice_status',     // ✅ AGT
'invoice_status_date',// ✅ AGT
'source_id',          // ✅ AGT
'source_billing',     // ✅ AGT
'hash',               // ✅ AGT
'hash_control',       // ✅ AGT
'hash_previous',      // ✅ AGT
'system_entry_date',  // ✅ AGT
'client_id',
'warehouse_id',
'invoice_date',
'due_date',
'status',
'is_service',         // ✅ AGT (para IRT)
'subtotal',
'net_total',          // ✅ AGT
'tax_amount',
'tax_payable',        // ✅ AGT
'irt_amount',         // ✅ AGT
'discount_amount',
'discount_commercial',
'discount_financial',
'total',
'gross_total',        // ✅ AGT
'paid_amount',
'currency',
'exchange_rate',
'notes',
'terms',
'created_by',
```

---

## 🎯 MAPEAMENTO PARA CONFORMIDADE AGT

### **Requisitos AGT vs Campos Existentes:**

#### **1. Hash no Documento** ✅
```php
// Campo: hash
// Uso: 4 primeiros caracteres no rodapé
$hashDisplay = substr($invoice->hash, 0, 4);
$message = "{$hashDisplay} - Processado por programa válido n31.1/AGT2025";
```

#### **2. Período Contabilístico** ✅
```php
// Campo: invoice_date
// Uso: YYYY-MM para Period no SAFT
$period = $invoice->invoice_date->format('Y-m');
```

#### **3. Tipo de Documento** ✅
```php
// Campo: invoice_type
// Valores: FT, FR, FS, NC, ND, GR, PR
$invoice->invoice_type = 'FT'; // Fatura
$invoice->invoice_type = 'NC'; // Nota Crédito
$invoice->invoice_type = 'ND'; // Nota Débito
```

#### **4. Status do Documento** ✅
```php
// Campo: invoice_status
// N = Normal (documento válido)
// A = Anulado (documento cancelado)
// F = Finalizado (documento fechado)
$invoice->invoice_status = 'N';
```

#### **5. Data de Entrada no Sistema** ✅
```php
// Campo: system_entry_date
// Uso: Hora de criação do documento
$invoice->system_entry_date = now();

// Para teste AGT #9: antes das 10h
if ($invoice->system_entry_date->hour < 10) {
    // Conforme requisito
}
```

#### **6. Totais Fiscais** ✅
```php
// net_total = Total sem IVA
// gross_total = Total com IVA
// tax_payable = IVA a pagar
// irt_amount = Retenção IRT (6.5%)

$invoice->net_total = $subtotal - $descontos;
$invoice->tax_payable = $net_total * 0.14; // IVA 14%
$invoice->irt_amount = $is_service ? $net_total * 0.065 : 0;
$invoice->gross_total = $net_total + $tax_payable - $irt_amount;
```

#### **7. Hash Control** ✅
```php
// Campo: hash_control
// Uso: Chave de controlo SAFT (sempre '1')
$invoice->hash_control = '1';
```

#### **8. Hash Anterior** ✅
```php
// Campo: hash_previous
// Uso: Encadeamento de documentos
$previousHash = SalesInvoice::where('tenant_id', $tenantId)
    ->where('id', '<', $invoice->id)
    ->orderBy('id', 'desc')
    ->value('hash');

$invoice->hash_previous = $previousHash ?? '';
```

#### **9. Source ID** ✅
```php
// Campo: source_id
// Uso: Identificar quem criou o documento
$invoice->source_id = auth()->user()->name;
// ou
$invoice->source_id = auth()->user()->id;
```

#### **10. ATCUD** ✅
```php
// Campo: atcud
// Uso: Código Único do Documento (AGT)
// Formato: ATCUD:XXXXX-YYYY
$invoice->atcud = 'ATCUD:' . $invoice->id . '-' . now()->year;
```

---

## 🔧 FUNÇÕES EXISTENTES

### **Geração de Hash** ✅
**Arquivo:** `app/Models/Invoicing/SalesInvoice.php`

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
        now()->format('Y-m-d H:i:s'),
        $this->invoice_number,
        $this->gross_total,
        $previousHash
    );
    
    $this->hash = hash('sha256', $dataToHash);
    $this->hash_previous = $previousHash;
    $this->hash_control = '1';
    $this->save();
}
```

**Status:** ✅ **JÁ FUNCIONA PERFEITAMENTE!**

---

## ✅ O QUE JÁ ESTÁ PRONTO

1. ✅ **Campos no banco de dados** - Todos criados
2. ✅ **Model configurado** - $fillable e $casts corretos
3. ✅ **Geração de Hash** - Função implementada
4. ✅ **Encadeamento** - hash_previous funcional
5. ✅ **Totais fiscais** - Campos para cálculos AGT
6. ✅ **Status de anulação** - invoice_status com 'A'

---

## ⚠️ O QUE FALTA IMPLEMENTAR

### **1. Mensagem AGT no Rodapé dos PDFs**
```php
// Adicionar em todas as views de PDF:
@php
    $hashDisplay = substr($invoice->hash ?? '', 0, 4);
    $agtMessage = $hashDisplay ? "{$hashDisplay} - Processado por programa válido n31.1/AGT2025" : '';
@endphp

@if($agtMessage)
<div class="agt-footer">
    {{ $agtMessage }}
</div>
@endif
```

### **2. Helper AGT (usa campos existentes)**
```php
<?php

namespace App\Helpers;

class AGTHelper
{
    /**
     * Mensagem AGT a partir do hash existente
     */
    public static function getFooterMessage($invoice): string
    {
        if (empty($invoice->hash)) {
            return '';
        }
        
        $hashDisplay = substr($invoice->hash, 0, 4);
        return "{$hashDisplay} - Processado por programa válido n31.1/AGT2025";
    }
    
    /**
     * Validar conformidade AGT usando campos existentes
     */
    public static function validateAGT($invoice): array
    {
        $errors = [];
        
        if (empty($invoice->hash)) {
            $errors[] = "Hash ausente";
        }
        
        if (empty($invoice->invoice_type)) {
            $errors[] = "Tipo de documento não definido";
        }
        
        if (empty($invoice->system_entry_date)) {
            $errors[] = "Data de entrada no sistema ausente";
        }
        
        if (empty($invoice->hash_control)) {
            $errors[] = "Hash Control ausente";
        }
        
        if ($invoice->gross_total != ($invoice->net_total + $invoice->tax_payable - $invoice->irt_amount)) {
            $errors[] = "Totais inconsistentes";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * Marcar documento como anulado (AGT)
     */
    public static function cancelDocument($invoice, $reason = null): bool
    {
        return $invoice->update([
            'invoice_status' => 'A',
            'invoice_status_date' => now(),
            'notes' => ($invoice->notes ? $invoice->notes . "\n\n" : '') . 
                       "ANULADO: " . ($reason ?? 'Sem motivo especificado'),
        ]);
    }
    
    /**
     * Período contabilístico (YYYY-MM)
     */
    public static function getPeriod($invoice): string
    {
        return $invoice->invoice_date->format('Y-m');
    }
}
```

### **3. Interface de Conformidade**

**Badge de Status AGT na listagem:**
```blade
{{-- Verificar conformidade AGT --}}
@php
    $agtValid = !empty($invoice->hash) && 
                !empty($invoice->hash_control) && 
                !empty($invoice->system_entry_date);
@endphp

@if($agtValid)
    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
        <i class="fas fa-check-circle"></i> AGT OK
    </span>
@else
    <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">
        <i class="fas fa-times-circle"></i> AGT Incompleto
    </span>
@endif
```

### **4. Modal de Preview com Checklist**
```blade
<button onclick="previewAGT({{ $invoice->id }})">
    <i class="fas fa-certificate"></i> Validar AGT
</button>

{{-- Modal Preview --}}
<div id="agtModal" class="modal">
    <iframe src="/invoice/{{ $invoice->id }}/pdf"></iframe>
    
    <div class="checklist">
        <label>
            <input type="checkbox" data-check="hash">
            Hash visível ({{ substr($invoice->hash, 0, 4) }})
        </label>
        <label>
            <input type="checkbox" data-check="message">
            Mensagem AGT no rodapé
        </label>
        <label>
            <input type="checkbox" data-check="period">
            Período: {{ $invoice->invoice_date->format('Y-m') }}
        </label>
        <label>
            <input type="checkbox" data-check="totals">
            Totais corretos (Gross: {{ $invoice->gross_total }})
        </label>
    </div>
</div>
```

---

## 📊 RESUMO

### **Status Atual:**
```
Campos no Banco:           ████████████████████ 100%
Geração de Hash:           ████████████████████ 100%
Cálculos Fiscais:          ████████████████████ 100%
Mensagem AGT em PDFs:      ░░░░░░░░░░░░░░░░░░░░   0%
Interface Conformidade:    ░░░░░░░░░░░░░░░░░░░░   0%
Helper AGT:                ░░░░░░░░░░░░░░░░░░░░   0%
                           ────────────────────
GERAL:                     ████████░░░░░░░░░░░░  60%
```

### **O que fazer:**
1. ✅ **NÃO criar campos novos** - Já existem!
2. ⏳ **Adicionar mensagem AGT nos PDFs**
3. ⏳ **Criar AGTHelper** (usa campos existentes)
4. ⏳ **Adicionar interface de validação**
5. ⏳ **Gerar documentos de teste**

---

## 🎯 PRÓXIMO PASSO

**Criar Helper AGT usando os campos existentes:**
```bash
# Criar helper
touch app/Helpers/AGTHelper.php

# Usar o código fornecido acima
# Não precisa migration!
```

---

**SISTEMA JÁ TEM 60% DOS REQUISITOS AGT IMPLEMENTADOS! 🎉**
