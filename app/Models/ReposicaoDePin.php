<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um PIN de turno reposto num aparelho sem rede — e o que o servidor decidiu
 * sobre isso quando a fila subiu.
 */
class ReposicaoDePin extends Model
{
    protected $table = 'reposicoes_de_pin';

    public const ACEITE   = 'aceite';
    public const RECUSADA = 'recusada';

    protected $fillable = [
        'tenant_id', 'local_uuid', 'user_id', 'autorizado_por', 'sessao_user_id',
        'aparelho', 'estado', 'motivo', 'reposto_no_aparelho_em',
    ];

    protected $casts = [
        'reposto_no_aparelho_em' => 'datetime',
    ];

    public function utilizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }

    public function foiAceite(): bool
    {
        return $this->estado === self::ACEITE;
    }
}
