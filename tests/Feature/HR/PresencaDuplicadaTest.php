<?php

namespace Tests\Feature\HR;

use App\Livewire\HR\AttendanceManagement;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use Livewire\Livewire;
use Tests\TenantTestCase;

class PresencaDuplicadaTest extends TenantTestCase
{
    public function test_duplicate_manual_entry_is_rejected_but_edit_is_allowed(): void
    {
        $employee = Employee::create(['tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(), 'first_name' => 'Teste',
            'last_name' => 'QA', 'full_name' => 'Teste QA', 'status' => 'active']);
        $screen = Livewire::test(AttendanceManagement::class)
            ->set('employee_id', $employee->id)->set('date', '2026-09-04')
            ->set('check_in', '08:00')->set('check_out', '16:00')->call('save');
        $record = Attendance::where('employee_id', $employee->id)->firstOrFail();
        $this->assertEquals(8, $record->hours_worked);
        $screen->call('create')->set('employee_id', $employee->id)->set('date', '2026-09-04')
            ->set('check_in', '08:00')->set('check_out', '16:00')->call('save')
            ->assertSet('showModal', true);
        $this->assertSame(1, Attendance::where('employee_id', $employee->id)->count());
        $screen->call('edit', $record->id)->set('check_out', '12:00')->call('save');
        $this->assertEquals(4, $record->fresh()->hours_worked);
    }
}
