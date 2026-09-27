<?php

namespace App\Models\Compras;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * AS REGRAS DO CIRCUITO DE CADA EMPRESA: quantas aprovações exige a encomenda,
 * quantas o pedido de pagamento, e quem é o tesoureiro por omissão.
 *
 * Sem linha, vale o de sempre — nenhuma aprovação. Uma empresa que nunca abriu
 * as regras continua a trabalhar exactamente como antes.
 */
class DefinicoesDasCompras extends Model
{
    protected $table = 'compras_definicoes';

    protected $fillable = ['tenant_id', 'aprovacoes_encomenda', 'aprovacoes_pagamento', 'tesoureiro_id'];

    protected $casts = [
        'aprovacoes_encomenda' => 'integer',
        'aprovacoes_pagamento' => 'integer',
    ];

    /** Até cinco pessoas por passo: mais do que isso é uma assembleia, não um circuito. */
    public const MAXIMO_DE_APROVACOES = 5;

    public static function da(int $tenantId): self
    {
        return static::firstOrNew(['tenant_id' => $tenantId], [
            'aprovacoes_encomenda' => 0,
            'aprovacoes_pagamento' => 0,
        ]);
    }

    public function tesoureiro()
    {
        return $this->belongsTo(User::class, 'tesoureiro_id');
    }
}
