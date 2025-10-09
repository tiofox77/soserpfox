<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  ATUALIZAR TEMPLATE WELCOME COM LAYOUT QUE FUNCIONOU\n";
echo "═══════════════════════════════════════════════════════\n\n";

$template = \App\Models\EmailTemplate::where('slug', 'welcome')->first();

if (!$template) {
    echo "❌ Template welcome não encontrado!\n";
    exit;
}

echo "📄 Template encontrado: {$template->name}\n\n";

// Novo HTML com gradientes, emojis e logo (LAYOUT QUE FUNCIONOU)
$newBodyHtml = <<<'HTML'
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
                                🎉 Bem-vindo ao {app_name}!
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
                                Estamos felizes em tê-lo conosco! Sua empresa <strong>{tenant_name}</strong> foi criada com sucesso e está pronta para uso.
                            </p>
                            
                            <!-- Box Proximos Passos -->
                            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background: linear-gradient(to right, #f7fafc 0%, #edf2f7 100%); border-left: 4px solid #667eea; border-radius: 8px; margin: 30px 0;'>
                                <tr>
                                    <td style='padding: 20px;'>
                                        <h3 style='color: #667eea; margin-top: 0; margin-bottom: 15px; font-size: 18px;'>📝 Próximos Passos:</h3>
                                        <ul style='color: #555555; line-height: 1.8; padding-left: 20px; margin: 10px 0; list-style: none;'>
                                            <li style='margin-bottom: 10px;'>✅ Complete o perfil da sua empresa</li>
                                            <li style='margin-bottom: 10px;'>⚙️ Configure os módulos disponíveis</li>
                                            <li style='margin-bottom: 10px;'>👥 Adicione membros da sua equipe</li>
                                            <li style='margin-bottom: 10px;'>🚀 Explore todas as funcionalidades</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Botao com Gradiente Suave -->
                            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin: 30px 0;'>
                                <tr>
                                    <td align='center'>
                                        <a href='{login_url}' style='display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; padding: 16px 45px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);'>
                                            🔐 Acessar Sistema
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

// Atualizar template
$template->body_html = $newBodyHtml;
$template->subject = 'Bem-vindo ao {app_name}, {user_name}!';
$template->save();

echo "✅ Template 'welcome' atualizado com sucesso!\n\n";

echo "Novo layout inclui:\n";
echo "  ✅ Header com gradiente roxo\n";
echo "  ✅ Logo do sistema\n";
echo "  ✅ Emojis estratégicos (🎉👋📝✅⚙️👥🚀🔐💬💼)\n";
echo "  ✅ Box 'Próximos Passos' com gradiente suave\n";
echo "  ✅ Botão com gradiente e shadow suave\n";
echo "  ✅ Estrutura em tabelas (compatível com todos clients)\n";
echo "  ✅ Footer com background cinza claro\n\n";

echo "═══════════════════════════════════════════════════════\n\n";
