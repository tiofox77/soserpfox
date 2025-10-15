<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'module',
        'description',
        'email_enabled',
        'sms_enabled',
        'whatsapp_enabled',
        'email_subject',
        'email_body',
        'sms_body',
        'email_template_id',
        'sms_template_sid',
        'whatsapp_template_sid',
        'trigger_event',
        'notify_before_minutes',
        'notify_at_time',
        'variable_mappings',
        'conditions',
        'is_active',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'is_active' => 'boolean',
        'variable_mappings' => 'array',
        'conditions' => 'array',
        'notify_before_minutes' => 'integer',
    ];

    /**
     * Módulos disponíveis no sistema
     */
    public static function getAvailableModules(): array
    {
        return [
            'hr' => 'Recursos Humanos',
            'events' => 'Eventos',
            'calendar' => 'Calendário',
            'finance' => 'Financeiro',
            'crm' => 'CRM',
            'projects' => 'Projetos',
            'tasks' => 'Tarefas',
        ];
    }

    /**
     * Eventos de trigger disponíveis
     */
    public static function getAvailableTriggers(): array
    {
        return [
            'created' => 'Quando criado',
            'updated' => 'Quando atualizado',
            'date_approaching' => 'Data se aproximando',
            'status_changed' => 'Status mudou',
            'custom' => 'Personalizado',
        ];
    }
    
    /**
     * Variáveis disponíveis por módulo
     */
    public static function getModuleVariables(string $module): array
    {
        $variables = [
            'events' => [
                'event' => ['label' => 'Nome do Evento', 'field' => 'name'],
                'date' => ['label' => 'Data de Início', 'field' => 'start_date'],
                'end_date' => ['label' => 'Data de Término', 'field' => 'end_date'],
                'local' => ['label' => 'Local do Evento', 'field' => 'venue.name'],
                'cliente' => ['label' => 'Nome do Cliente', 'field' => 'client.name'],
                'responsavel' => ['label' => 'Responsável', 'field' => 'responsible.name'],
                'tipo' => ['label' => 'Tipo de Evento', 'field' => 'type.name'],
                'participantes' => ['label' => 'Número de Participantes', 'field' => 'expected_attendees'],
                'valor' => ['label' => 'Valor Total', 'field' => 'total_value'],
                'status' => ['label' => 'Status', 'field' => 'status'],
                'fase' => ['label' => 'Fase', 'field' => 'phase'],
            ],
            
            'hr' => [
                'funcionario' => ['label' => 'Nome do Funcionário', 'field' => 'name'],
                'cargo' => ['label' => 'Cargo', 'field' => 'position'],
                'departamento' => ['label' => 'Departamento', 'field' => 'department.name'],
                'data_admissao' => ['label' => 'Data de Admissão', 'field' => 'hire_date'],
                'salario' => ['label' => 'Salário', 'field' => 'salary'],
                'email' => ['label' => 'Email', 'field' => 'email'],
                'telefone' => ['label' => 'Telefone', 'field' => 'phone'],
                'licenca_inicio' => ['label' => 'Início da Licença', 'field' => 'start_date'],
                'licenca_fim' => ['label' => 'Fim da Licença', 'field' => 'end_date'],
                'tipo_licenca' => ['label' => 'Tipo de Licença', 'field' => 'leave_type'],
            ],
            
            'calendar' => [
                'titulo' => ['label' => 'Título do Evento', 'field' => 'title'],
                'data_inicio' => ['label' => 'Data de Início', 'field' => 'start_date'],
                'data_fim' => ['label' => 'Data de Término', 'field' => 'end_date'],
                'descricao' => ['label' => 'Descrição', 'field' => 'description'],
                'local' => ['label' => 'Local', 'field' => 'location'],
                'organizador' => ['label' => 'Organizador', 'field' => 'organizer.name'],
            ],
            
            'finance' => [
                'documento' => ['label' => 'Número do Documento', 'field' => 'document_number'],
                'cliente' => ['label' => 'Cliente/Fornecedor', 'field' => 'partner.name'],
                'valor' => ['label' => 'Valor', 'field' => 'total_amount'],
                'data_emissao' => ['label' => 'Data de Emissão', 'field' => 'issue_date'],
                'data_vencimento' => ['label' => 'Data de Vencimento', 'field' => 'due_date'],
                'status' => ['label' => 'Status', 'field' => 'status'],
                'descricao' => ['label' => 'Descrição', 'field' => 'description'],
                'metodo_pagamento' => ['label' => 'Método de Pagamento', 'field' => 'payment_method'],
            ],
            
            'crm' => [
                'cliente' => ['label' => 'Nome do Cliente', 'field' => 'name'],
                'empresa' => ['label' => 'Empresa', 'field' => 'company'],
                'email' => ['label' => 'Email', 'field' => 'email'],
                'telefone' => ['label' => 'Telefone', 'field' => 'phone'],
                'responsavel' => ['label' => 'Responsável', 'field' => 'assigned_to.name'],
                'status' => ['label' => 'Status', 'field' => 'status'],
                'oportunidade' => ['label' => 'Valor da Oportunidade', 'field' => 'opportunity_value'],
                'proxima_acao' => ['label' => 'Próxima Ação', 'field' => 'next_action'],
            ],
            
            'projects' => [
                'projeto' => ['label' => 'Nome do Projeto', 'field' => 'name'],
                'cliente' => ['label' => 'Cliente', 'field' => 'client.name'],
                'gerente' => ['label' => 'Gerente do Projeto', 'field' => 'manager.name'],
                'data_inicio' => ['label' => 'Data de Início', 'field' => 'start_date'],
                'data_fim' => ['label' => 'Data de Término', 'field' => 'end_date'],
                'orcamento' => ['label' => 'Orçamento', 'field' => 'budget'],
                'status' => ['label' => 'Status', 'field' => 'status'],
                'progresso' => ['label' => 'Progresso (%)', 'field' => 'progress'],
            ],
            
            'tasks' => [
                'tarefa' => ['label' => 'Título da Tarefa', 'field' => 'title'],
                'descricao' => ['label' => 'Descrição', 'field' => 'description'],
                'responsavel' => ['label' => 'Responsável', 'field' => 'assigned_to.name'],
                'data_vencimento' => ['label' => 'Data de Vencimento', 'field' => 'due_date'],
                'prioridade' => ['label' => 'Prioridade', 'field' => 'priority'],
                'status' => ['label' => 'Status', 'field' => 'status'],
                'projeto' => ['label' => 'Projeto', 'field' => 'project.name'],
            ],
        ];
        
        return $variables[$module] ?? [];
    }

    /**
     * Obter variáveis do template
     */
    public function getVariables(): array
    {
        $variables = [];
        
        // Email
        if ($this->email_enabled && $this->email_template_id) {
            // Extrair variáveis do email template
        }
        
        // WhatsApp/SMS
        if ($this->whatsapp_template_sid) {
            // Buscar variáveis do template WhatsApp
            $whatsapp = new \App\Services\WhatsAppService();
            $details = $whatsapp->getTemplateDetails($this->whatsapp_template_sid);
            if ($details && isset($details['variables'])) {
                $variables = array_merge($variables, $details['variables']);
            }
        }
        
        return array_unique($variables);
    }

    /**
     * Mapear dados do modelo para variáveis do template
     */
    public function mapVariables($model): array
    {
        $mapped = [];
        
        if (!$this->variable_mappings) {
            return $mapped;
        }
        
        foreach ($this->variable_mappings as $variable => $fieldPath) {
            $value = $this->getNestedValue($model, $fieldPath);
            // Twilio não aceita null - converter para string vazia
            $mapped[$variable] = $value ?? '';
        }
        
        return $mapped;
    }
    
    /**
     * Processar corpo do texto substituindo variáveis {{ variable }} pelos valores reais
     */
    public function processBody(string $body, $model): string
    {
        if (!$body || !$this->variable_mappings) {
            return $body;
        }
        
        $variables = $this->mapVariables($model);
        
        foreach ($variables as $key => $value) {
            // Substituir {{ variable }} pelo valor
            $body = str_replace('{{ ' . $key . ' }}', $value, $body);
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }
        
        // Processar quebras de linha
        $body = str_replace('\\n', "\n", $body);
        
        return $body;
    }
    
    /**
     * Obter corpo do SMS processado com variáveis
     */
    public function getSmsBody($model): string
    {
        return $this->processBody($this->sms_body ?? '', $model);
    }
    
    /**
     * Obter assunto do email processado com variáveis
     */
    public function getEmailSubject($model): string
    {
        return $this->processBody($this->email_subject ?? '', $model);
    }
    
    /**
     * Obter corpo do email processado com variáveis
     */
    public function getEmailBody($model): string
    {
        return $this->processBody($this->email_body ?? '', $model);
    }
    
    /**
     * Renderizar email com layout profissional 
     * MESMA LÓGICA EXATA DO EmailTemplate->render() usado no RegisterWizard
     * Retorna array com subject e body_html formatado
     */
    public function renderEmail($model): array
    {
        $subject = $this->getEmailSubject($model);
        $body = $this->getEmailBody($model);
        
        // Converter quebras de linha para HTML
        $bodyHtml = nl2br(htmlspecialchars($body));
        
        // Se o body não contém doctype, envolve no layout usando arquivo temporário
        if (strpos($bodyHtml, '<!DOCTYPE') === false && strpos($bodyHtml, '@extends') === false) {
            $bodyHtml = $this->wrapInLayoutWithTempFile($bodyHtml, $subject);
        }
        
        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
        ];
    }
    
    /**
     * Envolver conteúdo no layout padrão criando arquivo temporário
     * MESMA TÉCNICA DO EmailTemplate->wrapInLayout()
     */
    protected function wrapInLayoutWithTempFile(string $content, string $subject): string
    {
        // Criar um arquivo temporário com o conteúdo (mesma lógica do EmailTemplate)
        $tempViewName = 'emails.temp_' . md5($content . time());
        $tempViewPath = resource_path('views/emails/temp_' . md5($content . time()) . '.blade.php');
        
        // Criar conteúdo do template temporário que estende o layout
        $tempContent = "@extends('emails.layout')\n\n@section('content')\n" . $content . "\n@endsection";
        
        // Salvar arquivo temporário
        file_put_contents($tempViewPath, $tempContent);
        
        try {
            // Renderizar usando Blade (renderiza @extends, @if, etc)
            $rendered = view($tempViewName, [
                'subject' => $subject,
            ])->render();
            
            // Deletar arquivo temporário
            @unlink($tempViewPath);
            
            return $rendered;
        } catch (\Exception $e) {
            // Deletar arquivo temporário em caso de erro
            @unlink($tempViewPath);
            
            \Log::error('Failed to wrap email in layout with temp file', [
                'error' => $e->getMessage()
            ]);
            
            // Fallback: renderização manual
            return $this->manualWrapInLayout($content, $subject);
        }
    }
    
    /**
     * Fallback: envolver manualmente no layout (mesma lógica do EmailTemplate)
     */
    protected function manualWrapInLayout(string $content, string $subject): string
    {
        $layout = file_get_contents(resource_path('views/emails/layout.blade.php'));
        
        // Substituir @yield('content')
        $layout = str_replace("@yield('content')", $content, $layout);
        
        // Processar @if(app_logo())
        if (app_logo()) {
            $logoUrl = url(app_logo());
            $appName = config('app.name', 'SOS ERP');
            $logoHtml = '<img src="' . $logoUrl . '" alt="' . $appName . '" style="max-height: 80px; max-width: 200px; width: auto; height: auto; display: block; margin: 0 auto;">';
            
            $layout = preg_replace('/@if\(app_logo\(\)\).*?@else.*?@endif/s', $logoHtml, $layout);
        } else {
            $layout = preg_replace('/@if\(app_logo\(\)\).*?@else(.*?)@endif/s', '$1', $layout);
        }
        
        // Substituir {{ $subject }}
        $layout = str_replace('{{ $subject ?? config(\'app.name\') }}', $subject, $layout);
        $layout = str_replace("{{ config('app.name', 'SOS ERP') }}", config('app.name', 'SOS ERP'), $layout);
        $layout = str_replace("{{ date('Y') }}", date('Y'), $layout);
        $layout = str_replace("{{ config('app.url') }}", config('app.url'), $layout);
        
        return $layout;
    }

    /**
     * Obter valor aninhado do modelo usando notação de ponto
     */
    protected function getNestedValue($model, string $path)
    {
        $parts = explode('.', $path);
        $value = $model;
        
        foreach ($parts as $part) {
            if (is_object($value) && isset($value->{$part})) {
                $value = $value->{$part};
            } elseif (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }
        
        // Formatar datas automaticamente para dd/mm/yyyy
        if ($value instanceof \Carbon\Carbon || $value instanceof \DateTime) {
            return $value->format('d/m/Y');
        }
        
        return $value;
    }

    /**
     * Verificar se as condições são atendidas
     */
    public function meetsConditions($model): bool
    {
        if (!$this->conditions || empty($this->conditions)) {
            return true;
        }
        
        foreach ($this->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;
            
            if (!$field) continue;
            
            $modelValue = $this->getNestedValue($model, $field);
            
            switch ($operator) {
                case '=':
                    if ($modelValue != $value) return false;
                    break;
                case '!=':
                    if ($modelValue == $value) return false;
                    break;
                case '>':
                    if ($modelValue <= $value) return false;
                    break;
                case '<':
                    if ($modelValue >= $value) return false;
                    break;
            }
        }
        
        return true;
    }

    /**
     * Tenant relationship
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
