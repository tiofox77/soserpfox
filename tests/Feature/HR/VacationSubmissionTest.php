<?php

namespace Tests\Feature\HR;

use App\Livewire\HR\VacationManagement;
use App\Models\HR\Employee;
use App\Models\HR\Vacation;
use Livewire\Livewire;
use Tests\TenantTestCase;

class VacationSubmissionTest extends TenantTestCase
{
    public function test_employee_can_submit_vacation_in_active_tenant(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-04'));
        $employee = Employee::create(['tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(), 'first_name' => 'Teste',
            'last_name' => 'QA', 'full_name' => 'Teste QA', 'status' => 'active',
            'hire_date' => '2024-01-01', 'base_salary' => 150000]);
        $screen = Livewire::test(VacationManagement::class)->call('create')
            ->set('employee_id', $employee->id)->set('start_date', '2026-09-14')
            ->set('end_date', '2026-09-18')->call('save')->assertHasNoErrors();
        $this->assertNull(session('error'), session('error') ?? '');
        $screen->assertSet('showModal', false);
        $vacation = Vacation::where('employee_id', $employee->id)->firstOrFail();
        $this->assertEquals($this->tenant->id, $vacation->tenant_id);
        $this->assertEquals(5, $vacation->working_days);
        $this->assertSame('pending', $vacation->status);
        $this->assertEquals(109090.91, $vacation->total_amount);
    }
}
