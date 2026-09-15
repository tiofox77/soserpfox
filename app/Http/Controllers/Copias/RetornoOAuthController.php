<?php

namespace App\Http\Controllers\Copias;

use App\Http\Controllers\Controller;
use App\Services\Copias\Destinos\OAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A VOLTA DO GOOGLE, DA MICROSOFT OU DO DROPBOX depois de a pessoa autorizar.
 *
 * Um só endereço de retorno para os três e para todos os âmbitos — é esse que
 * se regista na consola de cada fornecedor. O `state` diz de que destino se
 * trata; confirma-se que é da pessoa que o pediu e que ela ainda manda nesse
 * âmbito, troca-se o código pelos tokens, e volta-se ao ecrã.
 */
class RetornoOAuthController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $destino = OAuth::lerState((string) $request->query('state'), (int) $user->id);
            $ecra = $destino->tenant_id === null ? route('superadmin.copias') : route('company.copias');

            $autorizado = $destino->tenant_id === null
                ? $user->isPlatformSuperAdmin()
                : ((int) activeTenantId() === (int) $destino->tenant_id && $user->canManageAccount());

            if (! $autorizado) {
                abort(403);
            }

            if ($request->filled('error')) {
                return redirect($ecra . '?oauth=recusado');
            }

            OAuth::concluir($destino, (string) $request->query('code'));

            return redirect($ecra . '?oauth=ligado');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect(($user?->isPlatformSuperAdmin() ? route('superadmin.copias') : route('company.copias')) . '?oauth=erro&mensagem=' . rawurlencode(mb_strimwidth($e->getMessage(), 0, 200, '…')));
        }
    }
}
