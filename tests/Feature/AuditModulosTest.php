<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\HR\Employee;
use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use App\Models\Invoicing\PosShift;
use App\Models\Treasury\Transaction;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Services\Audit\AuditRecorder;
use Tests\TenantTestCase;

/**
 * Estar na allowlist não é o mesmo que ESCREVER.
 *
 * O `AuditAllowlistTest` garante que a lista está bem formada; estes ensaios
 * pegam num acto verdadeiro de cada módulo e verificam que a linha aparece
 * mesmo na trilha. É a diferença entre prometer cobertura e ter cobertura —
 * e a auditoria falha em silêncio, portanto a promessa não se vê a olho.
 */
class AuditModulosTest extends TenantTestCase
{
    private function trilha(): \Illuminate\Support\Collection
    {
        app(AuditRecorder::class)->despejar();

        return AuditTrail::where('tenant_id', $this->tenant->id)->get();
    }

    private function assertAuditado(string $classe, string $oQue): void
    {
        $this->assertTrue(
            $this->trilha()->contains(fn ($l) => $l->auditable_type === $classe),
            $oQue.' não deixou rasto na trilha'
        );
    }

    // ── Tesouraria ───────────────────────────────────────────────────────

    /** @test */
    public function tesouraria_um_movimento_de_dinheiro_fica_auditado(): void
    {
        Transaction::criar([
            'tenant_id' => $this->tenant->id,
            'type' => 'income',
            'amount' => 25000,
            'description' => 'Entrada de teste',
            'transaction_date' => now()->toDateString(),
            'user_id' => $this->user->id,
        ]);

        $this->assertAuditado(Transaction::class, 'uma transacção de tesouraria');
    }

    /** O fecho de turno mexe em dinheiro contado — tem de deixar rasto. */
    public function test_tesouraria_um_turno_de_caixa_fica_auditado(): void
    {
        PosShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'shift_number' => 'T'.random_int(1000, 9999),
            'opening_balance' => 5000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->assertAuditado(PosShift::class, 'a abertura de um turno');
    }

    // ── Recursos Humanos ─────────────────────────────────────────────────

    /**
     * O RH mexe no que uma pessoa recebe ao fim do mês.
     *
     * @test
     */
    public function rh_o_funcionario_e_o_seu_salario_ficam_auditados(): void
    {
        $funcionario = Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'F'.random_int(1000, 9999),
            'first_name' => 'Ana',
            'last_name' => 'Teste',
        ]);

        $this->assertAuditado(Employee::class, 'criar um funcionário');

        // E a alteração guarda o antes e o depois — que é a pergunta real:
        // não "mudou?", mas "de quanto para quanto?".
        $funcionario->update(['last_name' => 'Trocado']);

        $linha = $this->trilha()
            ->where('auditable_type', Employee::class)
            ->firstWhere('event', 'updated');

        $this->assertNotNull($linha);
        $this->assertSame('Teste', $linha->old_values['last_name'] ?? null);
        $this->assertSame('Trocado', $linha->new_values['last_name'] ?? null);
    }

    /**
     * O processamento salarial — o documento com mais consequência do RH.
     *
     * A folha (`Payroll`) é o lote do mês; a linha por pessoa é o
     * `PayrollItem`, que NÃO tem `tenant_id` e sobe à folha pela relação
     * `payroll`. É por isso que os dois entram aqui: se a relação-pai se
     * partir, a linha do salário de cada pessoa some da trilha em silêncio.
     */
    public function test_rh_a_folha_e_a_linha_de_cada_pessoa_ficam_auditadas(): void
    {
        $funcionario = Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'F'.random_int(1000, 9999),
            'first_name' => 'Bruno',
            'last_name' => 'Folha',
        ]);

        $folha = Payroll::create([
            'tenant_id' => $this->tenant->id,
            'payroll_number' => 'FP'.random_int(1000, 9999),
            'month' => (int) now()->format('m'),
            'year' => (int) now()->format('Y'),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => 'draft',
        ]);

        $this->assertAuditado(Payroll::class, 'uma folha de salários');

        PayrollItem::create([
            'payroll_id' => $folha->id,
            'employee_id' => $funcionario->id,
            'base_salary' => 150000,
            'net_salary' => 140000,
        ]);

        $linha = $this->trilha()->firstWhere('auditable_type', PayrollItem::class);

        $this->assertNotNull($linha, 'a linha do salário de cada pessoa tem de deixar rasto');
        $this->assertSame($this->tenant->id, $linha->tenant_id,
            'sem tenant_id próprio, tem de subir à folha para achar a empresa');
    }

    // ── Oficina ──────────────────────────────────────────────────────────

    /** @test */
    public function oficina_a_ordem_de_reparacao_fica_auditada(): void
    {
        $viatura = Vehicle::create([
            'tenant_id' => $this->tenant->id,
            'vehicle_number' => 'V'.random_int(1000, 9999),
            'plate' => 'LD-'.random_int(10, 99).'-'.random_int(10, 99).'-AB',
            'owner_name' => 'Cliente da Oficina',
            'brand' => 'Toyota',
            'model' => 'Hilux',
        ]);

        $this->assertAuditado(Vehicle::class, 'registar uma viatura');

        WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'OR'.random_int(1000, 9999),
            'vehicle_id' => $viatura->id,
            'received_at' => now(),
            'problem_description' => 'Não trava.',
        ]);

        $this->assertAuditado(WorkOrder::class, 'abrir uma ordem de reparação');
    }
}
