<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\PurchaseInvoiceItem;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\BatchAllocation;
use App\Models\Supplier;
use App\Models\Client;
use App\Models\Invoicing\Warehouse;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class TestBatchSystem extends Command
{
    protected $signature = 'batch:test {--tenant_id=1}';
    protected $description = 'Testa sistema completo de lotes e validade';

    private $tenantId;
    private $product;
    private $supplier;
    private $client;
    private $warehouse;

    public function handle()
    {
        $this->tenantId = $this->option('tenant_id');
        
        $this->info('');
        $this->info('🧪 ============================================');
        $this->info('   TESTE COMPLETO DO SISTEMA DE LOTES');
        $this->info('============================================');
        $this->info('');

        try {
            DB::beginTransaction();

            // ETAPA 1: Criar Produto com Rastreamento
            $this->step1_createProduct();
            
            // ETAPA 2: Criar Compra com Lote
            $this->step2_createPurchase();
            
            // ETAPA 3: Verificar Lote Criado
            $this->step3_verifyBatch();
            
            // ETAPA 4: Criar Venda
            $this->step4_createSale();
            
            // ETAPA 5: Verificar FIFO e Alocação
            $this->step5_verifyFIFO();
            
            // ETAPA 6: Resumo Final
            $this->step6_summary();

            DB::commit();
            
            $this->info('');
            $this->info('✅ TESTE CONCLUÍDO COM SUCESSO!');
            $this->info('');
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('');
            $this->error('❌ ERRO NO TESTE: ' . $e->getMessage());
            $this->error('Linha: ' . $e->getLine());
            $this->error('');
            return Command::FAILURE;
        }
    }

    private function step1_createProduct()
    {
        $this->info('📦 ETAPA 1: Criando Produto com Rastreamento de Lotes...');
        $this->info('─────────────────────────────────────────────────────');
        
        // Buscar ou criar categoria
        $category = Category::where('tenant_id', $this->tenantId)->first();
        if (!$category) {
            $category = Category::create([
                'tenant_id' => $this->tenantId,
                'name' => 'Teste',
                'is_active' => true,
            ]);
        }

        $this->product = Product::create([
            'tenant_id' => $this->tenantId,
            'category_id' => $category->id,
            'type' => 'produto',
            'code' => 'TEST-' . time(),
            'name' => 'Produto Teste Lote',
            'description' => 'Produto para teste do sistema de lotes',
            'price' => 100.00,
            'cost' => 50.00,
            'unit' => 'UN',
            'manage_stock' => true,
            'stock_quantity' => 0,
            'stock_min' => 10,
            'stock_max' => 1000,
            'track_batches' => true,           // ✅ RASTREAR LOTES
            'track_expiry' => true,            // ✅ CONTROLAR VALIDADE
            'require_batch_on_purchase' => false, // Não exigir (para teste funcionar)
            'require_batch_on_sale' => false,   // Não exigir (para teste funcionar)
            'tax_type' => 'isento',
            'exemption_reason' => 'M99',
            'is_active' => true,
        ]);

        $this->info("✅ Produto criado: {$this->product->code} - {$this->product->name}");
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID', $this->product->id],
                ['Código', $this->product->code],
                ['Nome', $this->product->name],
                ['Rastrear Lotes', $this->product->track_batches ? '✅ SIM' : '❌ NÃO'],
                ['Controlar Validade', $this->product->track_expiry ? '✅ SIM' : '❌ NÃO'],
                ['Exigir na Compra', $this->product->require_batch_on_purchase ? '✅ SIM' : '❌ NÃO'],
                ['Exigir na Venda', $this->product->require_batch_on_sale ? '✅ SIM' : '❌ NÃO'],
            ]
        );
        $this->info('');
    }

    private function step2_createPurchase()
    {
        $this->info('🛒 ETAPA 2: Criando Compra com Lote...');
        $this->info('─────────────────────────────────────────────────────');

        // Buscar ou criar fornecedor
        $this->supplier = Supplier::where('tenant_id', $this->tenantId)->first();
        if (!$this->supplier) {
            $this->supplier = Supplier::create([
                'tenant_id' => $this->tenantId,
                'name' => 'Fornecedor Teste',
                'nif' => '999999999',
                'is_active' => true,
            ]);
        }

        // Buscar ou criar armazém
        $this->warehouse = Warehouse::where('tenant_id', $this->tenantId)->first();
        if (!$this->warehouse) {
            $this->warehouse = Warehouse::create([
                'tenant_id' => $this->tenantId,
                'name' => 'Armazém Principal',
                'is_active' => true,
            ]);
        }

        // Criar fatura de compra
        $invoice = PurchaseInvoice::create([
            'tenant_id' => $this->tenantId,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'invoice_number' => 'COMP-TEST-' . time(),
            'invoice_date' => now(),
            'status' => 'paid', // ✅ PAID = Observer vai criar lote automaticamente
            'subtotal' => 5000.00,
            'tax_amount' => 0,
            'total' => 5000.00,
            'created_by' => 1,
        ]);

        // Criar item com dados do lote
        $item = PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 100, // Comprar 100 unidades
            'unit_price' => 50.00,
            'subtotal' => 5000.00,
            'tax_amount' => 0,
            'total' => 5000.00,
            'batch_number' => 'LOTE-TEST-001', // ✅ NÚMERO DO LOTE
            'manufacturing_date' => now()->subDays(10),
            'expiry_date' => now()->addMonths(6), // ✅ VALIDADE: +6 meses
            'alert_days' => 30,
        ]);

        $this->info("✅ Compra criada: {$invoice->invoice_number}");
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Fatura', $invoice->invoice_number],
                ['Fornecedor', $this->supplier->name],
                ['Armazém', $this->warehouse->name],
                ['Status', $invoice->status],
                ['Produto', $this->product->name],
                ['Quantidade', $item->quantity],
                ['Lote', $item->batch_number],
                ['Validade', $item->expiry_date->format('d/m/Y')],
            ]
        );
        $this->info('⏳ Aguardando Observer criar lote automaticamente...');
        sleep(1);
        $this->info('');
    }

    private function step3_verifyBatch()
    {
        $this->info('🔍 ETAPA 3: Verificando Lote Criado...');
        $this->info('─────────────────────────────────────────────────────');

        $batch = ProductBatch::where('tenant_id', $this->tenantId)
            ->where('product_id', $this->product->id)
            ->where('batch_number', 'LOTE-TEST-001')
            ->first();

        if (!$batch) {
            throw new \Exception('❌ FALHA: Lote não foi criado pelo Observer!');
        }

        $this->info('✅ Lote encontrado na tabela product_batches!');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID', $batch->id],
                ['Número do Lote', $batch->batch_number],
                ['Produto', $this->product->name],
                ['Armazém', $this->warehouse->name],
                ['Quantidade Total', $batch->quantity],
                ['Quantidade Disponível', $batch->quantity_available],
                ['Data de Fabricação', $batch->manufacturing_date ? $batch->manufacturing_date->format('d/m/Y') : 'N/A'],
                ['Data de Validade', $batch->expiry_date ? $batch->expiry_date->format('d/m/Y') : 'N/A'],
                ['Dias até Expirar', $batch->days_until_expiry ?? 'N/A'],
                ['Status', $batch->status],
            ]
        );
        
        if ($batch->quantity != 100 || $batch->quantity_available != 100) {
            throw new \Exception('❌ FALHA: Quantidade incorreta no lote!');
        }

        $this->info('✅ Quantidade correta: 100 unidades disponíveis');
        $this->info('');
    }

    private function step4_createSale()
    {
        $this->info('💰 ETAPA 4: Criando Venda (FIFO vai aplicar)...');
        $this->info('─────────────────────────────────────────────────────');

        // Buscar ou criar cliente
        $this->client = Client::where('tenant_id', $this->tenantId)->first();
        if (!$this->client) {
            $this->client = Client::create([
                'tenant_id' => $this->tenantId,
                'name' => 'Cliente Teste',
                'nif' => '999999999',
                'is_active' => true,
            ]);
        }

        // Criar fatura de venda
        $invoice = SalesInvoice::create([
            'tenant_id' => $this->tenantId,
            'client_id' => $this->client->id,
            'warehouse_id' => $this->warehouse->id,
            'invoice_number' => 'VND-TEST-' . time(),
            'invoice_date' => now(),
            'status' => 'paid', // ✅ PAID = Observer vai aplicar FIFO automaticamente
            'subtotal' => 3000.00,
            'tax_amount' => 0,
            'total' => 3000.00,
            'created_by' => 1,
        ]);

        // Criar item
        $item = SalesInvoiceItem::create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 30, // Vender 30 unidades
            'unit_price' => 100.00,
            'subtotal' => 3000.00,
            'tax_amount' => 0,
            'total' => 3000.00,
        ]);

        $this->info("✅ Venda criada: {$invoice->invoice_number}");
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Fatura', $invoice->invoice_number],
                ['Cliente', $this->client->name],
                ['Status', $invoice->status],
                ['Produto', $this->product->name],
                ['Quantidade', $item->quantity],
            ]
        );
        $this->info('⏳ Aguardando Observer aplicar FIFO...');
        sleep(1);
        $this->info('');
    }

    private function step5_verifyFIFO()
    {
        $this->info('🎯 ETAPA 5: Verificando FIFO e Alocação...');
        $this->info('─────────────────────────────────────────────────────');

        // Verificar batch_allocations
        $allocations = BatchAllocation::where('tenant_id', $this->tenantId)
            ->where('product_id', $this->product->id)
            ->get();

        if ($allocations->isEmpty()) {
            throw new \Exception('❌ FALHA: Nenhuma alocação encontrada em batch_allocations!');
        }

        $this->info('✅ Alocações encontradas na tabela batch_allocations!');
        $this->info('');

        foreach ($allocations as $index => $alloc) {
            $this->info("Alocação #" . ($index + 1) . ":");
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['ID', $alloc->id],
                    ['Lote', $alloc->batch_number_snapshot],
                    ['Quantidade Alocada', $alloc->quantity_allocated],
                    ['Data Validade (Snapshot)', $alloc->expiry_date_snapshot ? $alloc->expiry_date_snapshot->format('d/m/Y') : 'N/A'],
                    ['Status', $alloc->status],
                ]
            );
        }

        // Verificar quantity_available do lote
        $batch = ProductBatch::where('tenant_id', $this->tenantId)
            ->where('product_id', $this->product->id)
            ->where('batch_number', 'LOTE-TEST-001')
            ->first();

        if (!$batch) {
            throw new \Exception('❌ FALHA: Lote não encontrado!');
        }

        $this->info('');
        $this->info('📊 Status Final do Lote:');
        $this->table(
            ['Campo', 'Antes', 'Depois', 'Esperado'],
            [
                ['Quantidade Total', '100', $batch->quantity, '100'],
                ['Quantidade Disponível', '100', $batch->quantity_available, '70'],
                ['Quantidade Vendida', '0', (100 - $batch->quantity_available), '30'],
            ]
        );

        if ($batch->quantity_available != 70) {
            throw new \Exception("❌ FALHA: Quantidade disponível incorreta! Esperado: 70, Atual: {$batch->quantity_available}");
        }

        $this->info('✅ FIFO aplicado corretamente!');
        $this->info('✅ Quantity_available diminuiu de 100 para 70');
        $this->info('');
    }

    private function step6_summary()
    {
        $this->info('📋 ETAPA 6: Resumo Final');
        $this->info('─────────────────────────────────────────────────────');
        
        $batch = ProductBatch::where('tenant_id', $this->tenantId)
            ->where('product_id', $this->product->id)
            ->first();

        $allocations = BatchAllocation::where('tenant_id', $this->tenantId)
            ->where('product_id', $this->product->id)
            ->count();

        $this->table(
            ['Teste', 'Status', 'Resultado'],
            [
                ['Criar Produto com Lotes', '✅ PASSOU', "Produto: {$this->product->code}"],
                ['Criar Compra com Lote', '✅ PASSOU', 'Fatura criada e paga'],
                ['Observer Criar Lote', '✅ PASSOU', "Lote: {$batch->batch_number}"],
                ['Criar Venda', '✅ PASSOU', 'Venda finalizada'],
                ['Observer Aplicar FIFO', '✅ PASSOU', "{$allocations} alocação(ões)"],
                ['Diminuir Disponível', '✅ PASSOU', "100 → {$batch->quantity_available}"],
            ]
        );

        $this->info('');
        $this->info('🎉 SISTEMA DE LOTES FUNCIONANDO PERFEITAMENTE!');
        $this->info('');
        $this->info('📊 Estatísticas:');
        $this->info("   • Produto: {$this->product->name}");
        $this->info("   • Lote: {$batch->batch_number}");
        $this->info("   • Comprado: 100 unidades");
        $this->info("   • Vendido: 30 unidades");
        $this->info("   • Disponível: {$batch->quantity_available} unidades");
        $this->info("   • Validade: " . $batch->expiry_date->format('d/m/Y'));
        $this->info('');
    }
}
