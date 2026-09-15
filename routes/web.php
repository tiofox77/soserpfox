<?php

use Illuminate\Support\Facades\Route;

// Maintenance endpoints (token-protegidos) — para correr migrations/seeders/comandos remotamente
Route::prefix('maintenance/{token}')->controller(\App\Http\Controllers\MaintenanceController::class)->group(function () {
    Route::get('/', 'index')->name('maintenance.index');
    Route::get('/migrate', 'migrate')->name('maintenance.migrate');
    Route::get('/migrate-status', 'migrateStatus')->name('maintenance.migrate.status');
    Route::get('/seed/{seeder}', 'seed')->name('maintenance.seed');
    Route::get('/command/{cmd}', 'command')->where('cmd', '[a-z0-9:_-]+')->name('maintenance.command');
    Route::get('/farmacia-migration', 'farmaciaMigration')->name('maintenance.farmacia-migration');
    Route::get('/htaccess-fix', 'htaccessFix')->name('maintenance.htaccess-fix');
    Route::get('/pwa-cleanup', 'pwaCleanup')->name('maintenance.pwa-cleanup');
    Route::get('/logs', 'logs')->name('maintenance.logs');
    Route::get('/diag-tenant', 'diagTenant')->name('maintenance.diag-tenant');
    // Verificação de integridade do deploy (ver o comando deploy:verify).
    // POST porque o manifesto pode ter milhares de entradas.
    Route::post('/verify-files', 'verifyFiles')->name('maintenance.verify-files');
    // Rastreio de um artigo: vendido, saido e o que resta. So contagens.
    Route::get('/diag-produto', 'diagProduto')->name('maintenance.diag-produto');
    Route::get('/diag-roles', 'diagRoles')->name('maintenance.diag-roles');
});

// Os erros do React no browser de quem usa o sistema — vão para o registo
// agrupado (erros_do_sistema). Ver App\Http\Controllers\ErrosDoBrowserController.
Route::post('/erros-do-browser', \App\Http\Controllers\ErrosDoBrowserController::class)
    ->middleware('throttle:30,1')
    ->name('erros-do-browser');

// Licença offline (build on-premise): ecrã de activação e estado. Sempre
// acessível (o middleware da licença deixa `licenca*` passar) — é a porta para
// instalar uma licença nova com o sistema bloqueado. Inofensivo na cloud.
// Rotas do CLIENTE on-premise (ativação + 1.ª utilização). Só existem quando o
// enforce está ligado (a build offline) — na cloud nem sequer se registam.
if (config('licensing.enforce')) {
    Route::get('/licenca', [\App\Http\Controllers\LicencaController::class, 'index'])->name('licenca.index');
    Route::post('/licenca', [\App\Http\Controllers\LicencaController::class, 'guardar'])->name('licenca.guardar');
    // O cliente pede a licença ao fornecedor (online, ou gera código offline)
    Route::post('/licenca/solicitar', [\App\Http\Controllers\LicencaController::class, 'solicitar'])->name('licenca.solicitar');
    Route::post('/licenca/verificar', [\App\Http\Controllers\LicencaController::class, 'verificarPedido'])->name('licenca.verificar');
    // Puxar já o que o fornecedor mudou, sem esperar pela cadência automática.
    Route::post('/licenca/sincronizar', [\App\Http\Controllers\LicencaController::class, 'sincronizar'])->name('licenca.sincronizar');
    // Assistente de 1.ª utilização: cria empresa + admin a partir da licença.
    Route::get('/setup', [\App\Http\Controllers\Setup\AssistenteDeSetupController::class, 'index'])->name('setup');
    Route::post('/setup', [\App\Http\Controllers\Setup\AssistenteDeSetupController::class, 'finalizar'])->name('setup.finalizar');
}

// Analytics tracking (sem auth, sem CSRF — público)
//
// Com limite: sem ele, e sem CSRF, qualquer um enchia a tabela sem fim
// (auditoria de segurança de 2026-09-15).
Route::post('/api/analytics/track', [\App\Http\Controllers\AnalyticsController::class, 'track'])->middleware('throttle:120,1')->name('analytics.track');
// A escolha do aviso de cookies (RGPD): grava a prova e devolve o cookie.
Route::post('/api/analytics/consentimento', [\App\Http\Controllers\Privacidade\ConsentimentoController::class, 'guardar'])->middleware('throttle:30,1')->name('privacidade.consentimento');

// PWA: manifest dinâmico, ícones a partir do logo do sistema, service worker com versão automática
Route::get('/manifest.webmanifest', [\App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/manifest.json', [\App\Http\Controllers\PwaController::class, 'manifest']);
// O mascarável ANTES do genérico: `icon-{size}.png` com `[0-9]+` não apanha
// "maskable-192", mas a ordem torna a intenção óbvia a quem ler.
Route::get('/pwa/icon-maskable-{size}.png', [\App\Http\Controllers\PwaController::class, 'iconeMascaravel'])
    ->where('size', '[0-9]+')->name('pwa.icon.maskable');

Route::get('/pwa/icon-{size}.png', [\App\Http\Controllers\PwaController::class, 'icon'])->where('size', '[0-9]+')->name('pwa.icon');
Route::get('/pwa/icon-{size}x{size2}.png', [\App\Http\Controllers\PwaController::class, 'icon'])->where(['size' => '[0-9]+', 'size2' => '[0-9]+']);
Route::get('/sw.js', [\App\Http\Controllers\PwaController::class, 'serviceWorker'])->name('pwa.sw');

// Página de Changelog / Atualizações do sistema (autenticada para usar layout app)
Route::middleware(['auth'])->get('/changelog', [\App\Http\Controllers\ChangelogController::class, 'index'])->name('changelog');

// Landing Page
Route::get('/', [App\Http\Controllers\LandingController::class, 'home'])->name('landing.home');

// Documentos legais — públicos e sem dependências (têm de abrir mesmo a
// alguém que ainda não é cliente, e são referenciados no registo).
Route::view('/termos', 'legal.termos')->name('legal.termos');
Route::view('/privacidade', 'legal.privacidade')->name('legal.privacidade');
Route::view('/cookies', 'legal.cookies')->name('legal.cookies');

// O sitemap: só o que se indexa, com as páginas públicas das empresas. Ver o controlador.
Route::get('/sitemap.xml', [App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');

// Webhook do Meta (Facebook/Instagram/WhatsApp) — POR EMPRESA. Públicos: é o
// Meta que chama, máquina-a-máquina. Sem sessão nem CSRF (a isenção está em
// bootstrap/app.php). Seguros pelo verify_token (GET) e pela assinatura (POST).
Route::get('/webhooks/meta/{tenant}', [\App\Http\Controllers\Webhooks\MetaWebhookController::class, 'verify'])
    ->where('tenant', '[0-9]+')->name('webhooks.meta.verify');
Route::post('/webhooks/meta/{tenant}', [\App\Http\Controllers\Webhooks\MetaWebhookController::class, 'receive'])
    ->where('tenant', '[0-9]+')->name('webhooks.meta.receive');

// Webhook do KiandaStay — o motor de reservas do hotel entrega aqui cada
// reserva feita no site. Publico pela mesma razao, e fechado pela assinatura
// HMAC do segredo que o proprio site devolveu ao registar o webhook.
Route::post('/webhooks/kiandastay/{tenant}', [\App\Http\Controllers\Webhooks\KiandaStayWebhookController::class, 'receive'])
    ->where('tenant', '[0-9]+')->name('webhooks.kiandastay');

// Páginas de módulos (marketing)
Route::get('/modulos', [\App\Http\Controllers\ModulePagesController::class, 'index'])->name('modules.index');
Route::get('/modulos/{slug}', [\App\Http\Controllers\ModulePagesController::class, 'show'])->name('modules.show');
Route::post('/contact', [App\Http\Controllers\ContactController::class, 'store'])->name('contact.store');

// Custom Register Wizard
Route::get('/subscrever/{plan}', function (string $plan) {
    $plano = \App\Models\Plan::query()
        ->where('slug', $plan)
        ->where('is_active', true)
        ->firstOrFail();

    $tracking = request()->only([
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'fbclid', 'gclid',
    ]);

    return redirect()->route('register', array_merge($tracking, [
        'plan' => $plano->slug,
    ]));
})->where('plan', '[a-z0-9-]+')->name('subscribe.plan');

// O registo de uma conta nova: a página e as acções do assistente (React).
Route::get('/register', [\App\Http\Controllers\Registo\RegistoController::class, 'index'])->name('register');
Route::prefix('register')->name('register.')->controller(\App\Http\Controllers\Registo\RegistoController::class)->group(function () {
    // COM LIMITE (auditoria de 2026-09-15): o «Próximo» diz se um email ou um
    // NIF já estão registados, e sem travão perguntava-se isso sem fim — a
    // lista de quem é cliente, a um pedido de cada vez.
    Route::post('/seguinte', 'seguinte')->middleware('throttle:20,10')->name('seguinte');
    Route::post('/anterior', 'anterior')->middleware('throttle:60,1')->name('anterior');
    Route::post('/outro-plano', 'outroPlano')->middleware('throttle:60,1')->name('outro-plano');
    Route::put('/progresso', 'guardar')->middleware('throttle:120,1')->name('guardar');
    Route::delete('/progresso', 'recomecar')->middleware('throttle:20,1')->name('recomecar');
    Route::post('/', 'registar')->middleware('throttle:10,1')->name('registar');
});

// User Invitation Routes
Route::get('/invitation/{token}', [App\Http\Controllers\InvitationController::class, 'show'])->name('invitation.accept');
Route::post('/invitation/{token}', [App\Http\Controllers\InvitationController::class, 'accept'])->middleware('throttle:10,10')->name('invitation.accept.post');

// Auth routes (sem register padrão)
Auth::routes(['register' => false]);

// As duas portas fechadas — ecrãs React (ver EntradaController).
Route::get('/tenant-deactivated', [\App\Http\Controllers\EntradaController::class, 'empresaDesactivada'])->name('tenant.deactivated');
Route::get('/subscription-expired', [\App\Http\Controllers\EntradaController::class, 'subscricaoExpirada'])->middleware('auth')->name('subscription.expired');

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Compatibilidade: PWAs instalados antes do redesenho do manifest tinham start_url=/dashboard
// e shortcut POS=/pos — redirecionamos para as rotas válidas em vez de devolver 404.
// A página inicial do PWA é o POS Offline, por isso /dashboard cai lá (abre mesmo offline).
Route::get('/dashboard', fn() => redirect('/invoicing/offline/pos'));
Route::get('/pos', fn() => redirect('/invoicing/offline/pos'));

// PWA Offline Page
Route::get('/offline', function () {
    return view('offline');
})->name('offline');

// My Account Route
Route::middleware(['auth'])->group(function () {
    Route::get('/my-account', \App\Support\EcraReact::pagina('conta/minha-conta', 'A Minha Conta'))->name('my-account');

    /*
     * O DICIONÁRIO DOS ECRÃS EM REACT.
     *
     * O mesmo `lang/{lingua}.json` do Blade, servido uma vez a quem trabalha
     * em inglês ou francês. Em português não se chega aqui — a chave já é a
     * frase. A marca da versão vai na morada, por isso pode ficar guardado
     * para sempre: um dicionário novo tem morada nova.
     */
    Route::get('/react/traducoes/{marca}.json', function (string $marca) {
        abort_unless(\App\Support\DicionarioDoReact::precisa(), 404);

        return response()
            ->json(\App\Support\DicionarioDoReact::frases())
            ->setMaxAge(31536000)
            ->setPublic();
    })->name('react.traducoes');

    // Dados da Empresa — identificação, contactos, endereço, logótipo e regime
    // fiscal AGT (a alteração de regime propaga-se via TaxRegimeSyncer).
    //
    // ESTAVA SÓ ATRÁS DO `auth`: qualquer utilizador com sessão abria a página
    // e podia GRAVAR — mudar o NIF, o nome, e sobretudo o REGIME FISCAL, que
    // se propaga aos impostos, às definições de facturação e a todos os
    // produtos. Um caixa punha a empresa inteira no regime errado.
    Route::middleware('permission:settings.view')
        ->get('/empresa', \App\Support\EcraReact::pagina('empresa/dados', 'Dados da Empresa'))->name('company.profile');

    // As cópias de segurança da empresa — só os dados dela. Quem gere a conta;
    // a API recusa os outros, e a página não abre sem isso.
    Route::get('/empresa/copias-de-seguranca', \App\Support\EcraReact::pagina('empresa/copias', 'Cópias de segurança', [], function () {
        abort_unless(auth()->user()?->canManageAccount(), 403);

        return [];
    }))->name('company.copias');

    // O retorno do Google, da Microsoft e do Dropbox depois de autorizar um
    // destino de cópias. Um endereço só, registado na consola de cada um.
    Route::get('/copias/oauth/retorno', \App\Http\Controllers\Copias\RetornoOAuthController::class)->name('copias.oauth.retorno');
});

// Keep-alive: ping leve para manter a sessão viva enquanto o utilizador
// tem a página aberta. Devolve 204 No Content; o simples facto da request
// passar pelo middleware web renova o cookie de sessão.
Route::middleware(['web', 'auth'])
    ->get('/keep-alive', fn() => response()->noContent())
    ->name('keep-alive');

// O endpoint único de provisionamento da Medical Connect saiu (auditoria de
// segurança de 2026-09-13): tinha o segredo escrito no código e criava
// empresa e utilizadores por um GET. Já foi usado; o controlador fica no
// histórico do git.

/*
 * OS UTILIZADORES, OS PAPÉIS E OS CONVITES.
 *
 * As três páginas estavam TODAS atrás de `users.manage` — a permissão mais
 * larga que existe — enquanto oito permissões mais finas estavam declaradas e
 * ninguém as pedia. Agora cada porta pede a sua, com o `users.manage` a valer
 * como guarda-chuva: quem já o tem não perde nada, e quem só precisa de VER a
 * lista pode passar a tê-lo sem levar junto o poder de apagar contas.
 */
Route::middleware(['auth'])->prefix('users')->name('users.')->group(function () {
    Route::middleware('permission:users.view|users.manage')
        ->get('/', \App\Support\EcraReact::pagina('utilizadores/lista', 'Gestão de Utilizadores'))->name('index');
    Route::middleware('permission:users.roles.manage|users.permissions|users.manage')
        ->get('/roles-permissions', \App\Support\EcraReact::pagina('utilizadores/papeis', 'Papéis e Permissões'))->name('roles-permissions');
    Route::middleware('permission:users.invite|users.manage')
        ->get('/invitations', \App\Support\EcraReact::pagina('utilizadores/convites', 'Convites'))->name('invitations');
});

/*
 * A API DO PAINEL DA PLATAFORMA.
 *
 * GRUPO PRÓPRIO, e por duas razões: aqui não há empresa no escopo — quem olha é
 * o dono da plataforma e o que conta são TODAS as empresas — e o `subscription`
 * não se aplica, porque o dono não é subscritor de nada. O `superadmin` é a
 * única porta, e cada controlador não precisa de mais nada.
 */
// A CASCA: o topo de todas as páginas (empresa activa, contador, sino, mensagens
// da plataforma). Só sessão — tem de responder também com a subscrição acabada.
Route::middleware(['auth'])->prefix('api/v1/casca')->name('api.casca.')->group(function () {
    $c = \App\Http\Controllers\Api\Casca\CascaApiController::class;

    Route::get('/topo', [$c, 'topo'])->name('topo');
    Route::post('/empresas/{id}/entrar', [$c, 'trocarDeEmpresa'])->whereNumber('id')->name('empresa');
    Route::get('/notificacoes', [$c, 'notificacoes'])->name('notificacoes');
    Route::post('/notificacoes/lidas', [$c, 'marcarTodasComoLidas'])->name('notificacoes.lidas');
    Route::post('/notificacoes/{id}/lida', [$c, 'marcarComoLida'])->name('notificacoes.lida');
    Route::delete('/notificacoes/{id}', [$c, 'apagar'])->name('notificacoes.apagar');
    Route::delete('/notificacoes', [$c, 'limparTodas'])->name('notificacoes.limpar');
    Route::get('/mensagens', [$c, 'mensagens'])->name('mensagens');
    Route::post('/mensagens/{id}/dispensar', [$c, 'dispensar'])->whereNumber('id')->name('mensagens.dispensar');
    Route::get('/avisos', [$c, 'avisos'])->name('avisos');
    Route::get('/inicio', [$c, 'inicio'])->name('inicio');
    // Voltar à plataforma a meio de uma personificação. Aqui, e não no grupo
    // da plataforma: quem carrega no botão é, para o sistema, a pessoa da
    // empresa — o `superadmin` recusava-o.
    Route::post('/personificacao/sair', [$c, 'sairDaPersonificacao'])->name('personificacao.sair');
});

Route::middleware(['auth', 'superadmin'])->prefix('api/v1/plataforma/react')->name('api.plataforma.react.')->group(function () {
    // As cópias de segurança da base inteira, os destinos e as aplicações OAuth.
    Route::prefix('copias')->name('copias.')->group(function () {
        $c = \App\Http\Controllers\Api\Copias\CopiasDaPlataformaApiController::class;
        require base_path('routes/copias.php');
        Route::get('/aplicacoes', [$c, 'aplicacoes'])->name('aplicacoes');
        Route::put('/aplicacoes/{fornecedor}', [$c, 'guardarAplicacao'])->where('fornecedor', 'google|microsoft|dropbox')->name('aplicacoes.guardar');
    });

    Route::get('/inicio', [\App\Http\Controllers\Api\Plataforma\InicioApiController::class, 'index'])->name('inicio');

    Route::prefix('painel')->name('painel.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\PainelApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        // ENTRAR NUMA EMPRESA EM NOME DE ALGUÉM DE LÁ (personificação): o acto
        // com mais poder que há. Primeiro escolhe-se a pessoa; a entrada pede
        // confirmação e tem limite de tentativas — ver App\Services\Plataforma\Personificacao.
        Route::get('/empresas/{id}/utilizadores', [$c, 'utilizadoresDaEmpresa'])->whereNumber('id')->name('empresas.utilizadores');
        Route::post('/empresas/{id}/entrar', [$c, 'entrarNaEmpresa'])->whereNumber('id')
            ->middleware('throttle:10,1')->name('empresas.entrar');
    });

    Route::prefix('analitica')->name('analitica.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\AnalyticsApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        // O PERCURSO DE UM VISITANTE tem morada própria: abrir a ficha de
        // alguém não tem de recalcular os vinte agregados do ecrã todo.
        Route::get('/percurso/{visitante}', [$c, 'percurso'])->name('percurso');
    });

    Route::prefix('planos')->name('planos.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\PlanosApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/', [$c, 'guardar'])->name('criar');
        Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
        Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
        Route::post('/{id}/alternar', [$c, 'alternar'])->whereNumber('id')->name('alternar');
        Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
    });

    Route::prefix('empresas')->name('empresas.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\EmpresasApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/', [$c, 'guardar'])->name('criar');
        Route::get('/{id}', [$c, 'ver'])->whereNumber('id')->name('ver');
        Route::get('/{id}/ficha', [$c, 'ficha'])->whereNumber('id')->name('ficha');
        Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
        Route::post('/{id}/desactivar', [$c, 'desactivar'])->whereNumber('id')->name('desactivar');
        Route::post('/{id}/activar', [$c, 'activar'])->whereNumber('id')->name('activar');
        Route::post('/{id}/suspender', [$c, 'suspender'])->whereNumber('id')->name('suspender');
        // APAGAR MESMO: primeiro vê-se o que se perde, depois escreve-se o nome.
        Route::get('/{id}/apagar', [$c, 'oQueSePerde'])->whereNumber('id')->name('perdas');
        Route::delete('/{id}', [$c, 'apagarDefinitivo'])->whereNumber('id')->name('apagar');

        $u = \App\Http\Controllers\Api\Plataforma\EmpresaUtilizadoresApiController::class;

        Route::get('/{empresa}/utilizadores', [$u, 'index'])->whereNumber('empresa')->name('utilizadores');
        Route::get('/{empresa}/utilizadores/procurar', [$u, 'procurar'])->whereNumber('empresa')->name('utilizadores.procurar');
        Route::post('/{empresa}/utilizadores', [$u, 'juntar'])->whereNumber('empresa')->name('utilizadores.juntar');
        Route::put('/{empresa}/utilizadores/{utilizador}/papel', [$u, 'papel'])->whereNumber(['empresa', 'utilizador'])->name('utilizadores.papel');
        Route::delete('/{empresa}/utilizadores/{utilizador}', [$u, 'retirar'])->whereNumber(['empresa', 'utilizador'])->name('utilizadores.retirar');

        $p = \App\Http\Controllers\Api\Plataforma\EmpresaPlanoApiController::class;

        Route::get('/{empresa}/plano', [$p, 'index'])->whereNumber('empresa')->name('plano');
        Route::post('/{empresa}/plano/resumo', [$p, 'resumo'])->whereNumber('empresa')->name('plano.resumo');
        Route::put('/{empresa}/plano', [$p, 'guardar'])->whereNumber('empresa')->name('plano.guardar');
        Route::get('/{empresa}/plano-a-medida', [$p, 'medida'])->whereNumber('empresa')->name('medida');
        Route::post('/{empresa}/plano-a-medida', [$p, 'guardarMedida'])->whereNumber('empresa')->name('medida.guardar');
    });

    Route::prefix('facturacao')->name('facturacao.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\FacturacaoApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::put('/saft', [$c, 'guardarSaft'])->name('saft');

        Route::get('/subscricoes', [$c, 'subscricoes'])->name('subscricoes');
        Route::post('/subscricoes/resumo', [$c, 'resumo'])->name('subscricoes.resumo');
        Route::post('/subscricoes', [$c, 'guardarSubscricao'])->name('subscricoes.criar');
        Route::get('/subscricoes/{id}', [$c, 'fichaDaSubscricao'])->whereNumber('id')->name('subscricoes.ficha');
        Route::put('/subscricoes/{id}', [$c, 'guardarSubscricao'])->whereNumber('id')->name('subscricoes.guardar');
        Route::post('/subscricoes/{id}/cancelar', [$c, 'cancelarSubscricao'])->whereNumber('id')->name('subscricoes.cancelar');
        Route::delete('/subscricoes/{id}', [$c, 'apagarSubscricao'])->whereNumber('id')->name('subscricoes.apagar');
        Route::get('/empresas/{empresa}/subscricao-activa', [$c, 'subscricaoActivaDe'])->whereNumber('empresa')->name('subscricoes.activa');

        Route::get('/facturas', [$c, 'facturas'])->name('facturas');
        Route::get('/facturas/nova', [$c, 'novaFactura'])->name('facturas.nova');
        Route::post('/facturas', [$c, 'guardarFactura'])->name('facturas.criar');
        Route::get('/facturas/{id}', [$c, 'fichaDaFactura'])->whereNumber('id')->name('facturas.ficha');
        Route::put('/facturas/{id}', [$c, 'guardarFactura'])->whereNumber('id')->name('facturas.guardar');
        Route::post('/facturas/{id}/pagar', [$c, 'pagarFactura'])->whereNumber('id')->name('facturas.pagar');
        Route::delete('/facturas/{id}', [$c, 'apagarFactura'])->whereNumber('id')->name('facturas.apagar');

        Route::post('/pedidos/{id}/aprovar', [$c, 'aprovarPedido'])->whereNumber('id')->name('pedidos.aprovar');
        Route::post('/pedidos/{id}/recusar', [$c, 'recusarPedido'])->whereNumber('id')->name('pedidos.recusar');
    });

    Route::prefix('correio')->name('correio.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\CorreioApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/', [$c, 'guardar'])->name('criar');
        Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
        Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
        Route::post('/{id}/alternar', [$c, 'alternar'])->whereNumber('id')->name('alternar');
        Route::post('/{id}/padrao', [$c, 'padrao'])->whereNumber('id')->name('padrao');
        Route::post('/{id}/testar', [$c, 'testar'])->whereNumber('id')->name('testar');
        Route::post('/{id}/enviar-teste', [$c, 'enviarTeste'])->whereNumber('id')->name('enviar-teste');
        Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
    });

    Route::prefix('sms')->name('sms.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\SmsApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::put('/', [$c, 'guardar'])->name('guardar');
        Route::post('/saldo', [$c, 'saldo'])->name('saldo');
        Route::post('/testar', [$c, 'testar'])->name('testar');
        Route::get('/historico', [$c, 'historico'])->name('historico');
        Route::get('/modelos/{modelo}/previsualizar', [$c, 'previsualizar'])->whereNumber('modelo')->name('modelos.previsualizar');
        Route::put('/modelos/{id}', [$c, 'guardarModelo'])->whereNumber('id')->name('modelos.guardar');
    });

    Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\WhatsAppApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::put('/', [$c, 'guardar'])->name('guardar');
        Route::post('/testar', [$c, 'testarLigacao'])->name('testar');
        Route::get('/modelos-da-twilio', [$c, 'modelosDaTwilio'])->name('modelos');
        Route::post('/enviar-teste', [$c, 'enviarTeste'])->name('enviar-teste');
    });

    Route::prefix('chaves-saft')->name('chaves-saft.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\ChavesSaftApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/gerar', [$c, 'gerar'])->name('gerar');
        Route::post('/regenerar', [$c, 'regenerar'])->name('regenerar');
    });

    Route::prefix('sistema')->name('sistema.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\SistemaApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::put('/{grupo}', [$c, 'guardar'])->name('guardar');
        Route::post('/imagens/{chave}', [$c, 'enviarImagem'])->name('imagem');
    });

    Route::prefix('software')->name('software.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\SoftwareApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::put('/bloqueios', [$c, 'guardarBloqueios'])->name('bloqueios');
        Route::put('/produtor', [$c, 'guardarProdutor'])->name('produtor');
        Route::delete('/produtor', [$c, 'limparProdutor'])->name('produtor.limpar');
        Route::get('/empresas/{empresa}/prontidao', [$c, 'prontidao'])->whereNumber('empresa')->name('prontidao');
        Route::put('/ambiente', [$c, 'aplicarAmbiente'])->name('ambiente');
        Route::post('/agt/testar', [$c, 'testarLigacao'])->name('agt.testar');
        Route::post('/agt/operacao', [$c, 'operacao'])->name('agt.operacao');
    });

    Route::prefix('modulos')->name('modulos.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\ModulosApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/', [$c, 'guardar'])->name('criar');
        Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
        Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
        Route::post('/{id}/alternar', [$c, 'alternar'])->whereNumber('id')->name('alternar');
        Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
    });

    Route::prefix('contactos')->name('contactos.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\ContactosApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/{id}/marcar', [$c, 'marcar'])->whereNumber('id')->name('marcar');
        Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
    });

    Route::prefix('pedidos-de-estabelecimentos')->name('estabelecimentos.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\PedidosDeEstabelecimentosApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/{id}/aprovar', [$c, 'aprovar'])->whereNumber('id')->name('aprovar');
        Route::post('/{id}/recusar', [$c, 'recusar'])->whereNumber('id')->name('recusar');
    });

    Route::prefix('registo-de-emails')->name('emails.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\RegistoDeEmailsApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/limpar-antigos', [$c, 'limparAntigos'])->name('limpar');
        Route::get('/{id}', [$c, 'ver'])->whereNumber('id')->name('ver');
        Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
    });

    Route::get('/aparelhos-pwa', [\App\Http\Controllers\Api\Plataforma\AparelhosPwaApiController::class, 'index'])->name('aparelhos-pwa');

    Route::prefix('modelos-de-email')->name('modelos-de-email.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\ModelosDeEmailApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/', [$c, 'guardar'])->name('criar');
        Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
        Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
        Route::post('/{id}/alternar', [$c, 'alternar'])->whereNumber('id')->name('alternar');
        Route::get('/{id}/previsualizar', [$c, 'previsualizar'])->whereNumber('id')->name('previsualizar');
        Route::post('/{id}/enviar-teste', [$c, 'enviarTeste'])->whereNumber('id')->name('enviar-teste');
        Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
    });

    Route::prefix('avisos')->name('avisos.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\AvisosApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/', [$c, 'guardar'])->name('criar');
        Route::post('/alcance', [$c, 'alcance'])->name('alcance');
        Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
        Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
        Route::post('/{id}/alternar', [$c, 'alternar'])->whereNumber('id')->name('alternar');
        Route::get('/{id}/leituras', [$c, 'leituras'])->whereNumber('id')->name('leituras');
        Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
    });

    Route::prefix('sms-empresas')->name('sms-empresas.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\SmsParaEmpresasApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/rever', [$c, 'rever'])->name('rever');
        Route::post('/enviar', [$c, 'enviar'])->name('enviar');
    });

    Route::prefix('otimizacao')->name('otimizacao.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\OtimizacaoApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/limpar-opcache', [$c, 'limparOpcache'])->name('opcache');
        Route::post('/limpar-caches', [$c, 'limparCaches'])->name('caches');
        Route::post('/otimizar', [$c, 'otimizar'])->name('otimizar');
        Route::post('/user-ini', [$c, 'gerarIni'])->name('ini');
    });

    Route::prefix('comandos')->name('comandos.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\ComandosApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/seeder', [$c, 'semear'])->name('seeder');
        Route::delete('/historico', [$c, 'limparHistorico'])->name('historico');
        Route::post('/{chave}', [$c, 'correr'])->where('chave', '[a-z_]+')->name('correr');
    });

    Route::prefix('scripts')->name('scripts.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\ScriptsApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/log', [$c, 'log'])->name('log');
        Route::post('/correr', [$c, 'correr'])->name('correr');
        Route::delete('/log', [$c, 'limparLog'])->name('limpar-log');
    });

    Route::prefix('actualizacoes')->name('actualizacoes.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\ActualizacoesApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/releases', [$c, 'releases'])->name('releases');
        Route::post('/instalar', [$c, 'instalar'])->name('instalar');
    });

    Route::prefix('licenciamento')->name('licenciamento.')->group(function () {
        $c = \App\Http\Controllers\Api\Plataforma\LicenciamentoApiController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/licencas', [$c, 'emitir'])->name('emitir');
        Route::get('/instalacoes/{id}', [$c, 'instalacao'])->whereNumber('id')->name('instalacao');
        Route::put('/instalacoes/{id}/empresa', [$c, 'guardarEmpresa'])->whereNumber('id')->name('empresa');
        Route::post('/instalacoes/{id}/suspensao', [$c, 'alternarSuspensao'])->whereNumber('id')->name('suspensao');
        Route::post('/instalacoes/{id}/aviso', [$c, 'avisar'])->whereNumber('id')->name('aviso');
        Route::post('/instalacoes/{id}/renovar', [$c, 'renovar'])->whereNumber('id')->name('renovar');
        Route::post('/pedidos/{id}/aprovar', [$c, 'aprovarPedido'])->whereNumber('id')->name('pedidos.aprovar');
        Route::post('/pedidos/{id}/recusar', [$c, 'recusarPedido'])->whereNumber('id')->name('pedidos.recusar');
        Route::post('/versoes', [$c, 'publicarVersao'])->name('versoes');
        Route::put('/versoes/{id}/rollout', [$c, 'definirRollout'])->whereNumber('id')->name('rollout');
        Route::post('/alvos', [$c, 'adicionarAlvo'])->name('alvos');
        Route::delete('/alvos/{id}', [$c, 'removerAlvo'])->whereNumber('id')->name('alvos.remover');
    });
});

// Super Admin Routes
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', \App\Support\EcraReact::plataforma('plataforma/painel', 'Painel da Plataforma'))->name('dashboard');
    Route::get('/analytics', \App\Support\EcraReact::plataforma('plataforma/analitica', 'Analítica e visitantes'))->name('analytics');
    Route::get('/tenants', \App\Support\EcraReact::plataforma('plataforma/empresas', 'Empresas'))->name('tenants');
    Route::get('/restaurant-venue-requests', \App\Support\EcraReact::plataforma('plataforma/estabelecimentos', 'Pedidos de estabelecimentos'))->name('restaurant-venue-requests');
    Route::get('/modules', \App\Support\EcraReact::plataforma('plataforma/modulos', 'Módulos'))->name('modules');
    Route::get('/plans', \App\Support\EcraReact::plataforma('plataforma/planos', 'Planos'))->name('plans');
    Route::get('/billing', \App\Support\EcraReact::plataforma('plataforma/facturacao', 'Facturação da plataforma'))->name('billing');
    Route::get('/licenciamento', \App\Support\EcraReact::plataforma('plataforma/licenciamento', 'Licenciamento offline'))->name('licenciamento');

    // Que empresas usam o PWA, em que aparelhos e em que VERSÃO. Existe porque
    // um deploy do motor podia não chegar aos aparelhos e não havia como saber.
    Route::get('/aparelhos-pwa', \App\Support\EcraReact::plataforma('plataforma/aparelhos-pwa', 'Aparelhos PWA'))->name('aparelhos-pwa');
    Route::get('/system-updates', \App\Support\EcraReact::plataforma('plataforma/actualizacoes', 'Atualizações do Sistema'))->name('system-updates');
    Route::get('/system-commands', \App\Support\EcraReact::plataforma('plataforma/comandos', 'Comandos do Sistema'))->name('system-commands');
    Route::get('/script-runner', \App\Support\EcraReact::plataforma('plataforma/scripts', 'Executar Scripts'))->name('script-runner');
    Route::get('/system-settings', \App\Support\EcraReact::plataforma('plataforma/sistema', 'Definições do sistema'))->name('system-settings');
    // As cópias de segurança da base inteira: de 6 em 6 horas, destinos e restauro.
    Route::get('/copias-de-seguranca', \App\Support\EcraReact::plataforma('plataforma/copias', 'Cópias de segurança'))->name('copias');
    Route::get('/software-settings', \App\Support\EcraReact::plataforma('plataforma/software', 'Definições do software'))->name('software-settings');
    // O .user.ini gerado com os valores do formulário: é um ficheiro, e por isso uma rota de página.
    Route::get('/system-optimization/user-ini', [\App\Http\Controllers\Api\Plataforma\OtimizacaoApiController::class, 'descarregarIni'])->name('system-optimization.ini');
    Route::get('/system-optimization', \App\Support\EcraReact::plataforma('plataforma/otimizacao', 'Otimização do Sistema'))->name('system-optimization');
    Route::get('/email-templates', \App\Support\EcraReact::plataforma('plataforma/modelos-de-email', 'Modelos de email'))->name('email-templates');
    Route::get('/smtp-settings', \App\Support\EcraReact::plataforma('plataforma/correio', 'Servidores de correio'))->name('smtp-settings');
    Route::get('/email-logs', \App\Support\EcraReact::plataforma('plataforma/registo-de-emails', 'Registo de emails'))->name('email-logs');
    // Avisos e mensagens do dono da plataforma para as empresas.
    Route::get('/mensagens', \App\Support\EcraReact::plataforma('plataforma/avisos', 'Mensagens às empresas'))->name('mensagens');
    Route::get('/sms-settings', \App\Support\EcraReact::plataforma('plataforma/sms', 'SMS'))->name('sms-settings');
    // Enviar um SMS às empresas — a todas, ou só às escolhidas.
    Route::get('/sms-empresas', \App\Support\EcraReact::plataforma('plataforma/sms-empresas', 'SMS às empresas'))->name('sms-empresas');
    Route::get('/whatsapp-notifications', \App\Support\EcraReact::plataforma('plataforma/whatsapp', 'WhatsApp'))->name('whatsapp-notifications');
    Route::get('/saft-configuration', \App\Support\EcraReact::plataforma('plataforma/chaves-saft', 'Chaves do SAF-T'))->name('saft');
    // A descarga das chaves: é um ficheiro, e por isso uma rota de página.
    Route::get('/saft-configuration/descarregar/{qual}/{formato}', [\App\Http\Controllers\Api\Plataforma\ChavesSaftApiController::class, 'descarregar'])->name('saft.descarregar');
    Route::get('/contact-messages', \App\Support\EcraReact::plataforma('plataforma/contactos', 'Mensagens de contacto'))->name('contact-messages');
});

// PWA Offline — Faturação (rotas standalone com auth por sessão)
// Entrada do PWA — FORA do `auth`, senão a página de entrada mandava o
// utilizador para o login para poder mostrar o login. Precisa de sessão para
// o token CSRF do formulário, por isso fica no grupo `web`.
Route::get('/invoicing/offline/login', \App\Support\PaginaDoPwa::rota('entrada'))
    ->name('invoicing.offline.login');

// Esqueci o PIN — sem rede. Também FORA do `auth`: quem cá chega não tem
// sessão nem rede, e tudo o que a página faz é local (o gestor autoriza com o
// seu PIN, o aparelho calcula o verificador novo e põe-no na fila). O servidor
// só decide quando a fila subir, com sessão.
Route::get('/invoicing/offline/pin-esquecido', \App\Support\PaginaDoPwa::rota('pin-esquecido'))
    ->name('invoicing.offline.pin-esquecido');

// Sair: termina a sessão e volta à entrada do PWA. Não apaga nada do
// aparelho — pode haver vendas por enviar.
// Sem `auth`: quando a saída é feita sem rede, ela vai para a fila e chega
// aqui mais tarde — se a sessão já tiver caído entretanto, isto tem de ser um
// nada-a-fazer e não um 302 para o login, senão o trabalho ficava a falhar
// para sempre na fila do aparelho.
Route::post('/invoicing/offline/sair', \App\Http\Controllers\Invoicing\PwaSairController::class)
    ->name('invoicing.offline.sair');

Route::middleware(['auth'])->prefix('invoicing/offline')->name('invoicing.offline.')->group(function () {
    // O funcionario define/muda o PIN de turno (login offline no POS).
    Route::get('pin', \App\Support\EcraReact::pagina('facturacao/definir-pin', 'PIN de turno'))->name('pin');
    // CADA ECRÃ TEM A PORTA QUE O MENU USA PARA O DESENHAR.
    //
    // O middleware `pwa:<chave>` pergunta o mesmo que o menu — módulo da
    // empresa, permissão do utilizador, e a escolha da empresa nas definições
    // (ver App\Support\MenuDoPwa). Antes o menu escondia a entrada e a rota
    // abria na mesma a quem escrevesse o endereço: esconder um botão não é
    // fechar uma porta, e as duas regras viviam em ficheiros diferentes, por
    // isso discordavam.
    //
    // O 403 é deliberado: o service worker só guarda respostas OK, portanto um
    // ecrã a que o utilizador não tem direito nem chega a ficar no aparelho.
    Route::get('/', \App\Support\PaginaDoPwa::rota('inicio'))->name('index');

    // O MOLDE de cada documento: o próprio modelo de impressão do servidor,
    // renderizado com marcas no lugar dos valores, para o aparelho o
    // preencher sem rede. Um desenho só — ver App\Services\Pwa\MoldeDoDocumento.
    Route::get('/molde/{tipo}', function (\Illuminate\Http\Request $request, string $tipo) {
        abort_unless(in_array($tipo, \App\Services\Pwa\MoldeDoDocumento::TIPOS, true), 404);
        $tenant = \App\Models\Tenant::find(activeTenantId());
        abort_if(!$tenant, 404);

        $html = app(\App\Services\Pwa\MoldeDoDocumento::class)->render($tipo, $tenant);

        // O molde leva o logótipo embutido e passa dos 700 KB. Só desce
        // outra vez quando muda: o aparelho manda a etiqueta que guardou.
        $etiqueta = '"' . md5($html) . '"';
        if ($request->header('If-None-Match') === $etiqueta) {
            return response('', 304, ['ETag' => $etiqueta]);
        }

        return response($html, 200, [
            'Content-Type'  => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'ETag'          => $etiqueta,
        ]);
    })->name('molde');
    Route::middleware('pwa:catalogo')->get('/catalog', \App\Support\PaginaDoPwa::rota('catalogo'))->name('catalog');
    Route::middleware('pwa:clientes')->group(function () {
        Route::get('/clients', \App\Support\PaginaDoPwa::rota('clientes'))->name('clients');
        Route::get('/clients/new', \App\Support\PaginaDoPwa::rota('novo-cliente'))->name('client-new');
    });
    Route::middleware('pwa:documentos')->group(function () {
        Route::get('/drafts', \App\Support\PaginaDoPwa::rota('documentos'))->name('drafts');
        Route::get('/drafts/new', \App\Support\PaginaDoPwa::rota('novo-documento'))->name('draft-new');
    });
    Route::middleware('pwa:pos')->get('/pos', \App\Support\PaginaDoPwa::rota('pos'))->middleware('permission:invoicing.pos.access')->name('pos');
    Route::middleware('pwa:restaurante')->get('/restaurant', \App\Support\PaginaDoPwa::rota('restaurante'))->name('restaurant');
    // Saída do PWA → redireciona para a 1ª área a que o utilizador tem permissão
    Route::get('/exit', \App\Http\Controllers\Invoicing\PwaExitController::class)->name('exit');
});

// API Auth (token Bearer) — app móvel
Route::prefix('api/v1/auth')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login'])->middleware('throttle:20,1')->name('api.auth.login');
    Route::middleware('api.token')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\AuthController::class, 'me'])->name('api.auth.me');
        Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout'])->name('api.auth.logout');
    });
});

// API REST — Faturação (sessão web OU token Bearer; api.token autentica).
// 'subscription' DEPOIS de 'api.token': sem isto, a app móvel com token Bearer
// contornava o CheckSubscription por completo e um tenant com subscrição
// expirada continuava a faturar pelo telemóvel.
//
// SEM PERMISSÕES ATÉ 2026-09-13: qualquer membro da empresa anulava um artigo
// já produzido (sai da conta e vai para desperdício — a fraude clássica do
// empregado), juntava contas e fechava-as. Agora as mesmas permissões da API
// React das comandas, e o módulo.
Route::middleware(['api.token', 'subscription', 'tenant.module:restaurant'])->prefix('api/v1/restaurant')->name('api.restaurant.')->group(function () {
    Route::get('/snapshot', [\App\Http\Controllers\Api\RestaurantController::class, 'snapshot'])->middleware('permission:restaurant.orders.view|restaurant.floor.view');
    Route::post('/orders', [\App\Http\Controllers\Api\RestaurantController::class, 'open'])->middleware('permission:restaurant.orders.create|restaurant.checkout.charge');
    Route::post('/orders/{order}/items', [\App\Http\Controllers\Api\RestaurantController::class, 'add'])->middleware('permission:restaurant.orders.edit|restaurant.checkout.charge');
    Route::post('/orders/{order}/confirm', [\App\Http\Controllers\Api\RestaurantController::class, 'confirm'])->middleware('permission:restaurant.orders.edit|restaurant.checkout.charge');
    Route::post('/orders/{order}/transfer', [\App\Http\Controllers\Api\RestaurantController::class, 'transfer'])->middleware('permission:restaurant.orders.transfer');
    Route::post('/orders/{order}/merge', [\App\Http\Controllers\Api\RestaurantController::class, 'merge'])->middleware('permission:restaurant.orders.split');
    Route::post('/orders/{order}/items/{item}/void', [\App\Http\Controllers\Api\RestaurantController::class, 'voidItem'])->middleware('permission:restaurant.orders.cancel');
    Route::post('/orders/{order}/checkout', [\App\Http\Controllers\Api\RestaurantController::class, 'checkout'])->middleware('permission:restaurant.checkout.charge');

    // A comanda feita sem rede, reposta de uma vez: mesa, artigos, cozinha e
    // recebimento. As rotas acima encadeiam-se por id do servidor e por isso
    // não servem offline — ver ComandaOfflineController.
    Route::post('/offline/comanda', [\App\Http\Controllers\Api\Restaurant\ComandaOfflineController::class, 'store'])
        ->middleware('permission:restaurant.orders.create|restaurant.orders.edit|restaurant.checkout.charge')
        ->name('offline.comanda');
});

Route::middleware(['api.token', 'subscription'])->prefix('api/v1/invoicing')->name('api.invoicing.')->group(function () {
    Route::get('/ping', [\App\Http\Controllers\Api\Invoicing\SyncController::class, 'ping'])->name('ping');
    // A permissão de cada porta, para a sessão E para o operador indicado — ver AutorizaApiDoPwa.
    Route::get('/sync', [\App\Http\Controllers\Api\Invoicing\SyncController::class, 'index'])->middleware('pwa.api:ler')->name('sync');
    Route::get('/diagnose', [\App\Http\Controllers\Api\Invoicing\SyncController::class, 'diagnose'])->middleware('pwa.api:ler')->name('diagnose');
    Route::post('/clients', [\App\Http\Controllers\Api\Invoicing\ClientController::class, 'store'])->middleware('pwa.api:cliente')->name('clients.store');
    Route::post('/drafts', [\App\Http\Controllers\Api\Invoicing\DraftController::class, 'store'])->middleware('pwa.api:emitir')->name('drafts.store');
    Route::post('/pos/sale', [\App\Http\Controllers\Api\Invoicing\PosSaleController::class, 'store'])->middleware('pwa.api:vender')->name('pos.sale.store');
    // Um PIN de turno reposto no aparelho sem rede, autorizado por um gestor
    // ao balcão. Chega pela fila; o servidor confirma quem pode e regista.
    // Com `auth` explícito: o grupo não o tem, e aqui mexe-se em credenciais.
    Route::post('/pin/repor', \App\Http\Controllers\Api\Invoicing\ReporPinController::class)
        ->middleware('auth')->name('pin.repor');
    // Turno POS (abertura/fecho offline → sincronizado quando online)
    Route::get('/pos/shift', [\App\Http\Controllers\Api\Invoicing\PosShiftController::class, 'status'])->middleware('pwa.api:ler')->name('pos.shift.status');
    Route::post('/pos/shift/open', [\App\Http\Controllers\Api\Invoicing\PosShiftController::class, 'open'])->middleware('pwa.api:vender')->name('pos.shift.open');
    Route::post('/pos/shift/close', [\App\Http\Controllers\Api\Invoicing\PosShiftController::class, 'close'])->middleware('pwa.api:vender')->name('pos.shift.close');
    // Listagem genérica (read-only) das áreas de faturação para a app móvel
    Route::get('/list/{area}', [\App\Http\Controllers\Api\Invoicing\InvoicingListController::class, 'index'])->name('list');
    Route::get('/dashboard-stats', [\App\Http\Controllers\Api\Invoicing\InvoicingListController::class, 'dashboard'])->name('dashboard-stats');
    Route::get('/detail/{area}/{id}', [\App\Http\Controllers\Api\Invoicing\InvoicingListController::class, 'detail'])->name('detail');
    // CRUD das áreas de dados-mestre
    Route::post('/list/{area}', [\App\Http\Controllers\Api\Invoicing\InvoicingListController::class, 'store'])->name('list.store');
    Route::put('/list/{area}/{id}', [\App\Http\Controllers\Api\Invoicing\InvoicingListController::class, 'update'])->name('list.update');
    Route::delete('/list/{area}/{id}', [\App\Http\Controllers\Api\Invoicing\InvoicingListController::class, 'destroy'])->name('list.destroy');

    /*
     * OS ECRÃS EM REACT.
     *
     * Prefixo próprio (`/react/`) para não se confundirem com a API da app
     * móvel, que tem outra forma e outros compromissos. A permissão é
     * verificada em cada controlador — o grupo autentica, não autoriza.
     */
    // O módulo de cada área (hotel, RH, restaurante…) confere-se aqui — ver ModuloDaApi.
    Route::prefix('react')->name('react.')->middleware(\App\Http\Middleware\ModuloDaApi::class)->group(function () {
        Route::get('/sales-invoices', [\App\Http\Controllers\Api\Invoicing\SalesInvoiceApiController::class, 'index'])
            ->name('sales-invoices.index');
        // Apagar um RASCUNHO — nunca um documento fiscal emitido, que se
        // rectifica por nota de crédito. As guardas estão no controlador.
        Route::delete('/sales-invoices/{id}', [\App\Http\Controllers\Api\Invoicing\SalesInvoiceApiController::class, 'eliminar'])
            ->whereNumber('id')->name('sales-invoices.eliminar');
        Route::get('/sales-invoices/opcoes', [\App\Http\Controllers\Api\Invoicing\SalesInvoiceApiController::class, 'opcoes'])
            ->name('sales-invoices.opcoes');

        /*
         * AS LISTAS QUE TÊM TODAS A MESMA FORMA.
         *
         * Proformas de venda e de compra, orçamentos, facturas de compra e
         * recibos. O que muda entre elas está no `TiposDeDocumento`; aqui há
         * uma rota só. A permissão de cada tipo é exigida lá dentro.
         */
        Route::get('/documentos/{tipo}/opcoes', [\App\Http\Controllers\Api\Invoicing\DocumentosApiController::class, 'opcoes'])
            ->where('tipo', '[a-z-]+')->name('documentos.opcoes');
        // O HISTÓRICO DE CONVERSÕES vem ANTES do `{tipo}` genérico: uma rota
        // mais larga declarada primeiro apanharia `/orcamentos/12/historico`.
        Route::get('/documentos/{tipo}/{id}/historico', [\App\Http\Controllers\Api\Invoicing\DocumentosApiController::class, 'historico'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->name('documentos.historico');
        // A FICHA de um documento: o modal de ver, sem sair da lista.
        Route::get('/documentos/{tipo}/{id}', [\App\Http\Controllers\Api\Invoicing\DocumentosApiController::class, 'mostrar'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->name('documentos.mostrar');
        // Converter uma proposta em factura — nasce em rascunho.
        Route::post('/documentos/{tipo}/{id}/converter', [\App\Http\Controllers\Api\Invoicing\DocumentosApiController::class, 'converter'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->name('documentos.converter');
        // Eliminar. As facturas de compra não passam por aqui: anulam-se.
        Route::delete('/documentos/{tipo}/{id}', [\App\Http\Controllers\Api\Invoicing\DocumentosApiController::class, 'destroy'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->name('documentos.destroy');

        Route::get('/documentos/{tipo}', [\App\Http\Controllers\Api\Invoicing\DocumentosApiController::class, 'index'])
            ->where('tipo', '[a-z-]+')->name('documentos.index');

        /*
         * EMITIR PROPOSTAS. Só proformas e orçamentos — o que não tem
         * número fiscal, assinatura nem stock. Ver TiposDeDocumento::editaveis.
         */
        Route::get('/emissor/{tipo}/opcoes', [\App\Http\Controllers\Api\Invoicing\EmissorApiController::class, 'opcoes'])
            ->where('tipo', '[a-z-]+')->name('emissor.opcoes');
        Route::post('/emissor/{tipo}/calcular', [\App\Http\Controllers\Api\Invoicing\EmissorApiController::class, 'calcular'])
            ->where('tipo', '[a-z-]+')->name('emissor.calcular');
        Route::post('/emissor/{tipo}', [\App\Http\Controllers\Api\Invoicing\EmissorApiController::class, 'guardar'])
            ->where('tipo', '[a-z-]+')->name('emissor.guardar');
        // Duplicar: devolve CONTEÚDO para o editor abrir em branco, e não um
        // documento novo já gravado. Ver DuplicaDocumento. Antes do `{id}`
        // genérico, que é onde acabaria se viesse depois.
        Route::get('/emissor/{tipo}/{id}/duplicar', [\App\Http\Controllers\Api\Invoicing\EmissorApiController::class, 'duplicar'])->where('tipo', '[a-z-]+')->whereNumber('id')->name('emissor.duplicar');
        Route::get('/emissor/{tipo}/{id}', [\App\Http\Controllers\Api\Invoicing\EmissorApiController::class, 'abrir'])->where('tipo', '[a-z-]+')->whereNumber('id')->name('emissor.abrir');
        Route::put('/emissor/{tipo}/{id}', [\App\Http\Controllers\Api\Invoicing\EmissorApiController::class, 'actualizar'])->where('tipo', '[a-z-]+')->whereNumber('id')->name('emissor.actualizar');

        // Recibos. O pagamento lança-se pelos ganchos do modelo — aqui não
        // se toca no paid_amount.
        Route::get('/recibos/opcoes', [\App\Http\Controllers\Api\Invoicing\ReciboApiController::class, 'opcoes'])->name('recibos.opcoes');
        Route::get('/recibos/facturas', [\App\Http\Controllers\Api\Invoicing\ReciboApiController::class, 'facturas'])->name('recibos.facturas');
        Route::post('/recibos', [\App\Http\Controllers\Api\Invoicing\ReciboApiController::class, 'guardar'])->name('recibos.guardar');
        Route::get('/recibos/{id}', [\App\Http\Controllers\Api\Invoicing\ReciboApiController::class, 'mostrar'])->whereNumber('id')->name('recibos.mostrar');

        // Notas de crédito e de débito. Zero lógica fiscal aqui: tudo no
        // EmissorDeNotas, o mesmo que o Livewire chama.
        Route::get('/notas/{tipo}/opcoes', [\App\Http\Controllers\Api\Invoicing\NotasApiController::class, 'opcoes'])
            ->where('tipo', 'credito|debito')->name('notas.opcoes');
        Route::get('/notas/{tipo}/facturas', [\App\Http\Controllers\Api\Invoicing\NotasApiController::class, 'facturas'])
            ->where('tipo', 'credito|debito')->name('notas.facturas');
        Route::get('/notas/{tipo}/facturas/{factura}/linhas', [\App\Http\Controllers\Api\Invoicing\NotasApiController::class, 'linhas'])
            ->where('tipo', 'credito|debito')->whereNumber('factura')->name('notas.linhas');
        Route::post('/notas/{tipo}', [\App\Http\Controllers\Api\Invoicing\NotasApiController::class, 'guardar'])
            ->where('tipo', 'credito|debito')->name('notas.guardar');
        Route::get('/notas/{tipo}/{id}', [\App\Http\Controllers\Api\Invoicing\NotasApiController::class, 'mostrar'])->where('tipo', '[a-z-]+')->whereNumber('id')->name('notas.mostrar');

        // Factura de venda (FT/FR). Zero lógica fiscal aqui: tudo no
        // EmissorDeFacturas, o mesmo que o Livewire chama.
        Route::get('/factura/opcoes', [\App\Http\Controllers\Api\Invoicing\FacturaApiController::class, 'opcoes'])->name('factura.opcoes');
        Route::post('/factura/calcular', [\App\Http\Controllers\Api\Invoicing\FacturaApiController::class, 'calcular'])->name('factura.calcular');
        Route::post('/factura', [\App\Http\Controllers\Api\Invoicing\FacturaApiController::class, 'guardar'])->name('factura.guardar');
        Route::get('/factura/{id}/duplicar', [\App\Http\Controllers\Api\Invoicing\FacturaApiController::class, 'duplicar'])->whereNumber('id')->name('factura.duplicar');
        Route::get('/factura/{id}', [\App\Http\Controllers\Api\Invoicing\FacturaApiController::class, 'abrir'])->whereNumber('id')->name('factura.abrir');
        Route::put('/factura/{id}', [\App\Http\Controllers\Api\Invoicing\FacturaApiController::class, 'actualizar'])->whereNumber('id')->name('factura.actualizar');

        // Factura de compra. Zero lógica de negócio aqui: tudo no
        // EmissorDeCompras, o mesmo que o Livewire chama.
        Route::get('/compra/opcoes', [\App\Http\Controllers\Api\Invoicing\CompraApiController::class, 'opcoes'])->name('compra.opcoes');
        Route::post('/compra/calcular', [\App\Http\Controllers\Api\Invoicing\CompraApiController::class, 'calcular'])->name('compra.calcular');
        Route::post('/compra', [\App\Http\Controllers\Api\Invoicing\CompraApiController::class, 'guardar'])->name('compra.guardar');
        Route::get('/compra/{id}/duplicar', [\App\Http\Controllers\Api\Invoicing\CompraApiController::class, 'duplicar'])->whereNumber('id')->name('compra.duplicar');
        Route::get('/compra/{id}', [\App\Http\Controllers\Api\Invoicing\CompraApiController::class, 'abrir'])->whereNumber('id')->name('compra.abrir');
        Route::put('/compra/{id}', [\App\Http\Controllers\Api\Invoicing\CompraApiController::class, 'actualizar'])->whereNumber('id')->name('compra.actualizar');
        /*
         * ANULAR uma factura de compra — o caminho certo, porque apagar não
         * existe. É um POST e não um DELETE de propósito: a factura NÃO
         * desaparece, muda de estado e o stock que entrou é revertido. Um
         * `Route::delete` aqui seria a porta que o `PurchaseInvoiceImutavelTest`
         * vigia para que nunca se abra.
         */
        Route::post('/compra/{id}/anular', [\App\Http\Controllers\Api\Invoicing\CompraApiController::class, 'anular'])->whereNumber('id')->name('compra.anular');

        // As definições da facturação e as séries. As regras vivem no
        // DefinicoesDaFacturacao e no GestorDeSeries, os mesmos do Livewire.
        Route::get('/definicoes', [\App\Http\Controllers\Api\Invoicing\DefinicoesApiController::class, 'mostrar'])->name('definicoes.mostrar');
        Route::put('/definicoes', [\App\Http\Controllers\Api\Invoicing\DefinicoesApiController::class, 'guardar'])->name('definicoes.guardar');
        Route::post('/definicoes/series', [\App\Http\Controllers\Api\Invoicing\DefinicoesApiController::class, 'criarSerie'])->name('definicoes.series.criar');
        Route::put('/definicoes/series/{serie}', [\App\Http\Controllers\Api\Invoicing\DefinicoesApiController::class, 'renomearSerie'])
            ->whereNumber('serie')->name('definicoes.series.renomear');
        Route::post('/definicoes/series/{serie}/padrao', [\App\Http\Controllers\Api\Invoicing\DefinicoesApiController::class, 'tornarPadrao'])
            ->whereNumber('serie')->name('definicoes.series.padrao');
        Route::get('/notification-gateways', [\App\Http\Controllers\Api\Invoicing\NotificationGatewaysApiController::class, 'mostrar'])->name('notification-gateways.mostrar');
        Route::put('/notification-gateways', [\App\Http\Controllers\Api\Invoicing\NotificationGatewaysApiController::class, 'guardar'])->name('notification-gateways.guardar');
        Route::post('/notification-gateways/testar-sms', [\App\Http\Controllers\Api\Invoicing\NotificationGatewaysApiController::class, 'testarSms'])->name('notification-gateways.testar-sms');
        Route::post('/notification-gateways/testar-email', [\App\Http\Controllers\Api\Invoicing\NotificationGatewaysApiController::class, 'testarEmail'])->name('notification-gateways.testar-email');
        Route::post('/notification-gateways/whatsapp/templates', [\App\Http\Controllers\Api\Invoicing\NotificationGatewaysApiController::class, 'templatesWhatsApp'])->name('notification-gateways.whatsapp.templates');
        Route::post('/notification-gateways/whatsapp/testar', [\App\Http\Controllers\Api\Invoicing\NotificationGatewaysApiController::class, 'testarWhatsApp'])->name('notification-gateways.whatsapp.testar');

        // OS CATÁLOGOS: fornecedores, categorias, marcas, armazéns, condições
        // de pagamento e impostos — uma API só, o esquema vem do Catalogos.
        Route::get('/catalogos/{tipo}/opcoes', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'opcoes'])
            ->where('tipo', '[a-z-]+')->name('catalogos.opcoes');
        /*
         * IMPORTAR PARA O CATÁLOGO — hoje, os mecânicos a partir do pessoal do
         * RH. Não pende de um registo (não há `{id}`): a lista é de quem AINDA
         * NÃO está cá dentro, e o POST cria-os.
         */
        Route::get('/catalogos/{tipo}/importaveis', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'importaveis'])
            ->where('tipo', '[a-z-]+')->name('catalogos.importaveis');
        Route::post('/catalogos/{tipo}/importar', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'importar'])
            ->where('tipo', '[a-z-]+')->name('catalogos.importar');
        // O EXTRATO — só nos fornecedores, que são os únicos que o têm. Vem
        // ANTES do `{tipo}/{id}` genérico: uma rota mais larga declarada antes
        // apanharia `/fornecedores/12/extrato` como um `accao`.
        Route::get('/catalogos/{tipo}/{id}/extrato', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'extrato'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->name('catalogos.extrato');
        Route::get('/catalogos/{tipo}', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'index'])
            ->where('tipo', '[a-z-]+')->name('catalogos.index');
        Route::post('/catalogos/{tipo}', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'store'])
            ->where('tipo', '[a-z-]+')->name('catalogos.store');
        Route::put('/catalogos/{tipo}/{id}', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'update'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->name('catalogos.update');
        Route::delete('/catalogos/{tipo}/{id}', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'destroy'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->name('catalogos.destroy');
        Route::post('/catalogos/{tipo}/{id}/logotipo', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'logotipo'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->name('catalogos.logotipo');
        /*
         * A GALERIA — várias imagens numa coluna JSON. É ela que o site de
         * reservas mostra num tipo de quarto.
         */
        Route::post('/catalogos/{tipo}/{id}/galeria', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'juntarAGaleria'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->name('catalogos.galeria.juntar');
        Route::delete('/catalogos/{tipo}/{id}/galeria', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'tirarDaGaleria'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->name('catalogos.galeria.tirar');
        /*
         * ATRIBUIR EM LOTE — quem se pode atribuir, e atribuir.
         *
         * Antes do `{accao}` genérico de propósito: `atribuir` é POST com
         * corpo e o outro não, e a rota curinga apanhava-o primeiro.
         */
        Route::get('/catalogos/{tipo}/{id}/atribuiveis', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'atribuiveis'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->name('catalogos.atribuiveis');
        Route::post('/catalogos/{tipo}/{id}/atribuir', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'atribuir'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->name('catalogos.atribuir');
        Route::post('/catalogos/{tipo}/{id}/{accao}', [\App\Http\Controllers\Api\Invoicing\CatalogoApiController::class, 'accao'])
            ->where('tipo', '[a-z-]+')->whereNumber('id')->where('accao', 'activar|padrao')->name('catalogos.accao');

        /*
         * ─── RECURSOS HUMANOS ──────────────────────────────────────────
         *
         * A ficha do funcionário. As permissões são exigidas DENTRO do
         * controlador, por verbo (`employees.view/create/edit/delete`): ver a
         * lista é uma coisa, mexer no salário de alguém é outra.
         */
        Route::get('/rh/funcionarios/opcoes', [\App\Http\Controllers\Api\Hr\FuncionariosApiController::class, 'opcoes'])->name('rh.funcionarios.opcoes');
        Route::get('/rh/funcionarios/importaveis', [\App\Http\Controllers\Api\Hr\FuncionariosApiController::class, 'importaveis'])->name('rh.funcionarios.importaveis');
        Route::post('/rh/funcionarios/importar', [\App\Http\Controllers\Api\Hr\FuncionariosApiController::class, 'importar'])->name('rh.funcionarios.importar');
        Route::get('/rh/funcionarios', [\App\Http\Controllers\Api\Hr\FuncionariosApiController::class, 'index'])->name('rh.funcionarios.index');
        Route::post('/rh/funcionarios', [\App\Http\Controllers\Api\Hr\FuncionariosApiController::class, 'guardar'])->name('rh.funcionarios.guardar');
        Route::get('/rh/funcionarios/{id}', [\App\Http\Controllers\Api\Hr\FuncionariosApiController::class, 'abrir'])->whereNumber('id')->name('rh.funcionarios.abrir');
        Route::put('/rh/funcionarios/{id}', [\App\Http\Controllers\Api\Hr\FuncionariosApiController::class, 'actualizar'])->whereNumber('id')->name('rh.funcionarios.actualizar');
        Route::delete('/rh/funcionarios/{id}', [\App\Http\Controllers\Api\Hr\FuncionariosApiController::class, 'eliminar'])->whereNumber('id')->name('rh.funcionarios.eliminar');
        Route::post('/rh/funcionarios/{id}/fotografia', [\App\Http\Controllers\Api\Hr\FuncionariosApiController::class, 'guardarFotografia'])->whereNumber('id')->name('rh.funcionarios.fotografia');
        Route::post('/rh/funcionarios/{id}/documentos/{tipo}', [\App\Http\Controllers\Api\Hr\FuncionariosApiController::class, 'guardarDocumento'])
            ->whereNumber('id')->where('tipo', '[a-z_]+')->name('rh.funcionarios.documento');
        Route::delete('/rh/funcionarios/{id}/documentos/{tipo}', [\App\Http\Controllers\Api\Hr\FuncionariosApiController::class, 'apagarDocumento'])
            ->whereNumber('id')->where('tipo', '[a-z_]+')->name('rh.funcionarios.documento.apagar');
        // Abrir o documento: pela sessão e com a permissão, nunca pelo /storage.
        Route::get('/rh/funcionarios/{id}/documentos/{tipo}', [\App\Http\Controllers\Api\Hr\FuncionariosApiController::class, 'abrirDocumento'])
            ->whereNumber('id')->where('tipo', '[a-z_]+')->name('rh.funcionarios.documento.abrir');

        /*
         * OS SEIS PEDIDOS — férias, licenças, horas extras, turno nocturno,
         * adiantamentos e descontos. Uma API para todos, com o `tipo` na
         * morada; o esquema é do `PedidosDeRh`.
         *
         * APROVAR é uma permissão à parte de criar: quem pede as suas férias
         * não é quem as autoriza.
         */
        Route::prefix('rh/pedidos/{tipo}')->where(['tipo' => '[a-z-]+'])->name('rh.pedidos.')->group(function () {
            $c = \App\Http\Controllers\Api\Hr\PedidosApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            // Quem está fora, e quando — só nos pedidos que ocupam dias.
            Route::get('/calendario', [$c, 'calendario'])->name('calendario');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('guardar');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::delete('/{id}', [$c, 'eliminar'])->whereNumber('id')->name('eliminar');
            Route::post('/{id}/aprovar', [$c, 'aprovar'])->whereNumber('id')->name('aprovar');
            Route::post('/{id}/rejeitar', [$c, 'rejeitar'])->whereNumber('id')->name('rejeitar');
            Route::post('/{id}/pagar', [$c, 'pagar'])->whereNumber('id')->name('pagar');
            Route::post('/{id}/cancelar', [$c, 'cancelar'])->whereNumber('id')->name('cancelar');
            Route::post('/{id}/anexo', [$c, 'anexo'])->whereNumber('id')->name('anexo');
            Route::get('/{id}/anexo', [$c, 'abrirAnexo'])->whereNumber('id')->name('anexo.abrir');
        });

        /*
         * O PONTO. É a origem do que a folha desconta: uma falta a menos aqui
         * é dinheiro a mais no salário.
         */
        Route::prefix('rh/presencas')->name('rh.presencas.')->group(function () {
            $c = \App\Http\Controllers\Api\Hr\PresencasApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/calendario', [$c, 'calendario'])->name('calendario');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('guardar');
            Route::post('/importar', [$c, 'importar'])->name('importar');
            Route::post('/entrada/{funcionario}', [$c, 'entrada'])->whereNumber('funcionario')->name('entrada');
            Route::put('/{id}', [$c, 'actualizar'])->whereNumber('id')->name('actualizar');
            Route::post('/{id}/saida', [$c, 'saida'])->whereNumber('id')->name('saida');
            Route::delete('/{id}', [$c, 'eliminar'])->whereNumber('id')->name('eliminar');
        });

        /*
         * A FOLHA DE PAGAMENTO. Nenhuma conta aqui: cada passo é uma chamada
         * ao `PayrollService`.
         */
        Route::prefix('rh/folha')->name('rh.folha.')->group(function () {
            $c = \App\Http\Controllers\Api\Hr\FolhaApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('guardar');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::delete('/{id}', [$c, 'eliminar'])->whereNumber('id')->name('eliminar');
            Route::post('/{id}/processar', [$c, 'processar'])->whereNumber('id')->name('processar');
            Route::post('/{id}/aprovar', [$c, 'aprovar'])->whereNumber('id')->name('aprovar');
            Route::post('/{id}/pagar', [$c, 'pagar'])->whereNumber('id')->name('pagar');
            Route::post('/{id}/recalcular', [$c, 'recalcular'])->whereNumber('id')->name('recalcular');
            Route::put('/{id}/linhas/{linha}', [$c, 'acertarLinha'])->whereNumber('id')->whereNumber('linha')->name('linha');
        });

        /*
         * O PAINEL DO RH: os avisos primeiro, e cada um com a morada do ecrã
         * onde se resolve — filtrados pela permissão de quem vê.
         */
        Route::get('/rh/painel', [\App\Http\Controllers\Api\Hr\PainelApiController::class, 'index'])->name('rh.painel');

        /*
         * ─── OFICINA ───────────────────────────────────────────────────
         *
         * O painel e os cinco mapas. Os catálogos (mecânicos, viaturas,
         * serviços) e as peças vivem nas rotas genéricas acima.
         */
        Route::get('/oficina/painel', [\App\Http\Controllers\Api\Workshop\PainelApiController::class, 'index'])->name('oficina.painel');

        Route::prefix('oficina/relatorios')->name('oficina.relatorios.')->group(function () {
            $c = \App\Http\Controllers\Api\Workshop\RelatoriosApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'mostrar'])->name('mostrar');
        });

        /*
         * AS ORDENS DE SERVIÇO. A mudança de estado tem porta própria porque
         * não é um `update` de uma coluna: passar a «Concluída» desconta as
         * peças do stock e anular devolve-as.
         */
        /*
         * ─── HOTEL ─────────────────────────────────────────────────────
         *
         * A manutenção. Os catálogos (tipos de quarto, quartos, hóspedes,
         * pessoal, pacotes, códigos) vivem nas rotas genéricas acima.
         */
        Route::get('/hotel/painel', [\App\Http\Controllers\Api\Hotel\PainelApiController::class, 'index'])->name('hotel.painel');

        Route::prefix('hotel/relatorios')->name('hotel.relatorios.')->group(function () {
            $c = \App\Http\Controllers\Api\Hotel\RelatoriosApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'mostrar'])->name('mostrar');
        });

        Route::prefix('hotel/manutencao')->name('hotel.manutencao.')->group(function () {
            $c = \App\Http\Controllers\Api\Hotel\ManutencaoApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/quadro', [$c, 'quadro'])->name('quadro');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'store'])->name('store');
            Route::put('/{id}', [$c, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [$c, 'destroy'])->whereNumber('id')->name('destroy');
            Route::post('/{id}/estado', [$c, 'estado'])->whereNumber('id')->name('estado');
            Route::post('/{id}/atribuir-me', [$c, 'atribuirMe'])->whereNumber('id')->name('atribuir-me');
        });

        /*
         * A LIMPEZA. Começar, acabar e verificar têm porta própria porque não
         * são um `update` de uma coluna: cada uma delas mexe também no ESTADO
         * DO QUARTO, e é o modelo que o faz.
         */
        Route::prefix('hotel/limpeza')->name('hotel.limpeza.')->group(function () {
            $c = \App\Http\Controllers\Api\Hotel\LimpezaApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'store'])->name('store');
            Route::post('/gerar', [$c, 'gerar'])->name('gerar');
            Route::put('/{id}', [$c, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [$c, 'destroy'])->whereNumber('id')->name('destroy');
            Route::post('/{id}/estado', [$c, 'estado'])->whereNumber('id')->name('estado');
            Route::post('/{id}/ponto', [$c, 'ponto'])->whereNumber('id')->name('ponto');
            Route::post('/{id}/atribuir', [$c, 'atribuir'])->whereNumber('id')->name('atribuir');
        });

        /*
         * AS RESERVAS. As transições têm porta própria — confirmar, dar
         * entrada, cancelar e «não compareceu» não são um `update` de uma
         * coluna: cada uma delas mexe também no QUARTO. O CHECK-OUT não está
         * aqui de propósito: fecha-se no ecrã que factura.
         */
        Route::prefix('hotel/reservas')->name('hotel.reservas.')->group(function () {
            $c = \App\Http\Controllers\Api\Hotel\ReservasApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/hospedes', [$c, 'hospedes'])->name('hospedes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'store'])->name('store');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::put('/{id}', [$c, 'update'])->whereNumber('id')->name('update');
            Route::get('/{id}/quartos-livres', [$c, 'quartosLivres'])->whereNumber('id')->name('quartos-livres');
            Route::post('/{id}/estado', [$c, 'estado'])->whereNumber('id')->name('estado');
            Route::post('/{id}/receber', [$c, 'receber'])->whereNumber('id')->name('receber');
        });

        /* O CALENDÁRIO: a mesma reserva vista ao longo do tempo. */
        Route::prefix('hotel/calendario')->name('hotel.calendario.')->group(function () {
            $c = \App\Http\Controllers\Api\Hotel\CalendarioApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'grelha'])->name('grelha');
            Route::post('/{id}/mover', [$c, 'mover'])->whereNumber('id')->name('mover');
        });

        /*
         * O BALCÃO — quem chega sem reserva. É uma porta à parte das reservas
         * porque tem a sua própria permissão (`hotel.walk-in.create`): dar
         * entrada a quem chega não é o mesmo que gerir a agenda da casa.
         */
        Route::prefix('hotel/balcao')->name('hotel.balcao.')->group(function () {
            $c = \App\Http\Controllers\Api\Hotel\BalcaoApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/quartos', [$c, 'quartos'])->name('quartos');
            Route::get('/hospedes', [$c, 'hospedes'])->name('hospedes');
            Route::post('/', [$c, 'registar'])->name('registar');
        });

        /*
         * O FOLIO E O CHECK-OUT — a conta da estada, e o seu fecho.
         *
         * São o mesmo assunto em dois momentos: o folio acumula durante a
         * estada, o check-out fecha-a e emite o documento.
         */
        Route::prefix('hotel/fecho')->name('hotel.fecho.')->group(function () {
            $c = \App\Http\Controllers\Api\Hotel\FechoApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/por-sair', [$c, 'porSair'])->name('por-sair');
            Route::get('/{id}', [$c, 'conta'])->whereNumber('id')->name('conta');
            Route::post('/{id}/consumos', [$c, 'lancar'])->whereNumber('id')->name('consumos.lancar');
            Route::delete('/{id}/consumos/{consumo}', [$c, 'apagarConsumo'])
                ->whereNumber('id')->whereNumber('consumo')->name('consumos.apagar');
            Route::post('/{id}/fechar', [$c, 'fechar'])->whereNumber('id')->name('fechar');
        });

        /*
         * AS TARIFAS. As épocas são uma lista e vivem no catálogo genérico
         * (`epocas-do-hotel`); aqui fica o que não é lista — o dia da semana,
         * o dia concreto, e o calendário que mostra as três camadas juntas.
         *
         * `preco` é a porta que põe a conta a servir para alguma coisa: o
         * formulário da reserva propõe a taxa por noite que sai daqui.
         */
        Route::prefix('hotel/tarifas')->name('hotel.tarifas.')->group(function () {
            $c = \App\Http\Controllers\Api\Hotel\TarifasApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/preco', [$c, 'preco'])->name('preco');
            Route::get('/calendario', [$c, 'calendario'])->name('calendario');
            Route::get('/epocas', [$c, 'epocas'])->name('epocas');
            Route::get('/por-dia', [$c, 'porDia'])->name('por-dia');
            Route::put('/por-dia', [$c, 'guardarPorDia'])->name('por-dia.guardar');
            Route::get('/especiais', [$c, 'especiais'])->name('especiais');
            Route::post('/especiais', [$c, 'guardarEspecial'])->name('especiais.guardar');
            Route::delete('/especiais/{id}', [$c, 'apagarEspecial'])->whereNumber('id')->name('especiais.apagar');
        });

        /*
         * AS DEFINIÇÕES DO HOTEL. Ver e alterar são duas permissões, e passam
         * a valer as duas: o ecrã de sempre deixava alterar a quem só podia
         * ver.
         */
        Route::prefix('hotel/definicoes')->name('hotel.definicoes.')->group(function () {
            $c = \App\Http\Controllers\Api\Hotel\DefinicoesApiController::class;

            Route::get('/', [$c, 'mostrar'])->name('mostrar');
            Route::put('/', [$c, 'guardar'])->name('guardar');
            Route::post('/imagem', [$c, 'imagem'])->name('imagem');
            Route::delete('/imagem', [$c, 'apagarImagem'])->name('imagem.apagar');
            Route::post('/novo-endereco', [$c, 'novoEndereco'])->name('novo-endereco');
        });

        /*
         * A LIGAÇÃO AO KIANDASTAY. Três passos por esta ordem: as credenciais,
         * o hotel do site, e ligar. A chave da API nunca volta ao browser.
         */
        Route::prefix('hotel/kiandastay')->name('hotel.kiandastay.api.')->group(function () {
            $c = \App\Http\Controllers\Api\Hotel\KiandaStayApiController::class;

            Route::get('/', [$c, 'mostrar'])->name('mostrar');
            Route::put('/credenciais', [$c, 'credenciais'])->name('credenciais');
            Route::post('/autorizar', [$c, 'autorizar'])->name('autorizar');
            Route::post('/testar', [$c, 'testar'])->name('testar');
            Route::put('/hotel', [$c, 'escolherHotel'])->name('hotel');
            Route::post('/ligar', [$c, 'ligar'])->name('ligar');
            Route::put('/opcoes', [$c, 'opcoes'])->name('opcoes');
        });

        /*
         * ─── O RESTAURANTE ────────────────────────────────────────────
         *
         * A SALA e as COMANDAS são portas separadas porque são perguntas
         * separadas: o mapa da sala responde «que mesas há e como estão», a
         * comanda responde «o que está nesta mesa». O balcão usa as duas ao
         * mesmo tempo, e é por isso que nenhuma delas as junta.
         *
         * O PULSO DA COZINHA continua onde sempre esteve
         * (`/restaurant/kitchen/pulso`, no grupo do módulo): é uma agregação
         * que o ecrã pergunta de três em três segundos, e mudá-la de sítio
         * era partir o único caminho barato que a cozinha tem.
         */
        Route::get('/restaurant/painel', [\App\Http\Controllers\Api\Restaurant\PainelApiController::class, 'index'])
            ->name('restaurant.painel');

        Route::prefix('restaurant/sala')->name('restaurant.sala.')->group(function () {
            $c = \App\Http\Controllers\Api\Restaurant\SalaApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'mapa'])->name('mapa');
            Route::post('/mesas', [$c, 'criarMesa'])->name('mesas.criar');
            Route::post('/abrir', [$c, 'abrir'])->name('abrir');
            Route::post('/abrir-sem-mesa', [$c, 'abrirSemMesa'])->name('abrir-sem-mesa');
            Route::post('/mesas/{id}/estado', [$c, 'estadoDaMesa'])->whereNumber('id')->name('mesas.estado');
            Route::post('/mesas/{id}/limpar', [$c, 'limpar'])->whereNumber('id')->name('mesas.limpar');
            Route::post('/carta/{id}/aceitar', [$c, 'aceitarPedidoDaCarta'])->whereNumber('id')->name('carta.aceitar');
            Route::post('/carta/{id}/descartar', [$c, 'descartarPedidoDaCarta'])->whereNumber('id')->name('carta.descartar');
            Route::post('/espera', [$c, 'chegouAFila'])->name('espera.chegou');
            Route::post('/espera/{id}/sentar', [$c, 'sentarDaFila'])->whereNumber('id')->name('espera.sentar');
            Route::post('/espera/{id}/desistiu', [$c, 'desistiuDaFila'])->whereNumber('id')->name('espera.desistiu');
        });

        Route::prefix('restaurant/comandas')->name('restaurant.comandas.')->group(function () {
            $c = \App\Http\Controllers\Api\Restaurant\ComandasApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/artigos', [$c, 'artigos'])->name('artigos');
            Route::post('/artigo-rapido', [$c, 'artigoRapido'])->name('artigo-rapido');
            Route::get('/', [$c, 'index'])->name('index');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::post('/{id}/artigos', [$c, 'acrescentar'])->whereNumber('id')->name('artigos.juntar');
            Route::put('/{id}/artigos/{item}', [$c, 'quantidade'])->whereNumber('id')->whereNumber('item')->name('artigos.quantidade');
            Route::delete('/{id}/artigos/{item}', [$c, 'remover'])->whereNumber('id')->whereNumber('item')->name('artigos.remover');
            Route::post('/{id}/artigos/{item}/anular', [$c, 'anularArtigo'])->whereNumber('id')->whereNumber('item')->name('artigos.anular');
            Route::post('/{id}/confirmar', [$c, 'confirmar'])->whereNumber('id')->name('confirmar');
            Route::post('/{id}/despachar', [$c, 'despachar'])->whereNumber('id')->name('despachar');
            Route::post('/{id}/libertar-mesa', [$c, 'libertarMesa'])->whereNumber('id')->name('libertar-mesa');
            Route::post('/{id}/anular', [$c, 'anular'])->whereNumber('id')->name('anular');
            Route::post('/{id}/transferir', [$c, 'transferir'])->whereNumber('id')->name('transferir');
            Route::post('/{id}/juntar', [$c, 'juntar'])->whereNumber('id')->name('juntar');
            Route::post('/{id}/fechar', [$c, 'fechar'])->whereNumber('id')->name('fechar');
        });

        Route::prefix('restaurant/cozinha')->name('restaurant.cozinha.')->group(function () {
            $c = \App\Http\Controllers\Api\Restaurant\CozinhaApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'bilhetes'])->name('bilhetes');
            Route::post('/{id}/avancar', [$c, 'avancar'])->whereNumber('id')->name('avancar');
        });

        Route::prefix('restaurant/carta')->name('restaurant.carta.')->group(function () {
            $c = \App\Http\Controllers\Api\Restaurant\CartaApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/pratos', [$c, 'criarPrato'])->name('pratos.criar');
            Route::put('/pratos/{id}/preco', [$c, 'preco'])->whereNumber('id')->name('pratos.preco');
            Route::put('/pratos/{id}/nome', [$c, 'nome'])->whereNumber('id')->name('pratos.nome');
            Route::put('/pratos/{id}/categoria', [$c, 'categoriaDoPrato'])->whereNumber('id')->name('pratos.categoria');
            Route::post('/pratos/{id}/disponibilidade', [$c, 'disponibilidade'])->whereNumber('id')->name('pratos.disponibilidade');
            Route::post('/categorias', [$c, 'guardarCategoria'])->name('categorias.criar');
            Route::put('/categorias/{id}', [$c, 'guardarCategoria'])->whereNumber('id')->name('categorias.guardar');
            Route::post('/categorias/{id}/alternar', [$c, 'alternarCategoria'])->whereNumber('id')->name('categorias.alternar');
            Route::post('/categorias/{id}/mover', [$c, 'moverCategoria'])->whereNumber('id')->name('categorias.mover');
            Route::delete('/categorias/{id}', [$c, 'apagarCategoria'])->whereNumber('id')->name('categorias.apagar');
        });

        Route::prefix('restaurant/reservas')->name('restaurant.reservas.')->group(function () {
            $c = \App\Http\Controllers\Api\Restaurant\ReservasApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/estado', [$c, 'estado'])->whereNumber('id')->name('estado');
        });

        Route::prefix('restaurant/fichas')->name('restaurant.fichas.')->group(function () {
            $c = \App\Http\Controllers\Api\Restaurant\FichasApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/ingredientes', [$c, 'acrescentar'])->whereNumber('id')->name('ingredientes.juntar');
            Route::delete('/{id}/ingredientes/{linha}', [$c, 'removerIngrediente'])->whereNumber('id')->whereNumber('linha')->name('ingredientes.tirar');
            Route::post('/{id}/alternar', [$c, 'alternar'])->whereNumber('id')->name('alternar');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
        });

        Route::prefix('restaurant/stock')->name('restaurant.stock.')->group(function () {
            $c = \App\Http\Controllers\Api\Restaurant\StockApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/desperdicio', [$c, 'desperdicio'])->name('desperdicio');
        });

        Route::get('/restaurant/relatorios', [\App\Http\Controllers\Api\Restaurant\RelatoriosApiController::class, 'index'])
            ->name('restaurant.relatorios');

        Route::prefix('restaurant/definicoes')->name('restaurant.definicoes.')->group(function () {
            $c = \App\Http\Controllers\Api\Restaurant\DefinicoesApiController::class;

            Route::get('/', [$c, 'index'])->name('index');
            Route::put('/', [$c, 'guardar'])->name('guardar');
            Route::put('/carta', [$c, 'guardarCarta'])->name('carta');
            Route::post('/estabelecimentos', [$c, 'criarEstabelecimento'])->name('estabelecimentos.criar');
            Route::post('/estabelecimentos/pedir', [$c, 'pedirMaisEstabelecimentos'])->name('estabelecimentos.pedir');
            Route::post('/zonas', [$c, 'criarZona'])->name('zonas.criar');
            Route::post('/postos', [$c, 'criarPosto'])->name('postos.criar');
            Route::post('/{tipo}/{id}/alternar', [$c, 'alternar'])->whereNumber('id')->where('tipo', '[a-z]+')->name('alternar');
            Route::put('/{tipo}/{id}', [$c, 'renomear'])->whereNumber('id')->where('tipo', '[a-z]+')->name('renomear');
            Route::delete('/{tipo}/{id}', [$c, 'apagar'])->whereNumber('id')->where('tipo', '[a-z]+')->name('apagar');
        });

        Route::prefix('restaurant/aparencia')->name('restaurant.aparencia.')->group(function () {
            $c = \App\Http\Controllers\Api\Restaurant\AparenciaApiController::class;

            Route::get('/', [$c, 'index'])->name('index');
            Route::put('/', [$c, 'guardar'])->name('guardar');
            Route::post('/imagem', [$c, 'imagem'])->name('imagem');
            Route::delete('/imagem', [$c, 'removerImagem'])->name('imagem.remover');
            Route::get('/candidatos', [$c, 'candidatos'])->name('candidatos');
            Route::post('/destaques', [$c, 'destacar'])->name('destaques.juntar');
            Route::post('/destaques/{id}/mover', [$c, 'mover'])->whereNumber('id')->name('destaques.mover');
            Route::delete('/destaques/{id}', [$c, 'retirarDestaque'])->whereNumber('id')->name('destaques.tirar');
        });

        /*
         * ─── O SALÃO ──────────────────────────────────────────────────
         *
         * As MARCAÇÕES têm duas vistas (lista e calendário) e uma porta só,
         * para não haver duas contagens. Os SERVIÇOS trazem as categorias
         * consigo: a ordem delas é a ordem por que os serviços aparecem, e
         * decide-se a olhar para eles.
         */
        Route::get('/salao/painel', [\App\Http\Controllers\Api\Salon\PainelApiController::class, 'index'])
            ->name('salao.painel');

        Route::prefix('salao/marcacoes')->name('salao.marcacoes.')->group(function () {
            $c = \App\Http\Controllers\Api\Salon\MarcacoesApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/calendario', [$c, 'calendario'])->name('calendario');
            Route::get('/clientes', [$c, 'clientes'])->name('clientes');
            Route::post('/clientes', [$c, 'clienteRapido'])->name('clientes.criar');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/estado', [$c, 'estado'])->whereNumber('id')->name('estado');
        });

        Route::prefix('salao/servicos')->name('salao.servicos.')->group(function () {
            $c = \App\Http\Controllers\Api\Salon\ServicosApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/alternar', [$c, 'alternar'])->whereNumber('id')->name('alternar');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
            Route::post('/categorias', [$c, 'guardarCategoria'])->name('categorias.criar');
            Route::put('/categorias/{id}', [$c, 'guardarCategoria'])->whereNumber('id')->name('categorias.guardar');
            Route::post('/categorias/{id}/mover', [$c, 'moverCategoria'])->whereNumber('id')->name('categorias.mover');
            Route::delete('/categorias/{id}', [$c, 'apagarCategoria'])->whereNumber('id')->name('categorias.apagar');
        });

        Route::prefix('salao/profissionais')->name('salao.profissionais.')->group(function () {
            $c = \App\Http\Controllers\Api\Salon\ProfissionaisApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/do-rh', [$c, 'doRh'])->name('do-rh');
            Route::post('/do-rh', [$c, 'importarDoRh'])->name('do-rh.importar');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/alternar', [$c, 'alternar'])->whereNumber('id')->name('alternar');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
        });

        Route::prefix('salao/clientes')->name('salao.clientes.')->group(function () {
            $c = \App\Http\Controllers\Api\Salon\ClientesApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/vip', [$c, 'alternarVip'])->whereNumber('id')->name('vip');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
        });

        Route::get('/salao/tempos', [\App\Http\Controllers\Api\Salon\TemposApiController::class, 'index'])
            ->name('salao.tempos');

        Route::prefix('salao/definicoes')->name('salao.definicoes.')->group(function () {
            $c = \App\Http\Controllers\Api\Salon\DefinicoesApiController::class;

            Route::get('/', [$c, 'index'])->name('index');
            Route::put('/', [$c, 'guardar'])->name('guardar');
            Route::put('/pagina', [$c, 'guardarPagina'])->name('pagina');
            Route::post('/imagem', [$c, 'imagem'])->name('imagem');
            Route::delete('/imagem', [$c, 'removerImagem'])->name('imagem.remover');
            Route::post('/endereco', [$c, 'novoEndereco'])->name('endereco');
        });

        /*
         * OS EVENTOS.
         *
         * O CALENDÁRIO tem porta própria (`/agenda/calendario`) e devolve o mês
         * em semanas inteiras: é o servidor que monta a grelha, e não uma
         * biblioteca que o ecrã ia buscar a um CDN — numa instalação sem
         * internet ficava um quadrado branco sem aviso nenhum.
         */
        Route::get('/eventos/painel', [\App\Http\Controllers\Api\Events\PainelApiController::class, 'index'])
            ->name('eventos.painel');

        Route::prefix('eventos/agenda')->name('eventos.agenda.')->group(function () {
            $c = \App\Http\Controllers\Api\Events\AgendaApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/calendario', [$c, 'calendario'])->name('calendario');
            Route::post('/clientes', [$c, 'clienteRapido'])->name('clientes.criar');
            Route::post('/locais', [$c, 'localRapido'])->name('locais.criar');
            Route::post('/tipos', [$c, 'tipoRapido'])->name('tipos.criar');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/mover', [$c, 'mover'])->whereNumber('id')->name('mover');
            Route::post('/{id}/estado', [$c, 'estado'])->whereNumber('id')->name('estado');
            Route::post('/{id}/fase', [$c, 'avancarFase'])->whereNumber('id')->name('fase');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
            Route::post('/tarefas/{id}', [$c, 'tarefa'])->whereNumber('id')->name('tarefa');
        });

        Route::prefix('eventos/equipamentos')->name('eventos.equipamentos.')->group(function () {
            $c = \App\Http\Controllers\Api\Events\EquipamentosApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/painel', [$c, 'painel'])->name('painel');
            Route::get('/conjuntos', [$c, 'conjuntos'])->name('conjuntos');
            Route::post('/conjuntos', [$c, 'guardarConjunto'])->name('conjuntos.criar');
            Route::put('/conjuntos/{id}', [$c, 'guardarConjunto'])->whereNumber('id')->name('conjuntos.guardar');
            Route::post('/conjuntos/{id}/itens', [$c, 'juntarAoConjunto'])->whereNumber('id')->name('conjuntos.juntar');
            Route::delete('/conjuntos/{id}/itens/{equipamento}', [$c, 'tirarDoConjunto'])
                ->whereNumber('id')->whereNumber('equipamento')->name('conjuntos.tirar');
            Route::delete('/conjuntos/{id}', [$c, 'apagarConjunto'])->whereNumber('id')->name('conjuntos.apagar');
            Route::post('/categorias', [$c, 'guardarCategoria'])->name('categorias.criar');
            Route::put('/categorias/{id}', [$c, 'guardarCategoria'])->whereNumber('id')->name('categorias.guardar');
            Route::post('/categorias/{id}/alternar', [$c, 'alternarCategoria'])->whereNumber('id')->name('categorias.alternar');
            Route::delete('/categorias/{id}', [$c, 'apagarCategoria'])->whereNumber('id')->name('categorias.apagar');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/imagem', [$c, 'imagem'])->whereNumber('id')->name('imagem');
            Route::post('/{id}/emprestar', [$c, 'emprestar'])->whereNumber('id')->name('emprestar');
            Route::post('/{id}/devolver', [$c, 'devolver'])->whereNumber('id')->name('devolver');
            Route::post('/{id}/manutencao', [$c, 'manutencao'])->whereNumber('id')->name('manutencao');
            Route::get('/{id}/historial', [$c, 'historial'])->whereNumber('id')->name('historial');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
        });

        Route::prefix('eventos/locais')->name('eventos.locais.')->group(function () {
            $c = \App\Http\Controllers\Api\Events\LocaisApiController::class;

            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/alternar', [$c, 'alternar'])->whereNumber('id')->name('alternar');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
        });

        Route::prefix('eventos/tipos')->name('eventos.tipos.')->group(function () {
            $c = \App\Http\Controllers\Api\Events\TiposApiController::class;

            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/mover', [$c, 'mover'])->whereNumber('id')->name('mover');
            Route::post('/{id}/alternar', [$c, 'alternar'])->whereNumber('id')->name('alternar');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
        });

        Route::prefix('eventos/tecnicos')->name('eventos.tecnicos.')->group(function () {
            $c = \App\Http\Controllers\Api\Events\TecnicosApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/do-rh', [$c, 'doRh'])->name('do-rh');
            Route::post('/do-rh', [$c, 'importarDoRh'])->name('do-rh.importar');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/alternar', [$c, 'alternar'])->whereNumber('id')->name('alternar');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
        });

        Route::get('/eventos/relatorios', [\App\Http\Controllers\Api\Events\RelatoriosApiController::class, 'index'])
            ->name('eventos.relatorios');
        Route::get('/eventos/relatorios/csv', [\App\Http\Controllers\Api\Events\RelatoriosApiController::class, 'csv'])
            ->name('eventos.relatorios.csv');

        /*
         * O CRM.
         *
         * O FUNIL vive dentro das oportunidades (`/oportunidades/funil`) e não
         * numa morada própria: é o mesmo assunto visto de outra maneira — a
         * lista é o registo, o funil é o quadro da parede.
         */
        Route::get('/crm/painel', [\App\Http\Controllers\Api\CRM\PainelApiController::class, 'index'])
            ->name('crm.painel');

        Route::prefix('crm/leads')->name('crm.leads.')->group(function () {
            $c = \App\Http\Controllers\Api\CRM\LeadsApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/avancar', [$c, 'avancar'])->whereNumber('id')->name('avancar');
            Route::post('/{id}/converter', [$c, 'converter'])->whereNumber('id')->name('converter');
            Route::post('/{id}/perder', [$c, 'perder'])->whereNumber('id')->name('perder');
            Route::post('/{id}/reabrir', [$c, 'reabrir'])->whereNumber('id')->name('reabrir');
            Route::get('/{id}/conversa', [$c, 'conversa'])->whereNumber('id')->name('conversa');
            Route::post('/{id}/actividades', [$c, 'actividade'])->whereNumber('id')->name('actividades');
            Route::post('/{id}/responder', [$c, 'responder'])->whereNumber('id')->name('responder');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
        });

        Route::prefix('crm/oportunidades')->name('crm.oportunidades.')->group(function () {
            $c = \App\Http\Controllers\Api\CRM\OportunidadesApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/funil', [$c, 'funil'])->name('funil');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/ganhar', [$c, 'ganhar'])->whereNumber('id')->name('ganhar');
            Route::post('/{id}/perder', [$c, 'perder'])->whereNumber('id')->name('perder');
            Route::post('/{id}/reabrir', [$c, 'reabrir'])->whereNumber('id')->name('reabrir');
            Route::post('/{id}/facturar', [$c, 'facturar'])->whereNumber('id')->name('facturar');
            Route::post('/{id}/mover', [$c, 'mover'])->whereNumber('id')->name('mover');
            Route::get('/{id}/historico', [$c, 'historico'])->whereNumber('id')->name('historico');
            Route::post('/{id}/actividades', [$c, 'actividade'])->whereNumber('id')->name('actividades');
        });

        Route::prefix('crm/meta')->name('crm.meta.')->group(function () {
            $c = \App\Http\Controllers\Api\CRM\MetaApiController::class;

            Route::get('/', [$c, 'index'])->name('index');
            Route::put('/', [$c, 'guardar'])->name('guardar');
            Route::post('/token', [$c, 'novoToken'])->name('token');
            Route::post('/testar-whatsapp', [$c, 'testarWhatsApp'])->name('testar-whatsapp');
        });

        /*
         * OS PROJETOS.
         *
         * A FOLHA DE HORAS é de quem a abre: as portas de `/horas` devolvem e
         * aceitam SÓ as linhas do próprio, a menos que quem chama tenha
         * `projetos.horas.gerir`. E FACTURAR pede permissão própria — emite um
         * documento a um cliente, que é outra autoridade.
         */
        Route::get('/projetos/painel', [\App\Http\Controllers\Api\Projetos\PainelApiController::class, 'index'])
            ->name('projetos.painel');

        Route::prefix('projetos/lista')->name('projetos.lista.')->group(function () {
            $c = \App\Http\Controllers\Api\Projetos\ProjetosApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/estado', [$c, 'estado'])->whereNumber('id')->name('estado');
            Route::post('/{id}/facturar', [$c, 'facturar'])->whereNumber('id')->name('facturar');
        });

        Route::prefix('projetos/tarefas')->name('projetos.tarefas.')->group(function () {
            $c = \App\Http\Controllers\Api\Projetos\TarefasApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/estado', [$c, 'estado'])->whereNumber('id')->name('estado');
        });

        Route::prefix('projetos/horas')->name('projetos.horas.')->group(function () {
            $c = \App\Http\Controllers\Api\Projetos\HorasApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/tarefas/{projeto}', [$c, 'tarefas'])->whereNumber('projeto')->name('tarefas');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
        });

        /*
         * AS COMPRAS.
         *
         * QUEM PEDE NÃO É QUEM APROVA: `requisicoes.manage` e
         * `requisicoes.decidir` são duas permissões, e a porta pede a que
         * corresponde ao acto. RECEBER é a entrada de stock e tem a sua.
         */
        Route::get('/compras/painel', [\App\Http\Controllers\Api\Compras\PainelApiController::class, 'index'])
            ->name('compras.painel');

        Route::prefix('compras/requisicoes')->name('compras.requisicoes.')->group(function () {
            $c = \App\Http\Controllers\Api\Compras\RequisicoesApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/artigos', [$c, 'artigos'])->name('artigos');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/submeter', [$c, 'submeter'])->whereNumber('id')->name('submeter');
            Route::post('/{id}/aprovar', [$c, 'aprovar'])->whereNumber('id')->name('aprovar');
            Route::post('/{id}/rejeitar', [$c, 'rejeitar'])->whereNumber('id')->name('rejeitar');
            Route::post('/{id}/cancelar', [$c, 'cancelar'])->whereNumber('id')->name('cancelar');
        });

        Route::prefix('compras/encomendas')->name('compras.encomendas.')->group(function () {
            $c = \App\Http\Controllers\Api\Compras\EncomendasApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::post('/da-requisicao', [$c, 'daRequisicao'])->name('da-requisicao');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::post('/{id}/enviar', [$c, 'enviar'])->whereNumber('id')->name('enviar');
            Route::post('/{id}/confirmar', [$c, 'confirmar'])->whereNumber('id')->name('confirmar');
            Route::get('/{id}/recepcao', [$c, 'recepcao'])->whereNumber('id')->name('recepcao');
            Route::post('/{id}/receber', [$c, 'receber'])->whereNumber('id')->name('receber');
            Route::post('/{id}/facturar', [$c, 'facturar'])->whereNumber('id')->name('facturar');
            Route::post('/{id}/cancelar', [$c, 'cancelar'])->whereNumber('id')->name('cancelar');
        });

        /*
         * O INVENTÁRIO.
         *
         * Os MOVIMENTOS só lêem — tudo o que mexe no stock passa por eles, e é
         * onde essa história se consulta. A CONTAGEM tem permissão própria: é
         * ela que acerta o stock contra a prateleira.
         */
        Route::get('/inventario/painel', [\App\Http\Controllers\Api\Inventario\PainelApiController::class, 'index'])
            ->name('inventario.painel');
        Route::get('/inventario/movimentos', [\App\Http\Controllers\Api\Inventario\MovimentosApiController::class, 'index'])
            ->name('inventario.movimentos');

        Route::prefix('inventario/contagem')->name('inventario.contagem.')->group(function () {
            $c = \App\Http\Controllers\Api\Inventario\ContagemApiController::class;

            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'abrir'])->name('abrir');
            Route::get('/{id}/linhas', [$c, 'linhas'])->whereNumber('id')->name('linhas');
            Route::post('/{id}/contar', [$c, 'contar'])->whereNumber('id')->name('contar');
            Route::get('/{id}/resumo', [$c, 'resumoDoFecho'])->whereNumber('id')->name('resumo');
            Route::post('/{id}/fechar', [$c, 'fechar'])->whereNumber('id')->name('fechar');
            Route::post('/{id}/cancelar', [$c, 'cancelar'])->whereNumber('id')->name('cancelar');
            Route::get('/{id}/diferencas', [$c, 'diferencas'])->whereNumber('id')->name('diferencas');
        });

        /*
         * OS UTILIZADORES, OS PAPÉIS E OS CONVITES.
         *
         * A permissão é verificada acto a acto lá dentro — ver, criar, editar,
         * eliminar e convidar são cinco portas diferentes, e o `users.manage`
         * abre-as todas para não fechar a porta a quem já cá está.
         */
        Route::prefix('utilizadores')->name('utilizadores.')->group(function () {
            $c = \App\Http\Controllers\Api\Utilizadores\UtilizadoresApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/convites', [$c, 'convites'])->name('convites');
            Route::post('/convites', [$c, 'convidar'])->name('convidar');
            Route::post('/convites/{id}/reenviar', [$c, 'reenviar'])->whereNumber('id')->name('reenviar');
            Route::delete('/convites/{id}', [$c, 'cancelarConvite'])->whereNumber('id')->name('cancelar-convite');

            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
            Route::post('/{id}/estado', [$c, 'alternar'])->whereNumber('id')->name('estado');
            Route::post('/{id}/pin', [$c, 'pin'])->whereNumber('id')->name('pin');
        });

        /*
         * O SUPORTE.
         *
         * Sem permissão nenhuma, e de propósito: pedir ajuda é auto-serviço. O
         * que está fechado à chave é o escopo — um pedido é de quem o abriu, e
         * uma sugestão de outra empresa não se lê nem se vota.
         */
        /*
         * OS DADOS DA EMPRESA.
         *
         * VER e MUDAR são direitos diferentes: `settings.view` abre a página,
         * `settings.edit` é que grava. O regime fiscal é o campo mais perigoso
         * do sistema — mudá-lo reescreve o imposto por omissão e o regime de
         * todos os produtos de uma vez.
         */
        /*
         * A MINHA CONTA: as empresas, o plano, as facturas, o perfil e a senha.
         *
         * O perfil e a senha são de cada um. As empresas, o plano e a
         * facturação são de quem GERE a conta — antes, qualquer utilizador da
         * empresa (um caixa, um vendedor) trocava o plano.
         */
        /*
         * A CONTABILIDADE.
         *
         * As regras de um lançamento vivem no `Services\Accounting\Lancamentos`
         * — e metade delas não vivia em lado nenhum: um lançamento todo a zeros
         * equilibrava, uma linha podia ser débito E crédito, uma conta de
         * agregação recebia movimento, e um confirmado apagava-se.
         */
        Route::prefix('contabilidade')->name('contabilidade.')->group(function () {
            Route::get('/painel', [\App\Http\Controllers\Api\Contabilidade\PainelApiController::class, 'index'])
                ->name('painel');

            Route::prefix('contas')->name('contas.')->group(function () {
                $c = \App\Http\Controllers\Api\Contabilidade\ContasApiController::class;

                Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
                Route::get('/', [$c, 'index'])->name('index');
                Route::post('/', [$c, 'guardar'])->name('criar');
                Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
                Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
                Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
                Route::post('/{id}/estado', [$c, 'alternar'])->whereNumber('id')->name('estado');
                // A RAZÃO DA CONTA: o extracto dela, com saldo de abertura.
                Route::get('/{id}/razao', [$c, 'razao'])->whereNumber('id')->name('razao');
            });

            /*
             * OS PERÍODOS. Faltava por completo CRIAR um: nasciam de um seeder
             * corrido à mão, e uma empresa nova via «não há períodos abertos»
             * nos lançamentos sem ter por onde resolver.
             */
            /*
             * AS MOEDAS SÃO DA PLATAFORMA — as tabelas não têm `tenant_id` — e
             * por isso escrever nelas pede a permissão de GERIR: mudar o nome
             * de uma moeda muda-o para todas as empresas.
             */
            Route::prefix('moedas')->name('moedas.')->group(function () {
                $c = \App\Http\Controllers\Api\Contabilidade\MoedasApiController::class;

                Route::get('/', [$c, 'index'])->name('index');
                Route::post('/', [$c, 'guardarMoeda'])->name('criar');
                Route::put('/{id}', [$c, 'guardarMoeda'])->whereNumber('id')->name('guardar');
                Route::delete('/{id}', [$c, 'apagarMoeda'])->whereNumber('id')->name('apagar');
                Route::post('/cambios', [$c, 'guardarTaxa'])->name('cambios.guardar');
                Route::delete('/cambios/{id}', [$c, 'apagarTaxa'])->whereNumber('id')->name('cambios.apagar');
            });

            /*
             * A ANALÍTICA. Editar uma etiqueta CRIAVA outra, e nada olhava à
             * empresa: o id bastava para mexer na etiqueta de outra companhia.
             */
            Route::prefix('analitica')->name('analitica.')->group(function () {
                $c = \App\Http\Controllers\Api\Contabilidade\AnaliticaApiController::class;

                Route::get('/', [$c, 'index'])->name('index');
                Route::post('/dimensoes', [$c, 'guardarDimensao'])->name('dimensoes.criar');
                Route::put('/dimensoes/{id}', [$c, 'guardarDimensao'])->whereNumber('id')->name('dimensoes.guardar');
                Route::delete('/dimensoes/{id}', [$c, 'apagarDimensao'])->whereNumber('id')->name('dimensoes.apagar');
                Route::post('/etiquetas', [$c, 'guardarEtiqueta'])->name('etiquetas.criar');
                Route::put('/etiquetas/{id}', [$c, 'guardarEtiqueta'])->whereNumber('id')->name('etiquetas.guardar');
                Route::delete('/etiquetas/{id}', [$c, 'apagarEtiqueta'])->whereNumber('id')->name('etiquetas.apagar');
            });

            /*
             * OS ORÇAMENTOS. Faltava a comparação com o REAL, que é o ponto
             * inteiro de um orçamento: gravavam-se doze números e mostravam-se
             * outra vez.
             */
            /*
             * A RECONCILIAÇÃO BANCÁRIA. Importar um extracto NUNCA funcionou — o
             * serviço procurava uma relação que não existia no `MoveLine` — e o
             * botão «Ver» da lista não tinha `wire:click` nenhum, pelo que as
             * linhas do extracto não se viam nem se casavam.
             */
            /*
             * OS RELATÓRIOS. O `render()` do ecrã antigo calculava o BALANCETE
             * INTEIRO em todas as visitas, qualquer que fosse o mapa escolhido —
             * e com as somas feitas em PHP sobre todas as contas da empresa.
             */
            Route::prefix('relatorios')->name('relatorios.')->group(function () {
                $c = \App\Http\Controllers\Api\Contabilidade\RelatoriosApiController::class;

                Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
                Route::get('/', [$c, 'mostrar'])->name('mostrar');
            });

            /*
             * AS DEFINIÇÕES. A página abria com `settings.view` e TODAS as
             * escritas eram livres: correr os seeders, ligar a integração
             * automática (que decide se cada factura gera lançamentos) e
             * reescrever os mapeamentos das contas.
             */
            Route::prefix('definicoes')->name('definicoes.')->group(function () {
                $c = \App\Http\Controllers\Api\Contabilidade\DefinicoesApiController::class;

                Route::get('/', [$c, 'index'])->name('index');
                Route::post('/sincronizar', [$c, 'sincronizar'])->name('sincronizar');
                Route::post('/integracao', [$c, 'integracao'])->name('integracao');
                Route::post('/mapeamento', [$c, 'mapeamento'])->name('mapeamento');
                Route::delete('/tudo', [$c, 'apagarTudo'])->name('apagar-tudo');
            });

            Route::prefix('reconciliacao')->name('reconciliacao.')->group(function () {
                $c = \App\Http\Controllers\Api\Contabilidade\ReconciliacaoApiController::class;

                Route::get('/', [$c, 'index'])->name('index');
                Route::post('/importar', [$c, 'importar'])->name('importar');
                Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
                Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
                Route::post('/{id}/automatico', [$c, 'automatico'])->whereNumber('id')->name('automatico');
                Route::post('/{id}/aprovar', [$c, 'aprovar'])->whereNumber('id')->name('aprovar');
                Route::post('/linhas/{id}/casar', [$c, 'casar'])->whereNumber('id')->name('linhas.casar');
                Route::post('/linhas/{id}/desfazer', [$c, 'desfazer'])->whereNumber('id')->name('linhas.desfazer');
            });

            Route::prefix('orcamentos')->name('orcamentos.')->group(function () {
                $c = \App\Http\Controllers\Api\Contabilidade\OrcamentosApiController::class;

                Route::get('/', [$c, 'index'])->name('index');
                Route::post('/', [$c, 'guardar'])->name('criar');
                Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
                Route::post('/{id}/estado', [$c, 'estado'])->whereNumber('id')->name('estado');
                Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
            });

            /*
             * O IMOBILIZADO. O ecrã antigo era uma FACHADA: o `save()` flashava
             * «será implementada em breve» e não gravava nada, a lista era um
             * paginador vazio e os totais eram zeros literais. As três tabelas
             * existiam desde 2025 e nunca receberam uma linha.
             */
            Route::prefix('imobilizado')->name('imobilizado.')->group(function () {
                $c = \App\Http\Controllers\Api\Contabilidade\ImobilizadoApiController::class;

                Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
                Route::get('/', [$c, 'index'])->name('index');
                Route::post('/', [$c, 'guardar'])->name('criar');
                Route::post('/calcular', [$c, 'calcular'])->name('calcular');
                Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
                Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
                Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
                Route::post('/amortizacoes/{id}/lancar', [$c, 'lancar'])->whereNumber('id')->name('amortizacoes.lancar');
            });

            Route::prefix('periodos')->name('periodos.')->group(function () {
                $c = \App\Http\Controllers\Api\Contabilidade\PeriodosApiController::class;

                Route::get('/', [$c, 'index'])->name('index');
                Route::post('/', [$c, 'criar'])->name('criar');
                Route::post('/gerar', [$c, 'gerar'])->name('gerar');
                Route::post('/{id}/fechar', [$c, 'fechar'])->whereNumber('id')->name('fechar');
                Route::post('/{id}/reabrir', [$c, 'reabrir'])->whereNumber('id')->name('reabrir');
            });

            Route::prefix('lancamentos')->name('lancamentos.')->group(function () {
                $c = \App\Http\Controllers\Api\Contabilidade\LancamentosApiController::class;

                Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
                Route::get('/referencia/{diario}', [$c, 'referencia'])->whereNumber('diario')->name('referencia');
                Route::get('/', [$c, 'index'])->name('index');
                Route::post('/', [$c, 'criar'])->name('criar');
                Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
                Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
                Route::post('/{id}/confirmar', [$c, 'confirmar'])->whereNumber('id')->name('confirmar');
                Route::post('/{id}/estornar', [$c, 'estornar'])->whereNumber('id')->name('estornar');
            });
        });

        // As cópias de segurança desta empresa (só os dados dela).
        Route::prefix('copias')->name('copias.')->group(function () {
            $c = \App\Http\Controllers\Api\Copias\CopiasDaEmpresaApiController::class;
            require base_path('routes/copias.php');
        });

        Route::prefix('conta')->name('conta.')->group(function () {
            $c = \App\Http\Controllers\Api\Conta\MinhaContaApiController::class;

            Route::get('/', [$c, 'mostrar'])->name('mostrar');

            Route::post('/empresas', [$c, 'criarEmpresa'])->name('empresas.criar');
            Route::put('/empresas/{id}', [$c, 'editarEmpresa'])->whereNumber('id')->name('empresas.editar');
            Route::post('/empresas/{id}/logotipo', [$c, 'logotipo'])->whereNumber('id')->name('empresas.logotipo');
            Route::delete('/empresas/{id}/logotipo', [$c, 'apagarLogotipo'])->whereNumber('id')->name('empresas.apagar-logotipo');
            Route::get('/empresas/{id}/pode-arquivar', [$c, 'podeArquivar'])->whereNumber('id')->name('empresas.pode-arquivar');
            Route::delete('/empresas/{id}', [$c, 'arquivarEmpresa'])->whereNumber('id')->name('empresas.arquivar');
            Route::post('/empresas/{id}/activar', [$c, 'trocarDeEmpresa'])->whereNumber('id')->name('empresas.activar');

            Route::put('/perfil', [$c, 'perfil'])->name('perfil');
            Route::post('/avatar', [$c, 'avatar'])->name('avatar');
            Route::delete('/avatar', [$c, 'apagarAvatar'])->name('apagar-avatar');
            // A senha actual confere-se aqui: sem travão, uma sessão esquecida
            // aberta deixava adivinhá-la sem fim.
            Route::put('/senha', [$c, 'senha'])->middleware('throttle:5,10')->name('senha');

            // Privacidade: os direitos do titular (RGPD / LGPD / Lei 22/11).
            $p = \App\Http\Controllers\Api\Conta\PrivacidadeApiController::class;
            Route::get('/privacidade', [$p, 'mostrar'])->name('privacidade');
            Route::get('/privacidade/exportar', [$p, 'exportar'])->middleware('throttle:5,10')->name('privacidade.exportar');
            Route::put('/privacidade/consentimentos', [$p, 'consentimentos'])->middleware('throttle:20,1')->name('privacidade.consentimentos');
            Route::post('/privacidade/sessoes/terminar', [$p, 'terminarSessoes'])->middleware('throttle:10,10')->name('privacidade.terminar-sessoes');
            Route::post('/privacidade/pedidos', [$p, 'pedir'])->middleware('throttle:5,60')->name('privacidade.pedir');

            Route::post('/contratar', [$c, 'contratar'])->name('contratar');
            Route::post('/pedidos/{id}/comprovativo', [$c, 'comprovativo'])->whereNumber('id')->name('comprovativo');
        });

        Route::prefix('empresa')->name('empresa.')->group(function () {
            $c = \App\Http\Controllers\Api\Empresa\EmpresaApiController::class;

            Route::get('/', [$c, 'mostrar'])->name('mostrar');
            Route::put('/', [$c, 'guardar'])->name('guardar');
            Route::post('/logotipo', [$c, 'logotipo'])->name('logotipo');
            Route::delete('/logotipo', [$c, 'apagarLogotipo'])->name('apagar-logotipo');
        });

        /*
         * AS NOTIFICAÇÕES: as definições dos canais e os modelos.
         *
         * VER NÃO É CONFIGURAR. As duas páginas estavam atrás de
         * `notifications.view` — a mais fraca das três permissões do módulo — e
         * quem as abrisse mudava o servidor de saída da empresa e apagava
         * modelos. `notifications.manage` («Gerir Configurações de
         * Notificações») estava declarada há muito e nunca ninguém a pediu.
         */
        Route::prefix('notificacoes')->name('notificacoes.')->group(function () {
            $d = \App\Http\Controllers\Api\Notificacoes\DefinicoesApiController::class;

            Route::get('/definicoes', [$d, 'mostrar'])->name('definicoes');
            Route::put('/definicoes', [$d, 'guardar'])->name('definicoes.guardar');
            Route::post('/definicoes/testar-email', [$d, 'testarEmail'])->name('testar-email');
            Route::post('/definicoes/testar-sms', [$d, 'testarSms'])->name('testar-sms');
            Route::post('/definicoes/modelos-whatsapp', [$d, 'modelosDeWhatsApp'])->name('modelos-whatsapp');

            $m = \App\Http\Controllers\Api\Notificacoes\ModelosApiController::class;

            Route::get('/modelos/opcoes', [$m, 'opcoes'])->name('modelos.opcoes');
            Route::get('/modelos/variaveis/{modulo}', [$m, 'variaveis'])
                ->where('modulo', '[a-z_]+')->name('modelos.variaveis');
            Route::get('/modelos', [$m, 'index'])->name('modelos');
            Route::post('/modelos', [$m, 'guardar'])->name('modelos.criar');
            Route::get('/modelos/{id}', [$m, 'ficha'])->whereNumber('id')->name('modelos.ficha');
            Route::put('/modelos/{id}', [$m, 'guardar'])->whereNumber('id')->name('modelos.guardar');
            Route::delete('/modelos/{id}', [$m, 'apagar'])->whereNumber('id')->name('modelos.apagar');
            Route::post('/modelos/{id}/estado', [$m, 'alternar'])->whereNumber('id')->name('modelos.estado');
            Route::get('/modelos/{id}/teste', [$m, 'preparar'])->whereNumber('id')->name('modelos.preparar');
            Route::post('/modelos/{id}/previsao', [$m, 'previsualizar'])->whereNumber('id')->name('modelos.previsao');
            Route::post('/modelos/{id}/testar', [$m, 'testar'])->whereNumber('id')->name('modelos.testar');
        });

        Route::prefix('suporte')->name('suporte.')->group(function () {
            $c = \App\Http\Controllers\Api\Suporte\SuporteApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');

            Route::get('/pedidos', [$c, 'tickets'])->name('pedidos');
            Route::post('/pedidos', [$c, 'abrirTicket'])->name('abrir');
            Route::get('/pedidos/{id}', [$c, 'ticket'])->whereNumber('id')->name('pedido');
            Route::post('/pedidos/{id}/responder', [$c, 'responder'])->whereNumber('id')->name('responder');
            Route::post('/pedidos/{id}/fechar', [$c, 'fecharTicket'])->whereNumber('id')->name('fechar');

            Route::get('/sugestoes', [$c, 'sugestoes'])->name('sugestoes');
            Route::post('/sugestoes', [$c, 'sugerir'])->name('sugerir');
            Route::post('/sugestoes/{id}/votar', [$c, 'votar'])->whereNumber('id')->name('votar');
            Route::delete('/sugestoes/{id}', [$c, 'apagarSugestao'])->whereNumber('id')->name('apagar-sugestao');
        });

        Route::prefix('papeis')->name('papeis.')->group(function () {
            $c = \App\Http\Controllers\Api\Utilizadores\PapeisApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/utilizadores', [$c, 'utilizadores'])->name('utilizadores');
            Route::post('/utilizadores/{id}', [$c, 'atribuir'])->whereNumber('id')->name('atribuir');

            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('criar');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::put('/{id}', [$c, 'guardar'])->whereNumber('id')->name('guardar');
            Route::delete('/{id}', [$c, 'apagar'])->whereNumber('id')->name('apagar');
        });

        Route::prefix('oficina/ordens')->name('oficina.ordens.')->group(function () {
            $c = \App\Http\Controllers\Api\Workshop\OrdensApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/artigos', [$c, 'artigos'])->name('artigos');
            // As folhas de obra de uma viatura e as facturas que saíram delas — a ficha da viatura.
            Route::get('/viatura/{id}', [$c, 'daViatura'])->whereNumber('id')->name('viatura');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'store'])->name('store');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::put('/{id}', [$c, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [$c, 'destroy'])->whereNumber('id')->name('destroy');
            Route::post('/{id}/estado', [$c, 'estado'])->whereNumber('id')->name('estado');
            Route::post('/{id}/linhas', [$c, 'juntarLinha'])->whereNumber('id')->name('linhas.juntar');
            Route::delete('/{id}/linhas/{linha}', [$c, 'tirarLinha'])->whereNumber('id')->whereNumber('linha')->name('linhas.tirar');
            Route::put('/{id}/desconto', [$c, 'desconto'])->whereNumber('id')->name('desconto');
            Route::post('/{id}/facturar', [$c, 'facturar'])->whereNumber('id')->name('facturar');
            Route::post('/{id}/anexos', [$c, 'anexar'])->whereNumber('id')->name('anexos.juntar');
            Route::delete('/{id}/anexos/{anexo}', [$c, 'apagarAnexo'])->whereNumber('id')->whereNumber('anexo')->name('anexos.apagar');
        });

        /*
         * OS MAPAS: cinco relatórios e o mapa de IRT. Nenhum recalcula nada —
         * lêem o que ficou gravado no processamento da folha.
         */
        Route::prefix('rh/relatorios')->name('rh.relatorios.')->group(function () {
            $c = \App\Http\Controllers\Api\Hr\RelatoriosApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/irt', [$c, 'irt'])->name('irt');
            Route::get('/', [$c, 'mostrar'])->name('mostrar');
        });

        /*
         * AS DEFINIÇÕES DE RH. Ver é uma permissão, alterar é outra: são
         * estes números que decidem quanto cada pessoa recebe.
         */
        Route::prefix('rh/definicoes')->name('rh.definicoes.')->group(function () {
            $c = \App\Http\Controllers\Api\Hr\DefinicoesApiController::class;

            Route::get('/', [$c, 'index'])->name('index');
            Route::put('/', [$c, 'guardar'])->name('guardar');
            Route::post('/repor', [$c, 'repor'])->name('repor');
        });

        /*
         * OS CONTRATOS. A tabela já decidia o salário pago e não tinha ecrã
         * nenhum — ver `ContratosApiController`.
         */
        Route::prefix('rh/contratos')->name('rh.contratos.')->group(function () {
            $c = \App\Http\Controllers\Api\Hr\ContratosApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'guardar'])->name('guardar');
            Route::get('/{id}', [$c, 'ficha'])->whereNumber('id')->name('ficha');
            Route::put('/{id}', [$c, 'actualizar'])->whereNumber('id')->name('actualizar');
            Route::post('/{id}/cessar', [$c, 'cessar'])->whereNumber('id')->name('cessar');
            Route::delete('/{id}', [$c, 'eliminar'])->whereNumber('id')->name('eliminar');
        });

        // Adiantamentos: as regras no EmissorDeAdiantamentos, o mesmo do Livewire.
        Route::get('/adiantamentos/opcoes', [\App\Http\Controllers\Api\Invoicing\AdiantamentoApiController::class, 'opcoes'])->name('adiantamentos.opcoes');
        Route::get('/adiantamentos/{id}', [\App\Http\Controllers\Api\Invoicing\AdiantamentoApiController::class, 'mostrar'])->whereNumber('id')->name('adiantamentos.mostrar');
        Route::post('/adiantamentos', [\App\Http\Controllers\Api\Invoicing\AdiantamentoApiController::class, 'guardar'])->name('adiantamentos.guardar');
        Route::put('/adiantamentos/{id}', [\App\Http\Controllers\Api\Invoicing\AdiantamentoApiController::class, 'actualizar'])->whereNumber('id')->name('adiantamentos.actualizar');

        // Guias de transporte: a emissão, a anulação e a AGT no EmissorDeGuias.
        Route::get('/guias/opcoes', [\App\Http\Controllers\Api\Invoicing\GuiasApiController::class, 'opcoes'])->name('guias.opcoes');
        Route::get('/guias', [\App\Http\Controllers\Api\Invoicing\GuiasApiController::class, 'index'])->name('guias.index');
        Route::get('/guias/facturas/{factura}/linhas', [\App\Http\Controllers\Api\Invoicing\GuiasApiController::class, 'linhasDaFactura'])->whereNumber('factura')->name('guias.linhas');
        Route::post('/guias', [\App\Http\Controllers\Api\Invoicing\GuiasApiController::class, 'guardar'])->name('guias.guardar');
        Route::post('/guias/{id}/agt', [\App\Http\Controllers\Api\Invoicing\GuiasApiController::class, 'comunicar'])->whereNumber('id')->name('guias.agt');
        Route::delete('/guias/{id}', [\App\Http\Controllers\Api\Invoicing\GuiasApiController::class, 'anular'])->whereNumber('id')->name('guias.anular');

        // Importações: o registo e o percurso no GestorDeImportacoes.
        Route::get('/importacoes/opcoes', [\App\Http\Controllers\Api\Invoicing\ImportacoesApiController::class, 'opcoes'])->name('importacoes.opcoes');
        Route::get('/importacoes', [\App\Http\Controllers\Api\Invoicing\ImportacoesApiController::class, 'index'])->name('importacoes.index');
        Route::post('/importacoes', [\App\Http\Controllers\Api\Invoicing\ImportacoesApiController::class, 'guardar'])->name('importacoes.guardar');
        Route::put('/importacoes/{id}', [\App\Http\Controllers\Api\Invoicing\ImportacoesApiController::class, 'actualizar'])->whereNumber('id')->name('importacoes.actualizar');
        Route::post('/importacoes/{id}/estado', [\App\Http\Controllers\Api\Invoicing\ImportacoesApiController::class, 'estado'])->whereNumber('id')->name('importacoes.estado');
        Route::delete('/importacoes/{id}', [\App\Http\Controllers\Api\Invoicing\ImportacoesApiController::class, 'apagar'])->whereNumber('id')->name('importacoes.apagar');

        // Pagar uma factura: recibo, tesouraria, adiantamento e AGT no RegistoDePagamento.
        Route::get('/pagamentos/{tipo}/{factura}', [\App\Http\Controllers\Api\Invoicing\PagamentoApiController::class, 'contexto'])
            ->where('tipo', 'sale|purchase')->whereNumber('factura')->name('pagamentos.contexto');
        Route::post('/pagamentos/{tipo}/{factura}', [\App\Http\Controllers\Api\Invoicing\PagamentoApiController::class, 'registar'])
            ->where('tipo', 'sale|purchase')->whereNumber('factura')->name('pagamentos.registar');

        // Stock: a lista e os cartões da mesma consulta; ajustar, transferir e
        // a movimentação em lote pelo MovimentacaoDeStock.
        Route::get('/stock/opcoes', [\App\Http\Controllers\Api\Invoicing\StockApiController::class, 'opcoes'])->name('stock.opcoes');
        Route::get('/stock', [\App\Http\Controllers\Api\Invoicing\StockApiController::class, 'index'])->name('stock.index');
        Route::get('/stock/artigos', [\App\Http\Controllers\Api\Invoicing\StockApiController::class, 'artigos'])->name('stock.artigos');
        Route::get('/stock/movimentos/{produto}', [\App\Http\Controllers\Api\Invoicing\StockApiController::class, 'movimentos'])->whereNumber('produto')->name('stock.movimentos');
        Route::post('/stock/ajustar', [\App\Http\Controllers\Api\Invoicing\StockApiController::class, 'ajustar'])->name('stock.ajustar');
        Route::post('/stock/transferir', [\App\Http\Controllers\Api\Invoicing\StockApiController::class, 'transferir'])->name('stock.transferir');
        Route::post('/stock/entrada', [\App\Http\Controllers\Api\Invoicing\StockApiController::class, 'entrada'])->name('stock.entrada');

        // Quebras de stock: registo e relatório; registar e anular no QuebraDeStock.
        Route::get('/quebras/opcoes', [\App\Http\Controllers\Api\Invoicing\QuebrasApiController::class, 'opcoes'])->name('quebras.opcoes');
        Route::get('/quebras/artigos', [\App\Http\Controllers\Api\Invoicing\QuebrasApiController::class, 'artigos'])->name('quebras.artigos');
        Route::get('/quebras', [\App\Http\Controllers\Api\Invoicing\QuebrasApiController::class, 'index'])->name('quebras.index');
        Route::post('/quebras', [\App\Http\Controllers\Api\Invoicing\QuebrasApiController::class, 'registar'])->name('quebras.registar');
        Route::post('/quebras/{id}/anular', [\App\Http\Controllers\Api\Invoicing\QuebrasApiController::class, 'anular'])->whereNumber('id')->name('quebras.anular');

        // Lotes e validades: criar, corrigir e apagar no GestorDeLotes.
        Route::get('/lotes/opcoes', [\App\Http\Controllers\Api\Invoicing\LotesApiController::class, 'opcoes'])->name('lotes.opcoes');
        Route::get('/lotes', [\App\Http\Controllers\Api\Invoicing\LotesApiController::class, 'index'])->name('lotes.index');
        Route::post('/lotes', [\App\Http\Controllers\Api\Invoicing\LotesApiController::class, 'guardar'])->name('lotes.guardar');
        Route::put('/lotes/{id}', [\App\Http\Controllers\Api\Invoicing\LotesApiController::class, 'actualizar'])->whereNumber('id')->name('lotes.actualizar');
        Route::delete('/lotes/{id}', [\App\Http\Controllers\Api\Invoicing\LotesApiController::class, 'apagar'])->whereNumber('id')->name('lotes.apagar');

        // Transferências (entre armazéns, ajuste em lote, entre empresas): TransferenciaDeStock.
        Route::get('/transferencias/opcoes', [\App\Http\Controllers\Api\Invoicing\TransferenciasApiController::class, 'opcoes'])->name('transferencias.opcoes');
        Route::get('/transferencias/empresas/{empresa}/armazens', [\App\Http\Controllers\Api\Invoicing\TransferenciasApiController::class, 'armazensDaEmpresa'])->whereNumber('empresa')->name('transferencias.armazens');
        Route::get('/transferencias/artigos', [\App\Http\Controllers\Api\Invoicing\TransferenciasApiController::class, 'artigos'])->name('transferencias.artigos');
        Route::get('/transferencias/historico', [\App\Http\Controllers\Api\Invoicing\TransferenciasApiController::class, 'historico'])->name('transferencias.historico');
        Route::get('/transferencias/detalhes', [\App\Http\Controllers\Api\Invoicing\TransferenciasApiController::class, 'detalhes'])->name('transferencias.detalhes');
        Route::get('/transferencias/entre-empresas/historico', [\App\Http\Controllers\Api\Invoicing\TransferenciasApiController::class, 'historicoEntreEmpresas'])->name('transferencias.entre-empresas.historico');
        Route::post('/transferencias/entre-armazens', [\App\Http\Controllers\Api\Invoicing\TransferenciasApiController::class, 'entreArmazens'])->name('transferencias.entre-armazens');
        Route::post('/transferencias/ajuste', [\App\Http\Controllers\Api\Invoicing\TransferenciasApiController::class, 'ajuste'])->name('transferencias.ajuste');
        Route::post('/transferencias/entre-empresas', [\App\Http\Controllers\Api\Invoicing\TransferenciasApiController::class, 'entreEmpresas'])->name('transferencias.entre-empresas');

        // As séries de documentos: a ficha inteira, com a AGT a mandar no que se mexe.
        Route::get('/series/opcoes', [\App\Http\Controllers\Api\Invoicing\SeriesApiController::class, 'opcoes'])->name('series.opcoes');
        Route::get('/series', [\App\Http\Controllers\Api\Invoicing\SeriesApiController::class, 'index'])->name('series.index');
        Route::post('/series', [\App\Http\Controllers\Api\Invoicing\SeriesApiController::class, 'guardar'])->name('series.guardar');
        Route::put('/series/{id}', [\App\Http\Controllers\Api\Invoicing\SeriesApiController::class, 'actualizar'])->whereNumber('id')->name('series.actualizar');
        Route::delete('/series/{id}', [\App\Http\Controllers\Api\Invoicing\SeriesApiController::class, 'eliminar'])->whereNumber('id')->name('series.eliminar');

        // A trilha de auditoria: só leitura, por construção.
        Route::get('/auditoria/opcoes', [\App\Http\Controllers\Api\Invoicing\AuditoriaApiController::class, 'opcoes'])->name('auditoria.opcoes');
        Route::get('/auditoria/integridade', [\App\Http\Controllers\Api\Invoicing\AuditoriaApiController::class, 'integridade'])->name('auditoria.integridade');
        Route::get('/auditoria', [\App\Http\Controllers\Api\Invoicing\AuditoriaApiController::class, 'index'])->name('auditoria.index');
        Route::get('/auditoria/{id}', [\App\Http\Controllers\Api\Invoicing\AuditoriaApiController::class, 'mostrar'])->whereNumber('id')->name('auditoria.mostrar');

        // O SAFT-AO: as contagens do período. A descarga é rota de página, com a sessão.
        Route::get('/saft/opcoes', [\App\Http\Controllers\Api\Invoicing\SaftApiController::class, 'opcoes'])->name('saft.opcoes');
        Route::get('/saft/estatisticas', [\App\Http\Controllers\Api\Invoicing\SaftApiController::class, 'estatisticas'])->name('saft.estatisticas');

        // A AGT: os dois ambientes em separado, as chaves de cada um, as séries, as submissões e a ficha do contribuinte.
        Route::get('/agt/opcoes', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'opcoes'])->name('agt.opcoes');
        Route::get('/agt/estado', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'estado'])->name('agt.estado');
        Route::post('/agt/definicoes', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'guardar'])->name('agt.guardar');
        Route::post('/agt/ambiente', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'activarAmbiente'])->name('agt.ambiente');
        Route::post('/agt/chaves', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'guardarChaves'])->name('agt.chaves');
        Route::post('/agt/chaves/remover', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'removerChaves'])->name('agt.chaves.remover');
        // O que FALA com a AGT tem limite por empresa e utilizador (ver AppServiceProvider, agt-comunicacao):
        // um duplo clique repetido ou um script não martelam a AGT em nome da empresa.
        Route::post('/agt/ligacao', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'testarLigacao'])->middleware('throttle:agt-comunicacao')->name('agt.ligacao');
        Route::post('/agt/series/sincronizar', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'sincronizarSeries'])->middleware('throttle:agt-comunicacao')->name('agt.series.sincronizar');
        Route::post('/agt/submissoes/actualizar', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'actualizarEstados'])->middleware('throttle:agt-comunicacao')->name('agt.submissoes.actualizar');
        Route::post('/agt/submissoes/{id}/reenviar', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'reenviar'])->whereNumber('id')->middleware('throttle:agt-comunicacao')->name('agt.submissoes.reenviar');
        Route::post('/agt/consulta', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'consultar'])->middleware('throttle:agt-comunicacao')->name('agt.consulta');
        Route::get('/agt/contribuinte', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'contribuinte'])->name('agt.contribuinte');
        Route::post('/agt/contribuinte', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'guardarContribuinte'])->name('agt.contribuinte.guardar');
        // A chave privada «do modo antigo»: cola-se e remove-se, e nunca volta na resposta.
        Route::post('/agt/contribuinte/chave', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'guardarChaveLegado'])->name('agt.contribuinte.chave');
        Route::post('/agt/contribuinte/chave/remover', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'removerChaveLegado'])->name('agt.contribuinte.chave.remover');

        // O adquirente: as facturas que os FORNECEDORES emitiram contra esta empresa.
        // Confirmar e rejeitar escrevem na AGT — permissão de editar, e só no ambiente activo.
        Route::get('/adquirente/estado', [\App\Http\Controllers\Api\Invoicing\AdquirenteApiController::class, 'estado'])->name('adquirente.estado');
        Route::get('/adquirente/facturas', [\App\Http\Controllers\Api\Invoicing\AdquirenteApiController::class, 'listar'])->name('adquirente.listar');
        Route::get('/adquirente/factura', [\App\Http\Controllers\Api\Invoicing\AdquirenteApiController::class, 'detalhe'])->name('adquirente.detalhe');
        Route::post('/adquirente/validar', [\App\Http\Controllers\Api\Invoicing\AdquirenteApiController::class, 'validar'])->name('adquirente.validar');

        // Os relatórios: o esquema e os números de cada mapa, pelo mesmo serviço do ecrã de sempre.
        Route::get('/relatorios', [\App\Http\Controllers\Api\Invoicing\RelatoriosApiController::class, 'seccoes'])->name('relatorios.seccoes');
        Route::get('/relatorios/{slug}/entidades', [\App\Http\Controllers\Api\Invoicing\RelatoriosApiController::class, 'entidades'])->name('relatorios.entidades');
        Route::get('/relatorios/{slug}', [\App\Http\Controllers\Api\Invoicing\RelatoriosApiController::class, 'mostrar'])->name('relatorios.mostrar');

        // Os turnos do POS: o do balcão e o histórico.
        Route::get('/turnos/estado', [\App\Http\Controllers\Api\Invoicing\TurnosApiController::class, 'estado'])->name('turnos.estado');
        Route::post('/turnos/abrir', [\App\Http\Controllers\Api\Invoicing\TurnosApiController::class, 'abrir'])->name('turnos.abrir');
        Route::post('/turnos/fechar', [\App\Http\Controllers\Api\Invoicing\TurnosApiController::class, 'fechar'])->name('turnos.fechar');
        Route::get('/turnos/historico', [\App\Http\Controllers\Api\Invoicing\TurnosApiController::class, 'historico'])->name('turnos.historico');
        Route::get('/turnos/{id}', [\App\Http\Controllers\Api\Invoicing\TurnosApiController::class, 'mostrar'])->whereNumber('id')->name('turnos.mostrar');


        /*
         * O BALCÃO. A venda entra pelo `PosSaleService` — a MESMA porta do
         * PWA offline. Ver o `PosApiController`.
         */
        Route::get('/pos/opcoes', [\App\Http\Controllers\Api\Invoicing\PosApiController::class, 'opcoes'])->name('pos.opcoes');
        Route::get('/pos/artigos', [\App\Http\Controllers\Api\Invoicing\PosApiController::class, 'artigos'])->name('pos.artigos');
        Route::get('/pos/clientes', [\App\Http\Controllers\Api\Invoicing\PosApiController::class, 'clientes'])->name('pos.clientes');
        Route::post('/pos/clientes', [\App\Http\Controllers\Api\Invoicing\PosApiController::class, 'criarCliente'])->name('pos.clientes.criar');
        /*
         * O QUE ESTE CÓDIGO DE BARRAS É.
         *
         * A grelha esconde o que está sem stock; passar o leitor por um artigo
         * esgotado dava um ecrã vazio, indistinguível de «este código não
         * existe». Esta porta pergunta ao catálogo inteiro e diz qual dos casos
         * é — vende-se, sem stock, inactivo, de outro módulo, ou desconhecido.
         */
        Route::get('/pos/por-codigo', [\App\Http\Controllers\Api\Invoicing\PosApiController::class, 'porCodigo'])->name('pos.por-codigo');
        Route::post('/pos/vender', [\App\Http\Controllers\Api\Invoicing\PosApiController::class, 'vender'])->name('pos.vender');
        Route::get('/pos/relatorio', [\App\Http\Controllers\Api\Invoicing\PosApiController::class, 'relatorio'])->name('pos.relatorio');

        /*
         * A TESOURARIA. Os movimentos: lançar, editar, apagar e estornar.
         *
         * O dinheiro mexe-se pelo `TreasuryMovementService` e por mais lado
         * nenhum. Cada verbo exige a sua `treasury.transactions.*` — as quatro
         * permissões existiam e a morada de sempre não aplicava nenhuma.
         */
        Route::get('/tesouraria/movimentos/opcoes', [\App\Http\Controllers\Api\Treasury\MovimentosApiController::class, 'opcoes'])->name('tesouraria.movimentos.opcoes');
        Route::get('/tesouraria/movimentos', [\App\Http\Controllers\Api\Treasury\MovimentosApiController::class, 'index'])->name('tesouraria.movimentos.index');
        Route::post('/tesouraria/movimentos', [\App\Http\Controllers\Api\Treasury\MovimentosApiController::class, 'criar'])->name('tesouraria.movimentos.criar');
        Route::get('/tesouraria/movimentos/{id}', [\App\Http\Controllers\Api\Treasury\MovimentosApiController::class, 'mostrar'])->whereNumber('id')->name('tesouraria.movimentos.mostrar');
        Route::put('/tesouraria/movimentos/{id}', [\App\Http\Controllers\Api\Treasury\MovimentosApiController::class, 'actualizar'])->whereNumber('id')->name('tesouraria.movimentos.actualizar');
        Route::delete('/tesouraria/movimentos/{id}', [\App\Http\Controllers\Api\Treasury\MovimentosApiController::class, 'eliminar'])->whereNumber('id')->name('tesouraria.movimentos.eliminar');
        Route::post('/tesouraria/movimentos/{id}/creditar', [\App\Http\Controllers\Api\Treasury\MovimentosApiController::class, 'creditar'])->whereNumber('id')->name('tesouraria.movimentos.creditar');

        // As transferências entre contas e caixas. As três pernas — saída,
        // entrada e taxa — passam pelo mesmo serviço dos movimentos.
        Route::get('/tesouraria/transferencias/opcoes', [\App\Http\Controllers\Api\Treasury\TransferenciasApiController::class, 'opcoes'])->name('tesouraria.transferencias.opcoes');
        Route::get('/tesouraria/transferencias', [\App\Http\Controllers\Api\Treasury\TransferenciasApiController::class, 'index'])->name('tesouraria.transferencias.index');
        Route::post('/tesouraria/transferencias', [\App\Http\Controllers\Api\Treasury\TransferenciasApiController::class, 'criar'])->name('tesouraria.transferencias.criar');
        Route::delete('/tesouraria/transferencias/{id}', [\App\Http\Controllers\Api\Treasury\TransferenciasApiController::class, 'anular'])->whereNumber('id')->name('tesouraria.transferencias.anular');

        // O painel e os relatórios: só lêem. As contas dos relatórios são as
        // da `RelatoriosDeTesouraria` — as mesmas do PDF e do Excel.
        Route::get('/tesouraria/painel', \App\Http\Controllers\Api\Treasury\PainelApiController::class)->name('tesouraria.painel');
        Route::get('/tesouraria/relatorios', \App\Http\Controllers\Api\Treasury\RelatoriosApiController::class)->name('tesouraria.relatorios');
        // O modo offline: recuperar uma cópia do PWA, e o PIN de turno.
        Route::post('/copia-offline/analisar', [\App\Http\Controllers\Api\Invoicing\OfflineApiController::class, 'analisar'])->name('copia-offline.analisar');
        Route::post('/copia-offline/importar', [\App\Http\Controllers\Api\Invoicing\OfflineApiController::class, 'importar'])->name('copia-offline.importar');
        Route::get('/pin', [\App\Http\Controllers\Api\Invoicing\OfflineApiController::class, 'pin'])->name('pin');
        Route::post('/pin', [\App\Http\Controllers\Api\Invoicing\OfflineApiController::class, 'definirPin'])->name('pin.definir');

        // Os modelos de proposta: a lista e o editor.
        Route::get('/modelos-de-proposta/opcoes', [\App\Http\Controllers\Api\Invoicing\ModelosDePropostaApiController::class, 'opcoes'])->name('modelos.opcoes');
        Route::get('/modelos-de-proposta', [\App\Http\Controllers\Api\Invoicing\ModelosDePropostaApiController::class, 'index'])->name('modelos.index');
        Route::post('/modelos-de-proposta', [\App\Http\Controllers\Api\Invoicing\ModelosDePropostaApiController::class, 'criar'])->name('modelos.criar');
        Route::post('/modelos-de-proposta/{id}/duplicar', [\App\Http\Controllers\Api\Invoicing\ModelosDePropostaApiController::class, 'duplicar'])->whereNumber('id')->name('modelos.duplicar');
        Route::post('/modelos-de-proposta/{id}/padrao', [\App\Http\Controllers\Api\Invoicing\ModelosDePropostaApiController::class, 'tornarPadrao'])->whereNumber('id')->name('modelos.padrao');
        Route::delete('/modelos-de-proposta/{id}', [\App\Http\Controllers\Api\Invoicing\ModelosDePropostaApiController::class, 'eliminar'])->whereNumber('id')->name('modelos.eliminar');
        Route::get('/modelos-de-proposta/{id}/editor', [\App\Http\Controllers\Api\Invoicing\ModelosDePropostaApiController::class, 'editor'])->whereNumber('id')->name('modelos.editor');
        Route::post('/modelos-de-proposta/{id}/editor', [\App\Http\Controllers\Api\Invoicing\ModelosDePropostaApiController::class, 'accao'])->whereNumber('id')->name('modelos.editor.accao');

        // O painel: só números, só leitura.
        Route::get('/painel', \App\Http\Controllers\Api\Invoicing\PainelApiController::class)
            ->name('painel');

        // Clientes — o primeiro que também escreve. Cada verbo tem a sua
        // permissão, verificada dentro do controlador.
        Route::get('/clients/opcoes', [\App\Http\Controllers\Api\Invoicing\ClientApiController::class, 'opcoes'])
            ->name('clients.opcoes');
        Route::get('/clients', [\App\Http\Controllers\Api\Invoicing\ClientApiController::class, 'index'])
            ->name('clients.index');
        Route::post('/clients', [\App\Http\Controllers\Api\Invoicing\ClientApiController::class, 'store'])
            ->name('clients.store');
        Route::put('/clients/{id}', [\App\Http\Controllers\Api\Invoicing\ClientApiController::class, 'update'])
            ->whereNumber('id')->name('clients.update');
        Route::delete('/clients/{id}', [\App\Http\Controllers\Api\Invoicing\ClientApiController::class, 'destroy'])
            ->whereNumber('id')->name('clients.destroy');

        // O EXTRATO DO CLIENTE: as contas, as últimas facturas, os artigos que
        // mais leva e a frequência. É o que se olha antes de dar crédito.
        Route::get('/clients/{id}/extrato', [\App\Http\Controllers\Api\Invoicing\ClientApiController::class, 'extrato'])
            ->whereNumber('id')->name('clients.extrato');

        // O logótipo do cliente. Um ficheiro não viaja em JSON — vai em
        // multipart, como o logótipo dos catálogos e as imagens do artigo.
        Route::post('/clients/{id}/logotipo', [\App\Http\Controllers\Api\Invoicing\ClientApiController::class, 'logotipo'])
            ->whereNumber('id')->name('clients.logotipo');
        Route::delete('/clients/{id}/logotipo', [\App\Http\Controllers\Api\Invoicing\ClientApiController::class, 'apagarLogotipo'])
            ->whereNumber('id')->name('clients.logotipo.apagar');

        // Artigos.
        Route::get('/products/opcoes', [\App\Http\Controllers\Api\Invoicing\ProductApiController::class, 'opcoes'])
            ->name('products.opcoes');
        Route::get('/products', [\App\Http\Controllers\Api\Invoicing\ProductApiController::class, 'index'])
            ->name('products.index');
        Route::post('/products', [\App\Http\Controllers\Api\Invoicing\ProductApiController::class, 'store'])
            ->name('products.store');
        Route::put('/products/{id}', [\App\Http\Controllers\Api\Invoicing\ProductApiController::class, 'update'])
            ->whereNumber('id')->name('products.update');
        Route::delete('/products/{id}', [\App\Http\Controllers\Api\Invoicing\ProductApiController::class, 'destroy'])
            ->whereNumber('id')->name('products.destroy');

        // RESTAURAR UM ARTIGO APAGADO. A eliminação sempre foi recuperável (o
        // modelo tem SoftDeletes) e durante anos não houve como o fazer pela
        // aplicação — só com SQL directo.
        Route::post('/products/{id}/restaurar', [\App\Http\Controllers\Api\Invoicing\ProductApiController::class, 'restaurar'])
            ->whereNumber('id')->name('products.restaurar');

        // PARA ONDE FOI ESTE ARTIGO: vendas e movimentos de stock lado a lado.
        // É a discrepância entre os dois que denuncia a baixa de stock que
        // falhou — e é por isso que as duas listas vêm juntas.
        Route::get('/products/{id}/rastreio', [\App\Http\Controllers\Api\Invoicing\ProductApiController::class, 'rastreio'])
            ->whereNumber('id')->name('products.rastreio');

        // As imagens do artigo: destaque e galeria. Um ficheiro não viaja em
        // JSON — vai em multipart, como o logótipo dos catálogos.
        Route::post('/products/{id}/imagem', [\App\Http\Controllers\Api\Invoicing\ProductApiController::class, 'imagem'])
            ->whereNumber('id')->name('products.imagem');
        Route::delete('/products/{id}/imagem', [\App\Http\Controllers\Api\Invoicing\ProductApiController::class, 'apagarImagem'])
            ->whereNumber('id')->name('products.imagem.apagar');
        Route::post('/products/{id}/galeria', [\App\Http\Controllers\Api\Invoicing\ProductApiController::class, 'galeria'])
            ->whereNumber('id')->name('products.galeria');
        Route::delete('/products/{id}/galeria', [\App\Http\Controllers\Api\Invoicing\ProductApiController::class, 'apagarDaGaleria'])
            ->whereNumber('id')->name('products.galeria.apagar');
    });
});

// Invoicing Module Routes
Route::middleware(['auth', 'tenant.module:invoicing'])->prefix('invoicing')->name('invoicing.')->group(function () {
    // Dashboard
    Route::middleware('permission:invoicing.dashboard.view')->get('/dashboard', \App\Support\EcraReact::pagina('facturacao/painel', 'Dashboard de Faturação'))->name('dashboard');
    
    Route::middleware('permission:invoicing.clients.view')->get('/clients', \App\Support\EcraReact::pagina('facturacao/clientes', 'Clientes'))->name('clients');

    // As cinco listas que partilham forma, num ficheiro à parte.
    // O SAFT e o CSV dos relatórios descarregam-se com a sessão: um ficheiro não viaja em JSON.
    Route::middleware('permission:invoicing.saft.generate')
        ->get('/saft-generator/descarregar', [\App\Http\Controllers\Api\Invoicing\SaftApiController::class, 'descarregar'])
        ->name('saft-generator.descarregar');

    foreach (\App\Services\Invoicing\Relatorios\Catalogo::RELATORIOS as $slugDoMapa => $classeDoMapa) {
        Route::middleware('permission:' . \App\Services\Invoicing\Relatorios\Catalogo::permissao($slugDoMapa))
            ->get(($slugDoMapa === 'expiry-report' ? '/expiry-report' : '/reports/' . $slugDoMapa) . '/csv', [\App\Http\Controllers\Api\Invoicing\RelatoriosApiController::class, 'csv'])
            ->defaults('slug', $slugDoMapa)
            ->name('relatorio.' . $slugDoMapa . '.csv');
    }


    // O mesmo ecrã em React, na morada de ensaio. A de sempre fica intacta.

    // O mesmo ecrã em React, na morada de ensaio. A de sempre fica intacta.
    Route::middleware('permission:invoicing.suppliers.view')->get('/suppliers', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Fornecedores', ['tipo' => 'fornecedores',]))->name('suppliers');
    Route::middleware('permission:invoicing.products.view')->get('/products', \App\Support\EcraReact::pagina('facturacao/produtos', 'Produtos'))->name('products');
    Route::middleware('permission:invoicing.categories.view')->get('/categories', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Categorias', ['tipo' => 'categorias',]))->name('categories');
    Route::middleware('permission:invoicing.brands.view')->get('/brands', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Marcas', ['tipo' => 'marcas',]))->name('brands');
    
    // Proformas e Faturas de Venda
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::middleware('permission:invoicing.sales.proformas.view')->get('/proformas', \App\Support\EcraReact::pagina('facturacao/documentos', 'Proformas de Venda', ['tipo' => 'proformas-venda',]))->name('proformas');
        // `?duplicar=123` traz o conteúdo de outra proforma — ver DuplicarNaMorada.
        Route::get('/proformas/create', \App\Support\EcraReact::pagina('facturacao/emitir-proposta', 'Emitir · Proformas de Venda', ['tipo' => 'proformas-venda',], fn () => \App\Support\DuplicarNaMorada::props()))->middleware('permission:invoicing.sales.proformas.create')->name('proformas.create');
        Route::get('/proformas/{id}/edit', \App\Support\EcraReact::pagina('facturacao/emitir-proposta', 'Emitir · Proformas de Venda', ['tipo' => 'proformas-venda',]))->middleware('permission:invoicing.sales.proformas.edit')->name('proformas.edit');
        Route::get('/proformas/{id}/pdf', [\App\Http\Controllers\Invoicing\ProformaController::class, 'generatePdf'])->middleware('permission:invoicing.sales.proformas.view')->name('proformas.pdf');
        Route::get('/proformas/{id}/preview', [\App\Http\Controllers\Invoicing\ProformaController::class, 'previewHtml'])->middleware('permission:invoicing.sales.proformas.view')->name('proformas.preview');

        // Orçamentos (documento comercial, não fiscal)
        Route::middleware('permission:invoicing.sales.quotes.view')->get('/quotes', \App\Support\EcraReact::pagina('facturacao/documentos', 'Orçamentos', ['tipo' => 'orcamentos',]))->name('quotes');
        Route::get('/quotes/create', \App\Support\EcraReact::pagina('facturacao/emitir-proposta', 'Emitir · Orçamentos', ['tipo' => 'orcamentos',]))->middleware('permission:invoicing.sales.quotes.create')->name('quotes.create');
        Route::get('/quotes/{id}/edit', \App\Support\EcraReact::pagina('facturacao/emitir-proposta', 'Emitir · Orçamentos', ['tipo' => 'orcamentos',]))->middleware('permission:invoicing.sales.quotes.edit')->name('quotes.edit');
        Route::get('/quotes/{id}/pdf', [\App\Http\Controllers\Invoicing\QuoteController::class, 'generatePdf'])->middleware('permission:invoicing.sales.quotes.view')->name('quotes.pdf');
        Route::get('/quotes/{id}/preview', [\App\Http\Controllers\Invoicing\QuoteController::class, 'previewHtml'])->middleware('permission:invoicing.sales.quotes.view')->name('quotes.preview');

        // Modelos de proposta: o desenho do orçamento, separado dos números.
        // Vive sob as permissões de orçamento — quem faz orçamentos é quem
        // precisa de mexer nos modelos.
        Route::middleware('permission:invoicing.sales.quotes.view')
            ->get('/quote-templates', \App\Support\EcraReact::pagina('facturacao/modelos-de-proposta', 'Modelos de Proposta'))
            ->name('quote-templates');
        Route::middleware('permission:invoicing.sales.quotes.edit')
            ->get('/quote-templates/{id}/edit', \App\Support\EcraReact::pagina('facturacao/editor-de-modelo', 'Editor de Modelo de Proposta'))
            ->name('quote-templates.edit');
        Route::middleware('permission:invoicing.sales.quotes.view')
            ->get('/quote-templates/{id}/preview', [\App\Http\Controllers\Invoicing\QuoteController::class, 'previewModelo'])
            ->name('quote-templates.preview');

        // Faturas de Venda
        // `?type=FR` é como o menu liga direito às Faturas-Recibo: a lista abre
        // já filtrada, como abria em Livewire.
        Route::middleware('permission:invoicing.sales.invoices.view')->get('/invoices', \App\Support\EcraReact::pagina('facturacao/lista-de-facturas', 'Faturas de Venda', [], fn () => in_array(request()->query('type'), ['FT', 'FR'], true) ? ['tipo' => request()->query('type')] : []))->name('invoices');

        /*
         * A MESMA LISTA, EM REACT, NOUTRA MORADA.
         *
         * Enquanto a migração durar, os dois ecrãs vivem ao lado um do outro:
         * compara-se, e o de sempre nunca deixa de estar lá. Quando o novo
         * estiver provado, é a rota de cima que passa a apontar para aqui.
         *
         * Mesma permissão. Um ecrã novo não é uma porta nova.
         */
        Route::get('/invoices/create', \App\Support\EcraReact::pagina('facturacao/emitir-factura', 'Fatura de Venda', [], fn () => \App\Support\DuplicarNaMorada::props()))->middleware('permission:invoicing.sales.invoices.create')->name('invoices.create');
        // Reabrir o rascunho é de quem o pode criar: o Vendedor não tem `.edit`
        // e dava 403 na factura que ele próprio gravou. A API abre com `.view`
        // e recusa gravar o que já foi emitido (auditoria de 2026-09-13).
        Route::get('/invoices/{id}/edit', \App\Support\EcraReact::pagina('facturacao/emitir-factura', 'Fatura de Venda'))->middleware('permission:invoicing.sales.invoices.edit|invoicing.sales.invoices.create')->name('invoices.edit');
        Route::get('/invoices/{id}/pdf', [\App\Http\Controllers\Invoicing\SalesInvoiceController::class, 'generatePdf'])->middleware('permission:invoicing.sales.invoices.view')->name('invoices.pdf');
        Route::get('/invoices/{id}/preview', [\App\Http\Controllers\Invoicing\SalesInvoiceController::class, 'previewHtml'])->middleware('permission:invoicing.sales.invoices.view')->name('invoices.preview');
        /*
         * O TALÃO DE 80 mm COMO PÁGINA.
         *
         * O balcão em React mostra-o dentro de um `iframe` e manda-o
         * imprimir sem abrir separador nenhum. O corpo é a mesma parcial que
         * o modal do POS inclui — um documento fiscal desenha-se num sítio só.
         */
        Route::get('/invoices/{id}/talao', [\App\Http\Controllers\Invoicing\SalesInvoiceController::class, 'talao'])
            ->whereNumber('id')
            ->middleware('permission:invoicing.sales.invoices.view')->name('invoices.talao');
        Route::get('/invoices/{id}/download', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'downloadPdf'])->middleware('permission:invoicing.sales.invoices.view')->name('invoices.download');
        
        // TESTE - Template simplificado
        Route::get('/proformas/{id}/pdf-test', function($id) {
            $proforma = \App\Models\Invoicing\SalesProforma::with(['client', 'items', 'warehouse'])
                ->where('tenant_id', activeTenantId())
                ->findOrFail($id);
            
            $tenant = \App\Models\Tenant::find(activeTenantId());
            
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoicing.proforma_test', [
                'proforma' => $proforma,
                'tenant' => $tenant,
            ]);
            
            $pdf->setPaper('A4', 'portrait');
            
            return $pdf->stream('proforma_test.pdf');
        })->middleware('permission:invoicing.sales.proformas.view')->name('proformas.pdf-test');
    });
    
    // Proformas e Faturas de Compra
    Route::prefix('purchases')->name('purchases.')->group(function () {
        Route::middleware('permission:invoicing.purchases.proformas.view')->get('/proformas', \App\Support\EcraReact::pagina('facturacao/documentos', 'Proformas de Compra', ['tipo' => 'proformas-compra',]))->name('proformas');
        Route::get('/proformas/create', \App\Support\EcraReact::pagina('facturacao/emitir-proposta', 'Emitir · Proformas de Compra', ['tipo' => 'proformas-compra',], fn () => \App\Support\DuplicarNaMorada::props()))->middleware('permission:invoicing.purchases.proformas.create')->name('proformas.create');
        Route::get('/proformas/{id}/edit', \App\Support\EcraReact::pagina('facturacao/emitir-proposta', 'Emitir · Proformas de Compra', ['tipo' => 'proformas-compra',]))->middleware('permission:invoicing.purchases.proformas.edit')->name('proformas.edit');
        Route::get('/proformas/{id}/pdf', [\App\Http\Controllers\Invoicing\PurchaseProformaController::class, 'generatePdf'])->middleware('permission:invoicing.purchases.proformas.view')->name('proformas.pdf');
        Route::get('/proformas/{id}/preview', [\App\Http\Controllers\Invoicing\PurchaseProformaController::class, 'previewHtml'])->middleware('permission:invoicing.purchases.proformas.view')->name('proformas.preview');
        
        // Faturas de Compra
        Route::middleware('permission:invoicing.purchases.invoices.view')->get('/invoices', \App\Support\EcraReact::pagina('facturacao/documentos', 'Faturas de Compra', ['tipo' => 'facturas-compra',]))->name('invoices');
        Route::get('/invoices/create', \App\Support\EcraReact::pagina('facturacao/emitir-factura-de-compra', 'Fatura de Compra', [], fn () => \App\Support\DuplicarNaMorada::props()))->middleware('permission:invoicing.purchases.invoices.create')->name('invoices.create');
        Route::get('/invoices/{id}/edit', \App\Support\EcraReact::pagina('facturacao/emitir-factura-de-compra', 'Fatura de Compra'))->middleware('permission:invoicing.purchases.invoices.edit|invoicing.purchases.invoices.create')->name('invoices.edit');
        Route::get('/invoices/{id}/pdf', [\App\Http\Controllers\Invoicing\PurchaseInvoiceController::class, 'generatePdf'])->middleware('permission:invoicing.purchases.invoices.view')->name('invoices.pdf');
        Route::get('/invoices/{id}/preview', [\App\Http\Controllers\Invoicing\PurchaseInvoiceController::class, 'previewHtml'])->middleware('permission:invoicing.purchases.invoices.view')->name('invoices.preview');
    });
    
    // Recibos
    /*
     * A LISTA EXIGIA A PERMISSÃO E OS IRMÃOS NÃO.
     *
     * O editor, o PDF e a pré-visualização abrem-se A PARTIR da lista e ficaram
     * sem guarda nenhuma: um `/{id}/pdf` entregava o documento a quem o
     * pedisse. Cada um passa a pedir o verbo que lhe corresponde — ver para
     * abrir e imprimir, criar para criar, editar para editar.
     */
    Route::prefix('receipts')->name('receipts.')->group(function () {
        Route::middleware('permission:invoicing.receipts.view')->get('/', \App\Support\EcraReact::pagina('facturacao/documentos', 'Recibos', ['tipo' => 'recibos',]))->name('index');
        Route::middleware('permission:invoicing.receipts.create')->get('/create', \App\Support\EcraReact::pagina('facturacao/registar-recibo', 'Recibo', [], fn () => \App\Support\FacturaNaMorada::props()))->name('create');
        Route::middleware('permission:invoicing.receipts.edit|invoicing.receipts.create|invoicing.receipts.view')->get('/{id}/edit', \App\Support\EcraReact::pagina('facturacao/registar-recibo', 'Recibo'))->name('edit');
        Route::middleware('permission:invoicing.receipts.view')->get('/{id}/pdf', [\App\Http\Controllers\Invoicing\ReceiptController::class, 'generatePdf'])->name('pdf');
        Route::middleware('permission:invoicing.receipts.view')->get('/{id}/preview', [\App\Http\Controllers\Invoicing\ReceiptController::class, 'previewHtml'])->name('preview');
    });
    
    // Notas de Crédito
    Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
        Route::middleware('permission:invoicing.credit-notes.view')->get('/', \App\Support\EcraReact::pagina('facturacao/documentos', 'Notas de Crédito', ['tipo' => 'notas-credito',]))->name('index');
        Route::middleware('permission:invoicing.credit-notes.create')->get('/create', \App\Support\EcraReact::pagina('facturacao/emitir-nota', 'Nota de Crédito', ['tipo' => 'credito',], fn () => \App\Support\FacturaNaMorada::props()))->name('create');
        Route::middleware('permission:invoicing.credit-notes.edit|invoicing.credit-notes.create|invoicing.credit-notes.view')->get('/{id}/edit', \App\Support\EcraReact::pagina('facturacao/emitir-nota', 'Nota de Crédito', ['tipo' => 'credito',]))->name('edit');
        Route::middleware('permission:invoicing.credit-notes.view')->get('/{id}/pdf', [\App\Http\Controllers\Invoicing\CreditNoteController::class, 'generatePdf'])->name('pdf');
        Route::middleware('permission:invoicing.credit-notes.view')->get('/{id}/preview', [\App\Http\Controllers\Invoicing\CreditNoteController::class, 'previewHtml'])->name('preview');
    });
    
    // Notas de Débito
    Route::prefix('debit-notes')->name('debit-notes.')->group(function () {
        Route::middleware('permission:invoicing.debit-notes.view')->get('/', \App\Support\EcraReact::pagina('facturacao/documentos', 'Notas de Débito', ['tipo' => 'notas-debito',]))->name('index');
        Route::middleware('permission:invoicing.debit-notes.create')->get('/create', \App\Support\EcraReact::pagina('facturacao/emitir-nota', 'Nota de Débito', ['tipo' => 'debito',], fn () => \App\Support\FacturaNaMorada::props()))->name('create');
        Route::middleware('permission:invoicing.debit-notes.edit|invoicing.debit-notes.create|invoicing.debit-notes.view')->get('/{id}/edit', \App\Support\EcraReact::pagina('facturacao/emitir-nota', 'Nota de Débito', ['tipo' => 'debito',]))->name('edit');
        Route::middleware('permission:invoicing.debit-notes.view')->get('/{id}/pdf', [\App\Http\Controllers\Invoicing\DebitNoteController::class, 'generatePdf'])->name('pdf');
        Route::middleware('permission:invoicing.debit-notes.view')->get('/{id}/preview', [\App\Http\Controllers\Invoicing\DebitNoteController::class, 'previewHtml'])->name('preview');
    });
    
    // Importações
    Route::prefix('imports')->name('imports.')->group(function () {
        Route::middleware('permission:invoicing.imports.view')->get('/', \App\Support\EcraReact::pagina('facturacao/importacoes', 'Importações'))->name('index');
    });

    // Recuperar uma cópia de segurança do PWA (aparelho que não sincronizou).
    // A permissão é a de criar vendas no POS: quem pode emitir é quem pode
    // recuperar o que já foi emitido offline.
    Route::middleware('permission:invoicing.pos.sell')
        ->get('/importar-copia-offline', \App\Support\EcraReact::pagina('facturacao/importar-copia-offline', 'Importar Cópia Offline'))
        ->name('importar-copia-offline');
    
    // Adiantamentos
    Route::prefix('advances')->name('advances.')->group(function () {
        Route::middleware('permission:invoicing.advances.view')->get('/', \App\Support\EcraReact::pagina('facturacao/documentos', 'Adiantamentos', ['tipo' => 'adiantamentos',]))->name('index');
        Route::middleware('permission:invoicing.advances.create')->get('/create', \App\Support\EcraReact::pagina('facturacao/emitir-adiantamento', 'Adiantamento'))->name('create');
        Route::middleware('permission:invoicing.advances.edit')->get('/{id}/edit', \App\Support\EcraReact::pagina('facturacao/emitir-adiantamento', 'Adiantamento'))->name('edit');
        Route::middleware('permission:invoicing.advances.view')->get('/{id}/pdf', [\App\Http\Controllers\Invoicing\AdvanceController::class, 'generatePdf'])->name('pdf');
        Route::middleware('permission:invoicing.advances.view')->get('/{id}/preview', [\App\Http\Controllers\Invoicing\AdvanceController::class, 'previewHtml'])->name('preview');
    });
    
    // Configurações
    Route::middleware('permission:invoicing.settings.view')->get('/settings', \App\Support\EcraReact::pagina('facturacao/definicoes', 'Configurações de Faturação'))->name('settings');
    Route::middleware('permission:invoicing.settings.view')
        ->get('/settings/notification-gateways', \App\Support\EcraReact::pagina('facturacao/gateways-de-notificacao', 'Gateways de Notificação'))
        ->name('notification-gateways');

    // Trilha de auditoria. Protegida pela mesma permissão das definições: quem
    // pode ver a configuração fiscal da empresa pode ver quem lhe mexeu.
    Route::middleware('permission:invoicing.settings.view')
        ->get('/auditoria', \App\Support\EcraReact::pagina('facturacao/auditoria', 'Auditoria'))->name('audit');
    Route::middleware('permission:invoicing.series.view')->get('/series', \App\Support\EcraReact::pagina('facturacao/series', 'Séries de Documentos'))->name('series');
    Route::middleware('permission:invoicing.taxes.view')->get('/taxes', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Impostos', ['tipo' => 'impostos',]))->name('taxes');
    Route::middleware('permission:invoicing.settings.view')->get('/payment-terms', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Condições de Pagamento', ['tipo' => 'condicoes-de-pagamento',]))->name('payment-terms');
    Route::middleware('permission:invoicing.agt.view')->get('/agt-settings', \App\Support\EcraReact::pagina('facturacao/agt', 'Configurações AGT'))->name('agt-settings');
    Route::middleware('permission:invoicing.agt.view')->get('/agt-credentials', \App\Support\EcraReact::pagina('facturacao/credenciais-agt', 'Configuração AGT — Contribuinte'))->name('agt-credentials');
    
    // Armazéns e Stock
    Route::get('/warehouses', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Armazéns', ['tipo' => 'armazens',]))->middleware('permission:invoicing.warehouses.view')->name('warehouses');
    // Com permissão, como todas as irmãs do módulo. Sem ela, qualquer papel com
    // acesso à faturação via o inventário e a valorização inteiros — as acções
    // já estavam travadas dentro do componente, mas a leitura não. Os papéis que
    // o seeder deliberadamente não contempla (restaurante e contabilidade)
    // deixam de entrar; quem precisar, o administrador da empresa concede.
    Route::middleware('permission:invoicing.stock.view')
        ->get('/stock', \App\Support\EcraReact::pagina('facturacao/stock', 'Gestão de Stock'))->name('stock');

    /*
     * O MAPA DE STOCK EM PAPEL E EM EXCEL.
     *
     * Os filtros do ecrã viajam no URL e a consulta é a MESMA da lista: quem
     * imprime está a conferir a prateleira contra aquilo que estava a ver, e um
     * mapa que mostrasse outra coisa seria pior do que não haver mapa.
     */
    Route::middleware('permission:invoicing.stock.view')->group(function () {
        Route::get('/stock/imprimir', [\App\Http\Controllers\Invoicing\StockExportController::class, 'imprimir'])->name('stock.imprimir');
        Route::get('/stock/excel', [\App\Http\Controllers\Invoicing\StockExportController::class, 'excel'])->name('stock.excel');
    });

    // As quebras: expirado/estragado/partido/perdido, com relatório próprio.
    // Genérico de propósito — salão, oficina e restaurante usam os mesmos
    // artigos, e a perda regista-se num sítio só.
    Route::middleware('permission:invoicing.stock.view')
        ->get('/quebras', \App\Support\EcraReact::pagina('facturacao/quebras', 'Quebras de Stock'))->name('quebras');

    // Documento do lote de movimentação (MOV/AAAA/NNNNNN).
    // A referência leva barras, daí o `where` — sem ele o Laravel parte o
    // parâmetro no primeiro '/' e a rota nunca corresponde.
    // Quem vê a lista de transferências abre o papel do lote: só com
    // `stock.view` aqui, quem transfere sem mexer em stock via os ícones e
    // levava 403 — o controlador já aceitava estas permissões todas.
    // E quem vê o relatório dos ajustes de stock (`reports.view`) também: o
    // mapa mostra cada lote com o seu papel ao lado, e a ligação tem de abrir
    // a quem o mapa a mostra.
    Route::get('/stock/movimentacao/{reference}/pdf', [\App\Http\Controllers\Invoicing\StockMovementController::class, 'batchPdf'])
        ->where('reference', '[A-Za-z0-9/_-]+')
        ->middleware('permission:invoicing.stock.view|invoicing.stock.edit|invoicing.warehouse-transfer.view|invoicing.warehouse-transfer.create|invoicing.inter-company-transfer.view|invoicing.inter-company-transfer.create|invoicing.reports.view')->name('stock.batch-pdf');
    Route::get('/stock/movimentacao/{reference}/preview', [\App\Http\Controllers\Invoicing\StockMovementController::class, 'batchPreview'])
        ->where('reference', '[A-Za-z0-9/_-]+')
        ->middleware('permission:invoicing.stock.view|invoicing.stock.edit|invoicing.warehouse-transfer.view|invoicing.warehouse-transfer.create|invoicing.inter-company-transfer.view|invoicing.inter-company-transfer.create|invoicing.reports.view')->name('stock.batch-preview');
    Route::get('/product-batches', \App\Support\EcraReact::pagina('facturacao/lotes', 'Lotes e Validades'))->middleware('permission:invoicing.product-batches.view')->name('product-batches');
    Route::get('/warehouse-transfer', \App\Support\EcraReact::pagina('facturacao/transferencias-entre-armazens', 'Transferências e Ajustes de Stock'))->middleware('permission:invoicing.warehouse-transfer.view')->name('warehouse-transfer');
    Route::get('/inter-company-transfer', \App\Support\EcraReact::pagina('facturacao/transferencias-entre-empresas', 'Transferências Inter-Empresas'))->middleware('permission:invoicing.inter-company-transfer.view')->name('inter-company-transfer');
    
    // Relatórios
    /*
     * O aviso de validade manda `?type=expired` — é a ligação do email que diz
     * «ACÇÃO URGENTE». O mapa chama àquilo `reportType`, e o endereço era
     * ignorado: quem carregava caía na lista dos que estão A EXPIRAR e não na
     * dos que JÁ EXPIRARAM. Traduz-se aqui, que é onde a ligação chega.
     */
    Route::get('/expiry-report', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Validade de Produtos', ['slug' => 'expiry-report',], function () {
        $pedido = (string) request()->query('type', '');

        return array_key_exists($pedido, \App\Services\Invoicing\Relatorios\Validades::TIPOS)
            ? ['filtrosIniciais' => ['reportType' => $pedido]]
            : [];
    }))->middleware('permission:invoicing.reports.view|invoicing.stock.view')->name('expiry-report');
    
    Route::prefix('reports')->name('reports.')->middleware('permission:invoicing.reports.view')->group(function () {
        Route::get('/', \App\Support\EcraReact::pagina('facturacao/relatorios-hub', 'Relatórios - Faturação'))->name('hub');

        // Relatório em gráficos: a mesma facturação dos outros mapas, mas
        // vista de relance — serve a pergunta anterior a "quanto exactamente".
        Route::get('/charts', \App\Support\EcraReact::pagina('facturacao/graficos', 'Relatório em Gráficos'))->name('charts');

        Route::get('/sales', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Mapa de Vendas', ['slug' => 'sales',]))->name('sales');
        Route::get('/purchases', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Mapa de Compras', ['slug' => 'purchases',]))->name('purchases');
        Route::get('/top-clients', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Top Clientes', ['slug' => 'top-clients',]))->name('top-clients');
        Route::get('/top-products', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Top Produtos Vendidos', ['slug' => 'top-products',]))->name('top-products');
        Route::get('/top-suppliers', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Top Fornecedores', ['slug' => 'top-suppliers',]))->name('top-suppliers');
        Route::get('/accounts-receivable', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Contas a Receber', ['slug' => 'accounts-receivable',]))->name('accounts-receivable');
        Route::get('/accounts-payable', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Contas a Pagar', ['slug' => 'accounts-payable',]))->name('accounts-payable');
        Route::get('/aging-clients', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Aging de Clientes', ['slug' => 'aging-clients',]))->name('aging-clients');
        Route::get('/vat', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Mapa de IVA', ['slug' => 'vat',]))->name('vat');
        Route::get('/documents', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Mapa de Documentos', ['slug' => 'documents',]))->name('documents');
        Route::get('/profit-loss', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Lucros e Perdas (DRE)', ['slug' => 'profit-loss',]))->name('profit-loss');
        Route::get('/margin', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Análise de Margem', ['slug' => 'margin',]))->name('margin');
        Route::get('/best-supplier', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Melhor Fornecedor', ['slug' => 'best-supplier',]))->name('best-supplier');
        Route::get('/comparative', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Comparativo entre Períodos', ['slug' => 'comparative',]))->name('comparative');
        Route::get('/product-performance', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Desempenho de Produtos', ['slug' => 'product-performance',]))->name('product-performance');
        Route::get('/services', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Mapa de Serviços', ['slug' => 'services',]))->name('services');
        Route::get('/price-list', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Tabela de Preços e Lucro', ['slug' => 'price-list',]))->name('price-list');
        Route::get('/payment-methods', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Recebimentos por Meio de Pagamento', ['slug' => 'payment-methods',]))->name('payment-methods');
        Route::get('/sales-by-user', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Vendas por Vendedor', ['slug' => 'sales-by-user',]))->name('sales-by-user');
        Route::get('/stock-adjustments', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Ajustes de Stock', ['slug' => 'stock-adjustments',]))->name('stock-adjustments');

        // Extracto de conta corrente — serve cliente e fornecedor.
        Route::get('/account-statement', \App\Support\EcraReact::pagina('facturacao/relatorio', 'Extracto de Conta Corrente', ['slug' => 'account-statement',]))->name('account-statement');
        Route::get('/account-statement/pdf', [\App\Http\Controllers\Invoicing\AccountStatementController::class, 'pdf'])
            ->name('account-statement.pdf');
    });
    
    // Guias de Transporte / Remessa (GT / GR)
    Route::get('/transport-guides', \App\Support\EcraReact::pagina('facturacao/guias-de-transporte', 'Guias de Transporte'))->middleware('permission:invoicing.transport-guides.view')->name('transport-guides');
    Route::get('/transport-guides/{id}/pdf', [\App\Http\Controllers\Invoicing\TransportGuideController::class, 'pdf'])->middleware('permission:invoicing.transport-guides.view')->name('transport-guides.pdf');

    // SAFT
    Route::get('/saft-generator', \App\Support\EcraReact::pagina('facturacao/saft', 'Gerador SAFT-AO'))->middleware('permission:invoicing.saft.view')->name('saft-generator');

    // Adquirente AGT (DS.120 §§4.3, 4.4, 4.7)
    Route::middleware('permission:invoicing.agt.view')
        ->get('/agt-adquirente', \App\Support\EcraReact::pagina('facturacao/adquirente-agt', 'Facturas Recebidas (Adquirente) — AGT'))
        ->name('agt-adquirente');
    
    // POS
    Route::get('/pos', \App\Support\EcraReact::pagina('facturacao/pos', 'POS — Ponto de Venda'))->middleware('permission:invoicing.pos.access')->name('pos');
    Route::get('/pos/shifts', \App\Support\EcraReact::pagina('facturacao/turnos', 'POS - Ponto de Venda'))->middleware('permission:invoicing.pos.access')->name('pos.shifts');
    Route::get('/pos/shift-history', \App\Support\EcraReact::pagina('facturacao/historico-de-turnos', 'Histórico de Turnos'))->middleware('permission:invoicing.pos.access')->name('pos.shift-history');
    Route::get('/pos/reports', \App\Support\EcraReact::pagina('facturacao/pos-relatorio', 'Relatórios do POS'))->middleware('permission:invoicing.pos.reports')->name('pos.reports');

    // POS Exports (PDF / Excel)
    Route::get('/pos/export/shift/{shift}/pdf', [\App\Http\Controllers\Pos\PosExportController::class, 'shiftPdf'])->middleware('permission:invoicing.pos.access')->name('pos.export.shift-pdf');
    Route::get('/pos/export/shift/{shift}/ticket', [\App\Http\Controllers\Pos\PosExportController::class, 'shiftTicket'])->middleware('permission:invoicing.pos.access')->name('pos.export.shift-ticket');
    Route::get('/pos/export/sales-report/pdf', [\App\Http\Controllers\Pos\PosExportController::class, 'salesReportPdf'])->middleware('permission:invoicing.pos.reports')->name('pos.export.sales-pdf');
    Route::get('/pos/export/sales-report/excel', [\App\Http\Controllers\Pos\PosExportController::class, 'salesReportExcel'])->middleware('permission:invoicing.pos.reports')->name('pos.export.sales-excel');
});

// Treasury Module Routes
Route::middleware(['auth', 'tenant.module:treasury'])->prefix('treasury')->name('treasury.')->group(function () {
    Route::middleware('permission:treasury.transactions.view')
        ->get('/dashboard', \App\Support\EcraReact::pagina('tesouraria/painel', 'Dashboard Tesouraria'))
        ->name('dashboard');
    Route::middleware('permission:treasury.reports.view')
        ->get('/reports', \App\Support\EcraReact::pagina('tesouraria/relatorios', 'Relatórios Financeiros'))
        ->name('reports');
    // Descarga dos relatórios financeiros. Só se via no ecrã, e um relatório
    // que não se pode levar ao banco nem ao contabilista serve para pouco.
    // E A DESCARGA PEDE A MESMA PERMISSÃO QUE O ECRÃ. Não pedia nenhuma:
    // quem não podia abrir os relatórios descarregava-os na mesma, com a
    // demonstração de resultados inteira lá dentro.
    Route::middleware('permission:treasury.reports.view')
        ->get('/reports/pdf', [\App\Http\Controllers\Treasury\ReportExportController::class, 'pdf'])
        ->name('reports.pdf');
    Route::middleware('permission:treasury.reports.view')
        ->get('/reports/excel', [\App\Http\Controllers\Treasury\ReportExportController::class, 'excel'])
        ->name('reports.excel');
    /*
     * OS CATÁLOGOS DA TESOURARIA, no ecrã genérico.
     *
     * Bancos, formas de pagamento, caixas, tipos e categorias de movimento
     * tinham cinco componentes Livewire com o mesmo desenho — lista, modal,
     * gravar, apagar. Passam pelo mesmo ecrã que os seis da facturação já
     * usam; o que os distingue vive no `Catalogos`.
     *
     * E GANHAM GUARDA. As 23 permissões `treasury.*` existiam e nenhuma rota
     * as aplicava: bastava ter o módulo activo para mexer em tudo. Agora a
     * morada exige a de VER, e a API exige a de criar, editar ou apagar em
     * cada acção.
     */
    Route::middleware('permission:treasury.payment-methods.view')
        ->get('/payment-methods', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Formas de Pagamento', ['tipo' => 'formas-de-pagamento']))
        ->name('payment-methods');
    Route::middleware('permission:treasury.banks.view')
        ->get('/banks', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Bancos', ['tipo' => 'bancos']))
        ->name('banks');
    Route::middleware('permission:treasury.cash-registers.view')
        ->get('/cash-registers', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Caixas', ['tipo' => 'caixas']))
        ->name('cash-registers');
    Route::middleware('permission:treasury.transactions.view')
        ->get('/transaction-types', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Tipos de Movimento', ['tipo' => 'tipos-de-movimento']))
        ->name('transaction-types');
    Route::middleware('permission:treasury.transactions.view')
        ->get('/transaction-categories', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Categorias de Movimento', ['tipo' => 'categorias-de-movimento']))
        ->name('transaction-categories');
    Route::middleware('permission:treasury.accounts.view')
        ->get('/accounts', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Contas Bancárias', ['tipo' => 'contas-bancarias']))
        ->name('accounts');
    /*
     * OS MOVIMENTOS, em React — e com guarda.
     *
     * A morada não exigia permissão nenhuma: bastava ter o módulo activo
     * para lançar, editar e apagar dinheiro. Agora exige a de VER, e a API
     * exige a de criar, editar, apagar ou estornar em cada acção.
     */
    Route::middleware('permission:treasury.transactions.view')
        ->get('/transactions', \App\Support\EcraReact::pagina('tesouraria/movimentos', 'Transações'))
        ->name('transactions');
    Route::middleware('permission:treasury.transfers.view')
        ->get('/transfers', \App\Support\EcraReact::pagina('tesouraria/transferencias', 'Transferências'))
        ->name('transfers');
});

/*
 * OS EVENTOS — as moradas de sempre, agora em React.
 *
 * A ROTA DE TESTE DO QR CODE SAIU DAQUI. Era um `/events/equipment/test-qrcode`
 * que desenhava um QR com o endereço `soserp.test` escrito à mão e devolvia o
 * `getMessage()`, o ficheiro e a linha da excepção a quem o abrisse — um
 * diagnóstico de programador exposto a qualquer utilizador autenticado. O QR
 * verdadeiro de cada equipamento continua em `/{id}/qrcode`.
 */
Route::middleware(['auth', 'tenant.module:eventos'])->prefix('events')->name('events.')->group(function () {
    Route::middleware('permission:events.dashboard.view')
        ->get('/dashboard', \App\Support\EcraReact::pagina('eventos/painel', 'Painel dos Eventos'))->name('dashboard');

    Route::middleware('permission:events.calendar.view')
        ->get('/calendar', \App\Support\EcraReact::pagina('eventos/agenda', 'Agenda de Eventos'))->name('calendar');

    Route::middleware('permission:events.reports.view')
        ->get('/reports', \App\Support\EcraReact::pagina('eventos/relatorios', 'Relatórios de Eventos'))->name('reports');

    Route::prefix('equipment')->name('equipment.')->middleware('permission:events.equipment.view')->group(function () {
        Route::get('/', \App\Support\EcraReact::pagina('eventos/equipamentos', 'Equipamentos'))->name('index');
        Route::get('/dashboard', \App\Support\EcraReact::pagina('eventos/equipamentos-painel', 'Painel dos Equipamentos'))->name('dashboard');
        Route::get('/sets', \App\Support\EcraReact::pagina('eventos/equipamentos', 'Conjuntos de Equipamentos', ['separador' => 'conjuntos']))->name('sets');
        Route::get('/categories', \App\Support\EcraReact::pagina('eventos/equipamentos', 'Categorias de Equipamentos', ['separador' => 'categorias']))->name('categories');

        /*
         * O QR COLADO AO EQUIPAMENTO aponta para aqui, e os que já estão
         * impressos continuam a apontar: a morada não muda. O que muda é o
         * destino — a lista em React abre a ficha do equipamento lido.
         */
        Route::get('/scan/{id}', function (int $id) {
            $equipamento = \App\Models\Equipment::forTenant()->findOrFail($id);

            return redirect()->route('events.equipment.index', ['equipamento' => $equipamento->id]);
        })->whereNumber('id')->name('scan');

        Route::get('/{id}/qrcode', [\App\Http\Controllers\EquipmentController::class, 'generateQrCode'])->name('qrcode');
        Route::get('/{id}/qrcode/print', [\App\Http\Controllers\EquipmentController::class, 'printQrCode'])->name('qrcode.print');
    });

    Route::middleware('permission:events.venues.view')
        ->get('/venues', \App\Support\EcraReact::pagina('eventos/locais', 'Locais'))->name('venues.index');

    Route::middleware('permission:events.types.view')
        ->get('/types', \App\Support\EcraReact::pagina('eventos/tipos', 'Tipos de Eventos'))->name('types.index');

    Route::middleware('permission:events.technicians.view')
        ->get('/technicians', \App\Support\EcraReact::pagina('eventos/tecnicos', 'Técnicos'))->name('technicians.index');
});

// ============================================
// PORTAL DO CLIENTE
// ============================================

// Login do Cliente
Route::group([], function () {
    Route::get('/client/login', \App\Support\EcraReact::entradaCliente('cliente/entrada', 'Portal do Cliente'))->name('client.login');
    // Tentativas limitadas por email e IP: ver EntradaNoPortalController.
    Route::post('/client/login', [\App\Http\Controllers\Cliente\EntradaNoPortalController::class, 'entrar'])->name('client.login.entrar');
    // A vista `client.forgot-password` nunca existiu: a ligação dava erro 500.
    Route::get('/client/forgot-password', \App\Support\EcraReact::entradaCliente('cliente/esqueci-a-senha', 'Esqueceu a senha?'))->name('client.forgot-password');
});

// Rotas protegidas do cliente
Route::middleware(['auth:client'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', \App\Support\EcraReact::cliente('cliente/painel', 'Portal do Cliente'))->name('dashboard');
    Route::get('/statement', \App\Support\EcraReact::cliente('cliente/extrato', 'Extrato Financeiro'))->name('statement');
    Route::get('/events', \App\Support\EcraReact::cliente('cliente/eventos', 'Meus Eventos'))->name('events');
    Route::get('/invoices', \App\Support\EcraReact::cliente('cliente/facturas', 'Minhas Faturas'))->name('invoices');
    Route::get('/proformas', \App\Support\EcraReact::cliente('cliente/proformas', 'Minhas Proformas'))->name('proformas');
    Route::get('/profile', \App\Support\EcraReact::cliente('cliente/perfil', 'Meu Perfil'))->name('profile');

    Route::prefix('api')->name('api.')->group(function () {
        $c = \App\Http\Controllers\Api\Cliente\PortalDoClienteApiController::class;

        Route::get('/painel', [$c, 'painel'])->name('painel');
        Route::get('/extrato', [$c, 'extrato'])->name('extrato');
        Route::get('/facturas', [$c, 'facturas'])->name('facturas');
        Route::get('/proformas', [$c, 'proformas'])->name('proformas');
        Route::get('/eventos', [$c, 'eventos'])->name('eventos');
        Route::get('/perfil', [$c, 'perfil'])->name('perfil');
        Route::put('/perfil', [$c, 'guardarPerfil'])->name('perfil.guardar');
        Route::put('/senha', [$c, 'mudarSenha'])->name('senha');
    });

    // Logout do portal (POST) — funciona a partir de qualquer página do portal
    Route::post('/logout', function (\Illuminate\Http\Request $request) {
        \Illuminate\Support\Facades\Auth::guard('client')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('client.login');
    })->name('logout');
});

// HR Module Routes
Route::middleware(['auth', 'tenant.module:rh'])->prefix('hr')->name('hr.')->group(function () {
    /*
     * O PAINEL — a primeira coisa que se vê no módulo, e a permissão
     * `hr.dashboard.view` existia desde sempre sem ninguém a aplicar.
     */
    Route::middleware('permission:hr.dashboard.view')
        ->get('/dashboard', \App\Support\EcraReact::pagina('rh/painel', 'Painel de RH'))
        ->name('dashboard');
    Route::middleware('permission:employees.view')
        ->get('/employees', \App\Support\EcraReact::pagina('rh/funcionarios', 'Funcionários'))
        ->name('employees.index');
    // A ficha em PDF leva a morada, o BI, o IBAN e o salário: pede a mesma
    // permissão do ecrã de onde se abre.
    Route::get('/employees/{id}/sheet', [\App\Http\Controllers\HR\EmployeeController::class, 'employeeSheet'])
        ->middleware('permission:employees.view')->name('employees.sheet');
    Route::get('/vacations/{id}/pdf', [\App\Http\Controllers\HR\VacationController::class, 'generatePDF'])
        ->middleware('permission:hr.vacations.view')->name('vacations.pdf');
    Route::get('/leaves/{id}/pdf', [\App\Http\Controllers\HR\LeaveController::class, 'generatePDF'])
        ->middleware('permission:hr.leaves.view')->name('leaves.pdf');
    /*
     * A FOLHA DE PAGAMENTO — o ecrã onde um erro custa dinheiro a alguém.
     *
     * `payroll.process` é a permissão que existia e que nenhuma destas rotas
     * aplicava: ver a folha é ver o salário de toda a gente, e os PDF levam o
     * recibo de cada um.
     */
    Route::middleware('permission:payroll.process')
        ->get('/payroll', \App\Support\EcraReact::pagina('rh/folha', 'Folha de Pagamento'))
        ->name('payroll');
    Route::get('/payroll/payslip/{id}/pdf', [\App\Http\Controllers\HR\PayrollController::class, 'generatePayslipPDF'])
        ->middleware('permission:payroll.process')->name('payroll.payslip.pdf');
    Route::get('/payroll/{id}/payslips-pdf', [\App\Http\Controllers\HR\PayrollController::class, 'generateAllPayslipsPDF'])
        ->middleware('permission:payroll.process')->name('payroll.payslips-all.pdf');
    Route::get('/payroll/{id}/excel', [\App\Http\Controllers\HR\PayrollController::class, 'exportExcel'])
        ->middleware('permission:payroll.process')->name('payroll.excel');
    /*
     * OS CATÁLOGOS DO RH, em React e COM GUARDA.
     *
     * As moradas são as de sempre. O que muda é que passam pelo ecrã genérico
     * de catálogos (o mesmo dos fornecedores e dos bancos) e que exigem a
     * permissão que o módulo inteiro nunca teve — ver
     * `permissions:sync-rh-catalogos`.
     *
     * Os CARGOS ganham morada própria: em Livewire viviam dentro do ecrã dos
     * departamentos, com um segundo conjunto de métodos igual ao primeiro.
     */
    Route::middleware('permission:hr.departments.view')
        ->get('/departments', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Departamentos', ['tipo' => 'departamentos']))
        ->name('departments.index');
    Route::middleware('permission:hr.positions.view')
        ->get('/positions', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Cargos', ['tipo' => 'cargos']))
        ->name('positions.index');
    Route::middleware('permission:attendance.manage')
        ->get('/attendance', \App\Support\EcraReact::pagina('rh/presencas', 'Presenças'))
        ->name('attendance.index');
    /*
     * OS CINCO ECRÃS DE PEDIDOS, no ecrã genérico e COM GUARDA.
     *
     * Férias, licenças, horas extras, turno nocturno, adiantamentos e
     * descontos: as moradas são as de sempre, o `tipo` diz qual é, e cada uma
     * exige a sua permissão de VER. Aprovar é outra, exigida na API.
     *
     * Os PDF ficam onde estavam — reescrever o gerador para migrar uma lista
     * era trocar o risco de sítio sem ganhar nada.
     */
    Route::middleware('permission:hr.vacations.view')
        ->get('/vacations', \App\Support\EcraReact::pagina('rh/pedidos', 'Férias', ['tipo' => 'ferias']))
        ->name('vacations.index');
    Route::middleware('permission:hr.leaves.view')
        ->get('/leaves', \App\Support\EcraReact::pagina('rh/pedidos', 'Licenças e Faltas', ['tipo' => 'licencas']))
        ->name('leaves');
    Route::middleware('permission:hr.advances.view')
        ->get('/advances', \App\Support\EcraReact::pagina('rh/pedidos', 'Adiantamentos', ['tipo' => 'adiantamentos']))
        ->name('advances');
    Route::get('/advances/{id}/pdf', [\App\Http\Controllers\HR\SalaryAdvanceController::class, 'generatePDF'])
        ->middleware('permission:hr.advances.view')->name('advances.pdf');
    Route::middleware('permission:hr.overtime.view')
        ->get('/overtime', \App\Support\EcraReact::pagina('rh/pedidos', 'Horas Extras', ['tipo' => 'horas-extras']))
        ->name('overtime');
    Route::get('/overtime/{id}/pdf', [\App\Http\Controllers\HR\OvertimeController::class, 'generatePDF'])
        ->middleware('permission:hr.overtime.view')->name('overtime.pdf');
    Route::middleware('permission:hr.overtime.view')
        ->get('/overtime-night-shift', \App\Support\EcraReact::pagina('rh/pedidos', 'Turno Nocturno', ['tipo' => 'turno-nocturno']))
        ->name('overtime-night-shift');
    Route::middleware('permission:hr.discounts.view')
        ->get('/salary-discounts', \App\Support\EcraReact::pagina('rh/pedidos', 'Descontos Salariais', ['tipo' => 'descontos']))
        ->name('salary-discounts');
    Route::get('/salary-discounts/{id}/pdf', [\App\Http\Controllers\HR\SalaryDiscountController::class, 'generatePDF'])
        ->middleware('permission:hr.discounts.view')->name('salary-discounts.pdf');
    Route::middleware('permission:hr.shifts.view')
        ->get('/shifts', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Turnos', ['tipo' => 'turnos']))
        ->name('shifts.index');
    /*
     * OS CONTRATOS — o ecrã que nunca existiu, sobre uma tabela que já
     * decidia o salário pago. Ver `ContratosApiController`.
     */
    Route::middleware('permission:hr.contracts.view')
        ->get('/contracts', \App\Support\EcraReact::pagina('rh/contratos', 'Contratos'))
        ->name('contracts.index');

    /*
     * OS MAPAS. Um mapa de salários é o salário de toda a gente numa página
     * só — e não tinha guarda nenhuma.
     */
    Route::middleware('permission:hr.reports.view')
        ->get('/reports', \App\Support\EcraReact::pagina('rh/relatorios', 'Relatórios de RH'))
        ->name('reports');

    // Mapa de IRT: o imposto retido aos trabalhadores no mês, para declarar
    // e pagar à AGT. O ecrã confere; o papel e o CSV entregam.
    Route::middleware('permission:hr.irt.view')
        ->get('/irt-map', \App\Support\EcraReact::pagina('rh/mapa-de-irt', 'Mapa de IRT'))
        ->name('irt-map');
    Route::get('/irt-map/print', [\App\Http\Controllers\HR\MapaDeIRTController::class, 'imprimir'])
        ->middleware('permission:hr.irt.view')->name('irt-map.pdf');
    Route::get('/irt-map/csv', [\App\Http\Controllers\HR\MapaDeIRTController::class, 'csv'])
        ->middleware('permission:hr.irt.view')->name('irt-map.csv');

    /*
     * AS DEFINIÇÕES. O componente que aqui estava tinha uma nota a explicar
     * que não verificava permissão nenhuma porque nenhuma existia; existem
     * agora, e são duas: ver as regras da casa não é poder mudá-las.
     */
    Route::middleware('permission:hr.settings.view')
        ->get('/settings', \App\Support\EcraReact::pagina('rh/definicoes', 'Definições de RH'))
        ->name('settings');
});

/*
 * A CONTABILIDADE — trinta permissões declaradas e nenhuma rota a exigi-las.
 *
 * É o módulo onde estão os movimentos, o balancete e os períodos: quem abre a
 * lista de lançamentos vê tudo o que a empresa facturou, pagou e deve.
 */
Route::middleware(['auth', 'tenant.module:contabilidade'])->prefix('accounting')->name('accounting.')->group(function () {
    Route::middleware('permission:accounting.dashboard.view')
        ->get('/dashboard', \App\Support\EcraReact::pagina('contabilidade/painel', 'Painel da Contabilidade'))->name('dashboard');
    Route::middleware('permission:accounting.accounts.view')
        ->get('/accounts', \App\Support\EcraReact::pagina('contabilidade/contas', 'Plano de Contas'))->name('accounts');
    /*
     * OS TRÊS CATÁLOGOS. Têm a forma de sempre — lista, modal, gravar, apagar
     * — e por isso vivem no ecrã genérico dos catálogos, com o esquema em
     * `Services\Invoicing\Catalogos`, em vez de três componentes iguais.
     */
    Route::middleware('permission:accounting.journals.view')
        ->get('/journals', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Diários', ['tipo' => 'diarios']))->name('journals');
    Route::middleware('permission:accounting.document-types.view')
        ->get('/document-types', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Tipos de Documento', ['tipo' => 'tipos-de-documento']))->name('document-types');
    Route::middleware('permission:accounting.moves.view')
        ->get('/moves', \App\Support\EcraReact::pagina('contabilidade/lancamentos', 'Lançamentos'))->name('moves');
    Route::middleware('permission:accounting.periods.view')
        ->get('/periods', \App\Support\EcraReact::pagina('contabilidade/periodos', 'Períodos Contabilísticos'))->name('periods');
    Route::middleware('permission:accounting.reports.view')
        ->get('/reports', \App\Support\EcraReact::pagina('contabilidade/relatorios', 'Relatórios da Contabilidade'))->name('reports');
    /*
     * DESCARREGAR UM MAPA devolve um FICHEIRO, e por isso vive aqui e não no
     * prefixo da API: o ecrã abre-o numa janela nova e o browser guarda-o.
     */
    Route::middleware('permission:accounting.reports.view')
        ->get('/reports/descarregar', [\App\Http\Controllers\Api\Contabilidade\RelatoriosApiController::class, 'descarregar'])
        ->name('reports.descarregar');

    // R1 & R2 Routes
    Route::middleware('permission:accounting.reconciliation.view')
        ->get('/reconciliation', \App\Support\EcraReact::pagina('contabilidade/reconciliacao', 'Reconciliação Bancária'))->name('reconciliation');
    Route::middleware('permission:accounting.fixed-assets.view')
        ->get('/fixed-assets', \App\Support\EcraReact::pagina('contabilidade/imobilizado', 'Imobilizado'))->name('fixed-assets');
    // AS FAMÍLIAS existiam em tabela desde 2025 e não tinham ecrã: o campo
    // «Categoria» do imobilizado apontava para uma lista que ninguém preenchia.
    Route::middleware('permission:accounting.fixed-assets.view')
        ->get('/fixed-asset-categories', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Famílias do Imobilizado', ['tipo' => 'familias-do-imobilizado']))->name('fixed-asset-categories');
    Route::middleware('permission:accounting.currencies.view')
        ->get('/currencies', \App\Support\EcraReact::pagina('contabilidade/moedas', 'Moedas e Câmbios'))->name('currencies');
    Route::middleware('permission:accounting.cost-centers.view')
        ->get('/cost-centers', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Centros de Custo', ['tipo' => 'centros-de-custo']))->name('cost-centers');
    Route::middleware('permission:accounting.analytics.view')
        ->get('/analytics', \App\Support\EcraReact::pagina('contabilidade/analitica', 'Contabilidade Analítica'))->name('analytics');
    Route::middleware('permission:accounting.budgets.view')
        ->get('/budgets', \App\Support\EcraReact::pagina('contabilidade/orcamentos', 'Orçamentos'))->name('budgets');
    Route::middleware('permission:accounting.settings.view')
        ->get('/settings', \App\Support\EcraReact::pagina('contabilidade/definicoes', 'Definições da Contabilidade'))->name('settings');
});

/*
 * AS NOTIFICAÇÕES.
 *
 * As páginas abrem-se com `notifications.view`, como sempre. O que mudou é que
 * MEXER nelas passou a pedir `notifications.manage` — a permissão que se chama
 * «Gerir Configurações de Notificações», que está declarada desde o princípio e
 * que nenhuma linha de código pedia. Até aqui, quem podia ver mudava o servidor
 * de saída da empresa e apagava modelos.
 */
Route::middleware(['auth', 'tenant.module:notifications'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::middleware('permission:notifications.view')
        ->get('/settings', \App\Support\EcraReact::pagina('notificacoes/definicoes', 'Notificações'))->name('settings');
    Route::middleware('permission:notifications.view')
        ->get('/templates', \App\Support\EcraReact::pagina('notificacoes/modelos', 'Modelos de Notificação'))->name('templates');
});

/*
 * A OFICINA — e as dezanove permissões que existiam sem ninguém as aplicar.
 *
 * Como no RH: estavam declaradas, apareciam no ecrã de papéis, e nenhuma das
 * oito rotas as exigia. Quem tivesse o módulo activo abria a lista de ordens
 * de trabalho, os mecânicos e os relatórios.
 *
 * A segunda tranca é o escopo de empresa nos modelos (`BelongsToTenant`), que
 * fechou os `find()` que os componentes esqueciam.
 */
Route::middleware(['auth', 'tenant.module:oficina'])->prefix('workshop')->name('workshop.')->group(function () {
    Route::middleware('permission:workshop.dashboard.view')
        ->get('/dashboard', \App\Support\EcraReact::pagina('oficina/painel', 'Painel da Oficina'))->name('dashboard');
    /*
     * VIATURAS, MECÂNICOS E SERVIÇOS — o ecrã genérico dos catálogos.
     *
     * Eram três componentes Livewire com a forma de sempre (lista, modal,
     * gravar, apagar) e três cópias das mesmas quinhentas linhas. O que os
     * distingue vive agora no esquema (`App\Services\Invoicing\Catalogos`), e
     * o ecrã é o mesmo que serve os fornecedores e os turnos.
     */
    /*
     * OS CLIENTES, DENTRO DA OFICINA — o ecrã da facturação, não uma cópia.
     *
     * A viatura liga-se a um cliente da casa, e é esse cliente que a factura da
     * ordem de serviço leva. Uma segunda lista de clientes na oficina era a mesma
     * tabela com outra porta — aqui é a mesma porta, com o menu da oficina aberto.
     */
    Route::middleware('permission:invoicing.clients.view')
        ->get('/clients', \App\Support\EcraReact::pagina('facturacao/clientes', 'Clientes'))->name('clients');
    Route::middleware('permission:workshop.vehicles.view')
        ->get('/vehicles', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Viaturas', ['tipo' => 'viaturas']))->name('vehicles');
    Route::middleware('permission:workshop.mechanics.view')
        ->get('/mechanics', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Mecânicos', ['tipo' => 'mecanicos']))->name('mechanics');
    Route::middleware('permission:workshop.services.view')
        ->get('/services', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Serviços', ['tipo' => 'servicos']))->name('services');
    /*
     * AS PEÇAS SÃO OS ARTIGOS DA FACTURAÇÃO, filtrados ao que é físico.
     *
     * O Livewire da oficina já herdava do ecrã de artigos — o cabeçalho da
     * classe copiada dizia-o: «quando a oficina migrar, isto vai com ela». É o
     * que acontece aqui: a mesma tabela, as mesmas regras fiscais, o mesmo
     * ecrã, com o filtro do tipo já posto e o nome que a oficina lhe dá.
     *
     * A API dos artigos continua a pedir `invoicing.products.*`, que é a
     * autoridade verdadeira sobre o catálogo — quem gere peças gere artigos.
     * O `permissions:sync-oficina` reparte-a por quem tinha só a da oficina.
     */
    Route::middleware('permission:workshop.parts.view')
        ->get('/parts', \App\Support\EcraReact::pagina('facturacao/produtos', 'Peças', [
            'tipo' => 'produto',
            'titulo' => 'Peças',
            'subtitulo' => 'O catálogo de artigos da casa, aberto nas peças',
        ]))->name('parts');
    Route::middleware('permission:workshop.work-orders.view')
        ->get('/work-orders', \App\Support\EcraReact::pagina('oficina/ordens', 'Ordens de Serviço'))->name('work-orders');
    // A ordem em papel leva a viatura, o dono e o preço: a mesma permissão do
    // ecrã de onde se abre.
    Route::get('/work-orders/{id}/print', [\App\Http\Controllers\Workshop\WorkOrderController::class, 'printPreview'])
        ->middleware('permission:workshop.work-orders.view')->name('work-orders.print');
    Route::middleware('permission:workshop.reports.view')
        ->get('/reports', \App\Support\EcraReact::pagina('oficina/relatorios', 'Relatórios da Oficina'))->name('reports');
    /*
     * O PAPEL E O EXCEL DOS MAPAS.
     *
     * Os dois botões existiam e respondiam «Funcionalidade de exportação em
     * desenvolvimento». Levam os mesmos filtros do ecrã no URL.
     */
    Route::middleware('permission:workshop.reports.view')->group(function () {
        $c = \App\Http\Controllers\Workshop\MapaExportController::class;

        Route::get('/reports/imprimir', [$c, 'imprimir'])->name('reports.imprimir');
        Route::get('/reports/excel', [$c, 'excel'])->name('reports.excel');
    });
});

// CRM — leads, oportunidades e o funil. Deixou de ser placeholder: as quatro
// rotas prometidas desde o início passam a entregar o que o nome diz.
Route::middleware(['auth', 'tenant.module:crm'])->prefix('crm')->name('crm.')->group(function () {
    Route::middleware('permission:crm.view')
        ->get('/dashboard', \App\Support\EcraReact::pagina('crm/painel', 'Painel do CRM'))->name('dashboard');
    Route::middleware('permission:crm.leads.view')
        ->get('/leads', \App\Support\EcraReact::pagina('crm/leads', 'Leads'))->name('leads');

    /*
     * A LISTA E O FUNIL SÃO O MESMO ECRÃ, em separadores.
     *
     * Eram duas moradas para o mesmo assunto visto de duas maneiras — e quem
     * movia um cartão no funil tinha de ir à lista para lhe mexer no valor. As
     * duas moradas ficam, cada uma a abrir no seu separador.
     */
    Route::middleware('permission:crm.opportunities.view')
        ->get('/oportunidades', \App\Support\EcraReact::pagina('crm/oportunidades', 'Oportunidades'))->name('oportunidades');
    Route::middleware('permission:crm.opportunities.view')
        ->get('/funil-vendas', \App\Support\EcraReact::pagina('crm/oportunidades', 'Funil de Vendas', ['separador' => 'funil']))->name('funil-vendas');

    Route::middleware('permission:crm.integrations.manage')
        ->get('/integracoes', \App\Support\EcraReact::pagina('crm/meta', 'Integração Meta'))->name('integracoes');
});

// Inventário — deixou de ser placeholder. O painel e os movimentos lêem o
// stock que já existe; os armazéns são o ecrã de sempre da Facturação (um
// ecrã só, dois sítios no menu); a contagem física é a peça nova.
Route::middleware(['auth', 'tenant.module:inventario'])->prefix('inventario')->name('inventario.')->group(function () {
    Route::middleware('permission:inventario.view')
        ->get('/dashboard', \App\Support\EcraReact::pagina('inventario/painel', 'Painel do Inventário'))->name('dashboard');
    Route::middleware('permission:inventario.view')
        ->get('/armazens', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Armazéns', ['tipo' => 'armazens',]))->name('armazens');
    Route::middleware('permission:inventario.view')
        ->get('/movimentos', \App\Support\EcraReact::pagina('inventario/movimentos', 'Movimentos de Stock'))->name('movimentos');
    Route::middleware('permission:inventario.contagem.manage')
        ->get('/contagem', \App\Support\EcraReact::pagina('inventario/contagem', 'Contagem Física'))->name('contagem');
});

// Compras — deixou de ser placeholder. O circuito que faltava antes da
// factura de compra: requisição interna → encomenda ao fornecedor → recepção
// (é aqui que o stock entra) → factura. Os fornecedores são o ecrã de sempre
// da Facturação, um ecrã só em dois sítios do menu — como os armazéns.
Route::middleware(['auth', 'tenant.module:compras'])->prefix('compras')->name('compras.')->group(function () {
    Route::middleware('permission:compras.view')
        ->get('/dashboard', \App\Support\EcraReact::pagina('compras/painel', 'Painel das Compras'))->name('dashboard');
    Route::middleware('permission:compras.view')
        ->get('/fornecedores', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Fornecedores', ['tipo' => 'fornecedores',]))->name('fornecedores');
    Route::middleware('permission:compras.requisicoes.view')
        ->get('/requisicoes', \App\Support\EcraReact::pagina('compras/requisicoes', 'Requisições de Compra'))->name('requisicoes');
    Route::middleware('permission:compras.encomendas.view')
        ->get('/encomendas', \App\Support\EcraReact::pagina('compras/encomendas', 'Encomendas de Compra'))->name('encomendas');
});

// Projetos — deixou de ser placeholder. O projeto tem orçamento, as tarefas
// organizam o trabalho, e a folha de horas consome o orçamento e vira factura
// pelo ModuleInvoiceService — o mesmo do hotel, da oficina e do salão.
//
// A folha de horas é gateada por `horas.registar` e não por `view`: quem lança
// horas é toda a gente que trabalha, mas o ecrã só mostra as SUAS.
Route::middleware(['auth', 'tenant.module:projetos'])->prefix('projetos')->name('projetos.')->group(function () {
    Route::middleware('permission:projetos.view')
        ->get('/dashboard', \App\Support\EcraReact::pagina('projetos/painel', 'Painel dos Projetos'))->name('dashboard');
    Route::middleware('permission:projetos.view')
        ->get('/lista', \App\Support\EcraReact::pagina('projetos/lista', 'Projetos'))->name('lista');
    Route::middleware('permission:projetos.tarefas.view')
        ->get('/tarefas', \App\Support\EcraReact::pagina('projetos/tarefas', 'Tarefas de Projeto'))->name('tarefas');
    Route::middleware('permission:projetos.horas.registar')
        ->get('/timesheet', \App\Support\EcraReact::pagina('projetos/horas', 'Folha de Horas'))->name('timesheet');
});

/*
 * O HOTEL — trinta e cinco permissões declaradas e nenhuma rota a exigi-las.
 *
 * Era o maior dos cinco módulos sem guarda: qualquer utilizador com o módulo
 * activo abria a lista de hóspedes (nome, documento, morada), as reservas, o
 * folio com os consumos e o mapa do SEF.
 *
 * OS DOCUMENTOS DA RESERVA — voucher, folio, SEF, QR — pedem a permissão de
 * VER RESERVAS, que é o ecrã de onde se abrem. E o KiandaStay pede a de
 * definições: é lá que se liga a casa a um site de reservas.
 */
Route::middleware(['auth', 'tenant.module:hotel'])->prefix('hotel')->name('hotel.')->group(function () {
    Route::middleware('permission:hotel.dashboard.view')
        ->get('/dashboard', \App\Support\EcraReact::pagina('hotel/painel', 'Painel do Hotel'))->name('dashboard');
    Route::middleware('permission:hotel.room-types.view')
        ->get('/room-types', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Tipos de Quarto', ['tipo' => 'tipos-de-quarto']))->name('room-types');
    Route::middleware('permission:hotel.rooms.view')
        ->get('/rooms', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Quartos', ['tipo' => 'quartos']))->name('rooms');
    Route::middleware('permission:hotel.guests.view')
        ->get('/guests', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Hóspedes', ['tipo' => 'hospedes']))->name('guests');
    Route::middleware('permission:hotel.reservations.view')
        ->get('/reservations', \App\Support\EcraReact::pagina('hotel/reservas', 'Reservas'))->name('reservations');
    Route::middleware('permission:hotel.walk-in.create')
        ->get('/walk-in', \App\Support\EcraReact::pagina('hotel/balcao', 'Balcão'))->name('walk-in');
    // Parâmetro opcional: permite abrir o check-out já numa reserva concreta
    // (é o que o botão da lista de reservas faz). Sem parâmetro continua a
    // abrir o ecrã de pesquisa, como o menu lateral espera.
    Route::middleware('permission:hotel.checkout.manage')
        ->get('/checkout/{id?}', \App\Support\EcraReact::pagina('hotel/check-out', 'Check-out'))->name('checkout');
    Route::middleware('permission:hotel.reservations.view')
        ->get('/calendar', \App\Support\EcraReact::pagina('hotel/calendario', 'Calendário'))->name('calendar');
    Route::middleware('permission:hotel.housekeeping.view')
        ->get('/housekeeping', \App\Support\EcraReact::pagina('hotel/limpeza', 'Housekeeping'))->name('housekeeping');
    Route::middleware('permission:hotel.maintenance.view')
        ->get('/maintenance', \App\Support\EcraReact::pagina('hotel/manutencao', 'Manutenção'))->name('maintenance');
    Route::middleware('permission:hotel.staff.view')
        ->get('/staff', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Pessoal do Hotel', ['tipo' => 'pessoal-do-hotel']))->name('staff');
    Route::middleware('permission:hotel.reports.view')
        ->get('/reports', \App\Support\EcraReact::pagina('hotel/relatorios', 'Relatórios do Hotel'))->name('reports');
    /*
     * O PAPEL E O EXCEL DOS MAPAS. Os dois botões existiam e respondiam
     * «Exportação em desenvolvimento».
     */
    Route::middleware('permission:hotel.reports.view')->group(function () {
        $c = \App\Http\Controllers\Hotel\MapaExportController::class;

        Route::get('/reports/imprimir', [$c, 'imprimir'])->name('reports.imprimir');
        Route::get('/reports/excel', [$c, 'excel'])->name('reports.excel');
    });
    Route::middleware('permission:hotel.rates.view')
        ->get('/rates', \App\Support\EcraReact::pagina('hotel/tarifas', 'Tarifas'))->name('rates');
    /*
     * AS ÉPOCAS são uma lista com a forma de sempre, e vivem no ecrã
     * genérico dos catálogos. O ecrã das tarifas liga para aqui: são a
     * primeira das três camadas do preço.
     */
    Route::middleware('permission:hotel.rates.view')
        ->get('/seasons', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Épocas', ['tipo' => 'epocas-do-hotel']))->name('seasons');
    /*
     * OS PACOTES E OS CÓDIGOS PROMOCIONAIS — duas listas na mesma morada.
     *
     * Eram duas abas dentro do mesmo componente Livewire, e são duas listas
     * com a forma de sempre: o ecrã genérico desenha-as com os separadores por
     * cima.
     */
    Route::middleware('permission:hotel.packages.view')
        ->get('/packages', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Pacotes', [
            'tipos' => [
                ['tipo' => 'pacotes', 'rotulo' => 'Pacotes', 'icone' => 'fa-gift'],
                ['tipo' => 'codigos-promocionais', 'rotulo' => 'Códigos Promocionais', 'icone' => 'fa-ticket'],
            ],
        ]))->name('packages');
    Route::middleware('permission:hotel.settings.view')
        ->get('/settings', \App\Support\EcraReact::pagina('hotel/definicoes', 'Definições do Hotel'))->name('settings');
    // A ligacao ao KiandaStay: as reservas do site entram sozinhas na recepcao.
    Route::middleware('permission:hotel.settings.view')
        ->get('/kiandastay', \App\Support\EcraReact::pagina('hotel/kiandastay', 'KiandaStay'))->name('kiandastay');
    // A volta do «Entrar com o KiandaStay»: troca o bilhete pelo token.
    Route::get('/kiandastay/retorno', [\App\Http\Controllers\Hotel\LigacaoKiandaStayController::class, 'retorno'])
        ->middleware('permission:hotel.settings.edit')->name('kiandastay.retorno');

    // Folio (consumos por reserva)
    Route::middleware('permission:hotel.reservations.view')
        ->get('/reservations/{id}/folio', \App\Support\EcraReact::pagina('hotel/folio', 'Folio'))->name('reservations.folio');

    // Documents (PDF/HTML print-friendly)
    Route::middleware('permission:hotel.reservations.view')->group(function () {
        Route::get('/reservations/{id}/voucher', [\App\Http\Controllers\Hotel\ReservationController::class, 'voucher'])->name('reservations.voucher');
        Route::get('/reservations/{id}/folio-pdf', [\App\Http\Controllers\Hotel\ReservationController::class, 'folio'])->name('reservations.folio.pdf');
        Route::get('/reservations/{id}/sef', [\App\Http\Controllers\Hotel\ReservationController::class, 'sef'])->name('reservations.sef');
        Route::get('/reservations/{id}/qr', [\App\Http\Controllers\Hotel\ReservationController::class, 'qr'])->name('reservations.qr');
    });
});

// Hotel Express Check-in (Public via QR Code - no auth required)
Route::get('/hotel/reservations/{id}/checkin/{code}', [\App\Http\Controllers\Hotel\ReservationController::class, 'expressCheckIn'])->name('hotel.express-checkin');
Route::post('/hotel/reservations/{id}/checkin/{code}/confirm', [\App\Http\Controllers\Hotel\ReservationController::class, 'confirmExpressCheckIn'])->name('hotel.express-checkin.confirm');

// Hotel Booking Online (Public)
//
// Endereço ANTIGO, mantido só para não partir links já divulgados. Encaminha
// para a página a sério.
//
// Servia um segundo componente (Hotel\BookingOnline) que partilhava esta vista
// mas não lhe fornecia metade das variáveis ($settings, $viewingRoom) — a
// página rebentava — e, pior, não respeitava o interruptor
// `online_booking_enabled`: dava para reservar num hotel que tinha desligado as
// reservas online. Sem slug, ainda por cima, mostrava o hotel de outra empresa.
Route::get('/booking/{tenant?}', function ($tenant = null) {
    $empresa = $tenant ? \App\Models\Tenant::where('slug', $tenant)->first() : null;

    $definicoes = $empresa
        ? \App\Models\Hotel\HotelSettings::where('tenant_id', $empresa->id)
            ->whereNotNull('booking_slug')
            ->first()
        : null;

    abort_unless($definicoes && \App\Support\CasaPublica::aberta((int) $empresa->id, 'hotel'), 404, 'Hotel não encontrado.');

    return redirect()->route('hotel.booking.online', ['slug' => $definicoes->booking_slug]);
})->name('booking.online');

/*
 * A PÁGINA PÚBLICA DE RESERVAS.
 *
 * Sem sessão e sem empresa activa: quem manda é o SLUG. O cabeçalho (título,
 * descrição, imagem) é desenhado pelo servidor de propósito — o que o WhatsApp
 * e o Facebook mostram tem de estar no HTML antes de o JavaScript correr.
 */
Route::get('/hotel/booking/{slug}', function (string $slug) {
    $d = \App\Models\Hotel\HotelSettings::findBySlug($slug);

    // Uma empresa desactivada, ou sem o módulo, não tem página de reservas.
    abort_unless($d && \App\Support\CasaPublica::aberta((int) $d->tenant_id, 'hotel'), 404, __('Hotel não encontrado.'));
    abort_unless($d->online_booking_enabled, 403, __('Esta casa não aceita reservas por aqui.'));

    return view('react.publico', [
        'ecra' => 'hotel/reservar',
        'props' => ['slug' => $slug],
        'titulo' => $d->meta_title ?: ($d->hotel_name ? $d->hotel_name . ' — ' . __('Reservas online') : __('Reservas')),
        'descricao' => $d->meta_description ?: ($d->hotel_description ?: ''),
        'imagem' => $d->cover_url ?: $d->logo_url,
        'canonico' => \App\Support\DadosEstruturados::raiz() . '/hotel/booking/' . $slug,
        'robots' => \App\Support\CasaPublica::temConteudo($d->meta_description, $d->hotel_description) ? null : 'noindex, follow',
        'dadosEstruturados' => \App\Support\CasaPublica::dadosEstruturados('Hotel', \App\Support\DadosEstruturados::raiz() . '/hotel/booking/' . $slug, (int) $d->tenant_id, [
            'nome' => (string) ($d->hotel_name ?: \App\Models\Tenant::find($d->tenant_id)?->name),
            'descricao' => $d->hotel_description,
            'imagem' => $d->cover_url ?: $d->logo_url,
            'telefone' => $d->hotel_phone,
            'email' => $d->hotel_email,
            'morada' => $d->hotel_address,
            'cidade' => $d->hotel_city,
            'pais' => $d->hotel_country,
            'redes' => [$d->hotel_website],
        ]),
    ]);
})->name('hotel.booking.online');

/*
 * A PORTA DA PÁGINA PÚBLICA.
 *
 * Fora de qualquer autenticação — é um estranho a falar com a casa — e por
 * isso com travão de tráfego: reservar é escrever na base, e uma página aberta
 * ao mundo sem limite é um convite.
 */
Route::prefix('api/publico/hotel/{slug}')->name('hotel.publico.')
    ->middleware('throttle:60,1')
    ->group(function () {
        $c = \App\Http\Controllers\Api\Hotel\ReservaOnlineApiController::class;

        Route::get('/', [$c, 'casa'])->name('casa');
        Route::get('/disponibilidade', [$c, 'disponibilidade'])->name('disponibilidade');
        Route::post('/entrar', [$c, 'entrar'])->name('entrar');
        Route::post('/registar', [$c, 'registar'])->name('registar');
        Route::post('/reservar', [$c, 'reservar'])->name('reservar');
    });

/*
 * O SALÃO — vinte e nove permissões declaradas e nenhuma rota a exigi-las.
 *
 * As DEFINIÇÕES são o caso mais caro: é lá que se muda a morada pública do
 * salão, as horas de funcionamento e a política de cancelamento. Ver e alterar
 * são duas permissões diferentes, e a rota pede a de ver — alterar é a guarda
 * do próprio ecrã.
 */
Route::middleware(['auth', 'tenant.module:salon'])->prefix('salon')->name('salon.')->group(function () {
    Route::middleware('permission:salon.dashboard.view')
        ->get('/dashboard', \App\Support\EcraReact::pagina('salao/painel', 'Painel do Salão'))->name('dashboard');
    Route::middleware('permission:salon.appointments.view')
        ->get('/appointments', \App\Support\EcraReact::pagina('salao/marcacoes', 'Marcações'))->name('appointments');
    Route::middleware('permission:salon.services.view')
        ->get('/services', \App\Support\EcraReact::pagina('salao/servicos', 'Serviços'))->name('services');
    Route::middleware('permission:salon.categories.view')
        ->get('/services/categories', \App\Support\EcraReact::pagina('salao/servicos', 'Categorias de Serviços', ['separador' => 'categorias']))->name('services.categories');
    Route::middleware('permission:salon.professionals.view')
        ->get('/professionals', \App\Support\EcraReact::pagina('salao/profissionais', 'Profissionais'))->name('professionals');
    Route::middleware('permission:salon.clients.view')
        ->get('/clients', \App\Support\EcraReact::pagina('salao/clientes', 'Clientes do Salão'))->name('clients');
    Route::middleware('permission:salon.products.view')
        ->get('/products', \App\Support\EcraReact::pagina('facturacao/produtos', 'Produtos do Salão'))->name('products');
    Route::middleware('permission:salon.pos.access')
        ->get('/pos', \App\Support\EcraReact::pagina('facturacao/pos', 'POS - Salão de Beleza', ['modulo' => 'salon']))->name('pos');
    Route::middleware('permission:salon.reports.view')
        ->get('/reports/time', \App\Support\EcraReact::pagina('salao/tempos', 'Relatório de Tempos'))->name('reports.time');
    Route::middleware('permission:salon.settings.view')
        ->get('/settings', \App\Support\EcraReact::pagina('salao/definicoes', 'Definições do Salão'))->name('settings');
});

/*
 * A PÁGINA PÚBLICA DO SALÃO — a montra e a marcação online.
 *
 * Sem sessão de empresa: quem manda é o SLUG. As acções escrevem na base a
 * partir da rua, e por isso vão com travão de tráfego.
 */
Route::get('/agendar/{slug}', [\App\Http\Controllers\Salon\AgendamentoOnlineController::class, 'pagina'])->name('salon.booking.online');
Route::prefix('api/publico/salao/{slug}')->name('salon.publico.')
    ->middleware('throttle:60,1')
    ->controller(\App\Http\Controllers\Salon\AgendamentoOnlineController::class)
    ->group(function () {
        Route::get('/horarios', 'horarios')->name('horarios');
        Route::post('/entrar', 'entrar')->name('entrar');
        Route::post('/registar', 'registar')->name('registar');
        Route::post('/sair', 'sair')->name('sair');
        Route::post('/marcar', 'marcar')->name('marcar');
    });

// Menu online do restaurante (público) — a carta que o cliente abre no
// telemóvel. A segunda forma leva o código da mesa, e é a que vai no QR
// colado à mesa: o pedido chega já a dizer de onde vem.
//
// Fora de qualquer `auth`: quem abre isto é um cliente sentado à mesa. O
// controlador resolve a empresa pelo slug e recusa-se a servir uma carta
// desligada — ver RestaurantSettings::porSlugPublico.
Route::get('/menu/{slug}', [\App\Http\Controllers\Restaurant\CartaPublicaController::class, 'pagina'])->name('restaurant.menu.online');
Route::get('/menu/{slug}/{mesa}', [\App\Http\Controllers\Restaurant\CartaPublicaController::class, 'pagina'])->name('restaurant.menu.mesa');
// O pedido feito na própria carta: escrever na base a partir da rua, com travão.
Route::post('/api/publico/restaurante/{slug}/pedido', [\App\Http\Controllers\Restaurant\CartaPublicaController::class, 'pedido'])
    ->middleware('throttle:30,1')->name('restaurant.menu.pedido');

// Restaurante - operação de sala e comandas; faturação permanece no módulo Invoicing.
Route::middleware(['auth', 'tenant.module:restaurant'])->prefix('restaurant')->name('restaurant.')->group(function () {
    Route::middleware('permission:restaurant.dashboard.view')
        ->get('/dashboard', \App\Support\EcraReact::pagina('restaurant/painel', 'Painel do Restaurante'))->name('dashboard');
    Route::middleware('permission:restaurant.floor.view')
        ->get('/floor', \App\Support\EcraReact::pagina('restaurant/sala', 'Sala e Mesas'))->name('floor');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/orders', \App\Support\EcraReact::pagina('restaurant/comandas', 'Comandas'))->name('orders');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/pos', \App\Support\EcraReact::pagina('restaurant/balcao', 'Balcão do Restaurante'))->name('pos');
    Route::middleware('permission:restaurant.menu.view')
        ->get('/products', \App\Support\EcraReact::pagina('facturacao/produtos', 'Produtos'))->name('products');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/contacts', \App\Support\EcraReact::pagina('restaurant/contactos', 'Clientes e Fornecedores'))->name('contacts');
    Route::middleware('permission:restaurant.menu.view')
        ->get('/categories', \App\Support\EcraReact::pagina('restaurant/carta', 'Categorias', ['separador' => 'categorias']))->name('categories');
    // A carta inteira num ecrã: categorias e pratos em linha, sem os dois
    // formulários genéricos que obrigavam a saltar de ecrã para criar um prato.
    Route::middleware('permission:restaurant.menu.view')
        ->get('/carta', \App\Support\EcraReact::pagina('restaurant/carta', 'A Carta'))->name('carta');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/shifts', \App\Support\EcraReact::pagina('facturacao/turnos', 'POS - Ponto de Venda'))->name('shifts');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/shift-history', \App\Support\EcraReact::pagina('facturacao/historico-de-turnos', 'Histórico de Turnos'))->name('shift-history');
    Route::middleware('permission:restaurant.reports.view')
        ->get('/sales-report', \App\Support\EcraReact::pagina('facturacao/pos-relatorio', 'Relatório de Vendas', ['sourceModule' => 'restaurant']))->name('sales-report');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/orders/{id}/consultation-receipt', [\App\Http\Controllers\Restaurant\RestaurantDocumentController::class, 'consultationReceipt'])->name('orders.consultation-receipt');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/documents/{id}/print', [\App\Http\Controllers\Restaurant\RestaurantDocumentController::class, 'fiscalDocument'])->name('documents.print');
    Route::middleware('permission:restaurant.kitchen.view')
        ->get('/kitchen', \App\Support\EcraReact::pagina('restaurant/cozinha', 'Cozinha'))->name('kitchen');
    Route::middleware('permission:restaurant.kitchen.view')->get('/kitchen/tickets/{ticket}/print', [\App\Http\Controllers\Restaurant\KitchenTicketController::class,'print'])->name('kitchen.print');
    // O pulso da cozinha: uma agregação, sem relações. O ecrã pergunta de 3 em
    // 3 segundos e só refaz a página quando a resposta muda — em vez de a
    // refazer de 15 em 15 esteja ou não a acontecer alguma coisa.
    Route::middleware('permission:restaurant.kitchen.view')->get('/kitchen/pulso', \App\Http\Controllers\Restaurant\PulsoDaCozinhaController::class)->name('kitchen.pulso');
    Route::middleware('permission:restaurant.reservations.view')
        ->get('/reservations', \App\Support\EcraReact::pagina('restaurant/reservas', 'Reservas'))->name('reservations');
    Route::middleware('permission:restaurant.recipes.view')->get('/recipes', \App\Support\EcraReact::pagina('restaurant/fichas', 'Fichas Técnicas'))->name('recipes');
    Route::middleware('permission:restaurant.stock.view')->get('/stock', \App\Support\EcraReact::pagina('restaurant/stock', 'Stock e Desperdícios'))->name('stock');
    Route::middleware('permission:restaurant.reports.view')->get('/reports', \App\Support\EcraReact::pagina('restaurant/relatorios', 'Relatórios do Restaurante'))->name('reports');
    Route::middleware('permission:restaurant.settings.view')->get('/settings', \App\Support\EcraReact::pagina('restaurant/definicoes', 'Definições do Restaurante'))->name('settings');

    // A aparência da carta pública: capa, cores, tema e pratos em destaque.
    // Ecrã próprio e não mais um separador das definições — escolher a
    // fotografia da capa não é a mesma decisão que escolher um armazém.
    Route::middleware('permission:restaurant.settings.view')
        ->get('/carta/aparencia', \App\Support\EcraReact::pagina('restaurant/aparencia-da-carta', 'Aparência da Carta'))->name('carta.aparencia');

    // Os QR da carta, prontos a imprimir e a colar nas mesas. Vive sob a
    // permissão das definições: quem publica a carta é quem imprime os códigos.
    Route::middleware('permission:restaurant.settings.view')->group(function () {
        Route::get('/menu/qr', [\App\Http\Controllers\Restaurant\QrDoMenuController::class, 'folha'])->name('menu.qr');
        Route::get('/menu/qr/imagem/{mesa?}', [\App\Http\Controllers\Restaurant\QrDoMenuController::class, 'imagem'])->name('menu.qr.imagem');
    });
});

/*
 * O SUPORTE.
 *
 * Sem permissão: pedir ajuda é auto-serviço, e o quadro de melhorias é de toda
 * a gente da empresa — é esse o sentido de haver votos.
 */
Route::middleware(['auth'])->prefix('support')->name('support.')->group(function () {
    Route::get('/tickets', \App\Support\EcraReact::pagina('suporte/pedidos', 'Pedidos de Suporte'))->name('tickets');
    Route::get('/features', \App\Support\EcraReact::pagina('suporte/melhorias', 'Quadro de Melhorias'))->name('features');
});
