<?php

namespace Tests\Feature\HR;

use App\Models\HR\Contract;
use App\Models\HR\Employee;
use App\Services\HR\PayrollService;
use Tests\TenantTestCase;

/**
 * OS CONTRATOS — o ecrã que nunca existiu, sobre a tabela que já decidia o
 * salário pago.
 *
 * `hr_contracts` está no sistema desde o princípio e o `PayrollService` lê-a
 * ANTES da ficha do funcionário. Sem ecrã, ninguém a preenchia — e quem a
 * preenchesse à mão na base podia deixar dois contratos activos, com o
 * salário pago a depender da ordem de inserção.
 *
 * O QUE AQUI SE PROVA:
 *
 *  · UM CONTRATO ACTIVO POR PESSOA — activar um termina o anterior;
 *  · que o contrato ACTIVO é mesmo o que a folha usa;
 *  · que um contrato a termo tem de dizer quando acaba;
 *  · que se CESSA e não se apaga o que esteve em vigor;
 *  · e as quatro permissões, por verbo.
 */
class ApiDosContratosTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/rh/contratos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('rh');
    }

    private function funcionario(float $salario = 200000): Employee
    {
        return Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-' . uniqid(),
            'first_name' => 'Teste',
            'last_name' => 'QA',
            'status' => 'active',
            'salary' => $salario,
            'base_salary' => $salario,
            'hire_date' => now()->subYears(2)->startOfYear(),
        ]);
    }

    /** @return array<string, mixed> */
    private function corpo(Employee $e, array $campos = []): array
    {
        return array_merge([
            'employee_id' => $e->id,
            'contract_type' => 'Indeterminado',
            'status' => 'active',
            'start_date' => now()->startOfYear()->toDateString(),
            'base_salary' => 250000,
            'payment_frequency' => 'Mensal',
            'weekly_hours' => 40,
            'vacation_days_per_year' => 22,
        ], $campos);
    }

    /* ─── As permissões, por verbo ────────────────────────────────────── */

    public function test_cada_verbo_pede_a_sua_permissao(): void
    {
        $e = $this->funcionario();

        $this->getJson(self::RAIZ)->assertForbidden();
        $this->postJson(self::RAIZ, $this->corpo($e))->assertForbidden();

        // Ver não é criar.
        $this->comPermissoes('hr.contracts.view');

        $this->getJson(self::RAIZ)->assertOk();
        $this->postJson(self::RAIZ, $this->corpo($e))->assertForbidden();

        $this->comPermissoes('hr.contracts.create');

        $id = $this->postJson(self::RAIZ, $this->corpo($e))->assertCreated()->json('documento.id');

        // Criar não é editar, e editar não é eliminar.
        $this->putJson(self::RAIZ . "/{$id}", $this->corpo($e))->assertForbidden();
        $this->deleteJson(self::RAIZ . "/{$id}")->assertForbidden();
    }

    public function test_as_opcoes_dizem_o_que_quem_ve_pode_fazer(): void
    {
        $this->comPermissoes('hr.contracts.view');

        $this->getJson(self::RAIZ . '/opcoes')->assertOk()
            ->assertJsonPath('permissoes.pode_criar', false)
            ->assertJsonPath('permissoes.pode_editar', false)
            ->assertJsonPath('permissoes.pode_eliminar', false);
    }

    /** As opções trazem o salário da ficha, para o formulário o propor. */
    public function test_as_opcoes_trazem_o_salario_da_ficha(): void
    {
        $this->comPermissoes('hr.contracts.view');

        $e = $this->funcionario(315000);

        $lista = collect($this->getJson(self::RAIZ . '/opcoes')->assertOk()->json('funcionarios'));
        $linha = $lista->firstWhere('valor', (string) $e->id);

        $this->assertNotNull($linha);
        $this->assertEquals(315000, $linha['salario']);
    }

    /* ─── Um contrato activo por pessoa ───────────────────────────────── */

    /**
     * ACTIVAR UM TERMINA O ANTERIOR.
     *
     * `Employee::activeContract` é um `hasOne` sobre `status = active` com
     * `latest()`: com dois activos, o salário pago passa a depender da ordem
     * de inserção.
     */
    public function test_activar_um_contrato_caduca_o_anterior(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create');

        $e = $this->funcionario();

        $primeiro = $this->postJson(self::RAIZ, $this->corpo($e, [
            'start_date' => '2025-01-01', 'base_salary' => 200000,
        ]))->assertCreated()->json('documento.id');

        $this->postJson(self::RAIZ, $this->corpo($e, [
            'start_date' => '2026-03-01', 'base_salary' => 300000,
        ]))->assertCreated();

        $antigo = Contract::findOrFail($primeiro);

        $this->assertSame('expired', $antigo->status);
        $this->assertSame('2026-02-28', $antigo->end_date?->format('Y-m-d'), 'acaba na véspera do novo');

        $this->assertSame(
            1,
            Contract::where('employee_id', $e->id)->where('status', 'active')->count(),
            'um activo, e um só'
        );
    }

    /** Um contrato guardado como caducado não mexe em ninguém. */
    public function test_um_contrato_que_nasce_caducado_nao_termina_o_activo(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create');

        $e = $this->funcionario();

        $activo = $this->postJson(self::RAIZ, $this->corpo($e))->assertCreated()->json('documento.id');

        $this->postJson(self::RAIZ, $this->corpo($e, [
            'status' => 'suspended', 'start_date' => '2026-06-01',
        ]))->assertCreated();

        $this->assertSame('active', Contract::findOrFail($activo)->status);
    }

    /**
     * E O CONTRATO ACTIVO É MESMO O QUE A FOLHA PAGA.
     *
     * É a razão de este ecrã existir: o `createPayrollItem` lê
     * `$contract->base_salary ?? $employee->base_salary`.
     */
    public function test_o_salario_do_contrato_vence_o_da_ficha_na_folha(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create');

        // A ficha diz 200.000; o contrato diz 450.000.
        $e = $this->funcionario(200000);

        $this->postJson(self::RAIZ, $this->corpo($e, ['base_salary' => 450000]))->assertCreated();

        $folha = app(PayrollService::class)->createPayroll($this->tenant->id, 2026, 4);
        $linha = $folha->items()->where('employee_id', $e->id)->firstOrFail();

        $this->assertEquals(450000, $linha->base_salary, 'quem manda é o contrato');
    }

    /* ─── As regras do formulário ─────────────────────────────────────── */

    public function test_um_contrato_a_termo_tem_de_dizer_quando_acaba(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create');

        $e = $this->funcionario();

        $this->postJson(self::RAIZ, $this->corpo($e, ['contract_type' => 'Determinado']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');

        $this->postJson(self::RAIZ, $this->corpo($e, [
            'contract_type' => 'Determinado', 'end_date' => now()->addYear()->toDateString(),
        ]))->assertCreated();
    }

    public function test_o_fim_nao_pode_ser_antes_do_inicio(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create');

        $e = $this->funcionario();

        $this->postJson(self::RAIZ, $this->corpo($e, [
            'contract_type' => 'Determinado',
            'start_date' => '2026-06-01',
            'end_date' => '2026-01-01',
        ]))->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_um_funcionario_de_outra_empresa_e_recusado(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create');

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(800000000, 899999999),
            'email' => 'outra' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        $alheio = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'employee_number' => 'X-' . uniqid(),
            'first_name' => 'Alheio', 'last_name' => 'QA', 'status' => 'active',
        ]);

        $this->postJson(self::RAIZ, $this->corpo($alheio))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employee_id');
    }

    /** O número é por empresa, e segue. */
    public function test_o_numero_segue_a_sequencia_da_empresa(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create');

        $a = $this->postJson(self::RAIZ, $this->corpo($this->funcionario()))->assertCreated();
        $b = $this->postJson(self::RAIZ, $this->corpo($this->funcionario()))->assertCreated();

        $this->assertSame('CT-0001', $a->json('documento.numero'));
        $this->assertSame('CT-0002', $b->json('documento.numero'));
    }

    /* ─── Cessar, e não apagar ────────────────────────────────────────── */

    public function test_cessar_guarda_a_data_e_o_motivo(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create', 'hr.contracts.edit');

        $e = $this->funcionario();
        $id = $this->postJson(self::RAIZ, $this->corpo($e))->assertCreated()->json('documento.id');

        $this->postJson(self::RAIZ . "/{$id}/cessar", [
            'termination_date' => now()->toDateString(),
            'termination_reason' => 'Rescisão por mútuo acordo.',
        ])->assertOk();

        $c = Contract::findOrFail($id);

        $this->assertSame('terminated', $c->status);
        $this->assertSame('Rescisão por mútuo acordo.', $c->termination_reason);
    }

    public function test_a_cessacao_nao_pode_ser_antes_do_inicio(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create', 'hr.contracts.edit');

        $e = $this->funcionario();
        $id = $this->postJson(self::RAIZ, $this->corpo($e, ['start_date' => '2026-06-01']))
            ->assertCreated()->json('documento.id');

        $this->postJson(self::RAIZ . "/{$id}/cessar", [
            'termination_date' => '2026-01-01',
            'termination_reason' => 'Impossível.',
        ])->assertStatus(422)->assertJsonValidationErrors('termination_date');
    }

    /**
     * SÓ SE APAGA O QUE NUNCA VALEU.
     *
     * Um contrato que esteve em vigor explica salários já pagos; apagá-lo
     * apagava a razão de aqueles meses terem sido aqueles.
     */
    public function test_um_contrato_que_ja_comecou_nao_se_elimina(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create', 'hr.contracts.delete');

        $e = $this->funcionario();

        $emVigor = $this->postJson(self::RAIZ, $this->corpo($e, ['start_date' => now()->subMonth()->toDateString()]))
            ->assertCreated()->json('documento.id');

        $this->deleteJson(self::RAIZ . "/{$emVigor}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('hr_contracts', ['id' => $emVigor]);
    }

    public function test_um_contrato_que_ainda_nao_comecou_elimina_se(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create', 'hr.contracts.delete');

        $e = $this->funcionario();

        $futuro = $this->postJson(self::RAIZ, $this->corpo($e, ['start_date' => now()->addMonth()->toDateString()]))
            ->assertCreated()->json('documento.id');

        $this->deleteJson(self::RAIZ . "/{$futuro}")->assertOk();

        $this->assertDatabaseMissing('hr_contracts', ['id' => $futuro]);
    }

    /* ─── A lista ─────────────────────────────────────────────────────── */

    /**
     * O CARTÃO «A TERMINAR» é a razão de este ecrã se abrir: um contrato a
     * termo que caduca sem ninguém dar por isso converte-se em permanente por
     * lei.
     */
    public function test_o_resumo_conta_os_que_acabam_nos_proximos_sessenta_dias(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create');

        $this->postJson(self::RAIZ, $this->corpo($this->funcionario(), [
            'contract_type' => 'Determinado',
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'base_salary' => 100000,
        ]))->assertCreated();

        // Este acaba daqui a um ano: não entra no aviso.
        $this->postJson(self::RAIZ, $this->corpo($this->funcionario(), [
            'contract_type' => 'Determinado',
            'end_date' => now()->addYear()->toDateString(),
            'base_salary' => 400000,
        ]))->assertCreated();

        $r = $this->getJson(self::RAIZ)->assertOk();

        $this->assertSame(2, $r->json('resumo.em_vigor'));
        $this->assertSame(1, $r->json('resumo.a_terminar'));
        $this->assertEquals(500000, $r->json('resumo.massa_salarial'));
    }

    public function test_a_lista_filtra_por_estado_e_por_procura(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create', 'hr.contracts.edit');

        $ana = Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_number' => 'ANA-1',
            'first_name' => 'Ana', 'last_name' => 'Kiala', 'status' => 'active',
        ]);

        $this->postJson(self::RAIZ, $this->corpo($ana))->assertCreated();
        $outro = $this->postJson(self::RAIZ, $this->corpo($this->funcionario()))->assertCreated()->json('documento.id');

        $this->postJson(self::RAIZ . "/{$outro}/cessar", [
            'termination_date' => now()->toDateString(), 'termination_reason' => 'Fim.',
        ])->assertOk();

        $this->getJson(self::RAIZ . '?estado=active')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::RAIZ . '?procura=Kiala')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.funcionario', 'Ana Kiala');
    }

    /** Os contratos de uma empresa não se vêem de outra. */
    public function test_os_contratos_nao_atravessam_empresas(): void
    {
        $this->comPermissoes('hr.contracts.view', 'hr.contracts.create');

        $this->postJson(self::RAIZ, $this->corpo($this->funcionario()))->assertCreated();

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(800000000, 899999999),
            'email' => 'outra' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        $alheio = Contract::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id,
            'employee_id' => $this->funcionario()->id,
            'contract_number' => 'CT-9999',
            'contract_type' => 'Indeterminado',
            'start_date' => '2026-01-01',
            'base_salary' => 1,
            'status' => 'active',
        ]);

        $this->getJson(self::RAIZ)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::RAIZ . "/{$alheio->id}")->assertNotFound();
    }
}
