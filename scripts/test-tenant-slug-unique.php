<?php

/**
 * TESTAR GERAÇÃO DE SLUG ÚNICO PARA TENANTS
 * 
 * Verifica se o sistema gera slugs únicos quando há nomes duplicados
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  TESTAR GERAÇÃO DE SLUG ÚNICO\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Testar criação de tenant com nome duplicado
$testName = 'fox292933939';

echo "🧪 Testando criação de tenants com nome: {$testName}\n\n";

try {
    // Verificar quantos tenants já existem com esse nome
    $existing = \App\Models\Tenant::where('name', $testName)->get();
    
    echo "📊 Tenants existentes com esse nome: " . $existing->count() . "\n";
    foreach ($existing as $tenant) {
        echo "   • ID: {$tenant->id} | Slug: {$tenant->slug}\n";
    }
    echo "\n";
    
    // Tentar criar um novo
    echo "➕ Criando novo tenant com nome '{$testName}'...\n";
    
    $newTenant = \App\Models\Tenant::create([
        'name' => $testName,
        'company_name' => $testName,
        'nif' => '8987876656556' . rand(1000, 9999),
        'regime' => 'regime_geral',
        'address' => 'av 21',
        'phone' => '+244939729902',
        'email' => 'teste' . rand(1000, 9999) . '@gmail.com',
        'is_active' => true,
    ]);
    
    echo "✅ Tenant criado com sucesso!\n";
    echo "   ID: {$newTenant->id}\n";
    echo "   Nome: {$newTenant->name}\n";
    echo "   Slug: {$newTenant->slug}\n\n";
    
    // Verificar todos os tenants com esse nome agora
    $all = \App\Models\Tenant::where('name', $testName)->get();
    
    echo "📋 Todos os tenants com nome '{$testName}':\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    foreach ($all as $tenant) {
        echo "  • ID: {$tenant->id}\n";
        echo "    Nome: {$tenant->name}\n";
        echo "    Slug: {$tenant->slug} " . ($tenant->id == $newTenant->id ? '⭐ NOVO' : '') . "\n";
        echo "    Email: {$tenant->email}\n\n";
    }
    
    // Deletar o tenant criado (limpeza)
    echo "🧹 Limpando tenant de teste...\n";
    $newTenant->forceDelete();
    echo "✅ Tenant deletado\n\n";
    
} catch (\Exception $e) {
    echo "❌ Erro ao criar tenant:\n";
    echo "   " . $e->getMessage() . "\n\n";
    
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        echo "⚠️  PROBLEMA ENCONTRADO!\n";
        echo "   O sistema ainda não está gerando slugs únicos.\n";
        echo "   Solução já foi implementada no código.\n\n";
    }
}

echo "═══════════════════════════════════════════════════════\n";
echo "  TESTE DE EXCLUSÃO DE TENANT\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Testar verificação de exclusão
$tenant = \App\Models\Tenant::first();

if ($tenant) {
    echo "🏢 Testando tenant: {$tenant->name} (ID: {$tenant->id})\n\n";
    
    $canDelete = $tenant->canBeDeleted();
    
    if ($canDelete['can_delete']) {
        echo "✅ Este tenant PODE ser deletado\n";
        echo "   Não possui faturas emitidas\n\n";
    } else {
        echo "❌ Este tenant NÃO PODE ser deletado\n";
        echo "   Motivo: {$canDelete['reason']}\n";
        echo "   Faturas: {$canDelete['invoices_count']}\n\n";
    }
}

echo "📝 Regras de Exclusão:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ✓ Tenant SEM faturas: Pode ser deletado PERMANENTEMENTE\n";
echo "  ✗ Tenant COM faturas: NÃO pode ser deletado (Lei fiscal)\n";
echo "  ℹ️  Soft delete foi REMOVIDO - Agora é HARD DELETE\n\n";

echo "✅ Teste concluído!\n\n";
