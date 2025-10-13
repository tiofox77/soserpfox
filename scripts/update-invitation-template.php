<?php

/**
 * ATUALIZAR TEMPLATE DE CONVITE COM BOTÃO MELHORADO
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  ATUALIZAR TEMPLATE DE CONVITE\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Buscar template
$template = \App\Models\EmailTemplate::where('slug', 'user-invitation')->first();

if (!$template) {
    echo "❌ Template 'user-invitation' não encontrado!\n";
    echo "   Execute: php test-invitation-email.php para criar\n\n";
    exit(1);
}

echo "📧 Template encontrado: {$template->name}\n";
echo "   ID: {$template->id}\n";
echo "   Última atualização: {$template->updated_at->format('d/m/Y H:i')}\n\n";

// Novo HTML com botão grande e visível
$newBodyHtml = '
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
</div>';

echo "🔄 Atualizando template...\n";

$template->update([
    'body_html' => $newBodyHtml,
    'variables' => 'user_name, name, invited_name, company_name, tenant_name, invite_url, invite_link, expires_in_days, expiry_date, app_name, support_email, email, inviter_name',
]);

echo "✅ Template atualizado com sucesso!\n\n";

echo "📋 Melhorias aplicadas:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ✅ Botão GRANDE (20px 50px padding)\n";
echo "  ✅ Fonte MAIOR (18px)\n";
echo "  ✅ Texto em MAIÚSCULAS para destaque\n";
echo "  ✅ Gradiente roxo/azul vibrante\n";
echo "  ✅ Sombra para profundidade\n";
echo "  ✅ Emoji de foguete (🚀) para ação\n";
echo "  ✅ Espaçamento generoso (40px)\n";
echo "  ✅ Compatível com email clients (table)\n";
echo "  ✅ Box de aviso sobre expiração\n";
echo "  ✅ Link alternativo caso botão falhe\n\n";

echo "🧪 Para testar o novo template:\n";
echo "   php test-invitation-email.php\n\n";

echo "📱 Ou envie um novo convite pela interface:\n";
echo "   http://soserp.test/users\n\n";
