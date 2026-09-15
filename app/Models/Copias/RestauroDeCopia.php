<?php

namespace App\Models\Copias;

use Illuminate\Database\Eloquent\Model;

/** Um restauro: de que cópia, a cópia que se tirou antes, e como correu. */
class RestauroDeCopia extends Model
{
    protected $table = 'restauros_de_copia';

    protected $guarded = ['id'];

    protected $casts = [
        'resumo' => 'array',
        'iniciado_em' => 'datetime',
        'concluido_em' => 'datetime',
    ];

    public function scopeDoAmbito($q, ?int $tenantId)
    {
        return $tenantId === null ? $q->whereNull('tenant_id') : $q->where('tenant_id', $tenantId);
    }
}
