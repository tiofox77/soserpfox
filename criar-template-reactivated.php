<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  CRIAR TEMPLATE DE REATIVAÇÃO DE CONTA\n";
echo "═══════════════════════════════════════════════════════\n\n";

$bodyHtml = <<<'HTML'
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
                        <td style='padding: 40px 20px; text-align: center; background: linear-gradient(135deg, #10b981 0%, #059669 100%);'>
                            <!-- Logo -->
                            <div style='margin-bottom: 20px;'>
                                <img src='{{app_url}}/images/logo.png' alt='SOS ERP' style='max-width: 120px; height: auto;' />
                            </div>
                            <h2 style='color: #ffffff; font-size: 28px; margin: 0; font-weight: bold;'>
                                ✅ Sua Conta foi Reativada!
                            </h2>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style='padding: 40px;'>
                            <p style='font-size: 18px; color: #333333; margin-bottom: 20px;'>
                                👋 Olá <strong>{user_name}</strong>!
                            </p>
                            
                            <p style='font-size: 16px; color: #555555; line-height: 1.6; margin-bottom: 20px;'>
                                Temos uma ótima notícia! Sua conta da empresa <strong>{tenant_name}</strong> foi reativada e você já pode acessar o sistema normalmente.
                            </p>
                            
                            <!-- Box Info -->
                            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background: linear-gradient(to right, #d1fae5 0%, #a7f3d0 100%); border-left: 4px solid #10b981; border-radius: 8px; margin: 30px 0;'>
                                <tr>
                                    <td style='padding: 20px;'>
                                        <h3 style='color: #059669; margin-top: 0; margin-bottom: 15px; font-size: 18px;'>🎉 O que isso significa:</h3>
                                        <ul style='color: #555555; line-height: 1.8; padding-left: 20px; margin: 10px 0; list-style: none;'>
                                            <li style='margin-bottom: 10px;'>✅ Acesso total ao sistema restaurado</li>
                                            <li style='margin-bottom: 10px;'>📊 Todos os seus dados estão disponíveis</li>
                                            <li style='margin-bottom: 10px;'>👥 Sua equipe pode trabalhar normalmente</li>
                                            <li style='margin-bottom: 10px;'>🚀 Todos os módulos estão ativos</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Botao -->
                            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin: 30px 0;'>
                                <tr>
                                    <td align='center'>
                                        <a href='{login_url}' style='display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; padding: 16px 45px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);'>
                                            🔐 Acessar Sistema Agora
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Secao Suporte -->
                            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='border-top: 2px solid #e2e8f0; margin-top: 30px; padding-top: 20px;'>
                                <tr>
                                    <td style='text-align: center;'>
                                        <p style='font-size: 14px; color: #718096; margin: 10px 0;'>
                                            💬 Dúvidas? Nossa equipe está à disposição!
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
    ['slug' => 'account_reactivated'],
    [
        'name' => 'Conta Reativada',
        'subject' => '✅ Sua conta foi reativada - {app_name}',
        'body_html' => $bodyHtml,
        'description' => 'Email enviado quando uma conta (tenant) é reativada pelo super admin',
        'is_active' => true,
    ]
);

echo "✅ Template 'account_reactivated' criado/atualizado com sucesso!\n";
echo "   ID: {$template->id}\n";
echo "   Nome: {$template->name}\n";
echo "   Subject: {$template->subject}\n\n";

echo "Características do template:\n";
echo "  ✅ Header com gradiente verde (reativação)\n";
echo "  ✅ Logo do sistema\n";
echo "  ✅ Emojis apropriados\n";
echo "  ✅ Box informativo com gradiente suave\n";
echo "  ✅ Botão de ação verde\n";
echo "  ✅ Layout consistente com outros templates\n\n";

echo "═══════════════════════════════════════════════════════\n\n";
