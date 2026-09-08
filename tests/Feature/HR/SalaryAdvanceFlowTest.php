<?php

namespace Tests\Feature\HR;

use App\Models\HR\Employee;
use App\Models\HR\SalaryAdvance;
use App\Services\HR\SalaryAdvanceService;
use Tests\TenantTestCase;

/**
 * O ADIANTAMENTO SALARIAL: o serviço, e a porta por onde o ecrã o pede.
 *
 * O segundo ensaio era contra o componente Livewire, que deixou de existir;
 * passou para a API, que chama o MESMO serviço — é ele que aplica o tecto e
 * reparte pelas prestações.
 */
class SalaryAdvanceFlowTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/rh/pedidos/adiantamentos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('rh');
    }

    public function test_advance_service_creates_a_pending_request(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(),
            'first_name' => 'Serviço',
            'last_name' => 'QA',
            'full_name' => 'Serviço QA',
            'status' => 'active',
            'base_salary' => 150000,
        ]);

        $advance = app(SalaryAdvanceService::class)->createAdvanceRequest([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'requested_amount' => 10000,
            'installments' => 2,
            'reason' => 'Teste automatizado do serviço de adiantamento',
            'notes' => 'Sem pagamento real',
        ]);

        $this->assertSame('pending', $advance->status);
        $this->assertEquals(5000, $advance->installment_amount);

        $limits = app(SalaryAdvanceService::class)->calculateMaxAllowed($employee);
        $this->assertEquals(10000, $limits['pending_balance']);
        $this->assertEquals(65000, $limits['available_amount']);
    }

    public function test_advance_request_is_created_from_the_api(): void
    {
        $this->comPermissoes('hr.advances.view', 'hr.advances.create');

        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(),
            'first_name' => 'Adiantamento',
            'last_name' => 'QA',
            'full_name' => 'Adiantamento QA',
            'status' => 'active',
            'base_salary' => 150000,
        ]);

        $this->postJson(self::RAIZ, [
            'employee_id' => $employee->id,
            'requested_amount' => 10000,
            'installments' => 2,
            'reason' => 'Teste automatizado de adiantamento salarial',
            'notes' => 'Sem pagamento real',
        ])->assertCreated();

        $advance = SalaryAdvance::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame('pending', $advance->status);
        $this->assertEquals(150000, $advance->base_salary);
        $this->assertEquals(10000, $advance->requested_amount);
        $this->assertEquals(5000, $advance->installment_amount);
        $this->assertSame(2, $advance->installments);
    }
}
