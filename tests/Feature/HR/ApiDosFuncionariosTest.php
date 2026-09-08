<?php

namespace Tests\Feature\HR;

use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Position;
use App\Models\HR\Shift;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * A FICHA DO FUNCIONÁRIO, na API que o ecrã em React usa.
 *
 * O que estes ensaios guardam:
 *
 * 1. QUE HÁ GUARDA POR VERBO. Ver a lista é uma coisa; mexer no salário de
 *    alguém é outra. Antes disto, a rota `/hr/employees` corria com `auth` e
 *    mais nada.
 *
 * 2. QUE AS COLUNAS A DOBRAR NÃO DIVERGEM. `salary`/`base_salary`,
 *    `status`/`employment_status`, `transport_allowance`/`transport_benefit`,
 *    `meal_allowance`/`food_benefit`: o formulário em Blade gravava numas e o
 *    cálculo lia as outras. Aqui escrevem-se as duas.
 *
 * 3. QUE AS ESCOLHAS SÃO DESTA EMPRESA. Um `exists:` simples aceitava o
 *    departamento, o cargo ou o turno de outra.
 *
 * 4. QUE OS CAMPOS QUE O ECRÃ DE SEMPRE NÃO OFERECIA agora gravam: turno,
 *    chefia, cessação, os três subsídios que a folha calcula, e os
 *    beneficiários.
 */
class ApiDosFuncionariosTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/rh/funcionarios';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('rh');
    }

    private function tudo(): void
    {
        $this->comPermissoes('employees.view', 'employees.create', 'employees.edit', 'employees.delete');
    }

    /** @return array<string, mixed> */
    private function corpo(array $por = []): array
    {
        return array_merge([
            'first_name' => 'Ana',
            'last_name' => 'Bento',
            'employment_type' => 'Contrato',
            'status' => 'active',
        ], $por);
    }

    private function funcionario(array $por = []): Employee
    {
        return Employee::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'EMP-' . substr((string) (microtime(true) * 10000), -7),
            'first_name' => 'Carlos',
            'last_name' => 'Dias',
            'hire_date' => now()->subYear()->toDateString(),
            'status' => 'active',
        ], $por));
    }

    /* ─── A guarda que não existia ─────────────────────────────────────── */

    /** @test */
    public function sem_permissao_nao_se_ve_nem_se_escreve(): void
    {
        $this->getJson(self::RAIZ)->assertForbidden();
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
    }

    /**
     * VER A LISTA NÃO DÁ DIREITO A MEXER NO SALÁRIO DE NINGUÉM.
     *
     * São quatro permissões e não uma: é a diferença entre a recepcionista
     * que precisa do contacto de um colega e quem decide quanto se paga.
     *
     * @test
     */
    public function quem_so_ve_nao_escreve_nem_apaga(): void
    {
        $this->comPermissoes('employees.view');

        $e = $this->funcionario();

        $this->getJson(self::RAIZ)->assertOk();
        $this->getJson(self::RAIZ . '/' . $e->id)->assertOk();

        $r = $this->getJson(self::RAIZ . '/opcoes')->assertOk();
        $this->assertFalse($r->json('permissoes.pode_criar'));
        $this->assertFalse($r->json('permissoes.pode_editar'));
        $this->assertFalse($r->json('permissoes.pode_apagar'));

        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
        $this->putJson(self::RAIZ . '/' . $e->id, $this->corpo())->assertForbidden();
        $this->deleteJson(self::RAIZ . '/' . $e->id)->assertForbidden();
    }

    /* ─── Gravar ───────────────────────────────────────────────────────── */

    /** @test */
    public function criar_gera_o_numero_e_guarda_a_ficha(): void
    {
        $this->tudo();

        $r = $this->postJson(self::RAIZ, $this->corpo([
            'email' => 'ana@exemplo.ao',
            'nif' => '005000000LA042',
            'hire_date' => '2024-03-01',
        ]))->assertCreated();

        $this->assertStringStartsWith('EMP-', $r->json('documento.numero'));

        $this->assertDatabaseHas('hr_employees', [
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Ana',
            'last_name' => 'Bento',
            'email' => 'ana@exemplo.ao',
        ]);
    }

    /** Sem nome não há ficha, e diz-se em que campo. @test */
    public function sem_nome_nao_ha_ficha(): void
    {
        $this->tudo();

        $this->postJson(self::RAIZ, $this->corpo(['first_name' => '', 'last_name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name']);
    }

    /**
     * AS QUATRO COLUNAS A DOBRAR ESCREVEM-SE JUNTAS.
     *
     * É o defeito que fazia a folha do mês sair pelo salário antigo depois de
     * um aumento: o formulário gravava `salary`, o cálculo lia `base_salary`.
     *
     * @test
     */
    public function as_colunas_a_dobrar_ficam_sempre_de_acordo(): void
    {
        $this->tudo();

        $id = $this->postJson(self::RAIZ, $this->corpo([
            'salary' => 250000,
            'transport_allowance' => 30000,
            'meal_allowance' => 25000,
            'status' => 'active',
        ]))->assertCreated()->json('documento.id');

        $e = Employee::findOrFail($id);

        $this->assertEqualsWithDelta(250000, (float) $e->salary, 0.01);
        $this->assertEqualsWithDelta(250000, (float) $e->base_salary, 0.01, 'base_salary segue salary');
        $this->assertEqualsWithDelta(30000, (float) $e->transport_benefit, 0.01);
        $this->assertEqualsWithDelta(25000, (float) $e->food_benefit, 0.01);
        $this->assertSame('active', $e->employment_status);

        // E ao AUMENTAR o salário, as duas sobem.
        $this->putJson(self::RAIZ . '/' . $id, $this->corpo([
            'salary' => 300000,
            'status' => 'on_leave',
        ]))->assertOk();

        $e->refresh();

        $this->assertEqualsWithDelta(300000, (float) $e->salary, 0.01);
        $this->assertEqualsWithDelta(300000, (float) $e->base_salary, 0.01);
        $this->assertSame('on_leave', $e->employment_status);
    }

    /**
     * OS CAMPOS QUE O ECRÃ DE SEMPRE NÃO OFERECIA.
     *
     * Turno, chefia, cessação, os três subsídios que a folha calcula e os
     * beneficiários: a base guardava-os e o formulário não os pedia.
     *
     * @test
     */
    public function os_campos_que_faltavam_no_ecra_gravam_e_voltam(): void
    {
        $this->tudo();

        $departamento = Department::create(['tenant_id' => $this->tenant->id, 'name' => 'Operações', 'code' => 'OPS']);
        $cargo = Position::create(['tenant_id' => $this->tenant->id, 'title' => 'Motorista', 'code' => 'MOT', 'department_id' => $departamento->id]);
        $turno = Shift::create(['tenant_id' => $this->tenant->id, 'name' => 'Manhã', 'start_time' => '08:00', 'end_time' => '16:00', 'hours_per_day' => 8]);
        $chefe = $this->funcionario(['first_name' => 'Eva', 'last_name' => 'Fonseca']);

        $id = $this->postJson(self::RAIZ, $this->corpo([
            'department_id' => $departamento->id,
            'position_id' => $cargo->id,
            'shift_id' => $turno->id,
            'manager_id' => $chefe->id,
            'hire_date' => '2023-01-10',
            'termination_date' => '2026-01-10',
            'family_allowance' => 5000,
            'position_subsidy' => 12000,
            'performance_subsidy' => 8000,
            'criminal_record_number' => 'RC-991',
            'beneficiaries' => [
                ['nome' => 'Maria Bento', 'parentesco' => 'Mãe', 'contacto' => '923000000'],
                ['nome' => 'João Bento', 'parentesco' => 'Filho'],
            ],
        ]))->assertCreated()->json('documento.id');

        $e = Employee::findOrFail($id);

        $this->assertSame($turno->id, (int) $e->shift_id);
        $this->assertSame($chefe->id, (int) $e->manager_id);
        $this->assertSame('2026-01-10', $e->termination_date->format('Y-m-d'));
        $this->assertEqualsWithDelta(12000, (float) $e->position_subsidy, 0.01);
        $this->assertSame('RC-991', $e->criminal_record_number);
        $this->assertCount(2, $e->beneficiaries);
        $this->assertSame('Maria Bento', $e->beneficiaries[0]['nome']);

        // E voltam todos no `abrir`, que é por onde o formulário reabre.
        $aberta = $this->getJson(self::RAIZ . '/' . $id)->assertOk();

        $this->assertSame($turno->id, $aberta->json('documento.shift_id'));
        $this->assertSame($chefe->id, $aberta->json('documento.manager_id'));
        $this->assertSame('2026-01-10', $aberta->json('documento.termination_date'));
        $this->assertCount(2, $aberta->json('documento.beneficiaries'));
    }

    /** A cessação não pode ser anterior à admissão. @test */
    public function a_cessacao_nao_e_anterior_a_admissao(): void
    {
        $this->tudo();

        $this->postJson(self::RAIZ, $this->corpo([
            'hire_date' => '2024-01-01',
            'termination_date' => '2023-12-31',
        ]))->assertStatus(422)->assertJsonValidationErrors('termination_date');
    }

    /**
     * O DEPARTAMENTO, O CARGO E O TURNO SÃO DESTA EMPRESA.
     *
     * Um `exists:hr_departments,id` aceitava o id de outra, e a ficha ficava a
     * apontar para fora de casa sem nada dar erro.
     *
     * @test
     */
    public function as_escolhas_de_outra_empresa_nao_entram(): void
    {
        $this->tudo();

        $outra = Tenant::create(['name' => 'Vizinha', 'slug' => 'viz-' . uniqid(), 'is_active' => true]);

        $departamento = Department::withoutGlobalScopes()->create(['tenant_id' => $outra->id, 'name' => 'De Fora', 'code' => 'FORA']);
        $turno = Shift::withoutGlobalScopes()->create(['tenant_id' => $outra->id, 'name' => 'Fora', 'start_time' => '08:00', 'end_time' => '16:00', 'hours_per_day' => 8]);

        $this->postJson(self::RAIZ, $this->corpo(['department_id' => $departamento->id]))
            ->assertStatus(422)->assertJsonValidationErrors('department_id');

        $this->postJson(self::RAIZ, $this->corpo(['shift_id' => $turno->id]))
            ->assertStatus(422)->assertJsonValidationErrors('shift_id');
    }

    /** Ninguém chefia a si próprio: a cadeia de chefia ficava com um laço. @test */
    public function ninguem_e_chefe_de_si_proprio(): void
    {
        $this->tudo();

        $e = $this->funcionario();

        $this->putJson(self::RAIZ . '/' . $e->id, $this->corpo(['manager_id' => $e->id]))
            ->assertStatus(422);
    }

    /** Uma ficha de outra empresa não se abre nem se altera. @test */
    public function a_ficha_de_outra_empresa_nao_se_toca(): void
    {
        $this->tudo();

        $outra = Tenant::create(['name' => 'Vizinha', 'slug' => 'viz-' . uniqid(), 'is_active' => true]);
        $deFora = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'employee_number' => 'X1',
            'first_name' => 'Fora', 'last_name' => 'Daqui', 'status' => 'active',
        ]);

        $this->getJson(self::RAIZ . '/' . $deFora->id)->assertNotFound();
        $this->putJson(self::RAIZ . '/' . $deFora->id, $this->corpo())->assertNotFound();
        $this->deleteJson(self::RAIZ . '/' . $deFora->id)->assertNotFound();

        $ids = collect($this->getJson(self::RAIZ)->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($deFora->id));
    }

    /* ─── A lista ──────────────────────────────────────────────────────── */

    /**
     * AS CONTAGENS SÃO DE TODA A EMPRESA, não da página à frente.
     *
     * «12 activos» tem de querer dizer doze activos.
     *
     * @test
     */
    public function o_resumo_conta_a_empresa_inteira(): void
    {
        $this->tudo();

        $this->funcionario(['status' => 'active']);
        $this->funcionario(['status' => 'active']);
        $this->funcionario(['status' => 'on_leave']);
        $this->funcionario(['status' => 'terminated']);

        $r = $this->getJson(self::RAIZ . '?por_pagina=5')->assertOk();

        $this->assertSame(4, $r->json('resumo.total'));
        $this->assertSame(2, $r->json('resumo.activos'));
        $this->assertSame(1, $r->json('resumo.de_licenca'));
        $this->assertSame(1, $r->json('resumo.cessados'));
    }

    /**
     * O AVISO DOS DOCUMENTOS A VENCER — o que paga a renda deste ecrã.
     *
     * Um BI caducado é uma pessoa que não pode ser paga em condições, e
     * ninguém vai à ficha de cada um conferir sete datas.
     *
     * @test
     */
    public function os_documentos_a_vencer_contam_se_na_linha_e_no_resumo(): void
    {
        $this->tudo();

        $aVencer = $this->funcionario(['bi_expiry_date' => now()->addDays(20)->toDateString()]);
        $caducado = $this->funcionario(['passport_expiry_date' => now()->subDays(5)->toDateString()]);
        $emDia = $this->funcionario(['bi_expiry_date' => now()->addYears(3)->toDateString()]);

        $r = $this->getJson(self::RAIZ)->assertOk();
        $linhas = collect($r->json('data'))->keyBy('id');

        $this->assertSame(1, $linhas[$aVencer->id]['documentos_a_vencer']);
        $this->assertSame(1, $linhas[$caducado->id]['documentos_a_vencer'], 'um caducado também conta');
        $this->assertSame(0, $linhas[$emDia->id]['documentos_a_vencer']);

        $this->assertSame(2, $r->json('resumo.documentos_a_vencer'));
    }

    /** A procura apanha nome, número, email, telefone e NIF. @test */
    public function a_procura_apanha_os_cinco_campos(): void
    {
        $this->tudo();

        $this->funcionario(['first_name' => 'Zulmira', 'last_name' => 'Quissanga', 'email' => 'zq@exemplo.ao', 'nif' => '004900000LA011']);
        $this->funcionario(['first_name' => 'Outro', 'last_name' => 'Qualquer']);

        foreach (['Zulmira', 'Quissanga', 'zq@exemplo', '004900000'] as $termo) {
            $achados = collect($this->getJson(self::RAIZ . '?procura=' . urlencode($termo))->assertOk()->json('data'));

            $this->assertCount(1, $achados, "a procura por «{$termo}» tem de encontrar um");
            $this->assertSame('Zulmira Quissanga', $achados->first()['nome']);
        }
    }

    /** Filtrar por departamento, cargo e estado. @test */
    public function os_filtros_estreitam_a_lista(): void
    {
        $this->tudo();

        $departamento = Department::create(['tenant_id' => $this->tenant->id, 'name' => 'Operações', 'code' => 'OPS']);

        $this->funcionario(['department_id' => $departamento->id]);
        $this->funcionario();
        $this->funcionario(['status' => 'terminated']);

        $this->assertCount(1, $this->getJson(self::RAIZ . '?departamento=' . $departamento->id)->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson(self::RAIZ . '?estado=terminated')->assertOk()->json('data'));
        $this->assertCount(3, $this->getJson(self::RAIZ)->assertOk()->json('data'));
    }

    /* ─── Os documentos ────────────────────────────────────────────────── */

    /**
     * UM DOCUMENTO SOBE, SUBSTITUI E APAGA-SE.
     *
     * O ficheiro antigo é apagado antes de o novo entrar: a pasta da pessoa
     * não pode encher-se de versões que ninguém volta a ver.
     *
     * @test
     */
    public function um_documento_sobe_substitui_e_apaga_se(): void
    {
        Storage::fake('public');
        $this->tudo();

        $e = $this->funcionario();

        $r = $this->post(self::RAIZ . "/{$e->id}/documentos/bi", [
            'ficheiro' => UploadedFile::fake()->create('bi.pdf', 40, 'application/pdf'),
        ])->assertOk();

        $caminho = $e->fresh()->bi_document_path;

        $this->assertNotNull($caminho);
        Storage::disk('public')->assertExists($caminho);
        $this->assertNotNull(collect($r->json('documento.documentos'))->firstWhere('chave', 'bi')['url']);

        // Substituir: o antigo sai.
        $this->post(self::RAIZ . "/{$e->id}/documentos/bi", [
            'ficheiro' => UploadedFile::fake()->image('bi.jpg'),
        ])->assertOk();

        Storage::disk('public')->assertMissing($caminho);
        $this->assertStringEndsWith('.jpg', $e->fresh()->bi_document_path);

        // Apagar: o ficheiro sai e a coluna fica nula.
        $this->deleteJson(self::RAIZ . "/{$e->id}/documentos/bi")->assertOk();

        $this->assertNull($e->fresh()->bi_document_path);
    }

    /** Um tipo de documento que não existe na ficha dá 404. @test */
    public function um_documento_que_nao_existe_e_404(): void
    {
        Storage::fake('public');
        $this->tudo();

        $e = $this->funcionario();

        $this->post(self::RAIZ . "/{$e->id}/documentos/xpto", [
            'ficheiro' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertNotFound();
    }

    /** Um executável não é um documento de identidade. @test */
    public function um_ficheiro_que_nao_e_documento_e_recusado(): void
    {
        Storage::fake('public');
        $this->tudo();

        $e = $this->funcionario();

        // O `Accept` importa: sem ele a recusa vinha como redirecção, que é o
        // que um formulário quer e uma API não.
        $this->post(self::RAIZ . "/{$e->id}/documentos/bi", [
            'ficheiro' => UploadedFile::fake()->create('malicioso.exe', 10, 'application/octet-stream'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('ficheiro');
    }

    /** A fotografia tem porta própria: é o retrato, não um documento. @test */
    public function a_fotografia_sobe_e_aparece_na_lista(): void
    {
        Storage::fake('public');
        $this->tudo();

        $e = $this->funcionario();

        $this->post(self::RAIZ . "/{$e->id}/fotografia", [
            'ficheiro' => UploadedFile::fake()->image('retrato.jpg'),
        ])->assertOk();

        $this->assertNotNull($e->fresh()->photo);

        $linha = collect($this->getJson(self::RAIZ)->assertOk()->json('data'))->firstWhere('id', $e->id);
        $this->assertNotNull($linha['fotografia']);
    }

    /* ─── Eliminar ─────────────────────────────────────────────────────── */

    /**
     * ELIMINAR É UM `SOFT DELETE`, de propósito.
     *
     * A ficha sai da lista mas a linha fica: as folhas de pagamento antigas
     * apontam para ela, e apagá-la a sério deixava recibos já emitidos sem
     * saber de quem eram.
     *
     * @test
     */
    public function eliminar_tira_da_lista_e_deixa_a_linha(): void
    {
        $this->tudo();

        $e = $this->funcionario();

        $this->deleteJson(self::RAIZ . '/' . $e->id)->assertOk();

        $this->assertSoftDeleted('hr_employees', ['id' => $e->id]);

        $ids = collect($this->getJson(self::RAIZ)->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($e->id));
    }

    /* ─── Importar ─────────────────────────────────────────────────────── */

    /** Importar exige a permissão de criar — é criar fichas. @test */
    public function importar_exige_a_permissao_de_criar(): void
    {
        $this->comPermissoes('employees.view');

        $this->getJson(self::RAIZ . '/importaveis')->assertForbidden();
        $this->postJson(self::RAIZ . '/importar', ['origem' => 'tecnicos', 'ids' => [1]])->assertForbidden();
    }

    /** Uma origem que não existe é 422 — não se inventa de onde importar. @test */
    public function uma_origem_desconhecida_e_recusada(): void
    {
        $this->tudo();

        $this->postJson(self::RAIZ . '/importar', ['origem' => 'marte', 'ids' => [1]])
            ->assertStatus(422)->assertJsonValidationErrors('origem');
    }
}
