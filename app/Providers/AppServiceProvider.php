<?php

namespace App\Providers;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\PurchaseInvoice;
use App\Observers\SalesInvoiceObserver;
use App\Observers\PurchaseInvoiceObserver;
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
