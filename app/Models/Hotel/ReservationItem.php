<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationItem extends Model
{
    use HasFactory;

    protected $table = 'hotel_reservation_items';

    protected $fillable = [
        'reservation_id',
        'type',
        'category',
        'description',
        'quantity',
        'unit_price',
        'total',
        'date',
        'charged_at',
        'charged_by',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'charged_at' => 'datetime',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    const TYPES = [
        'room' => 'Quarto',
        'extra_bed' => 'Cama Extra',
        'service' => 'Serviço',
        'minibar' => 'Minibar',
        'restaurant' => 'Restaurante',
        'room_service' => 'Room Service',
        'laundry' => 'Lavandaria',
        'transfer' => 'Transfer',
        'spa' => 'Spa',
        'other' => 'Outro',
    ];

    const CATEGORIES = [
        'minibar' => ['label' => 'Minibar', 'icon' => 'fa-wine-bottle', 'color' => 'red'],
        'room_service' => ['label' => 'Room Service', 'icon' => 'fa-utensils', 'color' => 'orange'],
        'restaurant' => ['label' => 'Restaurante', 'icon' => 'fa-concierge-bell', 'color' => 'amber'],
        'laundry' => ['label' => 'Lavandaria', 'icon' => 'fa-tshirt', 'color' => 'blue'],
        'transfer' => ['label' => 'Transfer', 'icon' => 'fa-shuttle-van', 'color' => 'indigo'],
        'spa' => ['label' => 'Spa & Wellness', 'icon' => 'fa-spa', 'color' => 'pink'],
        'telephone' => ['label' => 'Telefone', 'icon' => 'fa-phone', 'color' => 'gray'],
        'other' => ['label' => 'Outro', 'icon' => 'fa-tag', 'color' => 'slate'],
    ];

    public function getCategoryLabelAttribute()
    {
        return self::CATEGORIES[$this->category]['label'] ?? ($this->category ?? '—');
    }

    public function getCategoryIconAttribute()
    {
        return self::CATEGORIES[$this->category]['icon'] ?? 'fa-tag';
    }

    public function getCategoryColorAttribute()
    {
        return self::CATEGORIES[$this->category]['color'] ?? 'slate';
    }

    // Boot
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($model) {
            $model->total = $model->quantity * $model->unit_price;
        });

        static::saved(function ($model) {
            // Recalcular totais da reserva
            $model->reservation->calculateTotals();
            $model->reservation->saveQuietly();
        });
    }

    // Relationships
    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function chargedByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'charged_by');
    }

    // Accessors
    public function getTypeLabelAttribute()
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getFormattedTotalAttribute()
    {
        return number_format($this->total, 2, ',', '.') . ' Kz';
    }
}
