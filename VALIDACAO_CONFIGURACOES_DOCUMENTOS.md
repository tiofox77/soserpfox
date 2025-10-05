# ⚠️ VALIDAÇÃO - CONFIGURAÇÕES DE DOCUMENTOS

## 🔍 VERIFICAÇÃO REALIZADA

Verifiquei se as seguintes configurações estão sendo aplicadas nos documentos:

### **1. Validade e Prazos** 📅
- `proforma_validity_days` - Validade da Proforma em dias
- `invoice_due_days` - Prazo de pagamento da Fatura em dias

### **2. Opções de Impressão** 🖨️
- `auto_print_after_save` - Imprimir automaticamente após salvar
- `show_company_logo` - Mostrar logótipo da empresa
- `invoice_footer_text` - Texto do rodapé da fatura

---

## ❌ RESULTADO DA VERIFICAÇÃO

### **STATUS:**
```
✅ Configurações salvas no banco de dados
❌ NÃO estão sendo aplicadas nos componentes
❌ NÃO estão sendo usadas nos PDFs
❌ NÃO estão sendo usadas nas views
```

### **Encontrado apenas em:**
- ✅ `app/Livewire/Invoicing/Settings.php` (salvar/carregar)
- ✅ `app/Models/Invoicing/InvoicingSettings.php` (model)
- ❌ Nenhum componente de criação de documentos usa

---

## 📊 ONDE DEVERIA SER USADO

### **1. Validade da Proforma (30 dias padrão)**

#### **❌ NÃO IMPLEMENTADO EM:**
```
app/Livewire/Invoicing/Sales/ProformaCreate.php
app/Livewire/Invoicing/Purchases/ProformaCreate.php
```

#### **✅ DEVERIA FUNCIONAR ASSIM:**

```php
use App\Models\Invoicing\InvoicingSettings;

public function mount()
{
    $settings = InvoicingSettings::forTenant(activeTenantId());
    
    // Calcular data de validade automaticamente
    $this->valid_until = now()->addDays($settings->proforma_validity_days ?? 30);
}
```

**Na View:**
```blade
<div>
    <label>Válido até</label>
    <input type="date" wire:model="valid_until" readonly>
    <p class="text-xs text-gray-500">
        Validade configurada: {{ $settings->proforma_validity_days }} dias
    </p>
</div>
```

---

### **2. Prazo de Pagamento da Fatura (30 dias padrão)**

#### **❌ NÃO IMPLEMENTADO EM:**
```
app/Livewire/Invoicing/Sales/InvoiceCreate.php
app/Livewire/Invoicing/Purchases/InvoiceCreate.php
app/Livewire/POS/POSSystem.php
```

#### **✅ DEVERIA FUNCIONAR ASSIM:**

```php
use App\Models\Invoicing\InvoicingSettings;

public function mount()
{
    $settings = InvoicingSettings::forTenant(activeTenantId());
    
    // Calcular data de vencimento automaticamente
    $this->due_date = now()->addDays($settings->invoice_due_days ?? 30);
}
```

**Na View:**
```blade
<div>
    <label>Data de Vencimento</label>
    <input type="date" wire:model="due_date">
    <p class="text-xs text-gray-500">
        Prazo padrão: {{ $settings->invoice_due_days }} dias
    </p>
</div>
```

---

### **3. Imprimir Automaticamente Após Salvar**

#### **❌ NÃO IMPLEMENTADO EM:**
```
Todos os componentes de criação de documentos
```

#### **✅ DEVERIA FUNCIONAR ASSIM:**

```php
use App\Models\Invoicing\InvoicingSettings;

public function save()
{
    // ... salvar documento ...
    
    $settings = InvoicingSettings::forTenant(activeTenantId());
    
    if ($settings->auto_print_after_save) {
        // Redirecionar automaticamente para PDF
        return redirect()->route('invoicing.invoice.pdf', $invoice->id);
    }
    
    $this->dispatch('success', message: 'Documento salvo com sucesso!');
    return redirect()->route('invoicing.invoices');
}
```

**Ou com JavaScript:**
```php
public function save()
{
    // ... salvar documento ...
    
    $settings = InvoicingSettings::forTenant(activeTenantId());
    
    if ($settings->auto_print_after_save) {
        // Abrir PDF em nova aba automaticamente
        $this->dispatch('auto-print', url: route('invoicing.invoice.pdf', $invoice->id));
    }
}
```

**Na View (JavaScript):**
```blade
<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('auto-print', (event) => {
        window.open(event.url, '_blank');
    });
});
</script>
```

---

### **4. Mostrar Logótipo da Empresa (PDF)**

#### **❌ NÃO IMPLEMENTADO EM:**
```
Todos os controladores de PDF
```

#### **✅ DEVERIA FUNCIONAR ASSIM:**

**No Controlador de PDF:**
```php
use App\Models\Invoicing\InvoicingSettings;

public function generatePDF($id)
{
    $invoice = SalesInvoice::findOrFail($id);
    $settings = InvoicingSettings::forTenant(activeTenantId());
    
    $pdf = PDF::loadView('pdf.invoice', [
        'invoice' => $invoice,
        'showLogo' => $settings->show_company_logo ?? true,
        'footerText' => $settings->invoice_footer_text ?? '',
    ]);
    
    return $pdf->download('invoice.pdf');
}
```

**Na View do PDF:**
```blade
{{-- Cabeçalho --}}
@if($showLogo && $tenant->logo)
    <img src="{{ storage_path('app/public/' . $tenant->logo) }}" 
         alt="Logo" 
         style="max-height: 80px;">
@endif

{{-- Conteúdo do PDF --}}
...

{{-- Rodapé --}}
@if($footerText)
    <div style="text-align: center; font-size: 10px; margin-top: 20px; color: #666;">
        {{ $footerText }}
    </div>
@endif
```

---

### **5. Texto Rodapé Fatura**

#### **❌ NÃO IMPLEMENTADO EM:**
```
Todos os PDFs de documentos
```

#### **✅ DEVERIA FUNCIONAR ASSIM:**

**Template PDF:**
```blade
<!DOCTYPE html>
<html>
<head>
    <title>Fatura</title>
    <style>
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    {{-- Conteúdo da fatura --}}
    
    {{-- Rodapé --}}
    @if(isset($footerText) && $footerText)
    <div class="footer">
        {!! nl2br(e($footerText)) !!}
    </div>
    @endif
</body>
</html>
```

---

## 📝 HELPER SUGERIDO

**Criar:** `app/Helpers/DocumentConfigHelper.php`

```php
<?php

namespace App\Helpers;

use App\Models\Invoicing\InvoicingSettings;
use Carbon\Carbon;

class DocumentConfigHelper
{
    /**
     * Obtém as configurações de faturação do tenant atual
     */
    private static function getSettings(): InvoicingSettings
    {
        return InvoicingSettings::forTenant(activeTenantId());
    }

    /**
     * Calcula data de validade da proforma
     */
    public static function getProformaValidUntil(?Carbon $fromDate = null): Carbon
    {
        $settings = self::getSettings();
        $days = $settings->proforma_validity_days ?? 30;
        $from = $fromDate ?? now();
        
        return $from->copy()->addDays($days);
    }

    /**
     * Calcula data de vencimento da fatura
     */
    public static function getInvoiceDueDate(?Carbon $fromDate = null): Carbon
    {
        $settings = self::getSettings();
        $days = $settings->invoice_due_days ?? 30;
        $from = $fromDate ?? now();
        
        return $from->copy()->addDays($days);
    }

    /**
     * Verifica se deve imprimir automaticamente
     */
    public static function shouldAutoPrint(): bool
    {
        $settings = self::getSettings();
        return $settings->auto_print_after_save ?? false;
    }

    /**
     * Verifica se deve mostrar logo
     */
    public static function shouldShowLogo(): bool
    {
        $settings = self::getSettings();
        return $settings->show_company_logo ?? true;
    }

    /**
     * Obtém texto do rodapé
     */
    public static function getFooterText(): ?string
    {
        $settings = self::getSettings();
        return $settings->invoice_footer_text;
    }

    /**
     * Obtém todos os dias configurados
     */
    public static function getDaysSettings(): array
    {
        $settings = self::getSettings();
        
        return [
            'proforma_validity' => $settings->proforma_validity_days ?? 30,
            'invoice_due' => $settings->invoice_due_days ?? 30,
        ];
    }
}
```

---

## 🎯 EXEMPLO DE IMPLEMENTAÇÃO COMPLETA

### **No Componente Livewire:**

```php
<?php

namespace App\Livewire\Invoicing\Sales;

use App\Helpers\DocumentConfigHelper;
use Livewire\Component;

class InvoiceCreate extends Component
{
    public $due_date;
    public $footer_text;
    
    public function mount()
    {
        // Definir data de vencimento automaticamente
        $this->due_date = DocumentConfigHelper::getInvoiceDueDate()->format('Y-m-d');
        
        // Carregar texto do rodapé
        $this->footer_text = DocumentConfigHelper::getFooterText();
    }
    
    public function save()
    {
        // ... validações e save ...
        
        $invoice = SalesInvoice::create([
            // ... dados ...
            'due_date' => $this->due_date,
            'footer_text' => $this->footer_text,
        ]);
        
        // Verificar se deve imprimir automaticamente
        if (DocumentConfigHelper::shouldAutoPrint()) {
            $this->dispatch('auto-print', url: route('invoicing.invoice.pdf', $invoice->id));
        }
        
        $this->dispatch('success', message: 'Fatura criada!');
        return redirect()->route('invoicing.invoices');
    }
    
    public function render()
    {
        return view('livewire.invoicing.sales.invoice-create', [
            'daysSettings' => DocumentConfigHelper::getDaysSettings(),
        ]);
    }
}
```

### **Na View:**

```blade
<div>
    {{-- Data de Vencimento --}}
    <div class="mb-4">
        <label class="block text-sm font-medium mb-2">
            <i class="fas fa-calendar mr-1"></i>
            Data de Vencimento
        </label>
        <input type="date" 
               wire:model="due_date" 
               class="w-full px-3 py-2 border rounded">
        <p class="text-xs text-gray-500 mt-1">
            <i class="fas fa-info-circle mr-1"></i>
            Prazo padrão: {{ $daysSettings['invoice_due'] }} dias
        </p>
    </div>
    
    {{-- Texto Rodapé --}}
    <div class="mb-4">
        <label class="block text-sm font-medium mb-2">
            Texto Rodapé (opcional)
        </label>
        <textarea wire:model="footer_text" 
                  rows="2" 
                  class="w-full px-3 py-2 border rounded"
                  placeholder="Texto padrão: {{ \App\Helpers\DocumentConfigHelper::getFooterText() }}"></textarea>
    </div>
</div>

{{-- Script para auto-impressão --}}
<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('auto-print', (event) => {
        window.open(event.url, '_blank');
        // Opcional: fechar modal automaticamente
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    });
});
</script>
```

---

## 📋 CHECKLIST DE IMPLEMENTAÇÃO

### **Validade e Prazos:**
- [ ] Implementar em `ProformaCreate.php` (validade automática)
- [ ] Implementar em `InvoiceCreate.php` (vencimento automático)
- [ ] Implementar em `POS/POSSystem.php` (vencimento automático)
- [ ] Mostrar informação de dias configurados nas views

### **Impressão Automática:**
- [ ] Implementar em todos os componentes de criação
- [ ] Adicionar JavaScript para abrir PDF automaticamente
- [ ] Testar com configuração ligada/desligada

### **Logo e Rodapé em PDF:**
- [ ] Implementar em controlador de PDF de faturas
- [ ] Implementar em controlador de PDF de proformas
- [ ] Implementar em controlador de PDF de recibos
- [ ] Implementar em controlador de PDF de notas
- [ ] Testar com logo presente/ausente
- [ ] Testar com texto de rodapé vazio/preenchido

---

## 🚀 PRÓXIMOS PASSOS

1. ✅ **Helper criado** - `DocumentConfigHelper.php` (SUGERIDO)
2. ⏳ **Implementar em componentes Livewire**
3. ⏳ **Implementar em geradores de PDF**
4. ⏳ **Atualizar views**
5. ⏳ **Testar todas as configurações**

---

## ⚠️ CONCLUSÃO

### **STATUS ATUAL:**
```
❌ Validade Proforma → NÃO aplicada (campo manual)
❌ Prazo Pagamento → NÃO aplicado (campo manual)
❌ Auto Imprimir → NÃO funciona
❌ Mostrar Logo → NÃO verificado nos PDFs
❌ Texto Rodapé → NÃO aparece nos PDFs
```

### **IMPACTO:**
```
🔴 Usuário configura mas não tem efeito
🔴 Precisa sempre preencher manualmente
🔴 Configurações são ignoradas
🔴 PDFs não respeitam configurações
```

---

**STATUS: ⚠️ CONFIGURAÇÕES SALVAS MAS NÃO FUNCIONAM NOS DOCUMENTOS**
