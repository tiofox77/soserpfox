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
use App\Observers\WorkOrderObserver;
use App\Observers\PlanObserver;
use App\Observers\StockObserver;
use App\Models\Workshop\WorkOrder;
use App\Models\Plan;
use App\Models\Invoicing\Stock;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // O gravador de auditoria TEM de ser singleton.
        //
        // O observer é resolvido do contentor a cada evento; sem isto, cada
        // acto criava uma instância nova — o buffer nunca agrupava (perdendo-se
        // a escrita única depois do commit) e cada linha ganhava um request_id
        // diferente, deixando de ser possível colar as 12-15 linhas de uma
        // venda ao pedido que as gerou.
        $this->app->singleton(\App\Services\Audit\AuditRecorder::class);

        // Defensive: garantir que helpers globais estão carregados em produção
        // (necessário se composer dump-autoload não foi executado após FTP)
        $helpers = [
            'getAGTQRData'                => app_path('Helpers/QRCodeHelper.php'),
            'activeTenantId'              => app_path('Helpers/TenantHelper.php'),
            'defaultWarehouse'            => app_path('Helpers/WarehouseHelper.php'),
            'setting'                     => app_path('Helpers/SettingsHelper.php'),
            'createDefaultRolesForTenant' => app_path('Helpers/RoleHelper.php'),
            'numberToWords'               => app_path('Helpers/NumberToWordsHelper.php'),
            'softwareSetting'             => app_path('Helpers/SoftwareSettingsHelper.php'),
            'calculateIRT'                => app_path('Helpers/AngolanTaxHelper.php'),
        ];
        foreach ($helpers as $fn => $path) {
            if (!function_exists($fn) && is_file($path)) {
                require_once $path;
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Auto-cleanup PWA: garantir que ficheiros estáticos legados são removidos
        // para que as rotas dinâmicas Laravel sejam usadas. Idempotente, leve.
        // Só corre em requests HTTP (não em comandos artisan) e no máximo 1x/dia.
        if (!app()->runningInConsole()) {
            try {
                $cleanupKey = 'pwa.cleanup.done';
                if (!\Illuminate\Support\Facades\Cache::has($cleanupKey)) {
                    if (\App\Http\Controllers\PwaController::needsCleanup()) {
                        \App\Http\Controllers\PwaController::performCleanup();
                    }
                    \Illuminate\Support\Facades\Cache::put($cleanupKey, true, 86400);
                }
            } catch (\Throwable $e) {
                // não bloquear request por falha no cleanup
                \Illuminate\Support\Facades\Log::warning('PWA auto-cleanup falhou: ' . $e->getMessage());
            }
        }

        // Registrar Observers para atualização automática de stock
        SalesInvoice::observe(SalesInvoiceObserver::class);
        PurchaseInvoice::observe(PurchaseInvoiceObserver::class);
        
        // Registrar Observer para aprovação automática de pedidos
        Order::observe(OrderObserver::class);
        
        // Registrar Observer para integração Licenças → Presenças + Notificações
        Leave::observe(LeaveObserver::class);
        
        // Registrar Observers para integração Contabilidade
        // NOTA: App\Models\Invoice é legado (tabela `invoices`, vazia). As faturas
        // reais são SalesInvoice — sem esta linha nenhuma venda ia à contabilidade.
        SalesInvoice::observe(\App\Observers\SalesInvoiceAccountingObserver::class);
        Receipt::observe(ReceiptObserver::class);
        Payment::observe(PaymentObserver::class);
        // Notas de crédito e débito: não tinham integração nenhuma, pelo que as
        // devoluções e as cobranças adicionais ficavam fora dos livros.
        \App\Models\Invoicing\CreditNote::observe(\App\Observers\NoteAccountingObserver::class);
        \App\Models\Invoicing\DebitNote::observe(\App\Observers\NoteAccountingObserver::class);
        
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
        
        // Registrar Observer para rastrear histórico de WorkOrders
        if (class_exists(\App\Models\Workshop\WorkOrder::class)) {
            WorkOrder::observe(WorkOrderObserver::class);
        }
        
        // Registrar Observer para Salon - atualizar visitas e VIP automático
        if (class_exists(\App\Models\Salon\Appointment::class)) {
            \App\Models\Salon\Appointment::observe(\App\Observers\SalonAppointmentObserver::class);
        }
        
        // Registrar Observer para sincronizar módulos quando plano muda
        Plan::observe(PlanObserver::class);
        
        // Registrar Observer para manter stock agregado sincronizado com invoicing_stocks
        Stock::observe(StockObserver::class);
        // Histórico de produtos: quem criou, alterou, eliminou ou restaurou.
        \App\Models\Product::observe(\App\Observers\ProductActivityObserver::class);

        // Traduções em falta ficam no log — mas só fora do português.
        //
        // O PT é a origem: as chaves SÃO o texto português, portanto "faltar"
        // em pt é o estado normal de tudo. Em en/fr, uma chave em falta é
        // trabalho por fazer que o varrimento estático não apanhou (cadeias
        // montadas em runtime) — é este registo que fecha essa malha.
        \Illuminate\Support\Facades\Lang::handleMissingKeysUsing(function (string $key, array $replacements, string $locale) {
            if ($locale !== 'pt' && !str_contains($key, 'validation.')) {
                \Log::info('Tradução em falta', ['lingua' => $locale, 'chave' => mb_substr($key, 0, 160)]);
            }

            return $key;
        });

        // Todo o correio que sai fica registado em /superadmin/email-logs.
        //
        // Registar em cada sítio que envia é uma lista que nunca fica
        // completa: o ecrã mostrava dois registos — os envios de teste do SMTP
        // — e tudo o resto saía sem rasto. Aqui apanha-se no evento do próprio
        // Laravel, por onde passa obrigatoriamente todo o correio.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Mail\Events\MessageSending::class,
            [\App\Listeners\RegistarEmailEnviado::class, 'aoEnviar']
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Mail\Events\MessageSent::class,
            [\App\Listeners\RegistarEmailEnviado::class, 'aoSair']
        );

        // Entradas e saídas do sistema.
        //
        // Não são alterações de modelo, portanto o observer não as vê — e são
        // metade da pergunta que uma auditoria existe para responder: quem
        // estava lá dentro àquela hora.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            function ($evento) {
                app(\App\Services\Audit\AuditRecorder::class)->acto(
                    'login',
                    $evento->user->tenant_id ?? null,
                    ['guarda' => $evento->guard]
                );
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Failed::class,
            function ($evento) {
                // Tentativa falhada: é o sinal de força bruta e de credenciais
                // partilhadas. Regista-se o email tentado, nunca a password.
                app(\App\Services\Audit\AuditRecorder::class)->acto(
                    'login_falhado',
                    $evento->user->tenant_id ?? null,
                    ['guarda' => $evento->guard, 'email' => $evento->credentials['email'] ?? null]
                );
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Logout::class,
            function ($evento) {
                app(\App\Services\Audit\AuditRecorder::class)->acto(
                    'logout',
                    $evento->user->tenant_id ?? null,
                    ['guarda' => $evento->guard]
                );
            }
        );

        // Rollback desfaz também a auditoria pendente.
        //
        // O DB::afterCommit descarta o callback, mas o buffer do gravador
        // ficaria com as linhas — e sairiam no commit seguinte, a registar uma
        // transacção que foi desfeita.
        //
        // O NÍVEL é obrigatório. Este evento dispara TAMBÉM em rollbacks de
        // savepoint, não só nos de topo. Sem o nível, uma linha falhada no meio
        // de um lote apagava a auditoria das linhas irmãs que já tinham passado
        // e que iam mesmo ser confirmadas — medido no ecrã de movimentação de
        // stock: três movimentos gravados, uma única linha na trilha. E a
        // trilha é append-only: o que se perde ali não se repõe.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Database\Events\TransactionRolledBack::class,
            function (\Illuminate\Database\Events\TransactionRolledBack $evento) {
                app(\App\Services\Audit\AuditRecorder::class)
                    ->descartar($evento->connection->transactionLevel());
            }
        );

        // Um nível confirmou: o que lá dentro se registou sobe para o nível que
        // o contém, e deixa de estar ao alcance de reversões posteriores. É o
        // par obrigatório do ouvinte acima — os níveis reciclam-se, e sem esta
        // promoção o descarte de um savepoint apanhava factos de um savepoint
        // anterior que já tinha confirmado ao MESMO nível.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Database\Events\TransactionCommitted::class,
            function (\Illuminate\Database\Events\TransactionCommitted $evento) {
                app(\App\Services\Audit\AuditRecorder::class)
                    ->promover($evento->connection->transactionLevel());
            }
        );

        // Trilha de auditoria: um observer por modelo da allowlist.
        //
        // Em ciclo e não por wildcard sobre `eloquent.*`: o wildcard poria as
        // escritas dos 178 modelos a passar por aqui — incluindo as da própria
        // auditoria, o que dá recursão — e o raio de explosão de um erro
        // passaria a ser o sistema inteiro.
        foreach ((array) config('audit.models', []) as $modeloAuditado) {
            if (class_exists($modeloAuditado)) {
                $modeloAuditado::observe(\App\Observers\AuditObserver::class);
            }
        }

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
