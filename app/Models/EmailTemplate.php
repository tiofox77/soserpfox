<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'description',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Substituir variáveis no template
     */
    public function render(array $data): array
    {
        $subject = $this->subject;
        $bodyHtml = $this->body_html;
        $bodyText = $this->body_text;

        foreach ($data as $key => $value) {
            /*
             * AS DUAS FORMAS DO MARCADOR, a dupla primeiro (26/09/2026).
             *
             * Os modelos usam `{chave}`, mas o `new-user` — o das credenciais —
             * foi escrito com `{{chave}}`. Só se substituía a simples, e o
             * email saía com a senha e o email entre chavetas: «{Ab12…}», e o
             * assunto «Bem-vindo ao {SOS ERP}». Tirando primeiro a dupla, a
             * simples já não encontra o que sobrava dela.
             */
            $placeholders = ['{{' . $key . '}}', '{{ ' . $key . ' }}', '{' . $key . '}'];
            $value = is_scalar($value) || $value === null ? (string) $value : '';
            $subject = str_replace($placeholders, $value, $subject);
            // No HTML, o valor é TEXTO: um nome com «<script>» ou «<a href>» não
            // vira marcação no email. `false` não re-escapa quem já chega
            // escapado (AvisosDeSubscricao faz o seu próprio e()).
            $bodyHtml = str_replace($placeholders, e($value, false), $bodyHtml);
            if ($bodyText) {
                $bodyText = str_replace($placeholders, $value, $bodyText);
            }
        }

        // Se o body_html não contém doctype, envolve no layout
        if (strpos($bodyHtml, '<!DOCTYPE') === false && strpos($bodyHtml, '@extends') === false) {
            $bodyHtml = $this->wrapInLayout($bodyHtml, $subject);
        }

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ];
    }

    /**
     * Envolver conteúdo no layout padrão
     */
    protected function wrapInLayout(string $content, string $subject): string
    {
        /*
         * NUNCA SE COMPILA O CORPO COMO BLADE.
         *
         * Escrevia-se o corpo — já com os valores do pedido lá dentro — num
         * ficheiro temporário `.blade.php` e compilava-se: o nome de uma empresa
         * registada com «{{ system('id') }}» corria código no servidor. O corpo
         * é DADO: vai para uma vista fixa que o imprime tal e qual.
         */
        try {
            return view('emails.com-conteudo', [
                'subject' => $subject,
                'conteudo' => $content,
            ])->render();
        } catch (\Throwable $e) {
            report($e);

            return $this->manualWrapInLayout($content, $subject);
        }
    }
    
    /**
     * Fallback: envolver manualmente no layout
     */
    protected function manualWrapInLayout(string $content, string $subject): string
    {
        $layout = file_get_contents(resource_path('views/emails/layout.blade.php'));
        
        // Substituir @yield('content')
        $layout = str_replace("@yield('content')", $content, $layout);
        
        // Processar @if(app_logo())
        if (app_logo()) {
            $logoUrl = app_logo();
            $appName = config('app.name', 'SOS ERP');
            $logoHtml = '<img src="' . $logoUrl . '" alt="' . $appName . '" style="max-height: 80px; max-width: 200px; width: auto; height: auto; display: block; margin: 0 auto;">';
            
            $layout = preg_replace('/@if\(app_logo\(\)\).*?@else.*?@endif/s', $logoHtml, $layout);
        } else {
            $layout = preg_replace('/@if\(app_logo\(\)\).*?@else(.*?)@endif/s', '$1', $layout);
        }
        
        // Substituir variáveis Blade
        $layout = str_replace("{{ config('app.name', 'SOS ERP') }}", config('app.name', 'SOS ERP'), $layout);
        $layout = str_replace("{{ \$subject ?? config('app.name') }}", $subject, $layout);
        $layout = str_replace("{{ date('Y') }}", date('Y'), $layout);
        $layout = str_replace("{{ config('app.url') }}", config('app.url'), $layout);
        
        return $layout;
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Método estático para enviar email de teste
     * Usado pela modal de teste E pelo registro
     * Garante que o código seja 100% idêntico
     */
    public static function sendEmail(string $templateSlug, string $toEmail, array $data, $tenantId = null)
    {
        \Log::info('📧 EmailTemplate::sendEmail chamado', [
            'template' => $templateSlug,
            'to' => $toEmail,
            'tenant_id' => $tenantId,
        ]);

        $template = self::where('slug', $templateSlug)->first();
        if (!$template) {
            throw new \Exception("Template '{$templateSlug}' não encontrado.");
        }

        $smtpSetting = \App\Models\SmtpSetting::getForTenant($tenantId);
        if (!$smtpSetting) {
            throw new \Exception('Nenhuma configuração SMTP encontrada.');
        }

        // Configurar SMTP com as credenciais corretas
        $smtpSetting->configure();
        
        \Log::info('✅ SMTP configurado', [
            'smtp_id' => $smtpSetting->id,
            'host' => $smtpSetting->host,
        ]);

        // Renderizar template para pegar subject e body
        $rendered = $template->render($data);

        // Criar log ANTES de enviar
        $emailLog = \App\Models\EmailLog::createLog([
            'tenant_id' => $tenantId,
            'email_template_id' => $template->id,
            'smtp_setting_id' => $smtpSetting->id,
            'to_email' => $toEmail,
            'from_email' => $smtpSetting->from_email ?? config('mail.from.address'),
            'from_name' => $smtpSetting->from_name ?? config('mail.from.name'),
            'subject' => $rendered['subject'],
            'body_preview' => \Illuminate\Support\Str::limit(strip_tags($rendered['body_html']), 200),
            'template_slug' => $template->slug,
            'template_data' => $data,
        ]);

        \Log::info('📝 EmailLog criado', [
            'email_log_id' => $emailLog->id ?? 'NULL',
        ]);

        // Log antes de enviar
        \Log::info('🚀 Iniciando envio de email', [
            'template' => $template->slug,
            'to' => $toEmail,
            'smtp_id' => $smtpSetting->id,
            'smtp_host' => $smtpSetting->host,
            'smtp_port' => $smtpSetting->port,
            'smtp_encryption' => $smtpSetting->encryption,
        ]);

        // Enviar email
        $mail = new \App\Mail\TemplateMail($template->slug, $data);
        \Illuminate\Support\Facades\Mail::to($toEmail)->send($mail);

        \Log::info('✅ Email enviado com sucesso', [
            'to' => $toEmail,
            'template' => $template->slug
        ]);

        // Marcar log como enviado
        if ($emailLog) {
            $emailLog->markAsSent();
            \Log::info('✅ EmailLog marcado como enviado');
        }

        return true;
    }
}
