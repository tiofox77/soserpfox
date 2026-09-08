<?php

namespace Tests\Feature\HR;

use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Position;
use App\Models\HR\Shift;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * OS CATÁLOGOS DO RH — departamentos, cargos e turnos — no ecrã genérico.
 *
 * O que estes ensaios guardam:
 *
 * 1. QUE HÁ GUARDA. O módulo de RH tinha 26 rotas com `auth` e mais nada:
 *    qualquer utilizador de uma empresa com o módulo activo entrava em tudo.
 *    Cada catálogo que passa para React ganha permissão por verbo, e é a
 *    primeira coisa que se prova.
 *
 * 2. QUE A HORA SOBREVIVE À VIAGEM. O modelo do turno converte `start_time`
 *    para Carbon; em JSON isso ia como data inteira, que o campo de hora não
 *    sabe ler — reabrir um turno mostrava a hora vazia e gravar apagava-a.
 *
 * 3. QUE OS DIAS SÃO UMA LISTA. `work_days` guarda 1..7; passá-los por
 *    `String()` dava «1,2,3» na caixa e um erro ao gravar.
 *
 * 4. AS GUARDAS DE APAGAR: um departamento com gente, um cargo ocupado, um
 *    turno com presenças marcadas não se apagam.
 */
class CatalogosDoRhTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/catalogos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('rh');
    }

    private function tudoDe(string $prefixo): void
    {
        $this->comPermissoes("$prefixo.view", "$prefixo.create", "$prefixo.edit", "$prefixo.delete");
    }

    private function funcionario(array $por = []): Employee
    {
        return Employee::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'F' . substr((string) microtime(true) * 10000, -8),
            'first_name' => 'Ana',
            'last_name' => 'Bento',
            'hire_date' => now()->subYear()->toDateString(),
        ], $por));
    }

    /* ─── A guarda que não existia ─────────────────────────────────────── */

    /**
     * SEM PERMISSÃO NÃO SE ENTRA — nem para ver.
     *
     * É o ensaio mais importante deste lote: antes disto, as três listas
     * abriam-se com um `auth` e mais nada.
     *
     * @test
     */
    public function sem_permissao_nao_se_ve_nem_se_escreve(): void
    {
        foreach (['departamentos', 'cargos', 'turnos'] as $catalogo) {
            $this->getJson(self::RAIZ . "/{$catalogo}/opcoes")->assertForbidden();
            $this->getJson(self::RAIZ . "/{$catalogo}")->assertForbidden();
            $this->postJson(self::RAIZ . "/{$catalogo}", ['name' => 'Sem direito'])->assertForbidden();
        }
    }

    /** Ver é uma permissão; escrever é outra. @test */
    public function quem_so_ve_nao_escreve(): void
    {
        $this->comPermissoes('hr.departments.view');

        $r = $this->getJson(self::RAIZ . '/departamentos/opcoes')->assertOk();

        $this->assertFalse($r->json('permissoes.pode_escrever'));
        $this->assertSame('Departamentos', $r->json('titulo'));

        $this->postJson(self::RAIZ . '/departamentos', ['name' => 'Recursos Humanos', 'code' => 'RH'])
            ->assertForbidden();
    }

    /* ─── Departamentos ────────────────────────────────────────────────── */

    /** @test */
    public function o_departamento_grava_e_o_codigo_e_unico_na_empresa(): void
    {
        $this->tudoDe('hr.departments');

        $this->postJson(self::RAIZ . '/departamentos', [
            'name' => 'Recursos Humanos', 'code' => 'RH', 'is_active' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('hr_departments', [
            'tenant_id' => $this->tenant->id, 'code' => 'RH', 'name' => 'Recursos Humanos',
        ]);

        $this->postJson(self::RAIZ . '/departamentos', ['name' => 'Outro qualquer', 'code' => 'RH'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    /**
     * O RESPONSÁVEL TEM DE SER DESTA EMPRESA.
     *
     * A tabela `users` não tem `tenant_id` — a pertença é pela tabela do meio.
     * Sem esta verificação, `nullable|integer` aceitava o id de um utilizador
     * de outra empresa e o departamento ficava a apontar para fora de casa.
     *
     * @test
     */
    public function o_responsavel_de_outra_empresa_nao_entra(): void
    {
        $this->tudoDe('hr.departments');

        $outra = Tenant::create(['name' => 'Outra Empresa', 'slug' => 'outra-' . uniqid(), 'is_active' => true]);
        $estranho = User::create([
            'name' => 'Chefe de Fora', 'email' => 'fora-' . uniqid() . '@exemplo.ao', 'password' => bcrypt('segredo'),
        ]);
        $estranho->tenants()->attach($outra->id, ['is_active' => true]);

        $this->postJson(self::RAIZ . '/departamentos', [
            'name' => 'Operações', 'code' => 'OPS', 'manager_id' => $estranho->id,
        ])->assertStatus(422)->assertJsonValidationErrors('manager_id');
    }

    /** Um departamento com gente lá dentro não se apaga. @test */
    public function o_departamento_com_funcionarios_nao_se_apaga(): void
    {
        $this->tudoDe('hr.departments');

        $id = $this->postJson(self::RAIZ . '/departamentos', ['name' => 'Operações', 'code' => 'OPS'])
            ->assertCreated()->json('data.id');

        $this->funcionario(['department_id' => $id]);

        $linha = collect($this->getJson(self::RAIZ . '/departamentos')->assertOk()->json('data'))
            ->firstWhere('id', $id);

        $this->assertFalse($linha['pode_apagar'], 'a lista tem de dizer que este não se apaga');

        $this->deleteJson(self::RAIZ . '/departamentos/' . $id)->assertStatus(422);
        $this->assertDatabaseHas('hr_departments', ['id' => $id]);
    }

    /* ─── Cargos ───────────────────────────────────────────────────────── */

    /**
     * A BANDA SALARIAL TEM DE FAZER SENTIDO.
     *
     * Um mínimo acima do máximo passava calado e ficava na ficha do cargo a
     * dizer uma coisa impossível.
     *
     * @test
     */
    public function o_cargo_recusa_uma_banda_salarial_invertida(): void
    {
        $this->tudoDe('hr.positions');

        $this->postJson(self::RAIZ . '/cargos', [
            'title' => 'Técnico', 'code' => 'TEC', 'min_salary' => 300000, 'max_salary' => 200000,
        ])->assertStatus(422)->assertJsonValidationErrors('max_salary');

        $this->postJson(self::RAIZ . '/cargos', [
            'title' => 'Técnico', 'code' => 'TEC', 'min_salary' => 200000, 'max_salary' => 300000,
        ])->assertCreated();
    }

    /** O departamento do cargo é confirmado contra esta empresa. @test */
    public function o_cargo_de_um_departamento_de_fora_nao_entra(): void
    {
        $this->tudoDe('hr.positions');

        $outra = Tenant::create(['name' => 'Outra', 'slug' => 'outra-' . uniqid(), 'is_active' => true]);
        $deFora = Department::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'De Fora', 'code' => 'FORA',
        ]);

        $this->postJson(self::RAIZ . '/cargos', [
            'title' => 'Chefe', 'code' => 'CHF', 'department_id' => $deFora->id,
        ])->assertStatus(422)->assertJsonValidationErrors('department_id');
    }

    /** Um cargo ocupado não se apaga. @test */
    public function o_cargo_ocupado_nao_se_apaga(): void
    {
        $this->tudoDe('hr.positions');

        $id = $this->postJson(self::RAIZ . '/cargos', ['title' => 'Motorista', 'code' => 'MOT'])
            ->assertCreated()->json('data.id');

        $this->funcionario(['position_id' => $id]);

        $this->deleteJson(self::RAIZ . '/cargos/' . $id)->assertStatus(422);
        $this->assertDatabaseHas('hr_positions', ['id' => $id]);
    }

    /* ─── Turnos ───────────────────────────────────────────────────────── */

    /**
     * A HORA E OS DIAS SOBREVIVEM À IDA E À VOLTA.
     *
     * Gravar `08:00` e receber de volta uma data inteira era o defeito que
     * fazia o campo de hora abrir vazio; gravar `[1,2,3,4,5]` e receber
     * `"1,2,3,4,5"` era o que fazia a lista de dias vir errada.
     *
     * @test
     */
    public function o_turno_grava_a_hora_e_os_dias_e_devolve_os_como_sao(): void
    {
        $this->tudoDe('hr.shifts');

        $r = $this->postJson(self::RAIZ . '/turnos', [
            'name' => 'Manhã',
            'code' => 'M1',
            'start_time' => '08:00',
            'end_time' => '16:30',
            'hours_per_day' => 8.5,
            'work_days' => [1, 2, 3, 4, 5, 6],
            'color' => '#22c55e',
            'is_night_shift' => false,
            'is_active' => true,
        ])->assertCreated();

        $this->assertSame('08:00', $r->json('data.start_time'), 'a hora volta como hora, não como data');
        $this->assertSame('16:30', $r->json('data.end_time'));
        $this->assertSame([1, 2, 3, 4, 5, 6], $r->json('data.work_days'));

        $turno = Shift::findOrFail($r->json('data.id'));

        $this->assertSame([1, 2, 3, 4, 5, 6], $turno->work_days);
        $this->assertEqualsWithDelta(8.5, (float) $turno->hours_per_day, 0.001);

        // E na LISTA, que é por onde o ecrã abre: a mesma forma.
        $linha = collect($this->getJson(self::RAIZ . '/turnos')->assertOk()->json('data'))
            ->firstWhere('id', $turno->id);

        $this->assertSame('08:00', $linha['start_time']);
        $this->assertSame([1, 2, 3, 4, 5, 6], $linha['work_days']);
    }

    /**
     * UM TURNO SEM DIA NENHUM NÃO É UM TURNO.
     *
     * Sem escolha, vale a semana de trabalho — de segunda a sexta.
     *
     * @test
     */
    public function um_turno_sem_dias_nasce_de_segunda_a_sexta(): void
    {
        $this->tudoDe('hr.shifts');

        $r = $this->postJson(self::RAIZ . '/turnos', [
            'name' => 'Tarde', 'start_time' => '14:00', 'end_time' => '22:00',
            'hours_per_day' => 8, 'work_days' => [],
        ])->assertCreated();

        $this->assertSame([1, 2, 3, 4, 5], $r->json('data.work_days'));
    }

    /** Um dia fora de 1..7 não entra. @test */
    public function um_dia_que_nao_existe_e_recusado(): void
    {
        $this->tudoDe('hr.shifts');

        $this->postJson(self::RAIZ . '/turnos', [
            'name' => 'Impossível', 'start_time' => '08:00', 'end_time' => '17:00',
            'hours_per_day' => 8, 'work_days' => [1, 9],
        ])->assertStatus(422)->assertJsonValidationErrors('work_days.1');
    }

    /** Uma hora que não é hora não entra. @test */
    public function uma_hora_mal_escrita_e_recusada(): void
    {
        $this->tudoDe('hr.shifts');

        $this->postJson(self::RAIZ . '/turnos', [
            'name' => 'Torto', 'start_time' => '8h da manhã', 'end_time' => '17:00', 'hours_per_day' => 8,
        ])->assertStatus(422)->assertJsonValidationErrors('start_time');
    }

    /**
     * UM TURNO COM PRESENÇAS MARCADAS NÃO SE APAGA.
     *
     * As presenças passadas ficariam a apontar para um horário que já não
     * existe, e o cálculo do atraso deixava de ter contra o que medir.
     *
     * @test
     */
    public function o_turno_com_presencas_nao_se_apaga(): void
    {
        $this->tudoDe('hr.shifts');

        $id = $this->postJson(self::RAIZ . '/turnos', [
            'name' => 'Noite', 'start_time' => '22:00', 'end_time' => '06:00',
            'hours_per_day' => 8, 'is_night_shift' => true,
        ])->assertCreated()->json('data.id');

        Attendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->funcionario()->id,
            'shift_id' => $id,
            'date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $this->deleteJson(self::RAIZ . '/turnos/' . $id)->assertStatus(422);
        $this->assertDatabaseHas('hr_shifts', ['id' => $id, 'deleted_at' => null]);
    }

    /** Um turno livre apaga-se. @test */
    public function um_turno_sem_ninguem_apaga_se(): void
    {
        $this->tudoDe('hr.shifts');

        $id = $this->postJson(self::RAIZ . '/turnos', [
            'name' => 'Extra', 'start_time' => '09:00', 'end_time' => '13:00', 'hours_per_day' => 4,
        ])->assertCreated()->json('data.id');

        $this->deleteJson(self::RAIZ . '/turnos/' . $id)->assertOk();
        $this->assertSoftDeleted('hr_shifts', ['id' => $id]);
    }

    /* ─── Atribuir em lote ─────────────────────────────────────────────── */

    /**
     * ATRIBUIR TRINTA PESSOAS DE UMA VEZ — e tirar quem sai.
     *
     * O que se grava é a lista COMPLETA de quem fica: quem lá estava e não
     * vem, sai. Mandar só os novos deixava sem maneira de tirar alguém do
     * turno sem ir à ficha dele, que é o trabalho que este modal poupa.
     *
     * @test
     */
    public function atribuir_em_lote_poe_quem_vem_e_tira_quem_ja_nao_vem(): void
    {
        $this->tudoDe('hr.shifts');

        $id = $this->postJson(self::RAIZ . '/turnos', [
            'name' => 'Manhã', 'start_time' => '08:00', 'end_time' => '16:00', 'hours_per_day' => 8,
        ])->assertCreated()->json('data.id');

        $ana = $this->funcionario(['first_name' => 'Ana', 'shift_id' => $id]);
        $bento = $this->funcionario(['first_name' => 'Bento']);
        $carla = $this->funcionario(['first_name' => 'Carla']);

        // A lista diz quem já lá está.
        $antes = collect($this->getJson(self::RAIZ . "/turnos/{$id}/atribuiveis")->assertOk()->json('data'));

        $this->assertTrue($antes->firstWhere('id', $ana->id)['atribuido']);
        $this->assertFalse($antes->firstWhere('id', $bento->id)['atribuido']);

        // Ficam o Bento e a Carla — a Ana sai, porque não vem na lista.
        $this->postJson(self::RAIZ . "/turnos/{$id}/atribuir", ['ids' => [$bento->id, $carla->id]])
            ->assertOk()
            ->assertJsonPath('quantos', 2);

        $this->assertNull($ana->fresh()->shift_id, 'quem não vem na lista sai');
        $this->assertSame($id, (int) $bento->fresh()->shift_id);
        $this->assertSame($id, (int) $carla->fresh()->shift_id);
    }

    /** Uma lista vazia esvazia o turno — e é uma escolha legítima. @test */
    public function atribuir_uma_lista_vazia_esvazia_o_turno(): void
    {
        $this->tudoDe('hr.shifts');

        $id = $this->postJson(self::RAIZ . '/turnos', [
            'name' => 'Tarde', 'start_time' => '14:00', 'end_time' => '22:00', 'hours_per_day' => 8,
        ])->assertCreated()->json('data.id');

        $quem = $this->funcionario(['shift_id' => $id]);

        $this->postJson(self::RAIZ . "/turnos/{$id}/atribuir", ['ids' => []])->assertOk();

        $this->assertNull($quem->fresh()->shift_id);
    }

    /**
     * UM FUNCIONÁRIO DE OUTRA EMPRESA NÃO ENTRA NO TURNO.
     *
     * Os ids vêm do pedido e não são prova de nada: confirmam-se contra esta
     * empresa antes de se escrever.
     *
     * @test
     */
    public function atribuir_ignora_quem_e_de_outra_empresa(): void
    {
        $this->tudoDe('hr.shifts');

        $id = $this->postJson(self::RAIZ . '/turnos', [
            'name' => 'Noite', 'start_time' => '22:00', 'end_time' => '06:00', 'hours_per_day' => 8,
        ])->assertCreated()->json('data.id');

        $outra = Tenant::create(['name' => 'Vizinha', 'slug' => 'viz-' . uniqid(), 'is_active' => true]);
        $deFora = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'employee_number' => 'X1',
            'first_name' => 'Fora', 'last_name' => 'Daqui', 'hire_date' => now()->toDateString(),
        ]);

        $this->postJson(self::RAIZ . "/turnos/{$id}/atribuir", ['ids' => [$deFora->id]])
            ->assertOk()
            ->assertJsonPath('quantos', 0);

        $this->assertNull($deFora->fresh()->shift_id);
    }

    /**
     * ATRIBUIR É ESCREVER: quem só vê consulta e não muda.
     *
     * O turno cria-se pelo modelo e não pela API — `comPermissoes` acrescenta
     * e nunca tira, e criá-lo pela porta obrigava a dar a permissão de
     * escrever que este ensaio existe para não ter.
     *
     * @test
     */
    public function quem_so_ve_nao_atribui(): void
    {
        $this->comPermissoes('hr.shifts.view');

        $turno = Shift::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Manhã',
            'start_time' => '08:00', 'end_time' => '16:00', 'hours_per_day' => 8,
        ]);

        $this->getJson(self::RAIZ . "/turnos/{$turno->id}/atribuiveis")->assertOk();
        $this->postJson(self::RAIZ . "/turnos/{$turno->id}/atribuir", ['ids' => []])->assertForbidden();
    }

    /** Onde o esquema não oferece atribuição, a porta é 404. @test */
    public function onde_nao_se_atribui_a_porta_nao_existe(): void
    {
        $this->tudoDe('hr.departments');

        $id = $this->postJson(self::RAIZ . '/departamentos', ['name' => 'Operações', 'code' => 'OPS'])
            ->assertCreated()->json('data.id');

        $this->getJson(self::RAIZ . "/departamentos/{$id}/atribuiveis")->assertNotFound();
        $this->postJson(self::RAIZ . "/departamentos/{$id}/atribuir", ['ids' => []])->assertNotFound();
    }

    /* ─── O que a empresa do lado não vê ───────────────────────────────── */

    /** Cada empresa vê os seus, e só os seus. @test */
    public function um_catalogo_de_outra_empresa_nao_aparece(): void
    {
        $this->tudoDe('hr.departments');

        $outra = Tenant::create(['name' => 'Vizinha', 'slug' => 'viz-' . uniqid(), 'is_active' => true]);
        $deFora = Department::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Departamento Vizinho', 'code' => 'VIZ',
        ]);

        $ids = collect($this->getJson(self::RAIZ . '/departamentos')->assertOk()->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($deFora->id));

        // E pela porta directa também não: 404, não 200 com os dados de fora.
        $this->putJson(self::RAIZ . '/departamentos/' . $deFora->id, ['name' => 'Roubado', 'code' => 'VIZ'])
            ->assertNotFound();
    }
}
