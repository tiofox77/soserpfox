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

        // Licenciamento offline: uma porta única, cacheada por processo. É
        // inofensivo na cloud — só o middleware age, e só com LICENSE_ENFORCE.
        $this->app->singleton(\App\Services\Licensing\LicenseManager::class, function () {
            $cfg = config('licensing', []);

            return new \App\Services\Licensing\LicenseManager(
                new \App\Services\Licensing\LicenseStore($cfg),
                $cfg
            );
        });

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
            'licenca_estado'              => app_path('Helpers/LicenseHelper.php'),
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
        $this->prepararSuiteEmParalelo();

        // Limites de chamadas da API do agente externo.
        //
        // A contagem é por TOKEN, não por IP: dois agentes atrás do mesmo
        // NAT não podem gastar a quota um do outro, e um agente que mude
        // de IP não escapa ao limite.
        \Illuminate\Support\Facades\RateLimiter::for('agent-read', function ($request) {
            $token = app()->bound(\App\Support\AgenteAutenticado::class)
                ? app(\App\Support\AgenteAutenticado::class)->id()
                : $request->ip();

            return \Illuminate\Cache\RateLimiting\Limit::perMinute(
                (int) config('agent.limites.leitura_por_minuto', 120)
            )->by('agent-read:' . $token);
        });

        \Illuminate\Support\Facades\RateLimiter::for('agent-write', function ($request) {
            $token = app()->bound(\App\Support\AgenteAutenticado::class)
                ? app(\App\Support\AgenteAutenticado::class)->id()
                : $request->ip();

            return \Illuminate\Cache\RateLimiting\Limit::perMinute(
                (int) config('agent.limites.escrita_por_minuto', 10)
            )->by('agent-write:' . $token);
        });

        // O NIF de empresa também com nome, e não só como objecto.
        //
        // O ecrã de empresas do super admin guarda as regras numa PROPRIEDADE
        // de classe, e ali o PHP não deixa instanciar nada. Registada assim,
        // a mesma regra serve tanto o `new NifDeEmpresa()` como o
        // 'nullable|nif_empresa' — sem uma segunda cópia da regra a envelhecer
        // em paralelo com a primeira.
        \Illuminate\Support\Facades\Validator::extend('nif_empresa', function ($atributo, $valor, $parametros, $validador) {
            $falhou = null;

            (new \App\Rules\NifDeEmpresa())->validate($atributo, $valor, function ($mensagem, $dados = []) use (&$falhou) {
                $falhou = __($mensagem, $dados);
            });

            if ($falhou !== null) {
                $validador->setCustomMessages([$atributo . '.nif_empresa' => $falhou]);
            }

            return $falhou === null;
        });

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

        // Avisar quem administra a plataforma de que nasceu uma empresa nova.
        // Aqui e não nos ecrãs de registo: são três as vias que criam uma
        // empresa, e nenhuma delas avisava ninguém.
        \App\Models\Tenant::observe(\App\Observers\TenantObserver::class);
        
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

        // Pagar a factura de subscrição estende a subscrição — a peça que
        // fecha o ciclo de facturação da plataforma. Note-se que é o
        // FacturaDeSubscricaoObserver e NÃO o InvoiceObserver ao lado (esse
        // manda facturas para a contabilidade da empresa, e o que a empresa
        // paga à plataforma não entra nos livros dela).
        Invoice::observe(\App\Observers\FacturaDeSubscricaoObserver::class);
        
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

    /**
     * A suite a correr em paralelo: uma base de dados por processo.
     *
     * PORQUE ISTO NÃO É `migrate`. As migrações deste projecto não correm de
     * raiz — a ordem está partida e uma instalação limpa falha (ver
     * scripts/prepare_test_db.php). O caminho normal do Laravel para o modo
     * paralelo é criar cada base com `migrate:fresh`, e aqui isso não funciona.
     *
     * Então clona-se o ESQUEMA da base de testes que já existe. É rápido (só
     * DDL, nenhum dado) e é fiel ao esquema real, que é a razão de o
     * prepare_test_db.php existir.
     *
     * INERTE FORA DOS TESTES. O guarda não é defensivo por hábito: este
     * ficheiro corre em produção a cada pedido, e um `CREATE DATABASE` à solta
     * aqui seria a pior coisa que este ficheiro podia fazer.
     */
    private function prepararSuiteEmParalelo(): void
    {
        if (!$this->app->environment('testing')) {
            return;
        }

        \Illuminate\Support\Facades\ParallelTesting::setUpProcess(function ($token) {
            $molde = (string) config('database.connections.mysql.database');

            // O nome que o Laravel vai usar neste processo. Tem de ser
            // calculado aqui e não lido da configuração: nesta altura ela
            // ainda aponta para a base comum, e é precisamente ANTES de o
            // Laravel lá tocar que o esquema tem de existir.
            //
            // Se a base chegar vazia ao arranque do primeiro teste, o Laravel
            // tenta pô-la em dia com `migrate` — e as migrações deste projecto
            // não correm de raiz. Era o que rebentava: 1714 erros a dizer
            // "Table 'hr_departments' already exists".
            $daCorrida = "{$molde}_test_{$token}";

            $anfitriao = config('database.connections.mysql.host');
            $utilizador = config('database.connections.mysql.username');
            $palavra = config('database.connections.mysql.password');

            $pdo = new \PDO(
                "mysql:host={$anfitriao}",
                $utilizador,
                $palavra,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$daCorrida}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Só se constrói uma vez. As bases dos processos ficam entre
            // corridas de propósito: reconstruir dezenas de esquemas a cada
            // `artisan test` custava mais do que a paralelização poupa.
            //
            // MAS A CONTAGEM TEM DE BATER. "Tem tabelas" não chega como prova
            // de estar completa: uma corrida que rebentou a meio deixa a base
            // com meia dúzia de tabelas, e um simples "não está vazia" tomava
            // isso por pronto — o Laravel migrava por cima e voltava a
            // rebentar, corrida após corrida.
            // E AS COLUNAS TAMBÉM. Só as tabelas não chegava: uma migração
            // que acrescenta colunas a uma tabela existente (subscriptions
            // ganhou quatro em 2026-09-02) deixava a contagem a bater e os
            // 29 processos a correr com o esquema velho — centenas de
            // "Unknown column" que pareciam ensaios partidos e eram bases
            // fora de prazo. A impressão digital é tabelas + colunas.
            // E não só o NÚMERO de colunas: também o que cada uma é. Uma
            // migração que só muda um DEFAULT ou torna uma coluna anulável
            // (agt_schema_version, 2026-09-02) deixa as contagens iguais e
            // o esquema diferente. Resume-se tudo a um MD5 das definições.
            // (O resumo faz-se em PHP e não com GROUP_CONCAT: o MySQL
            // trunca-o a 1 KB por omissão, e 230 tabelas são 100 KB de
            // definições — o MD5 só veria as primeiras.)
            $contar = function (string $base) use ($pdo): string {
                $tabelas = (int) $pdo->query(
                    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $pdo->quote($base)
                )->fetchColumn();

                $definicoes = $pdo->query(
                    "SELECT CONCAT_WS('|', TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, IFNULL(COLUMN_DEFAULT, 'NULL'))"
                    . " FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = " . $pdo->quote($base)
                    . " ORDER BY TABLE_NAME, ORDINAL_POSITION"
                )->fetchAll(\PDO::FETCH_COLUMN);

                return $tabelas . ':' . count($definicoes) . ':' . md5(implode(';', $definicoes));
            };

            if ($contar($daCorrida) === $contar($molde)) {
                return;
            }

            // NUNCA TODOS AO MESMO TEMPO.
            //
            // Depois de uma migração nova, a contagem deixa de bater em TODOS
            // os processos — e os 26 clonavam o molde em simultâneo: 26 × 231
            // tabelas de DDL contra o mesmo MySQL. A suite ficou 13 minutos
            // pendurada no arranque sem correr um único teste.
            //
            // Semáforo com 4 lugares (GET_LOCK é por LIGAÇÃO, e esta fica
            // aberta até ao fim do clone): quatro clones de cada vez é o que o
            // disco aguenta sem se atropelar. Só custa alguma coisa na
            // primeira corrida depois de uma migração; nas outras, a contagem
            // bate e ninguém passa por aqui.
            $lugar = ((int) $token) % 4;

            // 90s e não mais: uma corrida morta a meio deixa a ligação zombie
            // com a tranca na mão, e um timeout de minutos pendurava TODAS as
            // corridas seguintes no arranque (aconteceu). Esgotado o prazo,
            // avança-se sem tranca — o pior caso é clonar em simultâneo, que
            // é lento mas nunca errado: cada worker clona a SUA base.
            $pdo->query("SELECT GET_LOCK('clone-esquema-{$lugar}', 90)");

            try {
                // Outro processo do mesmo lugar pode ter acabado de clonar
                // ESTA base? Não — cada processo clona a SUA. Mas a espera
                // pode ter demorado; confere-se outra vez por higiene.
                if ($contar($daCorrida) === $contar($molde)) {
                    return;
                }

                exec(sprintf(
                    'php %s %s %s 2>&1',
                    escapeshellarg(base_path('scripts/prepare_test_db.php')),
                    escapeshellarg($daCorrida),
                    escapeshellarg($molde)
                ));
            } finally {
                $pdo->query("SELECT RELEASE_LOCK('clone-esquema-{$lugar}')");
            }
        });
    }
}
