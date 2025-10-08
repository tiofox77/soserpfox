<?php

namespace App\Livewire\SuperAdmin;

use App\Models\EmailTemplate;
use Livewire\Component;
use Livewire\WithPagination;

class EmailTemplates extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editMode = false;
    
    public $templateId;
    public $slug;
    public $name;
    public $subject;
    public $body_html;
    public $body_text;
    public $description;
    public $is_active = true;
    
    public $showPreviewModal = false;
    public $previewData = [];
    
    public $showTestModal = false;
    public $testTemplateId;
    public $testEmail = '';
    public $sending = false;

    protected $rules = [
        'slug' => 'required|string|max:255',
        'name' => 'required|string|max:255',
        'subject' => 'required|string|max:500',
        'body_html' => 'required|string',
        'body_text' => 'nullable|string',
        'description' => 'nullable|string',
        'is_active' => 'boolean',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $template = EmailTemplate::findOrFail($id);
        
        $this->templateId = $template->id;
        $this->slug = $template->slug;
        $this->name = $template->name;
        $this->subject = $template->subject;
        $this->body_html = $template->body_html;
        $this->body_text = $template->body_text;
        $this->description = $template->description;
        $this->is_active = $template->is_active;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'slug' => $this->slug,
            'name' => $this->name,
            'subject' => $this->subject,
            'body_html' => $this->body_html,
            'body_text' => $this->body_text,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ];

        if ($this->editMode) {
            $template = EmailTemplate::findOrFail($this->templateId);
            $template->update($data);
            session()->flash('success', 'Template atualizado com sucesso!');
        } else {
            EmailTemplate::create($data);
            session()->flash('success', 'Template criado com sucesso!');
        }

        $this->closeModal();
    }

    public function delete($id)
    {
        EmailTemplate::findOrFail($id)->delete();
        session()->flash('success', 'Template excluído com sucesso!');
    }

    public function toggleActive($id)
    {
        $template = EmailTemplate::findOrFail($id);
        $template->update(['is_active' => !$template->is_active]);
        session()->flash('success', 'Status atualizado com sucesso!');
    }

    public function preview($id)
    {
        $template = EmailTemplate::findOrFail($id);
        
        // Dados de exemplo para preview
        $sampleData = [
            'user_name' => 'João Silva',
            'tenant_name' => 'Empresa Demo LTDA',
            'app_name' => config('app.name', 'SOS ERP'),
            'plan_name' => 'Plano Premium',
            'old_plan_name' => 'Plano Básico',
            'new_plan_name' => 'Plano Premium',
            'reason' => 'Pagamento não identificado',
            'support_email' => 'suporte@soserp.com',
            'login_url' => route('login'),
        ];
        
        $this->previewData = $template->render($sampleData);
        $this->showPreviewModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function closePreviewModal()
    {
        $this->showPreviewModal = false;
        $this->previewData = [];
    }

    public function openTestModal($id)
    {
        $this->testTemplateId = $id;
        $this->testEmail = auth()->user()->email ?? '';
        $this->showTestModal = true;
    }

    public function sendTestEmail()
    {
        $this->validate([
            'testEmail' => 'required|email',
        ]);

        $this->sending = true;
        $emailLog = null;

        try {
            $template = EmailTemplate::findOrFail($this->testTemplateId);
            $smtpSetting = \App\Models\SmtpSetting::getForTenant(null);
            
            // ✅ IMPORTANTE: Configurar SMTP ANTES de enviar
            if (!$smtpSetting) {
                throw new \Exception('Nenhuma configuração SMTP encontrada. Por favor, configure o SMTP em /superadmin/smtp-settings primeiro.');
            }
            
            // Configurar SMTP com as credenciais corretas
            $smtpSetting->configure();
            
            // Dados de exemplo para o teste
            $sampleData = [
                'user_name' => auth()->user()->name ?? 'Usuário Teste',
                'tenant_name' => 'Empresa Demo LTDA',
                'app_name' => config('app.name', 'SOS ERP'),
                'plan_name' => 'Plano Premium',
                'old_plan_name' => 'Plano Básico',
                'new_plan_name' => 'Plano Premium',
                'reason' => 'Teste de envio de email',
                'support_email' => config('mail.from.address', 'suporte@soserp.com'),
                'login_url' => route('login'),
            ];
            
            // Renderizar template para pegar subject e body
            $rendered = $template->render($sampleData);
            
            // Criar log ANTES de enviar
            $emailLog = \App\Models\EmailLog::createLog([
                'tenant_id' => null,
                'email_template_id' => $template->id,
                'smtp_setting_id' => $smtpSetting->id ?? null,
                'to_email' => $this->testEmail,
                'from_email' => $smtpSetting->from_email ?? config('mail.from.address'),
                'from_name' => $smtpSetting->from_name ?? config('mail.from.name'),
                'subject' => $rendered['subject'],
                'body_preview' => \Illuminate\Support\Str::limit(strip_tags($rendered['body_html']), 200),
                'template_slug' => $template->slug,
                'template_data' => $sampleData,
            ]);
            
            // Log antes de enviar
            \Log::info('🚀 Iniciando envio de email de teste', [
                'template' => $template->slug,
                'to' => $this->testEmail,
                'smtp_id' => $smtpSetting->id,
                'smtp_host' => $smtpSetting->host,
                'smtp_port' => $smtpSetting->port,
                'smtp_encryption' => $smtpSetting->encryption,
            ]);
            
            // Enviar email (TemplateMail.php já configura SMTP novamente, mas deixamos aqui também)
            $mail = new \App\Mail\TemplateMail($template->slug, $sampleData);
            \Illuminate\Support\Facades\Mail::to($this->testEmail)->send($mail);
            
            \Log::info('✅ Email enviado com sucesso (sem exceção)', [
                'to' => $this->testEmail,
                'template' => $template->slug
            ]);
            
            // Marcar log como enviado
            if ($emailLog) {
                $emailLog->markAsSent();
            }
            
            session()->flash('success', "Email de teste enviado com sucesso para {$this->testEmail}!");
            $this->dispatch('success', message: "Email de teste enviado com sucesso para {$this->testEmail}!");
            $this->closeTestModal();
        } catch (\Exception $e) {
            // Marcar log como falho
            if ($emailLog) {
                $emailLog->markAsFailed($e->getMessage());
            }
            
            session()->flash('error', 'Erro ao enviar email: ' . $e->getMessage());
            $this->dispatch('error', message: 'Erro ao enviar email: ' . $e->getMessage());
        }

        $this->sending = false;
    }

    public function closeTestModal()
    {
        $this->showTestModal = false;
        $this->testTemplateId = null;
        $this->testEmail = '';
    }

    private function resetForm()
    {
        $this->templateId = null;
        $this->slug = '';
        $this->name = '';
        $this->subject = '';
        $this->body_html = '';
        $this->body_text = '';
        $this->description = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $templates = EmailTemplate::query()
            ->when($this->search, function($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('slug', 'like', '%' . $this->search . '%')
                    ->orWhere('subject', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('livewire.super-admin.email-templates', [
            'templates' => $templates,
        ])->layout('layouts.superadmin');
    }
}
