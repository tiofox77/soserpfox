<?php

namespace App\Models\Workshop;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * UM EMPRÉSTIMO DE VIATURA DE CORTESIA (OF-17): a quem, ligado a que ordem, e
 * como saiu e voltou.
 */
class CourtesyLoan extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_courtesy_loans';

    protected $fillable = [
        'tenant_id', 'courtesy_car_id', 'work_order_id', 'driver_name', 'driver_phone', 'driver_licence', 'out_at', 'expected_return_at',
        'returned_at', 'mileage_out', 'mileage_in', 'fuel_out', 'fuel_in', 'notes_out', 'damages_in', 'user_id', 'returned_by',
    ];

    protected $casts = [
        'out_at' => 'datetime',
        'expected_return_at' => 'datetime',
        'returned_at' => 'datetime',
        'mileage_out' => 'integer',
        'mileage_in' => 'integer',
        'fuel_out' => 'integer',
        'fuel_in' => 'integer',
    ];

    public function car()
    {
        return $this->belongsTo(CourtesyCar::class, 'courtesy_car_id');
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function atrasado(): bool
    {
        return ! $this->returned_at && $this->expected_return_at && $this->expected_return_at->isPast();
    }
}
