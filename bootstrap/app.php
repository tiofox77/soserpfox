<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        // API do agente externo: FORA do grupo web, sem sessao nem CSRF.
        then: function () {
            \Illuminate\Support\Facades\Route::group([], __DIR__.'/../routes/agent.php');
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Um separador aberto de antes do React ainda fala Livewire: 409, e o
        // layout antigo recarrega a página para a nova. Antes de tudo, para
        // nem chegar à sessão nem ao CSRF.
        $middleware->prepend(\App\Http\Middleware\SeparadorDeAntesDoReact::class);

        // Cabeçalhos de segurança em todas as respostas (HSTS, CSP, X-Frame,
        // nosniff, Referrer-Policy, Permissions-Policy; remove X-Powered-By).
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // A barra lateral aberta ou encolhida: escrito pelo React no browser,
        // lido pelo layout para desenhar o lugar da barra com a largura certa.
        // Cifrado, o servidor não o conseguia ler — e não guarda nada sensível.
        // `sos_consentimento`: escrito e lido pelo JavaScript do aviso de cookies
        // (App\Services\Privacidade\Consentimentos) — cifrado, o servidor não o lia.
        $middleware->encryptCookies(except: ['casca_aberta', 'sos_consentimento']);

        // Que língua fala este pedido (utilizador → empresa → cookie → pt).
        // Em append e não prepend: precisa da sessão iniciada (auth) e dos
        // cookies decifrados, e isso só existe depois do miolo do grupo web.
        // Continua a correr antes de qualquer controlador.
        $middleware->appendToGroup('web', \App\Http\Middleware\DefinirLingua::class);

        // Os GET à API não reescrevem a sessão (só renovam a actividade): um
        // pedido de fundo que acabasse depois de outro repunha a sessão velha.
        // Ver App\Http\Middleware\SessaoSoDeLeitura.
        $middleware->appendToGroup('web', \App\Http\Middleware\SessaoSoDeLeitura::class);

        // A personificação do super admin: prazo de duas horas, as acções que
        // nunca se fazem em nome de outra pessoa, e o fim quando a pessoa deixa
        // de poder estar lá. CEDO no grupo — antes do CheckSubscription, do
        // RecordLastLogin e sobretudo do SaiQuemFoiDesactivado, que de outro
        // modo punha fora a sessão do ADMIN por causa da conta do cliente.
        // Todas as APIs de sessão (`api/v1/*/react`, `api/v1/casca`) estão em
        // routes/web.php, portanto no grupo web: ficam cobertas.
        $middleware->appendToGroup('web', \App\Http\Middleware\PersonificacaoComPrazo::class);

        // Licença offline (build on-premise). No grupo web e, por dentro, um
        // no-op TOTAL enquanto LICENSE_ENFORCE não estiver ligado — na cloud
        // não lê sequer a licença. Cedo no grupo para trancar antes do miolo.
        $middleware->appendToGroup('web', \App\Http\Middleware\VerificarLicenca::class);

        // Check-in de licença à boleia do tráfego (build offline). Corre em
        // terminate, uma vez por intervalo, e é no-op na cloud (enforce off).
        $middleware->appendToGroup('web', \App\Http\Middleware\CheckinDeLicenca::class);

        // Middleware global para identificar tenant
        $middleware->append(\App\Http\Middleware\IdentifyTenant::class);
        
        // Middleware global para verificar se tenant está ativo
        $middleware->append(\App\Http\Middleware\CheckTenantActive::class);
        
        // Middleware global para verificar subscription (após identificar tenant)
        // IMPORTANTE: Só executa em rotas web que exigem autenticação
        $middleware->appendToGroup('web', \App\Http\Middleware\CheckSubscription::class);
        
        // Middleware para registrar último login.
        //
        // No GRUPO WEB e não no global, e a diferença é tudo: o middleware
        // global corre ANTES de a sessão arrancar, e ali o auth()->check() é
        // sempre falso. Esteve dez meses appended ao global sem nunca gravar
        // uma única entrada — a lista de empresas dizia "nunca entrou" de toda
        // a gente, incluindo de quem estava a facturar naquele minuto.
        //
        // O CheckSubscription e o DespacharAgtPendentes, aqui ao lado, já
        // estavam no grupo web pela mesma razão.
        $middleware->appendToGroup('web', \App\Http\Middleware\RecordLastLogin::class);

        // Uma conta desactivada é terminada no pedido seguinte (ver o middleware).
        $middleware->appendToGroup('web', \App\Http\Middleware\SaiQuemFoiDesactivado::class);

        // Empresa com NIF de pessoa singular leva ao ecrã onde se corrige.
        // No grupo web e depois do RecordLastLogin, pela mesma razão: precisa
        // da sessão de pé para saber qual é a empresa activa.
        $middleware->appendToGroup('web', \App\Http\Middleware\ExigirNifDeEmpresa::class);

        // Faz andar as submissões AGT aproveitando o tráfego.
        //
        // Este alojamento não tem worker de fila — mediram-se 165 tarefas
        // paradas e 160 documentos presos em "Enviada" sem se saber o
        // desfecho. Corre em `terminate`, depois de a resposta seguir para o
        // browser, com tranca de um minuto por empresa.
        $middleware->appendToGroup('web', \App\Http\Middleware\DespacharAgtPendentes::class);

        // Descobre de onde vieram as visitas, pela mesma razão e do mesmo modo.
        // O painel de países do ecrã de analytics existia desde sempre e nunca
        // mostrou nada: o país só era lido de um cabeçalho da Cloudflare, que
        // este alojamento não tem. Tranca de cinco minutos.
        $middleware->appendToGroup('web', \App\Http\Middleware\ResolverRegiaoDasVisitas::class);

        // Faz sair as notificações agendadas pela mesma via. O comando existia
        // e dependia de um `schedule:run` que este alojamento pode não ter — os
        // modelos ficavam activos no ecrã e não saía nada, sem erro nenhum.
        // Só é seguro porque o envio passou a ter memória do que já mandou
        // (notification_sends): sem isso, correr mais vezes seria mandar o
        // mesmo aviso a cada janela do dia. Tranca de dez minutos por empresa.
        $middleware->appendToGroup('web', \App\Http\Middleware\DespacharNotificacoes::class);

        // Emite as facturas de renovação pela mesma via, e pela mesma razão.
        // Faltava a peça do meio do ciclo: facturava-se ao contratar, cortava-se
        // o acesso no fim do período, e no meio não saía conta nenhuma. Tranca
        // de uma hora para TODA a plataforma — isto é trabalho da plataforma, o
        // pedido de quem navega serve só de relógio.
        $middleware->appendToGroup('web', \App\Http\Middleware\FacturarRenovacoes::class);

        // E avisa o cliente do que se passa com a subscrição dele — factura
        // emitida, a vencer, vencida, período a acabar. Pela mesma via e pela
        // mesma razão, mas com tranca e interruptor PRÓPRIOS: desligar a
        // emissão de facturas não pode calar os avisos das que já existem.
        // Depois do FacturarRenovacoes de propósito, para que uma factura
        // acabada de emitir já exista quando a varredura corre.
        $middleware->appendToGroup('web', \App\Http\Middleware\AvisarSubscricoes::class);

        // Empurra os erros novos para o agente externo (openclaw), que
        // depois avisa quem é preciso. Pela mesma via e pela mesma razão:
        // um alarme pendurado num cron que pode não existir é um alarme que
        // nunca toca. Os pedidos do próprio agente ficam de fora, senão ele
        // disparava o empurrão para si mesmo. Tranca de cinco minutos.
        $middleware->appendToGroup('web', \App\Http\Middleware\EmpurrarErrosParaOAgente::class);

        // Faz as cópias de segurança que estão na hora (a da plataforma de 6
        // em 6 horas, e as das empresas). Pela mesma via e pela mesma razão:
        // uma cópia pendurada num cron que não existe nunca se faz. Tranca de
        // cinco minutos, uma cópia no máximo por passagem.
        $middleware->appendToGroup('web', \App\Http\Middleware\FazerCopiasDevidas::class);

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
            // Descoberto pelo ensaio de carga: sem isto, TODA a API do
            // restaurante respondia 419 a um Bearer — a app móvel nunca a
            // conseguiu chamar. O PWA safava-se por levar sessão+CSRF, e por
            // isso ninguém deu por nada. A mesma postura do invoicing acima:
            // as rotas exigem `api.token` e o CSRF não protege um Bearer.
            'api/v1/restaurant/*',
            'api/v1/auth/*',
            'api/analytics/*',
            // Verificação de integridade do deploy: chamada máquina-a-máquina
            // a partir da consola, sem sessão nem formulário, logo sem token
            // CSRF possível. Fica protegida pelo token de manutenção no URL e
            // é apenas de LEITURA — devolve caminhos e tamanhos, nunca conteúdo
            // nem altera nada.
            'maintenance/*/verify-files',
            // Webhook do Meta: é o Meta que chama, sem sessão nem token CSRF
            // possível. Fica protegido pela assinatura HMAC (app_secret) e pelo
            // verify_token — ver MetaWebhookController.
            'webhooks/meta/*',
            // Webhook do KiandaStay (motor de reservas do hotel): idem — quem o
            // fecha e a assinatura HMAC com o segredo que o site devolveu ao
            // registar o webhook. Ver KiandaStayWebhookController.
            'webhooks/kiandastay/*',
        ]);

        // Middleware aliases
        $middleware->alias([
            // API do agente externo (openclaw)
            'agent.token'       => \App\Http\Middleware\AutenticaAgente::class,
            'agent.scope'       => \App\Http\Middleware\ExigeEscopoDoAgente::class,
            'agent.idempotencia' => \App\Http\Middleware\IdempotenciaDoAgente::class,
            'tenant.access' => \App\Http\Middleware\EnsureTenantAccess::class,
            'superadmin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'tenant.module' => \App\Http\Middleware\CheckTenantModule::class,
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'tenant.active' => \App\Http\Middleware\CheckTenantActive::class,
            'api.token' => \App\Http\Middleware\ResolveApiToken::class,
            'pwa.api' => \App\Http\Middleware\AutorizaApiDoPwa::class,
            // A porta de cada ecra do PWA, com a mesma regra que desenha o menu.
            'pwa' => \App\Http\Middleware\EntradaDoPwa::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sessão expirada em pedidos AJAX/JSON: devolver 419 JSON em vez do 302→/login
        // (HTML). Um fetch que segue o redirect recebia a página de login em HTML e
        // tentava lê-la como JSON — o ecrã "congela" sem erro visível. Um 419 limpo é
        // detetado no cliente (os ecrãs React e o keep-alive do layout).
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => 'Sessão expirada'], 419);
            }
        });

    })->create();
