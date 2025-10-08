<?php

namespace App\Livewire\SuperAdmin;

use App\Models\SmtpSetting;
use App\Models\Tenant;
use Livewire\Component;
use Livewire\WithPagination;

class SmtpSettings extends Component
{
    use WithPagination;

    public $showModal = false;
    public $editMode = false;
    
    public $settingId;
    public $tenant_id;
    public $host;
    public $port = 587;
    public $username;
    public $password;
    public $encryption = 'tls';
    public $from_email;
    public $from_name;
    public $is_default = false;
    public $is_active = true;
    
    public $testResult = null;
    public $testing = false;
    
    public $showSendTestModal = false;
    public $sendTestSettingId;
    public $sendTestEmail = '';
    public $sendingTest = false;

    protected $rules = [
        'tenant_id' => 'nullable|exists:tenants,id',
        'host' => 'required|string|max:255',
        'port' => 'required|integer|min:1|max:65535',
        'username' => 'required|string|max:255',
        'password' => 'required|string|max:255',
        'encryption' => 'required|in:tls,ssl,none',
        'from_email' => 'required|email|max:255',
        'from_name' => 'required|string|max:255',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function create()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $setting = SmtpSetting::findOrFail($id);
        
        $this->settingId = $setting->id;
        $this->tenant_id = $setting->tenant_id;
        $this->host = $setting->host;
        $this->port = $setting->port;
        $this->username = $setting->username;
        $this->password = ''; // Não mostrar senha por segurança
        $this->encryption = $setting->encryption;
        $this->from_email = $setting->from_email;
        $this->from_name = $setting->from_name;
        $this->is_default = $setting->is_default;
        $this->is_active = $setting->is_active;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => $this->tenant_id,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'encryption' => $this->encryption,
            'from_email' => $this->from_email,
            'from_name' => $this->from_name,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
        ];

        // Só atualizar senha se foi preenchida
        if (!empty($this->password)) {
            $data['password'] = $this->password;
        }

        if ($this->editMode) {
            $setting = SmtpSetting::findOrFail($this->settingId);
            $setting->update($data);
            session()->flash('success', 'Configuração SMTP atualizada com sucesso!');
        } else {
            SmtpSetting::create($data);
            session()->flash('success', 'Configuração SMTP criada com sucesso!');
        }

        $this->closeModal();
    }

    public function delete($id)
    {
        SmtpSetting::findOrFail($id)->delete();
        session()->flash('success', 'Configuração SMTP excluída com sucesso!');
    }

    public function toggleActive($id)
    {
        $setting = SmtpSetting::findOrFail($id);
        $setting->update(['is_active' => !$setting->is_active]);
        session()->flash('success', 'Status atualizado com sucesso!');
    }

    public function setAsDefault($id)
    {
        // Remover default de todas
        SmtpSetting::where('is_default', true)->update(['is_default' => false]);
        
        // Definir como default
        $setting = SmtpSetting::findOrFail($id);
        $setting->update(['is_default' => true]);
        
        session()->flash('success', 'Configuração padrão definida com sucesso!');
    }

    public function testConnection($id)
    {
        $this->testing = true;
        $this->testResult = null;

        try {
            $setting = SmtpSetting::findOrFail($id);
            $result = $setting->testConnection();
            
            $this->testResult = $result;
            
            if ($result['success']) {
                session()->flash('success', $result['message']);
                $this->dispatch('success', message: $result['message']);
            } else {
                session()->flash('error', $result['message']);
                $this->dispatch('error', message: $result['message']);
            }
        } catch (\Exception $e) {
            $this->testResult = [
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage(),
            ];
            session()->flash('error', 'Erro ao testar conexão: ' . $e->getMessage());
            $this->dispatch('error', message: 'Erro ao testar conexão: ' . $e->getMessage());
        }

        $this->testing = false;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function openSendTestModal($id)
    {
        $this->sendTestSettingId = $id;
        $this->sendTestEmail = auth()->user()->email ?? '';
        $this->showSendTestModal = true;
    }

    public function sendTestEmailWithSmtp()
    {
        $this->validate([
            'sendTestEmail' => 'required|email',
        ]);

        $this->sendingTest = true;
        $emailLog = null;

        try {
            $setting = SmtpSetting::findOrFail($this->sendTestSettingId);
            
            // Configurar SMTP
            $setting->configure();
            
            $subject = 'Teste de Configuração SMTP - ' . config('app.name');
            $body = "Este é um email de teste enviado através da configuração SMTP:\n\n" .
                "Host: {$setting->host}\n" .
                "Port: {$setting->port}\n" .
                "Encryption: {$setting->encryption}\n" .
                "From: {$setting->from_email}\n\n" .
                "Se você recebeu este email, a configuração SMTP está funcionando corretamente!\n\n" .
                "Data/Hora: " . now()->format('d/m/Y H:i:s');
            
            // Criar log ANTES de enviar
            $emailLog = \App\Models\EmailLog::createLog([
                'tenant_id' => null,
                'email_template_id' => null,
                'smtp_setting_id' => $setting->id,
                'to_email' => $this->sendTestEmail,
                'from_email' => $setting->from_email,
                'from_name' => $setting->from_name,
                'subject' => $subject,
                'body_preview' => \Illuminate\Support\Str::limit($body, 200),
                'template_slug' => 'smtp_test',
                'template_data' => [
                    'host' => $setting->host,
                    'port' => $setting->port,
                    'encryption' => $setting->encryption,
                ],
            ]);
            
            // Enviar email de teste simples
            \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($setting) {
                $message->to($this->sendTestEmail)
                        ->subject('Teste de Configuração SMTP - ' . config('app.name'));
            });
            
            // Marcar log como enviado
            if ($emailLog) {
                $emailLog->markAsSent();
            }
            
            session()->flash('success', "Email de teste enviado com sucesso para {$this->sendTestEmail}!");
            $this->dispatch('success', message: "Email de teste enviado com sucesso para {$this->sendTestEmail}!");
            $this->closeSendTestModal();
        } catch (\Exception $e) {
            // Marcar log como falho
            if ($emailLog) {
                $emailLog->markAsFailed($e->getMessage());
            }
            
            session()->flash('error', 'Erro ao enviar email: ' . $e->getMessage());
            $this->dispatch('error', message: 'Erro ao enviar email: ' . $e->getMessage());
        }

        $this->sendingTest = false;
    }

    public function closeSendTestModal()
    {
        $this->showSendTestModal = false;
        $this->sendTestSettingId = null;
        $this->sendTestEmail = '';
    }

    private function resetForm()
    {
        $this->settingId = null;
        $this->tenant_id = null;
        $this->host = '';
        $this->port = 587;
        $this->username = '';
        $this->password = '';
        $this->encryption = 'tls';
        $this->from_email = '';
        $this->from_name = '';
        $this->is_default = false;
        $this->is_active = true;
        $this->testResult = null;
        $this->resetValidation();
    }

    public function render()
    {
        $settings = SmtpSetting::with('tenant')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $tenants = Tenant::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.super-admin.smtp-settings', [
            'settings' => $settings,
            'tenants' => $tenants,
        ])->layout('layouts.superadmin');
    }
}
