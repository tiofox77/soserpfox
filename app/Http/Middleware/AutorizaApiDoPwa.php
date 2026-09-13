<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\Invoicing\ClientApiController;
use App\Http\Controllers\Api\Invoicing\PosApiController;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * A API DO PWA E DA APP MÓVEL — `/api/v1/invoicing/*` — só autenticava.
 *
 * Qualquer membro activo da empresa, com o papel que fosse (um mecânico, a
 * limpeza, alguém sem papel nenhum), emitia uma Fatura-Recibo assinada e
 * enviada à AGT ao preço que escrevesse, em nome de um colega (`operator_id`),
 * abria e fechava o turno desse colega com a contagem que quisesse, e descia a
 * sincronização com os clientes da empresa (auditoria de segurança de
 * 2026-09-13). Os ecrãs web destas mesmas coisas pedem permissão; esta porta
 * passa a pedir a mesma.
 *
 * O OPERADOR. O PWA partilhado mantém a sessão de quem sincronizou e manda o
 * operador que entrou por PIN — o servidor não pode conferir esse PIN, que foi
 * visto sem rede. Mas pode exigir o que é verificável: que a SESSÃO e o
 * OPERADOR tenham ambos a permissão da acção, e que o operador, quando não é
 * quem tem a sessão, tenha PIN (sem PIN ninguém entra no aparelho sem rede).
 *
 *   pwa.api:ler     sincronizar, diagnóstico, estado do turno
 *   pwa.api:vender  venda do balcão, abrir e fechar turno
 *   pwa.api:emitir  documentos (FT/FR pedem facturar; proforma pede proformas)
 *   pwa.api:cliente criar cliente
 */
class AutorizaApiDoPwa
{
    public function handle(Request $request, Closure $next, string $accao): Response
    {
        $sessao = $request->user();

        if (! $sessao) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $operador = $this->operador($request, $sessao);

        $pessoas = $operador->is($sessao) ? [$sessao] : [$sessao, $operador];

        foreach ($pessoas as $quem) {
            if (! $this->pode($quem, $accao, $request)) {
                return response()->json([
                    'message' => $quem->is($sessao)
                        ? __('Sem permissão para esta operação.')
                        : __('O operador :nome não tem permissão para esta operação.', ['nome' => $quem->name]),
                ], 403);
            }
        }

        return $next($request);
    }

    private function pode(User $u, string $accao, Request $request): bool
    {
        if ($u->is_super_admin) {
            return true;
        }

        return match ($accao) {
            // Quem usa o PWA: o balcão, a facturação, ou o restaurante.
            'ler' => PosApiController::podeVenderAoBalcao($u)
                || $u->can('invoicing.sales.proformas.create')
                || $u->canAny(['restaurant.orders.view', 'restaurant.checkout.charge']),
            'vender' => PosApiController::podeVenderAoBalcao($u),
            'emitir' => $request->input('doc_type') === 'proforma'
                ? $u->canAny(['invoicing.sales.proformas.create', 'invoicing.sales.invoices.create'])
                : ($u->can('invoicing.sales.invoices.create') || ($request->input('doc_type') === 'FR' && PosApiController::podeVenderAoBalcao($u))),
            'cliente' => ClientApiController::podeCriarCliente($u),
            default => false,
        };
    }

    /**
     * O operador que o aparelho indica — com as MESMAS regras do
     * `ResolveOperadorOffline` (activo, ligado a esta empresa — senão fica a
     * sessão) e mais uma: um colega tem de ter PIN, que é a única forma de ter
     * entrado num aparelho alheio sem rede. Sem PIN o pedido é recusado.
     */
    private function operador(Request $request, User $sessao): User
    {
        $id = $request->input('operator_id');
        $email = $request->input('operator_email');

        if (! $id && ! $email) {
            return $sessao;
        }

        $op = User::where('is_active', true)
            ->when($id, fn ($q) => $q->where('id', (int) $id))
            ->when(! $id && $email, fn ($q) => $q->where('email', mb_strtolower(trim((string) $email))))
            ->first();

        if (! $op || $op->is($sessao)) {
            return $sessao;
        }

        $ligado = DB::table('tenant_user')->where('user_id', $op->id)
            ->where('tenant_id', activeTenantId())->where('is_active', true)->exists();

        // De fora da empresa: o controlador já o ignora e fica a sessão.
        if (! $ligado) {
            return $sessao;
        }

        abort_unless($op->temPinPos(), 403, __('O operador :nome não tem PIN de turno: não pode ter entrado neste aparelho sem rede.', ['nome' => $op->name]));

        // As permissões do operador lêem-se na empresa activa, como as da sessão.
        setPermissionsTeamId(activeTenantId());

        return $op;
    }
}
