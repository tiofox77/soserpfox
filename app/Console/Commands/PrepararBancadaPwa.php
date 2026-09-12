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
        $ordens = $this->montarAOficina($tenant);
        $quartos = $this->montarOHotel($tenant);
        $eventos = $this->montarOsEventos($tenant);
        $negocios = $this->montarOCrm($tenant);
        $obras = $this->montarOsProjetos($tenant);

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
            ['Ordens da oficina', $ordens],
            ['Quartos do hotel', $quartos],
            ['Eventos', $eventos],
            ['Negócios do CRM', $negocios],
            ['Projetos', $obras],
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
     * A OFICINA DA BANCADA: mecânicos, viaturas, serviços e ordens.
     *
     * Um painel de oficina sem trabalho nenhum não distingue «os cartões
     * funcionam» de «não há nada para contar», e um mapa vazio passa por
     * qualquer coisa. Por isso a bancada monta o mínimo que faz os cinco mapas
     * e os quatro gráficos terem o que dizer:
     *
     *  · TRÊS VIATURAS, e uma delas com o seguro CADUCADO e a inspecção a
     *    caducar — é isso que o cartão dos documentos existe para mostrar;
     *  · DUAS ORDENS EM ABERTO, uma delas URGENTE, para o painel ter o aviso;
     *  · UMA ORDEM CONCLUÍDA E PAGA, com um serviço e uma peça, para haver
     *    receita, mão-de-obra e peças nos mapas.
     *
     * @return int quantas ordens ficaram montadas
     */
    private function montarAOficina(Tenant $tenant): int
    {
        $mecanicos = [];

        foreach ([
            ['Zeca Mota', '923100001', ['Motor', 'Mecânica Geral'], 'senior', 3500],
            ['Tó Chapa', '923100002', ['Chapa', 'Pintura'], 'pleno', 2500],
        ] as [$nome, $telefone, $especialidades, $nivel, $hora]) {
            $mecanicos[] = \App\Models\Workshop\Mechanic::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'phone' => $telefone],
                [
                    'name' => $nome,
                    'specialties' => $especialidades,
                    'level' => $nivel,
                    'hourly_rate' => $hora,
                    'daily_rate' => $hora * 8,
                    'is_active' => true,
                    'is_available' => true,
                ]
            );
        }

        $viaturas = [];

        foreach ([
            // Esta traz o seguro já caducado e a inspecção quase — é o caso
            // que o painel tem de saber mostrar.
            ['LD-42-11-AA', 'Toyota', 'Hilux', 2019, now()->subDays(12), now()->addDays(9), now()->addYear()],
            ['LD-77-08-BB', 'Nissan', 'Navara', 2021, now()->addMonths(8), now()->addMonths(5), now()->addMonths(11)],
            ['LD-05-63-CC', 'Hyundai', 'H100', 2016, now()->addYear(), now()->addYear(), now()->addYear()],
        ] as [$matricula, $marca, $modelo, $ano, $seguro, $inspeccao, $livrete]) {
            $viaturas[] = \App\Models\Workshop\Vehicle::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'plate' => $matricula],
                [
                    'vehicle_number' => 'VEH-' . substr($matricula, 3, 5),
                    'owner_name' => 'Dono do ' . $modelo,
                    'owner_phone' => '923200' . random_int(100, 999),
                    'brand' => $marca,
                    'model' => $modelo,
                    'year' => $ano,
                    'fuel_type' => 'Diesel',
                    'mileage' => random_int(40000, 180000),
                    'insurance_expiry' => $seguro,
                    'inspection_expiry' => $inspeccao,
                    'registration_expiry' => $livrete,
                    'status' => 'active',
                ]
            );
        }

        $servicos = [];

        foreach ([
            ['SRV-BANC-01', 'Mudança de óleo e filtros', 'Manutenção', 15000, 1.5],
            ['SRV-BANC-02', 'Substituição de pastilhas', 'Reparação', 22000, 2],
        ] as [$codigo, $nome, $categoria, $custo, $horas]) {
            $servicos[] = \App\Models\Workshop\Service::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'service_code' => $codigo],
                [
                    'name' => $nome,
                    'category' => $categoria,
                    'labor_cost' => $custo,
                    'estimated_hours' => $horas,
                    'is_active' => true,
                ]
            );
        }

        $ordens = [
            // [nº, viatura, mecânico, estado, prioridade, dias atrás]
            ['OS-BANC-01', 0, 0, 'in_progress', 'urgent', 2],
            ['OS-BANC-02', 1, 1, 'pending', 'normal', 1],
            ['OS-BANC-03', 2, 0, 'completed', 'normal', 6],
        ];

        $quantas = 0;

        foreach ($ordens as [$numero, $iv, $im, $estado, $prioridade, $dias]) {
            $concluida = $estado === 'completed';

            $ordem = \App\Models\Workshop\WorkOrder::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'order_number' => $numero],
                [
                    'vehicle_id' => $viaturas[$iv]->id,
                    'mechanic_id' => $mecanicos[$im]->id,
                    'received_at' => now()->subDays($dias),
                    'completed_at' => $concluida ? now()->subDays($dias - 1) : null,
                    'problem_description' => 'Ruído na travagem e revisão dos 60.000 km.',
                    'status' => $estado,
                    'priority' => $prioridade,
                    'labor_total' => $concluida ? 15000 : 0,
                    'parts_total' => $concluida ? 8500 : 0,
                    'total' => $concluida ? 23500 : 0,
                    'payment_status' => $concluida ? 'paid' : 'pending',
                    'paid_amount' => $concluida ? 23500 : 0,
                    'warranty_days' => 30,
                ]
            );

            if ($concluida && $ordem->items()->count() === 0) {
                \App\Models\Workshop\WorkOrderItem::create([
                    'work_order_id' => $ordem->id,
                    'service_id' => $servicos[0]->id,
                    'mechanic_id' => $mecanicos[$im]->id,
                    'type' => 'service',
                    'name' => $servicos[0]->name,
                    'quantity' => 1,
                    'unit_price' => 15000,
                    'subtotal' => 15000,
                    'hours' => 1.5,
                ]);

                \App\Models\Workshop\WorkOrderItem::create([
                    'work_order_id' => $ordem->id,
                    'type' => 'part',
                    'name' => 'Filtro de óleo',
                    'quantity' => 1,
                    'unit_price' => 8500,
                    'subtotal' => 8500,
                ]);
            }

            $quantas++;
        }

        return $quantas;
    }

    /**
     * O HOTEL DA BANCADA: tipos de quarto, quartos e pessoal.
     *
     * Um mapa de ocupação sem quartos não distingue «funciona» de «não há
     * nada»; e uma lista de quartos com todos no mesmo estado não mostra o que
     * as cores do ecrã existem para mostrar. Por isso os seis quartos ficam em
     * estados diferentes, e um deles SUJO — que é o caso que separa «pode
     * entrar alguém» de «pode vender-se».
     *
     * @return int quantos quartos ficaram montados
     */
    private function montarOHotel(Tenant $tenant): int
    {
        $tipos = [];

        foreach ([
            ['SGL', 'Individual', 18000, 22000, 1],
            ['DBL', 'Duplo', 28000, 34000, 2],
            ['STE', 'Suite', 55000, 68000, 4],
        ] as [$codigo, $nome, $preco, $fimDeSemana, $pessoas]) {
            $tipos[$codigo] = \App\Models\Hotel\RoomType::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $codigo],
                [
                    'name' => $nome,
                    'description' => 'Quarto ' . mb_strtolower($nome) . ' da bancada de ensaio.',
                    'base_price' => $preco,
                    'weekend_price' => $fimDeSemana,
                    'capacity' => $pessoas,
                    'extra_bed_capacity' => $pessoas > 1 ? 1 : 0,
                    'extra_bed_price' => 8000,
                    'amenities' => ['wifi', 'ac', 'tv', 'safe'],
                    'is_active' => true,
                ]
            );
        }

        $quartos = 0;

        foreach ([
            ['101', '1', 'SGL', 'available', 'clean'],
            ['102', '1', 'SGL', 'occupied', 'dirty'],
            ['103', '1', 'DBL', 'available', 'dirty'],
            ['201', '2', 'DBL', 'reserved', 'clean'],
            ['202', '2', 'STE', 'available', 'clean'],
            ['203', '2', 'STE', 'maintenance', 'out_of_order'],
        ] as [$numero, $piso, $tipo, $estado, $limpeza]) {
            \App\Models\Hotel\Room::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'number' => $numero],
                [
                    'room_type_id' => $tipos[$tipo]->id,
                    'floor' => $piso,
                    'status' => $estado,
                    'housekeeping_status' => $limpeza,
                    'features' => $tipo === 'STE' ? ['balcony', 'sea_view', 'bathtub'] : [],
                    'is_active' => true,
                ]
            );

            $quartos++;
        }

        $pessoal = [];

        foreach ([
            ['Marta Sebastião', 'receptionist', 'front_desk', '923400001'],
            ['Joana Kituxi', 'housekeeper', 'housekeeping', '923400002'],
            ['Paulo Neto', 'maintenance', 'maintenance', '923400003'],
        ] as [$nome, $funcao, $area, $telefone]) {
            $pessoal[$funcao] = \App\Models\Hotel\Staff::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'phone' => $telefone],
                [
                    'name' => $nome,
                    'position' => $funcao,
                    'department' => $area,
                    'working_days' => [1, 2, 3, 4, 5, 6],
                    'work_start' => '08:00',
                    'work_end' => '17:00',
                    'monthly_salary' => 180000,
                    'is_active' => true,
                ]
            );
        }

        /*
         * AS ORDENS DE MANUTENÇÃO — uma por coluna do quadro.
         *
         * Um quadro com tudo na mesma coluna não distingue «funciona» de «não
         * há nada»; e sem uma urgente não se vê o que a cor existe para
         * mostrar.
         */
        $porQuarto = \App\Models\Hotel\Room::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->pluck('id', 'number');

        foreach ([
            ['Torneira do 103 a pingar', 'plumbing', 'urgent', 'pending', '103'],
            ['Ar condicionado do 201 não arrefece', 'hvac', 'high', 'in_progress', '201'],
            ['Fechadura do 203 emperrada', 'other', 'normal', 'waiting_parts', '203'],
            ['Lâmpada do corredor do piso 1', 'electrical', 'low', 'completed', null],
        ] as [$titulo, $categoria, $prioridade, $estado, $quarto]) {
            \App\Models\Hotel\MaintenanceOrder::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'title' => $titulo],
                [
                    'room_id' => $quarto ? ($porQuarto[$quarto] ?? null) : null,
                    'reported_by' => $pessoal['receptionist']->id ?? null,
                    'assigned_to' => $estado === 'pending' ? null : ($pessoal['maintenance']->id ?? null),
                    'type' => 'corrective',
                    'priority' => $prioridade,
                    'category' => $categoria,
                    'status' => $estado,
                    'location' => $quarto ? __('Quarto :n', ['n' => $quarto]) : 'Corredor',
                    'estimated_cost' => 15000,
                    'estimated_time' => 60,
                    'started_at' => in_array($estado, ['in_progress', 'completed'], true) ? now()->subHours(3) : null,
                    'completed_at' => $estado === 'completed' ? now()->subHours(2) : null,
                    'resolution' => $estado === 'completed' ? 'Lâmpada substituída.' : null,
                    'cost' => $estado === 'completed' ? 3500 : null,
                ]
            );
        }

        /*
         * A CASA COMO ELA SE APRESENTA — e a página pública.
         *
         * Sem definições não há endereço de reservas, e a página que um
         * hóspede abriria do cartaz responde 404: a bancada não conseguia
         * ensaiar o caminho por onde entram as reservas de fora.
         */
        \App\Models\Hotel\HotelSettings::withoutGlobalScopes()->firstOrCreate(
            ["tenant_id" => $tenant->id],
            [
                "hotel_name" => "Hotel da Bancada",
                "hotel_description" => "A casa de ensaio, à beira da baía.",
                "hotel_city" => "Luanda",
                "hotel_country" => "Angola",
                "hotel_phone" => "923400000",
                "hotel_whatsapp" => "923400000",
                "hotel_email" => "reservas@bancada.local",
                "star_rating" => 4,
                "primary_color" => "#0f766e",
                "secondary_color" => "#0891b2",
                "booking_slug" => "hotel-da-bancada",
                "online_booking_enabled" => true,
                "welcome_message" => "Bem-vindo à casa de ensaio.",
                "amenities_list" => ["wifi", "parking", "pool", "restaurant", "ac", "breakfast"],
                "min_advance_booking_hours" => 0,
                "max_advance_booking_days" => 365,
                "require_deposit" => true,
                "deposit_percent" => 30,
            ]
        );

        /*
         * AS RESERVAS — uma por estado, e uma a atravessar o mês.
         *
         * Sem elas o calendário, a lista, o painel, os mapas e o check-out
         * abrem todos vazios, e um ensaio que corre num hotel sem hóspedes
         * não distingue «funciona» de «não há nada».
         *
         * O HÓSPEDE É UM CLIENTE e não a ficha antiga: é o adquirente da
         * factura do check-out, e era isso que faltava às reservas que o
         * balcão e o calendário criavam.
         */
        $hospedes = [];

        foreach ([
            ['Aurora Kiala', '923500001', 'aurora.kiala@exemplo.ao'],
            ['Bento Mavungo', '923500002', 'bento.mavungo@exemplo.ao'],
            ['Carla Dias', '923500003', 'carla.dias@exemplo.ao'],
        ] as $i => [$nome, $telefone, $email]) {
            $hospedes[$i] = \App\Models\Client::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'phone' => $telefone],
                [
                    'name' => $nome,
                    'email' => $email,
                    'type' => 'pessoa_fisica',
                    'nationality' => 'Angola',
                    'country' => \App\Support\Geografia::PAIS_PADRAO,
                    'is_active' => true,
                ]
            );
        }

        $tipoPorQuarto = \App\Models\Hotel\Room::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->pluck('room_type_id', 'number');

        foreach ([
            ['102', 0, 'checked_in', today()->subDay(), today()->addDays(2), 22000],
            ['201', 1, 'confirmed', today()->addDays(2), today()->addDays(5), 28000],
            ['202', 2, 'pending', today()->addDays(6), today()->addDays(9), 55000],
        ] as [$quarto, $quem, $estado, $entrada, $saida, $taxa]) {
            \App\Models\Hotel\Reservation::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'reservation_number' => 'BANC-' . $quarto],
                [
                    'client_id' => $hospedes[$quem]->id,
                    'room_id' => $porQuarto[$quarto] ?? null,
                    'room_type_id' => $tipoPorQuarto[$quarto] ?? null,
                    'check_in_date' => $entrada,
                    'check_out_date' => $saida,
                    'adults' => 2,
                    'children' => 0,
                    'extra_beds' => 0,
                    'room_rate' => $taxa,
                    'status' => $estado,
                    'source' => 'direct',
                    'paid_amount' => $estado === 'checked_in' ? $taxa : 0,
                    'created_by' => \App\Models\User::where('tenant_id', $tenant->id)->value('id'),
                ]
            );
        }

        /*
         * AS TAREFAS DE LIMPEZA — uma por coluna do quadro, e a de hoje.
         *
         * Como na manutenção: um quadro com tudo na mesma coluna não distingue
         * «funciona» de «não há nada». A de PROBLEMA é a que mais importa
         * estar cá — era a coluna que o ecrã em Blade nunca conseguia encher.
         *
         * QUEM LIMPA É UM UTILIZADOR e não uma ficha de pessoal do hotel: é a
         * chave estrangeira desta tabela, ao contrário da manutenção.
         */
        $quemLimpa = \App\Models\User::where('tenant_id', $tenant->id)->orderBy('id')->value('id');

        foreach ([
            ['102', 'checkout_clean', 'urgent', 'pending'],
            ['103', 'stay_clean', 'normal', 'in_progress'],
            ['201', 'turndown', 'low', 'completed'],
            ['202', 'inspection', 'normal', 'verified'],
            ['203', 'deep_clean', 'high', 'issue'],
        ] as [$quarto, $tipo, $prioridade, $estado]) {
            \App\Models\Hotel\HousekeepingTask::withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'room_id' => $porQuarto[$quarto] ?? null,
                    'scheduled_date' => today(),
                ],
                [
                    'task_type' => $tipo,
                    'priority' => $prioridade,
                    'status' => $estado,
                    'assigned_to' => $estado === 'pending' ? null : $quemLimpa,
                    'estimated_duration' => 45,
                    'started_at' => $estado === 'pending' ? null : now()->subHours(2),
                    'completed_at' => in_array($estado, ['completed', 'verified'], true) ? now()->subHour() : null,
                    'verified_at' => $estado === 'verified' ? now()->subMinutes(30) : null,
                    'verified_by' => $estado === 'verified' ? $quemLimpa : null,
                    'actual_duration' => in_array($estado, ['completed', 'verified'], true) ? 52 : null,
                    'issues' => $estado === 'issue' ? 'Chuveiro partido — o quarto ficou fora de serviço.' : null,
                ]
            );
        }

        return $quartos;
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

    /**
     * Os eventos: um tipo, um local, um técnico, equipamento e um evento.
     *
     * O ensaio do browser abre as moradas todas do módulo, e uma lista vazia
     * não distingue «não implementado» de «ainda não há nada»: um calendário
     * sem eventos desenha-se exactamente igual quer a consulta funcione quer
     * não.
     *
     * @return int quantos eventos ficaram montados
     */
    private function montarOsEventos(Tenant $tenant): int
    {
        $tipo = \App\Models\Events\EventType::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Conferência'],
            ['icon' => '🎤', 'color' => '#8b5cf6', 'order' => 1, 'is_active' => true],
        );

        $local = \App\Models\Events\Venue::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Salão da Bancada'],
            [
                'address' => 'Luanda, Angola', 'city' => 'Luanda',
                'phone' => '923000001', 'contact_person' => 'Sr. Bancada',
                'capacity' => 400, 'is_active' => true,
            ],
        );

        \App\Models\Events\Technician::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Técnico da Bancada'],
            [
                'phone' => '923000002', 'email' => 'tecnico@bancada.local',
                'specialties' => ['audio', 'streaming'], 'level' => 'senior',
                'hourly_rate' => 2500, 'daily_rate' => 18000,
                'is_active' => true, 'is_available' => true,
            ],
        );

        $categoria = \App\Models\EquipmentCategory::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Som da Bancada'],
            ['icon' => '🔊', 'color' => '#6366f1', 'sort_order' => 1, 'is_active' => true],
        );

        \App\Models\Equipment::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Mesa de som da bancada'],
            [
                'category_id' => $categoria->id, 'serial_number' => 'BANCADA-SOM-01',
                'location' => 'Armazém da bancada', 'status' => 'disponivel',
                'purchase_price' => 450000, 'current_value' => 300000, 'is_active' => true,
            ],
        );

        $evento = \App\Models\Events\Event::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Conferência da Bancada'],
            [
                'type_id' => $tipo->id,
                'venue_id' => $local->id,
                /*
                 * A MEIO DO MÊS, e de propósito: um evento no dia 1 ou no 31
                 * cai numa semana que a grelha também pinta com os dias do mês
                 * ao lado, e um ensaio que o procure no calendário do mês
                 * certo pode não o encontrar onde espera.
                 */
                'start_date' => now()->startOfMonth()->addDays(14)->setTime(9, 0),
                'end_date' => now()->startOfMonth()->addDays(14)->setTime(18, 0),
                'expected_attendees' => 200,
                'total_value' => 850000,
                'status' => 'confirmado',
                'phase' => 'planejamento',
            ],
        );

        if ($evento->checklists()->count() === 0) {
            $evento->createDefaultChecklistForPhase('planejamento');
            $evento->updateChecklistProgress();
        }

        return \App\Models\Events\Event::where('tenant_id', $tenant->id)->count();
    }

    /**
     * O CRM: um lead na fila e um negócio no funil.
     *
     * As ETAPAS nascem sozinhas à primeira pergunta — é o padrão da casa, e
     * este montador limita-se a fazer essa pergunta.
     *
     * @return int quantos negócios ficaram no funil
     */
    private function montarOCrm(Tenant $tenant): int
    {
        \App\Models\CRM\Lead::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Lead da Bancada'],
            [
                'phone' => '923000003', 'company' => 'Obras da Bancada, Lda.',
                'source' => 'telefone', 'status' => 'novo',
            ],
        );

        $etapa = \App\Models\CRM\Stage::doTenant($tenant->id)->first();

        \App\Models\CRM\Opportunity::firstOrCreate(
            ['tenant_id' => $tenant->id, 'title' => 'Negócio da Bancada'],
            [
                'stage_id' => $etapa?->id,
                'amount' => 1200000,
                'probability' => (int) ($etapa?->probability ?? 10),
                'status' => 'open',
            ],
        );

        return \App\Models\CRM\Opportunity::where('tenant_id', $tenant->id)->count();
    }

    /**
     * Os projetos: um projeto activo, uma tarefa e horas lançadas.
     *
     * COM HORAS POR FACTURAR, de propósito: é o número que o painel existe
     * para dar, e um painel a zeros não distingue «não há» de «não conta».
     *
     * @return int quantos projetos ficaram montados
     */
    private function montarOsProjetos(Tenant $tenant): int
    {
        $cliente = Client::where('tenant_id', $tenant->id)->first();

        $projeto = \App\Models\Projetos\Projeto::firstOrCreate(
            ['tenant_id' => $tenant->id, 'nome' => 'Obra da Bancada'],
            [
                'client_id' => $cliente?->id,
                'estado' => 'activo',
                'data_inicio' => now()->subMonth()->toDateString(),
                'data_fim_prevista' => now()->addMonths(2)->toDateString(),
                'orcamento' => 800000,
                'valor_hora' => 6000,
                'descricao' => 'O projeto de ensaio da bancada.',
                // `created_by` é NOT NULL sem valor por omissão, aqui e na tarefa.
                'created_by' => User::where('email', self::EMAIL)->value('id'),
            ],
        );

        $tarefa = \App\Models\Projetos\Tarefa::firstOrCreate(
            ['tenant_id' => $tenant->id, 'projeto_id' => $projeto->id, 'titulo' => 'Levantamento na bancada'],
            [
                'estado' => 'em_curso', 'prioridade' => 'alta',
                'prazo' => now()->addWeek()->toDateString(), 'ordem' => 1,
                // `created_by` é NOT NULL sem valor por omissão.
                'created_by' => User::where('email', self::EMAIL)->value('id'),
            ],
        );

        \App\Models\Projetos\HoraLancada::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'projeto_id' => $projeto->id,
                'user_id' => User::where('email', self::EMAIL)->value('id'),
                'data' => today()->toDateString(),
            ],
            [
                'tarefa_id' => $tarefa->id,
                'horas' => 6,
                'descricao' => 'Trabalho de ensaio',
                'facturavel' => true,
                'valor_hora' => 6000,
            ],
        );

        return \App\Models\Projetos\Projeto::where('tenant_id', $tenant->id)->count();
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
        // turnos) já são React e os ensaios de browser abrem-nos. E a OFICINA,
        // desde que as viaturas, os mecânicos e os serviços passaram para o
        // mesmo ecrã genérico. E os EVENTOS, que passaram a React inteiros: a
        // agenda, os equipamentos, os locais, os tipos e os técnicos.
        foreach (['invoicing', 'treasury', 'restaurant', 'rh', 'oficina', 'eventos', 'crm', 'projetos'] as $slug) {
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
