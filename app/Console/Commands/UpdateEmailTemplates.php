<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmailTemplate;

class UpdateEmailTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:update-templates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atualizar templates de email com novo layout e logo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Atualizando templates de email...');
        
        $templates = $this->getTemplates();
        
        foreach ($templates as $slug => $data) {
            $template = EmailTemplate::where('slug', $slug)->first();
            
            if ($template) {
                $template->update([
                    'body_html' => $data['body_html'],
                    'body_text' => $data['body_text'],
                ]);
                $this->line("✅ Template '{$slug}' atualizado");
            } else {
                EmailTemplate::create([
                    'slug' => $slug,
                    'name' => $data['name'],
                    'subject' => $data['subject'],
                    'body_html' => $data['body_html'],
                    'body_text' => $data['body_text'],
                    'variables' => $data['variables'],
                    'description' => $data['description'],
                    'is_active' => true,
                ]);
                $this->line("✅ Template '{$slug}' criado");
            }
        }
        
        $this->newLine();
        $this->info('✅ Todos os templates foram atualizados com o novo layout!');
        $this->line('   Os emails agora incluem logo e design profissional automaticamente.');
        
        return 0;
    }
    
    protected function getTemplates(): array
    {
        return [
            'welcome' => [
                'name' => 'Boas-vindas',
                'subject' => 'Bem-vindo(a) ao {app_name}!',
                'variables' => ['user_name', 'tenant_name', 'app_name', 'login_url'],
                'description' => 'Email enviado quando um novo usuário é criado',
                'body_html' => '<h2 style="color: #1f2937; margin-bottom: 20px;">Olá, {user_name}! 👋</h2>

<p style="margin-bottom: 15px;">Seja muito bem-vindo(a) ao <strong>{app_name}</strong>!</p>

<p style="margin-bottom: 15px;">Sua conta foi criada com sucesso e você já pode começar a usar nossa plataforma.</p>

<div class="info-box">
    <strong>🏢 Empresa:</strong> {tenant_name}<br>
    <strong>👤 Usuário:</strong> {user_name}
</div>

<p style="text-align: center; margin: 30px 0;">
    <a href="{login_url}" class="button">Acessar o Sistema</a>
</p>

<p style="margin-bottom: 10px;">Se você tiver alguma dúvida, entre em contato com nosso suporte.</p>

<p style="margin-bottom: 10px;">Atenciosamente,<br><strong>Equipe {app_name}</strong></p>',
                'body_text' => 'Olá, {user_name}!

Seja muito bem-vindo(a) ao {app_name}!

Sua conta foi criada com sucesso e você já pode começar a usar nossa plataforma.

Empresa: {tenant_name}
Usuário: {user_name}

Acesse o sistema em: {login_url}

Se você tiver alguma dúvida, entre em contato com nosso suporte.

Atenciosamente,
Equipe {app_name}',
            ],
            
            'plan_approved' => [
                'name' => 'Plano Aprovado',
                'subject' => 'Seu plano {plan_name} foi aprovado!',
                'variables' => ['user_name', 'tenant_name', 'plan_name', 'app_name', 'login_url'],
                'description' => 'Email enviado quando um plano é aprovado',
                'body_html' => '<h2 style="color: #1f2937; margin-bottom: 20px;">Parabéns, {user_name}! 🎉</h2>

<div class="success-box">
    <p style="margin: 0;"><strong>✅ Seu plano foi aprovado com sucesso!</strong></p>
</div>

<p style="margin: 20px 0;">Temos o prazer de informar que seu plano <strong>{plan_name}</strong> foi aprovado e já está ativo.</p>

<p style="margin-bottom: 15px;">Agora você tem acesso completo a todos os recursos da plataforma!</p>

<div class="info-box">
    <strong>📦 Plano:</strong> {plan_name}<br>
    <strong>🏢 Empresa:</strong> {tenant_name}<br>
    <strong>✅ Status:</strong> Ativo
</div>

<p style="text-align: center; margin: 30px 0;">
    <a href="{login_url}" class="button">Começar a Usar</a>
</p>

<p style="margin-bottom: 10px;">Obrigado por escolher o <strong>{app_name}</strong>!</p>

<p style="margin-bottom: 10px;">Atenciosamente,<br><strong>Equipe {app_name}</strong></p>',
                'body_text' => 'Parabéns, {user_name}!

Seu plano foi aprovado com sucesso!

Temos o prazer de informar que seu plano {plan_name} foi aprovado e já está ativo.

Agora você tem acesso completo a todos os recursos da plataforma!

Plano: {plan_name}
Empresa: {tenant_name}
Status: Ativo

Acesse o sistema em: {login_url}

Obrigado por escolher o {app_name}!

Atenciosamente,
Equipe {app_name}',
            ],
            
            'plan_rejected' => [
                'name' => 'Plano Rejeitado',
                'subject' => 'Atualização sobre seu pedido de plano',
                'variables' => ['user_name', 'tenant_name', 'plan_name', 'reason', 'app_name', 'support_email'],
                'description' => 'Email enviado quando um plano é rejeitado',
                'body_html' => '<h2 style="color: #1f2937; margin-bottom: 20px;">Olá, {user_name}</h2>

<div class="error-box">
    <p style="margin: 0;"><strong>⚠️ Seu pedido de plano precisa de atenção</strong></p>
</div>

<p style="margin: 20px 0;">Infelizmente, não foi possível aprovar seu pedido do plano <strong>{plan_name}</strong> no momento.</p>

<div class="warning-box">
    <strong>📝 Motivo:</strong><br>
    {reason}
</div>

<p style="margin: 20px 0;">Por favor, entre em contato com nosso suporte para mais informações e resolução.</p>

<div class="info-box">
    <strong>📧 Suporte:</strong> {support_email}<br>
    <strong>🏢 Empresa:</strong> {tenant_name}
</div>

<p style="margin-bottom: 10px;">Estamos à disposição para ajudar!</p>

<p style="margin-bottom: 10px;">Atenciosamente,<br><strong>Equipe {app_name}</strong></p>',
                'body_text' => 'Olá, {user_name}

Infelizmente, não foi possível aprovar seu pedido do plano {plan_name} no momento.

Motivo:
{reason}

Por favor, entre em contato com nosso suporte para mais informações e resolução.

Suporte: {support_email}
Empresa: {tenant_name}

Estamos à disposição para ajudar!

Atenciosamente,
Equipe {app_name}',
            ],
            
            'plan_updated' => [
                'name' => 'Plano Atualizado',
                'subject' => 'Seu plano foi atualizado para {new_plan_name}',
                'variables' => ['user_name', 'tenant_name', 'old_plan_name', 'new_plan_name', 'app_name', 'login_url'],
                'description' => 'Email enviado quando um plano é atualizado',
                'body_html' => '<h2 style="color: #1f2937; margin-bottom: 20px;">Olá, {user_name}! 🚀</h2>

<div class="success-box">
    <p style="margin: 0;"><strong>✅ Seu plano foi atualizado com sucesso!</strong></p>
</div>

<p style="margin: 20px 0;">Seu plano foi atualizado e você já tem acesso aos novos recursos.</p>

<div class="info-box">
    <strong>📦 Plano Anterior:</strong> {old_plan_name}<br>
    <strong>🎁 Novo Plano:</strong> {new_plan_name}<br>
    <strong>🏢 Empresa:</strong> {tenant_name}
</div>

<p style="text-align: center; margin: 30px 0;">
    <a href="{login_url}" class="button">Explorar Novos Recursos</a>
</p>

<p style="margin-bottom: 10px;">Aproveite os novos recursos do seu plano!</p>

<p style="margin-bottom: 10px;">Atenciosamente,<br><strong>Equipe {app_name}</strong></p>',
                'body_text' => 'Olá, {user_name}!

Seu plano foi atualizado com sucesso!

Seu plano foi atualizado e você já tem acesso aos novos recursos.

Plano Anterior: {old_plan_name}
Novo Plano: {new_plan_name}
Empresa: {tenant_name}

Acesse o sistema em: {login_url}

Aproveite os novos recursos do seu plano!

Atenciosamente,
Equipe {app_name}',
            ],
            
            'account_suspended' => [
                'name' => 'Conta Suspensa',
                'subject' => 'Sua conta foi suspensa',
                'variables' => ['user_name', 'tenant_name', 'reason', 'app_name', 'support_email'],
                'description' => 'Email enviado quando uma conta é suspensa',
                'body_html' => '<h2 style="color: #1f2937; margin-bottom: 20px;">Olá, {user_name}</h2>

<div class="error-box">
    <p style="margin: 0;"><strong>⚠️ Sua conta foi suspensa</strong></p>
</div>

<p style="margin: 20px 0;">Informamos que sua conta foi temporariamente suspensa.</p>

<div class="warning-box">
    <strong>📝 Motivo:</strong><br>
    {reason}
</div>

<p style="margin: 20px 0;">Para reativar sua conta e resolver esta situação, entre em contato com nosso suporte o mais breve possível.</p>

<div class="info-box">
    <strong>📧 Suporte:</strong> {support_email}<br>
    <strong>🏢 Empresa:</strong> {tenant_name}
</div>

<p style="margin-bottom: 10px;">Estamos à disposição para ajudar!</p>

<p style="margin-bottom: 10px;">Atenciosamente,<br><strong>Equipe {app_name}</strong></p>',
                'body_text' => 'Olá, {user_name}

Sua conta foi suspensa.

Informamos que sua conta foi temporariamente suspensa.

Motivo:
{reason}

Para reativar sua conta e resolver esta situação, entre em contato com nosso suporte o mais breve possível.

Suporte: {support_email}
Empresa: {tenant_name}

Estamos à disposição para ajudar!

Atenciosamente,
Equipe {app_name}',
            ],
            
            'user_invitation' => [
                'name' => 'Convite de Usuário',
                'subject' => 'Você foi convidado para {tenant_name} no {app_name}!',
                'variables' => ['inviter_name', 'invited_name', 'tenant_name', 'invite_url', 'expires_in_days', 'app_name'],
                'description' => 'Email enviado quando um usuário convida outro usuário para o tenant',
                'body_html' => '<h2 style="color: #1f2937; margin-bottom: 20px;">Olá, {invited_name}! 👋</h2>

<div class="success-box">
    <p style="margin: 0;"><strong>🎉 Você foi convidado para fazer parte de uma equipe!</strong></p>
</div>

<p style="margin: 20px 0;"><strong>{inviter_name}</strong> convidou você para se juntar à equipe da <strong>{tenant_name}</strong> no {app_name}.</p>

<div class="info-box">
    <strong>👤 Convidado por:</strong> {inviter_name}<br>
    <strong>🏢 Empresa:</strong> {tenant_name}<br>
    <strong>⏰ Válido por:</strong> {expires_in_days} dias
</div>

<p style="margin: 20px 0;">Para aceitar o convite e criar sua conta, clique no botão abaixo:</p>

<p style="text-align: center; margin: 30px 0;">
    <a href="{invite_url}" class="button">Aceitar Convite</a>
</p>

<div class="warning-box">
    <strong>⚠️ Importante:</strong><br>
    Este convite é válido por <strong>{expires_in_days} dias</strong>. Após este período, você precisará solicitar um novo convite.
</div>

<p style="margin-top: 20px; font-size: 13px; color: #6b7280;">
    Se o botão não funcionar, copie e cole este link no seu navegador:<br>
    <a href="{invite_url}" style="color: #3b82f6; word-break: break-all;">{invite_url}</a>
</p>

<p style="margin: 20px 0 10px 0;">Estamos ansiosos para tê-lo(a) em nossa equipe!</p>

<p style="margin-bottom: 10px;">Atenciosamente,<br><strong>Equipe {tenant_name}</strong></p>',
                'body_text' => 'Olá, {invited_name}!

Você foi convidado para fazer parte de uma equipe!

{inviter_name} convidou você para se juntar à equipe da {tenant_name} no {app_name}.

Convidado por: {inviter_name}
Empresa: {tenant_name}
Válido por: {expires_in_days} dias

Para aceitar o convite e criar sua conta, acesse o link abaixo:

{invite_url}

IMPORTANTE:
Este convite é válido por {expires_in_days} dias. Após este período, você precisará solicitar um novo convite.

Estamos ansiosos para tê-lo(a) em nossa equipe!

Atenciosamente,
Equipe {tenant_name}',
            ],
        ];
    }
}
