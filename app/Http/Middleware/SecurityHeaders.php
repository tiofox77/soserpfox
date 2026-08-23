<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança em todas as respostas.
 *
 * Escolhas deliberadas para NÃO partir uma app que depende de scripts inline,
 * de CDNs (Tailwind, cdnjs, jsdelivr, Google Fonts) e de analítica (GTM, pixel
 * do Facebook):
 *
 *  - A CSP é "solta": permite script/style de https + inline + eval (o Tailwind
 *    CDN compila em runtime = eval; o Alpine/Livewire usam inline). O que ela
 *    fecha de verdade é o essencial e sem custo: frame-ancestors (clickjacking),
 *    object-src none (sem Flash/applets), base-uri self. Dá para apertar depois,
 *    quando os inline saírem para ficheiros.
 *  - HSTS vai em todas as respostas; o browser só a honra sobre HTTPS. Sem
 *    includeSubDomains para já, para não forçar HTTPS em subdomínios que possam
 *    não o ter (ex.: docs.soserp.vip).
 *  - X-Powered-By é removido aqui; o Apache/LiteSpeed remove-o também no
 *    .htaccess para as respostas estáticas que não passam pelo PHP.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Não reescrever se já vier definido (ex.: uma resposta específica).
        $set = function (string $name, string $value) use ($response) {
            if (!$response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        };

        $set('X-Content-Type-Options', 'nosniff');
        $set('X-Frame-Options', 'SAMEORIGIN');
        $set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $set('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=(self), payment=(), usb=()');
        // Só é honrada sobre HTTPS; inofensiva sobre HTTP.
        $set('Strict-Transport-Security', 'max-age=31536000');

        $set('Content-Security-Policy', implode('; ', [
            "default-src 'self' https: data: blob: 'unsafe-inline' 'unsafe-eval'",
            "img-src 'self' https: data: blob:",
            "font-src 'self' https: data:",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
        ]));

        // Não anunciar a versão do PHP.
        $response->headers->remove('X-Powered-By');
        if (function_exists('header_remove')) {
            @header_remove('X-Powered-By');
        }

        return $response;
    }
}
