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
                'slug' => 'plan_approved',
                'name' => 'Plano Aprovado',
                'subject' => '✅ Seu plano foi aprovado - {tenant_name}',
                'description' => 'Email enviado quando um plano/subscrição é aprovado',
                'variables' => ['user_name', 'tenant_name', 'plan_name', 'app_name'],
                'body_html' => '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .success-box { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Plano Aprovado!</h1>
        </div>
        <div class="content">
            <h2>Olá {user_name}!</h2>
            
            <div class="success-box">
                <strong>🎉 Boas notícias!</strong><br>
                Seu plano <strong>{plan_name}</strong> foi aprovado e ativado com sucesso!
            </div>
            
            <p>A partir de agora, você tem acesso a todos os recursos incluídos no seu plano.</p>
            
            <h3>O que você pode fazer agora:</h3>
            <ul>
                <li>✅ Acessar todos os módulos contratados</li>
                <li>✅ Adicionar membros da equipe</li>
                <li>✅ Configurar integrações</li>
                <li>✅ Começar a usar o sistema completo</li>
            </ul>
            
            <p>Estamos aqui para garantir seu sucesso. Se precisar de ajuda, não hesite em contatar nosso suporte!</p>
            
            <p>Atenciosamente,<br><strong>Equipe {app_name}</strong></p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' {app_name}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>',
                'body_text' => 'Olá {user_name}! Seu plano {plan_name} foi aprovado e ativado com sucesso!',
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
