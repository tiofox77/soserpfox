<?php

/**
 * SCRIPT DE EXEMPLO
 * 
 * Este é um exemplo de script que pode ser executado pela interface.
 * Use como base para criar seus próprios scripts.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  SCRIPT DE EXEMPLO\n";
echo "═══════════════════════════════════════════════════════\n\n";

// 1. Informações do Sistema
echo "📊 Informações do Sistema:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Laravel: " . app()->version() . "\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Ambiente: " . config('app.env') . "\n";
echo "Debug: " . (config('app.debug') ? 'Ativo' : 'Inativo') . "\n\n";

// 2. Estatísticas do Banco de Dados
echo "💾 Estatísticas do Banco:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $userCount = \App\Models\User::count();
    $tenantCount = \App\Models\Tenant::count();
    
    echo "Total de Usuários: {$userCount}\n";
    echo "Total de Tenants: {$tenantCount}\n\n";
    
} catch (\Exception $e) {
    echo "❌ Erro ao buscar estatísticas: " . $e->getMessage() . "\n\n";
}

// 3. Verificação de Diretórios
echo "📁 Verificação de Diretórios:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$directories = [
    'storage/logs' => storage_path('logs'),
    'storage/framework/cache' => storage_path('framework/cache'),
    'public/storage' => public_path('storage'),
];

foreach ($directories as $name => $path) {
    $exists = is_dir($path);
    $writable = is_writable($path);
    $status = $exists && $writable ? '✅' : '❌';
    
    echo "{$status} {$name}: " . ($exists ? 'Existe' : 'Não existe') . 
         ($exists && $writable ? ' (Gravável)' : ' (Não gravável)') . "\n";
}

echo "\n";

// 4. Tempo de Execução
echo "⏱️ Tempo de Execução:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
sleep(1); // Simular processamento
echo "Processamento simulado: 1 segundo\n\n";

echo "✅ Script de exemplo executado com sucesso!\n";
echo "   Use este script como base para criar seus próprios.\n\n";
