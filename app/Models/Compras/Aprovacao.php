<?php

namespace App\Models\Compras;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * UM VOTO: quem aprovou (ou recusou) uma encomenda ou um pedido de pagamento,
 * em que ronda, quando e porquê. Só se acrescentam linhas — é a prova da
 * separação de funções que o circuito existe para garantir.
 */
class Aprovacao extends Model
{
    use BelongsToTenant;

    protected $table = 'compras_aprovacoes';

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'aprovavel_type', 'aprovavel_id', 'ronda', 'user_id', 'decisao', 'comentario',
    ];

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
