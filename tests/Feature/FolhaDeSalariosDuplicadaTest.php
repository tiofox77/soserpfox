<?php

namespace Tests\Feature;

use App\Exceptions\HR\FolhaJaExiste;
use App\Models\HR\Payroll;
use App\Services\HR\PayrollService;
use Tests\TenantTestCase;

/**
 * Criar duas vezes a folha do mesmo mês.
 *
 * Havia um índice único (tenant_id, year, month) e nenhuma verificação: a
 * segunda tentativa rebentava e o utilizador via o erro cru da base —
 * "Duplicate entry '70-2026-8' for key hr_payrolls_tenant_id_year_month_unique".
 */
class FolhaDeSalariosDuplicadaTest extends TenantTestCase
{
    private function criar(int $ano = 2026, int $mes = 8): Payroll
    {
        return app(PayrollService::class)->createPayroll($this->tenant->id, $ano, $mes);
    }

    public function test_a_primeira_folha_do_mes_cria_se(): void
    {
        $folha = $this->criar();

        $this->assertNotNull($folha->id);
        $this->assertSame(2026, (int) $folha->year);
        $this->assertSame(8, (int) $folha->month);
    }

    public function test_a_segunda_folha_do_mesmo_mes_e_recusada_com_explicacao(): void
    {
        $primeira = $this->criar();

        try {
            $this->criar();
            $this->fail('a segunda folha do mesmo mês não devia ser criada');
        } catch (FolhaJaExiste $e) {
            // A mensagem tem de servir a quem está do outro lado.
            $this->assertStringContainsString('Já existe uma folha', $e->getMessage());
            $this->assertStringContainsString($primeira->payroll_number, $e->getMessage());

            // E nada de SQL cru.
            $this->assertStringNotContainsString('Duplicate entry', $e->getMessage());
            $this->assertStringNotContainsString('SQLSTATE', $e->getMessage());

            // Aponta a folha que já lá está, para o ecrã poder levar lá o utilizador.
            $this->assertNotNull($e->folha);
            $this->assertSame($primeira->id, $e->folha->id);
        }
    }

    public function test_nao_fica_uma_segunda_folha_na_base(): void
    {
        $this->criar();

        try {
            $this->criar();
        } catch (FolhaJaExiste $e) {
            // esperado
        }

        $this->assertSame(1, Payroll::where('tenant_id', $this->tenant->id)
            ->where('year', 2026)->where('month', 8)->count());
    }

    public function test_outro_mes_cria_se_sem_problema(): void
    {
        $this->criar(2026, 8);
        $setembro = $this->criar(2026, 9);

        $this->assertNotNull($setembro->id);
        $this->assertSame(9, (int) $setembro->month);
    }
}
