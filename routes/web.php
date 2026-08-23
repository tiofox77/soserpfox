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
Route::get('/licenca', [\App\Http\Controllers\LicencaController::class, 'index'])->name('licenca.index');
Route::post('/licenca', [\App\Http\Controllers\LicencaController::class, 'guardar'])->name('licenca.guardar');

// Analytics tracking (sem auth, sem CSRF — público)
Route::post('/api/analytics/track', [\App\Http\Controllers\AnalyticsController::class, 'track'])->name('analytics.track');

// PWA: manifest dinâmico, ícones a partir do logo do sistema, service worker com versão automática
Route::get('/manifest.webmanifest', [\App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/manifest.json', [\App\Http\Controllers\PwaController::class, 'manifest']);
Route::get('/pwa/icon-{size}.png', [\App\Http\Controllers\PwaController::class, 'icon'])->where('size', '[0-9]+')->name('pwa.icon');
Route::get('/pwa/icon-{size}x{size2}.png', [\App\Http\Controllers\PwaController::class, 'icon'])->where(['size' => '[0-9]+', 'size2' => '[0-9]+']);
Route::get('/sw.js', [\App\Http\Controllers\PwaController::class, 'serviceWorker'])->name('pwa.sw');

// Página de Changelog / Atualizações do sistema (autenticada para usar layout app)
Route::middleware(['auth'])->get('/changelog', [\App\Http\Controllers\ChangelogController::class, 'index'])->name('changelog');

// Landing Page
Route::get('/', [App\Http\Controllers\LandingController::class, 'home'])->name('landing.home');

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

    // Dados da Empresa — identificação, contactos, endereço, logótipo e regime
    // fiscal AGT (a alteração de regime propaga-se via TaxRegimeSyncer).
    Route::get('/empresa', \App\Livewire\Company\CompanyProfile::class)->name('company.profile');
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
    Route::get('pin', \App\Livewire\Invoicing\Offline\DefinirPin::class)->name('pin');
    Route::get('/', fn() => view('invoicing.offline.index'))->name('index');
    Route::get('/catalog', fn() => view('invoicing.offline.catalog'))->name('catalog');
    Route::get('/clients', fn() => view('invoicing.offline.clients'))->name('clients');
    Route::get('/clients/new', fn() => view('invoicing.offline.client-form'))->name('client-new');
    Route::get('/drafts', fn() => view('invoicing.offline.drafts'))->name('drafts');
    Route::get('/drafts/new', fn() => view('invoicing.offline.draft-form'))->name('draft-new');
    Route::get('/pos', fn() => view('invoicing.offline.pos'))->name('pos');
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
});

Route::middleware(['api.token', 'subscription'])->prefix('api/v1/invoicing')->name('api.invoicing.')->group(function () {
    Route::get('/ping', [\App\Http\Controllers\Api\Invoicing\SyncController::class, 'ping'])->name('ping');
    Route::get('/sync', [\App\Http\Controllers\Api\Invoicing\SyncController::class, 'index'])->name('sync');
    Route::get('/diagnose', [\App\Http\Controllers\Api\Invoicing\SyncController::class, 'diagnose'])->name('diagnose');
    Route::post('/clients', [\App\Http\Controllers\Api\Invoicing\ClientController::class, 'store'])->name('clients.store');
    Route::post('/drafts', [\App\Http\Controllers\Api\Invoicing\DraftController::class, 'store'])->name('drafts.store');
    Route::post('/pos/sale', [\App\Http\Controllers\Api\Invoicing\PosSaleController::class, 'store'])->name('pos.sale.store');
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
});

// Invoicing Module Routes
Route::middleware(['auth', 'tenant.module:invoicing'])->prefix('invoicing')->name('invoicing.')->group(function () {
    // Dashboard
    Route::middleware('permission:invoicing.dashboard.view')->get('/dashboard', \App\Livewire\Invoicing\InvoicingDashboard::class)->name('dashboard');
    
    Route::middleware('permission:invoicing.clients.view')->get('/clients', \App\Livewire\Invoicing\Clients::class)->name('clients');
    Route::middleware('permission:invoicing.suppliers.view')->get('/suppliers', \App\Livewire\Invoicing\Suppliers::class)->name('suppliers');
    Route::middleware('permission:invoicing.products.view')->get('/products', \App\Livewire\Invoicing\Products::class)->name('products');
    Route::middleware('permission:invoicing.categories.view')->get('/categories', \App\Livewire\Invoicing\Categories::class)->name('categories');
    Route::middleware('permission:invoicing.brands.view')->get('/brands', \App\Livewire\Invoicing\Brands::class)->name('brands');
    
    // Proformas e Faturas de Venda
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::middleware('permission:invoicing.sales.proformas.view')->get('/proformas', \App\Livewire\Invoicing\Sales\Proformas::class)->name('proformas');
        Route::get('/proformas/create', \App\Livewire\Invoicing\Sales\ProformaCreate::class)->name('proformas.create');
        Route::get('/proformas/{id}/edit', \App\Livewire\Invoicing\Sales\ProformaCreate::class)->name('proformas.edit');
        Route::get('/proformas/{id}/pdf', [\App\Http\Controllers\Invoicing\ProformaController::class, 'generatePdf'])->name('proformas.pdf');
        Route::get('/proformas/{id}/preview', [\App\Http\Controllers\Invoicing\ProformaController::class, 'previewHtml'])->name('proformas.preview');

        // Orçamentos (documento comercial, não fiscal)
        Route::middleware('permission:invoicing.sales.quotes.view')->get('/quotes', \App\Livewire\Invoicing\Sales\Quotes::class)->name('quotes');
        Route::get('/quotes/create', \App\Livewire\Invoicing\Sales\QuoteCreate::class)->name('quotes.create');
        Route::get('/quotes/{id}/edit', \App\Livewire\Invoicing\Sales\QuoteCreate::class)->name('quotes.edit');
        Route::get('/quotes/{id}/pdf', [\App\Http\Controllers\Invoicing\QuoteController::class, 'generatePdf'])->name('quotes.pdf');
        Route::get('/quotes/{id}/preview', [\App\Http\Controllers\Invoicing\QuoteController::class, 'previewHtml'])->name('quotes.preview');
        
        // Faturas de Venda
        Route::middleware('permission:invoicing.sales.invoices.view')->get('/invoices', \App\Livewire\Invoicing\Sales\Invoices::class)->name('invoices');
        Route::get('/invoices/create', \App\Livewire\Invoicing\Sales\InvoiceCreate::class)->name('invoices.create');
        Route::get('/invoices/{id}/edit', \App\Livewire\Invoicing\Sales\InvoiceCreate::class)->name('invoices.edit');
        Route::get('/invoices/{id}/pdf', [\App\Http\Controllers\Invoicing\SalesInvoiceController::class, 'generatePdf'])->name('invoices.pdf');
        Route::get('/invoices/{id}/preview', [\App\Http\Controllers\Invoicing\SalesInvoiceController::class, 'previewHtml'])->name('invoices.preview');
        Route::get('/invoices/{id}/download', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'downloadPdf'])->name('invoices.download');
        
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
        })->name('proformas.pdf-test');
    });
    
    // Proformas e Faturas de Compra
    Route::prefix('purchases')->name('purchases.')->group(function () {
        Route::middleware('permission:invoicing.purchases.proformas.view')->get('/proformas', \App\Livewire\Invoicing\Purchases\Proformas::class)->name('proformas');
        Route::get('/proformas/create', \App\Livewire\Invoicing\Purchases\ProformaCreate::class)->name('proformas.create');
        Route::get('/proformas/{id}/edit', \App\Livewire\Invoicing\Purchases\ProformaCreate::class)->name('proformas.edit');
        Route::get('/proformas/{id}/pdf', [\App\Http\Controllers\Invoicing\PurchaseProformaController::class, 'generatePdf'])->name('proformas.pdf');
        Route::get('/proformas/{id}/preview', [\App\Http\Controllers\Invoicing\PurchaseProformaController::class, 'previewHtml'])->name('proformas.preview');
        
        // Faturas de Compra
        Route::middleware('permission:invoicing.purchases.invoices.view')->get('/invoices', \App\Livewire\Invoicing\Purchases\Invoices::class)->name('invoices');
        Route::get('/invoices/create', \App\Livewire\Invoicing\Purchases\InvoiceCreate::class)->name('invoices.create');
        Route::get('/invoices/{id}/edit', \App\Livewire\Invoicing\Purchases\InvoiceCreate::class)->name('invoices.edit');
        Route::get('/invoices/{id}/pdf', [\App\Http\Controllers\Invoicing\PurchaseInvoiceController::class, 'generatePdf'])->name('invoices.pdf');
        Route::get('/invoices/{id}/preview', [\App\Http\Controllers\Invoicing\PurchaseInvoiceController::class, 'previewHtml'])->name('invoices.preview');
    });
    
    // Recibos
    Route::prefix('receipts')->name('receipts.')->group(function () {
        Route::middleware('permission:invoicing.receipts.view')->get('/', \App\Livewire\Invoicing\Receipts\Receipts::class)->name('index');
        Route::get('/create', \App\Livewire\Invoicing\Receipts\ReceiptCreate::class)->name('create');
        Route::get('/{id}/edit', \App\Livewire\Invoicing\Receipts\ReceiptCreate::class)->name('edit');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Invoicing\ReceiptController::class, 'generatePdf'])->name('pdf');
        Route::get('/{id}/preview', [\App\Http\Controllers\Invoicing\ReceiptController::class, 'previewHtml'])->name('preview');
    });
    
    // Notas de Crédito
    Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
        Route::middleware('permission:invoicing.credit-notes.view')->get('/', \App\Livewire\Invoicing\CreditNotes\CreditNotes::class)->name('index');
        Route::get('/create', \App\Livewire\Invoicing\CreditNotes\CreditNoteCreate::class)->name('create');
        Route::get('/{id}/edit', \App\Livewire\Invoicing\CreditNotes\CreditNoteCreate::class)->name('edit');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Invoicing\CreditNoteController::class, 'generatePdf'])->name('pdf');
        Route::get('/{id}/preview', [\App\Http\Controllers\Invoicing\CreditNoteController::class, 'previewHtml'])->name('preview');
    });
    
    // Notas de Débito
    Route::prefix('debit-notes')->name('debit-notes.')->group(function () {
        Route::middleware('permission:invoicing.debit-notes.view')->get('/', \App\Livewire\Invoicing\DebitNotes\DebitNotes::class)->name('index');
        Route::get('/create', \App\Livewire\Invoicing\DebitNotes\DebitNoteCreate::class)->name('create');
        Route::get('/{id}/edit', \App\Livewire\Invoicing\DebitNotes\DebitNoteCreate::class)->name('edit');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Invoicing\DebitNoteController::class, 'generatePdf'])->name('pdf');
        Route::get('/{id}/preview', [\App\Http\Controllers\Invoicing\DebitNoteController::class, 'previewHtml'])->name('preview');
    });
    
    // Importações
    Route::prefix('imports')->name('imports.')->group(function () {
        Route::middleware('permission:invoicing.imports.view')->get('/', \App\Livewire\Invoicing\Imports\Imports::class)->name('index');
    });

    // Recuperar uma cópia de segurança do PWA (aparelho que não sincronizou).
    // A permissão é a de criar vendas no POS: quem pode emitir é quem pode
    // recuperar o que já foi emitido offline.
    Route::middleware('permission:invoicing.pos.create')
        ->get('/importar-copia-offline', \App\Livewire\Invoicing\ImportarCopiaOffline::class)
        ->name('importar-copia-offline');
    
    // Adiantamentos
    Route::prefix('advances')->name('advances.')->group(function () {
        Route::middleware('permission:invoicing.advances.view')->get('/', \App\Livewire\Invoicing\Advances\Advances::class)->name('index');
        Route::get('/create', \App\Livewire\Invoicing\Advances\AdvanceCreate::class)->name('create');
        Route::get('/{id}/edit', \App\Livewire\Invoicing\Advances\AdvanceCreate::class)->name('edit');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Invoicing\AdvanceController::class, 'generatePdf'])->name('pdf');
        Route::get('/{id}/preview', [\App\Http\Controllers\Invoicing\AdvanceController::class, 'previewHtml'])->name('preview');
    });
    
    // Configurações
    Route::middleware('permission:invoicing.settings.view')->get('/settings', \App\Livewire\Invoicing\Settings::class)->name('settings');
    Route::middleware('permission:invoicing.settings.view')
        ->get('/settings/notification-gateways', \App\Livewire\Settings\NotificationSettings::class)
        ->defaults('tab', 'sms')->name('notification-gateways');

    // Trilha de auditoria. Protegida pela mesma permissão das definições: quem
    // pode ver a configuração fiscal da empresa pode ver quem lhe mexeu.
    Route::middleware('permission:invoicing.settings.view')
        ->get('/auditoria', \App\Livewire\Invoicing\AuditTrailViewer::class)->name('audit');
    Route::middleware('permission:invoicing.series.view')->get('/series', \App\Livewire\Invoicing\SeriesManagement::class)->name('series');
    Route::middleware('permission:invoicing.taxes.view')->get('/taxes', \App\Livewire\Invoicing\TaxManagement::class)->name('taxes');
    Route::middleware('permission:invoicing.settings.view')->get('/payment-terms', \App\Livewire\Invoicing\PaymentTerms::class)->name('payment-terms');
    Route::middleware('permission:invoicing.agt.view')->get('/agt-settings', \App\Livewire\Invoicing\AGTSettings::class)->name('agt-settings');
    Route::middleware('permission:invoicing.agt.view')->get('/agt-credentials', \App\Livewire\Invoicing\AGTCredentials::class)->name('agt-credentials');
    
    // Armazéns e Stock
    Route::get('/warehouses', \App\Livewire\Invoicing\Warehouses::class)->name('warehouses');
    // Com permissão, como todas as irmãs do módulo. Sem ela, qualquer papel com
    // acesso à faturação via o inventário e a valorização inteiros — as acções
    // já estavam travadas dentro do componente, mas a leitura não. Os papéis que
    // o seeder deliberadamente não contempla (restaurante e contabilidade)
    // deixam de entrar; quem precisar, o administrador da empresa concede.
    Route::middleware('permission:invoicing.stock.view')
        ->get('/stock', \App\Livewire\Invoicing\StockManagement::class)->name('stock');

    // Documento do lote de movimentação (MOV/AAAA/NNNNNN).
    // A referência leva barras, daí o `where` — sem ele o Laravel parte o
    // parâmetro no primeiro '/' e a rota nunca corresponde.
    Route::get('/stock/movimentacao/{reference}/pdf', [\App\Http\Controllers\Invoicing\StockMovementController::class, 'batchPdf'])
        ->where('reference', '[A-Za-z0-9/_-]+')
        ->name('stock.batch-pdf');
    Route::get('/stock/movimentacao/{reference}/preview', [\App\Http\Controllers\Invoicing\StockMovementController::class, 'batchPreview'])
        ->where('reference', '[A-Za-z0-9/_-]+')
        ->name('stock.batch-preview');
    Route::get('/product-batches', \App\Livewire\Invoicing\ProductBatches\ProductBatches::class)->name('product-batches');
    Route::get('/warehouse-transfer', \App\Livewire\Invoicing\WarehouseTransfer::class)->name('warehouse-transfer');
    Route::get('/inter-company-transfer', \App\Livewire\Invoicing\InterCompanyTransfer::class)->name('inter-company-transfer');
    
    // Relatórios
    Route::get('/expiry-report', \App\Livewire\Invoicing\Reports\ExpiryReport::class)->name('expiry-report');
    
    Route::prefix('reports')->name('reports.')->middleware('permission:invoicing.reports.view')->group(function () {
        Route::get('/', \App\Livewire\Invoicing\Reports\ReportsHub::class)->name('hub');
        Route::get('/sales', \App\Livewire\Invoicing\Reports\SalesReport::class)->name('sales');
        Route::get('/purchases', \App\Livewire\Invoicing\Reports\PurchasesReport::class)->name('purchases');
        Route::get('/top-clients', \App\Livewire\Invoicing\Reports\TopClientsReport::class)->name('top-clients');
        Route::get('/top-products', \App\Livewire\Invoicing\Reports\TopProductsReport::class)->name('top-products');
        Route::get('/top-suppliers', \App\Livewire\Invoicing\Reports\TopSuppliersReport::class)->name('top-suppliers');
        Route::get('/accounts-receivable', \App\Livewire\Invoicing\Reports\AccountsReceivableReport::class)->name('accounts-receivable');
        Route::get('/accounts-payable', \App\Livewire\Invoicing\Reports\AccountsPayableReport::class)->name('accounts-payable');
        Route::get('/aging-clients', \App\Livewire\Invoicing\Reports\AgingClientsReport::class)->name('aging-clients');
        Route::get('/vat', \App\Livewire\Invoicing\Reports\VatReport::class)->name('vat');
        Route::get('/documents', \App\Livewire\Invoicing\Reports\DocumentsReport::class)->name('documents');
        Route::get('/profit-loss', \App\Livewire\Invoicing\Reports\ProfitLossReport::class)->name('profit-loss');
        Route::get('/margin', \App\Livewire\Invoicing\Reports\MarginReport::class)->name('margin');
        Route::get('/best-supplier', \App\Livewire\Invoicing\Reports\BestSupplierReport::class)->name('best-supplier');
        Route::get('/comparative', \App\Livewire\Invoicing\Reports\ComparativeReport::class)->name('comparative');
        Route::get('/product-performance', \App\Livewire\Invoicing\Reports\ProductPerformanceReport::class)->name('product-performance');
        Route::get('/services', \App\Livewire\Invoicing\Reports\ServicesReport::class)->name('services');
        Route::get('/price-list', \App\Livewire\Invoicing\Reports\PriceListReport::class)->name('price-list');
        Route::get('/payment-methods', \App\Livewire\Invoicing\Reports\PaymentMethodsReport::class)->name('payment-methods');
        Route::get('/sales-by-user', \App\Livewire\Invoicing\Reports\SalesByUserReport::class)->name('sales-by-user');
        Route::get('/stock-adjustments', \App\Livewire\Invoicing\Reports\StockAdjustmentsReport::class)->name('stock-adjustments');

        // Extracto de conta corrente — serve cliente e fornecedor.
        Route::get('/account-statement', \App\Livewire\Invoicing\Reports\AccountStatementReport::class)->name('account-statement');
        Route::get('/account-statement/pdf', [\App\Http\Controllers\Invoicing\AccountStatementController::class, 'pdf'])
            ->name('account-statement.pdf');
    });
    
    // Guias de Transporte / Remessa (GT / GR)
    Route::get('/transport-guides', \App\Livewire\Invoicing\TransportGuides\TransportGuides::class)->name('transport-guides');
    Route::get('/transport-guides/{id}/pdf', [\App\Http\Controllers\Invoicing\TransportGuideController::class, 'pdf'])->name('transport-guides.pdf');

    // SAFT
    Route::get('/saft-generator', \App\Livewire\Invoicing\SAFTGenerator::class)->name('saft-generator');

    // Adquirente AGT (DS.120 §§4.3, 4.4, 4.7)
    Route::middleware('permission:invoicing.agt.view')
        ->get('/agt-adquirente', \App\Livewire\Agt\AdquirenteIndex::class)
        ->name('agt-adquirente');
    
    // POS
    Route::get('/pos', \App\Livewire\POS\POSSystem::class)->name('pos');
    Route::get('/pos/shifts', \App\Livewire\Invoicing\Pos\PosShiftManager::class)->name('pos.shifts');
    Route::get('/pos/shift-history', \App\Livewire\Invoicing\Pos\ShiftHistory::class)->name('pos.shift-history');
    Route::get('/pos/reports', \App\Livewire\POS\SalesReport::class)->name('pos.reports');

    // POS Exports (PDF / Excel)
    Route::get('/pos/export/shift/{shift}/pdf', [\App\Http\Controllers\Pos\PosExportController::class, 'shiftPdf'])->name('pos.export.shift-pdf');
    Route::get('/pos/export/shift/{shift}/ticket', [\App\Http\Controllers\Pos\PosExportController::class, 'shiftTicket'])->name('pos.export.shift-ticket');
    Route::get('/pos/export/sales-report/pdf', [\App\Http\Controllers\Pos\PosExportController::class, 'salesReportPdf'])->name('pos.export.sales-pdf');
    Route::get('/pos/export/sales-report/excel', [\App\Http\Controllers\Pos\PosExportController::class, 'salesReportExcel'])->name('pos.export.sales-excel');
});

// Treasury Module Routes
Route::middleware(['auth', 'tenant.module:treasury'])->prefix('treasury')->name('treasury.')->group(function () {
    Route::get('/dashboard', \App\Livewire\Treasury\Dashboard::class)->name('dashboard');
    Route::get('/reports', \App\Livewire\Treasury\Reports::class)->name('reports');
    // Descarga dos relatórios financeiros. Só se via no ecrã, e um relatório
    // que não se pode levar ao banco nem ao contabilista serve para pouco.
    Route::get('/reports/pdf', [\App\Http\Controllers\Treasury\ReportExportController::class, 'pdf'])
        ->name('reports.pdf');
    Route::get('/reports/excel', [\App\Http\Controllers\Treasury\ReportExportController::class, 'excel'])
        ->name('reports.excel');
    Route::get('/payment-methods', \App\Livewire\Treasury\PaymentMethods::class)->name('payment-methods');
    Route::get('/banks', \App\Livewire\Treasury\Banks::class)->name('banks');
    Route::get('/accounts', \App\Livewire\Treasury\Accounts::class)->name('accounts');
    Route::get('/cash-registers', \App\Livewire\Treasury\CashRegisters::class)->name('cash-registers');
    Route::get('/transactions', \App\Livewire\Treasury\Transactions::class)->name('transactions');
    Route::get('/transaction-types', \App\Livewire\Treasury\TransactionClassifications::class)
        ->defaults('kind', 'type')->name('transaction-types');
    Route::get('/transaction-categories', \App\Livewire\Treasury\TransactionClassifications::class)
        ->defaults('kind', 'category')->name('transaction-categories');
    Route::get('/transfers', \App\Livewire\Treasury\TransfersManagement::class)->name('transfers');
});

// Events Module Routes
Route::middleware(['auth', 'tenant.module:eventos'])->prefix('events')->name('events.')->group(function () {
    // Dashboard
    Route::get('/dashboard', \App\Livewire\Events\Dashboard::class)->name('dashboard');
    
    // Calendário
    Route::get('/calendar', \App\Livewire\Events\EventCalendar::class)->name('calendar');
    
    // Relatórios
    Route::get('/reports', \App\Livewire\Events\Reports::class)->name('reports');
    
    // Equipamentos
    Route::prefix('equipment')->name('equipment.')->group(function () {
        Route::get('/', \App\Livewire\Events\Equipment\EquipmentManager::class)->name('index');
        Route::get('/dashboard', \App\Livewire\Events\Equipment\EquipmentDashboard::class)->name('dashboard');
        Route::get('/sets', \App\Livewire\Events\Equipment\EquipmentSets::class)->name('sets');
        Route::get('/categories', \App\Livewire\Events\Equipment\EquipmentCategories::class)->name('categories');
        Route::get('/scan/{id}', function($id) {
            $equipment = \App\Models\Equipment::findOrFail($id);
            return redirect()->route('events.equipment.index')->with('scan_equipment', $equipment->id);
        })->name('scan');
        Route::get('/{id}/qrcode', [\App\Http\Controllers\EquipmentController::class, 'generateQrCode'])->name('qrcode');
        Route::get('/{id}/qrcode/print', [\App\Http\Controllers\EquipmentController::class, 'printQrCode'])->name('qrcode.print');
        
        // Rota de teste QR Code
        Route::get('/test-qrcode', function() {
            try {
                $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                    new \BaconQrCode\Renderer\RendererStyle\RendererStyle(400, 2),
                    new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
                );
                $writer = new \BaconQrCode\Writer($renderer);
                $qrCode = $writer->writeString('https://soserp.test/events/equipment');
                return response($qrCode)->header('Content-Type', 'image/svg+xml');
            } catch (\Exception $e) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ], 500);
            }
        })->name('test.qrcode');
    });
    
    // Locais
    Route::prefix('venues')->name('venues.')->group(function () {
        Route::get('/', \App\Livewire\Events\Venues\VenuesManager::class)->name('index');
    });
    
    // Tipos de Eventos
    Route::prefix('types')->name('types.')->group(function () {
        Route::get('/', \App\Livewire\Events\EventTypes::class)->name('index');
    });
    
    // Técnicos
    Route::prefix('technicians')->name('technicians.')->group(function () {
        Route::get('/', \App\Livewire\Events\TechniciansManager::class)->name('index');
    });
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
    Route::get('/dashboard', \App\Livewire\HR\HRDashboard::class)->name('dashboard');
    Route::get('/employees', \App\Livewire\HR\EmployeeManagement::class)->name('employees.index');
    Route::get('/employees/{id}/sheet', [\App\Http\Controllers\HR\EmployeeController::class, 'employeeSheet'])->name('employees.sheet');
    Route::get('/vacations/{id}/pdf', [\App\Http\Controllers\HR\VacationController::class, 'generatePDF'])->name('vacations.pdf');
    Route::get('/leaves/{id}/pdf', [\App\Http\Controllers\HR\LeaveController::class, 'generatePDF'])->name('leaves.pdf');
    Route::get('/payroll', \App\Livewire\HR\PayrollManagement::class)->name('payroll');
    Route::get('/payroll/payslip/{id}/pdf', [\App\Http\Controllers\HR\PayrollController::class, 'generatePayslipPDF'])->name('payroll.payslip.pdf');
    Route::get('/payroll/{id}/payslips-pdf', [\App\Http\Controllers\HR\PayrollController::class, 'generateAllPayslipsPDF'])->name('payroll.payslips-all.pdf');
    Route::get('/departments', \App\Livewire\HR\DepartmentManagement::class)->name('departments.index');
    Route::get('/attendance', \App\Livewire\HR\AttendanceManagement::class)->name('attendance.index');
    Route::get('/vacations', \App\Livewire\HR\VacationManagement::class)->name('vacations.index');
    Route::get('/leaves', \App\Livewire\HR\LeaveManagement::class)->name('leaves');
    Route::get('/advances', \App\Livewire\HR\SalaryAdvanceManagement::class)->name('advances');
    Route::get('/advances/{id}/pdf', [\App\Http\Controllers\HR\SalaryAdvanceController::class, 'generatePDF'])->name('advances.pdf');
    Route::get('/overtime', \App\Livewire\HR\OvertimeManagement::class)->name('overtime');
    Route::get('/overtime/{id}/pdf', [\App\Http\Controllers\HR\OvertimeController::class, 'generatePDF'])->name('overtime.pdf');
    Route::get('/overtime-night-shift', \App\Livewire\HR\OvertimeNightShiftManagement::class)->name('overtime-night-shift');
    Route::get('/salary-discounts', \App\Livewire\HR\SalaryDiscountManagement::class)->name('salary-discounts');
    Route::get('/salary-discounts/{id}/pdf', [\App\Http\Controllers\HR\SalaryDiscountController::class, 'generatePDF'])->name('salary-discounts.pdf');
    Route::get('/shifts', \App\Livewire\HR\ShiftsManagement::class)->name('shifts.index');
    Route::get('/reports', \App\Livewire\HR\HRReports::class)->name('reports');
    Route::get('/settings', \App\Livewire\HR\SettingsManagement::class)->name('settings');
});

// Accounting Module Routes
Route::middleware(['auth', 'tenant.module:contabilidade'])->prefix('accounting')->name('accounting.')->group(function () {
    Route::get('/dashboard', \App\Livewire\Accounting\Dashboard::class)->name('dashboard');
    Route::get('/accounts', \App\Livewire\Accounting\AccountManagement::class)->name('accounts');
    Route::get('/journals', \App\Livewire\Accounting\JournalManagement::class)->name('journals');
    Route::get('/document-types', \App\Livewire\Accounting\DocumentTypeManagement::class)->name('document-types');
    Route::get('/moves', \App\Livewire\Accounting\MoveManagement::class)->name('moves');
    Route::get('/periods', \App\Livewire\Accounting\PeriodManagement::class)->name('periods');
    Route::get('/reports', \App\Livewire\Accounting\ReportsManagement::class)->name('reports');
    
    // R1 & R2 Routes
    Route::get('/reconciliation', \App\Livewire\Accounting\BankReconciliationManagement::class)->name('reconciliation');
    Route::get('/fixed-assets', \App\Livewire\Accounting\FixedAssetManagement::class)->name('fixed-assets');
    Route::get('/currencies', \App\Livewire\Accounting\CurrencyManagement::class)->name('currencies');
    Route::get('/cost-centers', \App\Livewire\Accounting\CostCenterManagement::class)->name('cost-centers');
    Route::get('/analytics', \App\Livewire\Accounting\AnalyticManagement::class)->name('analytics');
    Route::get('/budgets', \App\Livewire\Accounting\BudgetManagement::class)->name('budgets');
    Route::get('/settings', \App\Livewire\Accounting\SettingsManagement::class)->name('settings');
});

// Notifications Module Routes
Route::middleware(['auth', 'tenant.module:notifications'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/settings', \App\Livewire\Settings\NotificationSettings::class)->name('settings');
    Route::get('/templates', \App\Livewire\Settings\ManageNotificationTemplates::class)->name('templates');
});

// Workshop Module Routes
Route::middleware(['auth', 'tenant.module:oficina'])->prefix('workshop')->name('workshop.')->group(function () {
    Route::get('/dashboard', \App\Livewire\Workshop\Dashboard::class)->name('dashboard');
    Route::get('/vehicles', \App\Livewire\Workshop\VehicleManagement::class)->name('vehicles');
    Route::get('/mechanics', \App\Livewire\Workshop\MechanicManagement::class)->name('mechanics');
    Route::get('/services', \App\Livewire\Workshop\ServiceManagement::class)->name('services');
    Route::get('/parts', \App\Livewire\Workshop\PartManagement::class)->name('parts');
    Route::get('/work-orders', \App\Livewire\Workshop\WorkOrderManagement::class)->name('work-orders');
    Route::get('/work-orders/{id}/print', [\App\Http\Controllers\Workshop\WorkOrderController::class, 'printPreview'])->name('work-orders.print');
    Route::get('/reports', \App\Livewire\Workshop\Reports::class)->name('reports');
});

// CRM Module Routes (Placeholder)
Route::middleware(['auth', 'tenant.module:crm'])->prefix('crm')->name('crm.')->group(function () {
    Route::get('/dashboard', fn() => view('modules.under-construction', ['module' => 'CRM']))->name('dashboard');
    Route::get('/leads', fn() => view('modules.under-construction', ['module' => 'Leads']))->name('leads');
    Route::get('/oportunidades', fn() => view('modules.under-construction', ['module' => 'Oportunidades']))->name('oportunidades');
    Route::get('/funil-vendas', fn() => view('modules.under-construction', ['module' => 'Funil de Vendas']))->name('funil-vendas');
});

// Inventário Module Routes (Placeholder)
Route::middleware(['auth', 'tenant.module:inventario'])->prefix('inventario')->name('inventario.')->group(function () {
    Route::get('/dashboard', fn() => view('modules.under-construction', ['module' => 'Inventário']))->name('dashboard');
    Route::get('/armazens', fn() => view('modules.under-construction', ['module' => 'Armazéns']))->name('armazens');
    Route::get('/movimentos', fn() => view('modules.under-construction', ['module' => 'Movimentos de Stock']))->name('movimentos');
    Route::get('/contagem', fn() => view('modules.under-construction', ['module' => 'Contagem de Stock']))->name('contagem');
});

// Compras Module Routes (Placeholder)
Route::middleware(['auth', 'tenant.module:compras'])->prefix('compras')->name('compras.')->group(function () {
    Route::get('/dashboard', fn() => view('modules.under-construction', ['module' => 'Compras']))->name('dashboard');
    Route::get('/fornecedores', fn() => view('modules.under-construction', ['module' => 'Fornecedores']))->name('fornecedores');
    Route::get('/requisicoes', fn() => view('modules.under-construction', ['module' => 'Requisições de Compra']))->name('requisicoes');
    Route::get('/encomendas', fn() => view('modules.under-construction', ['module' => 'Encomendas']))->name('encomendas');
});

// Projetos Module Routes (Placeholder)
Route::middleware(['auth', 'tenant.module:projetos'])->prefix('projetos')->name('projetos.')->group(function () {
    Route::get('/dashboard', fn() => view('modules.under-construction', ['module' => 'Projetos']))->name('dashboard');
    Route::get('/lista', fn() => view('modules.under-construction', ['module' => 'Lista de Projetos']))->name('lista');
    Route::get('/tarefas', fn() => view('modules.under-construction', ['module' => 'Tarefas']))->name('tarefas');
    Route::get('/timesheet', fn() => view('modules.under-construction', ['module' => 'Timesheet']))->name('timesheet');
});

// Hotel Module Routes
Route::middleware(['auth', 'tenant.module:hotel'])->prefix('hotel')->name('hotel.')->group(function () {
    Route::get('/dashboard', \App\Livewire\Hotel\Dashboard::class)->name('dashboard');
    Route::get('/room-types', \App\Livewire\Hotel\RoomTypeManagement::class)->name('room-types');
    Route::get('/rooms', \App\Livewire\Hotel\RoomManagement::class)->name('rooms');
    Route::get('/guests', \App\Livewire\Hotel\GuestManagement::class)->name('guests');
    Route::get('/reservations', \App\Livewire\Hotel\ReservationManagement::class)->name('reservations');
    Route::get('/walk-in', \App\Livewire\Hotel\WalkIn::class)->name('walk-in');
    // Parâmetro opcional: permite abrir o check-out já numa reserva concreta
    // (é o que o botão da lista de reservas faz). Sem parâmetro continua a
    // abrir o ecrã de pesquisa, como o menu lateral espera.
    Route::get('/checkout/{reservationId?}', \App\Livewire\Hotel\Checkout::class)->name('checkout');
    Route::get('/calendar', \App\Livewire\Hotel\CalendarReservation::class)->name('calendar');
    Route::get('/housekeeping', \App\Livewire\Hotel\HousekeepingDashboard::class)->name('housekeeping');
    Route::get('/maintenance', \App\Livewire\Hotel\MaintenanceManagement::class)->name('maintenance');
    Route::get('/staff', \App\Livewire\Hotel\StaffManagement::class)->name('staff');
    Route::get('/reports', \App\Livewire\Hotel\Reports::class)->name('reports');
    Route::get('/rates', \App\Livewire\Hotel\RateManagement::class)->name('rates');
    Route::get('/packages', \App\Livewire\Hotel\PackageManagement::class)->name('packages');
    Route::get('/settings', \App\Livewire\Hotel\HotelSettingsManagement::class)->name('settings');

    // Folio (consumos por reserva)
    Route::get('/reservations/{id}/folio', \App\Livewire\Hotel\ReservationFolio::class)->name('reservations.folio');

    // Documents (PDF/HTML print-friendly)
    Route::get('/reservations/{id}/voucher', [\App\Http\Controllers\Hotel\ReservationController::class, 'voucher'])->name('reservations.voucher');
    Route::get('/reservations/{id}/folio-pdf', [\App\Http\Controllers\Hotel\ReservationController::class, 'folio'])->name('reservations.folio.pdf');
    Route::get('/reservations/{id}/sef', [\App\Http\Controllers\Hotel\ReservationController::class, 'sef'])->name('reservations.sef');
    Route::get('/reservations/{id}/qr', [\App\Http\Controllers\Hotel\ReservationController::class, 'qr'])->name('reservations.qr');
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

Route::get('/hotel/booking/{slug}', \App\Livewire\Hotel\HotelBookingOnline::class)->name('hotel.booking.online');

// Salon Module Routes
Route::middleware(['auth', 'tenant.module:salon'])->prefix('salon')->name('salon.')->group(function () {
    Route::get('/dashboard', \App\Livewire\Salon\Dashboard::class)->name('dashboard');
    Route::get('/appointments', \App\Livewire\Salon\AppointmentManagement::class)->name('appointments');
    Route::get('/services', \App\Livewire\Salon\ServiceManagement::class)->name('services');
    Route::get('/services/categories', \App\Livewire\Salon\ServiceCategoryManagement::class)->name('services.categories');
    Route::get('/professionals', \App\Livewire\Salon\ProfessionalManagement::class)->name('professionals');
    Route::get('/clients', \App\Livewire\Salon\ClientManagement::class)->name('clients');
    Route::get('/products', \App\Livewire\Salon\ProductManagement::class)->name('products');
    Route::get('/pos', \App\Livewire\Salon\SalonPOS::class)->name('pos');
    Route::get('/reports/time', \App\Livewire\Salon\TimeReport::class)->name('reports.time');
    Route::get('/settings', \App\Livewire\Salon\SalonSettingsManagement::class)->name('settings');
});

// Salon Booking Online (Public) - Landing Page Customizada
Route::get('/agendar/{slug}', \App\Livewire\Salon\SalonBookingOnline::class)->name('salon.booking.online');

// Restaurante - operação de sala e comandas; faturação permanece no módulo Invoicing.
Route::middleware(['auth', 'tenant.module:restaurant'])->prefix('restaurant')->name('restaurant.')->group(function () {
    Route::middleware('permission:restaurant.dashboard.view')
        ->get('/dashboard', \App\Livewire\Restaurant\Dashboard::class)->name('dashboard');
    Route::middleware('permission:restaurant.floor.view')
        ->get('/floor', \App\Livewire\Restaurant\FloorManagement::class)->name('floor');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/orders', \App\Livewire\Restaurant\OrderManagement::class)->name('orders');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/pos', \App\Livewire\Restaurant\RestaurantPos::class)->name('pos');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/products', \App\Livewire\Invoicing\Products::class)->name('products');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/contacts', \App\Livewire\Restaurant\ContactManagement::class)->name('contacts');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/categories', \App\Livewire\Restaurant\CategoryManagement::class)->name('categories');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/shifts', \App\Livewire\Invoicing\Pos\PosShiftManager::class)->name('shifts');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/shift-history', \App\Livewire\Invoicing\Pos\ShiftHistory::class)->name('shift-history');
    Route::middleware('permission:restaurant.reports.view')
        ->get('/sales-report', \App\Livewire\POS\SalesReport::class)
        ->defaults('sourceModule', 'restaurant')->name('sales-report');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/orders/{id}/consultation-receipt', [\App\Http\Controllers\Restaurant\RestaurantDocumentController::class, 'consultationReceipt'])->name('orders.consultation-receipt');
    Route::middleware('permission:restaurant.orders.view')
        ->get('/documents/{id}/print', [\App\Http\Controllers\Restaurant\RestaurantDocumentController::class, 'fiscalDocument'])->name('documents.print');
    Route::middleware('permission:restaurant.kitchen.view')
        ->get('/kitchen', \App\Livewire\Restaurant\KitchenDisplay::class)->name('kitchen');
    Route::middleware('permission:restaurant.kitchen.view')->get('/kitchen/tickets/{ticket}/print', [\App\Http\Controllers\Restaurant\KitchenTicketController::class,'print'])->name('kitchen.print');
    Route::middleware('permission:restaurant.reservations.view')
        ->get('/reservations', \App\Livewire\Restaurant\ReservationManagement::class)->name('reservations');
    Route::middleware('permission:restaurant.recipes.view')->get('/recipes', \App\Livewire\Restaurant\RecipeManagement::class)->name('recipes');
    Route::middleware('permission:restaurant.stock.view')->get('/stock', \App\Livewire\Restaurant\StockWasteManagement::class)->name('stock');
    Route::middleware('permission:restaurant.reports.view')->get('/reports', \App\Livewire\Restaurant\Reports::class)->name('reports');
    Route::middleware('permission:restaurant.settings.view')->get('/settings', \App\Livewire\Restaurant\SettingsManagement::class)->name('settings');
});

// Support/Help Center Routes
Route::middleware(['auth'])->prefix('support')->name('support.')->group(function () {
    Route::get('/tickets', \App\Livewire\Support\TicketsManagement::class)->name('tickets');
    Route::get('/features', \App\Livewire\Support\FeatureRequestsBoard::class)->name('features');
});
