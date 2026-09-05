<?php

namespace Tests\Feature\HR;

use App\Models\HR\Employee;
use App\Models\HR\Overtime;
use App\Livewire\HR\OvertimeNightShiftManagement;
use App\Services\HR\OvertimeService;
use Livewire\Livewire;
use Tests\TenantTestCase;

class OvertimeInputModesTest extends TenantTestCase
{
    public function test_regular_alias_and_direct_hours_are_stored_with_valid_enum_values(): void
    {
        $employee = Employee::create(['tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(), 'first_name' => 'Teste',
            'last_name' => 'QA', 'full_name' => 'Teste QA', 'status' => 'active',
            'base_salary' => 150000]);
        $record = (new OvertimeService)->createOvertimeRecord([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'date' => '2026-09-07', 'input_type' => 'daily_hours',
            'direct_hours' => 2, 'overtime_type' => 'regular',
            'description' => 'Teste de horas directas',
        ]);
        $this->assertSame('daily', $record->input_type);
        $this->assertSame('weekday', $record->overtime_type);
        $this->assertNull($record->start_time);
        $this->assertNull($record->end_time);
        $this->assertEquals(2, $record->total_hours);
        $this->assertEquals(2556.82, $record->total_amount);
    }

    public function test_night_shift_screen_populates_legacy_and_current_rate_columns(): void
    {
        $employee = Employee::create(['tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(), 'first_name' => 'Turno',
            'last_name' => 'Noturno', 'full_name' => 'Turno Noturno', 'status' => 'active',
            'base_salary' => 150000]);

        Livewire::test(OvertimeNightShiftManagement::class)
            ->set('employee_id', $employee->id)
            ->set('date', '2026-09-01')
            ->set('night_days', 2)
            ->set('description', 'Teste automatizado de turno noturno')
            ->call('save')
            ->assertHasNoErrors();

        $record = Overtime::where('employee_id', $employee->id)
            ->where('is_night_shift', true)
            ->firstOrFail();

        $this->assertSame('night', $record->overtime_type);
        $this->assertEquals(2, $record->direct_hours);
        $this->assertGreaterThan(0, (float) $record->overtime_rate);
        $this->assertEquals($record->overtime_rate, $record->rate);
        $this->assertEquals($record->amount, $record->total_amount);
    }
}
