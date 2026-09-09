<?php

namespace Tests\Feature\HR;

use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use App\Models\HR\Vacation;
use Tests\TenantTestCase;

/**
 * O PAINEL, OS CINCO MAPAS E O MAPA DE IRT.
 *
 * As três últimas portas do RH que não tinham guarda nenhuma: bastava ter o
 * módulo activo para abrir o mapa de salários — o salário de toda a gente
 * numa página só — e o mapa de IRT, que é o que se entrega à AGT.
 *
 * O QUE AQUI SE PROVA:
 *
 *  · que cada porta exige a SUA permissão;
 *  · que os AVISOS do painel são filtrados pela permissão de quem vê — mandar
 *    alguém para um ecrã que lhe vai dar 403 é pior do que não avisar;
 *  · que nenhum mapa recalcula nada: lê o que a folha gravou;
 *  · que o mapa de IRT MOSTRA as folhas que ficaram de fora, que é a razão
 *    nº1 de o mapa não bater com o que a contabilidade espera.
 */
class ApiDoPainelEDosMapasTest extends TenantTestCase
{
    private const PAINEL = '/api/v1/invoicing/react/rh/painel';
    private const MAPAS = '/api/v1/invoicing/react/rh/relatorios';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('rh');
    }

    private function funcionario(array $campos = []): Employee
    {
        return Employee::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-' . uniqid(),
            'first_name' => 'Teste',
            'last_name' => 'QA',
            'status' => 'active',
            'base_salary' => 200000,
            'hire_date' => now()->subYears(2)->startOfYear(),
        ], $campos));
    }

    /* ─── O painel ────────────────────────────────────────────────────── */

    public function test_o_painel_pede_a_sua_permissao(): void
    {
        $this->getJson(self::PAINEL)->assertForbidden();

        $this->comPermissoes('hr.dashboard.view');

        $this->getJson(self::PAINEL)->assertOk();
    }

    public function test_o_painel_conta_quem_esta_na_casa(): void
    {
        $this->comPermissoes('hr.dashboard.view');

        $this->funcionario();
        $this->funcionario(['status' => 'terminated']);

        $r = $this->getJson(self::PAINEL)->assertOk();

        $this->assertSame(2, $r->json('cartoes.funcionarios'));
        $this->assertSame(1, $r->json('cartoes.activos'));
    }

    /**
     * OS AVISOS SÓ APARECEM A QUEM PODE RESOLVÊ-LOS.
     *
     * Um aviso com um botão que dá 403 lê-se como avaria do sistema quando é
     * a guarda a funcionar.
     */
    public function test_um_aviso_so_aparece_a_quem_pode_resolve_lo(): void
    {
        $e = $this->funcionario();

        Vacation::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $e->id,
            'vacation_number' => 'FE-' . uniqid(),
            'reference_year' => (int) now()->format('Y'),
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'calculated_days' => 22,
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDays(5)->toDateString(),
            'requested_days' => 5,
            'working_days' => 5,
            'status' => 'pending',
        ]);

        // Só o painel: o pedido existe, mas quem vê não decide férias.
        $this->comPermissoes('hr.dashboard.view');

        $titulos = collect($this->getJson(self::PAINEL)->assertOk()->json('avisos'))->pluck('titulo');

        $this->assertNotContains(__('Férias por aprovar'), $titulos->all());

        // Com a permissão de ver férias, o aviso aparece — e traz a morada.
        $this->comPermissoes('hr.vacations.view');

        $avisos = collect($this->getJson(self::PAINEL)->assertOk()->json('avisos'));
        $ferias = $avisos->firstWhere('titulo', __('Férias por aprovar'));

        $this->assertNotNull($ferias, 'o aviso tem de aparecer a quem vê férias');
        $this->assertStringContainsString('/hr/vacations', $ferias['morada']);
    }

    public function test_o_painel_traz_as_series_dos_graficos(): void
    {
        $this->comPermissoes('hr.dashboard.view');

        $r = $this->getJson(self::PAINEL)->assertOk();

        // Doze meses de custo, sete dias de presença — mesmo a zero.
        $this->assertCount(12, $r->json('graficos.custo_mensal.etiquetas'));
        $this->assertCount(12, $r->json('graficos.custo_mensal.valores'));
        $this->assertCount(7, $r->json('graficos.presenca_da_semana.valores'));
    }

    /* ─── Os mapas ────────────────────────────────────────────────────── */

    public function test_os_mapas_pedem_a_sua_permissao(): void
    {
        $this->getJson(self::MAPAS . '/opcoes')->assertForbidden();
        $this->getJson(self::MAPAS . '?mapa=mapa_de_salarios&ano=2026&mes=9')->assertForbidden();

        $this->comPermissoes('hr.reports.view');

        $this->getJson(self::MAPAS . '/opcoes')->assertOk()->assertJsonCount(5, 'mapas');
    }

    /** Cada mapa diz o que precisa: o quadro de pessoal é do ano e não pede mês. */
    public function test_cada_mapa_diz_que_filtros_pede(): void
    {
        $this->comPermissoes('hr.reports.view');

        $mapas = collect($this->getJson(self::MAPAS . '/opcoes')->assertOk()->json('mapas'))->keyBy('valor');

        $this->assertTrue($mapas['mapa_de_salarios']['pede_mes']);
        $this->assertTrue($mapas['mapa_de_salarios']['pede_departamento']);
        $this->assertFalse($mapas['quadro_de_pessoal']['pede_mes']);
        $this->assertFalse($mapas['custo_por_departamento']['pede_departamento']);
        $this->assertFalse($mapas['saldo_de_ferias']['pede_mes']);
    }

    public function test_um_mapa_que_nao_existe_e_recusado(): void
    {
        $this->comPermissoes('hr.reports.view');

        $this->getJson(self::MAPAS . '?mapa=inventado&ano=2026')
            ->assertStatus(422)
            ->assertJsonValidationErrors('mapa');
    }

    /**
     * O MAPA DE SALÁRIOS LÊ A FOLHA, e soma coluna a coluna.
     *
     * Não recalcula: se recalculasse, podia dizer um valor e o recibo que o
     * trabalhador tem em casa dizer outro.
     */
    public function test_o_mapa_de_salarios_soma_o_que_a_folha_gravou(): void
    {
        $this->comPermissoes('hr.reports.view');

        $folha = $this->folhaCom([
            ['base' => 200000, 'bruto' => 230000, 'inss' => 6900, 'irt' => 5000, 'liquido' => 218100],
            ['base' => 300000, 'bruto' => 330000, 'inss' => 9900, 'irt' => 20000, 'liquido' => 300100],
        ]);

        $r = $this->getJson(self::MAPAS . "?mapa=mapa_de_salarios&ano={$folha->year}&mes={$folha->month}")->assertOk();

        $this->assertCount(2, $r->json('linhas'));
        $this->assertEquals(560000, $r->json('totais.bruto'));
        $this->assertEquals(25000, $r->json('totais.irt'));
        $this->assertEquals(518200, $r->json('totais.liquido'));
        $this->assertSame($folha->payroll_number, $r->json('folha.numero'));
    }

    public function test_sem_folha_o_mapa_diz_que_nao_ha(): void
    {
        $this->comPermissoes('hr.reports.view');

        $r = $this->getJson(self::MAPAS . '?mapa=mapa_de_salarios&ano=2019&mes=7')->assertOk();

        $this->assertSame([], $r->json('linhas'));
        $this->assertNotNull($r->json('nada'));
    }

    public function test_o_custo_por_departamento_agrupa_e_junta_o_inss_da_empresa(): void
    {
        $this->comPermissoes('hr.reports.view');

        $dep = Department::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Operações', 'code' => 'OPS-' . uniqid(), 'is_active' => true,
        ]);

        $folha = $this->folhaCom([
            ['base' => 200000, 'bruto' => 200000, 'inss' => 6000, 'irt' => 0, 'liquido' => 194000, 'empresa' => 16000, 'departamento' => $dep->id],
        ]);

        $r = $this->getJson(self::MAPAS . "?mapa=custo_por_departamento&ano={$folha->year}&mes={$folha->month}")->assertOk();

        $linha = collect($r->json('linhas'))->firstWhere('departamento', 'Operações');

        $this->assertNotNull($linha);
        $this->assertEquals(1, $linha['pessoas']);
        // O INSS da linha é o do trabalhador MAIS o da empresa: é o custo.
        $this->assertEquals(22000, $linha['inss']);
    }

    /** O resumo de presenças conta a partir do ponto, e a taxa é a assiduidade. */
    public function test_o_resumo_de_presencas_conta_o_mes(): void
    {
        $this->comPermissoes('hr.reports.view');

        $e = $this->funcionario();
        $mes = now()->startOfMonth();

        foreach ([['present', 1], ['present', 2], ['late', 3], ['absent', 4]] as [$estado, $dia]) {
            Attendance::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $e->id,
                'date' => $mes->copy()->addDays($dia - 1)->toDateString(),
                'status' => $estado,
            ]);
        }

        $r = $this->getJson(self::MAPAS . '?mapa=resumo_de_presencas&ano=' . $mes->year . '&mes=' . $mes->month)->assertOk();

        $linha = collect($r->json('linhas'))->firstWhere('id', $e->id);

        $this->assertNotNull($linha);
        $this->assertEquals(3, $linha['equivalente'], 'dois presentes mais um atraso');
        $this->assertEquals(1, $linha['atrasos']);
        $this->assertEquals(1, $linha['faltas']);
        $this->assertEquals(75.0, $linha['taxa'], 'três de quatro dias marcados');
    }

    public function test_o_saldo_de_ferias_vem_do_servico(): void
    {
        $this->comPermissoes('hr.reports.view');

        $e = $this->funcionario();

        $r = $this->getJson(self::MAPAS . '?mapa=saldo_de_ferias&ano=' . now()->year)->assertOk();

        $linha = collect($r->json('linhas'))->firstWhere('id', $e->id);

        $this->assertNotNull($linha);
        // O direito vem do VacationService — não é escrito aqui.
        $this->assertSame($linha['direito'] - $linha['gozados'], $linha['saldo']);
    }

    public function test_o_quadro_de_pessoal_conta_ate_ao_mes_corrente(): void
    {
        $this->comPermissoes('hr.reports.view');

        $this->funcionario(['hire_date' => now()->startOfYear()]);

        $r = $this->getJson(self::MAPAS . '?mapa=quadro_de_pessoal&ano=' . now()->year)->assertOk();

        $linhas = $r->json('linhas');

        $this->assertCount((int) now()->format('n'), $linhas, 'um mês por cada mês já começado');
        $this->assertNull($linhas[0]['variacao'], 'o primeiro mês não tem com que comparar');
        $this->assertSame(array_column($linhas, 'pessoas'), $r->json('grafico.valores'));
    }

    /* ─── O mapa de IRT ───────────────────────────────────────────────── */

    public function test_o_mapa_de_irt_pede_a_sua_permissao(): void
    {
        $this->getJson(self::MAPAS . '/irt')->assertForbidden();

        // A permissão dos relatórios NÃO abre o mapa de IRT: é o que se
        // entrega à AGT, e é uma decisão à parte.
        $this->comPermissoes('hr.reports.view');
        $this->getJson(self::MAPAS . '/irt')->assertForbidden();

        $this->comPermissoes('hr.irt.view');
        $this->getJson(self::MAPAS . '/irt')->assertOk();
    }

    /** Sem ano nem mês, abre no MÊS PASSADO — é esse que se está a preparar. */
    public function test_o_mapa_de_irt_abre_no_mes_passado(): void
    {
        $this->comPermissoes('hr.irt.view');

        $anterior = now()->subMonthNoOverflow();

        $this->getJson(self::MAPAS . '/irt')->assertOk()
            ->assertJsonPath('periodo.ano', (int) $anterior->year)
            ->assertJsonPath('periodo.mes', (int) $anterior->month);
    }

    /**
     * SÓ ENTRAM AS FOLHAS APROVADAS OU PAGAS — mas as outras MOSTRAM-SE.
     *
     * Um rascunho por aprovar é a razão nº1 de o mapa não bater com o que a
     * contabilidade espera, e descobri-lo depois de entregar é tarde.
     */
    public function test_o_rascunho_nao_entra_no_mapa_mas_e_avisado(): void
    {
        $this->comPermissoes('hr.irt.view');

        $folha = $this->folhaCom([
            ['base' => 300000, 'bruto' => 330000, 'inss' => 9900, 'irt' => 20000, 'liquido' => 300100, 'irt_base' => 320100],
        ], 'draft');

        $r = $this->getJson(self::MAPAS . "/irt?ano={$folha->year}&mes={$folha->month}")->assertOk();

        $this->assertSame([], $r->json('linhas'), 'um rascunho não entrega imposto nenhum');
        $this->assertCount(1, $r->json('ignoradas'));
        $this->assertSame($folha->payroll_number, $r->json('ignoradas.0.numero'));

        // Aprovada, entra — e some do aviso.
        $folha->update(['status' => 'approved']);

        $r = $this->getJson(self::MAPAS . "/irt?ano={$folha->year}&mes={$folha->month}")->assertOk();

        $this->assertCount(1, $r->json('linhas'));
        $this->assertSame([], $r->json('ignoradas'));
        $this->assertEquals(20000, $r->json('totais.irt'));
        $this->assertEquals(1, $r->json('totais.tributados'));
    }

    /** Quem não paga IRT sai marcado como isento, e não como zero sem explicação. */
    public function test_quem_nao_paga_irt_sai_isento(): void
    {
        $this->comPermissoes('hr.irt.view');

        $folha = $this->folhaCom([
            ['base' => 100000, 'bruto' => 100000, 'inss' => 3000, 'irt' => 0, 'liquido' => 97000, 'irt_base' => 97000],
        ], 'approved');

        $r = $this->getJson(self::MAPAS . "/irt?ano={$folha->year}&mes={$folha->month}")->assertOk();

        $this->assertTrue($r->json('linhas.0.isento'));
        $this->assertEquals(0, $r->json('linhas.0.taxa'));
        $this->assertEquals(1, $r->json('totais.isentos'));
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * Uma folha com as linhas que o ensaio pede — escritas à mão de propósito.
     *
     * O que estes mapas provam é que LÊEM o que está gravado; usar o
     * `PayrollService` para as montar tornava o ensaio dependente do cálculo,
     * que tem os seus próprios.
     *
     * @param  array<int, array<string, mixed>>  $linhas
     */
    private function folhaCom(array $linhas, string $estado = 'approved'): Payroll
    {
        $mes = now()->subMonthNoOverflow();

        $folha = Payroll::create([
            'tenant_id' => $this->tenant->id,
            'payroll_number' => 'FP-' . uniqid(),
            'year' => (int) $mes->year,
            'month' => (int) $mes->month,
            'period_start' => $mes->copy()->startOfMonth(),
            'period_end' => $mes->copy()->endOfMonth(),
            'status' => $estado,
            'total_employees' => count($linhas),
            'processed_employees' => count($linhas),
        ]);

        foreach ($linhas as $l) {
            $e = $this->funcionario(['department_id' => $l['departamento'] ?? null]);

            PayrollItem::create([
                'payroll_id' => $folha->id,
                'employee_id' => $e->id,
                'base_salary' => $l['base'],
                'food_allowance' => $l['alimentacao'] ?? 0,
                'transport_allowance' => $l['transporte'] ?? 0,
                'gross_salary' => $l['bruto'],
                'inss_employee' => $l['inss'],
                'inss_employer' => $l['empresa'] ?? 0,
                'irt_base' => $l['irt_base'] ?? $l['bruto'],
                'irt_amount' => $l['irt'],
                'total_deductions' => $l['inss'] + $l['irt'],
                'net_salary' => $l['liquido'],
                'status' => 'calculated',
            ]);
        }

        return $folha;
    }
}
