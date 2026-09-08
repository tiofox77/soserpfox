<?php

namespace Tests\Feature\HR;

use App\Models\HR\Employee;
use App\Models\HR\Overtime;
use App\Services\HR\OvertimeService;
use Tests\TenantTestCase;

/**
 * OS MODOS DE LANÇAR HORAS — e o turno nocturno, que não é uma hora extra.
 *
 * O segundo ensaio era contra o componente Livewire do turno nocturno, que
 * deixou de existir. A conta que ele fazia por dentro mudou-se para o
 * `OvertimeService::createNightShiftRecord()` — onde a API e este ensaio lhe
 * chegam — e é lá que continua a ser medida.
 */
class OvertimeInputModesTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/rh/pedidos/turno-nocturno';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('rh');
    }

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

    /**
     * O TURNO NOCTURNO conta NOITES, e não horas — e escreve as colunas
     * antigas (`rate`, `amount`) ao lado das novas, porque há relatórios que
     * lêem umas e relatórios que lêem as outras.
     */
    public function test_night_shift_populates_legacy_and_current_rate_columns(): void
    {
        $this->comPermissoes('hr.overtime.view', 'hr.overtime.create');

        $employee = Employee::create(['tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(), 'first_name' => 'Turno',
            'last_name' => 'Noturno', 'full_name' => 'Turno Noturno', 'status' => 'active',
            'base_salary' => 150000]);

        $this->postJson(self::RAIZ, [
            'employee_id' => $employee->id,
            'date' => '2026-09-01',
            'night_days' => 2,
            'description' => 'Teste automatizado de turno noturno',
        ])->assertCreated();

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
