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
    Route::get('/setup', \App\Livewire\Setup\SetupWizard::class)->name('setup');
}

// Analytics tracking (sem auth, sem CSRF — público)
Route::post('/api/analytics/track', [\App\Http\Controllers\AnalyticsController::class, 'track'])->name('analytics.track');

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

Route::get('/register', \App\Livewire\Auth\RegisterWizard::class)->name('register');

// User Invitation Routes
Route::get('/invitation/{token}', [App\Http\Controllers\InvitationController::class, 'show'])->name('invitation.accept');
Route::post('/invitation/{token}', [App\Http\Controllers\InvitationController::class, 'accept'])->name('invitation.accept.post');

// Auth routes (sem register padrão)
Auth::routes(['register' => false]);

// Tenant Deactivated Page
Route::get('/tenant-deactivated', function () {
    return view('auth.tenant-deactivated');
})->name('tenant.deactivated');

// Subscription Expired Page
Route::get('/subscription-expired', function () {
    return view('subscription-expired');
})->middleware('auth')->name('subscription.expired');

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
    Route::get('/my-account', \App\Livewire\MyAccount::class)->name('my-account');

    /*
     * A CASCA EM REACT, em ensaio. Não é uma página: é um interruptor de
     * sessão que troca a barra lateral de sempre pela nova, em todos os
     * ecrãs, só para quem o ligou. O menu é o mesmo (MenuDaCasca).
     */
    Route::get('/casca/novo-ecra', function () {
        session(['casca_react' => true]);

        return redirect()->back(fallback: route('home'));
    })->name('casca.react');

    Route::get('/casca/ecra-de-sempre', function () {
        session()->forget('casca_react');

        return redirect()->back(fallback: route('home'));
    })->name('casca.livewire');

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
        ->get('/empresa', \App\Livewire\Company\CompanyProfile::class)->name('company.profile');
});

// Keep-alive: ping leve para manter a sessão viva enquanto o utilizador
// tem a página aberta. Devolve 204 No Content; o simples facto da request
// passar pelo middleware web renova o cookie de sessão.
Route::middleware(['web', 'auth'])
    ->get('/keep-alive', fn() => response()->noContent())
    ->name('keep-alive');

// ⚠️ ENDPOINT ONE-SHOT: provisionamento da empresa
// FARMACIA MEDICAL CONNECT SERVICE, LDA. Remover após primeiro uso.
Route::get(
    '/setup/seed/medical-connect/{token}',
    \App\Http\Controllers\Setup\SeedMedicalConnectController::class
)->name('setup.seed.medical-connect');

// User Management Routes (Admin do tenant ou Super Admin)
Route::middleware(['auth', 'permission:users.manage'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', \App\Livewire\Users\UserManagement::class)->name('index');
    Route::get('/roles-permissions', \App\Livewire\Users\RolesAndPermissions::class)->name('roles-permissions');
    Route::get('/invitations', \App\Livewire\Users\InviteUser::class)->name('invitations');
});

// Super Admin Routes
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', \App\Livewire\SuperAdmin\Dashboard::class)->name('dashboard');
    Route::get('/analytics', \App\Livewire\SuperAdmin\Analytics::class)->name('analytics');
    Route::get('/tenants', \App\Livewire\SuperAdmin\Tenants::class)->name('tenants');
    Route::get('/restaurant-venue-requests', \App\Livewire\SuperAdmin\RestaurantVenueRequests::class)->name('restaurant-venue-requests');
    Route::get('/modules', \App\Livewire\SuperAdmin\Modules::class)->name('modules');
    Route::get('/plans', \App\Livewire\SuperAdmin\Plans::class)->name('plans');
    Route::get('/billing', \App\Livewire\SuperAdmin\Billing::class)->name('billing');
    Route::get('/licenciamento', \App\Livewire\SuperAdmin\Licenciamento::class)->name('licenciamento');

    // Que empresas usam o PWA, em que aparelhos e em que VERSÃO. Existe porque
    // um deploy do motor podia não chegar aos aparelhos e não havia como saber.
    Route::get('/aparelhos-pwa', \App\Livewire\SuperAdmin\AparelhosPwa::class)->name('aparelhos-pwa');
    Route::get('/system-updates', \App\Livewire\SuperAdmin\SystemUpdates::class)->name('system-updates');
    Route::get('/system-commands', \App\Livewire\SuperAdmin\SystemCommands::class)->name('system-commands');
    Route::get('/script-runner', \App\Livewire\SuperAdmin\ScriptRunner::class)->name('script-runner');
    Route::get('/system-settings', \App\Livewire\SuperAdmin\SystemSettings::class)->name('system-settings');
    Route::get('/software-settings', \App\Livewire\SuperAdmin\SoftwareSettings::class)->name('software-settings');
    Route::get('/system-optimization', \App\Livewire\SuperAdmin\SystemOptimization::class)->name('system-optimization');
    Route::get('/email-templates', \App\Livewire\SuperAdmin\EmailTemplates::class)->name('email-templates');
    Route::get('/smtp-settings', \App\Livewire\SuperAdmin\SmtpSettings::class)->name('smtp-settings');
    Route::get('/email-logs', \App\Livewire\SuperAdmin\EmailLogs::class)->name('email-logs');
    // Avisos e mensagens do dono da plataforma para as empresas.
    Route::get('/mensagens', \App\Livewire\SuperAdmin\MensagensPlataforma::class)->name('mensagens');
    Route::get('/sms-settings', \App\Livewire\SuperAdmin\SmsSettings::class)->name('sms-settings');
    // Enviar um SMS às empresas — a todas, ou só às escolhidas.
    Route::get('/sms-empresas', \App\Livewire\SuperAdmin\SmsParaEmpresas::class)->name('sms-empresas');
    Route::get('/whatsapp-notifications', \App\Livewire\SuperAdmin\WhatsAppNotifications::class)->name('whatsapp-notifications');
    Route::get('/saft-configuration', \App\Livewire\SuperAdmin\SaftConfiguration::class)->name('saft');
    Route::get('/contact-messages', \App\Livewire\SuperAdmin\ContactMessages::class)->name('contact-messages');
});

// PWA Offline — Faturação (rotas standalone com auth por sessão)
// Entrada do PWA — FORA do `auth`, senão a página de entrada mandava o
// utilizador para o login para poder mostrar o login. Precisa de sessão para
// o token CSRF do formulário, por isso fica no grupo `web`.
Route::get('/invoicing/offline/login', fn () => view('invoicing.offline.login'))
    ->name('invoicing.offline.login');

// Esqueci o PIN — sem rede. Também FORA do `auth`: quem cá chega não tem
// sessão nem rede, e tudo o que a página faz é local (o gestor autoriza com o
// seu PIN, o aparelho calcula o verificador novo e põe-no na fila). O servidor
// só decide quando a fila subir, com sessão.
Route::get('/invoicing/offline/pin-esquecido', fn () => view('invoicing.offline.pin-esquecido'))
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
    Route::get('/', fn() => view('invoicing.offline.index'))->name('index');

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
    Route::middleware('pwa:catalogo')->get('/catalog', fn() => view('invoicing.offline.catalog'))->name('catalog');
    Route::middleware('pwa:clientes')->group(function () {
        Route::get('/clients', fn() => view('invoicing.offline.clients'))->name('clients');
        Route::get('/clients/new', fn() => view('invoicing.offline.client-form'))->name('client-new');
    });
    Route::middleware('pwa:documentos')->group(function () {
        Route::get('/drafts', fn() => view('invoicing.offline.drafts'))->name('drafts');
        Route::get('/drafts/new', fn() => view('invoicing.offline.draft-form'))->name('draft-new');
    });
    Route::middleware('pwa:pos')->get('/pos', fn() => view('invoicing.offline.pos'))->middleware('permission:invoicing.pos.access')->name('pos');
    Route::middleware('pwa:restaurante')->get('/restaurant', fn() => view('invoicing.offline.restaurant'))->name('restaurant');
    // Saída do PWA → redireciona para a 1ª área a que o utilizador tem permissão
    Route::get('/exit', \App\Http\Controllers\Invoicing\PwaExitController::class)->name('exit');
});

// API Auth (token Bearer) — app móvel
Route::prefix('api/v1/auth')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login'])->name('api.auth.login');
    Route::middleware('api.token')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\AuthController::class, 'me'])->name('api.auth.me');
        Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout'])->name('api.auth.logout');
    });
});

// API REST — Faturação (sessão web OU token Bearer; api.token autentica).
// 'subscription' DEPOIS de 'api.token': sem isto, a app móvel com token Bearer
// contornava o CheckSubscription por completo e um tenant com subscrição
// expirada continuava a faturar pelo telemóvel.
Route::middleware(['api.token', 'subscription'])->prefix('api/v1/restaurant')->name('api.restaurant.')->group(function () {
    Route::get('/snapshot', [\App\Http\Controllers\Api\RestaurantController::class, 'snapshot']);
    Route::post('/orders', [\App\Http\Controllers\Api\RestaurantController::class, 'open']);
    Route::post('/orders/{order}/items', [\App\Http\Controllers\Api\RestaurantController::class, 'add']);
    Route::post('/orders/{order}/confirm', [\App\Http\Controllers\Api\RestaurantController::class, 'confirm']);
    Route::post('/orders/{order}/transfer', [\App\Http\Controllers\Api\RestaurantController::class, 'transfer']);
    Route::post('/orders/{order}/merge', [\App\Http\Controllers\Api\RestaurantController::class, 'merge']);
    Route::post('/orders/{order}/items/{item}/void', [\App\Http\Controllers\Api\RestaurantController::class, 'voidItem']);
    Route::post('/orders/{order}/checkout', [\App\Http\Controllers\Api\RestaurantController::class, 'checkout']);

    // A comanda feita sem rede, reposta de uma vez: mesa, artigos, cozinha e
    // recebimento. As rotas acima encadeiam-se por id do servidor e por isso
    // não servem offline — ver ComandaOfflineController.
    Route::post('/offline/comanda', [\App\Http\Controllers\Api\Restaurant\ComandaOfflineController::class, 'store'])
        ->name('offline.comanda');
});

Route::middleware(['api.token', 'subscription'])->prefix('api/v1/invoicing')->name('api.invoicing.')->group(function () {
    Route::get('/ping', [\App\Http\Controllers\Api\Invoicing\SyncController::class, 'ping'])->name('ping');
    Route::get('/sync', [\App\Http\Controllers\Api\Invoicing\SyncController::class, 'index'])->name('sync');
    Route::get('/diagnose', [\App\Http\Controllers\Api\Invoicing\SyncController::class, 'diagnose'])->name('diagnose');
    Route::post('/clients', [\App\Http\Controllers\Api\Invoicing\ClientController::class, 'store'])->name('clients.store');
    Route::post('/drafts', [\App\Http\Controllers\Api\Invoicing\DraftController::class, 'store'])->name('drafts.store');
    Route::post('/pos/sale', [\App\Http\Controllers\Api\Invoicing\PosSaleController::class, 'store'])->name('pos.sale.store');
    // Um PIN de turno reposto no aparelho sem rede, autorizado por um gestor
    // ao balcão. Chega pela fila; o servidor confirma quem pode e regista.
    // Com `auth` explícito: o grupo não o tem, e aqui mexe-se em credenciais.
    Route::post('/pin/repor', \App\Http\Controllers\Api\Invoicing\ReporPinController::class)
        ->middleware('auth')->name('pin.repor');
    // Turno POS (abertura/fecho offline → sincronizado quando online)
    Route::get('/pos/shift', [\App\Http\Controllers\Api\Invoicing\PosShiftController::class, 'status'])->name('pos.shift.status');
    Route::post('/pos/shift/open', [\App\Http\Controllers\Api\Invoicing\PosShiftController::class, 'open'])->name('pos.shift.open');
    Route::post('/pos/shift/close', [\App\Http\Controllers\Api\Invoicing\PosShiftController::class, 'close'])->name('pos.shift.close');
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
    Route::prefix('react')->name('react.')->group(function () {
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

        Route::prefix('oficina/ordens')->name('oficina.ordens.')->group(function () {
            $c = \App\Http\Controllers\Api\Workshop\OrdensApiController::class;

            Route::get('/opcoes', [$c, 'opcoes'])->name('opcoes');
            Route::get('/artigos', [$c, 'artigos'])->name('artigos');
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
        Route::post('/agt/ligacao', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'testarLigacao'])->name('agt.ligacao');
        Route::post('/agt/series/sincronizar', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'sincronizarSeries'])->name('agt.series.sincronizar');
        Route::post('/agt/submissoes/actualizar', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'actualizarEstados'])->name('agt.submissoes.actualizar');
        Route::post('/agt/submissoes/{id}/reenviar', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'reenviar'])->whereNumber('id')->name('agt.submissoes.reenviar');
        Route::post('/agt/consulta', [\App\Http\Controllers\Api\Invoicing\AgtApiController::class, 'consultar'])->name('agt.consulta');
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
        Route::get('/invoices/{id}/edit', \App\Support\EcraReact::pagina('facturacao/emitir-factura', 'Fatura de Venda'))->middleware('permission:invoicing.sales.invoices.edit')->name('invoices.edit');
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
        Route::get('/invoices/{id}/edit', \App\Support\EcraReact::pagina('facturacao/emitir-factura-de-compra', 'Fatura de Compra'))->middleware('permission:invoicing.purchases.invoices.edit')->name('invoices.edit');
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
        Route::middleware('permission:invoicing.receipts.edit')->get('/{id}/edit', \App\Support\EcraReact::pagina('facturacao/registar-recibo', 'Recibo'))->name('edit');
        Route::middleware('permission:invoicing.receipts.view')->get('/{id}/pdf', [\App\Http\Controllers\Invoicing\ReceiptController::class, 'generatePdf'])->name('pdf');
        Route::middleware('permission:invoicing.receipts.view')->get('/{id}/preview', [\App\Http\Controllers\Invoicing\ReceiptController::class, 'previewHtml'])->name('preview');
    });
    
    // Notas de Crédito
    Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
        Route::middleware('permission:invoicing.credit-notes.view')->get('/', \App\Support\EcraReact::pagina('facturacao/documentos', 'Notas de Crédito', ['tipo' => 'notas-credito',]))->name('index');
        Route::middleware('permission:invoicing.credit-notes.create')->get('/create', \App\Support\EcraReact::pagina('facturacao/emitir-nota', 'Nota de Crédito', ['tipo' => 'credito',], fn () => \App\Support\FacturaNaMorada::props()))->name('create');
        Route::middleware('permission:invoicing.credit-notes.edit')->get('/{id}/edit', \App\Support\EcraReact::pagina('facturacao/emitir-nota', 'Nota de Crédito', ['tipo' => 'credito',]))->name('edit');
        Route::middleware('permission:invoicing.credit-notes.view')->get('/{id}/pdf', [\App\Http\Controllers\Invoicing\CreditNoteController::class, 'generatePdf'])->name('pdf');
        Route::middleware('permission:invoicing.credit-notes.view')->get('/{id}/preview', [\App\Http\Controllers\Invoicing\CreditNoteController::class, 'previewHtml'])->name('preview');
    });
    
    // Notas de Débito
    Route::prefix('debit-notes')->name('debit-notes.')->group(function () {
        Route::middleware('permission:invoicing.debit-notes.view')->get('/', \App\Support\EcraReact::pagina('facturacao/documentos', 'Notas de Débito', ['tipo' => 'notas-debito',]))->name('index');
        Route::middleware('permission:invoicing.debit-notes.create')->get('/create', \App\Support\EcraReact::pagina('facturacao/emitir-nota', 'Nota de Débito', ['tipo' => 'debito',], fn () => \App\Support\FacturaNaMorada::props()))->name('create');
        Route::middleware('permission:invoicing.debit-notes.edit')->get('/{id}/edit', \App\Support\EcraReact::pagina('facturacao/emitir-nota', 'Nota de Débito', ['tipo' => 'debito',]))->name('edit');
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
    Route::get('/stock/movimentacao/{reference}/pdf', [\App\Http\Controllers\Invoicing\StockMovementController::class, 'batchPdf'])
        ->where('reference', '[A-Za-z0-9/_-]+')
        ->middleware('permission:invoicing.stock.view')->name('stock.batch-pdf');
    Route::get('/stock/movimentacao/{reference}/preview', [\App\Http\Controllers\Invoicing\StockMovementController::class, 'batchPreview'])
        ->where('reference', '[A-Za-z0-9/_-]+')
        ->middleware('permission:invoicing.stock.view')->name('stock.batch-preview');
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
Route::get('/client/login', \App\Livewire\Client\ClientLogin::class)->name('client.login');
Route::get('/client/forgot-password', function () {
    return view('client.forgot-password');
})->name('client.forgot-password');

// Rotas protegidas do cliente
Route::middleware(['auth:client'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', \App\Livewire\Client\ClientDashboard::class)->name('dashboard');
    Route::get('/statement', \App\Livewire\Client\ClientStatement::class)->name('statement');
    Route::get('/events', \App\Livewire\Client\ClientEvents::class)->name('events');
    Route::get('/invoices', \App\Livewire\Client\ClientInvoices::class)->name('invoices');
    Route::get('/proformas', \App\Livewire\Client\ClientProformas::class)->name('proformas');
    Route::get('/profile', \App\Livewire\Client\ClientProfile::class)->name('profile');

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
        ->get('/dashboard', \App\Livewire\Accounting\Dashboard::class)->name('dashboard');
    Route::middleware('permission:accounting.accounts.view')
        ->get('/accounts', \App\Livewire\Accounting\AccountManagement::class)->name('accounts');
    Route::middleware('permission:accounting.journals.view')
        ->get('/journals', \App\Livewire\Accounting\JournalManagement::class)->name('journals');
    Route::middleware('permission:accounting.document-types.view')
        ->get('/document-types', \App\Livewire\Accounting\DocumentTypeManagement::class)->name('document-types');
    Route::middleware('permission:accounting.moves.view')
        ->get('/moves', \App\Livewire\Accounting\MoveManagement::class)->name('moves');
    Route::middleware('permission:accounting.periods.view')
        ->get('/periods', \App\Livewire\Accounting\PeriodManagement::class)->name('periods');
    Route::middleware('permission:accounting.reports.view')
        ->get('/reports', \App\Livewire\Accounting\ReportsManagement::class)->name('reports');

    // R1 & R2 Routes
    Route::middleware('permission:accounting.reconciliation.view')
        ->get('/reconciliation', \App\Livewire\Accounting\BankReconciliationManagement::class)->name('reconciliation');
    Route::middleware('permission:accounting.fixed-assets.view')
        ->get('/fixed-assets', \App\Livewire\Accounting\FixedAssetManagement::class)->name('fixed-assets');
    Route::middleware('permission:accounting.currencies.view')
        ->get('/currencies', \App\Livewire\Accounting\CurrencyManagement::class)->name('currencies');
    Route::middleware('permission:accounting.cost-centers.view')
        ->get('/cost-centers', \App\Livewire\Accounting\CostCenterManagement::class)->name('cost-centers');
    Route::middleware('permission:accounting.analytics.view')
        ->get('/analytics', \App\Livewire\Accounting\AnalyticManagement::class)->name('analytics');
    Route::middleware('permission:accounting.budgets.view')
        ->get('/budgets', \App\Livewire\Accounting\BudgetManagement::class)->name('budgets');
    Route::middleware('permission:accounting.settings.view')
        ->get('/settings', \App\Livewire\Accounting\SettingsManagement::class)->name('settings');
});

// Notifications Module Routes
Route::middleware(['auth', 'tenant.module:notifications'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/settings', \App\Livewire\Settings\NotificationSettings::class)->middleware('permission:notifications.view')->name('settings');
    Route::get('/templates', \App\Livewire\Settings\ManageNotificationTemplates::class)->middleware('permission:notifications.view')->name('templates');
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
        ->get('/dashboard', \App\Livewire\Inventario\Dashboard::class)->name('dashboard');
    Route::middleware('permission:inventario.view')
        ->get('/armazens', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Armazéns', ['tipo' => 'armazens',]))->name('armazens');
    Route::middleware('permission:inventario.view')
        ->get('/movimentos', \App\Livewire\Inventario\Movimentos::class)->name('movimentos');
    Route::middleware('permission:inventario.contagem.manage')
        ->get('/contagem', \App\Livewire\Inventario\Contagem::class)->name('contagem');
});

// Compras — deixou de ser placeholder. O circuito que faltava antes da
// factura de compra: requisição interna → encomenda ao fornecedor → recepção
// (é aqui que o stock entra) → factura. Os fornecedores são o ecrã de sempre
// da Facturação, um ecrã só em dois sítios do menu — como os armazéns.
Route::middleware(['auth', 'tenant.module:compras'])->prefix('compras')->name('compras.')->group(function () {
    Route::middleware('permission:compras.view')
        ->get('/dashboard', \App\Livewire\Compras\Dashboard::class)->name('dashboard');
    Route::middleware('permission:compras.view')
        ->get('/fornecedores', \App\Support\EcraReact::pagina('facturacao/catalogo', 'Fornecedores', ['tipo' => 'fornecedores',]))->name('fornecedores');
    Route::middleware('permission:compras.requisicoes.view')
        ->get('/requisicoes', \App\Livewire\Compras\Requisicoes::class)->name('requisicoes');
    Route::middleware('permission:compras.encomendas.view')
        ->get('/encomendas', \App\Livewire\Compras\Encomendas::class)->name('encomendas');
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

    abort_unless($definicoes, 404, 'Hotel não encontrado.');

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

    abort_unless($d, 404, __('Hotel não encontrado.'));
    abort_unless($d->online_booking_enabled, 403, __('Esta casa não aceita reservas por aqui.'));

    return view('react.publico', [
        'ecra' => 'hotel/reservar',
        'props' => ['slug' => $slug],
        'titulo' => $d->meta_title ?: ($d->hotel_name ?: __('Reservas')),
        'descricao' => $d->meta_description ?: ($d->hotel_description ?: ''),
        'imagem' => $d->cover_url ?: $d->logo_url,
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
        ->get('/pos', \App\Support\EcraReact::pagina('facturacao/pos', 'POS - Salão de Beleza'))->name('pos');
    Route::middleware('permission:salon.reports.view')
        ->get('/reports/time', \App\Support\EcraReact::pagina('salao/tempos', 'Relatório de Tempos'))->name('reports.time');
    Route::middleware('permission:salon.settings.view')
        ->get('/settings', \App\Support\EcraReact::pagina('salao/definicoes', 'Definições do Salão'))->name('settings');
});

// Salon Booking Online (Public) - Landing Page Customizada
Route::get('/agendar/{slug}', \App\Livewire\Salon\SalonBookingOnline::class)->name('salon.booking.online');

// Menu online do restaurante (público) — a carta que o cliente abre no
// telemóvel. A segunda forma leva o código da mesa, e é a que vai no QR
// colado à mesa: o pedido chega já a dizer de onde vem.
//
// Fora de qualquer `auth`: quem abre isto é um cliente sentado à mesa. O
// componente resolve a empresa pelo slug e recusa-se a servir uma carta
// desligada — ver RestaurantSettings::porSlugPublico.
Route::get('/menu/{slug}', \App\Livewire\Restaurant\MenuOnline::class)->name('restaurant.menu.online');
Route::get('/menu/{slug}/{mesa}', \App\Livewire\Restaurant\MenuOnline::class)->name('restaurant.menu.mesa');

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

// Support/Help Center Routes
Route::middleware(['auth'])->prefix('support')->name('support.')->group(function () {
    Route::get('/tickets', \App\Livewire\Support\TicketsManagement::class)->name('tickets');
    Route::get('/features', \App\Livewire\Support\FeatureRequestsBoard::class)->name('features');
});
