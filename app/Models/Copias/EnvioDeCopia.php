<?php

namespace App\Models\Copias;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma cópia num destino: enviada, falhada ou apagada pela retenção. */
class EnvioDeCopia extends Model
{
    protected $table = 'envios_de_copia';

    protected $guarded = ['id'];

    protected $casts = ['enviado_em' => 'datetime'];

    public function destino(): BelongsTo
    {
        return $this->belongsTo(DestinoDeCopia::class, 'destino_id');
    }

    public function copia(): BelongsTo
    {
        return $this->belongsTo(CopiaDeSeguranca::class, 'copia_id');
    }
}
