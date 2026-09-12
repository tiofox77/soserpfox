<?php

namespace App\Models\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um comentário numa sugestão do quadro de melhorias.
 *
 * ESTAVA VAZIO — um `class FeatureRequestComment extends Model {}` sem mais
 * nada, como o `TicketMessage` ao lado esteve. A tabela adivinhada por acaso
 * calhava certa (`feature_request_comments`), mas sem `$fillable` qualquer
 * `create()` rebentava com uma MassAssignmentException; e sem relações, ler o
 * autor de um comentário custava uma consulta por linha.
 */
class FeatureRequestComment extends Model
{
    protected $fillable = [
        'request_id',
        'user_id',
        'comment',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(FeatureRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
