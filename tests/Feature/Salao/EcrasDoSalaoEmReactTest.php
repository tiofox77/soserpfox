<?php

namespace Tests\Feature\Salao;

use App\Models\Salon\Appointment;
use App\Models\Salon\Client as ClienteDeSalao;
use App\Models\Salon\Professional;
use App\Models\Salon\Service;
use App\Models\Salon\ServiceCategory;
use Tests\TenantTestCase;

/**
 * OS ECRÃS DO SALÃO EM REACT — as portas e as guardas.
 *
 * O QUE ESTE ENSAIO PRENDE é o que a migração destapou: os componentes em
 * Livewire não verificavam NADA por dentro. Quem chegasse à página de serviços
 * apagava um serviço; quem chegasse à dos profissionais despedia um. A rota
 * pedia `.view` e o botão de apagar ficava lá, a funcionar.
 *
 * Agora cada porta pede a permissão do que faz — ver, criar, editar, apagar são
 * quatro coisas diferentes —, e é isso que aqui se confirma, uma a uma.
 *
 * E confirma-se também que cada página MONTA O ECRÃ CERTO: um `data-ecra` fora
 * do registo não monta nada, e a página fica um buraco branco sem explicação.
 */
class EcrasDoSalaoEmReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/salao';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('salon');
    }

    /** As páginas e o ecrã que cada uma monta. */
    public static function paginas(): array
    {
        return [
            'painel' => ['/salon/dashboard', 'salao/painel', 'salon.dashboard.view'],
            'marcações' => ['/salon/appointments', 'salao/marcacoes', 'salon.appointments.view'],
            'serviços' => ['/salon/services', 'salao/servicos', 'salon.services.view'],
            'categorias' => ['/salon/services/categories', 'salao/servicos', 'salon.categories.view'],
            'profissionais' => ['/salon/professionals', 'salao/profissionais', 'salon.professionals.view'],
            'clientes' => ['/salon/clients', 'salao/clientes', 'salon.clients.view'],
            'tempos' => ['/salon/reports/time', 'salao/tempos', 'salon.reports.view'],
            'definições' => ['/salon/settings', 'salao/definicoes', 'salon.settings.view'],
        ];
    }

    /**
     * @dataProvider paginas
     */
    public function test_cada_pagina_monta_o_seu_ecra(string $morada, string $ecra, string $permissao): void
    {
        // Sem a permissão, a porta fecha-se.
        $this->get($morada)->assertForbidden();

        $this->comPermissoes($permissao);

        $this->get($morada)->assertOk()->assertSee($ecra, false);
    }

    /** O ecrã que cada página pede tem de estar no registo do React. */
    public function test_os_ecras_pedidos_existem_no_registo(): void
    {
        $registo = file_get_contents(resource_path('js/ecras/registo.ts'));

        foreach (self::paginas() as [$morada, $ecra]) {
            $this->assertStringContainsString("'{$ecra}':", $registo,
                "A página {$morada} pede o ecrã {$ecra}, que não está no registo — a página montaria um buraco branco.");
        }
    }

    /** E o ficheiro de cada ecrã existe mesmo — o registo aponta para algo. */
    public function test_os_ficheiros_dos_ecras_existem(): void
    {
        foreach ([
            'Painel', 'Marcacoes', 'Servicos', 'Profissionais', 'Clientes', 'Tempos', 'Definicoes',
        ] as $ficheiro) {
            $this->assertFileExists(resource_path("js/ecras/salao/{$ficheiro}.tsx"));
        }
    }

    /** O Livewire do salão foi-se — só a página pública de marcação ficou. */
    public function test_so_a_pagina_publica_continua_em_livewire(): void
    {
        $restantes = array_values(array_diff(
            scandir(app_path('Livewire/Salon')) ?: [],
            ['.', '..'],
        ));

        $this->assertSame(['SalonBookingOnline.php'], $restantes,
            'A página de marcação online é a única do salão sem sessão — e por isso a única que fica.');
    }

    /* ─── O painel ─────────────────────────────────────────────────────── */

    public function test_o_painel_exige_a_sua_permissao(): void
    {
        $this->getJson(self::RAIZ.'/painel')->assertForbidden();

        $this->comPermissoes('salon.dashboard.view');

        $this->getJson(self::RAIZ.'/painel')
            ->assertOk()
            ->assertJsonStructure([
                'dia',
                'resumo' => ['marcacoes', 'confirmadas', 'em_curso', 'faltas', 'receita_do_dia', 'duracao_media', 'espera_media'],
                'agenda', 'a_seguir', 'por_dia', 'por_estado', 'por_profissional', 'servicos',
            ]);
    }

    /**
     * O GRÁFICO DOS 30 DIAS TEM 30 DIAS.
     *
     * Os dias fechados vão a zero e não desaparecem: uma linha que salta a
     * segunda-feira encosta a receita do domingo à de terça.
     */
    public function test_o_painel_devolve_o_mes_inteiro(): void
    {
        $this->comPermissoes('salon.dashboard.view');

        $dados = $this->getJson(self::RAIZ.'/painel')->assertOk()->json();

        $this->assertCount(30, $dados['por_dia']['etiquetas']);
        $this->assertCount(30, $dados['por_dia']['valores']);
    }

    /** A agenda vem por profissional, mesmo quem tem o dia livre. */
    public function test_a_agenda_traz_todos_os_profissionais(): void
    {
        $this->comPermissoes('salon.dashboard.view');

        Professional::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Ana', 'is_active' => true,
        ]);
        Professional::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Berta', 'is_active' => true,
        ]);

        $agenda = $this->getJson(self::RAIZ.'/painel')->assertOk()->json('agenda');

        $this->assertCount(2, $agenda);
        $this->assertSame([], $agenda[0]['marcacoes']);
    }

    /* ─── Os serviços ──────────────────────────────────────────────────── */

    /**
     * VER NÃO É APAGAR.
     *
     * O ecrã em Livewire mostrava o caixote do lixo a quem só tinha a
     * permissão de ver — e ele funcionava.
     */
    public function test_os_servicos_separam_ver_de_mexer(): void
    {
        $this->comPermissoes('salon.services.view');

        $this->getJson(self::RAIZ.'/servicos')->assertOk();

        $this->postJson(self::RAIZ.'/servicos', [
            'name' => 'Corte', 'price' => 5000, 'duration' => 30,
        ])->assertForbidden();

        $servico = Service::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Corte', 'price' => 5000,
        ]);

        $this->putJson(self::RAIZ."/servicos/{$servico->id}", [
            'name' => 'Corte', 'price' => 6000, 'duration' => 30,
        ])->assertForbidden();

        $this->deleteJson(self::RAIZ."/servicos/{$servico->id}")->assertForbidden();
    }

    /**
     * A DURAÇÃO E A COMISSÃO GRAVAM-SE — e continuam lá depois de reler.
     *
     * O serviço do salão é um `invoicing_products` com os extras num JSON na
     * descrição. O método antigo relia o texto da descrição ANTIGA antes de
     * gravar, e a descrição que a pessoa acabara de escrever perdia-se sempre.
     */
    public function test_o_servico_guarda_a_duracao_a_comissao_e_a_descricao(): void
    {
        $this->comPermissoes('salon.services.view', 'salon.services.create', 'salon.services.edit');

        $categoria = ServiceCategory::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cabelo', 'slug' => 'cabelo-'.uniqid(),
        ]);

        $this->postJson(self::RAIZ.'/servicos', [
            'name' => 'Coloração',
            'price' => 15000,
            'cost' => 4000,
            'duration' => 90,
            'commission_percent' => 25,
            'category_id' => $categoria->id,
            'text_description' => 'Com produto da casa.',
            'is_active' => true,
            'online_booking' => true,
        ])->assertCreated();

        $lido = collect($this->getJson(self::RAIZ.'/servicos')->assertOk()->json('data'))
            ->firstWhere('nome', 'Coloração');

        $id = $lido['id'];

        $this->assertSame(90, (int) $lido['duracao']);
        $this->assertEqualsWithDelta(25, (float) $lido['comissao'], 0.01);
        $this->assertSame($categoria->id, $lido['category_id']);
        $this->assertSame('Com produto da casa.', $lido['descricao']);

        // E editar a descrição não perde a duração, nem o contrário.
        $this->putJson(self::RAIZ."/servicos/{$id}", [
            'name' => 'Coloração',
            'price' => 15000,
            'duration' => 90,
            'commission_percent' => 25,
            'category_id' => $categoria->id,
            'text_description' => 'Outra frase.',
        ])->assertOk();

        $lido = collect($this->getJson(self::RAIZ.'/servicos')->assertOk()->json('data'))
            ->firstWhere('id', $id);

        $this->assertSame('Outra frase.', $lido['descricao']);
        $this->assertSame(90, (int) $lido['duracao']);
    }

    /** Uma categoria com serviços não se apaga por engano. */
    public function test_a_categoria_com_servicos_nao_se_apaga(): void
    {
        $this->comPermissoes('salon.services.view', 'salon.categories.edit');

        $categoria = ServiceCategory::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cabelo', 'slug' => 'cabelo-'.uniqid(),
        ]);

        $servico = Service::create(['tenant_id' => $this->tenant->id, 'name' => 'Corte', 'price' => 5000]);
        $servico->updateSalonData(['category_id' => $categoria->id, 'duration' => 30]);

        $this->deleteJson(self::RAIZ."/servicos/categorias/{$categoria->id}")->assertStatus(422);

        $this->assertNotNull(ServiceCategory::withoutGlobalScopes()->find($categoria->id));
    }

    /* ─── Os profissionais ─────────────────────────────────────────────── */

    public function test_os_profissionais_separam_ver_de_mexer(): void
    {
        $this->comPermissoes('salon.professionals.view');

        $this->getJson(self::RAIZ.'/profissionais')->assertOk();

        $this->postJson(self::RAIZ.'/profissionais', ['name' => 'Ana'])->assertForbidden();

        $p = Professional::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Ana', 'is_active' => true,
        ]);

        $this->putJson(self::RAIZ."/profissionais/{$p->id}", ['name' => 'Ana Maria'])->assertForbidden();
        $this->postJson(self::RAIZ."/profissionais/{$p->id}/alternar")->assertForbidden();
        $this->deleteJson(self::RAIZ."/profissionais/{$p->id}")->assertForbidden();
    }

    /** O horário, os dias e os serviços que a pessoa faz gravam-se todos. */
    public function test_o_profissional_guarda_o_horario_os_dias_e_os_servicos(): void
    {
        $this->comPermissoes(
            'salon.professionals.view', 'salon.professionals.create', 'salon.professionals.edit',
        );

        $servico = Service::create(['tenant_id' => $this->tenant->id, 'name' => 'Corte', 'price' => 5000]);
        $servico->updateSalonData(['duration' => 30]);

        $id = (int) $this->postJson(self::RAIZ.'/profissionais', [
            'name' => 'Ana',
            'nickname' => 'Aninha',
            'email' => 'ana'.uniqid().'@exemplo.ao',
            'phone' => '923000000',
            'specialization' => 'Coloração',
            'level' => 'senior',
            'working_days' => [1, 2, 3, 4, 5],
            'work_start' => '09:00',
            'work_end' => '18:00',
            'lunch_start' => '13:00',
            'lunch_end' => '14:00',
            'commission_percent' => 30,
            'service_ids' => [$servico->id],
            'is_active' => true,
            'accepts_online_booking' => true,
        ])->assertCreated()->json('data.id');

        $lido = collect($this->getJson(self::RAIZ.'/profissionais')->assertOk()->json('data'))
            ->firstWhere('id', $id);

        $this->assertSame([1, 2, 3, 4, 5], $lido['dias']);
        $this->assertSame('09:00', $lido['entrada']);
        $this->assertSame('13:00', $lido['almoco_de']);
        $this->assertSame('senior', $lido['nivel']);
        $this->assertEqualsWithDelta(30, (float) $lido['comissao'], 0.01);
        $this->assertSame([$servico->id], $lido['service_ids']);
    }

    /**
     * QUEM TEM MARCAÇÕES POR ATENDER NÃO SE APAGA.
     *
     * Apagar a pessoa deixava as marcações dela sem dono e a agenda do dia com
     * buracos — e ninguém dava por isso até a cliente chegar.
     */
    public function test_o_profissional_com_marcacoes_por_atender_nao_se_apaga(): void
    {
        $this->comPermissoes('salon.professionals.view', 'salon.professionals.delete');

        $p = Professional::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Ana', 'is_active' => true,
        ]);

        $cliente = ClienteDeSalao::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Dona Ana',
            'type' => 'pessoa_fisica', 'is_active' => true,
        ]);

        Appointment::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $cliente->id,
            'professional_id' => $p->id, 'date' => today()->addDay(),
            'start_time' => '10:00', 'end_time' => '10:30',
            'status' => 'scheduled', 'subtotal' => 0, 'total' => 0,
        ]);

        $this->deleteJson(self::RAIZ."/profissionais/{$p->id}")->assertStatus(422);

        $this->assertNotNull(Professional::withoutGlobalScopes()->find($p->id));
    }

    /* ─── As clientes ──────────────────────────────────────────────────── */

    public function test_as_clientes_separam_ver_de_mexer(): void
    {
        $this->comPermissoes('salon.clients.view');

        $this->getJson(self::RAIZ.'/clientes')->assertOk();

        $this->postJson(self::RAIZ.'/clientes', ['name' => 'Dona Ana'])->assertForbidden();

        $c = ClienteDeSalao::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Dona Ana',
            'type' => 'pessoa_fisica', 'is_active' => true,
        ]);

        $this->putJson(self::RAIZ."/clientes/{$c->id}", ['name' => 'Ana'])->assertForbidden();
        $this->postJson(self::RAIZ."/clientes/{$c->id}/vip")->assertForbidden();
        $this->deleteJson(self::RAIZ."/clientes/{$c->id}")->assertForbidden();
    }

    /**
     * AS ALERGIAS GRAVAM-SE — e voltam a sair inteiras.
     *
     * Num salão, uma tinta no couro cabeludo de quem é alérgica é uma ida ao
     * hospital. Vivem num JSON dentro das notas do cliente da facturação, e é
     * fácil perdê-las ao gravar outro campo qualquer.
     */
    public function test_a_cliente_guarda_as_alergias_e_o_vip(): void
    {
        $this->comPermissoes('salon.clients.view', 'salon.clients.create', 'salon.clients.edit');

        $id = (int) $this->postJson(self::RAIZ.'/clientes', [
            'name' => 'Dona Ana',
            'phone' => '923000000',
            'allergies' => ['Amoníaco', 'Látex'],
            'is_vip' => true,
            'birth_date' => '1990-05-10',
        ])->assertCreated()->json('data.id');

        $ficha = $this->getJson(self::RAIZ."/clientes/{$id}")->assertOk()->json('data');

        $this->assertSame(['Amoníaco', 'Látex'], $ficha['alergias']);
        $this->assertTrue($ficha['vip']);

        // Mudar o telefone não apaga as alergias.
        $this->putJson(self::RAIZ."/clientes/{$id}", [
            'name' => 'Dona Ana', 'phone' => '923111111',
        ])->assertOk();

        $ficha = $this->getJson(self::RAIZ."/clientes/{$id}")->assertOk()->json('data');

        $this->assertSame(['Amoníaco', 'Látex'], $ficha['alergias']);
    }

    /** Uma cliente com marcações POR ATENDER não se apaga — a agenda ficava coxa. */
    public function test_a_cliente_com_marcacoes_por_atender_nao_se_apaga(): void
    {
        $this->comPermissoes('salon.clients.view', 'salon.clients.delete');

        $cliente = ClienteDeSalao::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Dona Ana',
            'type' => 'pessoa_fisica', 'is_active' => true,
        ]);

        $p = Professional::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Ana', 'is_active' => true,
        ]);

        Appointment::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $cliente->id,
            'professional_id' => $p->id, 'date' => today()->addDay(),
            'start_time' => '10:00', 'end_time' => '10:30',
            'status' => 'scheduled', 'subtotal' => 0, 'total' => 0,
        ]);

        $this->deleteJson(self::RAIZ."/clientes/{$cliente->id}")->assertStatus(422);
    }

    /* ─── Os tempos ────────────────────────────────────────────────────── */

    /**
     * O RELATÓRIO DE TEMPOS COMPARA O PREVISTO COM O REAL.
     *
     * É a única forma de saber se a agenda está bem montada: um serviço que o
     * catálogo diz durar 30 minutos e leva sempre 50 enche o dia de atrasos.
     */
    public function test_os_tempos_exigem_a_permissao_e_comparam_previsto_com_real(): void
    {
        $this->getJson(self::RAIZ.'/tempos')->assertForbidden();

        $this->comPermissoes('salon.reports.view');

        $cliente = ClienteDeSalao::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Dona Ana',
            'type' => 'pessoa_fisica', 'is_active' => true,
        ]);

        $p = Professional::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Ana', 'is_active' => true,
        ]);

        Appointment::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $cliente->id,
            'professional_id' => $p->id, 'date' => today(),
            'start_time' => '10:00', 'end_time' => '10:30',
            'total_duration' => 30,
            'status' => 'completed', 'subtotal' => 5000, 'total' => 5000,
            'arrived_at' => today()->setTime(9, 55),
            'started_at' => today()->setTime(10, 0),
            'completed_at' => today()->setTime(10, 50),
        ]);

        $r = $this->getJson(self::RAIZ.'/tempos')->assertOk()->json();

        $this->assertSame(1, $r['resumo']['atendimentos']);
        $this->assertSame(50, (int) $r['data'][0]['real']);
        $this->assertSame(30, (int) $r['data'][0]['previsto']);
        $this->assertSame(20, (int) $r['data'][0]['diferenca']);
        $this->assertSame(5, (int) $r['data'][0]['espera']);
        $this->assertSame(1, $r['resumo']['atrasados']);
    }

    /* ─── As definições ────────────────────────────────────────────────── */

    /**
     * VER AS REGRAS NÃO É MUDÁ-LAS.
     *
     * A rota pede `salon.settings.view`; alterar as horas de funcionamento ou a
     * política de cancelamento pede `salon.settings.edit`.
     */
    public function test_as_definicoes_separam_ver_de_alterar(): void
    {
        $this->getJson(self::RAIZ.'/definicoes')->assertForbidden();

        $this->comPermissoes('salon.settings.view');

        $this->getJson(self::RAIZ.'/definicoes')
            ->assertOk()
            ->assertJsonStructure(['regras' => ['opening_time', 'closing_time', 'working_days'], 'pagina', 'permissoes']);

        $this->putJson(self::RAIZ.'/definicoes', [
            'opening_time' => '08:00', 'closing_time' => '20:00', 'working_days' => [1, 2, 3],
        ])->assertForbidden();
    }

    /** E com a permissão de alterar, as regras gravam-se. */
    public function test_as_regras_gravam_se(): void
    {
        $this->comPermissoes('salon.settings.view', 'salon.settings.edit');

        $this->putJson(self::RAIZ.'/definicoes', [
            'opening_time' => '08:00',
            'closing_time' => '20:00',
            'working_days' => [1, 2, 3, 4, 5, 6],
            'slot_interval' => 15,
            'min_advance_booking_hours' => 2,
            'max_advance_booking_days' => 45,
            'cancellation_hours' => 12,
            'online_booking_enabled' => true,
            'require_confirmation' => true,
        ])->assertOk();

        $regras = $this->getJson(self::RAIZ.'/definicoes')->assertOk()->json('regras');

        $this->assertSame('08:00', $regras['opening_time']);
        $this->assertSame('20:00', $regras['closing_time']);
        $this->assertSame(15, (int) $regras['slot_interval']);
        $this->assertSame(45, (int) $regras['max_advance_booking_days']);
        $this->assertTrue($regras['require_confirmation']);
    }

    /* ─── O escopo de empresa ──────────────────────────────────────────── */

    /** Nada do salão de outra empresa aparece aqui — em nenhuma das listas. */
    public function test_nada_de_outra_empresa_aparece(): void
    {
        $this->comPermissoes(
            'salon.services.view', 'salon.professionals.view', 'salon.clients.view',
        );

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        Service::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Serviço alheio', 'price' => 1000,
            'type' => 'servico', 'module' => 'salon',
        ]);

        Professional::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Alheia', 'is_active' => true,
        ]);

        $this->getJson(self::RAIZ.'/servicos')->assertOk()->assertJsonMissing(['nome' => 'Serviço alheio']);
        $this->getJson(self::RAIZ.'/profissionais')->assertOk()->assertJsonMissing(['nome' => 'Alheia']);
    }
}
