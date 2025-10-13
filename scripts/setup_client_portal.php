<?php

/**
 * SCRIPT PARA CONFIGURAR PORTAL DO CLIENTE
 * 
 * Este script:
 * 1. Ativa acesso ao portal para um cliente existente
 * 2. Define uma senha de teste
 * 3. Cria algumas faturas de teste (opcional)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  CONFIGURAR PORTAL DO CLIENTE\n";
echo "═══════════════════════════════════════════════════════\n\n";

use App\Models\Client;
use Illuminate\Support\Facades\Hash;

// Buscar ou criar cliente de teste
echo "📝 1. Procurando cliente de teste...\n";

$client = Client::where('email', 'cliente@teste.com')->first();

if (!$client) {
    echo "   ⚠️  Cliente não encontrado. Criando novo cliente...\n";
    
    // Buscar primeiro tenant
    $tenant = \App\Models\Tenant::first();
    
    if (!$tenant) {
        echo "   ❌ Nenhum tenant encontrado! Crie um tenant primeiro.\n";
        exit(1);
    }
    
    $client = Client::create([
        'tenant_id' => $tenant->id,
        'type' => 'pessoa_fisica', // pessoa_fisica ou pessoa_juridica
        'name' => 'Cliente Teste',
        'nif' => '123456789',
        'email' => 'cliente@teste.com',
        'phone' => '+244939729902',
        'mobile' => '+244939729902',
        'address' => 'Rua Teste, 123',
        'city' => 'Luanda',
        'province' => 'Luanda',
        'postal_code' => '1000',
        'country' => 'Angola',
        'is_active' => true,
        'password' => Hash::make('senha123'),
        'portal_access' => true,
    ]);
    
    echo "   ✅ Cliente criado com sucesso!\n";
} else {
    echo "   ✅ Cliente encontrado!\n";
    
    // Atualizar com senha e acesso ao portal
    $client->update([
        'password' => Hash::make('senha123'),
        'portal_access' => true,
        'is_active' => true,
    ]);
    
    echo "   ✅ Senha e acesso ao portal configurados!\n";
}

echo "\n";
echo "📊 INFORMAÇÕES DO CLIENTE:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "ID: {$client->id}\n";
echo "Nome: {$client->name}\n";
echo "Email: {$client->email}\n";
echo "Senha: senha123\n";
echo "Acesso ao Portal: " . ($client->portal_access ? 'SIM' : 'NÃO') . "\n";
echo "Ativo: " . ($client->is_active ? 'SIM' : 'NÃO') . "\n";
echo "\n";

// Verificar faturas
echo "📝 2. Verificando faturas do cliente...\n";
$invoices = \App\Models\Invoicing\SalesInvoice::where('client_id', $client->id)->count();
echo "   Faturas encontradas: {$invoices}\n";

if ($invoices == 0) {
    echo "   ℹ️  Cliente não possui faturas. Você pode criar faturas manualmente no sistema.\n";
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
echo "✅ CONFIGURAÇÃO CONCLUÍDA!\n\n";

echo "🔐 CREDENCIAIS DE ACESSO:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "URL: http://soserp.test/client/login\n";
echo "Email: cliente@teste.com\n";
echo "Senha: senha123\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📋 PRÓXIMOS PASSOS:\n";
echo "  1. Acesse: http://soserp.test/client/login\n";
echo "  2. Faça login com as credenciais acima\n";
echo "  3. Visualize o dashboard do cliente\n";
echo "  4. (Opcional) Crie faturas para este cliente no sistema\n\n";
