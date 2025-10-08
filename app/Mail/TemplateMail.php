<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\SmtpSetting;
use App\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public $templateSlug;
    public $data;
    public $tenantId;
    public $emailLog;

    /**
     * Create a new message instance.
     */
    public function __construct(string $templateSlug, array $data = [], $tenantId = null)
    {
        $this->templateSlug = $templateSlug;
        $this->data = $data;
        $this->tenantId = $tenantId;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // Buscar template
        $template = EmailTemplate::bySlug($this->templateSlug)->active()->first();

        if (!$template) {
            throw new \Exception("Template de email '{$this->templateSlug}' não encontrado.");
        }

        // Configurar SMTP
        $smtpSetting = SmtpSetting::getForTenant($this->tenantId);
        
        if (!$smtpSetting) {
            \Log::warning('⚠️ Nenhuma configuração SMTP encontrada para tenant. Usando configuração padrão do sistema (.env)', [
                'tenant_id' => $this->tenantId,
                'template' => $this->templateSlug,
                'default_mailer' => config('mail.default'),
                'default_host' => config('mail.mailers.smtp.host'),
            ]);
            
            // Usar configuração padrão do sistema (não lançar exceção)
            // O Laravel usará as configurações do .env
            
        } else {
            \Log::info('📧 Configurando SMTP personalizado para envio de email', [
                'template' => $this->templateSlug,
                'smtp_host' => $smtpSetting->host,
                'smtp_port' => $smtpSetting->port,
                'smtp_encryption' => $smtpSetting->encryption,
                'from_email' => $smtpSetting->from_email,
            ]);
            
            // Configurar SMTP personalizado
            $smtpSetting->configure();
        }

        // Renderizar template com dados
        $rendered = $template->render($this->data);
        
        \Log::info('📨 Email renderizado e pronto para envio', [
            'subject' => $rendered['subject'],
            'to' => $this->to ?? 'não definido ainda'
        ]);

        return $this->subject($rendered['subject'])
            ->html($rendered['body_html'])
            ->text('emails.text', ['content' => $rendered['body_text'] ?? strip_tags($rendered['body_html'])]);
    }
}
