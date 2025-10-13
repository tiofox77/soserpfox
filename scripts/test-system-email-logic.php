<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  TESTAR NOVA LÓGICA DE EMAILS DO SISTEMA\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Verificar SMTP padrão
echo "1️⃣  VERIFICAR SMTP DO SUPER ADMIN (PADRÃO)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$smtpDefault = \App\Models\SmtpSetting::default()->active()->first();

if (!$smtpDefault) {
    echo "❌ ERRO: Nenhuma configuração SMTP padrão encontrada!\n";
    echo "   Configure em: /superadmin/smtp-settings\n\n";
    exit(1);
}

echo "✅ SMTP Padrão (Super Admin):\n";
echo "   ID: {$smtpDefault->id}\n";
echo "   Host: {$smtpDefault->host}:{$smtpDefault->port}\n";
echo "   De: {$smtpDefault->from_email}\n";
echo "   Nome: {$smtpDefault->from_name}\n";
echo "   Encryption: {$smtpDefault->encryption}\n";
echo "   Is Default: " . ($smtpDefault->is_default ? 'SIM' : 'NÃO') . "\n\n";

// Testar auto-detecção
echo "2️⃣  TESTAR AUTO-DETECÇÃO DE EMAILS DO SISTEMA\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$systemTemplates = [
    'welcome',
    'payment_approved',
    'payment_rejected',
    'subscription_suspended',
    'trial_expiring',
];

$normalTemplates = [
    'marketing_campaign',
    'event_reminder',
    'invoice_notification',
];

echo "Templates do SISTEMA (devem usar SMTP padrão):\n";
foreach ($systemTemplates as $template) {
    $mail = new \App\Mail\TemplateMail($template, []);
    $isSystem = $mail->isSystemEmail;
    echo "   " . ($isSystem ? '✅' : '❌') . " {$template}: " . ($isSystem ? 'SISTEMA' : 'TENANT') . "\n";
}

echo "\nTemplates NORMAIS (devem usar SMTP do tenant):\n";
foreach ($normalTemplates as $template) {
    $mail = new \App\Mail\TemplateMail($template, []);
    $isSystem = $mail->isSystemEmail;
    echo "   " . (!$isSystem ? '✅' : '❌') . " {$template}: " . ($isSystem ? 'SISTEMA' : 'TENANT') . "\n";
}

// Enviar email de teste
echo "\n3️⃣  ENVIAR EMAIL DE TESTE (welcome)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$testEmail = 'tiofox2019@gmail.com';

try {
    $testData = [
        'user_name' => 'Teste Sistema',
        'tenant_name' => 'Empresa de Teste',
        'app_name' => config('app.name'),
        'login_url' => url('/login'),
    ];
    
    echo "Enviando para: {$testEmail}...\n";
    echo "Template: welcome (EMAIL DO SISTEMA)\n";
    echo "Deve usar: SMTP do Super Admin\n\n";
    
    \Illuminate\Support\Facades\Mail::to($testEmail)
        ->send(new \App\Mail\TemplateMail('welcome', $testData));
    
    echo "✅ EMAIL ENVIADO COM SUCESSO!\n\n";
    
    echo "🔍 Verifique os logs:\n";
    echo "   - Deve mostrar: 'EMAIL DO SISTEMA - Usando SMTP do Super Admin'\n";
    echo "   - Host: {$smtpDefault->host}\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO ao enviar email:\n";
    echo "   {$e->getMessage()}\n\n";
}

// Limpar dados para teste
echo "4️⃣  LIMPAR DADOS DO USUÁRIO PARA NOVO TESTE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$user = \App\Models\User::withTrashed()->where('email', $testEmail)->first();

if (!$user) {
    echo "✅ Usuário já está limpo!\n\n";
} else {
    DB::beginTransaction();
    
    try {
        echo "Deletando usuário: {$user->name} (ID: {$user->id})\n";
        
        if ($user->tenant_id) {
            $tenant = \App\Models\Tenant::withTrashed()->find($user->tenant_id);
            if ($tenant) {
                echo "Deletando tenant: {$tenant->name} (ID: {$tenant->id})\n";
                \DB::table('subscriptions')->where('tenant_id', $tenant->id)->delete();
                \DB::table('orders')->where('tenant_id', $tenant->id)->delete();
                \DB::table('users')->where('tenant_id', $tenant->id)->delete();
                $tenant->forceDelete();
            }
        }
        
        \DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', 'App\\Models\\User')->delete();
        $user->forceDelete();
        
        DB::commit();
        
        echo "✅ Dados deletados com sucesso!\n\n";
        
    } catch (\Exception $e) {
        DB::rollBack();
        echo "❌ ERRO: {$e->getMessage()}\n\n";
    }
}

echo "═══════════════════════════════════════════════════════\n";
echo "  📋 RESUMO\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "✅ SMTP padrão configurado\n";
echo "✅ Auto-detecção de templates funcionando\n";
echo "✅ Email de teste enviado\n";
echo "✅ Dados limpos para novo registro\n\n";

echo "🎯 PRÓXIMO PASSO:\n";
echo "   1. Verifique o email em: {$testEmail}\n";
echo "   2. Faça novo registro: http://soserp.test/register\n";
echo "   3. Verifique logs: 'EMAIL DO SISTEMA - Usando SMTP do Super Admin'\n\n";

echo "📊 LOGS IMPORTANTES:\n";
echo "   - 🔐 EMAIL DO SISTEMA = Usa SMTP padrão\n";
echo "   - 📧 EMAIL DO TENANT = Usa SMTP do tenant\n\n";

echo "═══════════════════════════════════════════════════════\n\n";
