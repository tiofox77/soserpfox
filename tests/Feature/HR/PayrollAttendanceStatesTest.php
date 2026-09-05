<?php

namespace Tests\Feature\HR;

use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Services\HR\PayrollService;
use Carbon\Carbon;
use Tests\TenantTestCase;

class PayrollAttendanceStatesTest extends TenantTestCase
{
    public function test_recalculation_preserves_draft_and_half_days_without_contract(): void
    {
        $employee = Employee::create(['tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(), 'first_name' => 'Teste',
            'last_name' => 'QA', 'full_name' => 'Teste QA', 'status' => 'active', 'base_salary' => 150000]);
        Attendance::create(['tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id, 'date' => '2026-09-03', 'status' => 'half_day']);
        Attendance::create(['tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id, 'date' => '2026-09-04', 'status' => 'present']);
        $service = new PayrollService;
        $payroll = $service->createPayroll($this->tenant->id, 2026, 9);
        $before = $payroll->items()->firstOrFail();
        $this->assertEquals(1.5, $before->worked_days);
        $service->processPayroll($payroll, false);
        $payroll->refresh();
        $this->assertSame('draft', $payroll->status);
        $this->assertEquals(1, $payroll->processed_employees);
        $this->assertEquals($before->net_salary, $before->fresh()->net_salary);
        $this->assertEquals(1.5, $before->fresh()->worked_days);
    }

    public function test_late_and_half_day_are_not_full_absences(): void
    {
        $employee = Employee::create(['tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(), 'first_name' => 'Teste',
            'last_name' => 'QA', 'full_name' => 'Teste QA', 'status' => 'active']);
        foreach (['present', 'late', 'half_day'] as $index => $status) {
            Attendance::create(['tenant_id' => $this->tenant->id,
                'employee_id' => $employee->id, 'date' => '2026-09-0'.($index + 1),
                'status' => $status]);
        }
        $method = new \ReflectionMethod(PayrollService::class, 'calculateAttendanceData');
        $data = $method->invoke(new PayrollService, $employee->id,
            Carbon::parse('2026-09-01'), Carbon::parse('2026-09-03'));
        $this->assertEquals(2.5, $data['paid_days']);
        $this->assertEquals(2.5, $data['worked_days']);
        $this->assertEquals(0.5, $data['deductible_absences']);
        $this->assertEquals(1, $data['late_count']);
    }
}
