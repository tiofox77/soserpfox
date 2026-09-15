<?php

namespace App\Models\Workshop;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * UM PACOTE DE SERVIÇO — mão-de-obra e peças que entram juntas numa ordem (15/09/2026, OF-07).
 *
 * `lines`: `[{tipo: service|part, service_id, product_id, nome, quantidade, preco, desconto, horas}]`.
 * O total e as horas acompanham as linhas a cada gravação.
 */
class ServicePackage extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_packages';

    protected $fillable = ['tenant_id', 'name', 'description', 'lines', 'total', 'hours', 'times_used', 'is_active'];

    protected $casts = ['lines' => 'array', 'total' => 'decimal:2', 'hours' => 'decimal:2', 'times_used' => 'integer', 'is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (ServicePackage $p) {
            $linhas = collect($p->lines ?? []);
            $p->total = round($linhas->sum(fn ($l) => (float) $l['quantidade'] * (float) $l['preco'] * (1 - (float) ($l['desconto'] ?? 0) / 100)), 2);
            $p->hours = round($linhas->where('tipo', 'service')->sum(fn ($l) => (float) ($l['horas'] ?? 0) * max(1, (float) $l['quantidade'])), 2);
        });
    }
}
