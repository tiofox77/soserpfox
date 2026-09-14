<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Invoicing\InvoicingSeries;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AgtDeEnsaio;
use Tests\TenantTestCase;

/**
 * SINCRONIZAR SÉRIES DEPOIS DE MUDAR DE AMBIENTE.
 *
 * O `syncAllSeries` só pegava nas séries SEM código. Uma série registada em
 * homologação tem código — o de homologação —, e por isso quem passava a
 * produção carregava em «Sincronizar», via «nada pendente» e ficava sem série
 * para emitir: o getIssuanceSeries() recusa, e bem, um código que a AGT de
 * produção desconhece. Entram agora as do outro ambiente, e a resposta diz
 * série a série o que aconteceu, com o código de erro da AGT.
 */
class AgtSincronizarSeriesTest extends TenantTestCase
{
    use AgtDeEnsaio;

    private const RAIZ = '/api/v1/invoicing/react/agt';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('local');

        $this->comModulo('invoicing')->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $this->emitirEm('production');
        $this->instalarChavesDoContribuinte('production');
        $this->instalarProdutor('production');

        // As séries que a empresa de ensaio traz de nascença ficam de fora:
        // cada ensaio monta as suas, para a ordem dos pedidos à AGT falsa ser
        // a que ele diz.
        InvoicingSeries::where('tenant_id', $this->tenant->id)->update(['is_active' => false]);
    }

    private function serie(array $campos): InvoicingSeries
    {
        return InvoicingSeries::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Série de ensaio',
            'is_active' => true,
            'is_default' => false,
            'next_number' => 1,
            'current_year' => (int) date('Y'),
        ], $campos));
    }

    public function test_re_regista_as_series_do_outro_ambiente_e_devolve_os_detalhes(): void
    {
        $deHomologacao = $this->serie([
            'document_type' => 'invoice', 'prefix' => 'FT', 'series_code' => 'ENSFT',
            'agt_series_id' => 'FT-SBX-ANTIGO', 'atcud_validation_code' => 'FT-SBX-ANTIGO', 'agt_environment' => 'sandbox',
            // A resposta guardada é de homologação: não pode ser «recuperada» para produção.
            'agt_response' => ['seriesFEResult' => ['seriesCode' => 'FT-SBX-ANTIGO']],
        ]);
        $jaDeProducao = $this->serie([
            'document_type' => 'credit_note', 'prefix' => 'NC', 'series_code' => 'ENSNC',
            'agt_series_id' => 'NC-PROD', 'atcud_validation_code' => 'NC-PROD', 'agt_environment' => 'production',
        ]);
        $porRegistar = $this->serie([
            'document_type' => 'debit_note', 'prefix' => 'ND', 'series_code' => 'ENSND', 'agt_environment' => 'production',
        ]);

        Http::fake(['*/solicitarSerie' => Http::sequence()
            ->push(['seriesFEResult' => ['seriesCode' => 'FT-PROD-NOVO'], 'errorList' => []], 200)
            ->push(['errorList' => [['idError' => 'E39', 'descriptionError' => 'Os dados constantes na assinatura do produtor não estão de acordo com o Processo de Certificação.']]], 200),
        ]);

        $r = $this->postJson(self::RAIZ . '/series/sincronizar', ['ambiente' => 'production'])->assertOk();

        Http::assertSentCount(2);
        $detalhes = collect($r->json('details'));
        $this->assertCount(2, $detalhes, 'a de produção já registada não volta a ir');

        $a = $detalhes->firstWhere('serie_id', $deHomologacao->id);
        $this->assertTrue($a['ok']);
        $this->assertSame('sandbox', $a['ambiente_anterior']);
        $this->assertSame('FT ENSFT', $a['codigo']);
        $this->assertNull($a['codigo_erro']);

        $c = $detalhes->firstWhere('serie_id', $porRegistar->id);
        $this->assertFalse($c['ok']);
        $this->assertSame('E39', $c['codigo_erro']);
        $this->assertStringContainsString('Processo de Certificação', $c['erro']);

        $deHomologacao->refresh();
        $this->assertSame('FT-PROD-NOVO', $deHomologacao->agt_series_id, 'o código de produção substitui o de homologação');
        $this->assertSame('production', $deHomologacao->agt_environment);
        $this->assertSame('NC-PROD', $jaDeProducao->fresh()->agt_series_id);

        // O ecrã vê cada série no estado deste ambiente.
        $series = collect($this->getJson(self::RAIZ . '/estado?ambiente=production')->assertOk()->json('series'));
        $this->assertSame('registada', $series->firstWhere('id', $deHomologacao->id)['estado']);
        $this->assertSame('Factura', $series->firstWhere('id', $deHomologacao->id)['tipo_rotulo']);
        $this->assertSame('FT-PROD-NOVO', $series->firstWhere('id', $deHomologacao->id)['atcud']);
        $recusada = $series->firstWhere('id', $porRegistar->id);
        $this->assertSame('rejeitada', $recusada['estado']);
        $this->assertSame('E39', $recusada['erros'][0]['codigo']);

        app(AuditRecorder::class)->despejar();
        $this->assertTrue(
            AuditTrail::where('tenant_id', $this->tenant->id)->where('event', 'agt.series.sincronizadas')->exists(),
            'registar séries na AGT fica na trilha'
        );
    }

    public function test_o_estado_das_series_vistas_do_outro_ambiente_e_das_nao_fiscais(): void
    {
        $deHomologacao = $this->serie([
            'document_type' => 'invoice', 'prefix' => 'FT', 'series_code' => 'ENSFT',
            'agt_series_id' => 'FT-SBX', 'atcud_validation_code' => 'FT-SBX', 'agt_environment' => 'sandbox',
        ]);
        $proforma = $this->serie(['document_type' => 'proforma', 'prefix' => 'PR', 'series_code' => 'ENSPR']);

        $estado = $this->getJson(self::RAIZ . '/estado?ambiente=production')->assertOk();
        $series = collect($estado->json('series'));

        // Registada em homologação é «por registar» em produção — e aparece.
        $this->assertSame('por_registar', $series->firstWhere('id', $deHomologacao->id)['estado']);
        $this->assertNull($series->firstWhere('id', $deHomologacao->id)['atcud']);
        $this->assertSame('nao_aplicavel', $series->firstWhere('id', $proforma->id)['estado']);
        $this->assertTrue($estado->json('cae_em_falta'));
    }
}
