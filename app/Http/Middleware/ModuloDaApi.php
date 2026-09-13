<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A API DE UM MÓDULO SÓ SERVE A EMPRESA QUE TEM O MÓDULO.
 *
 * As páginas web de cada módulo passam pelo `tenant.module:<slug>`; a API React
 * não passava por nenhum. Uma empresa cujo teste do hotel ou do RH terminou (ou
 * cujo plano não os tem) continuava a usá-los inteiros pela API — as
 * permissões não caem quando o teste acaba (auditoria de segurança de
 * 2026-09-13). O primeiro segmento depois de `react/` diz o módulo.
 */
class ModuloDaApi
{
    private const MODULOS = [
        'hotel' => 'hotel',
        'restaurant' => 'restaurant',
        'rh' => 'rh',
        'salao' => 'salon',
        'oficina' => 'oficina',
        'eventos' => 'eventos',
        'contabilidade' => 'contabilidade',
        'crm' => 'crm',
        'compras' => 'compras',
        'projetos' => 'projetos',
        'inventario' => 'inventario',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (preg_match('#^api/v1/invoicing/react/([a-z-]+)#', $request->path(), $m) && isset(self::MODULOS[$m[1]])) {
            return app(CheckTenantModule::class)->handle($request, $next, self::MODULOS[$m[1]]);
        }

        return $next($request);
    }
}
