<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\Category;

echo "\n🧪 TESTE SIMPLES - CRIAR PRODUTO COM LOTES\n";
echo "==========================================\n\n";

try {
    $tenantId = 1;
    
    // 1. Buscar categoria
    $category = Category::where('tenant_id', $tenantId)->first();
    if (!$category) {
        echo "❌ Nenhuma categoria encontrada\n";
        exit(1);
    }
    echo "✅ Categoria encontrada: {$category->name}\n";
    
    // 2. Criar produto
    echo "\n📦 Criando produto com rastreamento de lotes...\n";
    
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
    
    echo "✅ Produto criado com sucesso!\n";
    echo "   ID: {$product->id}\n";
    echo "   Código: {$product->code}\n";
    echo "   Nome: {$product->name}\n";
    echo "   Rastrear Lotes: " . ($product->track_batches ? 'SIM' : 'NÃO') . "\n";
    echo "   Controlar Validade: " . ($product->track_expiry ? 'SIM' : 'NÃO') . "\n";
    
    echo "\n✅ TESTE PASSOU!\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n\n";
    exit(1);
}
