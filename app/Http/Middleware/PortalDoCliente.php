<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\PortalDoCliente as Areas;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * AS PORTAS DO PORTAL DO CLIENTE.
 *
 * Sem área (`portal`): só a empresa activa. O portal não olhava à empresa —
 * uma empresa desactivada pela plataforma continuava com os clientes a
 * entrar e a ver facturas. Agora sai, com o recado.
 *
 * Com área (`portal:oficina`): e o cliente tem de a ter marcada na ficha, e a
 * empresa o módulo activo. Página e API respondem igual — não se vê o menu
 * e não se chega pelo endereço.
 */
class PortalDoCliente
{
    public function handle(Request $request, Closure $next, ?string $area = null): Response
    {
        $cliente = $request->user('client');

        if (! $cliente) {
            return $next($request);
        }

        $empresa = Tenant::find($cliente->tenant_id);

        if (! $empresa || ! $empresa->is_active || ! $cliente->is_active || ! $cliente->portal_access) {
            Auth::guard('client')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $recado = __('O acesso a este portal não está disponível. Contacte a empresa.');

            return $request->expectsJson()
                ? response()->json(['message' => $recado], 403)
                : redirect()->route('client.login')->with('error', $recado);
        }

        if ($area === 'facturas' && ! Areas::veFacturas($cliente)) {
            abort(403, __('Esta área não faz parte do seu portal.'));
        }

        if ($area && $area !== 'facturas' && ! Areas::ve($cliente, $area)) {
            abort(403, __('Esta área não faz parte do seu portal.'));
        }

        return $next($request);
    }
}
