<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Quem viu (e quem dispensou) uma mensagem da plataforma.
 *
 * Uma linha por pessoa que a viu — não por pessoa a quem se destina. Uma
 * mensagem para todas as empresas não escreve nada até alguém a abrir.
 */
class PlatformMessageRead extends Model
{
    protected $fillable = [
        'platform_message_id', 'user_id', 'tenant_id', 'seen_at', 'dismissed_at',
    ];

    protected $casts = [
        'seen_at'      => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function mensagem()
    {
        return $this->belongsTo(PlatformMessage::class, 'platform_message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
