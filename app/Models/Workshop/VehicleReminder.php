<?php

namespace App\Models\Workshop;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * UM CONTACTO DE LEMBRETE (OF-11): a viatura, de que vencimento, por onde e quem.
 *
 * `due_key` diz A QUE vencimento o contacto respondeu — a revisão dos 60 000 km
 * ou o seguro que caduca a 20/09. Quando a revisão se faz ou o seguro se renova,
 * o vencimento muda e a viatura volta a poder ser chamada.
 */
class VehicleReminder extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_reminders';

    public const TIPOS = [
        'revisao' => 'Revisão',
        'seguro' => 'Seguro',
        'inspeccao' => 'Inspecção',
        'livrete' => 'Livrete',
    ];

    public const CANAIS = [
        'sms' => 'SMS',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'telefone' => 'Telefone',
        'nota' => 'Nota',
    ];

    protected $fillable = ['tenant_id', 'vehicle_id', 'kind', 'due_key', 'channel', 'note', 'user_id'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
