<?php

namespace Tests\Feature\HR;

use App\Livewire\HR\SalaryAdvanceManagement;
use App\Models\HR\Employee;
use App\Models\HR\SalaryAdvance;
use App\Services\HR\SalaryAdvanceService;
use Livewire\Livewire;
use Tests\TenantTestCase;

class SalaryAdvanceFlowTest extends TenantTestCase
{
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

    public function test_advance_request_is_created_from_the_screen(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(),
            'first_name' => 'Adiantamento',
            'last_name' => 'QA',
            'full_name' => 'Adiantamento QA',
            'status' => 'active',
            'base_salary' => 150000,
        ]);

        Livewire::test(SalaryAdvanceManagement::class)
            ->set('employee_id', $employee->id)
            ->set('requested_amount', 10000)
            ->set('installments', 2)
            ->set('reason', 'Teste automatizado de adiantamento salarial')
            ->set('notes', 'Sem pagamento real')
            ->call('save')
            ->assertHasNoErrors();

        $advance = SalaryAdvance::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame('pending', $advance->status);
        $this->assertEquals(150000, $advance->base_salary);
        $this->assertEquals(10000, $advance->requested_amount);
        $this->assertEquals(5000, $advance->installment_amount);
        $this->assertSame(2, $advance->installments);
    }
}
