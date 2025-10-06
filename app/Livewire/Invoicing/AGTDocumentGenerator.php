<?php

namespace App\Livewire\Invoicing;

use App\Models\Client;
use App\Models\Product;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\SalesProformaItem;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\CreditNoteItem;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\DebitNoteItem;
use App\Models\Invoicing\Warehouse;
use App\Models\Invoicing\InvoicingSeries;
use App\Helpers\AGTHelper;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Gerador de Documentos AGT')]
class AGTDocumentGenerator extends Component
{
    public $selectedDocuments = [];
    public $generatedDocuments = [];
    public $isGenerating = false;
    public $currentStep = '';
    public $logs = [];
    public $progressPercentage = 0;
    
    // Recursos existentes
    public $hasClientWithNIF = false;
    public $hasClientWithoutNIF = false;
    public $hasProducts = false;
    public $hasWarehouse = false;
    
    public function mount()
    {
        $this->checkExistingResources();
    }
    
    public function checkExistingResources()
    {
        $this->hasClientWithNIF = Client::where('tenant_id', activeTenantId())
            ->whereNotNull('nif')
            ->where('nif', '!=', '999999999')
            ->exists();
            
        $this->hasClientWithoutNIF = Client::where('tenant_id', activeTenantId())
            ->where(function($q) {
                $q->whereNull('nif')
                  ->orWhere('nif', '999999999');
            })
            ->exists();
            
        // Verificar se tem produtos (pelo menos 1 produto E 1 serviço)
        $hasProduct = Product::where('tenant_id', activeTenantId())->where('type', 'produto')->exists();
        $hasService = Product::where('tenant_id', activeTenantId())->where('type', 'servico')->exists();
        $this->hasProducts = $hasProduct && $hasService;
        
        $this->hasWarehouse = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->exists();
    }
    
    public function toggleAll()
    {
        if (count($this->selectedDocuments) === 17) {
            $this->selectedDocuments = [];
        } else {
            $this->selectedDocuments = range(1, 17);
        }
    }
    
    public function generateDocuments()
    {
        $this->isGenerating = true;
        $this->generatedDocuments = [];
        $this->logs = [];
        $this->progressPercentage = 0;
        
        $this->addLog('🚀 Iniciando geração de documentos AGT...', 'info');
        $this->addLog('📋 ' . count($this->selectedDocuments) . ' documentos selecionados', 'info');
        
        DB::beginTransaction();
        
        try {
            // Criar recursos necessários
            $this->addLog('🔧 Verificando e criando recursos necessários...', 'info');
            $this->createResources();
            $this->addLog('✅ Recursos criados com sucesso', 'success');
            
            $total = count($this->selectedDocuments);
            $current = 0;
            
            // Gerar documentos selecionados
            foreach ($this->selectedDocuments as $docNumber) {
                $current++;
                $this->progressPercentage = ($current / $total) * 100;
                $this->currentStep = "Gerando documento {$current}/{$total}...";
                
                $this->addLog("📄 Iniciando documento #{$docNumber}...", 'info');
                $this->generateDocument($docNumber);
            }
            
            DB::commit();
            
            $this->addLog('💾 Commit realizado - dados salvos no banco de dados', 'success');
            
            // Verificar inserção no banco (faturas, proformas, NC e ND)
            $invoiceIds = collect($this->generatedDocuments)
                ->where('document_category', 'invoice')
                ->pluck('id');
                
            $proformaIds = collect($this->generatedDocuments)
                ->where('is_proforma', true)
                ->pluck('id');
                
            $creditNoteIds = collect($this->generatedDocuments)
                ->where('document_category', 'credit_note')
                ->pluck('id');
                
            $debitNoteIds = collect($this->generatedDocuments)
                ->where('document_category', 'debit_note')
                ->pluck('id');
            
            $invoiceCount = $invoiceIds->count() > 0 
                ? SalesInvoice::where('tenant_id', activeTenantId())->whereIn('id', $invoiceIds)->count() 
                : 0;
                
            $proformaCount = $proformaIds->count() > 0 
                ? SalesProforma::where('tenant_id', activeTenantId())->whereIn('id', $proformaIds)->count() 
                : 0;
                
            $creditNoteCount = $creditNoteIds->count() > 0 
                ? CreditNote::where('tenant_id', activeTenantId())->whereIn('id', $creditNoteIds)->count() 
                : 0;
                
            $debitNoteCount = $debitNoteIds->count() > 0 
                ? DebitNote::where('tenant_id', activeTenantId())->whereIn('id', $debitNoteIds)->count() 
                : 0;
            
            $totalInserted = $invoiceCount + $proformaCount + $creditNoteCount + $debitNoteCount;
            
            $this->addLog('🔍 Verificação BD:', 'info');
            $this->addLog('  → ' . $invoiceCount . ' faturas confirmadas (invoicing_sales_invoices)', 'success');
            $this->addLog('  → ' . $proformaCount . ' proformas confirmadas (invoicing_sales_proformas)', 'success');
            $this->addLog('  → ' . $creditNoteCount . ' notas crédito confirmadas (invoicing_credit_notes)', 'success');
            $this->addLog('  → ' . $debitNoteCount . ' notas débito confirmadas (invoicing_debit_notes)', 'success');
            $this->addLog('  → Total: ' . $totalInserted . ' documentos no banco', 'success');
            
            // Listar IDs dos documentos criados
            $ids = collect($this->generatedDocuments)->pluck('id')->join(', ');
            $this->addLog('🆔 IDs criados: ' . $ids, 'info');
            
            $this->addLog('🎉 Processo concluído com sucesso!', 'success');
            $this->addLog('📊 Total: ' . count($this->generatedDocuments) . ' documentos gerados', 'success');
            
            $this->dispatch('success', message: count($this->generatedDocuments) . ' documentos AGT gerados com sucesso!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->addLog('❌ ERRO: ' . $e->getMessage(), 'error');
            $this->dispatch('error', message: 'Erro: ' . $e->getMessage());
        } finally {
            $this->isGenerating = false;
            $this->currentStep = '';
            $this->progressPercentage = 100;
        }
    }
    
    private function addLog($message, $type = 'info')
    {
        $this->logs[] = [
            'time' => now()->format('H:i:s'),
            'message' => $message,
            'type' => $type
        ];
    }
    
    private function createResources()
    {
        $this->currentStep = 'Criando recursos necessários...';
        $this->addLog('🔍 Verificando recursos existentes...', 'info');
        
        // Cliente com NIF
        if (!$this->hasClientWithNIF) {
            $this->addLog('  → Criando cliente com NIF...', 'info');
            Client::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Cliente Teste AGT',
                'nif' => '123456789',
                'email' => 'cliente.agt@teste.ao',
                'phone' => '923456789',
                'address' => 'Rua Teste AGT, 123',
                'city' => 'Luanda',
                'country' => 'AO',
                'is_active' => true,
            ]);
            $this->addLog('  ✓ Cliente com NIF criado', 'success');
        }
        
        // Cliente sem NIF
        if (!$this->hasClientWithoutNIF) {
            $this->addLog('  → Criando consumidor final...', 'info');
            Client::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Consumidor Final',
                'nif' => '999999999',
                'is_active' => true,
            ]);
            $this->addLog('  ✓ Consumidor final criado', 'success');
        }
        
        // Produtos - sempre verificar e criar se necessário
        $hasProductType = Product::where('tenant_id', activeTenantId())->where('type', 'produto')->exists();
        $hasServiceType = Product::where('tenant_id', activeTenantId())->where('type', 'servico')->exists();
        
        if (!$hasProductType) {
            $this->addLog('  → Criando produtos de teste...', 'info');
            Product::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Produto IVA 14% AGT',
                'sku' => 'AGT-IVA14-' . time(),
                'price' => 100.00,
                'stock_quantity' => 1000,
                'type' => 'produto',
                'unit' => 'UN',
                'is_active' => true,
            ]);
            
            Product::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Produto Isento M11 AGT',
                'sku' => 'AGT-ISENTO-' . time(),
                'price' => 75.00,
                'stock_quantity' => 1000,
                'type' => 'produto',
                'unit' => 'UN',
                'is_active' => true,
            ]);
            $this->addLog('  ✓ Produtos criados', 'success');
        }
        
        if (!$hasServiceType) {
            $this->addLog('  → Criando serviço de teste...', 'info');
            Product::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Serviço IRT AGT',
                'sku' => 'AGT-SERV-' . time(),
                'price' => 200.00,
                'type' => 'servico',
                'unit' => 'HRS',
                'is_active' => true,
            ]);
            $this->addLog('  ✓ Serviço criado', 'success');
        }
        
        // Armazém
        if (!$this->hasWarehouse) {
            $this->addLog('  → Criando armazém...', 'info');
            Warehouse::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Armazém Principal',
                'code' => 'ARM-01',
                'is_default' => true,
                'is_active' => true,
            ]);
            $this->addLog('  ✓ Armazém criado', 'success');
        }
        
        $this->addLog('  → Séries do sistema serão utilizadas', 'info');
    }
    
    private function getSeries($prefix)
    {
        // Buscar série por prefix (FT, FS, FR, etc.) do tenant atual
        $series = InvoicingSeries::where('tenant_id', activeTenantId())
            ->where('prefix', $prefix)
            ->where('is_active', true)
            ->first();
            
        // Se não encontrar série específica, buscar por document_type
        if (!$series) {
            $documentTypeMap = [
                'FT' => 'invoice',
                'FS' => 'invoice',
                'FR' => 'invoice',
                'GT' => 'invoice',
                'NC' => 'credit_note',
                'ND' => 'debit_note',
                'FP' => 'proforma',
            ];
            
            $documentType = $documentTypeMap[$prefix] ?? 'invoice';
            
            // Buscar série padrão para este tipo de documento
            $series = InvoicingSeries::where('tenant_id', activeTenantId())
                ->where('document_type', $documentType)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();
                
            // Se não tiver padrão, pegar a primeira ativa deste tipo
            if (!$series) {
                $series = InvoicingSeries::where('tenant_id', activeTenantId())
                    ->where('document_type', $documentType)
                    ->where('is_active', true)
                    ->first();
            }
        }
        
        return $series;
    }
    
    private function generateDocument($docNumber)
    {
        $method = "generateDoc{$docNumber}";
        if (method_exists($this, $method)) {
            $this->{$method}();
        }
    }
    
    private function generateDoc1()
    {
        // 1. Fatura com NIF
        $this->addLog('  → Buscando cliente com NIF...', 'info');
        $client = Client::where('tenant_id', activeTenantId())
            ->whereNotNull('nif')
            ->where('nif', '!=', '999999999')
            ->first();
            
        if (!$client) {
            throw new \Exception('Cliente com NIF não encontrado');
        }
        
        $this->addLog('  → Buscando produto...', 'info');
        $product = Product::where('tenant_id', activeTenantId())
            ->where('type', 'produto')
            ->where('is_active', true)
            ->first();
            
        if (!$product) {
            throw new \Exception('Produto não encontrado. Certifique-se de ter produtos ativos no sistema.');
        }
        
        $warehouse = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->first();
            
        if (!$warehouse) {
            throw new \Exception('Armazém não encontrado');
        }
        
        $series = $this->getSeries('FT');
        if (!$series) {
            throw new \Exception('Série FT não encontrada');
        }
        
        $this->addLog('  → Criando fatura FT com série...', 'info');
        $invoiceNumber = $series->getNextNumber();
        
        $invoice = SalesInvoice::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'series_id' => $series->id,
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'FT',
            'invoice_status' => 'N',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'system_entry_date' => now(),
            'status' => 'paid',
            'subtotal' => 100.00,
            'net_total' => 100.00,
            'tax_amount' => 14.00,
            'tax_payable' => 14.00,
            'total' => 114.00,
            'gross_total' => 114.00,
            'created_by' => auth()->id(),
            'source_id' => auth()->user()->name,
            'source_billing' => 'SOSERP/1.0',
            'hash_control' => '1',
        ]);
        
        SalesInvoiceItem::create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'description' => $product->description ?? $product->name,
            'quantity' => 1,
            'unit' => 'UN',
            'unit_price' => 100.00,
            'subtotal' => 100.00,
            'tax_rate' => 14,
            'tax_amount' => 14.00,
            'total' => 114.00,
            'order' => 1,
        ]);
        
        $invoice->generateHash();
        
        $this->generatedDocuments[] = [
            'number' => 1,
            'type' => 'Fatura com NIF',
            'doc_number' => $invoice->invoice_number,
            'id' => $invoice->id,
            'hash' => substr($invoice->hash ?? '', 0, 4),
        ];
        
        $this->addLog('  ✅ Documento #1: ' . $invoice->invoice_number, 'success');
    }
    
    private function generateDoc2()
    {
        // 2. Fatura sem NIF (999999999)
        $this->addLog('  → Buscando consumidor final...', 'info');
        $client = Client::where('tenant_id', activeTenantId())
            ->where('nif', '999999999')
            ->first();
            
        if (!$client) {
            throw new \Exception('Consumidor final não encontrado');
        }
        
        $product = Product::where('tenant_id', activeTenantId())->where('type', 'produto')->skip(1)->first();
        if (!$product) {
            $product = Product::where('tenant_id', activeTenantId())->where('type', 'produto')->first();
        }
        
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->where('is_active', true)->first();
        
        if (!$warehouse) {
            throw new \Exception('Armazém não encontrado');
        }
        
        $series = $this->getSeries('FT');
        if (!$series) {
            throw new \Exception('Série FT não encontrada');
        }
        
        $this->addLog('  → Criando fatura para consumidor final...', 'info');
        $invoiceNumber = $series->getNextNumber();
        
        $invoice = SalesInvoice::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'series_id' => $series->id,
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'FT',
            'invoice_status' => 'N',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'system_entry_date' => now(),
            'status' => 'paid',
            'subtotal' => 75.00,
            'net_total' => 75.00,
            'tax_amount' => 10.50,
            'tax_payable' => 10.50,
            'total' => 85.50,
            'gross_total' => 85.50,
            'created_by' => auth()->id(),
            'source_id' => auth()->user()->name,
            'source_billing' => 'SOSERP/1.0',
            'hash_control' => '1',
        ]);
        
        SalesInvoiceItem::create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'description' => $product->description ?? $product->name,
            'quantity' => 1,
            'unit' => 'UN',
            'unit_price' => 75.00,
            'subtotal' => 75.00,
            'tax_rate' => 14,
            'tax_amount' => 10.50,
            'total' => 85.50,
            'order' => 1,
        ]);
        
        $invoice->generateHash();
        
        $this->generatedDocuments[] = [
            'number' => 2,
            'type' => 'Fatura sem NIF (999999999)',
            'doc_number' => $invoice->invoice_number,
            'id' => $invoice->id,
            'hash' => substr($invoice->hash ?? '', 0, 4),
        ];
        
        $this->addLog('  ✅ Documento #2: ' . $invoice->invoice_number, 'success');
    }
    
    private function generateDoc3()
    {
        // 3. Proforma
        $this->addLog('  → Criando fatura proforma...', 'info');
        $client = Client::where('tenant_id', activeTenantId())->whereNotNull('nif')->where('nif', '!=', '999999999')->first();
        
        if (!$client) {
            throw new \Exception('Cliente não encontrado');
        }
        
        $product = Product::where('tenant_id', activeTenantId())->where('type', 'produto')->first();
        if (!$product) {
            throw new \Exception('Produto não encontrado');
        }
        
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->where('is_active', true)->first();
        if (!$warehouse) {
            throw new \Exception('Armazém não encontrado');
        }
        
        $series = $this->getSeries('FP');
        if (!$series) {
            throw new \Exception('Série FP não encontrada');
        }
        
        $proformaNumber = $series->getNextNumber();
        
        $proforma = SalesProforma::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'series_id' => $series->id,
            'proforma_number' => $proformaNumber,
            'proforma_date' => now(),
            'valid_until' => now()->addDays(15),
            'status' => 'sent',
            'subtotal' => 200.00,
            'tax_amount' => 28.00,
            'total' => 228.00,
            'created_by' => auth()->id(),
        ]);
        
        SalesProformaItem::create([
            'sales_proforma_id' => $proforma->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'description' => $product->description ?? $product->name,
            'quantity' => 2,
            'unit_price' => 100.00,
            'subtotal' => 200.00,
            'tax_rate' => 14,
            'tax_amount' => 28.00,
            'total' => 228.00,
        ]);
        
        $this->generatedDocuments[] = [
            'number' => 3,
            'type' => 'Fatura Proforma',
            'doc_number' => $proforma->proforma_number,
            'id' => $proforma->id,
            'hash' => 'N/A',
            'is_proforma' => true,
        ];
        
        $this->addLog('  ✅ Documento #3: ' . $proforma->proforma_number, 'success');
    }
    
    private function generateDoc4()
    {
        // 4. Fatura Simplificada
        $this->addLog('  → Criando fatura simplificada FS...', 'info');
        $client = Client::where('tenant_id', activeTenantId())->where('nif', '999999999')->first();
        if (!$client) {
            throw new \Exception('Consumidor final não encontrado');
        }
        
        $product = Product::where('tenant_id', activeTenantId())->where('type', 'produto')->first();
        if (!$product) {
            throw new \Exception('Produto não encontrado');
        }
        
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->where('is_active', true)->first();
        if (!$warehouse) {
            throw new \Exception('Armazém não encontrado');
        }
        
        $series = $this->getSeries('FS');
        if (!$series) {
            throw new \Exception('Série FS não encontrada');
        }
        
        $invoiceNumber = $series->getNextNumber();
        
        $invoice = SalesInvoice::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'series_id' => $series->id,
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'FS',
            'invoice_status' => 'N',
            'invoice_date' => now(),
            'due_date' => now(),
            'system_entry_date' => now(),
            'status' => 'paid',
            'subtotal' => 50.00,
            'net_total' => 50.00,
            'tax_amount' => 7.00,
            'tax_payable' => 7.00,
            'total' => 57.00,
            'gross_total' => 57.00,
            'created_by' => auth()->id(),
            'source_id' => auth()->user()->name,
            'source_billing' => 'SOSERP/1.0',
            'hash_control' => '1',
        ]);
        
        SalesInvoiceItem::create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'description' => $product->description ?? $product->name,
            'quantity' => 1,
            'unit' => 'UN',
            'unit_price' => 50.00,
            'subtotal' => 50.00,
            'tax_rate' => 14,
            'tax_amount' => 7.00,
            'total' => 57.00,
            'order' => 1,
        ]);
        
        $invoice->generateHash();
        
        $this->generatedDocuments[] = [
            'number' => 4,
            'type' => 'Fatura Simplificada (FS)',
            'doc_number' => $invoice->invoice_number,
            'id' => $invoice->id,
            'hash' => substr($invoice->hash ?? '', 0, 4),
        ];
        
        $this->addLog('  ✅ Documento #4: ' . $invoice->invoice_number, 'success');
    }
    
    private function generateDoc5()
    {
        // 5. Nota de Crédito
        $this->addLog('  → Criando nota de crédito NC...', 'info');
        $client = Client::where('tenant_id', activeTenantId())->whereNotNull('nif')->where('nif', '!=', '999999999')->first();
        if (!$client) {
            throw new \Exception('Cliente não encontrado');
        }
        
        $product = Product::where('tenant_id', activeTenantId())->where('type', 'produto')->first();
        if (!$product) {
            throw new \Exception('Produto não encontrado');
        }
        
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->where('is_active', true)->first();
        if (!$warehouse) {
            throw new \Exception('Armazém não encontrado');
        }
        
        // Buscar fatura original para referenciar (Documento #1)
        $originalInvoice = collect($this->generatedDocuments)->where('number', 1)->first();
        $invoiceId = $originalInvoice ? $originalInvoice['id'] : null;
        
        if ($invoiceId) {
            $this->addLog('  → Referenciando fatura ID: ' . $invoiceId, 'info');
        }
        
        $series = $this->getSeries('NC');
        if (!$series) {
            throw new \Exception('Série NC não encontrada');
        }
        
        $creditNoteNumber = $series->getNextNumber();
        
        // Criar Nota de Crédito na tabela correta
        $creditNote = CreditNote::create([
            'tenant_id' => activeTenantId(),
            'credit_note_number' => $creditNoteNumber,
            'invoice_id' => $invoiceId, // Referência à fatura original
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'issue_date' => now(),
            'reason' => 'return',
            'type' => 'total',
            'status' => 'issued',
            'subtotal' => 100.00,
            'tax_amount' => 14.00,
            'total' => 114.00,
            'created_by' => auth()->id(),
        ]);
        
        // Criar item da NC
        CreditNoteItem::create([
            'credit_note_id' => $creditNote->id,
            'product_id' => $product->id,
            'description' => 'Devolução - ' . $product->name,
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
            'tax_rate' => 14,
            'tax_amount' => 14.00,
            'total' => 114.00,
        ]);
        
        $this->generatedDocuments[] = [
            'number' => 5,
            'type' => 'Nota de Crédito (NC)',
            'doc_number' => $creditNote->credit_note_number,
            'id' => $creditNote->id,
            'hash' => 'N/A',
            'document_category' => 'credit_note',
        ];
        
        $this->addLog('  ✅ Documento #5: ' . $creditNote->credit_note_number, 'success');
    }
    
    // Documentos 6-17: Simplificados para exemplo
    private function generateDoc6()
    {
        $this->addLog('  → Criando documento #6...', 'info');
        $this->generateGenericInvoice(6, 'FT', 'Fatura c/ múltiplos produtos', 3);
        $this->addLog('  ✅ Documento #6 criado', 'success');
    }
    
    private function generateDoc7()
    {
        $this->addLog('  → Criando documento #7...', 'info');
        $this->generateGenericInvoice(7, 'FT', 'Fatura c/ desconto', 1, 10);
        $this->addLog('  ✅ Documento #7 criado', 'success');
    }
    
    private function generateDoc8()
    {
        $this->addLog('  → Criando documento #8...', 'info');
        $this->generateGenericInvoice(8, 'FT', 'Fatura isenta IVA', 1, 0, 0);
        $this->addLog('  ✅ Documento #8 criado', 'success');
    }
    
    private function generateDoc9()
    {
        $this->addLog('  → Criando documento #9...', 'info');
        $this->generateGenericInvoice(9, 'FR', 'Fatura-Recibo', 1);
        $this->addLog('  ✅ Documento #9 criado', 'success');
    }
    
    private function generateDoc10()
    {
        $this->addLog('  → Criando documento #10...', 'info');
        $this->generateGenericInvoice(10, 'FT', 'Fatura grande valor', 1, 0, 14, 5000);
        $this->addLog('  ✅ Documento #10 criado', 'success');
    }
    
    private function generateDoc11()
    {
        $this->addLog('  → Criando documento #11...', 'info');
        $this->generateGenericInvoice(11, 'FT', 'Fatura serviços', 1, 0, 14, 200, 'servico');
        $this->addLog('  ✅ Documento #11 criado', 'success');
    }
    
    private function generateDoc12()
    {
        // 12. Nota de Débito
        $this->addLog('  → Criando nota de débito ND...', 'info');
        $client = Client::where('tenant_id', activeTenantId())->whereNotNull('nif')->where('nif', '!=', '999999999')->first();
        if (!$client) {
            throw new \Exception('Cliente não encontrado');
        }
        
        $product = Product::where('tenant_id', activeTenantId())->where('type', 'produto')->first();
        if (!$product) {
            throw new \Exception('Produto não encontrado');
        }
        
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->where('is_active', true)->first();
        if (!$warehouse) {
            throw new \Exception('Armazém não encontrado');
        }
        
        // Buscar fatura original para referenciar (Documento #1)
        $originalInvoice = collect($this->generatedDocuments)->where('number', 1)->first();
        $invoiceId = $originalInvoice ? $originalInvoice['id'] : null;
        
        if ($invoiceId) {
            $this->addLog('  → Referenciando fatura ID: ' . $invoiceId, 'info');
        }
        
        $series = $this->getSeries('ND');
        if (!$series) {
            throw new \Exception('Série ND não encontrada');
        }
        
        $debitNoteNumber = $series->getNextNumber();
        
        // Criar Nota de Débito na tabela correta
        $debitNote = DebitNote::create([
            'tenant_id' => activeTenantId(),
            'debit_note_number' => $debitNoteNumber,
            'invoice_id' => $invoiceId, // Referência à fatura original
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'issue_date' => now(),
            'reason' => 'additional_charges',
            'type' => 'partial',
            'status' => 'issued',
            'subtotal' => 50.00,
            'tax_amount' => 7.00,
            'total' => 57.00,
            'created_by' => auth()->id(),
        ]);
        
        // Criar item da ND
        DebitNoteItem::create([
            'debit_note_id' => $debitNote->id,
            'product_id' => $product->id,
            'description' => 'Taxa adicional - ' . $product->name,
            'quantity' => 1,
            'unit_price' => 50.00,
            'subtotal' => 50.00,
            'tax_rate' => 14,
            'tax_amount' => 7.00,
            'total' => 57.00,
        ]);
        
        $this->generatedDocuments[] = [
            'number' => 12,
            'type' => 'Nota de Débito (ND)',
            'doc_number' => $debitNote->debit_note_number,
            'id' => $debitNote->id,
            'hash' => 'N/A',
            'document_category' => 'debit_note',
        ];
        
        $this->addLog('  ✅ Documento #12: ' . $debitNote->debit_note_number, 'success');
    }
    
    private function generateDoc13()
    {
        $this->addLog('  → Criando documento #13...', 'info');
        $this->generateGenericInvoice(13, 'FT', 'Fatura c/ retenção', 1, 0, 14, 100, 'produto', 6.5);
        $this->addLog('  ✅ Documento #13 criado', 'success');
    }
    
    private function generateDoc14()
    {
        $this->addLog('  → Criando documento #14...', 'info');
        $this->generateGenericInvoice(14, 'FT', 'Fatura vencida', 1, 0, 14, 100, 'produto', 0, 'overdue');
        $this->addLog('  ✅ Documento #14 criado', 'success');
    }
    
    private function generateDoc15()
    {
        $this->addLog('  → Criando documento #15...', 'info');
        $this->generateGenericInvoice(15, 'FT', 'Fatura anulada', 1, 0, 14, 100, 'produto', 0, 'cancelled');
        $this->addLog('  ✅ Documento #15 criado', 'success');
    }
    
    private function generateDoc16()
    {
        $this->addLog('  → Criando documento #16...', 'info');
        $this->generateGenericInvoice(16, 'GT', 'Guia de Transporte', 2);
        $this->addLog('  ✅ Documento #16 criado', 'success');
    }
    
    private function generateDoc17()
    {
        $this->addLog('  → Criando documento #17...', 'info');
        $this->generateGenericInvoice(17, 'FT', 'Fatura exportação', 1, 0, 0);
        $this->addLog('  ✅ Documento #17 criado', 'success');
    }
    
    private function generateGenericInvoice($number, $prefix, $description, $qty = 1, $discount = 0, $taxRate = 14, $unitPrice = 100, $productType = 'produto', $withholdingTax = 0, $status = 'paid')
    {
        // Validar recursos
        $client = Client::where('tenant_id', activeTenantId())
            ->whereNotNull('nif')
            ->where('nif', '!=', '999999999')
            ->first();
            
        if (!$client) {
            throw new \Exception('Cliente não encontrado');
        }
        
        $product = Product::where('tenant_id', activeTenantId())
            ->where('type', $productType)
            ->first();
            
        if (!$product) {
            throw new \Exception('Produto tipo ' . $productType . ' não encontrado');
        }
        
        $warehouse = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->first();
            
        if (!$warehouse) {
            throw new \Exception('Armazém não encontrado');
        }
        
        // Buscar série do sistema pelo prefix
        $series = $this->getSeries($prefix);
        if (!$series) {
            throw new \Exception('Série ' . $prefix . ' não encontrada');
        }
        
        // Calcular valores
        $subtotal = $unitPrice * $qty;
        $discountAmount = ($subtotal * $discount) / 100;
        $netTotal = $subtotal - $discountAmount;
        $taxAmount = ($netTotal * $taxRate) / 100;
        $total = $netTotal + $taxAmount;
        
        // Obter número da série
        $invoiceNumber = $series->getNextNumber();
        
        // Criar fatura
        $invoice = SalesInvoice::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'series_id' => $series->id,
            'invoice_number' => $invoiceNumber,
            'invoice_type' => $prefix,
            'invoice_status' => $status === 'cancelled' ? 'A' : 'N',
            'invoice_date' => $status === 'overdue' ? now()->subDays(60) : now(),
            'due_date' => $status === 'overdue' ? now()->subDays(30) : now()->addDays(30),
            'system_entry_date' => now(),
            'status' => $status,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'net_total' => $netTotal,
            'tax_amount' => $taxAmount,
            'tax_payable' => $taxAmount,
            'withholding_tax' => $withholdingTax,
            'total' => $total,
            'gross_total' => $total,
            'created_by' => auth()->id(),
            'source_id' => auth()->user()->name,
            'source_billing' => 'SOSERP/1.0',
            'hash_control' => '1',
        ]);
        
        // Criar item
        SalesInvoiceItem::create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'description' => $description,
            'quantity' => $qty,
            'unit' => $productType === 'service' ? 'HRS' : 'UN',
            'unit_price' => $unitPrice,
            'discount_percentage' => $discount,
            'discount_amount' => $qty > 0 ? ($discountAmount / $qty) : 0,
            'subtotal' => $netTotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'order' => 1,
        ]);
        
        // Gerar hash
        $invoice->generateHash();
        
        // Determinar categoria do documento para rota correta
        $documentCategory = match($prefix) {
            'NC' => 'credit_note',
            'ND' => 'debit_note',
            default => 'invoice'
        };
        
        // Adicionar aos gerados
        $this->generatedDocuments[] = [
            'number' => $number,
            'type' => $description,
            'doc_number' => $invoice->invoice_number,
            'id' => $invoice->id,
            'hash' => substr($invoice->hash ?? '', 0, 4),
            'document_category' => $documentCategory,
        ];
    }
    
    public function viewDocument($id)
    {
        return redirect()->route('invoicing.invoices.show', $id);
    }
    
    public function render()
    {
        $documentTypes = AGTHelper::getTestCategories();
        
        return view('livewire.invoicing.a-g-t-document-generator', [
            'documentTypes' => $documentTypes,
        ]);
    }
}
