<?php

namespace Tests\Feature\HR;

use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Services\HR\VacationService;
use Tests\TenantTestCase;

/**
 * As três contas dos mapas que já tinham dado defeito.
 *
 * O ecrã era Livewire e passou para React; estes ensaios seguiram-no para a
 * API, que é a mesma porta que o ecrã novo usa. O que medem não mudou: o meio
 * dia vale meio na assiduidade, o direito a férias vem do serviço (e é
 * proporcional a quem entrou a meio do ano), e o mês corrente aparece no
 * quadro de pessoal antes de acabar.
 */
class HRReportsAttendanceTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/rh/relatorios';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('rh')->comPermissoes('hr.reports.view');
    }

    public function test_summary_counts_half_days_and_justified_leave_in_the_rate(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(),
            'first_name' => 'Relatório',
            'last_name' => 'QA',
            'full_name' => 'Relatório QA',
            'status' => 'active',
            'hire_date' => now()->subYear(),
            'base_salary' => 150000,
        ]);

        foreach (['present', 'half_day', 'on_leave'] as $day => $status) {
            Attendance::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $employee->id,
                'date' => now()->startOfMonth()->addDays($day),
                'status' => $status,
            ]);
        }

        $r = $this->getJson(self::RAIZ . '?mapa=resumo_de_presencas&ano=' . now()->year . '&mes=' . now()->month)
            ->assertOk();

        $linha = collect($r->json('linhas'))->firstWhere('id', $employee->id);

        $this->assertNotNull($linha);
        $this->assertEquals(1.5, $linha['equivalente'], 'um presente mais meio dia');
        $this->assertEquals(1, $linha['meio_dia']);
        $this->assertEquals(1, $linha['justificadas'], 'a licença conta como justificada');
        // (1,5 trabalhados + 1 justificada) de 3 dias marcados.
        $this->assertEquals(83.3, $linha['taxa']);
    }

    public function test_vacation_balance_uses_the_prorated_service_entitlement(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(),
            'first_name' => 'Férias',
            'last_name' => 'Proporcionais',
            'full_name' => 'Férias Proporcionais',
            'status' => 'active',
            'hire_date' => now()->startOfYear()->addMonths(6),
            'base_salary' => 150000,
        ]);

        $esperado = app(VacationService::class)
            ->getAvailableVacationDays($employee, (int) now()->year)['entitled'];

        $this->assertLessThan(22, $esperado);

        $r = $this->getJson(self::RAIZ . '?mapa=saldo_de_ferias&ano=' . now()->year)->assertOk();

        $linha = collect($r->json('linhas'))->firstWhere('id', $employee->id);

        $this->assertNotNull($linha);
        $this->assertSame('Férias Proporcionais', $linha['nome']);
        $this->assertEquals($esperado, $linha['direito'], 'o direito vem do serviço, não é reimplementado no mapa');
    }

    public function test_headcount_includes_the_current_partial_month(): void
    {
        Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(),
            'first_name' => 'Quadro',
            'last_name' => 'Actual',
            'full_name' => 'Quadro Actual',
            'status' => 'active',
            'hire_date' => now()->startOfMonth(),
            'base_salary' => 150000,
        ]);

        $r = $this->getJson(self::RAIZ . '?mapa=quadro_de_pessoal&ano=' . now()->year)->assertOk();

        $linhas = collect($r->json('linhas'));
        $corrente = $linhas->firstWhere('mes', (int) now()->month);

        $this->assertNotNull($corrente, 'o mês em curso aparece antes de acabar');
        $this->assertGreaterThanOrEqual(1, $corrente['pessoas']);
    }
}
