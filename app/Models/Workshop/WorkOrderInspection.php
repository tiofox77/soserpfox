<?php

namespace App\Models\Workshop;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A INSPECÇÃO DE UMA ORDEM — os pontos do modelo, cada um com o seu semáforo
 * (15/09/2026, OF-02). `results` é a lista de pontos copiada do modelo:
 * `{seccao, ponto, estado: ok|atencao|urgente|na|null, nota, foto}`.
 */
class WorkOrderInspection extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_work_order_inspections';

    protected $fillable = ['tenant_id', 'work_order_id', 'template_id', 'name', 'kind', 'results', 'completed_at', 'user_id'];

    protected $casts = ['results' => 'array', 'completed_at' => 'datetime'];

    public const ESTADOS = [
        'ok' => 'OK',
        'atencao' => 'Atenção',
        'urgente' => 'Urgente',
        'na' => 'Não se aplica',
    ];

    protected static function booted(): void
    {
        // As fotografias dos pontos vão com a inspecção.
        static::deleted(function (WorkOrderInspection $i) {
            foreach ($i->results ?? [] as $r) {
                if (! empty($r['foto'])) {
                    Storage::disk('public')->delete($r['foto']);
                }
            }
        });
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Quantos pontos em cada estado (e quantos por ver). */
    public function contas(): array
    {
        $contas = ['ok' => 0, 'atencao' => 0, 'urgente' => 0, 'na' => 0, 'por_ver' => 0];

        foreach ($this->results ?? [] as $r) {
            $contas[isset($r['estado'], $contas[$r['estado']]) ? $r['estado'] : 'por_ver']++;
        }

        return $contas;
    }
}
