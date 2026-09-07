<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\AGTKeyStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * O PAINEL DO ADQUIRENTE, para o ecrã em React.
 *
 * As facturas que os FORNECEDORES emitiram contra esta empresa: listar
 * (DS.120 §4.3), consultar uma (§4.4) e confirmar ou rejeitar (§4.7).
 *
 * NADA SAI DAQUI PARA A AGT. Todos os ensaios correm com `Http::fake()`, e
 * o que se prova é o que o servidor MANDA — o endpoint, o `documentNo` e a
 * acção que vão no corpo — e o que ele faz com o que a AGT responde.
 *
 * O que estes ensaios guardam: ver e validar são permissões diferentes; a
 * lista exige o período e devolve o que a AGT respondeu; o detalhe abre; a
 * confirmação e a rejeição chegam ao `/validarDocumento` com o payload
 * certo; um erro da AGT vira mensagem e não 500; e nada disto corre fora
 * do ambiente em que a empresa emite.
 */
class ApiDoAdquirenteParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/adquirente';

    /** Um par RSA custa a gerar; um por processo chega. */
    private static array $pares = [];

    /** O que a AGT «responde», por fim de endereço. Ver `agtResponde()`. */
    private array $respostas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        Storage::fake('local');

        /*
         * NADA SAI PARA A AGT A PARTIR DE UM ENSAIO.
         *
         * Um único `fake`, que despacha pelo endereço. Registar um segundo
         * `Http::fake(['*listarFacturas' => …])` dentro do ensaio não servia:
         * os stubs ACUMULAM e ganha o primeiro que casa — o `*` daqui
         * respondia sempre vazio e o ensaio provava o contrário do que dizia.
         */
        Http::fake(function (Request $pedido) {
            foreach ($this->respostas as $fim => $corpo) {
                if (str_ends_with($pedido->url(), $fim)) {
                    return Http::response($corpo, 200);
                }
            }

            return Http::response([], 200);
        });
    }

    /** O que a AGT devolve neste endpoint, neste ensaio. */
    private function agtResponde(string $fim, array $corpo): void
    {
        $this->respostas[$fim] = $corpo;
    }

    /* ─── Bancada ─────────────────────────────────────────────────────── */

    private function definicoes(): InvoicingSettings
    {
        return InvoicingSettings::forTenant($this->tenant->id);
    }

    private function parRsa(int $n = 0): array
    {
        if (isset(self::$pares[$n])) {
            return self::$pares[$n];
        }

        $opcoes = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

        // No Windows o PHP não encontra o openssl.cnf sozinho.
        $cnf = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras'
            . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
        if (is_file($cnf)) {
            $opcoes['config'] = $cnf;
        }

        $res = openssl_pkey_new($opcoes);
        $this->assertNotFalse($res, 'não foi possível gerar o par RSA de ensaio');
        openssl_pkey_export($res, $privada, null, $opcoes);

        return self::$pares[$n] = [openssl_pkey_get_details($res)['key'], $privada];
    }

    /** Chaves do produtor, chaves da empresa e credenciais: o mínimo para a AGT atender. */
    private function comAAgtConfigurada(string $ambiente = 'sandbox'): void
    {
        [$publicaProdutor, $privadaProdutor] = $this->parRsa(0);
        Storage::disk('local')->put('saft/public_key.pem', $publicaProdutor);
        Storage::disk('local')->put('saft/private_key.pem', $privadaProdutor);
        Storage::disk('local')->put("saft/{$ambiente}/public_key.pem", $publicaProdutor);
        Storage::disk('local')->put("saft/{$ambiente}/private_key.pem", $privadaProdutor);

        [$publicaEmpresa, $privadaEmpresa] = $this->parRsa(1);
        AGTKeyStore::store($this->tenant->id, $publicaEmpresa, $privadaEmpresa, $ambiente);

        config(['services.agt.username' => 'utilizador', 'services.agt.password' => 'palavra']);

        $this->definicoes()->update(['agt_environment' => $ambiente]);
        InvoicingSettings::esquecerMemoria($this->tenant->id);
    }

    /** O corpo JSON do último pedido que saiu para um endpoint da AGT. */
    private function pedidoPara(string $fim): ?array
    {
        foreach (Http::recorded() as [$pedido]) {
            if (str_ends_with($pedido->url(), $fim)) {
                return $pedido->data();
            }
        }

        return null;
    }

    /* ─── Permissões ──────────────────────────────────────────────────── */

    /** @test */
    public function ver_e_validar_sao_permissoes_diferentes(): void
    {
        $this->getJson(self::RAIZ . '/estado')->assertForbidden();

        $this->comPermissoes('invoicing.agt.view');

        $this->getJson(self::RAIZ . '/estado')->assertOk()
            ->assertJsonPath('permissoes.pode_validar', false)
            ->assertJsonStructure(['data' => ['empresa' => ['id', 'nome', 'nif'], 'ambiente', 'rotulo', 'em_falta', 'periodo' => ['de', 'ate'], 'accoes']]);

        // Ver não confirma nem rejeita nada.
        $this->postJson(self::RAIZ . '/validar', ['documento' => 'FT A/1', 'accao' => 'C'])->assertForbidden();

        $this->comPermissoes('invoicing.agt.edit');
        $this->getJson(self::RAIZ . '/estado')->assertOk()->assertJsonPath('permissoes.pode_validar', true);
    }

    /** O período é obrigatório: uma listagem sem datas é uma ida à AGT ao acaso. @test */
    public function a_listagem_exige_o_periodo(): void
    {
        $this->comPermissoes('invoicing.agt.view');

        $this->getJson(self::RAIZ . '/facturas')->assertStatus(422)->assertJsonValidationErrors(['de', 'ate']);

        $this->getJson(self::RAIZ . '/facturas?de=2026-09-30&ate=2026-09-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('ate');

        Http::assertNothingSent();
    }

    /* ─── Listar (§4.3) ───────────────────────────────────────────────── */

    /** @test */
    public function a_lista_pede_o_periodo_a_agt_e_devolve_o_que_ela_respondeu(): void
    {
        $this->comPermissoes('invoicing.agt.view');
        $this->comAAgtConfigurada();

        $this->agtResponde('/listarFacturas', [
            'documentResultCount' => 2,
            'resultEntryList' => [
                ['documentNo' => 'FT SERIE/1', 'documentType' => 'FT', 'documentDate' => '2026-09-02', 'documentStatus' => 'V', 'documentStatusDescription' => 'Validado', 'netTotal' => '1500.00', 'grossTotal' => '1710.00'],
                ['documentNo' => 'FR SERIE/9', 'documentType' => 'FR', 'documentDate' => '2026-09-04', 'documentStatus' => 'I', 'netTotal' => 300],
            ],
        ]);

        $r = $this->getJson(self::RAIZ . '/facturas?de=2026-09-01&ate=2026-09-30')->assertOk();

        $r->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.periodo.de', '2026-09-01')
            ->assertJsonPath('data.facturas.0.numero', 'FT SERIE/1')
            ->assertJsonPath('data.facturas.0.estado_descricao', 'Validado')
            ->assertJsonPath('data.facturas.0.liquido', 1500)
            ->assertJsonPath('data.facturas.1.numero', 'FR SERIE/9')
            ->assertJsonPath('data.facturas.1.estado_descricao', null);

        // O que saiu para a AGT: o endpoint certo e o período pedido.
        $enviado = $this->pedidoPara('/listarFacturas');
        $this->assertNotNull($enviado, 'não saiu pedido para /listarFacturas');
        $this->assertSame('2026-09-01', $enviado['queryStartDate']);
        $this->assertSame('2026-09-30', $enviado['queryEndDate']);
        $this->assertSame((string) $this->tenant->nif, $enviado['taxRegistrationNumber']);
        $this->assertNotEmpty($enviado['jwsSignature']);
    }

    /** Zero facturas é o resultado normal de quem só emite — não é erro. @test */
    public function um_periodo_sem_facturas_recebidas_nao_e_erro(): void
    {
        $this->comPermissoes('invoicing.agt.view');
        $this->comAAgtConfigurada();

        $this->agtResponde('/listarFacturas', ['documentResultCount' => 0, 'resultEntryList' => []]);

        $this->getJson(self::RAIZ . '/facturas?de=2026-09-01&ate=2026-09-30')->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.facturas', []);
    }

    /* ─── Detalhe (§4.4) ──────────────────────────────────────────────── */

    /** @test */
    public function o_detalhe_consulta_a_factura_pelo_numero(): void
    {
        $this->comPermissoes('invoicing.agt.view');
        $this->comAAgtConfigurada();

        $this->agtResponde('/consultarFactura', [
            'documentNo' => 'FT SERIE/1',
            'documentStatus' => 'V',
            'document' => ['grossTotal' => '1710.00'],
        ]);

        $this->getJson(self::RAIZ . '/factura?documento=' . urlencode('FT SERIE/1'))->assertOk()
            ->assertJsonPath('data.documento', 'FT SERIE/1')
            ->assertJsonPath('data.estado', 'V')
            ->assertJsonPath('data.detalhe.document.grossTotal', '1710.00');

        $enviado = $this->pedidoPara('/consultarFactura');
        $this->assertNotNull($enviado);
        $this->assertSame('FT SERIE/1', $enviado['invoiceNo']);
    }

    /* ─── Confirmar e rejeitar (§4.7) ─────────────────────────────────── */

    /** @test */
    public function confirmar_chega_ao_validardocumento_com_a_accao_c(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $this->comAAgtConfigurada();

        $this->agtResponde('/validarDocumento', ['actionResultCode' => 'C_OK', 'documentStatusCode' => 'S_C']);

        $r = $this->postJson(self::RAIZ . '/validar', [
            'documento' => 'FT SERIE/1',
            'accao' => 'C',
            'percentagem_iva_dedutivel' => 50,
        ])->assertOk();

        $r->assertJsonPath('data.actionResultCode', 'C_OK')
            ->assertJsonPath('data.documentStatusCode', 'S_C');
        $this->assertStringContainsString('C_OK', $r->json('message'));

        $enviado = $this->pedidoPara('/validarDocumento');
        $this->assertNotNull($enviado, 'não saiu pedido para /validarDocumento');
        $this->assertSame('FT SERIE/1', $enviado['documentNo']);
        $this->assertSame('C', $enviado['action']);
        $this->assertSame(50.0, $enviado['deductibleVATPercentage']);
        $this->assertArrayNotHasKey('nonDeductibleAmount', $enviado);
    }

    /** @test */
    public function rejeitar_chega_ao_validardocumento_com_a_accao_r_e_sem_percentagens(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $this->comAAgtConfigurada();

        $this->agtResponde('/validarDocumento', ['actionResultCode' => 'R_OK', 'documentStatusCode' => 'S_RJ']);

        $this->postJson(self::RAIZ . '/validar', [
            'documento' => 'FT SERIE/2',
            'accao' => 'R',
            // Numa rejeição estes não têm sentido: têm de ficar pelo caminho.
            'percentagem_iva_dedutivel' => 20,
        ])->assertOk()->assertJsonPath('data.actionResultCode', 'R_OK');

        $enviado = $this->pedidoPara('/validarDocumento');
        $this->assertSame('R', $enviado['action']);
        $this->assertArrayNotHasKey('deductibleVATPercentage', $enviado);
        $this->assertArrayNotHasKey('nonDeductibleAmount', $enviado);
    }

    /** A AGT recusa os dois juntos (E51/E52): a recusa é nossa, e antes de sair. @test */
    public function a_percentagem_e_o_valor_nao_dedutivel_sao_exclusivos(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $this->comAAgtConfigurada();

        $r = $this->postJson(self::RAIZ . '/validar', [
            'documento' => 'FT SERIE/1',
            'accao' => 'C',
            'percentagem_iva_dedutivel' => 50,
            'valor_nao_dedutivel' => 120,
        ])->assertStatus(422);

        $this->assertStringContainsString('exclusiv', $r->json('message'));
        Http::assertNothingSent();
    }

    /* ─── Os erros da AGT ─────────────────────────────────────────────── */

    /** @test */
    public function um_erro_da_agt_vira_mensagem_e_nao_500(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $this->comAAgtConfigurada();

        $this->agtResponde('/listarFacturas', ['errorList' => [['idError' => 'E01', 'descriptionError' => 'Falta parâmetro']]]);
        $this->agtResponde('/validarDocumento', ['errorList' => [['idError' => 'E53', 'descriptionError' => 'Documento indisponível para confirmação.']]]);

        $lista = $this->getJson(self::RAIZ . '/facturas?de=2026-09-01&ate=2026-09-30')->assertStatus(422);
        $this->assertStringContainsString('E01', $lista->json('message'));

        $validacao = $this->postJson(self::RAIZ . '/validar', ['documento' => 'FT SERIE/1', 'accao' => 'C'])->assertStatus(422);
        $this->assertStringContainsString('E53', $validacao->json('message'));
        $this->assertFalse($validacao->json('ambiente_errado'));
    }

    /** Sem chaves nem credenciais não sai pedido nenhum — e diz-se o que falta. @test */
    public function sem_a_agt_configurada_nao_sai_pedido_nenhum(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        config(['services.agt.username' => '', 'services.agt.password' => '']);

        $r = $this->getJson(self::RAIZ . '/facturas?de=2026-09-01&ate=2026-09-30')->assertStatus(422);
        $this->assertStringContainsString('Falta configurar', $r->json('message'));

        Http::assertNothingSent();
    }

    /* ─── O ambiente activo ───────────────────────────────────────────── */

    /** @test */
    public function nada_corre_fora_do_ambiente_activo(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $this->comAAgtConfigurada('production');

        $this->getJson(self::RAIZ . '/estado')->assertOk()->assertJsonPath('data.ambiente', 'production');

        foreach ([
            ['GET', '/facturas?de=2026-09-01&ate=2026-09-30&ambiente=sandbox', []],
            ['GET', '/factura?documento=FT+A%2F1&ambiente=sandbox', []],
            ['POST', '/validar', ['documento' => 'FT A/1', 'accao' => 'C', 'ambiente' => 'sandbox']],
        ] as [$metodo, $caminho, $corpo]) {
            $r = $metodo === 'GET'
                ? $this->getJson(self::RAIZ . $caminho)
                : $this->postJson(self::RAIZ . $caminho, $corpo);

            $r->assertStatus(422)->assertJsonPath('ambiente_errado', true);
        }

        Http::assertNothingSent();

        // E no ambiente certo o pedido sai.
        $this->agtResponde('/validarDocumento', ['actionResultCode' => 'C_OK', 'documentStatusCode' => 'S_C']);
        $this->postJson(self::RAIZ . '/validar', ['documento' => 'FT A/1', 'accao' => 'C', 'ambiente' => 'production'])->assertOk();
    }
}
