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
 * no ecrã) e SÓ na build offline (enforce ligado + checkin_url definido). Nunca
 * rebenta o pedido.
 *
 * A CADÊNCIA NÃO É FIXA. Antes trancava 12 horas ANTES de tentar: bastava não
 * haver rede nesse segundo para a máquina ficar meio dia sem voltar a tentar —
 * e o que o super admin mudasse no painel só aparecia no dia seguinte. Agora:
 *
 *   - a tranca curta (2 min) só existe para não haver dez pedidos em paralelo
 *     a ligar ao servidor ao mesmo tempo;
 *   - a tranca a sério é posta DEPOIS, e depende do que aconteceu:
 *       renovada  → o que o servidor mandar (10 min se a licença é curta)
 *       sem rede  → 5 min, para apanhar a internet mal ela volte
 *       bloqueada → 10 min, para o desbloqueio também ser rápido
 */
class CheckinDeLicenca
{
    private const CHAVE = 'licenca:checkin:janela';

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

            if (trim((string) config('licensing.checkin_url', '')) === '') {
                return; // sem servidor configurado
            }

            if (Cache::get(self::CHAVE)) {
                return;
            }

            // Trava de avalanche: enquanto esta tentativa decorre, mais nenhum
            // pedido tenta o mesmo. Curta de propósito — não é a cadência.
            Cache::put(self::CHAVE, 'a-tentar', now()->addMinutes(2));

            $r = LicenseCheckin::apartirDaConfig()->executar();

            Cache::put(
                self::CHAVE,
                CarbonImmutable::now()->toIso8601String(),
                now()->addMinutes($this->minutosAteProxima($r))
            );
        } catch (\Throwable $e) {
            // O check-in nunca pode partir o pedido do utilizador.
        }
    }

    /** Quanto tempo esperar até tentar outra vez, pelo que acabou de acontecer. */
    private function minutosAteProxima(array $r): int
    {
        $normal = max(1, (int) config('licensing.checkin_interval_hours', 12)) * 60;

        if (($r['ok'] ?? false) === true) {
            if (($r['acao'] ?? null) === 'bloquear') {
                return 10; // levantar um bloqueio tem de ser rápido
            }

            return (int) ($r['proximo_minutos'] ?? $normal);
        }

        return match ($r['motivo'] ?? '') {
            // Falhas de rede: o cliente pode estar a arrancar, ou a net a ir e
            // vir. Voltar depressa é barato e é o que faz o painel e o ecrã
            // baterem certo poucos minutos depois de haver internet.
            'sem_rede'            => 5,
            'renovacao_invalida'  => 30,
            // Nada para fazer sem intervenção humana: não vale a pena insistir.
            'sem_url', 'sem_licenca' => $normal,
            default               => str_starts_with((string) ($r['motivo'] ?? ''), 'http_') ? 15 : $normal,
        };
    }
}
