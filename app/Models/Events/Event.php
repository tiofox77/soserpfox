<?php

namespace App\Models\Events;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'events_events';

    protected $fillable = [
        'tenant_id', 'client_id', 'venue_id', 'event_number', 'name', 'description',
        'type', 'start_date', 'end_date', 'setup_start', 'teardown_end',
        'expected_attendees', 'total_value', 'status', 'phase', 'notes', 'responsible_user_id',
        'confirmed_at', 'pre_production_started_at', 'setup_started_at', 
        'operation_started_at', 'teardown_started_at', 'completed_at',
        'checklist_progress', 'calendar_color',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'setup_start' => 'datetime',
        'teardown_end' => 'datetime',
        'confirmed_at' => 'datetime',
        'pre_production_started_at' => 'datetime',
        'setup_started_at' => 'datetime',
        'operation_started_at' => 'datetime',
        'teardown_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_value' => 'decimal:2',
        'checklist_progress' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (!$event->event_number) {
                $event->event_number = self::generateEventNumber();
            }
        });
    }

    public static function generateEventNumber()
    {
        $year = date('Y');
        $lastEvent = self::where('tenant_id', activeTenantId())
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastEvent ? (intval(substr($lastEvent->event_number, -4)) + 1) : 1;

        return 'EVT' . $year . '/' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    // Relacionamentos
    public function client() { return $this->belongsTo(Client::class); }
    public function venue() { return $this->belongsTo(Venue::class); }
    public function responsible() { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function equipment() { return $this->hasMany(EventEquipment::class); }
    public function staff() { return $this->hasMany(EventStaff::class); }
    public function checklists() { return $this->hasMany(Checklist::class); }

    // Accessors
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'orcamento' => 'gray',
            'confirmado' => 'blue',
            'em_montagem' => 'yellow',
            'em_andamento' => 'green',
            'concluido' => 'green',
            'cancelado' => 'red',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'orcamento' => 'Orçamento',
            'confirmado' => 'Confirmado',
            'em_montagem' => 'Em Montagem',
            'em_andamento' => 'Em Andamento',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado',
            default => $this->status,
        };
    }

    public function getPhaseLabelAttribute()
    {
        return match($this->phase) {
            'planejamento' => 'Planejamento',
            'pre_producao' => 'Pré-Produção',
            'montagem' => 'Montagem',
            'operacao' => 'Operação',
            'desmontagem' => 'Desmontagem',
            'concluido' => 'Concluído',
            default => $this->phase,
        };
    }

    public function getPhaseColorAttribute()
    {
        return match($this->phase) {
            'planejamento' => 'indigo',
            'pre_producao' => 'blue',
            'montagem' => 'yellow',
            'operacao' => 'green',
            'desmontagem' => 'orange',
            'concluido' => 'gray',
            default => 'gray',
        };
    }

    public function getPhaseIconAttribute()
    {
        return match($this->phase) {
            'planejamento' => 'fas fa-clipboard-list',
            'pre_producao' => 'fas fa-tasks',
            'montagem' => 'fas fa-hammer',
            'operacao' => 'fas fa-play-circle',
            'desmontagem' => 'fas fa-tools',
            'concluido' => 'fas fa-check-circle',
            default => 'fas fa-circle',
        };
    }

    /**
     * Avança para a próxima fase do evento
     */
    public function advanceToNextPhase()
    {
        $phases = ['planejamento', 'pre_producao', 'montagem', 'operacao', 'desmontagem', 'concluido'];
        $currentIndex = array_search($this->phase, $phases);
        
        if ($currentIndex !== false && $currentIndex < count($phases) - 1) {
            $nextPhase = $phases[$currentIndex + 1];
            
            // Atualizar fase e timestamp correspondente
            $this->phase = $nextPhase;
            
            match($nextPhase) {
                'pre_producao' => $this->pre_production_started_at = now(),
                'montagem' => $this->setup_started_at = now(),
                'operacao' => $this->operation_started_at = now(),
                'desmontagem' => $this->teardown_started_at = now(),
                'concluido' => $this->completed_at = now(),
                default => null,
            };
            
            $this->save();
            
            // Criar checklist padrão para a nova fase
            $this->createDefaultChecklistForPhase($nextPhase);
            
            return true;
        }
        
        return false;
    }

    /**
     * Cria checklist padrão baseado na fase
     */
    public function createDefaultChecklistForPhase($phase)
    {
        $templates = $this->getChecklistTemplates($phase);
        
        foreach ($templates as $order => $task) {
            $this->checklists()->create([
                'task' => $task['task'],
                'description' => $task['description'] ?? null,
                'phase' => $phase,
                'is_required' => $task['required'] ?? false,
                'status' => 'pendente',
                'order' => $order,
            ]);
        }
    }

    /**
     * Templates de checklist por fase
     */
    private function getChecklistTemplates($phase)
    {
        return match($phase) {
            'planejamento' => [
                ['task' => 'Reunião inicial com cliente', 'required' => true],
                ['task' => 'Definir briefing do evento', 'required' => true],
                ['task' => 'Confirmar data e horário', 'required' => true],
                ['task' => 'Definir orçamento', 'required' => true],
                ['task' => 'Contrato assinado', 'required' => true],
            ],
            'pre_producao' => [
                ['task' => 'Visita técnica ao local', 'required' => true],
                ['task' => 'Elaborar planta técnica', 'required' => false],
                ['task' => 'Listar equipamentos necessários', 'required' => true],
                ['task' => 'Reservar equipamentos', 'required' => true],
                ['task' => 'Alocar equipe técnica', 'required' => true],
                ['task' => 'Confirmar fornecedores', 'required' => false],
                ['task' => 'Criar cronograma de montagem', 'required' => true],
            ],
            'montagem' => [
                ['task' => 'Carregar equipamentos no veículo', 'required' => true],
                ['task' => 'Transporte até o local', 'required' => true],
                ['task' => 'Descarregar equipamentos', 'required' => true],
                ['task' => 'Montar estrutura física', 'required' => true],
                ['task' => 'Instalar equipamentos de áudio', 'required' => false],
                ['task' => 'Instalar equipamentos de vídeo', 'required' => false],
                ['task' => 'Instalar iluminação', 'required' => false],
                ['task' => 'Configurar sistema de streaming', 'required' => false],
                ['task' => 'Testes de som e imagem', 'required' => true],
                ['task' => 'Soundcheck final', 'required' => true],
            ],
            'operacao' => [
                ['task' => 'Briefing com equipe operacional', 'required' => true],
                ['task' => 'Checklist de segurança', 'required' => true],
                ['task' => 'Sistema em standby', 'required' => true],
                ['task' => 'Monitoramento contínuo', 'required' => true],
                ['task' => 'Registro de intercorrências', 'required' => false],
            ],
            'desmontagem' => [
                ['task' => 'Desligar todos os sistemas', 'required' => true],
                ['task' => 'Desmontar iluminação', 'required' => false],
                ['task' => 'Desmontar áudio', 'required' => false],
                ['task' => 'Desmontar vídeo', 'required' => false],
                ['task' => 'Recolher cabos e acessórios', 'required' => true],
                ['task' => 'Embalar equipamentos', 'required' => true],
                ['task' => 'Carregar veículo', 'required' => true],
                ['task' => 'Limpeza do local', 'required' => true],
                ['task' => 'Vistoria final com responsável', 'required' => true],
            ],
            default => [],
        };
    }

    /**
     * Calcula progresso do checklist
     */
    public function updateChecklistProgress()
    {
        $total = $this->checklists()->count();
        if ($total === 0) {
            $this->checklist_progress = 0;
            $this->save();
            return;
        }
        
        $completed = $this->checklists()->where('status', 'concluido')->count();
        $this->checklist_progress = round(($completed / $total) * 100);
        $this->save();
    }

    /**
     * Verifica se pode avançar para próxima fase
     */
    public function canAdvancePhase()
    {
        // Verifica se todas as tarefas obrigatórias da fase atual estão concluídas
        $requiredTasks = $this->checklists()
            ->where('phase', $this->phase)
            ->where('is_required', true)
            ->where('status', '!=', 'concluido')
            ->count();
        
        return $requiredTasks === 0;
    }

    /**
     * Cor para o calendário (com fallback)
     */
    public function getCalendarColorAttribute($value)
    {
        if ($value) {
            return $value;
        }
        
        // Cor padrão baseada no status
        return match($this->status) {
            'orcamento' => '#6B7280',
            'confirmado' => '#3B82F6',
            'em_montagem' => '#F59E0B',
            'em_andamento' => '#10B981',
            'concluido' => '#6B7280',
            'cancelado' => '#EF4444',
            default => '#6B7280',
        };
    }
}
