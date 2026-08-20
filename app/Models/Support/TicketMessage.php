<?php

namespace App\Models\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma mensagem no fio de um pedido de suporte.
 *
 * ESTAVA VAZIO. Era um `class TicketMessage extends Model {}` sem mais nada,
 * pelo que o Eloquent inferia a tabela `ticket_messages` — que não existe; a
 * tabela chama-se `support_ticket_messages`, como o `Ticket` ao lado já
 * declarava. Qualquer escrita ou leitura pela relação `Ticket::messages()`
 * rebentava com "Base table or view not found", e como nada no sistema a
 * usava, isso nunca se soube.
 */
class TicketMessage extends Model
{
    protected $table = 'support_ticket_messages';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'is_staff',
    ];

    protected $casts = [
        'is_staff' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
