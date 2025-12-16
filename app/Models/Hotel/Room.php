<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hotel_rooms';

    protected $fillable = [
        'tenant_id',
        'room_type_id',
        'number',
        'floor',
        'status',
        'housekeeping_status',
        'notes',
        'features',
        'is_active',
    ];

    const HOUSEKEEPING_STATUSES = [
        'clean' => 'Limpo',
        'dirty' => 'Sujo',
        'in_progress' => 'Em Limpeza',
        'inspecting' => 'Inspecao',
        'out_of_order' => 'Fora de Servico',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    const STATUS_AVAILABLE = 'available';
    const STATUS_OCCUPIED = 'occupied';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_CLEANING = 'cleaning';
    const STATUS_RESERVED = 'reserved';

    const STATUSES = [
        self::STATUS_AVAILABLE => 'Disponível',
        self::STATUS_OCCUPIED => 'Ocupado',
        self::STATUS_MAINTENANCE => 'Manutenção',
        self::STATUS_CLEANING => 'Limpeza',
        self::STATUS_RESERVED => 'Reservado',
    ];

    // Boot
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $model->tenant_id = activeTenantId();
            }
        });
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? activeTenantId());
    }

    // Relationships
    public function roomType()
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'room_id');
    }

    public function currentReservation()
    {
        return $this->hasOne(Reservation::class, 'room_id')
            ->where('status', 'checked_in')
            ->latest();
    }

    public function housekeepingTasks()
    {
        return $this->hasMany(HousekeepingTask::class, 'room_id');
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_AVAILABLE => 'green',
            self::STATUS_OCCUPIED => 'red',
            self::STATUS_MAINTENANCE => 'yellow',
            self::STATUS_CLEANING => 'blue',
            self::STATUS_RESERVED => 'purple',
            default => 'gray',
        };
    }

    public function getFullNameAttribute()
    {
        return "Quarto {$this->number}" . ($this->floor ? " - {$this->floor}º Andar" : '');
    }

    // Methods
    public function isAvailableForDates($checkIn, $checkOut, $excludeReservationId = null)
    {
        $query = $this->reservations()
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in_date', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                    ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                        $q2->where('check_in_date', '<=', $checkIn)
                            ->where('check_out_date', '>=', $checkOut);
                    });
            });

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        return $query->count() === 0;
    }
}
