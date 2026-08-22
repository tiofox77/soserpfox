<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Uma chamada de escrita já processada, para não a repetir. */
class AgentRequest extends Model
{
    protected $fillable = [
        'agent_token_id', 'idempotency_key', 'rota',
        'corpo_hash', 'http_status', 'resposta',
    ];

    protected $casts = ['resposta' => 'array'];

    public function token()
    {
        return $this->belongsTo(AgentToken::class, 'agent_token_id');
    }
}
