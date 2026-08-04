<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware global para identificar tenant
        $middleware->append(\App\Http\Middleware\IdentifyTenant::class);
        
        // Middleware global para verificar se tenant está ativo
        $middleware->append(\App\Http\Middleware\CheckTenantActive::class);
        
        // Middleware global para verificar subscription (após identificar tenant)
        // IMPORTANTE: Só executa em rotas web que exigem autenticação
        $middleware->appendToGroup('web', \App\Http\Middleware\CheckSubscription::class);
        
        // Middleware para registrar último login
        $middleware->append(\App\Http\Middleware\RecordLastLogin::class);
        
        // Com o sistema em manutenção, as rotas de manutenção continuam a
        // responder.
        //
        // Sem isto, um `artisan down` trancava a porta por dentro: o
        // `PreventRequestsDuringMaintenance` devolve 503 a tudo, incluindo
        // `/maintenance/{token}/command/up`, e a única forma de voltar a ligar
        // o sistema era apagar `storage/framework/down` por FTP. A lista tem de
        // estar AQUI — o middleware lê `$this->except` da classe e ignora o
        // campo `except` do ficheiro que o `artisan down` escreve.
        //
        // Continuam protegidas pelo token: quem não o tiver leva 404.
        $middleware->preventRequestsDuringMaintenance(except: [
            'maintenance/*',
        ]);

        // Excluir rotas de API do CSRF (PWA usa session auth + endpoints JSON)
        $middleware->validateCsrfTokens(except: [
            'api/v1/invoicing/*',
            'api/v1/auth/*',
            'api/analytics/*',
            // Verificação de integridade do deploy: chamada máquina-a-máquina
            // a partir da consola, sem sessão nem formulário, logo sem token
            // CSRF possível. Fica protegida pelo token de manutenção no URL e
            // é apenas de LEITURA — devolve caminhos e tamanhos, nunca conteúdo
            // nem altera nada.
            'maintenance/*/verify-files',
        ]);

        // Middleware aliases
        $middleware->alias([
            'tenant.access' => \App\Http\Middleware\EnsureTenantAccess::class,
            'superadmin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'tenant.module' => \App\Http\Middleware\CheckTenantModule::class,
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'tenant.active' => \App\Http\Middleware\CheckTenantActive::class,
            'api.token' => \App\Http\Middleware\ResolveApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sessão expirada em pedidos Livewire/AJAX/JSON: devolver 419 JSON em vez do
        // 302→/login (HTML). O fetch do Livewire segue o redirect e receberia a página
        // de login em HTML, tentando interpretá-la como snapshot JSON — o componente
        // "congela" sem erro visível (o sintoma "trava o sistema" no POS). Um 419 limpo
        // é detetado no cliente (layouts/app.blade.php) e trata a recuperação da sessão.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->hasHeader('X-Livewire') || $request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => 'Sessão expirada'], 419);
            }
        });

        // Separador aberto de ANTES de um deploy.
        //
        // O Livewire valida o snapshot do componente contra o código actual. Se
        // as propriedades mudaram entretanto — e mudam a cada deploy — o
        // snapshot que o separador tem em memória deixa de bater certo e o
        // utilizador leva um 500 com "corrupt data", que não lhe diz nada e não
        // lhe dá saída nenhuma.
        //
        // 409 e não 419: a sessão está boa, o que está velho é a página. O 419
        // mostra o ecrã de "sessão terminada" com botão de login, e mandar
        // alguém iniciar sessão outra vez quando ela nunca caiu é pior do que o
        // erro. O cliente trata o 409 recarregando a página no mesmo sítio.
        $exceptions->render(function (\Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException $e, \Illuminate\Http\Request $request) {
            if ($request->hasHeader('X-Livewire') || $request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'message' => 'A página está desactualizada. Vai ser recarregada.',
                ], 409);
            }
        });
    })->create();
