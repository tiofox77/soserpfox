<?php

/**
 * TESTE FINAL - LOGO NO EMAIL
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  TESTE FINAL - LOGO NO EMAIL\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "🔍 Verificando configuração do logo...\n\n";

// Verificar app_logo()
$logoPath = app_logo();

if ($logoPath) {
    echo "✅ Logo configurado no sistema!\n";
    echo "   Caminho relativo: {$logoPath}\n";
    echo "   URL completa: " . url($logoPath) . "\n\n";
    
    // Verificar se arquivo existe
    $fullPath = public_path($logoPath);
    if (file_exists($fullPath)) {
        echo "✅ Arquivo do logo existe: {$fullPath}\n\n";
    } else {
        echo "⚠️  Arquivo não encontrado em: {$fullPath}\n";
        echo "   Verifique se o arquivo foi enviado corretamente\n\n";
    }
} else {
    echo "⚠️  Nenhum logo configurado no sistema\n";
    echo "   Será usado o ícone padrão (coroa)\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📧 ENVIANDO EMAIL DE TESTE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$testEmail = 'tiofox2019@gmail.com';
$testData = [
    'user_name' => 'João Silva',
    'tenant_name' => 'Empresa Teste LTDA',
    'app_name' => config('app.name', 'SOSERP'),
    'login_url' => url('/login'),
];

try {
    // Configurar SMTP
    $smtp = \App\Models\SmtpSetting::default()->active()->first();
    if ($smtp) {
        $smtp->configure();
        echo "✅ SMTP: {$smtp->host}:{$smtp->port}\n\n";
    }
    
    // Enviar
    echo "Enviando para: {$testEmail}...\n";
    
    \Illuminate\Support\Facades\Mail::to($testEmail)
        ->send(new \App\Mail\TemplateMail('welcome', $testData, null));
    
    echo "\n✅ EMAIL ENVIADO COM SUCESSO!\n\n";
    
    echo "🔍 VERIFIQUE NO EMAIL:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    if ($logoPath) {
        echo "✅ Logo deve aparecer no topo do email\n";
        echo "   URL do logo: " . url($logoPath) . "\n";
    } else {
        echo "⚠️  Ícone padrão (coroa) deve aparecer\n";
    }
    echo "✅ Nome do app: " . config('app.name') . "\n";
    echo "✅ Conteúdo sem duplicação\n";
    echo "✅ Todas variáveis substituídas\n\n";
    
    echo "📧 Email enviado para: {$testEmail}\n";
    echo "   Verifique caixa de entrada e SPAM\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: {$e->getMessage()}\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "  ✅ TESTE CONCLUÍDO\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "📋 O QUE FOI CORRIGIDO:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Layout.blade.php agora usa: url(app_logo())\n";
echo "✅ Isso gera URL completa: http://soserp.test/storage/...\n";
echo "✅ Emails podem carregar imagens de URLs completas\n\n";

if (!$logoPath) {
    echo "💡 DICA: Para configurar logo:\n";
    echo "   1. Acesse: http://soserp.test/superadmin/settings\n";
    echo "   2. Faça upload do logo\n";
    echo "   3. Salve as configurações\n\n";
}

echo "═══════════════════════════════════════════════════════\n\n";
