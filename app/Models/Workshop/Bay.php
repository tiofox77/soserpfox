<?php

namespace App\Models\Workshop;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * UM ELEVADOR OU UMA BAIA — um lugar onde se trabalha num carro (15/09/2026, OF-05).
 *
 * A agenda mede a capacidade do dia por estes lugares. Uma oficina que nunca
 * abriu a lista recebe dois elevadores e uma baia.
 */
class Bay extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_bays';

    protected $fillable = ['tenant_id', 'name', 'kind', 'color', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public const TIPOS = [
        'elevador' => 'Elevador',
        'baia' => 'Baia',
        'bate_chapa' => 'Bate-chapa',
        'pintura' => 'Cabine de pintura',
        'lavagem' => 'Lavagem',
        'diagnostico' => 'Diagnóstico',
    ];

    public const CORES = ['azul', 'verde', 'ambar', 'laranja', 'roxo', 'teal', 'vermelho', 'cinza'];

    public static function garantirCatalogo(int $tenantId): void
    {
        if (self::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        foreach ([['Elevador 1', 'elevador', 'azul'], ['Elevador 2', 'elevador', 'verde'], ['Baia 1', 'baia', 'ambar']] as $n => [$nome, $tipo, $cor]) {
            self::withoutGlobalScopes()->create(['tenant_id' => $tenantId, 'name' => __($nome), 'kind' => $tipo, 'color' => $cor, 'is_active' => true, 'sort_order' => $n + 1]);
        }
    }
}
