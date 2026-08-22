<?php

use App\Http\Controllers\Api\Agent\AgentController;
use App\Http\Controllers\Api\Agent\GestaoController;
use App\Http\Controllers\Api\Agent\InteligenciaController;
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

                // O que se perde ao apagar — SEM apagar. Existe para que a
                // decisão possa ser tomada com a lista à frente: apagar uma
                // empresa é a única coisa nesta API que não tem volta.
                Route::get('tenants/{tenant}/eliminacao', [GestaoController::class, 'previsaoDeEliminacao']);
            });

            Route::middleware('agent.scope:plans:read')->group(function () {
                Route::get('plans/catalogo', [GestaoController::class, 'planos']);
                Route::get('plans/{plan}', [GestaoController::class, 'plano']);
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
                Route::get('followup/platform-owner', [AgentController::class, 'donoPlataforma']);
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
                Route::get('logs/audit', [InteligenciaController::class, 'auditoria']);
                Route::get('logs/agent', [InteligenciaController::class, 'pedidosDoAgente']);
            });

            Route::middleware('agent.scope:analytics:read')->group(function () {
                Route::get('analytics/overview', [InteligenciaController::class, 'visaoGeral']);
                Route::get('analytics/users', [InteligenciaController::class, 'utilizadores']);
                Route::get('analytics/recommendations', [InteligenciaController::class, 'recomendacoes']);
            });

            Route::middleware('agent.scope:system:read')
                ->get('system/status', [InteligenciaController::class, 'estadoDoSistema']);

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

            Route::middleware('agent.scope:followup:sms')
                ->post('followup/sms/platform-owner', [AgentController::class, 'enviarSmsDonoPlataforma']);

            Route::middleware('agent.scope:followup:free')->group(function () {
                Route::post('followup/free/sms', [AgentController::class, 'enviarSmsLivre']);
                Route::post('followup/free/email', [AgentController::class, 'enviarEmailLivre']);
            });

            // ── Operação da plataforma ────────────────────────────

            Route::middleware('agent.scope:logs:write')->group(function () {
                Route::post('logs/errors/{erro}/estado', [OperacoesController::class, 'fecharErro']);
                Route::post('logs/empurrar', [OperacoesController::class, 'empurrarErros']);
            });

            Route::middleware('agent.scope:system:write')
                ->post('system/actions', [InteligenciaController::class, 'executarAccao']);

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

            // ── Empresas: CRUD ────────────────────────────────────
            //
            // Suspender corta o acesso a toda a gente de uma empresa — é a
            // acção mais pesada que o agente pode fazer sem apagar nada.
            // Exige motivo escrito e é reversível pela mesma rota.
            //
            // Corrigir é quase sempre a resposta certa quando suspender
            // parece ser: um NIF mal preenchido corrige-se, não se corta o
            // acesso a quem paga por causa dele.
            Route::middleware('agent.scope:tenants:write')->group(function () {
                Route::post('tenants', [GestaoController::class, 'criarEmpresa']);
                Route::patch('tenants/{tenant}', [GestaoController::class, 'actualizarEmpresa']);
                Route::post('tenants/{tenant}/estado', [OperacoesController::class, 'estadoDaEmpresa']);
                Route::post('tenants/{tenant}/suspend', [OperacoesController::class, 'suspenderEmpresa']);
                Route::post('tenants/{tenant}/reactivate', [OperacoesController::class, 'reactivarEmpresa']);
                Route::post('tenants/{tenant}/reativar', [OperacoesController::class, 'reactivarEmpresa']);
            });

            // Apagar tem ESCOPO PRÓPRIO: dar ao agente a capacidade de
            // corrigir uma empresa não lhe deve dar a de a destruir. Exige
            // ainda escrever o nome da empresa por extenso — a diferença
            // entre confirmar e carregar por reflexo.
            Route::middleware('agent.scope:tenants:delete')
                ->delete('tenants/{tenant}', [GestaoController::class, 'apagarEmpresa']);

            // ── Planos: CRUD ──────────────────────────────────────
            //
            // Um plano criado por aqui nasce FORA DA MONTRA e desactivar
            // substitui apagar: há subscrições a apontar-lhe, e apagá-lo
            // deixava clientes com uma subscrição órfã.
            Route::middleware('agent.scope:plans:write')->group(function () {
                Route::post('plans', [GestaoController::class, 'criarPlano']);
                Route::patch('plans/{plan}', [GestaoController::class, 'actualizarPlano']);
                Route::post('plans/{plan}/estado', [GestaoController::class, 'desactivarPlano']);
            });
        });
    });
