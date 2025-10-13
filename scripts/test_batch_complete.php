<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Client;
use App\Models\Invoicing\Warehouse;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\PurchaseInvoiceItem;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\BatchAllocation;
use Illuminate\Support\Facades\DB;

echo "\n";
echo "🧪 ============================================\n";
echo "   TESTE COMPLETO DO SISTEMA DE LOTES\n";
echo "============================================\n\n";

$tenantId = 1;

try {
    DB::beginTransaction();
    
    // ============================================
    // ETAPA 1: CRIAR PRODUTO
    // ============================================
    echo "📦 ETAPA 1: Criando Produto com Rastreamento de Lotes...\n";
    echo "─────────────────────────────────────────────────────\n";
    
    $category = Category::where('tenant_id', $tenantId)->first();
    
    $product = Product::create([
        'tenant_id' => $tenantId,
        'category_id' => $category->id,
        'type' => 'produto',
        'code' => 'TEST-' . time(),
        'name' => 'Produto Teste Lote',
        'price' => 100.00,
        'cost' => 50.00,
        'unit' => 'UN',
        'manage_stock' => true,
        'stock_quantity' => 0,
        'stock_min' => 10,
        'stock_max' => 1000,
        'track_batches' => true,
        'track_expiry' => true,
        'require_batch_on_purchase' => false,
        'require_batch_on_sale' => false,
        'tax_type' => 'isento',
        'exemption_reason' => 'M99',
        'is_active' => true,
    ]);
    
    echo "✅ Produto criado: {$product->code} - {$product->name}\n";
    echo "   • ID: {$product->id}\n";
    echo "   • Rastrear Lotes: " . ($product->track_batches ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "   • Controlar Validade: " . ($product->track_expiry ? '✅ SIM' : '❌ NÃO') . "\n\n";
    
    // ============================================
    // ETAPA 2: CRIAR COMPRA COM LOTE
    // ============================================
    echo "🛒 ETAPA 2: Criando Compra com Lote...\n";
    echo "─────────────────────────────────────────────────────\n";
    
    $supplier = Supplier::where('tenant_id', $tenantId)->first();
    $warehouse = Warehouse::where('tenant_id', $tenantId)->first();
    
    $invoice = PurchaseInvoice::create([
        'tenant_id' => $tenantId,
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'invoice_number' => 'COMP-TEST-' . time(),
        'invoice_date' => now(),
        'status' => 'paid',
        'subtotal' => 5000.00,
        'tax_amount' => 0,
        'total' => 5000.00,
        'created_by' => 1,
    ]);
    
    $item = PurchaseInvoiceItem::create([
        'purchase_invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit' => $product->unit,
        'quantity' => 100,
        'unit_price' => 50.00,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'subtotal' => 5000.00,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 5000.00,
        'batch_number' => 'LOTE-TEST-001',
        'manufacturing_date' => now()->subDays(10),
        'expiry_date' => now()->addMonths(6),
        'alert_days' => 30,
    ]);
    
    echo "✅ Compra criada: {$invoice->invoice_number}\n";
    echo "   • Quantidade: 100 unidades\n";
    echo "   • Lote: LOTE-TEST-001\n";
    echo "   • Validade: " . now()->addMonths(6)->format('d/m/Y') . "\n";
    echo "⏳ Forçando execução do Observer...\n";
    
    // Forçar Observer manualmente (usar created pois foi criado com status paid)
    $observer = new \App\Observers\PurchaseInvoiceObserver();
    $observer->created($invoice);
    
    echo "✅ Observer executado\n\n";
    
    // ============================================
    // ETAPA 3: VERIFICAR LOTE CRIADO
    // ============================================
    echo "🔍 ETAPA 3: Verificando Lote Criado...\n";
    echo "─────────────────────────────────────────────────────\n";
    
    $batch = ProductBatch::where('tenant_id', $tenantId)
        ->where('product_id', $product->id)
        ->where('batch_number', 'LOTE-TEST-001')
        ->first();
    
    if (!$batch) {
        throw new \Exception('❌ FALHA: Lote não foi criado pelo Observer!');
    }
    
    echo "✅ Lote encontrado na tabela product_batches!\n";
    echo "   • ID: {$batch->id}\n";
    echo "   • Número: {$batch->batch_number}\n";
    echo "   • Quantidade Total: {$batch->quantity}\n";
    echo "   • Quantidade Disponível: {$batch->quantity_available}\n";
    echo "   • Validade: " . $batch->expiry_date->format('d/m/Y') . "\n";
    echo "   • Status: {$batch->status}\n\n";
    
    if ($batch->quantity != 100 || $batch->quantity_available != 100) {
        throw new \Exception('❌ FALHA: Quantidade incorreta no lote!');
    }
    
    // ============================================
    // ETAPA 4: CRIAR VENDA
    // ============================================
    echo "💰 ETAPA 4: Criando Venda (FIFO vai aplicar)...\n";
    echo "─────────────────────────────────────────────────────\n";
    
    $client = Client::where('tenant_id', $tenantId)->first();
    
    $saleInvoice = SalesInvoice::create([
        'tenant_id' => $tenantId,
        'client_id' => $client->id,
        'warehouse_id' => $warehouse->id,
        'invoice_number' => 'VND-TEST-' . time(),
        'invoice_date' => now(),
        'status' => 'paid',
        'subtotal' => 3000.00,
        'tax_amount' => 0,
        'total' => 3000.00,
        'created_by' => 1,
    ]);
    
    $saleItem = SalesInvoiceItem::create([
        'sales_invoice_id' => $saleInvoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit' => $product->unit,
        'quantity' => 30,
        'unit_price' => 100.00,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'subtotal' => 3000.00,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 3000.00,
    ]);
    
    echo "✅ Venda criada: {$saleInvoice->invoice_number}\n";
    echo "   • Quantidade: 30 unidades\n";
    echo "⏳ Forçando execução do Observer FIFO...\n";
    
    // Forçar Observer manualmente (usar created pois foi criado com status paid)
    $salesObserver = new \App\Observers\SalesInvoiceObserver();
    $salesObserver->created($saleInvoice);
    
    echo "✅ Observer FIFO executado\n\n";
    
    // ============================================
    // ETAPA 5: VERIFICAR FIFO
    // ============================================
    echo "🎯 ETAPA 5: Verificando FIFO e Alocação...\n";
    echo "─────────────────────────────────────────────────────\n";
    
    $allocations = BatchAllocation::where('tenant_id', $tenantId)
        ->where('product_id', $product->id)
        ->get();
    
    if ($allocations->isEmpty()) {
        throw new \Exception('❌ FALHA: Nenhuma alocação encontrada!');
    }
    
    echo "✅ Alocações encontradas: {$allocations->count()}\n";
    foreach ($allocations as $alloc) {
        echo "   • Lote: {$alloc->batch_number_snapshot}\n";
        echo "   • Quantidade: {$alloc->quantity_allocated}\n";
        echo "   • Status: {$alloc->status}\n";
    }
    echo "\n";
    
    // Recarregar lote
    $batch = $batch->fresh();
    
    echo "📊 Status Final do Lote:\n";
    echo "   • Quantidade Total: {$batch->quantity}\n";
    echo "   • Disponível ANTES: 100\n";
    echo "   • Disponível AGORA: {$batch->quantity_available}\n";
    echo "   • Vendido: " . (100 - $batch->quantity_available) . "\n\n";
    
    if ($batch->quantity_available != 70) {
        throw new \Exception("❌ FALHA: Esperado 70, atual {$batch->quantity_available}");
    }
    
    // ============================================
    // RESUMO FINAL
    // ============================================
    echo "📋 RESUMO FINAL\n";
    echo "─────────────────────────────────────────────────────\n";
    echo "✅ Criar Produto com Lotes: PASSOU\n";
    echo "✅ Criar Compra com Lote: PASSOU\n";
    echo "✅ Observer Criar Lote: PASSOU\n";
    echo "✅ Criar Venda: PASSOU\n";
    echo "✅ Observer Aplicar FIFO: PASSOU\n";
    echo "✅ Diminuir Disponível: PASSOU (100 → 70)\n";
    echo "\n";
    echo "🎉 SISTEMA DE LOTES FUNCIONANDO PERFEITAMENTE!\n";
    echo "\n";
    
    DB::commit();
    
    echo "✅ TESTE CONCLUÍDO COM SUCESSO!\n\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n\n";
    exit(1);
}
