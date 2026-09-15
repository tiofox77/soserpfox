<?php

namespace App\Models\Workshop;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UMA VIATURA DE CORTESIA da oficina (OF-17).
 *
 * O estado gravado é o da oficina (disponível, em manutenção, inactiva);
 * «emprestada» lê-se do empréstimo por devolver.
 */
class CourtesyCar extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'workshop_courtesy_cars';

    public const ESTADOS = [
        'disponivel' => 'Disponível',
        'manutencao' => 'Em manutenção',
        'inactiva' => 'Inactiva',
    ];

    protected $fillable = [
        'tenant_id', 'plate', 'brand', 'model', 'color', 'year', 'fuel_type', 'mileage', 'fuel_level', 'status', 'insurance_expiry', 'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'mileage' => 'integer',
        'fuel_level' => 'integer',
        'insurance_expiry' => 'date',
    ];

    public function loans()
    {
        return $this->hasMany(CourtesyLoan::class, 'courtesy_car_id');
    }

    public function openLoan()
    {
        return $this->hasOne(CourtesyLoan::class, 'courtesy_car_id')->whereNull('returned_at')->latestOfMany('out_at');
    }
}
