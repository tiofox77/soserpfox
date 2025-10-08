<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {email : Email de destino}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testar envio de email com configuração SMTP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $toEmail = $this->argument('email');
        
        $this->info('🔍 Buscando configuração SMTP...');
        
        // Buscar configuração SMTP padrão
        $smtpSetting = SmtpSetting::default()->active()->first();
        
        if (!$smtpSetting) {
            $this->error('❌ Nenhuma configuração SMTP padrão encontrada!');
            $this->warn('Configure SMTP em: /superadmin/smtp-settings');
            return 1;
        }
        
        $this->info('✅ Configuração SMTP encontrada:');
        $this->line("   Host: {$smtpSetting->host}");
        $this->line("   Port: {$smtpSetting->port}");
        $this->line("   Encryption: {$smtpSetting->encryption}");
        $this->line("   Username: {$smtpSetting->username}");
        $this->line("   From: {$smtpSetting->from_email}");
        
        $this->newLine();
        $this->info('⚙️ Configurando SMTP...');
        
        // Configurar SMTP
        $smtpSetting->configure();
        
        // Exibir configuração atual
        $this->line('   Config Mail Default: ' . Config::get('mail.default'));
        $this->line('   Config SMTP Host: ' . Config::get('mail.mailers.smtp.host'));
        $this->line('   Config SMTP Port: ' . Config::get('mail.mailers.smtp.port'));
        $this->line('   Config SMTP Encryption: ' . Config::get('mail.mailers.smtp.encryption'));
        
        $this->newLine();
        $this->info("📧 Enviando email de teste para: {$toEmail}");
        
        try {
            $subject = 'Teste de Email SMTP - ' . config('app.name');
            $body = "Este é um email de teste enviado via comando Artisan.\n\n" .
                "Configuração SMTP:\n" .
                "Host: {$smtpSetting->host}\n" .
                "Port: {$smtpSetting->port}\n" .
                "Encryption: {$smtpSetting->encryption}\n" .
                "From: {$smtpSetting->from_email}\n\n" .
                "Se você recebeu este email, a configuração está funcionando!\n\n" .
                "Data/Hora: " . now()->format('d/m/Y H:i:s');
            
            Mail::raw($body, function ($message) use ($toEmail, $subject) {
                $message->to($toEmail)
                        ->subject($subject);
            });
            
            $this->newLine();
            $this->info('✅ Email enviado com sucesso!');
            $this->warn('⏳ Aguarde alguns segundos e verifique:');
            $this->line('   1. Caixa de entrada');
            $this->line('   2. Pasta de SPAM');
            $this->line('   3. Logs em: storage/logs/laravel.log');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Erro ao enviar email:');
            $this->line('   ' . $e->getMessage());
            $this->newLine();
            $this->warn('💡 Dicas:');
            $this->line('   1. Verifique as credenciais SMTP');
            $this->line('   2. Verifique se a porta está aberta');
            $this->line('   3. Tente porta 587 com TLS se 465 não funcionar');
            $this->line('   4. Verifique logs: storage/logs/laravel.log');
            
            return 1;
        }
    }
}
