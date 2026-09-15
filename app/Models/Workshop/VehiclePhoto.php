<?php

namespace App\Models\Workshop;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * UMA FOTOGRAFIA DA VIATURA — antes, durante, depois ou de um dano (15/09/2026).
 *
 * O «antes» e o «depois» emparelham-se pelo SERVIÇO e pela ZONA (e pela folha
 * de obra, quando a há): a porta dianteira esquerda amolgada ao lado da mesma
 * porta pintada. O ficheiro vive no disco público e apaga-se com a linha.
 */
class VehiclePhoto extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_vehicle_photos';

    protected $fillable = [
        'tenant_id', 'vehicle_id', 'work_order_id', 'user_id', 'phase', 'service', 'zone', 'description',
        'file_path', 'original_filename', 'mime_type', 'file_size', 'width', 'height',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    /** A fase do trabalho em que se tirou a fotografia. */
    public const FASES = [
        'antes' => 'Antes',
        'durante' => 'Durante',
        'depois' => 'Depois',
        'dano' => 'Dano',
    ];

    /** O serviço — bate-chapa e pintura primeiro, que é para eles que isto serve. */
    public const SERVICOS = [
        'bate_chapa' => 'Bate-chapa',
        'pintura' => 'Pintura',
        'polimento' => 'Polimento',
        'mecanica' => 'Mecânica',
        'electrica' => 'Eléctrica',
        'vidros' => 'Vidros',
        'estofos' => 'Estofos / interior',
        'outros' => 'Outros',
    ];

    /** A zona do carro — é por ela que o antes e o depois se põem lado a lado. */
    public const ZONAS = [
        'frente' => 'Frente',
        'traseira' => 'Traseira',
        'lateral_esquerda' => 'Lateral esquerda',
        'lateral_direita' => 'Lateral direita',
        'capo' => 'Capô',
        'tejadilho' => 'Tejadilho',
        'portas' => 'Portas',
        'rodas' => 'Rodas / jantes',
        'interior' => 'Interior',
        'motor' => 'Motor',
        'chassis' => 'Chassis / fundo',
        'geral' => 'Vista geral',
    ];

    protected static function booted(): void
    {
        static::deleted(function (VehiclePhoto $foto) {
            if ($foto->file_path && Storage::disk('public')->exists($foto->file_path)) {
                Storage::disk('public')->delete($foto->file_path);
            }
        });
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /** As três listas, traduzidas, no formato das escolhas do ecrã. */
    public static function listas(): array
    {
        $escolhas = fn (array $l) => collect($l)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values()->all();

        return [
            'fases' => $escolhas(self::FASES),
            'servicos' => $escolhas(self::SERVICOS),
            'zonas' => $escolhas(self::ZONAS),
        ];
    }
}
