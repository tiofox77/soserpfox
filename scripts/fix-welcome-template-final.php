<?php

/**
 * CORRIGIR TEMPLATE - VERSÃO FINAL
 * Remove header duplicado e usa logo do banco
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  CORRIGIR TEMPLATE - REMOVER HEADER DUPLICADO\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Buscar template
$template = \App\Models\EmailTemplate::where('slug', 'welcome')->first();

if (!$template) {
    echo "❌ Template 'welcome' não encontrado!\n";
    exit(1);
}

// Template corrigido - SEM header próprio, deixa o layout.blade.php fazer isso
$newBodyHtml = '
<div style="padding: 20px;">
    <h2 style="color: #667eea; font-size: 24px; margin: 0 0 20px 0; text-align: center;">
        ✨ Bem-vindo ao {app_name}!
    </h2>
    
    <p style="font-size: 18px; color: #333; margin-bottom: 20px;">
        Olá <strong>{user_name}</strong>! 👋
    </p>
    
    <p style="font-size: 16px; color: #555; line-height: 1.6;">
        Estamos felizes em tê-lo conosco! Sua empresa <strong>{tenant_name}</strong> foi criada com sucesso e está pronta para uso.
    </p>
    
    <div style="background: #f7fafc; padding: 20px; border-radius: 8px; margin: 30px 0; border-left: 4px solid #667eea;">
        <h3 style="color: #667eea; margin-top: 0; font-size: 18px;">🚀 Próximos Passos:</h3>
        <ul style="color: #555; line-height: 1.8; padding-left: 20px; margin: 10px 0;">
            <li>Complete o perfil da sua empresa</li>
            <li>Configure os módulos disponíveis</li>
            <li>Adicione membros da sua equipe</li>
            <li>Explore todas as funcionalidades</li>
        </ul>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{login_url}" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);">
            🔐 Acessar Sistema
        </a>
    </div>
    
    <div style="border-top: 2px solid #e2e8f0; margin-top: 30px; padding-top: 20px;">
        <p style="font-size: 14px; color: #718096; text-align: center; margin: 10px 0;">
            💬 Precisa de ajuda? Nossa equipe de suporte está à disposição!
        </p>
        <p style="font-size: 13px; color: #a0aec0; text-align: center;">
            Responda este email ou acesse a central de ajuda.
        </p>
    </div>
    
    <p style="font-size: 14px; color: #718096; margin-top: 30px; text-align: center;">
        Atenciosamente,<br>
        <strong>Equipe {app_name}</strong>
    </p>
</div>
';

echo "🔧 Aplicando correção...\n\n";

echo "✅ Removendo header próprio (deixa o layout fazer)\n";
echo "✅ Mantendo apenas o conteúdo do corpo\n";
echo "✅ O logo será carregado automaticamente do banco\n\n";

// Atualizar template
$template->update([
    'subject' => 'Bem-vindo ao {app_name}, {user_name}!',
    'body_html' => $newBodyHtml,
    'variables' => ['user_name', 'tenant_name', 'app_name', 'login_url'],
    'is_active' => true,
]);

echo "✅ Template atualizado!\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🧪 ENVIANDO EMAIL DE TESTE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$testEmail = 'tiofox2019@gmail.com';
$testData = [
    'user_name' => 'João Silva',
    'tenant_name' => 'Empresa Teste LTDA',
    'app_name' => config('app.name', 'SOSERP'),
    'login_url' => 'http://soserp.test/login',
];

try {
    // Configurar SMTP
    $smtp = \App\Models\SmtpSetting::default()->active()->first();
    if ($smtp) {
        $smtp->configure();
    }
    
    // Enviar
    \Illuminate\Support\Facades\Mail::to($testEmail)
        ->send(new \App\Mail\TemplateMail('welcome', $testData, null));
    
    echo "✅ EMAIL ENVIADO!\n\n";
    echo "📧 Para: {$testEmail}\n";
    echo "📋 O email agora deve:\n";
    echo "   ✅ Mostrar o logo do sistema (do banco)\n";
    echo "   ✅ Não ter header duplicado\n";
    echo "   ✅ Ter layout limpo e profissional\n";
    echo "   ✅ Todas variáveis substituídas\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: {$e->getMessage()}\n\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "  ✅ CORREÇÃO APLICADA!\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "🎯 FAÇA UM REGISTRO DE TESTE:\n";
echo "   http://soserp.test/register\n\n";

echo "═══════════════════════════════════════════════════════\n\n";
