<?php

namespace App\Http\Middleware;

use App\Services\Plataforma\RenovacaoDeSubscricoes;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Faz sair as facturas de renovação aproveitando quem está a usar o sistema.
 *
 * Mesmo desenho do DespacharNotificacoes e pela mesma razão: este alojamento
 * não tem processo permanente, e um `schedule:run` que pode nunca ser chamado
 * não é sítio para pôr a única coisa que faz a plataforma cobrar. Já se viu
 * aqui o que isso dá — o `subscriptions:expire` cortava o acesso no fim do
 * período e nunca ninguém tinha recebido a segunda factura.
 *
 * Diferenças em relação ao das notificações, ambas de propósito:
 *
 *   · a tranca é UMA para toda a plataforma, não uma por empresa. Isto não é
 *     trabalho de uma empresa a correr no pedido de outra: é trabalho da
 *     plataforma, sobre as subscrições dela, e o pedido de quem está a navegar
 *     serve só de relógio;
 *   · uma vez por hora chega. Emitir a factura oito dias antes dá margem de
 *     sobra e não há aqui nada urgente ao minuto.
 *
 * O que torna isto seguro é a idempotência do serviço: uma factura por
 * período. Sem ela, correr a cada pedido enchia o cliente de contas iguais.
 */
class FacturarRenovacoes
{
    /** Intervalo mínimo entre passagens, para toda a plataforma. */
    private const INTERVALO_SEGUNDOS = 3600;

    private const TRANCA = 'plataforma:facturar-renovacoes';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('OPTIONS') || $request->is('maintenance/*') || $request->is('api/*')) {
            return;
        }

        // Só em pedidos de gente autenticada: é sinal de que há actividade
        // real, e evita que um robô a varrer a landing dispare o processo.
        if (!auth()->check()) {
            return;
        }

        // Interruptor. Uma factura é um documento que o cliente vê e que lhe
        // pede dinheiro — a primeira passagem emite de uma vez as contas de
        // todas as subscrições a acabar, e isso vê-se antes de acontecer
        // (`subscriptions:renovar --so-ver`). Ver config/billing.php.
        if (!config('billing.renovacao_automatica', false)) {
            return;
        }

        // Cache::add só devolve verdadeiro a quem CRIAR a chave — é o que
        // impede dois pedidos simultâneos de facturarem ambos.
        if (!Cache::add(self::TRANCA, 1, self::INTERVALO_SEGUNDOS)) {
            return;
        }

        try {
            $r = app(RenovacaoDeSubscricoes::class)->emitirFacturasAVencer(
                (int) config('billing.dias_de_antecedencia', RenovacaoDeSubscricoes::DIAS_DE_ANTECEDENCIA)
            );

            if ($r['emitidas'] > 0) {
                Log::info('Facturas de renovação emitidas', [
                    'emitidas'  => $r['emitidas'],
                    'ignoradas' => $r['ignoradas'],
                ]);
            }
        } catch (\Throwable $e) {
            // Isto corre depois de a resposta ter seguido para o browser, mas
            // uma excepção aqui ainda enche o log e mata o pedido a meio do
            // terminate. Quem estava a trabalhar não tem culpa da facturação.
            Log::error('Facturação de renovações falhou', [
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
