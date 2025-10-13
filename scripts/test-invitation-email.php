<?php

/**
 * TESTAR EMAIL DE CONVITE DE USUÁRIO
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  TESTAR EMAIL DE CONVITE DE USUÁRIO\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Verificar template
$template = \App\Models\EmailTemplate::where('slug', 'user-invitation')->first();

if (!$template) {
    echo "❌ Template 'user-invitation' não encontrado!\n";
    echo "   Criando template de exemplo...\n\n";
    
    $template = \App\Models\EmailTemplate::create([
        'tenant_id' => null,
        'name' => 'Convite de Usuário',
        'slug' => 'user-invitation',
        'subject' => '📨 Você foi convidado para {company_name}!',
        'body_html' => '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; margin-bottom: 30px;">
        <h2 style="color: #667eea; font-size: 28px; margin-bottom: 10px;">📨 Você foi convidado!</h2>
    </div>
    
    <p style="font-size: 16px; line-height: 1.6;">👋 Olá <strong>{user_name}</strong>!</p>
    
    <p style="font-size: 16px; line-height: 1.6;">Você foi convidado para fazer parte da equipe da <strong>{company_name}</strong> no {app_name}.</p>
    
    <h3 style="color: #333; font-size: 18px; margin-top: 25px;">📋 Como começar:</h3>
    <ol style="font-size: 15px; line-height: 1.8;">
        <li>✅ Clique no botão abaixo para aceitar o convite</li>
        <li>🔑 Defina sua senha de acesso</li>
        <li>👥 Conheça sua equipe</li>
        <li>🚀 Comece a trabalhar</li>
    </ol>
    
    <!-- Botão Grande e Visível -->
    <div style="text-align: center; margin: 40px 0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td align="center">
                    <a href="{invite_url}" 
                       style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                              color: white !important; 
                              padding: 20px 50px; 
                              text-decoration: none; 
                              border-radius: 12px; 
                              font-weight: bold;
                              font-size: 18px;
                              display: inline-block;
                              box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                              transition: all 0.3s ease;
                              letter-spacing: 0.5px;">
                        🚀 ACEITAR CONVITE E COMEÇAR
                    </a>
                </td>
            </tr>
        </table>
    </div>
    
    <div style="background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; border-radius: 8px; margin: 25px 0;">
        <p style="margin: 0; font-size: 14px; color: #666;">
            ⏰ <strong>Importante:</strong> Este convite expira em <strong>{expires_in_days} dias</strong> ({expiry_date})
        </p>
    </div>
    
    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
    
    <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin: 20px 0;">
        <p style="margin: 0; font-size: 14px;">
            <strong>💡 Dica:</strong> Se o botão não funcionar, copie e cole este link no seu navegador:<br>
            <a href="{invite_url}" style="color: #667eea; word-break: break-all; font-size: 12px;">{invite_url}</a>
        </p>
    </div>
    
    <p style="font-size: 14px; margin-top: 30px;">
        <strong>👋 Precisa de ajuda?</strong><br>
        Nossa equipe de suporte está à disposição: <a href="mailto:{support_email}" style="color: #667eea;">{support_email}</a>
    </p>
    
    <p style="color: #666; font-size: 12px; margin-top: 30px;">
        Atenciosamente,<br>
        <strong>👥 Equipe {app_name}</strong>
    </p>
</div>
',
        'body_text' => 'Você foi convidado! Olá {user_name}! Você foi convidado para fazer parte da equipe da {company_name}.',
        'variables' => 'user_name, name, invited_name, company_name, tenant_name, invite_url, invite_link, expires_in_days, expiry_date, app_name, support_email, email, inviter_name',
        'is_active' => true,
    ]);
    
    echo "✅ Template criado com sucesso!\n\n";
}

echo "📧 Template atual:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Nome: {$template->name}\n";
echo "Slug: {$template->slug}\n";
echo "Assunto: {$template->subject}\n\n";

// Variáveis disponíveis
echo "📋 Variáveis disponíveis:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$vars = [
    'user_name' => 'Nome do usuário convidado',
    'name' => 'Alias para user_name',
    'invited_name' => 'Alias para user_name',
    'company_name' => 'Nome da empresa',
    'tenant_name' => 'Nome do tenant',
    'invite_url' => 'URL completa do convite',
    'invite_link' => 'Alias para invite_url',
    'expires_in_days' => 'Dias até expirar (número)',
    'expiry_date' => 'Data de expiração (formatada)',
    'app_name' => 'Nome da aplicação',
    'support_email' => 'Email de suporte',
    'email' => 'Email do convidado',
    'inviter_name' => 'Nome de quem convidou',
];

foreach ($vars as $var => $desc) {
    echo "  {" . $var . "} → $desc\n";
}

echo "\n";

// Buscar convite mais recente
$invitation = \App\Models\UserInvitation::latest()->first();

if (!$invitation) {
    echo "⚠️  Nenhum convite encontrado no sistema.\n";
    echo "   Crie um convite através da interface para testar.\n\n";
    exit(0);
}

echo "📧 Último convite encontrado:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Email: {$invitation->email}\n";
echo "Nome: {$invitation->name}\n";
echo "Status: {$invitation->status}\n";
echo "Criado em: {$invitation->created_at->format('d/m/Y H:i')}\n";
echo "Expira em: {$invitation->expires_at->format('d/m/Y H:i')}\n\n";

// Perguntar se quer reenviar
echo "Deseja testar o envio de email para este convite? (s/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$answer = trim(strtolower($line));
fclose($handle);

if ($answer !== 's' && $answer !== 'sim') {
    echo "\n❌ Teste cancelado.\n\n";
    exit(0);
}

echo "\n🚀 Enviando email de teste...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    // Reenviar email
    $invitation->sendInvitationEmail();
    
    echo "\n✅ Email enviado com sucesso!\n";
    echo "   Verifique a caixa de entrada de: {$invitation->email}\n";
    echo "   Verifique também os logs em storage/logs/laravel.log\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ Erro ao enviar email:\n";
    echo "   {$e->getMessage()}\n\n";
    echo "   Verifique os logs em storage/logs/laravel.log\n\n";
}
