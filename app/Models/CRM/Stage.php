<?php

namespace App\Models\CRM;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma etapa do funil de vendas. Por empresa e ordenável: cada casa vende à
 * sua maneira. As etapas por omissão nascem com a primeira utilização.
 */
class Stage extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_stages';

    protected $fillable = ['tenant_id', 'name', 'sort_order', 'probability', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** As etapas por omissão — o funil clássico, em português de vender. */
    public const PADRAO = [
        ['name' => 'Novo contacto', 'probability' => 10],
        ['name' => 'Qualificação', 'probability' => 25],
        ['name' => 'Proposta enviada', 'probability' => 50],
        ['name' => 'Negociação', 'probability' => 75],
        ['name' => 'Fecho', 'probability' => 90],
    ];

    /**
     * As etapas da empresa — criadas à primeira pergunta.
     *
     * O mesmo padrão das condições de pagamento: ninguém configura nada para
     * começar a usar, e quem quiser outras etapas edita depois.
     */
    public static function doTenant(int $tenantId)
    {
        $etapas = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($etapas->isNotEmpty()) {
            return $etapas;
        }

        foreach (self::PADRAO as $i => $etapa) {
            static::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'name' => $etapa['name'],
                'probability' => $etapa['probability'],
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
        }

        return static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
