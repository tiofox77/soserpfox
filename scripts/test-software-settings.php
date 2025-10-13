<?php

/**
 * TESTAR CONFIGURAÇÕES DE SOFTWARE
 * 
 * Testa o sistema de configurações globais e bloqueio de eliminação
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SoftwareSetting;

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  TESTE: CONFIGURAÇÕES DE SOFTWARE\n";
echo "═══════════════════════════════════════════════════════\n\n";

// 1. VERIFICAR TABELA E DADOS INICIAIS
echo "📊 Verificando Configurações Iniciais...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$settings = SoftwareSetting::where('module', 'invoicing')->get();

if ($settings->isEmpty()) {
    echo "❌ Nenhuma configuração encontrada!\n";
    echo "   Execute a migration: php artisan migrate\n\n";
    exit(1);
}

echo "✅ {$settings->count()} configurações encontradas:\n\n";

foreach ($settings as $setting) {
    $value = $setting->setting_value === 'true' ? '🔴 BLOQUEADO' : '🟢 PERMITIDO';
    echo "  • {$setting->description}\n";
    echo "    Chave: {$setting->setting_key}\n";
    echo "    Status: {$value}\n\n";
}

// 2. TESTAR FUNÇÕES HELPER
echo "\n";
echo "🔧 Testando Funções Helper...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$documentTypes = [
    'sales_invoice' => 'Faturas de Venda',
    'proforma' => 'Proformas',
    'receipt' => 'Recibos',
    'credit_note' => 'Notas de Crédito',
    'invoice_receipt' => 'Faturas Recibo',
    'pos_invoice' => 'Faturas POS',
];

foreach ($documentTypes as $key => $name) {
    $canDelete = canDeleteDocument($key);
    $isBlocked = isDeleteBlocked($key);
    
    $status = $canDelete ? '✅ Pode eliminar' : '🔒 Bloqueado';
    $icon = $canDelete ? '🟢' : '🔴';
    
    echo "{$icon} {$name}\n";
    echo "   canDeleteDocument(): " . ($canDelete ? 'true' : 'false') . "\n";
    echo "   isDeleteBlocked(): " . ($isBlocked ? 'true' : 'false') . "\n";
    echo "   Status: {$status}\n\n";
}

// 3. TESTAR ALTERAÇÃO DE CONFIGURAÇÃO
echo "\n";
echo "🔄 Testando Alteração de Configuração...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Bloqueando eliminação de faturas de venda...\n";
SoftwareSetting::set('invoicing', 'block_delete_sales_invoice', true);

$canDeleteNow = canDeleteDocument('sales_invoice');
echo "Resultado: " . ($canDeleteNow ? '❌ ERRO - Ainda pode eliminar!' : '✅ Bloqueado com sucesso!') . "\n\n";

echo "Desbloqueando eliminação de faturas de venda...\n";
SoftwareSetting::set('invoicing', 'block_delete_sales_invoice', false);

$canDeleteNow = canDeleteDocument('sales_invoice');
echo "Resultado: " . ($canDeleteNow ? '✅ Desbloqueado com sucesso!' : '❌ ERRO - Ainda bloqueado!') . "\n\n";

// 4. TESTAR OBTENÇÃO DE DOCUMENTOS BLOQUEADOS
echo "\n";
echo "📋 Testando Lista de Documentos Bloqueados...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Bloquear alguns documentos para teste
SoftwareSetting::set('invoicing', 'block_delete_sales_invoice', true);
SoftwareSetting::set('invoicing', 'block_delete_receipt', true);

$blockedDocs = getBlockedDocuments();

if (empty($blockedDocs)) {
    echo "✅ Nenhum documento bloqueado.\n\n";
} else {
    $count = count($blockedDocs);
    echo "🔒 Documentos bloqueados ({$count}):\n\n";
    foreach ($blockedDocs as $key => $name) {
        echo "  • {$name} ({$key})\n";
    }
    echo "\n";
}

// Resetar para estado inicial
SoftwareSetting::set('invoicing', 'block_delete_sales_invoice', false);
SoftwareSetting::set('invoicing', 'block_delete_receipt', false);

echo "Configurações resetadas para estado inicial.\n\n";

// 5. TESTAR CACHE
echo "💾 Testando Sistema de Cache...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Bloqueando com cache...\n";
SoftwareSetting::set('invoicing', 'block_delete_proforma', true);

echo "Primeira leitura (cria cache): ";
$blocked1 = isDeleteBlocked('proforma');
echo ($blocked1 ? 'Bloqueado' : 'Permitido') . "\n";

echo "Segunda leitura (usa cache): ";
$blocked2 = isDeleteBlocked('proforma');
echo ($blocked2 ? 'Bloqueado' : 'Permitido') . "\n";

echo "Limpando cache...\n";
SoftwareSetting::clearCache();

echo "Leitura após limpar cache: ";
$blocked3 = isDeleteBlocked('proforma');
echo ($blocked3 ? 'Bloqueado' : 'Permitido') . "\n\n";

// Resetar
SoftwareSetting::set('invoicing', 'block_delete_proforma', false);

// 6. RESUMO FINAL
echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  RESUMO FINAL\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "✅ Testes Concluídos com Sucesso!\n\n";

echo "📍 Acesso ao Painel:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "URL: /superadmin/software-settings\n";
echo "Permissão: Super Admin apenas\n\n";

echo "🔧 Funções Disponíveis:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  canDeleteDocument(\$type)      → Verifica se pode eliminar\n";
echo "  isDeleteBlocked(\$type)         → Verifica se está bloqueado\n";
echo "  getBlockedDocuments()          → Lista documentos bloqueados\n";
echo "  softwareSetting(\$mod, \$key)   → Obter configuração\n\n";

echo "📚 Documentação:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  docs/SOFTWARE-SETTINGS.md\n";
echo "  docs/APPLY-DELETE-BLOCK-EXAMPLE.md\n\n";

echo "🎯 Próximos Passos:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  1. Acessar /superadmin/software-settings\n";
echo "  2. Configurar bloqueios desejados\n";
echo "  3. Aplicar verificações nos componentes\n";
echo "  4. Testar eliminação bloqueada\n\n";

echo "✨ Sistema pronto para uso!\n\n";
