<?php

namespace App\Http\Controllers\Api\Invoicing\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Quem foi o operador de uma venda/turno feito OFFLINE.
 *
 * O login offline por PIN é do lado do cliente: a sessão Laravel do aparelho
 * continua a ser a do último a sincronizar online. Sem isto, uma venda que o
 * funcionário B fez (entrou por PIN) seria comunicada à AGT em nome de A — o
 * talão diria B e a Fatura-Recibo assinada diria A. Para a rastreabilidade
 * fiscal do operador bater certo, o dispositivo envia quem entrou por PIN e o
 * servidor confirma-o antes de o aceitar.
 *
 * A confirmação não é opcional: um cliente podia mandar qualquer id. Só se
 * aceita um utilizador ACTIVO e com ligação ACTIVA a esta empresa; qualquer
 * outra coisa cai no operador da sessão.
 */
trait ResolveOperadorOffline
{
    protected function operadorOffline(Request $request, int $tenantId): int
    {
        $fallback = (int) auth()->id();

        $id    = $request->input('operator_id');
        $email = $request->input('operator_email');

        if (!$id && !$email) {
            return $fallback;
        }

        $op = User::where('is_active', true)
            ->when($id, fn ($q) => $q->where('id', (int) $id))
            ->when(!$id && $email, fn ($q) => $q->where('email', mb_strtolower(trim($email))))
            ->first();

        if (!$op) {
            return $fallback;
        }

        // A ligação ao tenant tem de existir e estar activa.
        $ligado = DB::table('tenant_user')
            ->where('user_id', $op->id)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->exists();

        return $ligado ? (int) $op->id : $fallback;
    }
}
