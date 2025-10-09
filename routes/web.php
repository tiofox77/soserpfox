<?php

use Illuminate\Support\Facades\Route;

// Landing Page
Route::get('/', [App\Http\Controllers\LandingController::class, 'home'])->name('landing.home');
Route::post('/contact', [App\Http\Controllers\ContactController::class, 'store'])->name('contact.store');

// Custom Register Wizard
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

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// My Account Route
Route::middleware(['auth'])->group(function () {
    Route::get('/my-account', \App\Livewire\MyAccount::class)->name('my-account');
});

// User Management Routes (Super Admin Only)
Route::middleware(['auth', 'superadmin'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', \App\Livewire\Users\UserManagement::class)->name('index');
    Route::get('/roles-permissions', \App\Livewire\Users\RolesAndPermissions::class)->name('roles-permissions');
    Route::get('/invitations', \App\Livewire\Users\InviteUser::class)->name('invitations');
});

// Super Admin Routes
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', \App\Livewire\SuperAdmin\Dashboard::class)->name('dashboard');
    Route::get('/tenants', \App\Livewire\SuperAdmin\Tenants::class)->name('tenants');
    Route::get('/modules', \App\Livewire\SuperAdmin\Modules::class)->name('modules');
    Route::get('/plans', \App\Livewire\SuperAdmin\Plans::class)->name('plans');
    Route::get('/billing', \App\Livewire\SuperAdmin\Billing::class)->name('billing');
    Route::get('/system-updates', \App\Livewire\SuperAdmin\SystemUpdates::class)->name('system-updates');
    Route::get('/system-commands', \App\Livewire\SuperAdmin\SystemCommands::class)->name('system-commands');
    Route::get('/system-settings', \App\Livewire\SuperAdmin\SystemSettings::class)->name('system-settings');
    Route::get('/email-templates', \App\Livewire\SuperAdmin\EmailTemplates::class)->name('email-templates');
    Route::get('/smtp-settings', \App\Livewire\SuperAdmin\SmtpSettings::class)->name('smtp-settings');
    Route::get('/email-logs', \App\Livewire\SuperAdmin\EmailLogs::class)->name('email-logs');
    Route::get('/saft-configuration', \App\Livewire\SuperAdmin\SaftConfiguration::class)->name('saft');
    Route::get('/contact-messages', \App\Livewire\SuperAdmin\ContactMessages::class)->name('contact-messages');
});

// Invoicing Module Routes
Route::middleware(['auth'])->prefix('invoicing')->name('invoicing.')->group(function () {
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
    
    // Adiantamentos
    Route::prefix('advances')->name('advances.')->group(function () {
        Route::middleware('permission:invoicing.advances.view')->get('/', \App\Livewire\Invoicing\Advances\Advances::class)->name('index');
        Route::get('/create', \App\Livewire\Invoicing\Advances\AdvanceCreate::class)->name('create');
        Route::get('/{id}/edit', \App\Livewire\Invoicing\Advances\AdvanceCreate::class)->name('edit');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Invoicing\AdvanceController::class, 'generatePdf'])->name('pdf');
        Route::get('/{id}/preview', [\App\Http\Controllers\Invoicing\AdvanceController::class, 'previewHtml'])->name('preview');
    });
    
    // Configurações
    Route::get('/settings', \App\Livewire\Invoicing\Settings::class)->name('settings');
    Route::get('/series', \App\Livewire\Invoicing\SeriesManagement::class)->name('series');
    Route::get('/taxes', \App\Livewire\Invoicing\TaxManagement::class)->name('taxes');
    
    // Armazéns e Stock
    Route::get('/warehouses', \App\Livewire\Invoicing\Warehouses::class)->name('warehouses');
    Route::get('/stock', \App\Livewire\Invoicing\StockManagement::class)->name('stock');
    Route::get('/product-batches', \App\Livewire\Invoicing\ProductBatches\ProductBatches::class)->name('product-batches');
    Route::get('/warehouse-transfer', \App\Livewire\Invoicing\WarehouseTransfer::class)->name('warehouse-transfer');
    Route::get('/inter-company-transfer', \App\Livewire\Invoicing\InterCompanyTransfer::class)->name('inter-company-transfer');
    
    // Relatórios
    Route::get('/expiry-report', \App\Livewire\Invoicing\Reports\ExpiryReport::class)->name('expiry-report');
    
    // SAFT
    Route::get('/saft-generator', \App\Livewire\Invoicing\SAFTGenerator::class)->name('saft-generator');
    Route::get('/agt-documents', \App\Livewire\Invoicing\AGTDocumentGenerator::class)->name('agt-documents');
    
    // POS
    Route::get('/pos', \App\Livewire\POS\POSSystem::class)->name('pos');
    Route::get('/pos/reports', \App\Livewire\POS\SalesReport::class)->name('pos.reports');
});

// Treasury Module Routes
Route::middleware(['auth'])->prefix('treasury')->name('treasury.')->group(function () {
    Route::get('/dashboard', \App\Livewire\Treasury\Dashboard::class)->name('dashboard');
    Route::get('/reports', \App\Livewire\Treasury\Reports::class)->name('reports');
    Route::get('/payment-methods', \App\Livewire\Treasury\PaymentMethods::class)->name('payment-methods');
    Route::get('/banks', \App\Livewire\Treasury\Banks::class)->name('banks');
    Route::get('/accounts', \App\Livewire\Treasury\Accounts::class)->name('accounts');
    Route::get('/cash-registers', \App\Livewire\Treasury\CashRegisters::class)->name('cash-registers');
    Route::get('/transactions', \App\Livewire\Treasury\Transactions::class)->name('transactions');
});

// Events Module Routes
Route::middleware(['auth'])->prefix('events')->name('events.')->group(function () {
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
});
