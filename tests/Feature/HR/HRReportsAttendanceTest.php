<?php

namespace Tests\Feature\HR;

use App\Livewire\HR\HRReports;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Services\HR\VacationService;
use Livewire\Livewire;
use Tests\TenantTestCase;

class HRReportsAttendanceTest extends TenantTestCase
{
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

        Livewire::test(HRReports::class)
            ->set('reportType', 'attendance_summary')
            ->assertSee('Equiv. trabalhado')
            ->assertSee('Meio-dia')
            ->assertSee('1,5')
            ->assertSee('83,3%');
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

        $expected = app(VacationService::class)
            ->getAvailableVacationDays($employee, (int) now()->year)['entitled'];

        $this->assertLessThan(22, $expected);

        Livewire::test(HRReports::class)
            ->set('reportType', 'vacation_balance')
            ->assertSee('Férias Proporcionais')
            ->assertSee((string) $expected);
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

        Livewire::test(HRReports::class)
            ->set('reportType', 'headcount')
            ->assertSee(ucfirst(now()->locale('pt_BR')->monthName));
    }
}
