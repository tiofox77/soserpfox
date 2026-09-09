<?php

namespace App\Models\Hotel;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant;

class MaintenanceOrder extends Model
{
    use BelongsToTenant;

    protected $table = 'hotel_maintenance_orders';

    /*
     * AS COLUNAS SÃO AS DA TABELA.
     *
     * Esta lista declarava nomes que a tabela NÃO TEM — `actual_cost`,
     * `actual_time`, `resolution_notes`, `images` — mais o `type`, o
     * `estimated_cost` e o `estimated_time`, que não existiam em lado nenhum.
     * O formulário mandava-os todos e o insert respondia «Unknown column
     * 'type'»: o ecrã de manutenção NUNCA conseguiu gravar uma ordem.
     *
     * Os três que faziam falta a sério ganharam coluna (ver a migração de
     * 2026-09-09); os outros passam a chamar-se como a tabela sempre lhes
     * chamou — `resolution` e `cost`. Colunas gémeas eram ficar com duas
     * verdades sobre quanto custou o arranjo.
     */
    protected $fillable = [
        'tenant_id',
        'order_number',
        'room_id',
        'reported_by',
        'assigned_to',
        'type',
        'priority',
        'category',
        'title',
        'description',
        'location',
        'status',
        'estimated_cost',
        'estimated_time',
        'cost',
        'scheduled_date',
        'started_at',
        'completed_at',
        'resolution',
        'parts_used',
        'photos_before',
        'photos_after',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'cost' => 'decimal:2',
        'estimated_time' => 'integer',
        // A coluna é DATE e não datetime: o agendamento é ao dia.
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'parts_used' => 'array',
        'photos_before' => 'array',
        'photos_after' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber($order->tenant_id);
            }
        });
    }

    public static function generateOrderNumber($tenantId): string
    {
        $prefix = 'MNT';
        $date = now()->format('ymd');
        $count = self::where('tenant_id', $tenantId)
            ->whereDate('created_at', today())
            ->count() + 1;
        
        return $prefix . $date . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to');
    }

    // Helpers
    public function getTypeLabel(): string
    {
        return match($this->type) {
            'preventive' => 'Preventiva',
            'corrective' => 'Corretiva',
            'emergency' => 'Emergência',
            default => ucfirst($this->type),
        };
    }

    public function getPriorityLabel(): string
    {
        return match($this->priority) {
            'low' => 'Baixa',
            'normal' => 'Normal',
            'high' => 'Alta',
            'urgent' => 'Urgente',
            default => ucfirst($this->priority),
        };
    }

    public function getCategoryLabel(): string
    {
        return match($this->category) {
            'electrical' => 'Elétrica',
            'plumbing' => 'Canalização',
            'hvac' => 'Ar Condicionado',
            'furniture' => 'Mobiliário',
            'appliance' => 'Equipamentos',
            'structural' => 'Estrutural',
            'other' => 'Outro',
            default => ucfirst($this->category),
        };
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Pendente',
            'in_progress' => 'Em Progresso',
            'waiting_parts' => 'Aguarda Peças',
            'completed' => 'Concluída',
            'cancelled' => 'Cancelada',
            default => ucfirst($this->status),
        };
    }

    public function getPriorityColor(): string
    {
        return match($this->priority) {
            'low' => 'gray',
            'normal' => 'blue',
            'high' => 'amber',
            'urgent' => 'red',
            default => 'gray',
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'in_progress' => 'blue',
            'waiting_parts' => 'purple',
            'completed' => 'green',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }
}
