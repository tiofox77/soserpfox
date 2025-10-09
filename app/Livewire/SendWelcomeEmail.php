<?php

namespace App\Livewire;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SendWelcomeEmail extends Component
{
    public $testTemplateId;
    public $testEmail = '';
    public $shouldSend = false;
    public $emailData = [];
    
    public function mount()
    {
        // Verificar se há email pendente na sessão
        if (session()->has('pending_welcome_email')) {
            $this->emailData = session('pending_welcome_email');
            $this->shouldSend = true;
            
            \Log::info('📧 Componente SendWelcomeEmail montado com dados pendentes', [
                'user_email' => $this->emailData['user_email'] ?? 'NULL',
            ]);
            
            // Limpar da sessão imediatamente
            session()->forget('pending_welcome_email');
        }
    }
    
    /**
     * Enviar email de boas-vindas
     * CHAMADO VIA JAVASCRIPT após página carregar
     */
    public function send()
    {
        if (!$this->shouldSend || empty($this->emailData)) {
            \Log::warning('⚠️ SendWelcomeEmail::send() chamado mas sem dados');
            return;
        }
        
        try {
            \Log::info('🚀 SendWelcomeEmail::send() iniciando em REQUEST SEPARADO');
            
            // Preparar email usando método igual ao openTestModal
            $this->prepareWelcomeEmail();
            
            // Enviar email usando método igual ao sendTestEmail
            $this->sendWelcomeEmail();
            
            \Log::info('✅ Email de boas-vindas enviado com sucesso (REQUEST SEPARADO)');
            
        } catch (\Exception $e) {
            \Log::error('❌ Erro ao enviar email de boas-vindas (REQUEST SEPARADO)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        
        // Marcar como enviado
        $this->shouldSend = false;
    }
    
    /**
     * Preparar envio de email de boas-vindas
     * CÓPIA EXATA do método openTestModal (EmailTemplates.php linha 145-149)
     */
    private function prepareWelcomeEmail()
    {
        // Buscar template welcome
        $welcomeTemplate = EmailTemplate::where('slug', 'welcome')->first();
        
        // Definir testTemplateId (IGUAL À MODAL)
        $this->testTemplateId = $welcomeTemplate ? $welcomeTemplate->id : 1;
        
        // Definir testEmail (IGUAL À MODAL)
        $this->testEmail = $this->emailData['user_email'];
        
        \Log::info('📧 Email preparado para envio', [
            'testTemplateId' => $this->testTemplateId,
            'testEmail' => $this->testEmail,
        ]);
    }
    
    /**
     * Enviar email de boas-vindas
     * CÓPIA EXATA do método sendTestEmail (EmailTemplates.php linha 152-203)
     */
    private function sendWelcomeEmail()
    {
        // Linha 161: EXATAMENTE como na modal
        $template = EmailTemplate::findOrFail($this->testTemplateId);
        
        // Linha 164-174: Dados de exemplo para o teste (EXATOS da modal)
        $sampleData = [
            'user_name' => $this->emailData['user_name'] ?? 'Usuário Teste',
            'tenant_name' => $this->emailData['tenant_name'] ?? 'Empresa Demo LTDA',
            'app_name' => config('app.name', 'SOS ERP'),
            'plan_name' => 'Plano Premium',
            'old_plan_name' => 'Plano Básico',
            'new_plan_name' => 'Plano Premium',
            'reason' => 'Teste de envio de email',
            'support_email' => config('mail.from.address', 'suporte@soserp.com'),
            'login_url' => route('login'),
        ];
        
        // Linha 176: EXATAMENTE como na modal
        \Log::info('🔷 MODAL: Chamando método estático centralizado');
        
        // Linha 180-185: Código EXATO da modal
        EmailTemplate::sendEmail(
            templateSlug: $template->slug,
            toEmail: $this->testEmail,
            data: $sampleData,
            tenantId: null
        );
        
        // Linha 187: EXATAMENTE como na modal
        \Log::info('✅ MODAL: Email enviado via método estático');
    }
    
    public function render()
    {
        return view('livewire.send-welcome-email');
    }
}
