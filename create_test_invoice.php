<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\Tax;
use Illuminate\Support\Facades\DB;

// Obter tenant ativo (usar o primeiro tenant disponível)
$tenantId = DB::table('tenants')->value('id');
if (!$tenantId) {
    die("Nenhum tenant encontrado!\n");
}

echo "Tenant ID: $tenantId\n";

// Obter ou criar cliente
$client = Client::where('tenant_id', $tenantId)->first();
if (!$client) {
    $client = Client::create([
        'tenant_id' => $tenantId,
        'name' => 'Cliente Teste Fatura Grande',
        'nif' => '999999999',
        'email' => 'teste@teste.com',
        'phone' => '900000000',
        'address' => 'Rua de Teste, 123',
        'city' => 'Luanda',
        'country' => 'Angola',
        'is_active' => true,
    ]);
}
echo "Cliente: {$client->name} (ID: {$client->id})\n";

// Obter produtos existentes
$products = Product::where('tenant_id', $tenantId)->where('is_active', true)->get();

// Se não houver produtos suficientes, duplicar os existentes na lista
$productList = [];
$targetCount = 100;

if ($products->count() > 0) {
    while (count($productList) < $targetCount) {
        foreach ($products as $p) {
            $productList[] = $p;
            if (count($productList) >= $targetCount) break;
        }
    }
} else {
    die("Nenhum produto encontrado! Crie produtos primeiro.\n");
}

echo "Produtos para fatura: " . count($productList) . " (base: {$products->count()})\n";

// Obter série de fatura
$series = InvoicingSeries::where('tenant_id', $tenantId)
    ->where('is_active', true)
    ->first();

if (!$series) {
    die("Nenhuma série de fatura encontrada! Crie uma série primeiro.\n");
}
echo "Série: {$series->prefix} (ID: {$series->id})\n";

// Obter armazém
$warehouseId = DB::table('invoicing_warehouses')->where('tenant_id', $tenantId)->value('id');

// Obter taxa de imposto
$tax = Tax::where('tenant_id', $tenantId)->first();
$taxRate = $tax ? $tax->rate : 14;

// Criar fatura (usando DB::table para evitar eventos AGT que geram hashes longos)
DB::beginTransaction();
try {
    $invoiceNumber = SalesInvoice::generateInvoiceNumber($tenantId);
    
    $invoiceId = DB::table('invoicing_sales_invoices')->insertGetId([
        'tenant_id' => $tenantId,
        'client_id' => $client->id,
        'series_id' => $series->id,
        'warehouse_id' => $warehouseId,
        'invoice_number' => $invoiceNumber,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'status' => 'draft',
        'currency' => 'AOA',
        'exchange_rate' => 1,
        'notes' => 'Fatura de teste com 50 produtos diversos para validação de impressão em múltiplas páginas.',
        'created_by' => 1,
        'subtotal' => 0,
        'net_total' => 0,
        'tax_amount' => 0,
        'tax_payable' => 0,
        'total' => 0,
        'gross_total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    $invoice = SalesInvoice::find($invoiceId);
    
    echo "Fatura criada: {$invoice->invoice_number}\n";
    
    // Adicionar itens
    $subtotal = 0;
    $totalTax = 0;
    
    foreach ($productList as $index => $product) {
        $quantity = rand(1, 5);
        $unitPrice = $product->price ?: rand(1000, 50000) / 100;
        $discount = rand(0, 10);
        $itemSubtotal = $quantity * $unitPrice;
        $discountAmount = $itemSubtotal * ($discount / 100);
        $taxableAmount = $itemSubtotal - $discountAmount;
        $itemTax = $taxableAmount * ($taxRate / 100);
        $itemTotal = $taxableAmount + $itemTax;
        
        SalesInvoiceItem::create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_code' => $product->sku ?? $product->code ?? 'PROD-' . $product->id,
            'product_name' => $product->name,
            'description' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_percent' => $discount,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $itemTax,
            'subtotal' => $itemSubtotal,
            'total' => $itemTotal,
        ]);
        
        $subtotal += $taxableAmount;
        $totalTax += $itemTax;
        
        echo "  Item " . ($index + 1) . ": {$product->name} - Qtd: $quantity - Total: " . number_format($itemTotal, 2) . " Kz\n";
    }
    
    // Atualizar totais da fatura diretamente na DB (bypass Eloquent events)
    DB::table('invoicing_sales_invoices')
        ->where('id', $invoice->id)
        ->update([
            'subtotal' => $subtotal,
            'net_total' => $subtotal,
            'tax_amount' => $totalTax,
            'tax_payable' => $totalTax,
            'total' => $subtotal + $totalTax,
            'gross_total' => $subtotal + $totalTax,
        ]);
    
    DB::commit();
    
    echo "\n✅ Fatura criada com sucesso!\n";
    echo "ID: {$invoice->id}\n";
    echo "Número: {$invoice->invoice_number}\n";
    echo "Itens: " . count($productList) . "\n";
    echo "Subtotal: " . number_format($subtotal, 2) . " Kz\n";
    echo "IVA ($taxRate%): " . number_format($totalTax, 2) . " Kz\n";
    echo "Total: " . number_format($invoice->total, 2) . " Kz\n";
    echo "\n🔗 Acesse: http://soserp.test/invoicing/sales/invoices/{$invoice->id}/preview\n";
    
} catch (Exception $e) {
    DB::rollBack();
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
