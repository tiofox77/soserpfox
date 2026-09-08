<?php

namespace Tests\Feature\HR;

use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use Tests\TenantTestCase;

/**
 * UMA PESSOA TEM UM PONTO POR DIA — mas o que lá está edita-se.
 *
 * Duas linhas para o mesmo dia contavam a presença duas vezes na folha. Era um
 * ensaio contra o componente Livewire, que deixou de existir com a migração
 * para React; passou para a API, que é a mesma porta que o ecrã novo usa.
 */
class PresencaDuplicadaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/rh/presencas';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('rh');
        $this->comPermissoes('attendance.manage');
    }

    public function test_duplicate_manual_entry_is_rejected_but_edit_is_allowed(): void
    {
        $employee = Employee::create(['tenant_id' => $this->tenant->id,
            'employee_number' => 'QA-'.uniqid(), 'first_name' => 'Teste',
            'last_name' => 'QA', 'full_name' => 'Teste QA', 'status' => 'active']);

        $ponto = [
            'employee_id' => $employee->id,
            'date' => '2026-09-04',
            'check_in' => '08:00',
            'check_out' => '16:00',
            'status' => 'present',
        ];

        $this->postJson(self::RAIZ, $ponto)->assertCreated();

        $record = Attendance::where('employee_id', $employee->id)->firstOrFail();
        $this->assertEquals(8, $record->hours_worked);

        // O SEGUNDO PONTO DO MESMO DIA É RECUSADO, e diz-se em que campo.
        $this->postJson(self::RAIZ, $ponto)
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');

        $this->assertSame(1, Attendance::where('employee_id', $employee->id)->count());

        // Mas o que lá está edita-se — e as horas são recontadas.
        $this->putJson(self::RAIZ . '/' . $record->id, array_merge($ponto, ['check_out' => '12:00']))->assertOk();

        $this->assertEquals(4, $record->fresh()->hours_worked);
    }
}
