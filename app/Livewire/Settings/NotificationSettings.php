<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Models\TenantNotificationSetting;
use App\Services\WhatsAppService;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class NotificationSettings extends Component
{
    public $activeTab = 'dashboard';
    
    // Email Settings
    public $email_enabled = true;
    public $smtp_host;
    public $smtp_port;
    public $smtp_username;
    public $smtp_password;
    public $smtp_encryption = 'tls';
    public $from_email;
    public $from_name;
    public $email_notifications = [];
    
    // SMS Settings
    public $sms_enabled = false;
    public $sms_provider = '';
    public $sms_account_sid;
    public $sms_auth_token;
    public $sms_from_number;
    public $sms_api_token; // D7 Networks
    public $sms_sender_id; // D7 Networks
    public $sms_notifications = [];
    public $sms_notification_templates = []; // Template ID para cada tipo SMS
    
    // WhatsApp Settings
    public $whatsapp_enabled = false;
    public $whatsapp_provider = 'twilio';
    public $whatsapp_account_sid;
    public $whatsapp_auth_token;
    public $whatsapp_from_number;
    public $whatsapp_business_account_id;
    public $whatsapp_sandbox = true;
    public $whatsapp_notifications = [];
    public $whatsapp_templates = [];
    public $whatsapp_notification_templates = []; // Template ID para cada tipo WhatsApp
    public $email_notification_templates = []; // Template ID para cada tipo Email
    
    // Test
    public $testEmail = '';
    public $testPhone;
    public $testTemplateSid = '';
    public $testTemplateVariables = [];
    public $showVariablesModal = false;
    public $selectedTemplate = null;
    public $testMessage = 'Teste de notificação do SOSERP';
    
    public $availableWhatsAppTemplates = [];
    public $availableNotificationTemplates = []; // Templates do sistema

    /**
     * Quais destes campos são segredos, e se já há um gravado.
     *
     * Em Livewire, uma propriedade pública é serializada PARA DENTRO da página
     * e volta em cada pedido. A senha do SMTP e os tokens das operadoras
     * estavam em propriedades públicas: o `type="password"` do campo esconde os
     * caracteres no ecrã e não no código-fonte, onde qualquer pessoa com as
     * ferramentas do browser abertas os lia por extenso.
     *
     * Agora os campos abrem VAZIOS e só se gravam quando alguém escrever um
     * valor novo. O que está guardado nunca sai do servidor; o ecrã limita-se
     * a dizer que existe.
     */
    private const SEGREDOS = [
        'smtp_password',
        'sms_auth_token',
        'sms_api_token',
        'whatsapp_auth_token',
    ];

    /** Para o ecrã poder mostrar "já configurado" sem revelar o valor. */
    public array $segredosGuardados = [];

    public function mount()
    {
        $requestedTab = request()->route('tab') ?? request()->query('tab');
        if (in_array($requestedTab, ['dashboard', 'email', 'sms', 'whatsapp'], true)) {
            $this->activeTab = $requestedTab;
        }

        $tenantId = auth()->user()->activeTenant()->id ?? session('active_tenant_id');
        $settings = TenantNotificationSetting::getForTenant($tenantId);

        foreach (self::SEGREDOS as $campo) {
            $this->segredosGuardados[$campo] = filled($settings->{$campo});
        }

        // Email
        $this->email_enabled = (bool) $settings->email_enabled;
        $this->smtp_host = $settings->smtp_host;
        $this->smtp_port = $settings->smtp_port ?? 587;
        $this->smtp_username = $settings->smtp_username;
        // A senha NÃO é carregada — ver a nota em SEGREDOS.
        $this->smtp_password = '';
        $this->smtp_encryption = $settings->smtp_encryption ?? 'tls';
        $this->from_email = $settings->from_email;
        $this->from_name = $settings->from_name;
        $this->email_notifications = $settings->email_notifications ?? TenantNotificationSetting::getDefaultEmailNotifications();
        
        // SMS
        $this->sms_enabled = (bool) $settings->sms_enabled;
        $this->sms_provider = $settings->sms_provider ?? '';
        $this->sms_account_sid = $settings->sms_account_sid;
        $this->sms_auth_token = '';       // segredo: não sai do servidor
        $this->sms_from_number = $settings->sms_from_number;
        $this->sms_api_token = '';        // segredo: não sai do servidor
        $this->sms_sender_id = $settings->sms_sender_id;
        $this->sms_notifications = $settings->sms_notifications ?? TenantNotificationSetting::getDefaultSmsNotifications();
        
        // WhatsApp
        $this->whatsapp_enabled = (bool) $settings->whatsapp_enabled;
        $this->whatsapp_provider = $settings->whatsapp_provider ?? 'twilio';
        $this->whatsapp_account_sid = $settings->whatsapp_account_sid;
        $this->whatsapp_auth_token = '';  // segredo: não sai do servidor
        $this->whatsapp_from_number = $settings->whatsapp_from_number;
        $this->whatsapp_business_account_id = $settings->whatsapp_business_account_id;
        $this->whatsapp_sandbox = $settings->whatsapp_sandbox === null ? true : (bool) $settings->whatsapp_sandbox;
        $this->whatsapp_notifications = $settings->whatsapp_notifications ?? TenantNotificationSetting::getDefaultWhatsAppNotifications();
        $this->whatsapp_templates = $settings->whatsapp_templates ?? [];
        $this->whatsapp_notification_templates = $settings->whatsapp_notification_templates ?? [];
        $this->sms_notification_templates = $settings->sms_notification_templates ?? [];
        $this->email_notification_templates = $settings->email_notification_templates ?? [];
        
        // Carregar templates do sistema (/notifications/templates)
        $this->loadNotificationTemplates($tenantId);
    }
    
    /**
     * Carregar templates configurados pelo usuário
     */
    protected function loadNotificationTemplates($tenantId)
    {
        $templates = \App\Models\NotificationTemplate::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
        
        $this->availableNotificationTemplates = $templates->map(function($template) {
            return [
                'id' => $template->id,
                'name' => $template->name,
                'module' => $template->module,
                'event' => $template->event,
                'whatsapp_enabled' => $template->whatsapp_enabled,
                'sms_enabled' => $template->sms_enabled,
                'email_enabled' => $template->email_enabled,
            ];
        })->toArray();
    }

    /**
     * As regras do que se grava.
     *
     * Não havia nenhuma: gravava-se um email inválido, uma porta "abc" ou um
     * host vazio com o email ligado, e o erro só aparecia mais tarde, na
     * primeira tentativa de envio — sem ninguém ligar uma coisa à outra.
     *
     * As regras dos canais só se aplicam quando o canal está LIGADO: quem tem
     * o SMS desligado não tem de preencher nada dele para poder gravar o resto.
     */
    protected function rules(): array
    {
        return [
            'smtp_host'     => 'exclude_unless:email_enabled,true|required|string|max:255',
            'smtp_port'     => 'exclude_unless:email_enabled,true|required|integer|min:1|max:65535',
            'smtp_username' => 'exclude_unless:email_enabled,true|nullable|string|max:255',
            'from_email'    => 'exclude_unless:email_enabled,true|required|email|max:255',
            'from_name'     => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|in:tls,ssl,',

            'sms_provider'  => 'exclude_unless:sms_enabled,true|required|string|max:50',
            'sms_sender_id' => 'nullable|string|max:30',

            'whatsapp_provider'    => 'exclude_unless:whatsapp_enabled,true|required|string|max:50',
            'whatsapp_from_number' => 'exclude_unless:whatsapp_enabled,true|required|string|max:30',
        ];
    }

    protected function messages(): array
    {
        return [
            'smtp_host.required'  => 'Indique o servidor de saída (SMTP) — sem ele não sai email nenhum.',
            'smtp_port.required'  => 'Indique a porta do servidor de saída.',
            'smtp_port.integer'   => 'A porta tem de ser um número (normalmente 587 ou 465).',
            'from_email.required' => 'Indique o endereço remetente.',
            'from_email.email'    => 'O endereço remetente não é um email válido.',
            'sms_provider.required'         => 'Escolha a operadora de SMS.',
            'whatsapp_provider.required'    => 'Escolha o fornecedor de WhatsApp.',
            'whatsapp_from_number.required' => 'Indique o número de origem do WhatsApp.',
        ];
    }

    /**
     * Um segredo só se grava quando alguém escreve um novo.
     *
     * Os campos abrem vazios de propósito (o valor guardado não viaja para o
     * browser). Gravar o vazio apagaria a senha do SMTP de quem só veio mudar
     * o nome do remetente.
     */
    private function segredoParaGravar(string $campo, TenantNotificationSetting $settings): array
    {
        $novo = trim((string) $this->{$campo});

        return $novo === '' ? [] : [$campo => $novo];
    }

    /**
     * O segredo a usar num teste de ligação: o que a pessoa acabou de escrever
     * ou, se não escreveu nada, o que já está guardado.
     *
     * Sem isto, testar a ligação depois de recarregar a página falhava sempre
     * com "senha em falta" — porque o campo abre vazio de propósito.
     */
    private function segredoEfectivo(string $campo): ?string
    {
        $escrito = trim((string) $this->{$campo});

        if ($escrito !== '') {
            return $escrito;
        }

        $tenantId = auth()->user()->activeTenant()->id ?? session('active_tenant_id');

        return TenantNotificationSetting::getForTenant($tenantId)->{$campo};
    }

    public function save()
    {
        $this->validate();

        if ($this->sms_enabled && in_array($this->sms_provider, ['d7networks', 'telcosms'], true)
            && blank($this->segredoEfectivo('sms_api_token'))) {
            $this->addError('sms_api_token', 'Indique a chave da aplicação/API antes de ativar este fornecedor.');
            return;
        }

        $tenantId = auth()->user()->activeTenant()->id ?? session('active_tenant_id');
        $settings = TenantNotificationSetting::getForTenant($tenantId);

        $settings->update([
            // Email
            'email_enabled' => $this->email_enabled,
            'smtp_host' => $this->smtp_host,
            'smtp_port' => $this->smtp_port,
            'smtp_username' => $this->smtp_username,
            'smtp_encryption' => $this->smtp_encryption,
            'from_email' => $this->from_email,
            'from_name' => $this->from_name,
            'email_notifications' => $this->email_notifications,
            // SMS
            'sms_enabled' => $this->sms_enabled,
            'sms_provider' => $this->sms_provider,
            'sms_account_sid' => $this->sms_account_sid,
            'sms_from_number' => $this->sms_from_number,
            'sms_sender_id' => $this->sms_provider === 'telcosms' ? 'SOSERP' : $this->sms_sender_id,
            'sms_notifications' => $this->sms_notifications,
            'sms_notification_templates' => $this->sms_notification_templates,
            // WhatsApp
            'whatsapp_enabled' => $this->whatsapp_enabled,
            'whatsapp_provider' => $this->whatsapp_provider,
            'whatsapp_account_sid' => $this->whatsapp_account_sid,
            'whatsapp_from_number' => $this->whatsapp_from_number,
            'whatsapp_business_account_id' => $this->whatsapp_business_account_id,
            'whatsapp_sandbox' => $this->whatsapp_sandbox,
            'whatsapp_notifications' => $this->whatsapp_notifications,
            'whatsapp_templates' => $this->whatsapp_templates,
            'whatsapp_notification_templates' => $this->whatsapp_notification_templates,
            'email_notification_templates' => $this->email_notification_templates,
        ]
            // Os segredos entram só se tiverem sido escritos agora. Um campo
            // vazio significa "manter o que lá está", nunca "apagar".
            + $this->segredoParaGravar('smtp_password', $settings)
            + $this->segredoParaGravar('sms_auth_token', $settings)
            + $this->segredoParaGravar('sms_api_token', $settings)
            + $this->segredoParaGravar('whatsapp_auth_token', $settings));

        // O ecrã volta a mostrar os campos vazios, agora a dizer que há
        // segredo guardado.
        foreach (self::SEGREDOS as $campo) {
            $this->segredosGuardados[$campo] = filled($settings->fresh()->{$campo});
            $this->{$campo} = '';
        }

        $this->dispatch('show-toast', [
            'type' => 'success',
            'message' => 'Configurações de notificações salvas com sucesso!'
        ]);
    }

    public function testEmailConnection()
    {
        $this->validate([
            'smtp_host' => 'required',
            'smtp_port' => 'required|numeric',
            'smtp_username' => 'required',
            'from_email' => 'required|email',
        ]);

        // A senha vem do que está guardado quando o campo está vazio — que é o
        // estado normal ao abrir a página.
        $senha = $this->segredoEfectivo('smtp_password');

        if (blank($senha)) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Escreva a senha do SMTP antes de testar — não há nenhuma guardada.',
            ]);

            return;
        }

        try {
            $port = $this->smtp_port ?? 587;
            $encryption = $this->smtp_encryption;
            if (!$encryption) {
                $encryption = ($port == 465) ? 'ssl' : 'tls';
            }

            config(['mail.default' => 'smtp']);
            config(['mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => $this->smtp_host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $this->smtp_username,
                'password' => $senha,
                'timeout' => 15,
                'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST)),
                'verify_peer' => false,
            ]]);
            config(['mail.from.address' => $this->from_email]);
            config(['mail.from.name' => $this->from_name ?? config('app.name')]);

            // Purge cached mailer so new config takes effect
            app('mail.manager')->purge('smtp');

            // Send a real test email to from_email
            $fromName = $this->from_name ?? config('app.name');
            $testSubject = '[TESTE] Conexão SMTP - ' . config('app.name');
            $testBody = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' .
                '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">' .
                '<h2 style="color:#2563eb;">Teste de Conexão SMTP</h2>' .
                '<p>Este email confirma que a configuração SMTP está funcionando correctamente.</p>' .
                '<table style="width:100%;border-collapse:collapse;margin:15px 0;">' .
                '<tr><td style="padding:8px;border:1px solid #e5e7eb;font-weight:bold;">Host</td><td style="padding:8px;border:1px solid #e5e7eb;">' . e($this->smtp_host) . '</td></tr>' .
                '<tr><td style="padding:8px;border:1px solid #e5e7eb;font-weight:bold;">Porta</td><td style="padding:8px;border:1px solid #e5e7eb;">' . e($port) . '</td></tr>' .
                '<tr><td style="padding:8px;border:1px solid #e5e7eb;font-weight:bold;">Encriptação</td><td style="padding:8px;border:1px solid #e5e7eb;">' . e($encryption) . '</td></tr>' .
                '<tr><td style="padding:8px;border:1px solid #e5e7eb;font-weight:bold;">Remetente</td><td style="padding:8px;border:1px solid #e5e7eb;">' . e($this->from_email) . '</td></tr>' .
                '<tr><td style="padding:8px;border:1px solid #e5e7eb;font-weight:bold;">Data</td><td style="padding:8px;border:1px solid #e5e7eb;">' . now()->format('d/m/Y H:i:s') . '</td></tr>' .
                '</table>' .
                '<p style="color:#6b7280;font-size:12px;">Enviado automaticamente pelo sistema ' . e(config('app.name')) . '</p>' .
                '</div></body></html>';

            \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($testSubject, $testBody, $fromName) {
                $message->to($this->from_email, $fromName)
                        ->subject($testSubject)
                        ->html($testBody);

                if ($this->from_email) {
                    $message->from($this->from_email, $fromName);
                    $message->replyTo($this->from_email, $fromName);
                }
            });

            \Log::info('✅ Teste de email SMTP enviado com sucesso', [
                'host' => $this->smtp_host,
                'port' => $port,
                'to' => $this->from_email,
            ]);

            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => 'Email de teste enviado com sucesso para ' . $this->from_email . '! Verifique a caixa de entrada.'
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Teste de email SMTP falhou', [
                'host' => $this->smtp_host,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Erro ao testar SMTP: ' . $e->getMessage()
            ]);
        }
    }

    public function testSmsConnection()
    {
        if ($this->sms_provider === 'telcosms') {
            $token = $this->segredoEfectivo('sms_api_token');
            if (blank($token)) {
                $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Escreva a chave da aplicação TelcoSMS antes de testar.']);
                return;
            }

            $result = (new \App\Services\TelcoSmsService($token))->checkBalance();
            if (($result['balance_unavailable'] ?? false) === true) {
                $this->dispatch('show-toast', [
                    'type' => 'warning',
                    'message' => 'A TelcoSMS não disponibilizou o saldo neste momento (HTTP '
                        . ($result['status'] ?? 500) . '). A chave deve ser validada enviando um SMS de teste.',
                ]);
                return;
            }
            $message = $result['message'];
            if ($result['success'] && array_key_exists('balance', $result) && $result['balance'] !== null) {
                $message .= ' Saldo: ' . $result['balance'];
            }
            $this->dispatch('show-toast', ['type' => $result['success'] ? 'success' : 'error', 'message' => $message]);
        } elseif ($this->sms_provider === 'd7networks') {
            $token = $this->segredoEfectivo('sms_api_token');

            if (blank($token)) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Escreva o token da D7 Networks antes de testar — não há nenhum guardado.',
                ]);

                return;
            }

            try {
                $d7 = new \App\Services\D7NetworksService($token, $this->sms_sender_id);
                $result = $d7->testConnection();

                if ($result['success']) {
                    $this->dispatch('show-toast', [
                        'type' => 'success',
                        'message' => $result['message'] . ' - Saldo: ' . $result['balance'] . ' ' . $result['currency']
                    ]);
                } else {
                    $this->dispatch('show-toast', [
                        'type' => 'error',
                        'message' => $result['message']
                    ]);
                }
            } catch (\Exception $e) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Erro ao testar D7 Networks: ' . $e->getMessage()
                ]);
            }
        } elseif ($this->sms_provider === 'twilio') {
            $this->validate([
                'sms_account_sid' => 'required',
                'sms_auth_token' => 'required',
            ]);

            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => 'Configurações do Twilio validadas!'
            ]);
        } else {
            $this->dispatch('show-toast', [
                'type' => 'info',
                'message' => 'Provider ' . $this->sms_provider . ' não suporta teste de conexão ainda.'
            ]);
        }
    }

    public function prepareTestWhatsApp()
    {
        $this->validate([
            'testPhone' => 'required|string',
            'testTemplateSid' => 'required|string',
            'whatsapp_account_sid' => 'required',
                        'whatsapp_from_number' => 'required',
        ], [
            'testTemplateSid.required' => 'Selecione um template para enviar o teste'
        ]);

        // Buscar template selecionado
        $this->selectedTemplate = collect(array_merge($this->whatsapp_templates, $this->availableWhatsAppTemplates))
            ->firstWhere('sid', $this->testTemplateSid);

        if (!$this->selectedTemplate) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Template não encontrado'
            ]);
            return;
        }

        // Verificar se template tem variáveis
        try {
            $whatsapp = new WhatsAppService(
                $this->whatsapp_account_sid,
                $this->segredoEfectivo('whatsapp_auth_token'),
                $this->whatsapp_from_number
            );
            
            $templateDetails = $whatsapp->getTemplateDetails($this->testTemplateSid);
            
            \Log::info('Template details fetched', ['details' => $templateDetails]);
            
            if ($templateDetails && isset($templateDetails['variables']) && count($templateDetails['variables']) > 0) {
                // Template tem variáveis - abrir modal
                $this->testTemplateVariables = [];
                foreach ($templateDetails['variables'] as $variable) {
                    $this->testTemplateVariables[$variable] = '';
                }
                $this->showVariablesModal = true;
                
                \Log::info('Opening variables modal', ['variables' => $this->testTemplateVariables]);
            } else {
                // Template sem variáveis - enviar direto
                \Log::info('No variables found, sending directly');
                $this->sendTestWhatsApp();
            }
        } catch (\Exception $e) {
            \Log::error('Error fetching template details', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Abrir modal com variáveis padrão do template
            $this->testTemplateVariables = [
                'date' => '',
                'var' => '',
                'event' => '',
                'number' => ''
            ];
            $this->showVariablesModal = true;
            
            $this->dispatch('show-toast', [
                'type' => 'warning',
                'message' => 'Não foi possível detectar variáveis automaticamente. Preencha as que forem necessárias.'
            ]);
        }
    }

    public function sendTestWhatsApp()
    {
        try {
            // Criar WhatsApp service com configurações do tenant
            $whatsapp = new WhatsAppService(
                $this->whatsapp_account_sid,
                $this->segredoEfectivo('whatsapp_auth_token'),
                $this->whatsapp_from_number
            );
            
            // Preparar variáveis no formato correto para Twilio
            $variables = [];
            foreach ($this->testTemplateVariables as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $variables[(string)$key] = (string)$value;
                }
            }
            
            \Log::info('Sending WhatsApp test', [
                'phone' => $this->testPhone,
                'template_sid' => $this->testTemplateSid,
                'template_name' => $this->selectedTemplate['name'] ?? 'test_template',
                'variables' => $variables
            ]);
            
            // Enviar usando template (obrigatório para WhatsApp Business)
            $result = $whatsapp->sendTemplate(
                $this->testPhone, 
                $this->selectedTemplate['name'] ?? 'test_template',
                $variables,
                $this->testTemplateSid
            );
            
            if ($result) {
                $this->dispatch('show-toast', [
                    'type' => 'success',
                    'message' => 'Mensagem de teste WhatsApp enviada! SID: ' . $result
                ]);
                $this->testPhone = '';
                $this->testTemplateVariables = [];
                $this->showVariablesModal = false;
            } else {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Falha ao enviar mensagem de teste WhatsApp'
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Erro: ' . $e->getMessage()
            ]);
        }
    }

    public function closeVariablesModal()
    {
        $this->showVariablesModal = false;
        $this->testTemplateVariables = [];
    }

    public function fetchWhatsAppTemplates()
    {
        $this->validate([
            'whatsapp_account_sid' => 'required',
                    ]);

        try {
            // Criar WhatsApp service com configurações do tenant
            $whatsapp = new WhatsAppService(
                $this->whatsapp_account_sid,
                $this->segredoEfectivo('whatsapp_auth_token'),
                $this->whatsapp_from_number
            );
            
            $this->availableWhatsAppTemplates = $whatsapp->fetchTemplates();
            
            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => count($this->availableWhatsAppTemplates) . ' templates encontrados!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Erro ao buscar templates: ' . $e->getMessage()
            ]);
        }
    }

    public function addWhatsAppTemplate($template)
    {
        if (!collect($this->whatsapp_templates)->contains('sid', $template['sid'])) {
            $this->whatsapp_templates[] = $template;
        }
    }

    public function removeWhatsAppTemplate($index)
    {
        unset($this->whatsapp_templates[$index]);
        $this->whatsapp_templates = array_values($this->whatsapp_templates);
    }

    public function render()
    {
        return view('livewire.settings.notification-settings');
    }
}
