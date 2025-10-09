<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  SIMULAR EMAIL DO REGISTRO COM EMAIL PERSONALIZADO\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Receber email como argumento
$emailTo = $argv[1] ?? 'tiofox2019@gmail.com';

echo "📧 Email destino: {$emailTo}\n\n";

// Limpar log
$logFile = storage_path('logs/laravel.log');
file_put_contents($logFile, '');
echo "✅ Log limpo\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔷 SIMULANDO ENVIO DO REGISTERWIZARD\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Criar usuário fake para simular o registro
$user = \App\Models\User::where('email', $emailTo)->first();
if (!$user) {
    echo "⚠️  Usuário não encontrado. Criando usuário fake...\n";
    $user = new \stdClass();
    $user->id = 999;
    $user->name = 'Teste Usuario';
    $user->email = $emailTo;
} else {
    echo "✅ Usuário encontrado: {$user->name} (ID: {$user->id})\n";
}

$tenant = new \stdClass();
$tenant->id = 999;
$tenant->name = 'Empresa Demo LTDA';

echo "\n";

// ✅ CÓDIGO 100% IDÊNTICO AO REGISTERWIZARD (linha 625-658)
try {
    // IMPORTANTE: Fazer login do usuário ANTES (modal usa auth()->user())
    if ($user instanceof \App\Models\User) {
        \Illuminate\Support\Facades\Auth::login($user);
        \Log::info('🔐 Usuário autenticado ANTES do envio (igual à modal que usa auth()->user())');
        echo "🔐 Usuário autenticado\n";
    } else {
        echo "⚠️  Usuário fake (sem autenticação)\n";
    }
    
    // Definir testTemplateId para buscar template welcome (ID 1)
    $testTemplateId = \App\Models\EmailTemplate::where('slug', 'welcome')->first()->id ?? 1;
    
    // Linha 161: EXATAMENTE como na modal
    $template = \App\Models\EmailTemplate::findOrFail($testTemplateId);
    
    echo "✅ Template encontrado: {$template->slug} (ID: {$template->id})\n\n";
    
    // Linha 164-174: Dados de exemplo para o teste (EXATOS da modal)
    $sampleData = [
        'user_name' => auth()->user()->name ?? $user->name,
        'tenant_name' => $tenant->name,
        'app_name' => config('app.name', 'SOS ERP'),
        'plan_name' => 'Plano Premium',
        'old_plan_name' => 'Plano Básico',
        'new_plan_name' => 'Plano Premium',
        'reason' => 'Teste de envio de email',
        'support_email' => config('mail.from.address', 'suporte@soserp.com'),
        'login_url' => route('login'),
    ];
    
    echo "📧 Dados preparados:\n";
    echo "   user_name: {$sampleData['user_name']}\n";
    echo "   tenant_name: {$sampleData['tenant_name']}\n\n";
    
    // Linha 176: EXATAMENTE como na modal
    \Log::info('🔷 MODAL: Chamando método estático centralizado');
    echo "🚀 Enviando email...\n";
    
    // Linha 180-185: Código EXATO da modal (SEM namespace completo)
    \App\Models\EmailTemplate::sendEmail(
        templateSlug: $template->slug,
        toEmail: $user->email,
        data: $sampleData,
        tenantId: null
    );
    
    // Linha 187: EXATAMENTE como na modal
    \Log::info('✅ MODAL: Email enviado via método estático');
    
    echo "✅ Email enviado com sucesso!\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: {$e->getMessage()}\n";
    echo "Arquivo: {$e->getFile()}:{$e->getLine()}\n\n";
    \Log::error('❌ Erro ao enviar email', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 RESULTADO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📧 Email enviado para: {$emailTo}\n\n";

echo "Verifique no Gmail:\n";
echo "1. Se chegou na CAIXA DE ENTRADA → Código está correto ✅\n";
echo "2. Se foi para SPAM → Ainda há algo diferente ❌\n\n";

echo "Para comparar com a modal, envie também pela interface:\n";
echo "http://soserp.test/superadmin/email-templates\n\n";

echo "═══════════════════════════════════════════════════════\n\n";
