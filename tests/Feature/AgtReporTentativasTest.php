<?php

namespace Tests\Feature;

use App\Models\AGT\AGTSubmission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TenantTestCase;

/**
 * agt:repor-tentativas — devolve tentativas a quem as gastou por culpa nossa.
 *
 * A seco não toca em nada; com --aplicar repõe só as pendentes/recusadas da
 * empresa (e só as que gastaram tentativas); uma validada é história e fica
 * como está.
 */
class AgtReporTentativasTest extends TenantTestCase
{
    use DatabaseTransactions;

    private function submissao(string $numero, string $estado, int $tentativas): AGTSubmission
    {
        return AGTSubmission::create([
            'tenant_id' => $this->tenant->id,
            'agt_environment' => 'production',
            'document_type' => 'App\\Models\\Invoicing\\SalesInvoice',
            'document_id' => random_int(100000, 999999),
            'document_number' => $numero,
            'document_type_code' => 'FT',
            'status' => $estado,
            'retry_count' => $tentativas,
            'error_code' => $tentativas ? 'COMMS' : null,
            'error_message' => $tentativas ? 'A versão 1.2 do schema já não é suportada.' : null,
        ]);
    }

    /** @test */
    public function a_seco_nao_toca_em_nada(): void
    {
        $s = $this->submissao('FT A/000002', AGTSubmission::STATUS_PENDING, 5);

        $this->artisan('agt:repor-tentativas', ['--tenant' => $this->tenant->id])
            ->expectsOutputToContain('A SECO')
            ->assertExitCode(0);

        $this->assertSame(5, (int) $s->fresh()->retry_count);
        $this->assertSame('COMMS', $s->fresh()->error_code);
    }

    /** @test */
    public function com_aplicar_repoe_as_esgotadas_e_deixa_as_validadas(): void
    {
        $esgotada = $this->submissao('FT A/000002', AGTSubmission::STATUS_PENDING, 5);
        $recusada = $this->submissao('FT A/000003', AGTSubmission::STATUS_REJECTED, 2);
        $validada = $this->submissao('FT A/000001', AGTSubmission::STATUS_VALIDATED, 3);

        $this->artisan('agt:repor-tentativas', ['--tenant' => $this->tenant->id, '--aplicar' => true])
            ->assertExitCode(0);

        foreach ([$esgotada, $recusada] as $s) {
            $s->refresh();
            $this->assertSame(0, (int) $s->retry_count);
            $this->assertSame(AGTSubmission::STATUS_PENDING, $s->status);
            $this->assertNull($s->error_code);
            $this->assertNull($s->error_message);
        }

        $validada->refresh();
        $this->assertSame(3, (int) $validada->retry_count, 'uma validada não se mexe');
        $this->assertSame(AGTSubmission::STATUS_VALIDATED, $validada->status);
    }

    /** @test */
    public function o_filtro_por_documento_so_apanha_esse(): void
    {
        $alvo = $this->submissao('FT A/000002', AGTSubmission::STATUS_PENDING, 5);
        $outra = $this->submissao('FT A/000009', AGTSubmission::STATUS_PENDING, 5);

        $this->artisan('agt:repor-tentativas', [
            '--tenant' => $this->tenant->id, '--doc' => '000002', '--aplicar' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, (int) $alvo->fresh()->retry_count);
        $this->assertSame(5, (int) $outra->fresh()->retry_count);
    }

    /** Só a empresa pedida — nunca as outras. */
    public function test_nao_toca_noutras_empresas(): void
    {
        $outraEmpresa = \App\Models\Tenant::create([
            'name' => 'Outra '.uniqid(), 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999), 'email' => uniqid().'@x.ao',
        ]);
        $alheia = AGTSubmission::create([
            'tenant_id' => $outraEmpresa->id, 'agt_environment' => 'production',
            'document_type' => 'App\\Models\\Invoicing\\SalesInvoice', 'document_id' => 1,
            'document_number' => 'FT B/000001', 'document_type_code' => 'FT',
            'status' => AGTSubmission::STATUS_PENDING, 'retry_count' => 5, 'error_code' => 'COMMS',
        ]);

        $this->artisan('agt:repor-tentativas', ['--tenant' => $this->tenant->id, '--aplicar' => true])
            ->assertExitCode(0);

        $this->assertSame(5, (int) $alheia->fresh()->retry_count);
    }
}
