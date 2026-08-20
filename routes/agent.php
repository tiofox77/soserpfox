<?php

use App\Http\Controllers\Api\Agent\AgentController;
use App\Http\Controllers\Api\Agent\OperacoesController;
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
                Route::get('plans', [AgentController::class, 'planos']);
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

            // ── Operação da plataforma ────────────────────────────

            // O resumo de hora a hora. UMA chamada, não sete: se
            // `precisa_atencao` for falso não há nada a dizer e o agente
            // fica calado, que é metade do trabalho de quem vigia.
            Route::middleware('agent.scope:logs:read')
                ->get('status/resumo', [OperacoesController::class, 'resumo']);

            Route::middleware('agent.scope:logs:read')->group(function () {
                Route::get('logs/errors', [OperacoesController::class, 'erros']);
                Route::get('logs/errors/{erro}', [OperacoesController::class, 'erro']);
            });

            Route::middleware('agent.scope:billing:read')->group(function () {
                Route::get('billing/ciclo', [OperacoesController::class, 'ciclo']);
            });

            Route::middleware('agent.scope:support:read')->group(function () {
                Route::get('support/tickets', [OperacoesController::class, 'suporte']);
                Route::get('support/feedback', [OperacoesController::class, 'feedback']);
            });

            // Contactos REAIS, por mascarar. Escopo próprio, dado à mão:
            // em toda a restante API os contactos continuam mascarados.
            // Cada leitura fica no registo de pedidos do agente, portanto há
            // sempre resposta para "quem viu este número e quando".
            Route::middleware('agent.scope:contacts:read')
                ->get('tenants/{tenant}/contacts', [OperacoesController::class, 'contactos']);
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

            // ── Operação da plataforma ────────────────────────────

            Route::middleware('agent.scope:logs:write')->group(function () {
                Route::post('logs/errors/{erro}/estado', [OperacoesController::class, 'fecharErro']);
                Route::post('logs/empurrar', [OperacoesController::class, 'empurrarErros']);
            });

            // Emitir facturas e disparar avisos. As DUAS nascem em modo de
            // leitura (`so_ver` por omissão): uma factura é um documento que o
            // cliente vê e sobre o qual lhe é pedido dinheiro, e um aviso vai
            // para a caixa de correio ou o telemóvel de uma pessoa real. Para
            // agir mesmo, o agente tem de o dizer explicitamente.
            Route::middleware('agent.scope:billing:write')->group(function () {
                Route::post('billing/renovar', [OperacoesController::class, 'renovar']);
                Route::post('billing/avisar', [OperacoesController::class, 'avisar']);
            });

            Route::middleware('agent.scope:support:write')
                ->post('support/tickets/{ticket}/nota', [OperacoesController::class, 'anotarTicket']);

            // Suspender corta o acesso a toda a gente de uma empresa — é a
            // acção mais pesada que o agente pode fazer. Exige motivo escrito
            // e é reversível pela mesma rota.
            Route::middleware('agent.scope:tenants:write')
                ->post('tenants/{tenant}/estado', [OperacoesController::class, 'estadoDaEmpresa']);
        });
    });
