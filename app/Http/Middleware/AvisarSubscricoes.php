<?php

namespace App\Http\Middleware;

use App\Services\Billing\AvisosDeSubscricao;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Faz sair os avisos de facturação ao cliente aproveitando o tráfego.
 *
 * Mesmo desenho do FacturarRenovacoes e do DespacharNotificacoes, e pela mesma
 * razão: este alojamento não tem processo permanente e o `schedule:run` pode
 * nunca ser chamado. Um aviso de cobrança pendurado num cron que não existe é
 * um aviso que nunca chega — foi assim que se chegou a cortar o acesso a
 * clientes que nunca tinham recebido a segunda factura.
 *
 * TRANCA E INTERRUPTOR PRÓPRIOS, separados dos da emissão de facturas. Desligar
 * a emissão não pode desligar os avisos das facturas que já existem, nem o
 * contrário. E é uma tranca para toda a plataforma, não uma por empresa: isto é
 * trabalho da plataforma sobre as subscrições dela; o pedido de quem está a
 * navegar serve só de relógio.
 *
 * Corre em `terminate`, depois de a resposta já ter seguido para o browser —
 * ninguém espera por um servidor de email nem por uma operadora de SMS.
 */
class AvisarSubscricoes
{
    /** Intervalo mínimo entre passagens, para toda a plataforma. */
    private const INTERVALO_SEGUNDOS = 3600;

    private const TRANCA = 'plataforma:avisos-subscricao';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('OPTIONS') || $request->is('maintenance/*') || $request->is('api/*')) {
            return;
        }

        if (!auth()->check()) {
            return;
        }

        if (!config('billing.avisos_ao_cliente', false)) {
            return;
        }

        // Cache::add só devolve verdadeiro a quem CRIAR a chave.
        if (!Cache::add(self::TRANCA, 1, self::INTERVALO_SEGUNDOS)) {
            return;
        }

        try {
            $r = app(AvisosDeSubscricao::class)->varrer();

            if (($r['email'] ?? 0) > 0 || ($r['sms'] ?? 0) > 0) {
                Log::info('Avisos de subscrição enviados', [
                    'email' => $r['email'], 'sms' => $r['sms'],
                    'repetidos' => $r['repetidos'], 'falhados' => $r['falhados'],
                    'sem_contacto' => $r['sem_contacto'],
                ]);
            }
        } catch (\Throwable $e) {
            // Isto corre depois de a resposta ter seguido, mas uma excepção
            // aqui ainda mata o pedido a meio do terminate. Quem estava a
            // trabalhar não tem culpa da facturação.
            Log::error('Avisos de subscrição falharam', ['erro' => $e->getMessage()]);
        }
    }
}
