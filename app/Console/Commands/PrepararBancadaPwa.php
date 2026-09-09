<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\Warehouse;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Monta a bancada de ensaio do PWA: empresa, utilizador, catálogo e stock.
 *
 * Serve os testes de browser (Playwright) que exercitam o modo offline a
 * sério — sem uma empresa montada, o PWA não passa do login e não há nada
 * para sincronizar.
 *
 * RECUSA-SE A CORRER FORA DE `local`. As credenciais são fixas e conhecidas,
 * o que num servidor a sério seria uma porta aberta. A verificação não é um
 * aviso: é um `abort`.
 */
class PrepararBancadaPwa extends Command
{
    protected $signature = 'bancada:pwa
                            {--limpar : Apaga a bancada em vez de a montar}';

    protected $description = 'Monta (ou limpa) a empresa de ensaio do PWA — só em ambiente local';

    public const SLUG     = 'bancada-pwa';
    public const EMAIL    = 'bancada@pwa.local';
    public const PASSWORD = 'bancada-pwa-2026';
    public const PIN      = '4321';

    /** O caixa sem direitos de gestão, para o "esqueci o PIN" sem rede. */
    public const EMAIL_CAIXA = 'caixa@pwa.local';
    public const PIN_CAIXA   = '7391';

    public function handle(): int
    {
        if (!app()->environment('local')) {
            $this->error('bancada:pwa só corre em APP_ENV=local. Aqui é ' . app()->environment() . '.');

            return self::FAILURE;
        }

        return $this->option('limpar') ? $this->limpar() : $this->montar();
    }

    private function montar(): int
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => self::SLUG],
            [
                'name'      => 'Bancada PWA',
                'nif'       => '5000000099',
                'email'     => self::EMAIL,
                'address'   => 'Luanda, Angola',
                'phone'     => '923000000',
                'is_active' => true,
            ]
        );

        $utilizador = User::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name'      => 'Operador da Bancada',
                'password'  => Hash::make(self::PASSWORD),
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]
        );

        // A password é reposta sempre: uma bancada que não deixa entrar não
        // serve para nada, e o teste não tem como adivinhar outra.
        $utilizador->forceFill([
            'password'  => Hash::make(self::PASSWORD),
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ])->save();

        // O ensaio de login realmente offline precisa do mesmo verificador
        // bcrypt que um operador define em "PIN de turno". Declarar o PIN na
        // bancada sem o gravar fazia os restantes testes passarem, mas um
        // aparelho sem sessão nunca conseguia autenticar-se.
        $utilizador->definirPinPos(self::PIN);

        // E um CAIXA sem direitos de gestão, para o ensaio do "esqueci o PIN":
        // é o gestor (o operador acima, com todas as permissões) que lhe
        // autoriza um PIN novo sem rede. Nunca entra com rede — só por PIN.
        $caixa = User::firstOrCreate(
            ['email' => self::EMAIL_CAIXA],
            [
                'name'      => 'Caixa da Bancada',
                'password'  => Hash::make(self::PASSWORD),
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]
        );
        $caixa->forceFill(['tenant_id' => $tenant->id, 'is_active' => true])->save();
        $caixa->definirPinPos(self::PIN_CAIXA);
        $caixa->tenants()->syncWithoutDetaching([$tenant->id => ['is_active' => true]]);

        $utilizador->tenants()->syncWithoutDetaching([$tenant->id => ['is_active' => true]]);
        setPermissionsTeamId($tenant->id);

        $this->darTodasAsPermissoes($utilizador);
        $this->ligarModulos($tenant);
        $this->assinatura($tenant);

        $armazem = Warehouse::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'BANC-01'],
            ['name' => 'Armazém da Bancada', 'is_active' => true, 'is_default' => true]
        );
        $armazem->setAsDefault();

        $imposto = Tax::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'BANC-IVA14'],
            ['name' => 'IVA 14%', 'rate' => 14, 'type' => 'iva', 'is_active' => true, 'is_default' => true]
        );

        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'default_warehouse_id' => $armazem->id,
                'default_tax_id'       => $imposto->id,
                'default_tax_rate'     => 14,
                // Sem isto o POS recusa vender o que não tem stock, e metade
                // dos ensaios morria antes de chegar ao que interessa.
                'pos_validate_stock'   => false,
            ]
        );

        // SÉRIES FISCAIS. Sem elas, uma fatura ou fatura-recibo criada no PWA
        // sobe e o servidor recusa-a com "Nenhuma série activa de homologação
        // para este tipo de documento" — um 500, e o documento fica na fila
        // para sempre. A proforma passava, porque não é documento fiscal e não
        // precisa de série; foi essa diferença que denunciou o que faltava.
        $this->call('series:create-defaults', ['--tenant' => $tenant->id]);

        $cliente = Client::firstOrCreate(
            ['tenant_id' => $tenant->id, 'nif' => '5000000098'],
            ['name' => 'Cliente da Bancada', 'type' => 'pessoa_juridica', 'is_active' => true]
        );

        $artigos = 0;
        foreach ([
            ['BANC-A', 'Água 1,5L', 500],
            ['BANC-B', 'Pão de forma', 850],
            ['BANC-C', 'Leite meio-gordo 1L', 1200],
            ['BANC-D', 'Arroz agulha 1kg', 2300],
            ['BANC-E', 'Óleo alimentar 900ml', 3100],
        ] as [$codigo, $nome, $preco]) {
            $artigo = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $codigo],
                [
                    'name'         => $nome,
                    'price'        => $preco,
                    'cost'         => round($preco * 0.7),
                    'tax_id'       => $imposto->id,
                    'tax_rate'     => 14,
                    'is_active'    => true,
                    'manage_stock' => true,
                ]
            );

            Stock::updateOrCreate(
                ['tenant_id' => $tenant->id, 'product_id' => $artigo->id, 'warehouse_id' => $armazem->id],
                ['quantity' => 500]
            );

            $artigos++;
        }

        $mesas = $this->montarORestaurante($tenant, $armazem, $imposto);
        $pessoas = $this->montarORh($tenant);

        $this->newLine();
        $this->info('Bancada do PWA montada.');
        $this->table(['', ''], [
            ['URL',      config('app.url') . '/invoicing/offline'],
            ['Empresa',  $tenant->name . ' (#' . $tenant->id . ')'],
            ['Email',    self::EMAIL],
            ['Password', self::PASSWORD],
            ['PIN',      self::PIN],
            ['Artigos',  $artigos],
            ['Mesas',    $mesas],
            ['Funcionários', $pessoas],
            ['Cliente',  $cliente->name],
            ['Armazém',  $armazem->name],
        ]);

        return self::SUCCESS;
    }

    /**
     * A sala do restaurante: estabelecimento, zona, mesas e pratos.
     *
     * Sem isto o POS de restaurante abre com a planta vazia, e um ensaio que
     * corre numa sala sem mesas não mede nada — passa por não haver nada que
     * possa falhar.
     *
     * Os pratos são artigos normais com categoria: é assim que o restaurante
     * funciona no sistema, e é assim que o POS offline os há-de encontrar.
     *
     * @return int quantas mesas ficaram montadas
     */
    private function montarORestaurante(Tenant $tenant, Warehouse $armazem, $imposto): int
    {
        \App\Models\Restaurant\RestaurantSettings::forTenant($tenant->id)->update([
            'default_warehouse_id' => $armazem->id,
            'use_kitchen_workflow' => true,
            // Exigir ficha técnica esconderia todos os pratos da bancada e o
            // ensaio morria num menu vazio, sem dizer porquê.
            'require_recipe_for_products' => false,
        ]);

        $sala = \App\Models\Restaurant\Venue::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'BANC-SALA'],
            ['name' => 'Salão da Bancada', 'warehouse_id' => $armazem->id, 'is_active' => true]
        );

        $zona = \App\Models\Restaurant\Area::firstOrCreate(
            ['tenant_id' => $tenant->id, 'venue_id' => $sala->id, 'name' => 'Esplanada'],
            ['sort_order' => 1, 'is_active' => true]
        );

        $mesas = 0;

        foreach (range(1, 6) as $n) {
            \App\Models\Restaurant\DiningTable::firstOrCreate(
                ['tenant_id' => $tenant->id, 'venue_id' => $sala->id, 'code' => 'MESA-' . $n],
                [
                    'area_id'  => $zona->id,
                    'name'     => 'Mesa ' . $n,
                    'capacity' => 4,
                    // As mesas voltam sempre a LIVRE ao montar a bancada. Uma
                    // corrida anterior deixa-as ocupadas, e o ensaio seguinte
                    // não teria onde sentar ninguém.
                    'status'   => 'available',
                    'is_active' => true,
                ]
            )->update(['status' => 'available']);

            $mesas++;
        }

        // As comandas da corrida anterior também saem: uma comanda aberta
        // ocupa a mesa outra vez assim que o servidor a devolve.
        \App\Models\Restaurant\Order::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', \App\Models\Restaurant\Order::OPEN_STATUSES)
            ->update(['status' => 'cancelled', 'closed_at' => now()]);

        $categoria = \App\Models\Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Cozinha'],
            ['is_active' => true, 'order' => 1]
        );

        foreach ([
            ['BANC-P1', 'Muamba de Galinha', 5500],
            ['BANC-P2', 'Calulu de Peixe', 6200],
            ['BANC-P3', 'Funge de Bombó', 1500],
            ['BANC-P4', 'Cerveja 33cl', 800],
        ] as [$codigo, $nome, $preco]) {
            Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $codigo],
                [
                    'category_id'  => $categoria->id,
                    'name'         => $nome,
                    'price'        => $preco,
                    'cost'         => round($preco * 0.4),
                    'tax_id'       => $imposto->id,
                    'tax_rate'     => 14,
                    'is_active'    => true,
                    // Um prato não desconta stock de si próprio: quem desconta
                    // é a ficha técnica, pelos ingredientes. Com gestão de
                    // stock ligada e zero em armazém, o sync escondia-os todos.
                    'manage_stock' => false,
                ]
            );
        }

        return $mesas;
    }

    /**
     * O RH da bancada: um departamento, um cargo, um turno e três pessoas.
     *
     * Sem gente, os ecrãs do RH abrem correctos e vazios — e um ensaio que
     * corre numa empresa sem funcionários não distingue «funciona» de «não há
     * nada para mostrar». O ponto e a folha precisam de alguém a quem marcar
     * a entrada e a quem pagar.
     *
     * Os salários são redondos e diferentes de propósito: um abaixo do limite
     * de isenção do IRT e dois acima, para que a folha da bancada tenha
     * imposto a zero numa linha e escalões diferentes nas outras.
     *
     * @return int quantas pessoas ficaram na casa
     */
    private function montarORh(Tenant $tenant): int
    {
        $departamento = \App\Models\HR\Department::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'BANC-OPS'],
            ['name' => 'Operações da Bancada', 'is_active' => true]
        );

        $cargo = \App\Models\HR\Position::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'BANC-OPER'],
            [
                'title' => 'Operador de Balcão',
                'department_id' => $departamento->id,
                'min_salary' => 100000,
                'max_salary' => 500000,
                'is_active' => true,
            ]
        );

        $turno = \App\Models\HR\Shift::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'BANC-DIA'],
            [
                'name' => 'Turno da Manhã',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'hours_per_day' => 8,
                // Segunda a sexta. É contra a hora de entrada deste turno que
                // o ponto mede o atraso.
                'work_days' => [1, 2, 3, 4, 5],
                'is_night_shift' => false,
                'is_active' => true,
            ]
        );

        $pessoas = 0;

        foreach ([
            ['BANC-001', 'Ana', 'Kiala', 120000],
            ['BANC-002', 'Bruno', 'Manuel', 260000],
            ['BANC-003', 'Célia', 'Domingos', 450000],
        ] as [$numero, $nome, $apelido, $salario]) {
            \App\Models\HR\Employee::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_number' => $numero],
                [
                    'first_name' => $nome,
                    'last_name' => $apelido,
                    'full_name' => $nome . ' ' . $apelido,
                    'department_id' => $departamento->id,
                    'position_id' => $cargo->id,
                    'shift_id' => $turno->id,
                    'hire_date' => now()->subYears(2)->startOfYear(),
                    'employment_type' => 'Contrato',
                    'status' => 'active',
                    'employment_status' => 'active',
                    // As duas colunas irmãs, escritas juntas — que é a regra
                    // que o `rh:alinhar-colunas-do-funcionario` veio impor.
                    'salary' => $salario,
                    'base_salary' => $salario,
                ]
            );

            $pessoas++;
        }

        $this->folhaAprovadaDeEnsaio($tenant);
        $this->pedidoPendenteDeEnsaio($tenant);

        return $pessoas;
    }

    /**
     * UM PEDIDO À ESPERA DE DECISÃO, para o painel ter um aviso.
     *
     * Os avisos do painel são o que faz alguém abri-lo, e cada um leva ao
     * ecrã onde se resolve. Um painel de bancada sem nada pendente não
     * distingue «os avisos funcionam» de «não há nada para avisar».
     *
     * Fica PENDENTE de propósito, e num ano de referência antigo para não se
     * confundir com o direito a férias deste ano.
     */
    private function pedidoPendenteDeEnsaio(Tenant $tenant): void
    {
        $pessoa = \App\Models\HR\Employee::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('employee_number', 'BANC-003')
            ->first();

        if (! $pessoa) {
            return;
        }

        \App\Models\HR\Vacation::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'vacation_number' => 'FE-BANCADA-01'],
            [
                'employee_id' => $pessoa->id,
                'reference_year' => 2019,
                'period_start' => '2019-01-01',
                'period_end' => '2019-12-31',
                'calculated_days' => 22,
                'entitled_days' => 22,
                'start_date' => '2019-08-01',
                'end_date' => '2019-08-15',
                'requested_days' => 15,
                'working_days' => 11,
                'status' => 'pending',
            ]
        );
    }

    /**
     * UMA FOLHA APROVADA E PARADA, de um mês antigo.
     *
     * O aviso do «marcar paga» — o passo que abate as prestações dos
     * adiantamentos e não se desfaz — só aparece numa folha aprovada. E uma
     * folha aprovada JÁ NÃO SE ELIMINA: um ensaio que a criasse e aprovasse
     * deixava-a para trás e não conseguia correr uma segunda vez.
     *
     * Por isso ela é da bancada, não do ensaio: Fevereiro de 2019, aprovada,
     * e nunca paga. O ensaio abre o aviso e cancela.
     */
    private function folhaAprovadaDeEnsaio(Tenant $tenant): void
    {
        $existente = \App\Models\HR\Payroll::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('year', 2019)->where('month', 2)
            ->first();

        $folha = $existente ?: app(\App\Services\HR\PayrollService::class)->createPayroll($tenant->id, 2019, 2);

        // Volta sempre a APROVADA: uma corrida anterior pode tê-la pago.
        $folha->forceFill(['status' => 'approved', 'approved_at' => now()])->save();
    }

    private function limpar(): int
    {
        $tenant = Tenant::where('slug', self::SLUG)->first();

        if (!$tenant) {
            $this->info('Não há bancada para limpar.');

            return self::SUCCESS;
        }

        // Sem softDeletes aqui: uma bancada meio-apagada dá ensaios que
        // dependem do lixo da corrida anterior.
        // O restaurante sai primeiro: as comandas apontam para artigos e mesas,
        // e apagar por outra ordem deixa chaves estrangeiras penduradas.
        \App\Models\Restaurant\OrderItem::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
        \App\Models\Restaurant\OrderEvent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
        \App\Models\Restaurant\Order::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
        \App\Models\Restaurant\DiningTable::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
        \App\Models\Restaurant\Area::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
        \App\Models\Restaurant\Venue::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
        \App\Models\Restaurant\RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();

        Stock::where('tenant_id', $tenant->id)->forceDelete();
        Product::where('tenant_id', $tenant->id)->forceDelete();
        Client::where('tenant_id', $tenant->id)->forceDelete();
        Warehouse::where('tenant_id', $tenant->id)->forceDelete();
        InvoicingSettings::where('tenant_id', $tenant->id)->delete();
        User::where('email', self::EMAIL)->forceDelete();
        User::where('email', self::EMAIL_CAIXA)->forceDelete();
        $tenant->forceDelete();

        $this->info('Bancada apagada.');

        return self::SUCCESS;
    }

    private function darTodasAsPermissoes(User $u): void
    {
        // O PWA toca em faturação, POS e clientes. Dar tudo é mais honesto do
        // que adivinhar a lista e ver o ensaio morrer num 403 daqui a um mês.
        $todas = \Spatie\Permission\Models\Permission::pluck('name')->all();

        if ($todas) {
            $u->syncPermissions($todas);
        }
    }

    private function ligarModulos(Tenant $tenant): void
    {
        // O restaurante entra na bancada: é ele que faz aparecer a entrada
        // das Mesas no PWA e que traz a sala na sincronização. Sem o módulo, o
        // ensaio do restaurante não teria como distinguir "não implementado"
        // de "não contratado".
        //
        // O RH entra pela mesma razão: os catálogos (departamentos, cargos,
        // turnos) já são React e os ensaios de browser abrem-nos.
        foreach (['invoicing', 'treasury', 'restaurant', 'rh'] as $slug) {
            $modulo = Module::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucfirst($slug), 'is_active' => true]
            );

            $tenant->modules()->syncWithoutDetaching([
                $modulo->id => ['is_active' => true, 'activated_at' => now(), 'trial_ends_at' => null],
            ]);
        }
    }

    private function assinatura(Tenant $tenant): void
    {
        $plano = Plan::where('slug', 'business')->first() ?: Plan::first();

        if (!$plano) {
            return;
        }

        // Sem subscrição activa o CheckSubscription manda tudo para
        // /subscription-expired e o PWA nem chega a carregar.
        $tenant->subscriptions()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id'              => $plano->id,
                'status'               => 'active',
                'current_period_start' => now()->subDay(),
                'current_period_end'   => now()->addYear(),
                'ends_at'              => now()->addYear(),
                'amount'               => $plano->price_monthly ?? 0,
                'billing_cycle'        => 'monthly',
            ]
        );
    }
}
