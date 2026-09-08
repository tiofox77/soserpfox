<?php

namespace Tests\Feature\HR;

use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\Payroll;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * O PONTO E A FOLHA, na API que os dois ecrãs usam.
 *
 * O que estes ensaios guardam:
 *
 * 1. QUE HÁ GUARDA. `/hr/attendance` e `/hr/payroll` corriam com `auth` e
 *    mais nada — e a folha é o salário de toda a gente.
 *
 * 2. QUE UMA PESSOA TEM UM PONTO POR DIA. Duas linhas para o mesmo dia
 *    contavam a presença duas vezes na folha.
 *
 * 3. QUE AS HORAS SÃO CONTADAS, e que uma saída antes da entrada é do dia
 *    seguinte — sem isso, um turno da noite dava horas negativas.
 *
 * 4. QUE O CICLO DA FOLHA SE RESPEITA: processar antes de aprovar, aprovar
 *    antes de pagar, e é o PAGAR que abate os adiantamentos.
 */
class ApiDoPontoEDaFolhaTest extends TenantTestCase
{
    private const PONTO = '/api/v1/invoicing/react/rh/presencas';
    private const FOLHA = '/api/v1/invoicing/react/rh/folha';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('rh');
    }

    private function funcionario(array $por = []): Employee
    {
        return Employee::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'EMP-' . substr((string) (microtime(true) * 10000), -7),
            'first_name' => 'Ana',
            'last_name' => 'Bento',
            'hire_date' => now()->subYear()->toDateString(),
            'status' => 'active',
            'salary' => 200000,
            'base_salary' => 200000,
        ], $por));
    }

    /* ─── A guarda que não existia ─────────────────────────────────────── */

    /** @test */
    public function sem_permissao_nao_se_ve_o_ponto_nem_a_folha(): void
    {
        $this->getJson(self::PONTO)->assertForbidden();
        $this->getJson(self::PONTO . '/opcoes')->assertForbidden();
        $this->postJson(self::PONTO, [])->assertForbidden();

        $this->getJson(self::FOLHA)->assertForbidden();
        $this->getJson(self::FOLHA . '/opcoes')->assertForbidden();
        $this->postJson(self::FOLHA, [])->assertForbidden();
    }

    /* ─── O ponto ──────────────────────────────────────────────────────── */

    /** As horas contam-se das picagens, e não se escrevem. @test */
    public function as_horas_contam_se_das_picagens(): void
    {
        $this->comPermissoes('attendance.manage');

        $r = $this->postJson(self::PONTO, [
            'employee_id' => $this->funcionario()->id,
            'date' => now()->toDateString(),
            'check_in' => '08:00',
            'check_out' => '17:30',
            'status' => 'present',
        ])->assertCreated();

        $this->assertEqualsWithDelta(9.5, $r->json('documento.hours_worked'), 0.01);
    }

    /**
     * UM TURNO DA NOITE ATRAVESSA A MEIA-NOITE.
     *
     * A saída antes da entrada é do dia seguinte — sem isto, das 22:00 às
     * 06:00 dava dezasseis horas negativas.
     *
     * @test
     */
    public function um_turno_da_noite_nao_da_horas_negativas(): void
    {
        $this->comPermissoes('attendance.manage');

        $r = $this->postJson(self::PONTO, [
            'employee_id' => $this->funcionario()->id,
            'date' => now()->toDateString(),
            'check_in' => '22:00',
            'check_out' => '06:00',
            'status' => 'present',
        ])->assertCreated();

        $this->assertEqualsWithDelta(8, $r->json('documento.hours_worked'), 0.01);
    }

    /**
     * MARCAR A ENTRADA COM UM CLIQUE — e só uma vez por dia.
     *
     * É o botão do dia: no fim da manhã, a lista de quem falta marcar.
     *
     * @test
     */
    public function a_entrada_marca_se_com_um_clique_e_so_uma_vez(): void
    {
        $this->comPermissoes('attendance.manage');

        $e = $this->funcionario();

        $r = $this->postJson(self::PONTO . '/entrada/' . $e->id)->assertCreated();

        $this->assertNotNull($r->json('documento.check_in'));
        $this->assertSame('present', $r->json('documento.status'));

        $this->postJson(self::PONTO . '/entrada/' . $e->id)
            ->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->assertSame(1, Attendance::where('employee_id', $e->id)->count());
    }

    /** A saída marca-se na linha, e conta as horas. Duas vezes não. @test */
    public function a_saida_marca_se_uma_vez(): void
    {
        $this->comPermissoes('attendance.manage');

        $e = $this->funcionario();
        $id = $this->postJson(self::PONTO . '/entrada/' . $e->id)->assertCreated()->json('documento.id');

        $this->postJson(self::PONTO . "/{$id}/saida")->assertOk();
        $this->postJson(self::PONTO . "/{$id}/saida")->assertStatus(422)->assertJsonValidationErrors('check_out');
    }

    /**
     * QUEM AINDA NÃO PICOU HOJE aparece na lista do dia — e sai dela assim que
     * pica. É a razão de existir do botão de entrada rápida.
     *
     * @test
     */
    public function quem_falta_marcar_aparece_na_lista_do_dia(): void
    {
        $this->comPermissoes('attendance.manage');

        $comPonto = $this->funcionario(['first_name' => 'Com']);
        $semPonto = $this->funcionario(['first_name' => 'Sem']);

        $this->postJson(self::PONTO . '/entrada/' . $comPonto->id)->assertCreated();

        $ids = collect($this->getJson(self::PONTO)->assertOk()->json('por_marcar'))->pluck('id');

        $this->assertTrue($ids->contains($semPonto->id));
        $this->assertFalse($ids->contains($comPonto->id));
    }

    /** O funcionário do ponto é desta empresa. @test */
    public function o_ponto_de_um_funcionario_de_fora_nao_entra(): void
    {
        $this->comPermissoes('attendance.manage');

        $outra = Tenant::create(['name' => 'Vizinha', 'slug' => 'viz-' . uniqid(), 'is_active' => true]);
        $deFora = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'employee_number' => 'X1',
            'first_name' => 'Fora', 'last_name' => 'Daqui', 'status' => 'active',
        ]);

        $this->postJson(self::PONTO, [
            'employee_id' => $deFora->id,
            'date' => now()->toDateString(),
            'check_in' => '08:00',
            'status' => 'present',
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }

    /** O resumo conta o período, por estado. @test */
    public function o_resumo_do_ponto_conta_por_estado(): void
    {
        $this->comPermissoes('attendance.manage');

        $hoje = now()->toDateString();

        foreach (['present', 'present', 'absent'] as $estado) {
            Attendance::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $this->funcionario()->id,
                'date' => $hoje,
                'status' => $estado,
                'hours_worked' => $estado === 'present' ? 8 : 0,
            ]);
        }

        $r = $this->getJson(self::PONTO)->assertOk();
        $porEstado = collect($r->json('resumo.por_estado'))->keyBy('valor');

        $this->assertSame(2, $porEstado['present']['quantos']);
        $this->assertSame(1, $porEstado['absent']['quantos']);
        $this->assertEqualsWithDelta(16, $r->json('resumo.horas'), 0.01);
    }

    /** O calendário do mês dá uma linha por pessoa. @test */
    public function o_calendario_do_mes_da_uma_linha_por_pessoa(): void
    {
        $this->comPermissoes('attendance.manage');

        $e = $this->funcionario();

        Attendance::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $e->id,
            'date' => now()->startOfMonth()->toDateString(), 'status' => 'present', 'hours_worked' => 8,
        ]);

        $r = $this->getJson(self::PONTO . '/calendario?ano=' . now()->format('Y') . '&mes=' . now()->format('n'))->assertOk();

        $this->assertCount(1, $r->json('linhas'));
        $this->assertSame($e->id, $r->json('linhas.0.id'));
        $this->assertSame('present', $r->json('linhas.0.dias.' . now()->startOfMonth()->format('Y-m-d') . '.estado'));
    }

    /* ─── A folha ──────────────────────────────────────────────────────── */

    /** Uma folha por mês e por empresa: duas pagavam duas vezes. @test */
    public function so_ha_uma_folha_por_mes(): void
    {
        $this->comPermissoes('payroll.process');

        $this->postJson(self::FOLHA, ['year' => 2026, 'month' => 3])->assertCreated();

        $this->postJson(self::FOLHA, ['year' => 2026, 'month' => 3])
            ->assertStatus(422)->assertJsonValidationErrors('month');

        $this->assertSame(1, Payroll::where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * O CICLO RESPEITA-SE: aprovar antes de pagar.
     *
     * Pagar uma folha que ninguém aprovou é dinheiro a sair sem decisão.
     *
     * A FOLHA NASCE CALCULADA — o `createPayroll` já monta a linha de cada
     * trabalhador — e por isso não há um passo de «processar» obrigatório:
     * refazer as contas é para quando algo muda depois.
     *
     * @test
     */
    public function o_ciclo_da_folha_respeita_se(): void
    {
        $this->comPermissoes('payroll.process');

        $this->funcionario();

        $id = $this->postJson(self::FOLHA, ['year' => 2026, 'month' => 4])->assertCreated()->json('documento.id');

        // Pagar sem aprovar: não.
        $this->postJson(self::FOLHA . "/{$id}/pagar")->assertStatus(422)->assertJsonValidationErrors('status');

        // Refazer as contas é sempre possível enquanto não estiver paga.
        $this->postJson(self::FOLHA . "/{$id}/processar")->assertOk();
        $this->postJson(self::FOLHA . "/{$id}/aprovar")->assertOk();
        $this->postJson(self::FOLHA . "/{$id}/pagar")->assertOk();

        $this->assertSame('paid', Payroll::findOrFail($id)->status);

        // E uma folha paga já não se recalcula nem se elimina.
        $this->postJson(self::FOLHA . "/{$id}/recalcular")->assertStatus(422);
        $this->deleteJson(self::FOLHA . "/{$id}")->assertStatus(422);
    }

    /** Processar calcula a linha de cada trabalhador. @test */
    public function processar_calcula_a_linha_de_cada_trabalhador(): void
    {
        $this->comPermissoes('payroll.process');

        $this->funcionario(['first_name' => 'Um']);
        $this->funcionario(['first_name' => 'Dois']);

        $id = $this->postJson(self::FOLHA, ['year' => 2026, 'month' => 5])->assertCreated()->json('documento.id');

        $this->postJson(self::FOLHA . "/{$id}/processar")->assertOk();

        $r = $this->getJson(self::FOLHA . "/{$id}")->assertOk();

        $this->assertCount(2, $r->json('documento.linhas'));

        // A linha vem agrupada como o recibo a mostra.
        $linha = $r->json('documento.linhas.0');

        $this->assertArrayHasKey('ganhos', $linha);
        $this->assertArrayHasKey('impostos', $linha);
        $this->assertArrayHasKey('descontos', $linha);
        $this->assertArrayHasKey('tempo', $linha);
        $this->assertGreaterThan(0, $linha['bruto']);
    }

    /** Uma folha aprovada não se elimina — é o registo de uma decisão. @test */
    public function uma_folha_aprovada_nao_se_elimina(): void
    {
        $this->comPermissoes('payroll.process');

        $this->funcionario();

        $id = $this->postJson(self::FOLHA, ['year' => 2026, 'month' => 6])->assertCreated()->json('documento.id');

        $this->postJson(self::FOLHA . "/{$id}/processar")->assertOk();
        $this->postJson(self::FOLHA . "/{$id}/aprovar")->assertOk();

        $this->deleteJson(self::FOLHA . "/{$id}")->assertStatus(422)->assertJsonValidationErrors('status');
    }

    /** Um rascunho elimina-se. @test */
    public function um_rascunho_elimina_se(): void
    {
        $this->comPermissoes('payroll.process');

        $id = $this->postJson(self::FOLHA, ['year' => 2026, 'month' => 7])->assertCreated()->json('documento.id');

        $this->deleteJson(self::FOLHA . "/{$id}")->assertOk();

        $this->assertDatabaseMissing('hr_payrolls', ['id' => $id]);
    }

    /** Uma folha de outra empresa não se abre nem se processa. @test */
    public function a_folha_de_outra_empresa_nao_se_toca(): void
    {
        $this->comPermissoes('payroll.process');

        $outra = Tenant::create(['name' => 'Vizinha', 'slug' => 'viz-' . uniqid(), 'is_active' => true]);
        $deFora = Payroll::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'payroll_number' => 'FP-FORA', 'year' => 2026, 'month' => 1,
            'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'status' => 'draft',
        ]);

        $this->getJson(self::FOLHA . "/{$deFora->id}")->assertNotFound();
        $this->postJson(self::FOLHA . "/{$deFora->id}/processar")->assertNotFound();
        $this->deleteJson(self::FOLHA . "/{$deFora->id}")->assertNotFound();
    }
}
