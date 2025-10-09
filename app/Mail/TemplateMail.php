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
    public $isSystemEmail;

    /**
     * Emails do sistema (sempre usam SMTP do Super Admin):
     * - welcome: Boas-vindas no registro
     * - payment_approved: Aprovação de pagamento
     * - payment_rejected: Rejeição de pagamento
     * - subscription_suspended: Suspensão de plano
     * - subscription_cancelled: Cancelamento de plano
     * - trial_expiring: Aviso de trial expirando
     * - trial_expired: Trial expirado
     * - password_reset: Reset de senha
     * - invoice_overdue: Fatura atrasada
     */
    protected static $systemEmailTemplates = [
        'welcome',
        'payment_approved',
        'payment_rejected',
        'subscription_suspended',
        'subscription_cancelled',
        'trial_expiring',
        'trial_expired',
        'password_reset',
        'invoice_overdue',
        'account_suspended',
        'account_reactivated',
    ];

    /**
     * Create a new message instance.
     * 
     * @param string $templateSlug
     * @param array $data
     * @param int|null $tenantId - Se NULL ou isSystemEmail=true, usa SMTP padrão
     * @param bool|null $isSystemEmail - Força uso do SMTP do super admin
     */
    public function __construct(string $templateSlug, array $data = [], $tenantId = null, $isSystemEmail = null)
    {
        $this->templateSlug = $templateSlug;
        $this->data = $data;
        $this->tenantId = $tenantId;
        
        // Auto-detectar se é email do sistema baseado no template
        if ($isSystemEmail === null) {
            $this->isSystemEmail = in_array($templateSlug, self::$systemEmailTemplates);
        } else {
            $this->isSystemEmail = $isSystemEmail;
        }
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

        // Determinar qual SMTP usar
        if ($this->isSystemEmail) {
            // Emails do sistema SEMPRE usam SMTP padrão (Super Admin)
            $smtpSetting = SmtpSetting::default()->active()->first();
            
            \Log::info('🔐 EMAIL DO SISTEMA - Usando SMTP do Super Admin', [
                'template' => $this->templateSlug,
                'smtp_id' => $smtpSetting ? $smtpSetting->id : 'NULL',
                'smtp_host' => $smtpSetting ? $smtpSetting->host : 'NULL',
                'is_default' => $smtpSetting ? $smtpSetting->is_default : 'NULL',
                'reason' => 'Email do sistema (registro, aprovações, avisos)',
            ]);
        } else {
            // Emails normais usam SMTP do tenant (ou padrão se não tiver)
            $smtpSetting = SmtpSetting::getForTenant($this->tenantId);
            
            \Log::info('📧 EMAIL DO TENANT - Usando SMTP específico', [
                'template' => $this->templateSlug,
                'tenant_id' => $this->tenantId,
                'smtp_id' => $smtpSetting ? $smtpSetting->id : 'NULL',
                'smtp_host' => $smtpSetting ? $smtpSetting->host : 'NULL',
            ]);
        }
        
        if (!$smtpSetting) {
            \Log::warning('⚠️ Nenhuma configuração SMTP encontrada. Usando configuração padrão do sistema (.env)', [
                'tenant_id' => $this->tenantId,
                'template' => $this->templateSlug,
                'is_system_email' => $this->isSystemEmail,
                'default_mailer' => config('mail.default'),
                'default_host' => config('mail.mailers.smtp.host'),
            ]);
            
            // Usar configuração padrão do sistema (não lançar exceção)
            // O Laravel usará as configurações do .env
            
        } else {
            \Log::info('✅ Configurando SMTP para envio', [
                'template' => $this->templateSlug,
                'smtp_type' => $this->isSystemEmail ? 'SISTEMA (Super Admin)' : 'TENANT',
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
