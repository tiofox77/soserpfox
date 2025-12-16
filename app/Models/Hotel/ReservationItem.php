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
        'description',
        'quantity',
        'unit_price',
        'total',
        'date',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    const TYPES = [
        'room' => 'Quarto',
        'extra_bed' => 'Cama Extra',
        'service' => 'Serviço',
        'minibar' => 'Minibar',
        'restaurant' => 'Restaurante',
        'laundry' => 'Lavandaria',
        'other' => 'Outro',
    ];

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
