<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'user_invitation',
                'name' => 'Convite de Usuário',
                'subject' => '📩 Você foi convidado para {tenant_name}',
                'description' => 'Email enviado quando um usuário é convidado para o tenant',
                'variables' => ['inviter_name', 'invited_name', 'tenant_name', 'invite_url', 'expires_in_days', 'app_name'],
                'body_html' => '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .invite-box { background: #e0e7ff; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .button { display: inline-block; background: #667eea; color: white !important; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        .expires { background: #fef3c7; padding: 10px; border-radius: 5px; margin-top: 15px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📩 Você foi convidado!</h1>
        </div>
        <div class="content">
            <h2>Olá {invited_name}!</h2>
            
            <div class="invite-box">
                <strong>🎉 Convite Especial</strong><br>
                <p style="margin:5px 0 0 0;"><strong>{inviter_name}</strong> convidou você para fazer parte da equipe em <strong>{tenant_name}</strong> no {app_name}!</p>
            </div>
            
            <h3>📋 O que isso significa:</h3>
            <ul>
                <li>✅ Acesso ao sistema da empresa</li>
                <li>✅ Colaboração com a equipe</li>
                <li>✅ Ferramentas profissionais de gestão</li>
                <li>✅ Produtividade aprimorada</li>
            </ul>
            
            <div style="text-align: center;">
                <a href="{invite_url}" class="button">Aceitar Convite</a>
            </div>
            
            <div class="expires">
                ⏰ <strong>Atenção:</strong> Este convite expira em <strong>{expires_in_days} dias</strong>. Aceite logo para não perder o acesso!
            </div>
            
            <p style="margin-top: 20px; font-size: 14px; color: #666;">Se você não esperava este convite ou não conhece {inviter_name}, pode ignorar este email com segurança.</p>
            
            <p>Atenciosamente,<br><strong>Equipe {app_name}</strong></p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' {app_name}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>',
                'body_text' => 'Olá {invited_name}! {inviter_name} convidou você para fazer parte de {tenant_name} no {app_name}. Aceite o convite: {invite_url}. Expira em {expires_in_days} dias.',
                'is_active' => true,
            ],
            
            [
                'slug' => 'welcome',
                'name' => 'Boas-vindas',
                'subject' => 'Bem-vindo ao {app_name}!',
                'description' => 'Email enviado quando um novo usuário se registra',
                'variables' => ['user_name', 'tenant_name', 'app_name', 'login_url'],
                'body_html' => '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Bem-vindo ao {app_name}!</h1>
        </div>
        <div class="content">
            <h2>Olá {user_name}!</h2>
            <p>Seja muito bem-vindo ao <strong>{app_name}</strong>!</p>
            <p>Estamos muito felizes em tê-lo conosco. Sua conta <strong>{tenant_name}</strong> foi criada com sucesso e já está pronta para uso.</p>
            
            <h3>Próximos Passos:</h3>
            <ul>
                <li>✅ Complete seu perfil</li>
                <li>✅ Configure sua empresa</li>
                <li>✅ Explore os módulos disponíveis</li>
                <li>✅ Adicione sua equipe</li>
            </ul>
            
            <div style="text-align: center;">
                <a href="{login_url}" class="button">Acessar Sistema</a>
            </div>
            
            <p>Se tiver alguma dúvida, nossa equipe de suporte está à disposição para ajudar!</p>
            
            <p>Atenciosamente,<br><strong>Equipe {app_name}</strong></p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' {app_name}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>',
                'body_text' => 'Olá {user_name}! Bem-vindo ao {app_name}. Sua conta {tenant_name} foi criada com sucesso. Acesse: {login_url}',
                'is_active' => true,
            ],
            
            [
                'slug' => 'plan_rejected',
                'name' => 'Plano Rejeitado',
                'subject' => '❌ Atualização sobre seu plano - {tenant_name}',
                'description' => 'Email enviado quando um plano/subscrição é rejeitado',
                'variables' => ['user_name', 'tenant_name', 'plan_name', 'reason', 'app_name', 'support_email'],
                'body_html' => '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .warning-box { background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Atualização sobre seu Plano</h1>
        </div>
        <div class="content">
            <h2>Olá {user_name},</h2>
            
            <div class="warning-box">
                <strong>Informação Importante</strong><br>
                Infelizmente, não foi possível aprovar seu plano <strong>{plan_name}</strong>.
            </div>
            
            <p><strong>Motivo:</strong> {reason}</p>
            
            <p>Não se preocupe! Você pode:</p>
            <ul>
                <li>📧 Entrar em contato conosco em: {support_email}</li>
                <li>🔄 Tentar novamente com outro método de pagamento</li>
                <li>💬 Solicitar mais informações pelo suporte</li>
            </ul>
            
            <p>Estamos aqui para ajudar a resolver qualquer problema.</p>
            
            <p>Atenciosamente,<br><strong>Equipe {app_name}</strong></p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' {app_name}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>',
                'body_text' => 'Olá {user_name}. Não foi possível aprovar seu plano {plan_name}. Motivo: {reason}. Entre em contato: {support_email}',
                'is_active' => true,
            ],
            
            [
                'slug' => 'plan_updated',
                'name' => 'Plano Atualizado',
                'subject' => '🔄 Seu plano foi atualizado - {tenant_name}',
                'description' => 'Email enviado quando um plano é atualizado/alterado',
                'variables' => ['user_name', 'tenant_name', 'old_plan_name', 'new_plan_name', 'app_name'],
                'body_html' => '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .info-box { background: #dbeafe; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Plano Atualizado!</h1>
        </div>
        <div class="content">
            <h2>Olá {user_name}!</h2>
            
            <div class="info-box">
                <strong>Seu plano foi atualizado com sucesso!</strong><br>
                De: <strong>{old_plan_name}</strong> → Para: <strong>{new_plan_name}</strong>
            </div>
            
            <p>As mudanças já estão ativas e você pode começar a aproveitar os novos recursos imediatamente.</p>
            
            <h3>Próximos Passos:</h3>
            <ul>
                <li>✅ Explore os novos recursos disponíveis</li>
                <li>✅ Verifique os novos limites de uso</li>
                <li>✅ Configure as novas funcionalidades</li>
            </ul>
            
            <p>Se tiver dúvidas sobre as mudanças, estamos à disposição para ajudar!</p>
            
            <p>Atenciosamente,<br><strong>Equipe {app_name}</strong></p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' {app_name}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>',
                'body_text' => 'Olá {user_name}! Seu plano foi atualizado de {old_plan_name} para {new_plan_name}.',
                'is_active' => true,
            ],
            
            [
                'slug' => 'payment_approved',
                'name' => 'Pagamento Aprovado',
                'subject' => '✅ Pagamento Aprovado - Sua assinatura está ativa!',
                'description' => 'Email enviado quando um pagamento é aprovado e a subscrição é ativada',
                'variables' => ['user_name', 'tenant_name', 'plan_name', 'amount', 'billing_cycle', 'period_start', 'period_end', 'app_name', 'login_url'],
                'body_html' => '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .success-box { background: #d1fae5; border-left: 4px solid #10b981; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .info-table { width: 100%; background: white; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .info-table td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .button { display: inline-block; background: #10b981; color: white !important; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Pagamento Aprovado!</h1>
        </div>
        <div class="content">
            <h2>Olá {user_name}!</h2>
            
            <div class="success-box">
                <h3 style="margin-top:0; color: #10b981;">✅ Excelente Notícia!</h3>
                <p style="margin:0;">Seu pagamento foi aprovado com sucesso e sua assinatura do plano <strong>{plan_name}</strong> está agora ativa!</p>
            </div>
            
            <h3>📋 Detalhes da Assinatura:</h3>
            <table class="info-table">
                <tr>
                    <td><strong>Empresa:</strong></td>
                    <td>{tenant_name}</td>
                </tr>
                <tr>
                    <td><strong>Plano:</strong></td>
                    <td>{plan_name}</td>
                </tr>
                <tr>
                    <td><strong>Valor:</strong></td>
                    <td>AOA {amount}</td>
                </tr>
                <tr>
                    <td><strong>Período:</strong></td>
                    <td>{period_start} a {period_end}</td>
                </tr>
            </table>
            
            <h3>🎯 O que você pode fazer agora:</h3>
            <ul>
                <li>✅ Acessar todos os módulos contratados</li>
                <li>✅ Adicionar membros da sua equipe</li>
                <li>✅ Configurar integrações e personalizar o sistema</li>
                <li>✅ Começar a usar todos os recursos disponíveis</li>
            </ul>
            
            <div style="text-align: center;">
                <a href="{login_url}" class="button">Acessar o Sistema</a>
            </div>
            
            <p>Estamos aqui para garantir seu sucesso. Se precisar de qualquer ajuda ou tiver dúvidas, nossa equipe de suporte está pronta para assist ir!</p>
            
            <p>Obrigado por confiar no {app_name}! 🚀</p>
            
            <p>Atenciosamente,<br><strong>Equipe {app_name}</strong></p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' {app_name}. Todos os direitos reservados.</p>
            <p style="color: #999; margin-top: 10px;">📧 Email: suporte@soserp.vip | 📞 Telefone: +244 939 779 902</p>
        </div>
    </div>
</body>
</html>',
                'body_text' => 'Olá {user_name}! Seu pagamento foi aprovado e sua assinatura do plano {plan_name} está ativa. Período: {period_start} a {period_end}. Acesse: {login_url}',
                'is_active' => true,
            ],
            
            [
                'slug' => 'account_suspended',
                'name' => 'Conta Suspensa',
                'subject' => '⚠️ Sua conta foi suspensa - {tenant_name}',
                'description' => 'Email enviado quando uma conta é desativada ou suspensa',
                'variables' => ['user_name', 'tenant_name', 'reason', 'app_name', 'support_email'],
                'body_html' => '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .alert-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Conta Suspensa</h1>
        </div>
        <div class="content">
            <h2>Olá {user_name},</h2>
            
            <div class="alert-box">
                <strong>Informação Importante</strong><br>
                Sua conta <strong>{tenant_name}</strong> foi suspensa temporariamente.
            </div>
            
            <p><strong>Motivo:</strong> {reason}</p>
            
            <h3>O que isso significa?</h3>
            <ul>
                <li>❌ Acesso ao sistema está temporariamente bloqueado</li>
                <li>💾 Seus dados estão seguros e preservados</li>
                <li>🔄 A conta pode ser reativada</li>
            </ul>
            
            <h3>Como resolver:</h3>
            <p>Entre em contato com nossa equipe de suporte:</p>
            <ul>
                <li>📧 Email: {support_email}</li>
                <li>💬 Descreva sua situação</li>
                <li>⚡ Resolveremos o mais rápido possível</li>
            </ul>
            
            <p>Estamos aqui para ajudar!</p>
            
            <p>Atenciosamente,<br><strong>Equipe {app_name}</strong></p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' {app_name}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>',
                'body_text' => 'Olá {user_name}. Sua conta {tenant_name} foi suspensa. Motivo: {reason}. Entre em contato: {support_email}',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }

        $this->command->info('✅ Templates de email criados com sucesso!');
    }
}
