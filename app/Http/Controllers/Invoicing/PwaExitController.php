<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Saída do PWA Offline — redireciona o utilizador para a primeira área a que
 * TEM permissão, evitando 403 (ex.: vendedor sem acesso ao dashboard).
 * Prioridade: POS → dashboard → faturas → produtos → clientes → /home.
 */
class PwaExitController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $user = auth()->user();

        // Garantir o contexto de equipa (tenant) para as verificações Spatie
        if (function_exists('setPermissionsTeamId') && function_exists('activeTenantId') && activeTenantId()) {
            setPermissionsTeamId(activeTenantId());
        }

        // Super Admin: vai direto ao dashboard de faturação
        if ($user && $user->is_super_admin) {
            return redirect()->route('invoicing.dashboard');
        }

        $hasInvoicing = $user && method_exists($user, 'hasActiveModule')
            ? $user->hasActiveModule('invoicing')
            : true;

        // [rota, permissão necessária] — por ordem de prioridade
        $candidates = [
            ['invoicing.pos',            'invoicing.pos.access'],
            ['invoicing.dashboard',      'invoicing.dashboard.view'],
            ['invoicing.sales.invoices', 'invoicing.sales.invoices.view'],
            ['invoicing.products',       'invoicing.products.view'],
            ['invoicing.clients',        'invoicing.clients.view'],
        ];

        if ($hasInvoicing) {
            foreach ($candidates as [$routeName, $permission]) {
                if ($user && $user->can($permission)) {
                    return redirect()->route($routeName);
                }
            }
        }

        // Sem permissão para nenhuma área de faturação → página inicial (só requer auth)
        return redirect()->route('home');
    }
}
