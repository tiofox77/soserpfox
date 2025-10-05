<?php

namespace App\Livewire\Invoicing;

use App\Models\Client;
use App\Models\Product;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\SalesProformaItem;
use App\Models\Invoicing\Warehouse;
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
            
        $this->hasProducts = Product::where('tenant_id', activeTenantId())->count() >= 3;
        
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
        
        // Cliente com NIF
        if (!$this->hasClientWithNIF) {
            $clientNIF = Client::create([
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
        }
        
        // Cliente sem NIF
        if (!$this->hasClientWithoutNIF) {
            $clientSemNIF = Client::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Consumidor Final',
                'nif' => '999999999',
                'is_active' => true,
            ]);
        }
        
        // Produtos
        if (!$this->hasProducts) {
            Product::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Produto IVA 14% AGT',
                'sku' => 'AGT-IVA14',
                'price' => 100.00,
                'stock' => 1000,
                'type' => 'product',
                'unit' => 'UN',
                'is_active' => true,
            ]);
            
            Product::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Produto Isento M11 AGT',
                'sku' => 'AGT-ISENTO',
                'price' => 75.00,
                'stock' => 1000,
                'type' => 'product',
                'unit' => 'UN',
                'is_active' => true,
            ]);
            
            Product::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Serviço IRT AGT',
                'sku' => 'AGT-SERV',
                'price' => 200.00,
                'type' => 'service',
                'unit' => 'HRS',
                'is_active' => true,
            ]);
        }
        
        // Armazém
        if (!$this->hasWarehouse) {
            Warehouse::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Armazém AGT',
                'code' => 'AGT-01',
                'is_default' => true,
                'is_active' => true,
            ]);
        }
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
        $product = Product::where('tenant_id', activeTenantId())->first();
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->first();
        
        $this->addLog('  → Criando fatura FT...', 'info');
        $invoice = SalesInvoice::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FT AGT ' . now()->year . '/' . str_pad(rand(1, 999), 6, '0', STR_PAD_LEFT),
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
            'hash' => substr($invoice->hash, 0, 4),
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
        $product = Product::where('tenant_id', activeTenantId())->skip(1)->first();
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->first();
        
        $this->addLog('  → Criando fatura para consumidor final...', 'info');
        $invoice = SalesInvoice::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FT AGT ' . now()->year . '/' . str_pad(rand(1, 999), 6, '0', STR_PAD_LEFT),
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
            'hash' => substr($invoice->hash, 0, 4),
        ];
        
        $this->addLog('  ✅ Documento #2: ' . $invoice->invoice_number, 'success');
    }
    
    private function generateDoc3()
    {
        // 3. Proforma
        $this->addLog('  → Criando fatura proforma...', 'info');
        $client = Client::where('tenant_id', activeTenantId())->whereNotNull('nif')->where('nif', '!=', '999999999')->first();
        $product = Product::where('tenant_id', activeTenantId())->first();
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->first();
        
        $proforma = SalesProforma::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'proforma_number' => 'FP AGT ' . now()->year . '/' . str_pad(rand(1, 999), 6, '0', STR_PAD_LEFT),
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
        ];
        
        $this->addLog('  ✅ Documento #3: ' . $proforma->proforma_number, 'success');
    }
    
    private function generateDoc4()
    {
        // 4. Fatura Simplificada
        $this->addLog('  → Criando fatura simplificada FS...', 'info');
        $client = Client::where('tenant_id', activeTenantId())->where('nif', '999999999')->first();
        $product = Product::where('tenant_id', activeTenantId())->first();
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->first();
        
        $invoice = SalesInvoice::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FS AGT ' . now()->year . '/' . str_pad(rand(1, 999), 6, '0', STR_PAD_LEFT),
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
            'hash' => substr($invoice->hash, 0, 4),
        ];
        
        $this->addLog('  ✅ Documento #4: ' . $invoice->invoice_number, 'success');
    }
    
    private function generateDoc5()
    {
        // 5. Nota de Crédito
        $this->addLog('  → Criando nota de crédito NC...', 'info');
        $client = Client::where('tenant_id', activeTenantId())->whereNotNull('nif')->where('nif', '!=', '999999999')->first();
        $product = Product::where('tenant_id', activeTenantId())->first();
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->first();
        
        $invoice = SalesInvoice::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'NC AGT ' . now()->year . '/' . str_pad(rand(1, 999), 6, '0', STR_PAD_LEFT),
            'invoice_type' => 'NC',
            'invoice_status' => 'N',
            'invoice_date' => now(),
            'due_date' => now(),
            'system_entry_date' => now(),
            'status' => 'paid',
            'subtotal' => -100.00,
            'net_total' => -100.00,
            'tax_amount' => -14.00,
            'tax_payable' => -14.00,
            'total' => -114.00,
            'gross_total' => -114.00,
            'created_by' => auth()->id(),
            'source_id' => auth()->user()->name,
            'source_billing' => 'SOSERP/1.0',
            'hash_control' => '1',
        ]);
        
        SalesInvoiceItem::create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'description' => 'Devolução - ' . ($product->description ?? $product->name),
            'quantity' => -1,
            'unit' => 'UN',
            'unit_price' => 100.00,
            'subtotal' => -100.00,
            'tax_rate' => 14,
            'tax_amount' => -14.00,
            'total' => -114.00,
            'order' => 1,
        ]);
        
        $invoice->generateHash();
        
        $this->generatedDocuments[] = [
            'number' => 5,
            'type' => 'Nota de Crédito (NC)',
            'doc_number' => $invoice->invoice_number,
            'id' => $invoice->id,
            'hash' => substr($invoice->hash, 0, 4),
        ];
        
        $this->addLog('  ✅ Documento #5: ' . $invoice->invoice_number, 'success');
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
        $this->generateGenericInvoice(11, 'FT', 'Fatura serviços', 1, 0, 14, 200, 'service');
        $this->addLog('  ✅ Documento #11 criado', 'success');
    }
    
    private function generateDoc12()
    {
        $this->addLog('  → Criando documento #12...', 'info');
        $this->generateGenericInvoice(12, 'ND', 'Nota de Débito', 1);
        $this->addLog('  ✅ Documento #12 criado', 'success');
    }
    
    private function generateDoc13()
    {
        $this->addLog('  → Criando documento #13...', 'info');
        $this->generateGenericInvoice(13, 'FT', 'Fatura c/ retenção', 1, 0, 14, 100, 'product', 6.5);
        $this->addLog('  ✅ Documento #13 criado', 'success');
    }
    
    private function generateDoc14()
    {
        $this->addLog('  → Criando documento #14...', 'info');
        $this->generateGenericInvoice(14, 'FT', 'Fatura vencida', 1, 0, 14, 100, 'product', 0, 'overdue');
        $this->addLog('  ✅ Documento #14 criado', 'success');
    }
    
    private function generateDoc15()
    {
        $this->addLog('  → Criando documento #15...', 'info');
        $this->generateGenericInvoice(15, 'FT', 'Fatura anulada', 1, 0, 14, 100, 'product', 0, 'cancelled');
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
    
    private function generateGenericInvoice($number, $type, $description, $qty = 1, $discount = 0, $taxRate = 14, $unitPrice = 100, $productType = 'product', $withholdingTax = 0, $status = 'paid')
    {
        $client = Client::where('tenant_id', activeTenantId())->whereNotNull('nif')->where('nif', '!=', '999999999')->first();
        $product = Product::where('tenant_id', activeTenantId())->where('type', $productType)->first();
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->first();
        
        $subtotal = $unitPrice * $qty;
        $discountAmount = ($subtotal * $discount) / 100;
        $netTotal = $subtotal - $discountAmount;
        $taxAmount = ($netTotal * $taxRate) / 100;
        $total = $netTotal + $taxAmount;
        
        $invoice = SalesInvoice::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $client->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => $type . ' AGT ' . now()->year . '/' . str_pad(rand(1, 999), 6, '0', STR_PAD_LEFT),
            'invoice_type' => $type,
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
        
        SalesInvoiceItem::create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'description' => $description,
            'quantity' => $qty,
            'unit' => $productType === 'service' ? 'HRS' : 'UN',
            'unit_price' => $unitPrice,
            'discount_percentage' => $discount,
            'discount_amount' => $discountAmount / $qty,
            'subtotal' => $netTotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'order' => 1,
        ]);
        
        $invoice->generateHash();
        
        $this->generatedDocuments[] = [
            'number' => $number,
            'type' => $description,
            'doc_number' => $invoice->invoice_number,
            'id' => $invoice->id,
            'hash' => substr($invoice->hash, 0, 4),
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
