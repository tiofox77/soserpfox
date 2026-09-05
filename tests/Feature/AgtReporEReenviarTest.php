<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\AGTSettings;
use App\Models\AGT\AGTSubmission;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Submissão com as tentativas gastas: o ecrã dá o botão «Repor e reenviar».
 *
 * 2026-09-02: a Free Dation ficou com a FT …/000002 pendente, 5 tentativas
 * gastas pela recusa do schema 1.2, e o ecrã mostrava «—» em vez de um
 * botão — não havia maneira de reenviar sem ir à base de dados.
 */
class AgtReporEReenviarTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.agt.edit');

        // Nada sai para a AGT a partir de um ensaio.
        Http::fake(['*' => Http::response(['requestID' => 'x', 'resultCode' => '0'], 200)]);
    }

    private function submissao(string $estado, int $tentativas): AGTSubmission
    {
        return AGTSubmission::create([
            'tenant_id' => $this->tenant->id,
            'agt_environment' => \App\Models\Invoicing\InvoicingSettings::forTenant($this->tenant->id)->agt_environment,
            'document_type' => 'App\\Models\\Invoicing\\SalesInvoice',
            'document_id' => 999999999,
            'document_number' => 'FT A/000002',
            'document_type_code' => 'FT',
            'status' => $estado,
            'retry_count' => $tentativas,
            'error_code' => 'COMMS',
            'error_message' => 'A versão 1.2 do schema já não é suportada.',
        ]);
    }

    /** @test */
    public function uma_submissao_esgotada_mostra_o_botao_de_repor(): void
    {
        $this->submissao(AGTSubmission::STATUS_PENDING, 5);

        Livewire::test(AGTSettings::class)
            ->set('activeTab', 'submissions')
            ->assertSee('Repor e reenviar');
    }

    /** @test */
    public function uma_validada_nao_mostra_botao_nenhum(): void
    {
        $this->submissao(AGTSubmission::STATUS_VALIDATED, 3);

        Livewire::test(AGTSettings::class)
            ->set('activeTab', 'submissions')
            ->assertDontSee('Repor e reenviar')
            ->assertDontSee('retrySubmission(');
    }

    /** @test */
    public function repor_zera_o_contador_e_tenta_reenviar_no_acto(): void
    {
        $s = $this->submissao(AGTSubmission::STATUS_PENDING, 5);

        Livewire::test(AGTSettings::class)->call('reporEReenviar', $s->id);

        $s->refresh();
        $this->assertSame(0, (int) $s->retry_count, 'o contador tinha de voltar a zero');
        $this->assertNull($s->error_code, 'o erro antigo tinha de ser apagado');
        // O documento deste ensaio não existe: o reenvio pára aí, mas o
        // contador já está reposto — é isso que o botão promete.
        $this->assertSame(AGTSubmission::STATUS_PENDING, $s->status);
    }

    /** @test */
    public function nao_repoe_uma_validada(): void
    {
        $s = $this->submissao(AGTSubmission::STATUS_VALIDATED, 3);

        Livewire::test(AGTSettings::class)->call('reporEReenviar', $s->id);

        $this->assertSame(3, (int) $s->fresh()->retry_count);
        $this->assertSame(AGTSubmission::STATUS_VALIDATED, $s->fresh()->status);
    }

    /** Uma submissão de OUTRA empresa não se toca, nem por id. */
    public function test_nao_repoe_a_de_outra_empresa(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra '.uniqid(), 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999), 'email' => uniqid().'@x.ao',
        ]);
        $alheia = AGTSubmission::create([
            'tenant_id' => $outra->id, 'agt_environment' => 'production',
            'document_type' => 'App\\Models\\Invoicing\\SalesInvoice', 'document_id' => 1,
            'document_number' => 'FT B/000001', 'document_type_code' => 'FT',
            'status' => AGTSubmission::STATUS_PENDING, 'retry_count' => 5, 'error_code' => 'COMMS',
        ]);

        Livewire::test(AGTSettings::class)->call('reporEReenviar', $alheia->id);

        $this->assertSame(5, (int) $alheia->fresh()->retry_count);
    }
}
