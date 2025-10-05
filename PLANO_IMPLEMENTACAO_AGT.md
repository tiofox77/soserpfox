# 🚀 PLANO DE IMPLEMENTAÇÃO - CONFORMIDADE AGT ANGOLA

## 📋 DOCUMENTOS CRIADOS

1. ✅ **CONFORMIDADE_AGT_ANGOLA.md** - Checklist completo com todos os requisitos
2. ✅ **Migration campos AGT** - Campos de conformidade no banco de dados
3. ⏳ Este documento - Plano de ação

---

## 🎯 PRÓXIMOS PASSOS PARA IMPLEMENTAÇÃO

### **FASE 1: BANCO DE DADOS** ⚠️ PRONTO PARA EXECUTAR

```bash
# Executar migration
php artisan migrate

# Adiciona campos em todas as tabelas de documentos:
# - agt_compliant (boolean)
# - agt_validated_at (timestamp)
# - agt_validated_by (user_id)
# - agt_validation_notes (text)
# - agt_hash_display (4 caracteres)
# - agt_footer_message (text)
# - agt_test_category (categoria 1-17)
```

---

### **FASE 2: HELPER AGT**

**Criar:** `app/Helpers/AGTHelper.php`

```php
<?php

namespace App\Helpers;

class AGTHelper
{
    /**
     * Gera mensagem AGT para rodapé do documento
     */
    public static function generateFooterMessage(string $hash): string
    {
        $hashDisplay = substr($hash, 0, 4);
        return "{$hashDisplay} - Processado por programa válido n31.1/AGT2025";
    }
    
    /**
     * Valida se documento está conforme AGT
     */
    public static function validateDocument($document): array
    {
        $errors = [];
        
        // Validar Hash
        if (empty($document->saft_hash)) {
            $errors[] = "Hash SAFT ausente";
        }
        
        // Validar Period
        if (empty($document->invoice_date)) {
            $errors[] = "Data do documento ausente";
        }
        
        // Validar Cliente
        if (empty($document->client_id)) {
            $errors[] = "Cliente não identificado";
        }
        
        // Validar Totais
        if ($document->total <= 0 && $document->document_type !== 'credit_note') {
            $errors[] = "Total inválido";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Categorias de teste AGT
     */
    public static function getTestCategories(): array
    {
        return [
            '1' => 'Fatura com NIF do cliente',
            '2' => 'Fatura anulada',
            '3' => 'Proforma',
            '4' => 'Fatura baseada em proforma',
            '5' => 'Nota de crédito',
            '6' => 'Fatura com IVA e isento',
            '7' => 'Fatura com descontos',
            '8' => 'Documento em moeda estrangeira',
            '9' => 'Fatura sem NIF (< 50 AOA)',
            '10' => 'Fatura sem NIF (normal)',
            '11' => 'Guia de remessa',
            '12' => 'Orçamento',
            '13' => 'Fatura genérica/Auto-facturação',
            '14' => 'Fatura global',
            '15' => 'Outros documentos',
        ];
    }
    
    /**
     * Códigos de isenção IVA AGT
     */
    public static function getExemptionCodes(): array
    {
        return [
            'M00' => 'Regime Transitório',
            'M02' => 'Transmissão de bens e serviço não sujeita',
            'M04' => 'Iva - Regime de não Sujeição',
            'M11' => 'Isento Artigo 12.º b) do CIVA',
            'M12' => 'Isento Artigo 12.º c) do CIVA',
            'M13' => 'Isento Artigo 12.º d) do CIVA',
            'M14' => 'Isento Artigo 12.º e) do CIVA',
            'M15' => 'Isento Artigo 12.º f) do CIVA',
            'M17' => 'Isento Artigo 12.º h) do CIVA',
            'M18' => 'Isento Artigo 12.º i) do CIVA',
            'M19' => 'Isento Artigo 12.º j) do CIVA',
            'M20' => 'Isento Artigo 12.º k) do CIVA',
            'M30' => 'Isento Artigo 15.º 1 a) do CIVA',
            'M31' => 'Isento Artigo 15.º 1 b) do CIVA',
            'M32' => 'Isento Artigo 15.º 1 c) do CIVA',
            'M33' => 'Isento Artigo 15.º 1 d) do CIVA',
            'M34' => 'Isento Artigo 15.º 1 e) do CIVA',
            'M35' => 'Isento Artigo 15.º 1 f) do CIVA',
            'M36' => 'Isento Artigo 15.º 1 g) do CIVA',
            'M37' => 'Isento Artigo 15.º 1 h) do CIVA',
            'M38' => 'Isento Artigo 15.º 1 i) do CIVA',
        ];
    }
}
```

---

### **FASE 3: MODIFICAR MODELOS**

**Adicionar em:** `app/Models/Invoicing/SalesInvoice.php`

```php
protected $fillable = [
    // ... campos existentes ...
    'agt_compliant',
    'agt_validated_at',
    'agt_validated_by',
    'agt_validation_notes',
    'agt_hash_display',
    'agt_footer_message',
    'agt_test_category',
];

protected $casts = [
    // ... casts existentes ...
    'agt_compliant' => 'boolean',
    'agt_validated_at' => 'datetime',
];

/**
 * Gerar mensagem AGT ao salvar
 */
protected static function booted()
{
    static::saving(function ($invoice) {
        if ($invoice->saft_hash && !$invoice->agt_hash_display) {
            $invoice->agt_hash_display = substr($invoice->saft_hash, 0, 4);
            $invoice->agt_footer_message = AGTHelper::generateFooterMessage($invoice->saft_hash);
        }
    });
}

/**
 * Marcar como conforme AGT
 */
public function markAsAGTCompliant($notes = null)
{
    $this->update([
        'agt_compliant' => true,
        'agt_validated_at' => now(),
        'agt_validated_by' => auth()->id(),
        'agt_validation_notes' => $notes,
    ]);
}
```

**Replicar em:**
- `SalesProforma.php`
- `CreditNote.php`
- `DebitNote.php`
- Outros documentos

---

### **FASE 4: INTERFACE DE CONFORMIDADE**

**Adicionar em views de listagem (ex: invoices/index.blade.php):**

```blade
<td>
    @if($invoice->agt_compliant)
        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full flex items-center">
            <i class="fas fa-check-circle mr-1"></i>
            AGT Conforme
        </span>
    @else
        <button wire:click="validateAGT({{ $invoice->id }})" 
                class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full hover:bg-yellow-200 flex items-center">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Validar AGT
        </button>
    @endif
</td>
```

**Modal de Validação AGT:**

```blade
{{-- Modal Conformidade AGT --}}
@if($showAGTModal)
<div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 rounded-t-2xl">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-certificate mr-2"></i>
                Conformidade AGT Angola
            </h3>
        </div>
        
        <div class="p-6 space-y-4">
            {{-- Preview do Documento --}}
            <div class="border-2 border-gray-200 rounded-lg p-4">
                <h4 class="font-bold mb-2">Preview do Documento</h4>
                <iframe src="{{ route('invoice.pdf', $selectedInvoice) }}" 
                        class="w-full h-96 border rounded"></iframe>
            </div>
            
            {{-- Checklist de Validação --}}
            <div class="bg-blue-50 rounded-lg p-4">
                <h4 class="font-bold mb-3 flex items-center">
                    <i class="fas fa-tasks mr-2 text-blue-600"></i>
                    Checklist de Conformidade
                </h4>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="agt_checks.hash" class="mr-2">
                        <span class="text-sm">Hash SAFT presente e visível (4 caracteres)</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="agt_checks.footer_message" class="mr-2">
                        <span class="text-sm">Mensagem AGT no rodapé</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="agt_checks.period" class="mr-2">
                        <span class="text-sm">Período contabilístico correto</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="agt_checks.totals" class="mr-2">
                        <span class="text-sm">Totais calculados corretamente</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="agt_checks.client" class="mr-2">
                        <span class="text-sm">Cliente identificado corretamente</span>
                    </label>
                </div>
            </div>
            
            {{-- Categoria AGT --}}
            <div>
                <label class="block font-semibold mb-2">Categoria de Teste AGT</label>
                <select wire:model="agt_category" class="w-full rounded-lg border-gray-300">
                    <option value="">Selecione...</option>
                    @foreach(AGTHelper::getTestCategories() as $code => $name)
                        <option value="{{ $code }}">{{ $code }}. {{ $name }}</option>
                    @endforeach
                </select>
            </div>
            
            {{-- Observações --}}
            <div>
                <label class="block font-semibold mb-2">Observações</label>
                <textarea wire:model="agt_notes" rows="3" 
                          class="w-full rounded-lg border-gray-300"
                          placeholder="Notas sobre a conformidade..."></textarea>
            </div>
        </div>
        
        <div class="px-6 pb-6 flex gap-3">
            <button wire:click="closeAGTModal" 
                    class="flex-1 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">
                Cancelar
            </button>
            <button wire:click="markAGTCompliant" 
                    class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                <i class="fas fa-check mr-2"></i>
                Marcar como Conforme
            </button>
        </div>
    </div>
</div>
@endif
```

---

### **FASE 5: RELATÓRIO DE CONFORMIDADE**

**Criar:** `app/Livewire/Invoicing/AGTConformityReport.php`

```php
<?php

namespace App\Livewire\Invoicing;

use Livewire\Component;
use App\Models\Invoicing\SalesInvoice;
use App\Helpers\AGTHelper;

class AGTConformityReport extends Component
{
    public $stats = [];
    public $documentsByCategory = [];
    
    public function mount()
    {
        $this->loadStats();
    }
    
    public function loadStats()
    {
        $this->stats = [
            'total' => SalesInvoice::where('tenant_id', activeTenantId())->count(),
            'compliant' => SalesInvoice::where('tenant_id', activeTenantId())
                            ->where('agt_compliant', true)->count(),
            'pending' => SalesInvoice::where('tenant_id', activeTenantId())
                            ->where('agt_compliant', false)->count(),
        ];
        
        // Documentos por categoria AGT
        $categories = AGTHelper::getTestCategories();
        foreach ($categories as $code => $name) {
            $count = SalesInvoice::where('tenant_id', activeTenantId())
                        ->where('agt_test_category', $code)
                        ->count();
            
            if ($count > 0) {
                $this->documentsByCategory[$code] = [
                    'name' => $name,
                    'count' => $count,
                ];
            }
        }
    }
    
    public function exportForAGT()
    {
        // Gerar PDFs + XML SAFT para submissão
        $this->dispatch('success', message: 'Exportação AGT em andamento...');
    }
    
    public function render()
    {
        return view('livewire.invoicing.agt-conformity-report');
    }
}
```

---

### **FASE 6: MODIFICAR PDFs**

**Em todos os templates de PDF, adicionar no rodapé:**

```blade
@if($invoice->agt_footer_message)
<div style="position: fixed; bottom: 20px; left: 0; right: 0; text-align: center; font-size: 9px; color: #666; border-top: 1px solid #ddd; padding-top: 10px;">
    {{ $invoice->agt_footer_message }}
</div>
@endif
```

---

## 🧪 FASE 7: GERAÇÃO DE DOCUMENTOS DE TESTE

**Criar Seeder:** `database/seeders/AGTTestDocumentsSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Client;
use App\Models\Product;

class AGTTestDocumentsSeeder extends Seeder
{
    public function run()
    {
        // 1. Fatura com NIF
        $this->createInvoiceWithNIF();
        
        // 2. Fatura anulada
        $this->createCancelledInvoice();
        
        // 3-17. Demais documentos...
    }
    
    private function createInvoiceWithNIF()
    {
        $client = Client::where('nif', '!=', null)->first();
        
        $invoice = SalesInvoice::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $client->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 100,
            'tax_amount' => 14,
            'total' => 114,
            'agt_test_category' => '1',
            // ... outros campos
        ]);
    }
}
```

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

- [ ] **Fase 1:** Executar migration
- [ ] **Fase 2:** Criar AGTHelper
- [ ] **Fase 3:** Modificar modelos
- [ ] **Fase 4:** Adicionar interface
- [ ] **Fase 5:** Criar relatório
- [ ] **Fase 6:** Modificar PDFs
- [ ] **Fase 7:** Gerar documentos de teste
- [ ] **Fase 8:** Validar cada documento
- [ ] **Fase 9:** Gerar SAFT XML
- [ ] **Fase 10:** Submeter à AGT

---

## 📊 TEMPO ESTIMADO

```
Fase 1: 5 minutos
Fase 2: 30 minutos
Fase 3: 1 hora
Fase 4: 2 horas
Fase 5: 1 hora
Fase 6: 30 minutos
Fase 7: 2 horas
Fase 8: 3 horas
Fase 9: 2 horas
Fase 10: Aguardar AGT

TOTAL: ~12 horas de desenvolvimento
```

---

## 🎯 PRÓXIMO COMANDO

```bash
# 1. Executar migration
php artisan migrate

# 2. Criar helper
# (seguir código acima)

# 3. Testar sistema
php artisan db:seed --class=AGTTestDocumentsSeeder
```

---

**PLANO COMPLETO PRONTO PARA EXECUÇÃO! 🚀**
