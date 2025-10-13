<?php

namespace App\Providers;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Order;
use App\Models\HR\Leave;
use App\Models\Invoice;
use App\Models\Payment;
use App\Observers\SalesInvoiceObserver;
use App\Observers\PurchaseInvoiceObserver;
use App\Observers\OrderObserver;
use App\Observers\LeaveObserver;
use App\Observers\InvoiceObserver;
use App\Observers\ReceiptObserver;
use App\Observers\PaymentObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar Observers para atualização automática de stock
        SalesInvoice::observe(SalesInvoiceObserver::class);
        PurchaseInvoice::observe(PurchaseInvoiceObserver::class);
        
        // Registrar Observer para aprovação automática de pedidos
        Order::observe(OrderObserver::class);
        
        // Registrar Observer para integração Licenças → Presenças
        Leave::observe(LeaveObserver::class);
        
        // Registrar Observers para integração Contabilidade
        Invoice::observe(InvoiceObserver::class);
        Receipt::observe(ReceiptObserver::class);
        Payment::observe(PaymentObserver::class);
        
        // Definir tenant_id para Spatie Permission em cada requisição
        if (function_exists('setPermissionsTeamId')) {
            \View::composer('*', function ($view) {
                if (auth()->check() && function_exists('activeTenantId')) {
                    $tenantId = activeTenantId();
                    if ($tenantId) {
                        setPermissionsTeamId($tenantId);
                    }
                }
            });
        }
    }
}
