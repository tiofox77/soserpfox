<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hotel_reservations';

    protected $fillable = [
        'tenant_id',
        'reservation_number',
        'guest_id',
        'client_id',
        'room_id',
        'room_type_id',
        'check_in_date',
        'check_out_date',
        'check_in_time',
        'check_out_time',
        'actual_check_in',
        'actual_check_out',
        'adults',
        'children',
        'extra_beds',
        'status',
        'source',
        'room_rate',
        'nights',
        'subtotal',
        'extras_total',
        'discount',
        'tax',
        'total',
        'paid_amount',
        'payment_status',
        'payment_method',
        'invoice_id',
        'special_requests',
        'internal_notes',
        'confirmation_code',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
        'cancelled_at' => 'datetime',
        'room_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'extras_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CHECKED_IN = 'checked_in';
    const STATUS_CHECKED_OUT = 'checked_out';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW = 'no_show';

    const STATUSES = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_CONFIRMED => 'Confirmada',
        self::STATUS_CHECKED_IN => 'Check-in',
        self::STATUS_CHECKED_OUT => 'Check-out',
        self::STATUS_CANCELLED => 'Cancelada',
        self::STATUS_NO_SHOW => 'No-show',
    ];

    const SOURCES = [
        'direct' => 'Directo',
        'website' => 'Website',
        'booking' => 'Booking.com',
        'airbnb' => 'Airbnb',
        'phone' => 'Telefone',
        'email' => 'Email',
        'walk_in' => 'Walk-in',
        'other' => 'Outro',
    ];

    const PAYMENT_STATUSES = [
        'pending' => 'Pendente',
        'partial' => 'Parcial',
        'paid' => 'Pago',
        'refunded' => 'Reembolsado',
    ];

    // Boot
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $model->tenant_id = activeTenantId();
            }
            if (!$model->reservation_number) {
                $model->reservation_number = $model->generateReservationNumber();
            }
            if (!$model->confirmation_code) {
                $model->confirmation_code = strtoupper(substr(md5(uniqid()), 0, 6));
            }
            if (!$model->created_by) {
                $model->created_by = auth()->id();
            }
            
            // Calcular noites
            if ($model->check_in_date && $model->check_out_date) {
                $model->nights = Carbon::parse($model->check_in_date)->diffInDays(Carbon::parse($model->check_out_date));
            }
        });

        static::saving(function ($model) {
            $model->calculateTotals();
        });
    }

    // Scopes
    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? activeTenantId());
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_NO_SHOW]);
    }

    public function scopeToday($query)
    {
        $today = now()->toDateString();
        return $query->where(function ($q) use ($today) {
            $q->where('check_in_date', $today)
                ->orWhere('check_out_date', $today);
        });
    }

    public function scopeCheckingInToday($query)
    {
        return $query->where('check_in_date', now()->toDateString())
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function scopeCheckingOutToday($query)
    {
        return $query->where('check_out_date', now()->toDateString())
            ->where('status', self::STATUS_CHECKED_IN);
    }

    public function scopeCurrentlyStaying($query)
    {
        return $query->where('status', self::STATUS_CHECKED_IN);
    }

    // Relationships
    public function guest()
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function invoice()
    {
        return $this->belongsTo(\App\Models\Invoicing\SalesInvoice::class, 'invoice_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    public function items()
    {
        return $this->hasMany(ReservationItem::class, 'reservation_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'cancelled_by');
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_CONFIRMED => 'blue',
            self::STATUS_CHECKED_IN => 'green',
            self::STATUS_CHECKED_OUT => 'gray',
            self::STATUS_CANCELLED => 'red',
            self::STATUS_NO_SHOW => 'orange',
            default => 'gray',
        };
    }

    public function getSourceLabelAttribute()
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    public function getPaymentStatusLabelAttribute()
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status;
    }

    public function getPaymentStatusColorAttribute()
    {
        return match($this->payment_status) {
            'pending' => 'yellow',
            'partial' => 'orange',
            'paid' => 'green',
            'refunded' => 'red',
            default => 'gray',
        };
    }

    public function getBalanceDueAttribute()
    {
        return $this->total - $this->paid_amount;
    }

    public function getFormattedTotalAttribute()
    {
        return number_format($this->total, 2, ',', '.') . ' Kz';
    }

    public function getStayDurationAttribute()
    {
        return $this->nights . ' ' . ($this->nights === 1 ? 'noite' : 'noites');
    }

    // Methods
    public function generateReservationNumber()
    {
        $prefix = 'RES';
        $year = now()->format('y');
        $month = now()->format('m');
        $count = static::where('tenant_id', $this->tenant_id ?? activeTenantId())
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;
        
        return "{$prefix}{$year}{$month}" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotals()
    {
        // Calcular subtotal (quarto × noites)
        $this->subtotal = $this->room_rate * $this->nights;
        
        // Calcular extras
        $this->extras_total = $this->items()->sum('total');
        
        // Calcular total antes de impostos
        $subtotalWithExtras = $this->subtotal + $this->extras_total - $this->discount;
        
        // Calcular imposto (14%)
        $this->tax = $subtotalWithExtras * 0.14;
        
        // Total final
        $this->total = $subtotalWithExtras + $this->tax;
        
        // Atualizar status de pagamento
        if ($this->paid_amount >= $this->total) {
            $this->payment_status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = 'partial';
        }
    }

    public function checkIn($roomId = null)
    {
        if ($roomId) {
            $this->room_id = $roomId;
        }
        
        $this->status = self::STATUS_CHECKED_IN;
        $this->actual_check_in = now();
        $this->save();

        // Atualizar status do quarto
        if ($this->room) {
            $this->room->update(['status' => Room::STATUS_OCCUPIED]);
        }

        // Incrementar estadias do hóspede/cliente
        $guestOrClient = $this->client ?? $this->guest;
        if ($guestOrClient && method_exists($guestOrClient, 'incrementStays')) {
            $guestOrClient->incrementStays();
        }
    }

    public function checkOut()
    {
        $this->status = self::STATUS_CHECKED_OUT;
        $this->actual_check_out = now();
        $this->save();

        // Atualizar status do quarto para limpeza
        if ($this->room) {
            $this->room->update(['status' => Room::STATUS_CLEANING]);
        }
    }

    public function cancel($reason = null, $userId = null)
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->cancelled_by = $userId ?? auth()->id();
        $this->cancellation_reason = $reason;
        $this->save();

        // Liberar quarto se estava reservado
        if ($this->room && $this->room->status === Room::STATUS_RESERVED) {
            $this->room->update(['status' => Room::STATUS_AVAILABLE]);
        }
    }

    public function confirm()
    {
        $this->status = self::STATUS_CONFIRMED;
        $this->save();
    }
}
