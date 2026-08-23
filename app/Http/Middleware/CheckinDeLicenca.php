<?php

namespace App\Http\Middleware;

use App\Services\Licensing\LicenseCheckin;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Faz o check-in ao servidor de licenças à boleia do tráfego — o mesmo padrão
 * do DespacharAgtPendentes, porque a instalação offline também não tem worker
 * de cron garantido.
 *
 * Corre em `terminate` (depois de a resposta seguir para o cliente, custo zero
 * no ecrã), no máximo uma vez por intervalo (tranca por TTL de cache), e SÓ na
 * build offline (enforce ligado + checkin_url definido). Nunca rebenta o pedido.
 */
class CheckinDeLicenca
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (!config('licensing.enforce', false)) {
                return; // cloud: no-op
            }

            $url = trim((string) config('licensing.checkin_url', ''));
            if ($url === '') {
                return; // sem servidor configurado
            }

            $horas = max(1, (int) config('licensing.checkin_interval_hours', 12));
            $chave = 'licenca:checkin:janela';

            // Tranca por TTL: enquanto a chave existir, não se tenta de novo.
            if (Cache::get($chave)) {
                return;
            }
            Cache::put($chave, CarbonImmutable::now()->toIso8601String(), now()->addHours($horas));

            LicenseCheckin::apartirDaConfig()->executar();
        } catch (\Throwable $e) {
            // O check-in nunca pode partir o pedido do utilizador.
        }
    }
}
