<?php

namespace Tests\Feature\HR;

use App\Models\HR\Employee;
use App\Models\HR\Vacation;
use Tests\TenantTestCase;

/**
 * PEDIR FÉRIAS — e o que o `VacationService` calcula ao gravar.
 *
 * Era um ensaio contra o componente Livewire (`VacationManagement`), que
 * deixou de existir com a migração para React. Passou para a API, que é a
 * mesma porta que o ecrã novo usa — e continua a medir o que interessa: os
 * dias úteis contados e o subsídio calculado.
 */
class VacationSubmissionTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/rh/pedidos/ferias';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('rh');
    }

    public function test_employee_can_submit_vacation_in_active_tenant(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-04'));
        $this->comPermissoes('hr.vacations.view', 'hr.vacations.create');

        $employee = Employee::create(['tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(), 'first_name' => 'Teste',
            'last_name' => 'QA', 'full_name' => 'Teste QA', 'status' => 'active',
            'hire_date' => '2024-01-01', 'base_salary' => 150000]);

        $this->postJson(self::RAIZ, [
            'employee_id' => $employee->id,
            'reference_year' => 2026,
            'vacation_type' => 'normal',
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-18',
        ])->assertCreated();

        $vacation = Vacation::where('employee_id', $employee->id)->firstOrFail();

        $this->assertEquals($this->tenant->id, $vacation->tenant_id);
        $this->assertEquals(5, $vacation->working_days);
        $this->assertSame('pending', $vacation->status);
        $this->assertEquals(109090.91, $vacation->total_amount);
    }
}
