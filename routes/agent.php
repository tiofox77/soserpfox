<?php

use App\Http\Controllers\Api\Agent\AgentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API do agente externo (openclaw)
|--------------------------------------------------------------------------
|
| FORA do grupo 'web' de propósito: sem sessão, sem cookies, sem CSRF. O
| token do agente não pode ser convertido num browser autenticado — foi
| por isso que não se reutilizou o ResolveApiToken, que faz Auth::login().
|
| Cada rota declara o escopo que a autoriza. Não há rota sem escopo, e não
| há escopo concedido por omissão.
|
*/

Route::prefix('api/agent/v1')
    ->middleware([
        // Fora dos grupos web/api nao ha SubstituteBindings: sem isto o
        // {order} da rota nunca e resolvido e o controlador recebe um
        // modelo VAZIO — a validacao passava a comparar com null.
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'agent.token',
    ])
    ->group(function () {

        // Identidade — qualquer token válido.
        Route::get('me', [AgentController::class, 'me']);

        // ── Leitura ──────────────────────────────────────────────
        Route::middleware('throttle:agent-read')->group(function () {

            Route::middleware('agent.scope:tenants:read')->group(function () {
                Route::get('tenants', [AgentController::class, 'tenants']);
                Route::get('tenants/{tenant}', [AgentController::class, 'tenant']);
            });

            Route::middleware('agent.scope:health:read')->group(function () {
                Route::get('health/checks', [AgentController::class, 'verificacoes']);
                Route::get('health/inconsistencias', [AgentController::class, 'inconsistencias']);
            });

            Route::middleware('agent.scope:orders:read')->group(function () {
                Route::get('orders', [AgentController::class, 'pedidos']);
                Route::get('orders/{order}', [AgentController::class, 'pedido']);
            });

            Route::middleware('agent.scope:followup:read')->group(function () {
                Route::get('followup/templates', [AgentController::class, 'modelos']);
                Route::get('followup/envios', [AgentController::class, 'envios']);
                Route::post('followup/preview', [AgentController::class, 'preview']);
            });
        });

        // ── Escrita ──────────────────────────────────────────────
        // Idempotência obrigatória: sem Idempotency-Key não se escreve.
        Route::middleware(['throttle:agent-write', 'agent.idempotencia'])->group(function () {

            Route::middleware('agent.scope:orders:approve')
                ->post('orders/{order}/approve', [AgentController::class, 'aprovar']);

            Route::middleware('agent.scope:orders:reject')
                ->post('orders/{order}/reject', [AgentController::class, 'recusar']);

            Route::middleware('agent.scope:followup:email')
                ->post('followup/email', [AgentController::class, 'enviarEmail']);

            Route::middleware('agent.scope:followup:sms')
                ->post('followup/sms', [AgentController::class, 'enviarSms']);
        });
    });
