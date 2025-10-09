<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\SmtpSetting;
use App\Models\EmailTemplate;

class ResetPasswordNotification extends Notification
{
    public $token;

    /**
     * Create a notification instance.
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's channels.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        \Log::info('📧 Enviando email de reset de senha', [
            'email' => $notifiable->email,
            'user_id' => $notifiable->id,
        ]);

        // BUSCAR CONFIGURAÇÃO SMTP DO BANCO (igual ao wizard)
        $smtpSetting = SmtpSetting::getForTenant(null);
        
        if (!$smtpSetting) {
            \Log::error('❌ Configuração SMTP não encontrada no banco');
            throw new \Exception('Configuração SMTP não encontrada');
        }
        
        \Log::info('📧 Configuração SMTP encontrada', [
            'host' => $smtpSetting->host,
            'port' => $smtpSetting->port,
            'encryption' => $smtpSetting->encryption,
        ]);
        
        // CONFIGURAR SMTP usando método configure() do modelo
        $smtpSetting->configure();
        \Log::info('✅ SMTP configurado do banco de dados');

        // BUSCAR TEMPLATE DO BANCO
        $template = EmailTemplate::where('slug', 'password-reset')->first();
        
        if (!$template) {
            \Log::error('❌ Template password-reset não encontrado');
            throw new \Exception('Template password-reset não encontrado');
        }
        
        \Log::info('📄 Template password-reset encontrado', [
            'id' => $template->id,
            'subject' => $template->subject,
        ]);
        
        // URL de reset
        $resetUrl = url(config('app.url').route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
        
        // Dados para o template
        $data = [
            'user_name' => $notifiable->name,
            'reset_url' => $resetUrl,
            'app_name' => config('app.name', 'SOS ERP'),
            'app_url' => config('app.url'),
            'support_email' => $smtpSetting->from_email,
        ];
        
        // Renderizar template do BD
        $rendered = $template->render($data);
        
        \Log::info('📧 Template renderizado', [
            'subject' => $rendered['subject'],
            'body_length' => strlen($rendered['body_html']),
        ]);
        
        // Criar MailMessage com HTML do template
        return (new MailMessage)
            ->subject($rendered['subject'])
            ->view('emails.template-custom', [
                'body_html' => $rendered['body_html']
            ]);
    }
}
