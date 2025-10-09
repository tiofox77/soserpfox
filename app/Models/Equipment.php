<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;
    
    protected $table = 'events_equipments_manager'; // Gerenciador de equipamentos

    protected $fillable = [
        'tenant_id',
        'name',
        'category_id',
        'serial_number',
        'location',
        'description',
        'status',
        'acquisition_date',
        'purchase_price',
        'current_value',
        'borrowed_to_client_id',
        'borrowed_to_technician_id',
        'borrow_date',
        'return_due_date',
        'actual_return_date',
        'rental_price_per_day',
        'last_maintenance_date',
        'next_maintenance_date',
        'maintenance_notes',
        'total_uses',
        'total_hours_used',
        'image_path',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'borrow_date' => 'date',
        'return_due_date' => 'date',
        'actual_return_date' => 'date',
        'last_maintenance_date' => 'date',
        'next_maintenance_date' => 'date',
        'purchase_price' => 'decimal:2',
        'current_value' => 'decimal:2',
        'rental_price_per_day' => 'decimal:2',
        'is_active' => 'boolean',
        'total_uses' => 'integer',
        'total_hours_used' => 'integer',
    ];

    // Relacionamentos
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'category_id');
    }

    public function borrowedToClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'borrowed_to_client_id');
    }

    public function borrowedToTechnician(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Events\Technician::class, 'borrowed_to_technician_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(EquipmentHistory::class);
    }

    public function sets(): BelongsToMany
    {
        return $this->belongsToMany(EquipmentSet::class, 'equipment_set_items')
            ->withPivot('quantity', 'notes')
            ->withTimestamps();
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Events\Event::class, 'event_equipment')
            ->withPivot('quantity', 'start_datetime', 'end_datetime', 'status', 'notes', 'assigned_by')
            ->withTimestamps();
    }

    public function activeEvents(): BelongsToMany
    {
        return $this->events()->wherePivot('status', 'em_uso');
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', 'disponivel')->where('is_active', true);
    }

    public function scopeInUse($query)
    {
        return $query->where('status', 'em_uso');
    }

    public function scopeBorrowed($query)
    {
        return $query->where('status', 'emprestado');
    }

    public function scopeNeedsMaintenance($query)
    {
        return $query->where('next_maintenance_date', '<=', now()->addDays(7));
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'emprestado')
                    ->where('return_due_date', '<', now())
                    ->whereNull('actual_return_date');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'disponivel' => 'Disponível',
            'reservado' => 'Reservado',
            'em_uso' => 'Em Uso',
            'avariado' => 'Avariado',
            'manutencao' => 'Em Manutenção',
            'emprestado' => 'Emprestado',
            'descartado' => 'Descartado',
            default => 'Desconhecido',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'disponivel' => '#10b981',    // green
            'reservado' => '#f59e0b',     // amber
            'em_uso' => '#3b82f6',        // blue
            'avariado' => '#ef4444',      // red
            'manutencao' => '#f97316',    // orange
            'emprestado' => '#8b5cf6',    // purple
            'descartado' => '#6b7280',    // gray
            default => '#6b7280',
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'emprestado' 
            && $this->return_due_date 
            && $this->return_due_date->isPast()
            && !$this->actual_return_date;
    }

    public function getDaysOverdueAttribute(): int
    {
        if (!$this->is_overdue) {
            return 0;
        }
        return now()->diffInDays($this->return_due_date);
    }

    // Métodos
    public function addToHistory(string $actionType, array $data = []): EquipmentHistory
    {
        return $this->history()->create([
            'tenant_id' => $this->tenant_id,
            'action_type' => $actionType,
            'user_id' => auth()->id(),
            'status_before' => $this->getOriginal('status'),
            'status_after' => $this->status,
            ...$data
        ]);
    }

    public function incrementUsage(int $hours = 0): void
    {
        $this->increment('total_uses');
        if ($hours > 0) {
            $this->increment('total_hours_used', $hours);
        }
    }

    // Atualizar status baseado em eventos ativos
    public function updateStatusFromEvents(): void
    {
        $activeEvent = $this->events()
            ->wherePivot('status', 'em_uso')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->first();

        if ($activeEvent && $this->status !== 'em_uso') {
            $this->update(['status' => 'em_uso']);
            $this->addToHistory('uso', [
                'event_id' => $activeEvent->id,
                'start_datetime' => $activeEvent->pivot->start_datetime,
                'notes' => "Em uso no evento: {$activeEvent->name}"
            ]);
        } elseif (!$activeEvent && $this->status === 'em_uso') {
            $this->update(['status' => 'disponivel']);
        }
    }

    // Verificar se está em uso em algum evento
    public function isInActiveEvent(): bool
    {
        return $this->events()
            ->wherePivot('status', 'em_uso')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->exists();
    }

    // QR Code
    public function getQrCodeUrlAttribute(): string
    {
        return route('events.equipment.scan', ['id' => $this->id]);
    }

    public function getQrCodeDataAttribute(): string
    {
        return json_encode([
            'id' => $this->id,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'category' => $this->category?->name ?? 'N/A',
            'location' => $this->location,
            'status' => $this->status,
            'tenant_id' => $this->tenant_id,
            'url' => $this->qr_code_url,
        ]);
    }
}
