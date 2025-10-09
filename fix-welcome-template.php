<?php

/**
 * CORRIGIR TEMPLATE DE BOAS-VINDAS
 * 
 * 1. Substituir {{app_name}} por {app_name}
 * 2. Melhorar conteúdo para evitar SPAM
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  CORRIGIR TEMPLATE DE BOAS-VINDAS\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Buscar template
$template = \App\Models\EmailTemplate::where('slug', 'welcome')->first();

if (!$template) {
    echo "❌ Template 'welcome' não encontrado!\n";
    exit(1);
}

echo "📧 Template atual:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Assunto: {$template->subject}\n\n";
echo "Corpo (primeiros 200 caracteres):\n";
echo substr($template->body_html, 0, 200) . "...\n\n";

// Verificar se tem {{}} ao invés de {}
$hasDoublebraces = strpos($template->subject, '{{') !== false || 
                   strpos($template->body_html, '{{') !== false;

if ($hasDoublebraces) {
    echo "⚠️  PROBLEMA ENCONTRADO: Template usa {{variável}} ao invés de {variável}\n";
    echo "   Laravel Blade usa {{}}, mas nosso sistema usa apenas {}\n\n";
}

// Novo template otimizado
$newSubject = 'Bem-vindo ao {app_name}, {user_name}!';

$newBodyHtml = '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 28px;">✨ Bem-vindo ao {app_name}!</h1>
    </div>
    
    <div style="background: #ffffff; padding: 40px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <p style="font-size: 18px; color: #333; margin-bottom: 20px;">
            Olá <strong>{user_name}</strong>! 👋
        </p>
        
        <p style="font-size: 16px; color: #555; line-height: 1.6;">
            Estamos felizes em tê-lo conosco! Sua empresa <strong>{tenant_name}</strong> foi criada com sucesso e está pronta para uso.
        </p>
        
        <div style="background: #f7fafc; padding: 20px; border-radius: 8px; margin: 30px 0;">
            <h3 style="color: #667eea; margin-top: 0;">🚀 Próximos Passos:</h3>
            <ul style="color: #555; line-height: 1.8; padding-left: 20px;">
                <li>Complete o perfil da sua empresa</li>
                <li>Configure os módulos disponíveis</li>
                <li>Adicione membros da sua equipe</li>
                <li>Explore todas as funcionalidades</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{login_url}" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                🔐 Acessar Sistema
            </a>
        </div>
        
        <div style="border-top: 2px solid #e2e8f0; margin-top: 30px; padding-top: 20px;">
            <p style="font-size: 14px; color: #718096; text-align: center;">
                Precisa de ajuda? Nossa equipe de suporte está à disposição!<br>
                Responda este email ou acesse a central de ajuda.
            </p>
        </div>
        
        <p style="font-size: 14px; color: #718096; margin-top: 30px;">
            Atenciosamente,<br>
            <strong>Equipe {app_name}</strong>
        </p>
    </div>
    
    <div style="text-align: center; padding: 20px; font-size: 12px; color: #a0aec0;">
        <p style="margin: 5px 0;">
            © ' . date('Y') . ' {app_name}. Todos os direitos reservados.
        </p>
        <p style="margin: 5px 0;">
            Este é um email automático do sistema {app_name}.
        </p>
    </div>
</div>
';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔧 APLICANDO CORREÇÕES:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "1. Corrigindo assunto...\n";
echo "   Antes: {$template->subject}\n";
echo "   Depois: {$newSubject}\n\n";

echo "2. Atualizando corpo HTML...\n";
echo "   ✅ Usa {variável} ao invés de {{variável}}\n";
echo "   ✅ Design moderno e profissional\n";
echo "   ✅ Conteúdo otimizado para evitar SPAM\n";
echo "   ✅ Call-to-action claro (botão de acesso)\n";
echo "   ✅ Rodapé com informações completas\n\n";

// Atualizar template
$template->update([
    'subject' => $newSubject,
    'body_html' => $newBodyHtml,
    'variables' => ['user_name', 'tenant_name', 'app_name', 'login_url'],
    'is_active' => true,
]);

echo "✅ Template atualizado com sucesso!\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🧪 TESTANDO NOVO TEMPLATE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Testar renderização
$testData = [
    'user_name' => 'João Silva',
    'tenant_name' => 'Empresa Teste LTDA',
    'app_name' => config('app.name', 'SOSERP'),
    'login_url' => 'http://soserp.test/login',
];

$rendered = $template->render($testData);

echo "✅ Assunto renderizado:\n";
echo "   {$rendered['subject']}\n\n";

echo "✅ Corpo renderizado (primeiros 300 caracteres):\n";
echo "   " . substr(strip_tags($rendered['body_html']), 0, 300) . "...\n\n";

// Verificar se as variáveis foram substituídas
$hasUnreplacedVars = preg_match('/\{(user_name|tenant_name|app_name|login_url)\}/', $rendered['body_html']);

if ($hasUnreplacedVars) {
    echo "⚠️  AVISO: Ainda há variáveis não substituídas!\n";
    preg_match_all('/\{([^}]+)\}/', $rendered['body_html'], $matches);
    echo "   Variáveis não substituídas: " . implode(', ', array_unique($matches[1])) . "\n\n";
} else {
    echo "✅ Todas as variáveis foram substituídas corretamente!\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📧 ENVIANDO EMAIL DE TESTE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$testEmail = 'tiofox2019@gmail.com';

try {
    // Configurar SMTP
    $smtp = \App\Models\SmtpSetting::default()->active()->first();
    if ($smtp) {
        $smtp->configure();
        echo "✅ SMTP configurado: {$smtp->host}:{$smtp->port}\n\n";
    }
    
    // Enviar email de teste
    echo "Enviando email de teste para {$testEmail}...\n";
    
    \Illuminate\Support\Facades\Mail::to($testEmail)
        ->send(new \App\Mail\TemplateMail('welcome', $testData, null));
    
    echo "✅ EMAIL DE TESTE ENVIADO COM SUCESSO!\n\n";
    echo "🔍 VERIFIQUE:\n";
    echo "   1. Caixa de entrada de {$testEmail}\n";
    echo "   2. Se ainda cair em SPAM:\n";
    echo "      - Marque como 'Não é spam'\n";
    echo "      - Adicione remetente aos contatos\n";
    echo "      - Mova para caixa de entrada\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO ao enviar email de teste:\n";
    echo "   {$e->getMessage()}\n\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "  ✅ CORREÇÃO CONCLUÍDA!\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "📋 O QUE FOI CORRIGIDO:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Sintaxe de variáveis: {{}} → {}\n";
echo "✅ Assunto personalizado com nome do usuário\n";
echo "✅ Design moderno e profissional\n";
echo "✅ Conteúdo claro e objetivo\n";
echo "✅ Call-to-action destacado (botão)\n";
echo "✅ Rodapé completo com copyright\n";
echo "✅ Otimizado para evitar filtro de SPAM\n\n";

echo "🎯 PRÓXIMO PASSO:\n";
echo "   Faça um registro real em: http://soserp.test/register\n\n";

echo "═══════════════════════════════════════════════════════\n\n";
