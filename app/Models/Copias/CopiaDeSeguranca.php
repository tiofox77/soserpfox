<?php

namespace App\Models\Copias;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Uma cópia: o ficheiro, o que traz, e para onde foi enviada. */
class CopiaDeSeguranca extends Model
{
    protected $table = 'copias_de_seguranca';

    protected $guarded = ['id'];

    protected $casts = [
        'cifrada' => 'boolean',
        'ficheiro_local' => 'boolean',
        'resumo' => 'array',
        'iniciada_em' => 'datetime',
        'concluida_em' => 'datetime',
    ];

    public function envios(): HasMany
    {
        return $this->hasMany(EnvioDeCopia::class, 'copia_id');
    }

    public function scopeDoAmbito($q, ?int $tenantId)
    {
        return $tenantId === null ? $q->whereNull('tenant_id') : $q->where('tenant_id', $tenantId);
    }
}
