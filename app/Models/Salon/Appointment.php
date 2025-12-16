<?php

namespace App\Models\Salon;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'salon_appointments';

    protected $fillable = [
        'tenant_id',
        'appointment_number',
        'client_id',
        'professional_id',
        'date',
        'start_time',
        'end_time',
        'total_duration',
        'status',
        'source',
        'subtotal',
        'discount',
        'total',
        'paid_amount',
        'payment_status',
        'payment_method',
        'notes',
        'internal_notes',
        'reminder_sent',
        'confirmed_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'reminder_sent' => 'boolean',
        'confirmed_at' => 'datetime',
        'arrived_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    const STATUSES = [
        'scheduled' => 'Agendado',
        'confirmed' => 'Confirmado',
        'arrived' => 'Aguardando',
        'in_progress' => 'Em Atendimento',
        'completed' => 'Concluído',
        'cancelled' => 'Cancelado',
        'no_show' => 'Não Compareceu',
    ];

    const STATUS_COLORS = [
        'scheduled' => 'yellow',
        'confirmed' => 'blue',
        'arrived' => 'purple',
        'in_progress' => 'indigo',
        'completed' => 'green',
        'cancelled' => 'red',
        'no_show' => 'gray',
    ];

    const SOURCES = [
        'walk_in' => 'Presencial',
        'phone' => 'Telefone',
        'whatsapp' => 'WhatsApp',
        'website' => 'Website',
        'app' => 'Aplicação',
        'instagram' => 'Instagram',
        'system' => 'Sistema',
        'other' => 'Outro',
    ];

    const SOURCE_COLORS = [
        'walk_in' => 'gray',
        'phone' => 'blue',
        'whatsapp' => 'green',
        'website' => 'purple',
        'app' => 'indigo',
        'instagram' => 'pink',
        'system' => 'cyan',
        'other' => 'gray',
    ];

    const SOURCE_ICONS = [
        'walk_in' => 'fas fa-walking',
        'phone' => 'fas fa-phone',
        'whatsapp' => 'fab fa-whatsapp',
        'website' => 'fas fa-globe',
        'app' => 'fas fa-mobile-alt',
        'instagram' => 'fab fa-instagram',
        'system' => 'fas fa-desktop',
        'other' => 'fas fa-ellipsis-h',
    ];

    // Fontes consideradas "online" (agendamento pelo cliente)
    const ONLINE_SOURCES = ['website', 'app', 'instagram'];
    
    // Fontes consideradas "sistema" (agendamento interno)
    const SYSTEM_SOURCES = ['walk_in', 'phone', 'whatsapp', 'system', 'other'];

    const PAYMENT_STATUSES = [
        'pending' => 'Pendente',
        'partial' => 'Parcial',
        'paid' => 'Pago',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = activeTenantId();
            }
            if (empty($model->appointment_number)) {
                $model->appointment_number = 'AGD-' . date('Ymd') . '-' . str_pad(
                    self::where('tenant_id', $model->tenant_id)->whereDate('created_at', today())->count() + 1,
                    4, '0', STR_PAD_LEFT
                );
            }
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::saving(function ($model) {
            // Update payment status
            if ($model->paid_amount >= $model->total && $model->total > 0) {
                $model->payment_status = 'paid';
            } elseif ($model->paid_amount > 0) {
                $model->payment_status = 'partial';
            } else {
                $model->payment_status = 'pending';
            }
        });
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getSourceLabelAttribute()
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    public function getSourceColorAttribute()
    {
        return self::SOURCE_COLORS[$this->source] ?? 'gray';
    }

    public function getSourceIconAttribute()
    {
        return self::SOURCE_ICONS[$this->source] ?? 'fas fa-calendar';
    }

    /**
     * Verifica se o agendamento foi feito online (pelo cliente)
     */
    public function getIsOnlineBookingAttribute(): bool
    {
        return in_array($this->source, self::ONLINE_SOURCES);
    }

    /**
     * Verifica se o agendamento foi feito pelo sistema (internamente)
     */
    public function getIsSystemBookingAttribute(): bool
    {
        return in_array($this->source, self::SYSTEM_SOURCES);
    }

    /**
     * Retorna o tipo de agendamento (online ou sistema)
     */
    public function getBookingTypeAttribute(): string
    {
        return $this->is_online_booking ? 'online' : 'system';
    }

    /**
     * Retorna o label do tipo de agendamento
     */
    public function getBookingTypeLabelAttribute(): string
    {
        return $this->is_online_booking ? 'Online' : 'Sistema';
    }

    public function getPaymentStatusLabelAttribute()
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status;
    }

    public function getBalanceDueAttribute()
    {
        return max(0, $this->total - $this->paid_amount);
    }

    public function getStartDateTimeAttribute()
    {
        return Carbon::parse($this->date->format('Y-m-d') . ' ' . Carbon::parse($this->start_time)->format('H:i'));
    }

    public function getEndDateTimeAttribute()
    {
        return Carbon::parse($this->date->format('Y-m-d') . ' ' . Carbon::parse($this->end_time)->format('H:i'));
    }

    /**
     * Tempo real de atendimento em minutos
     */
    public function getActualDurationAttribute()
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        return $this->started_at->diffInMinutes($this->completed_at);
    }

    /**
     * Tempo real formatado (ex: 1h 30min)
     */
    public function getActualDurationFormattedAttribute()
    {
        $minutes = $this->actual_duration;
        if ($minutes === null) return '-';
        
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . $mins . 'min';
        }
        return $mins . 'min';
    }

    /**
     * Diferença entre tempo previsto e real (positivo = demorou mais)
     */
    public function getTimeDifferenceAttribute()
    {
        if ($this->actual_duration === null || !$this->total_duration) {
            return null;
        }
        return $this->actual_duration - $this->total_duration;
    }

    /**
     * Tempo de espera do cliente (chegou até iniciar)
     */
    public function getWaitTimeAttribute()
    {
        if (!$this->arrived_at || !$this->started_at) {
            return null;
        }
        return $this->arrived_at->diffInMinutes($this->started_at);
    }

    // Methods
    public function confirm()
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function markArrived()
    {
        $this->update([
            'status' => 'arrived',
            'arrived_at' => now(),
        ]);
    }

    public function start()
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function complete($paidAmount = null, $paymentMethod = null)
    {
        $data = [
            'status' => 'completed',
            'completed_at' => now(),
        ];

        if ($paidAmount !== null) {
            $data['paid_amount'] = $paidAmount;
        }
        if ($paymentMethod) {
            $data['payment_method'] = $paymentMethod;
        }

        $this->update($data);

        // Update client stats
        $this->client->incrementVisit($this->total);
    }

    public function cancel($reason = null)
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function markNoShow()
    {
        $this->update(['status' => 'no_show']);
    }

    public function calculateTotal()
    {
        $subtotal = $this->services()->sum('total');
        $this->update([
            'subtotal' => $subtotal,
            'total' => $subtotal - $this->discount,
        ]);
    }

    // Scopes
    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? activeTenantId());
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', today())
            ->whereIn('status', ['scheduled', 'confirmed']);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['scheduled', 'confirmed']);
    }

    public function scopeOnlineBooking($query)
    {
        return $query->whereIn('source', self::ONLINE_SOURCES);
    }

    public function scopeSystemBooking($query)
    {
        return $query->whereIn('source', self::SYSTEM_SOURCES);
    }

    public function scopeBySource($query, $source)
    {
        if ($source === 'online') {
            return $query->onlineBooking();
        } elseif ($source === 'system') {
            return $query->systemBooking();
        } elseif ($source) {
            return $query->where('source', $source);
        }
        return $query;
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Relationships
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }

    public function services()
    {
        return $this->hasMany(AppointmentService::class, 'appointment_id');
    }

    public function products()
    {
        return $this->hasMany(AppointmentProduct::class, 'appointment_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
