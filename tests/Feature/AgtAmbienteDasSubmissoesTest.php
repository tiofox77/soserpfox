<?php

namespace Tests\Feature;

use App\Jobs\AGT\PollAGTStatusJob;
use App\Models\AGT\AGTSubmission;
use App\Services\AGT\AGTService;
use App\Services\AGT\DespachoPendentes;
use App\Services\AGT\GestaoAgt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AgtDeEnsaio;
use Tests\TenantTestCase;

/**
 * UMA SUBMISSÃO DE OUTRO AMBIENTE FICA QUIETA.
 *
 * O defeito (auditoria de 2026-09-14): voltar a homologação com documentos de
 * PRODUÇÃO por enviar. O despacho, a consulta do estado e o reenvio falam
 * sempre com a AGT do ambiente ACTIVO — e mandavam esses documentos para a
 * AGT de testes, que os aceitava. Ficavam «validados» sem nunca terem
 * existido para o fisco.
 *
 * Fechou-se na raiz: cada porta que envia ou pergunta só actua nas submissões
 * do ambiente activo. Aqui prova-se porta a porta, sempre com a AGT falsa a
 * responder «validado» — se alguma deixasse passar, o ensaio via-o.
 */
class AgtAmbienteDasSubmissoesTest extends TenantTestCase
{
    use AgtDeEnsaio;

    private const RAIZ = '/api/v1/invoicing/react/agt';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('local');

        // A AGT falsa diz sempre que sim: é o pior caso, o que dava por validado.
        Http::fake(['*' => Http::response(['requestID' => 'REQ-ENSAIO', 'resultCode' => '0', 'errorList' => []], 200)]);

        $this->emitirEm('sandbox');
        $this->definicoesAgt()->update(['agt_auto_submit' => true]);
        $this->instalarChavesDoContribuinte('sandbox');
        $this->instalarChavesDoContribuinte('production');
        $this->instalarProdutor('sandbox');
        $this->instalarProdutor('production');
    }

    public function test_o_despacho_nao_envia_nem_consulta_as_de_producao_com_a_empresa_em_homologacao(): void
    {
        $factura = $this->facturaDeEnsaio();
        $porEnviar = $this->submissaoAgt([
            'agt_environment' => 'production', 'document_id' => $factura->id, 'document_number' => $factura->invoice_number,
        ]);
        $aEspera = $this->submissaoAgt([
            'agt_environment' => 'production', 'status' => AGTSubmission::STATUS_SUBMITTED, 'agt_reference' => 'REQ-PROD-1',
        ]);

        $this->assertFalse(DespachoPendentes::temTrabalho($this->tenant->id), 'as de produção não são trabalho para homologação');

        $r = (new DespachoPendentes())->correr($this->tenant->id);

        $this->assertSame(['enviados' => 0, 'consultados' => 0], $r);
        Http::assertNothingSent();
        $this->assertSame(AGTSubmission::STATUS_PENDING, $porEnviar->fresh()->status);
        $this->assertSame(0, (int) $porEnviar->fresh()->retry_count);
        $this->assertSame(AGTSubmission::STATUS_SUBMITTED, $aEspera->fresh()->status, 'nunca «validada» pela AGT de testes');
    }

    public function test_o_despacho_continua_a_tratar_as_do_ambiente_activo(): void
    {
        $aEspera = $this->submissaoAgt([
            'agt_environment' => 'sandbox', 'status' => AGTSubmission::STATUS_SUBMITTED, 'agt_reference' => 'REQ-SBX-1',
        ]);

        $this->assertTrue(DespachoPendentes::temTrabalho($this->tenant->id));

        $r = (new DespachoPendentes())->correr($this->tenant->id);

        $this->assertSame(1, $r['consultados']);
        $this->assertSame(AGTSubmission::STATUS_VALIDATED, $aEspera->fresh()->status);
    }

    public function test_a_consulta_do_estado_ignora_a_submissao_do_outro_ambiente(): void
    {
        $s = $this->submissaoAgt([
            'agt_environment' => 'production', 'status' => AGTSubmission::STATUS_SUBMITTED, 'agt_reference' => 'REQ-PROD-2',
        ]);

        (new PollAGTStatusJob($this->tenant->id, $s->id, 'REQ-PROD-2'))->handle();

        Http::assertNothingSent();
        $this->assertSame(AGTSubmission::STATUS_SUBMITTED, $s->fresh()->status);
    }

    public function test_actualizar_estados_so_pergunta_pelas_do_ambiente_activo(): void
    {
        $s = $this->submissaoAgt([
            'agt_environment' => 'production', 'status' => AGTSubmission::STATUS_SUBMITTED, 'agt_reference' => 'REQ-PROD-3',
        ]);

        $this->assertSame(0, (new GestaoAgt($this->tenant->id))->actualizarEstados());

        Http::assertNothingSent();
        $this->assertSame(AGTSubmission::STATUS_SUBMITTED, $s->fresh()->status);
    }

    public function test_o_submit_recusa_com_mensagem_clara_e_nao_envia(): void
    {
        $factura = $this->facturaDeEnsaio();
        $s = $this->submissaoAgt([
            'agt_environment' => 'production', 'document_id' => $factura->id, 'document_number' => $factura->invoice_number,
        ]);

        $r = (new AGTService($this->tenant->id))->submitToAGT($factura);

        $this->assertFalse($r['success']);
        $this->assertTrue($r['ambiente_errado']);
        $this->assertStringContainsString('Produção', $r['error']);
        $this->assertStringContainsString('Homologação', $r['error']);
        Http::assertNothingSent();
        $this->assertSame(AGTSubmission::STATUS_PENDING, $s->fresh()->status);
        $this->assertSame(0, (int) $s->fresh()->retry_count, 'uma recusa por ambiente não gasta tentativa');
    }

    public function test_reenviar_e_repor_recusam_a_submissao_do_outro_ambiente(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');

        $factura = $this->facturaDeEnsaio();
        $s = $this->submissaoAgt([
            'agt_environment' => 'production', 'document_id' => $factura->id, 'status' => AGTSubmission::STATUS_REJECTED, 'retry_count' => 3,
        ]);

        foreach ([['repor' => false], ['repor' => true]] as $extra) {
            $this->postJson(self::RAIZ . '/submissoes/' . $s->id . '/reenviar', ['ambiente' => 'sandbox'] + $extra)
                ->assertStatus(422)
                ->assertJsonPath('ambiente_errado', true);
        }

        Http::assertNothingSent();
        $this->assertSame(3, (int) $s->fresh()->retry_count, 'o contador não se repõe numa submissão do outro ambiente');
        $this->assertSame(AGTSubmission::STATUS_REJECTED, $s->fresh()->status);

        // E o ecrã não lhe oferece os botões.
        $linha = collect($this->getJson(self::RAIZ . '/estado?ambiente=production')->assertOk()->json('submissoes'))->firstWhere('id', $s->id);
        $this->assertFalse($linha['pode_reenviar']);
        $this->assertFalse($linha['pode_repor']);
    }
}
