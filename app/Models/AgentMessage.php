<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Registo de tudo o que o agente mandou para fora. */
class AgentMessage extends Model
{
    protected $fillable = [
        'agent_token_id', 'tenant_id', 'canal', 'template_slug',
        'destinatario_handle', 'destinatario_mascarado', 'estado',
        'erro', 'motivo', 'idempotency_key', 'dia',
    ];

    protected $casts = ['dia' => 'date'];

    public const RESERVADO = 'reservado';
    public const ENVIADO   = 'enviado';
    public const FALHOU    = 'falhou';
}
