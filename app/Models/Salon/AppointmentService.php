<?php

namespace App\Models\Salon;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentService extends Model
{
    use HasFactory;

    protected $table = 'salon_appointment_services';

    protected $fillable = [
        'appointment_id',
        'service_id',
        'professional_id',
        'duration',
        'price',
        'discount',
        'total',
        'commission',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'commission' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->total = $model->price - $model->discount;
        });

        static::saved(function ($model) {
            $model->appointment->calculateTotal();
        });

        static::deleted(function ($model) {
            $model->appointment->calculateTotal();
        });
    }

    // Relationships
    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }
}
