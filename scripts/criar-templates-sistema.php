<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  CRIAR TEMPLATES DO SISTEMA COM LAYOUT QUE FUNCIONOU\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Templates a serem criados/atualizados
$templates = [
    [
        'slug' => 'user-invitation',
        'name' => 'Convite de Usuário',
        'subject' => 'Você foi convidado para {tenant_name}!',
        'description' => 'Email enviado quando um usuário é convidado para uma empresa',
        'emoji_title' => '📬',
        'title' => 'Você foi convidado!',
        'greeting' => 'Olá <strong>{user_name}</strong>!',
        'main_text' => 'Você foi convidado para fazer parte da equipe da <strong>{tenant_name}</strong> no {app_name}.',
        'box_title' => 'Como começar:',
        'box_items' => [
            '✅ Clique no botão abaixo para aceitar o convite',
            '🔐 Defina sua senha de acesso',
            '👥 Conheça sua equipe',
            '🚀 Comece a trabalhar',
        ],
        'button_text' => '📨 Aceitar Convite',
        'button_url' => '{invitation_url}',
    ],
    [
        'slug' => 'password-reset',
        'name' => 'Redefinição de Senha',
        'subject' => 'Redefinir senha - {app_name}',
        'description' => 'Email enviado quando o usuário solicita redefinição de senha',
        'emoji_title' => '🔒',
        'title' => 'Redefinir sua senha',
        'greeting' => 'Olá <strong>{user_name}</strong>!',
        'main_text' => 'Recebemos uma solicitação para redefinir a senha da sua conta no {app_name}.',
        'box_title' => 'Instruções:',
        'box_items' => [
            '✅ Clique no botão abaixo',
            '🔐 Defina uma nova senha forte',
            '⏰ O link expira em 60 minutos',
            '🛡️ Se não foi você, ignore este email',
        ],
        'button_text' => '🔑 Redefinir Senha',
        'button_url' => '{reset_url}',
    ],
    [
        'slug' => 'payment-confirmed',
        'name' => 'Pagamento Confirmado',
        'subject' => '✅ Pagamento confirmado - {app_name}',
        'description' => 'Email enviado quando um pagamento é confirmado',
        'emoji_title' => '💳',
        'title' => 'Pagamento Confirmado!',
        'greeting' => 'Olá <strong>{user_name}</strong>!',
        'main_text' => 'Seu pagamento foi confirmado com sucesso! Obrigado por confiar no {app_name}.',
        'box_title' => 'Detalhes do pagamento:',
        'box_items' => [
            '📋 Plano: <strong>{plan_name}</strong>',
            '💰 Valor: <strong>{amount}</strong>',
            '📅 Data: <strong>{payment_date}</strong>',
            '✅ Status: <strong>Confirmado</strong>',
        ],
        'button_text' => '🔐 Acessar Sistema',
        'button_url' => '{login_url}',
    ],
    [
        'slug' => 'subscription-expiring',
        'name' => 'Assinatura Expirando',
        'subject' => '⚠️ Sua assinatura expira em breve - {app_name}',
        'description' => 'Email enviado quando a assinatura está próxima do vencimento',
        'emoji_title' => '⏰',
        'title' => 'Sua assinatura expira em breve',
        'greeting' => 'Olá <strong>{user_name}</strong>!',
        'main_text' => 'Sua assinatura do plano <strong>{plan_name}</strong> expira em <strong>{days_remaining} dias</strong>. Renove agora para não perder acesso ao sistema.',
        'box_title' => 'O que acontece se não renovar:',
        'box_items' => [
            '⚠️ Perda de acesso ao sistema',
            '🔒 Dados ficam bloqueados temporariamente',
            '📊 Relatórios não serão gerados',
            '💡 Renove agora para evitar interrupções',
        ],
        'button_text' => '💳 Renovar Assinatura',
        'button_url' => '{renewal_url}',
    ],
];

foreach ($templates as $data) {
    echo "📝 Criando/Atualizando template: {$data['name']}\n";
    
    // Gerar HTML do template
    $boxItems = '';
    foreach ($data['box_items'] as $item) {
        $boxItems .= "                                            <li style='margin-bottom: 10px;'>{$item}</li>\n";
    }
    
    $bodyHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
</head>
<body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color: #f4f4f4; padding: 20px 0;'>
        <tr>
            <td align='center'>
                <table width='600' cellpadding='0' cellspacing='0' border='0' style='background-color: #ffffff; border-radius: 8px; overflow: hidden;'>
                    <!-- Header com Gradiente Suave -->
                    <tr>
                        <td style='padding: 40px 20px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);'>
                            <!-- Logo -->
                            <div style='margin-bottom: 20px;'>
                                <img src='{{app_url}}/images/logo.png' alt='SOS ERP' style='max-width: 120px; height: auto;' />
                            </div>
                            <h2 style='color: #ffffff; font-size: 28px; margin: 0; font-weight: bold;'>
                                {$data['emoji_title']} {$data['title']}
                            </h2>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style='padding: 40px;'>
                            <p style='font-size: 18px; color: #333333; margin-bottom: 20px;'>
                                👋 {$data['greeting']}
                            </p>
                            
                            <p style='font-size: 16px; color: #555555; line-height: 1.6; margin-bottom: 20px;'>
                                {$data['main_text']}
                            </p>
                            
                            <!-- Box -->
                            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background: linear-gradient(to right, #f7fafc 0%, #edf2f7 100%); border-left: 4px solid #667eea; border-radius: 8px; margin: 30px 0;'>
                                <tr>
                                    <td style='padding: 20px;'>
                                        <h3 style='color: #667eea; margin-top: 0; margin-bottom: 15px; font-size: 18px;'>📝 {$data['box_title']}</h3>
                                        <ul style='color: #555555; line-height: 1.8; padding-left: 20px; margin: 10px 0; list-style: none;'>
{$boxItems}                                        </ul>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Botao com Gradiente Suave -->
                            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin: 30px 0;'>
                                <tr>
                                    <td align='center'>
                                        <a href='{$data['button_url']}' style='display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; padding: 16px 45px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);'>
                                            {$data['button_text']}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Secao Suporte -->
                            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='border-top: 2px solid #e2e8f0; margin-top: 30px; padding-top: 20px;'>
                                <tr>
                                    <td style='text-align: center;'>
                                        <p style='font-size: 14px; color: #718096; margin: 10px 0;'>
                                            💬 Precisa de ajuda? Nossa equipe de suporte está à disposição!
                                        </p>
                                        <p style='font-size: 13px; color: #a0aec0; margin: 5px 0;'>
                                            Entre em contato: {support_email}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Assinatura -->
                            <p style='font-size: 14px; color: #718096; margin-top: 30px; text-align: center;'>
                                Atenciosamente,<br>
                                <strong>💼 Equipe {app_name}</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style='padding: 20px 40px 40px 40px; text-align: center; background-color: #f9fafb;'>
                            <p style='font-size: 12px; color: #999999; margin: 0;'>
                                Este é um email automático. Por favor, não responda.
                            </p>
                            <p style='font-size: 12px; color: #999999; margin: 5px 0 0 0;'>
                                {app_name} - Sistema de Gestão Empresarial
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    
    // Criar ou atualizar template
    $template = \App\Models\EmailTemplate::updateOrCreate(
        ['slug' => $data['slug']],
        [
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body_html' => $bodyHtml,
            'description' => $data['description'],
            'is_active' => true,
        ]
    );
    
    echo "   ✅ {$template->name} - ID: {$template->id}\n\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "✅ Total de templates criados/atualizados: " . count($templates) . "\n";
echo "═══════════════════════════════════════════════════════\n\n";
