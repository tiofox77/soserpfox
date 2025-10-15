<?php

namespace App\Providers;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Order;
use App\Models\HR\Leave;
use App\Models\HR\Employee;
use App\Models\HR\Advance;
use App\Models\HR\Payroll;
use App\Models\Task;
use App\Models\Meeting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Observers\SalesInvoiceObserver;
use App\Observers\PurchaseInvoiceObserver;
use App\Observers\OrderObserver;
use App\Observers\LeaveObserver;
use App\Observers\EmployeeObserver;
use App\Observers\AdvanceObserver;
use App\Observers\PayrollObserver;
use App\Observers\TaskObserver;
use App\Observers\MeetingObserver;
use App\Observers\InvoiceObserver;
use App\Observers\ReceiptObserver;
use App\Observers\PaymentObserver;
use App\Observers\EventObserver;
use App\Observers\EventTechnicianObserver;
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
        
        // Registrar Observer para integração Licenças → Presenças + Notificações
        Leave::observe(LeaveObserver::class);
        
        // Registrar Observers para integração Contabilidade
        Invoice::observe(InvoiceObserver::class);
        Receipt::observe(ReceiptObserver::class);
        Payment::observe(PaymentObserver::class);
        
        // Registrar Observers para notificações imediatas de eventos
        if (class_exists(\App\Models\Events\Event::class)) {
            \App\Models\Events\Event::observe(EventObserver::class);
        }
        
        // Registrar Observers para notificações de RH
        if (class_exists(\App\Models\HR\Employee::class)) {
            Employee::observe(EmployeeObserver::class);
        }
        
        if (class_exists(\App\Models\HR\Advance::class)) {
            Advance::observe(AdvanceObserver::class);
        }
        
        if (class_exists(\App\Models\HR\Payroll::class)) {
            Payroll::observe(PayrollObserver::class);
        }
        
        // Registrar Observers para notificações de tarefas e reuniões
        if (class_exists(\App\Models\Task::class)) {
            Task::observe(TaskObserver::class);
        }
        
        if (class_exists(\App\Models\Meeting::class)) {
            Meeting::observe(MeetingObserver::class);
        }
        
        // Observer para quando técnico é designado (tabela pivot)
        // Será acionado via helper manual nos controllers
        
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
