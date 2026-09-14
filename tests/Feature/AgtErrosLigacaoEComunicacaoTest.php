<?php

namespace Tests\Feature;

use App\Models\AGT\AGTCommunicationLog;
use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\AGTClient;
use App\Services\AGT\AGTErrorCode;
use App\Services\AGT\AGTHttpClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AgtDeEnsaio;
use Tests\TenantTestCase;

/**
 * O QUE A AGT DISSE, TAL COMO DISSE — e o ecrã a dizer a verdade sobre isso.
 *
 *  · Os códigos (E39, E43, E70) e as descrições da AGT não se perdem: o texto
 *    local vai AO LADO, não por cima.
 *  · «Testar ligação» dizia «credenciais válidas» com a errorList da AGT a
 *    dizer o contrário.
 *  · O registo de comunicação etiquetava com o ambiente ACTIVO, e não com o do
 *    pedido: o teste a produção aparecia no histórico de homologação.
 *  · O `estado` diz se a empresa está mesmo a comunicar, e porque não.
 */
class AgtErrosLigacaoEComunicacaoTest extends TenantTestCase
{
    use AgtDeEnsaio;

    private const RAIZ = '/api/v1/invoicing/react/agt';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('local');

        $this->comModulo('invoicing')->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
    }

    public function test_os_codigos_da_agt_e_a_descricao_dela_sobrevivem(): void
    {
        $resposta = [
            'resultCode' => '2',
            'requestErrorList' => [],
            'documentStatusList' => [[
                'documentNo' => 'NC S/000002',
                'errorList' => [['idError' => 'E43', 'descriptionError' => 'Excede o montante ainda não anulado (500,00).']],
            ]],
        ];

        $lista = AGTErrorCode::lista($resposta);

        $this->assertSame('E43', $lista[0]['codigo']);
        $this->assertSame('Excede o montante ainda não anulado (500,00).', $lista[0]['descricao'], 'a descrição é a da AGT');
        $this->assertNotNull($lista[0]['explicacao'], 'E43 tem explicação local');
        $this->assertSame('NC S/000002', $lista[0]['documento']);
        $this->assertSame('E43', AGTErrorCode::primeiroCodigo($resposta));

        foreach (['E39', 'E43', 'E70'] as $codigo) {
            $this->assertArrayHasKey($codigo, AGTErrorCode::MESSAGES);
        }

        // O formatList já não troca a descrição da AGT pelo texto local.
        $texto = AGTErrorCode::formatList([['idError' => 'E70', 'descriptionError' => 'Imposto apurado (140,01).']]);
        $this->assertStringContainsString('[E70]', $texto);
        $this->assertStringContainsString('Imposto apurado (140,01).', $texto);
        $this->assertStringContainsString(AGTErrorCode::MESSAGES['E70'], $texto);
    }

    public function test_o_cliente_http_devolve_o_codigo_e_etiqueta_o_ambiente_do_pedido(): void
    {
        $this->emitirEm('sandbox');
        $this->instalarProdutor('sandbox');

        Http::fake(['*' => Http::response(['errorList' => [['idError' => 'E39', 'descriptionError' => 'Assinatura do produtor não confere.']]], 200)]);

        $r = (new AGTHttpClient(InvoicingSettings::forTenant($this->tenant->id)))->post('/listarSeries', ['x' => 1], 'ListarSeries');

        $this->assertFalse($r['ok']);
        $this->assertSame('E39', $r['error_code']);
        $this->assertStringContainsString('[E39] Assinatura do produtor não confere.', $r['error']);
    }

    public function test_o_registo_leva_o_ambiente_do_pedido_e_nao_o_activo(): void
    {
        $this->emitirEm('sandbox');
        $this->instalarChavesDoContribuinte('production');
        $this->instalarProdutor('production');

        Http::fake(['*' => Http::response(['requestID' => 'R', 'resultCode' => '8'], 200)]);

        // A consola pergunta a PRODUÇÃO com a empresa ainda em homologação.
        (new AGTClient($this->tenant->id, 'production'))->getStatus('R');

        $log = AGTCommunicationLog::where('tenant_id', $this->tenant->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('production', $log->agt_environment);
    }

    public function test_testar_ligacao_com_errorlist_nao_e_sucesso(): void
    {
        $this->emitirEm('sandbox');
        $this->instalarChavesDoContribuinte('sandbox');
        $this->instalarProdutor('sandbox');

        Http::fake(['*/listarSeries' => Http::sequence()
            ->push(['errorList' => [['idError' => 'E40', 'descriptionError' => 'Assinatura jwsSignature inválida.']]], 200)
            ->push(['seriesResultCount' => 0, 'errorList' => []], 200),
        ]);

        $r = $this->postJson(self::RAIZ . '/ligacao', ['ambiente' => 'sandbox'])->assertOk();

        $r->assertJsonPath('ok', false)
            ->assertJsonPath('ambiente', 'sandbox')
            ->assertJsonPath('http', 200);
        $this->assertStringContainsString('E40', $r->json('mensagem'));
        $this->assertStringNotContainsString('válidas', $r->json('mensagem'));

        // A segunda resposta da AGT falsa já vem limpa.
        $this->postJson(self::RAIZ . '/ligacao', ['ambiente' => 'sandbox'])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_a_consulta_devolve_o_contrato_novo_e_o_rotulo_dos_recebidos(): void
    {
        $this->emitirEm('sandbox');
        $this->instalarChavesDoContribuinte('sandbox');
        $this->instalarProdutor('sandbox');

        Http::fake(['*/obterEstado' => Http::response(['requestID' => 'R', 'resultCode' => '0', 'documentStatusList' => []], 200)]);

        $r = $this->postJson(self::RAIZ . '/consulta', ['ambiente' => 'sandbox', 'apiOperation' => 'obterEstado', 'apiRequestId' => 'R'])->assertOk();

        $r->assertJsonStructure(['ok', 'mensagem', 'http', 'ms', 'testado_em', 'data'])->assertJsonPath('ok', true)->assertJsonPath('http', 200);

        $operacoes = collect($this->getJson(self::RAIZ . '/opcoes')->assertOk()->json('operacoes'));
        $this->assertStringContainsString('RECEBIDOS', $operacoes->firstWhere('valor', 'listarFacturas')['rotulo']);
    }

    public function test_o_estado_diz_se_a_empresa_esta_mesmo_a_comunicar(): void
    {
        $this->emitirEm('sandbox');
        $this->definicoesAgt()->update(['agt_auto_submit' => false, 'agt_eac_code' => null]);
        $this->semProdutorDeProducao();
        config(['services.agt.sandbox.username' => '', 'services.agt.sandbox.password' => '']);
        $this->facturaDeEnsaio();

        $estado = $this->getJson(self::RAIZ . '/estado')->assertOk();

        $c = $estado->json('comunicacao');
        $this->assertFalse($c['comunica']);
        $this->assertCount(5, $c['motivos'], 'sem chaves, sem CAE, homologação, envio desligado, produtor');
        $this->assertGreaterThanOrEqual(1, $c['emitidos_30d']);
        $this->assertGreaterThanOrEqual(1, $c['por_comunicar']);
        $this->assertFalse($estado->json('auto_submit'));
        $this->assertNotNull($estado->json('aviso_auto_submit'));
        $this->assertTrue($estado->json('cae_em_falta'));

        // Tudo em ordem: comunica.
        $this->emitirEm('production');
        $this->definicoesAgt()->update(['agt_auto_submit' => true, 'agt_eac_code' => '47730']);
        $this->instalarChavesDoContribuinte('production');
        $this->instalarProdutor('production');

        $pronto = $this->getJson(self::RAIZ . '/estado')->assertOk();
        $this->assertTrue($pronto->json('comunicacao.comunica'), implode(' | ', $pronto->json('comunicacao.motivos')));
        $this->assertSame([], $pronto->json('comunicacao.motivos'));
        $this->assertNull($pronto->json('aviso_auto_submit'));
    }
}
