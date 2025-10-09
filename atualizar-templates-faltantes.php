<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  ATUALIZAR TEMPLATES FALTANTES COM LAYOUT NOVO\n";
echo "═══════════════════════════════════════════════════════\n\n";

$templates = [
    [
        'slug' => 'plan_rejected',
        'name' => 'Plano Rejeitado',
        'subject' => '❌ Atualização sobre seu plano - {app_name}',
        'description' => 'Email enviado quando um plano é rejeitado',
        'emoji_title' => '❌',
        'title' => 'Atualização sobre seu plano',
        'greeting' => 'Olá <strong>{user_name}</strong>!',
        'main_text' => 'Infelizmente, não foi possível processar a atualização do seu plano <strong>{plan_name}</strong>. Motivo: <strong>{reason}</strong>',
        'box_title' => 'O que fazer agora:',
        'box_items' => [
            '📞 Entre em contato com o suporte',
            '💳 Verifique as informações de pagamento',
            '📋 Confira os dados cadastrais',
            '🔄 Tente novamente mais tarde',
        ],
        'button_text' => '💬 Falar com Suporte',
        'button_url' => '{support_url}',
    ],
    [
        'slug' => 'plan_updated',
        'name' => 'Plano Atualizado',
        'subject' => '✅ Seu plano foi atualizado - {app_name}',
        'description' => 'Email enviado quando um plano é atualizado',
        'emoji_title' => '🎉',
        'title' => 'Plano Atualizado com Sucesso!',
        'greeting' => 'Olá <strong>{user_name}</strong>!',
        'main_text' => 'Seu plano foi atualizado de <strong>{old_plan_name}</strong> para <strong>{new_plan_name}</strong> com sucesso!',
        'box_title' => 'Novos recursos disponíveis:',
        'box_items' => [
            '✅ Acesso aos novos módulos',
            '📈 Limites aumentados',
            '🚀 Recursos premium liberados',
            '💼 Suporte prioritário',
        ],
        'button_text' => '🔐 Acessar Sistema',
        'button_url' => '{login_url}',
    ],
    [
        'slug' => 'account_suspended',
        'name' => 'Conta Suspensa',
        'subject' => '⚠️ Sua conta foi suspensa - {app_name}',
        'description' => 'Email enviado quando uma conta é suspensa',
        'emoji_title' => '⚠️',
        'title' => 'Conta Suspensa',
        'greeting' => 'Olá <strong>{user_name}</strong>!',
        'main_text' => 'Sua conta foi suspensa. Motivo: <strong>{reason}</strong>',
        'box_title' => 'Como resolver:',
        'box_items' => [
            '📞 Entre em contato com o suporte',
            '💳 Regularize os pagamentos pendentes',
            '📋 Verifique os termos de uso',
            '🔄 Aguarde nossa análise',
        ],
        'button_text' => '💬 Falar com Suporte',
        'button_url' => '{support_url}',
    ],
    [
        'slug' => 'payment_approved',
        'name' => 'Pagamento Aprovado',
        'subject' => '✅ Pagamento aprovado - {app_name}',
        'description' => 'Email enviado quando um pagamento é aprovado',
        'emoji_title' => '💳',
        'title' => 'Pagamento Aprovado!',
        'greeting' => 'Olá <strong>{user_name}</strong>!',
        'main_text' => 'Seu pagamento foi aprovado! Sua assinatura está ativa e você já pode usar todos os recursos do sistema.',
        'box_title' => 'Detalhes do pagamento:',
        'box_items' => [
            '📋 Plano: <strong>{plan_name}</strong>',
            '💰 Valor: <strong>{amount}</strong>',
            '📅 Vencimento: <strong>{next_due_date}</strong>',
            '✅ Status: <strong>Ativo</strong>',
        ],
        'button_text' => '🔐 Acessar Sistema',
        'button_url' => '{login_url}',
    ],
];

foreach ($templates as $data) {
    echo "📝 Atualizando template: {$data['name']}\n";
    
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
    
    // Atualizar template
    $template = \App\Models\EmailTemplate::where('slug', $data['slug'])->first();
    if ($template) {
        $template->update([
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body_html' => $bodyHtml,
            'description' => $data['description'],
            'is_active' => true,
        ]);
        echo "   ✅ Atualizado - ID: {$template->id}\n\n";
    } else {
        echo "   ⚠️ Template não encontrado, criando novo...\n";
        $template = \App\Models\EmailTemplate::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body_html' => $bodyHtml,
            'description' => $data['description'],
            'is_active' => true,
        ]);
        echo "   ✅ Criado - ID: {$template->id}\n\n";
    }
}

echo "═══════════════════════════════════════════════════════\n";
echo "✅ Todos os templates atualizados com layout novo!\n";
echo "═══════════════════════════════════════════════════════\n\n";
