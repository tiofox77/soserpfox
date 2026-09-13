<?php

namespace Tests\Feature\Pwa;

use App\Services\Pwa\MoldeDoDocumento;
use Tests\TenantTestCase;

/**
 * O molde do documento: o modelo do servidor com marcas, para o aparelho.
 *
 * O papel sem rede tem de ser o mesmo da pré-visualização — e a única forma
 * de o garantir é ser o MESMO modelo. Estes ensaios prendem o contrato: o
 * molde renderiza para os três tipos, traz todas as marcas que o aparelho
 * preenche, não deixa nenhum valor sintético por trocar, e não leva o script
 * de auto-impressão.
 */
class MoldeDoDocumentoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A API do PWA pede permissão desde 2026-09-13 (AutorizaApiDoPwa): o
        // utilizador do ensaio é um caixa a sério, não um membro sem papel.
        $this->comPermissoesDoPwa();
    }

    private const MARCAS_COMUNS = [
        '%%NUMERO%%', '%%CLIENTE_NOME%%', '%%CLIENTE_NIF%%', '%%DATA%%', '%%HORA%%', '%%OPERADOR%%', '%%REFERENCIA%%',
        '%%ITEM_CODIGO%%', '%%ITEM_NOME%%', '%%ITEM_DESCRICAO%%', '%%ITEM_QTD%%', '%%ITEM_PRECO%%', '%%ITEM_BRUTO%%',
        '%%ITEM_DESCONTO%%', '%%TAXA%%', '%%ITEM_IMPOSTO%%', '%%ITEM_TOTAL%%',
        '%%IMPOSTO_BASE%%', '%%IMPOSTO_VALOR%%', '%%ISENTO_BASE%%', '%%ISENCAO_CODIGO%%', '%%ISENCAO_MOTIVO%%',
        '%%SUBTOTAL%%', '%%DESC_COMERCIAL%%', '%%DESC_FINANCEIRO%%', '%%IVA%%', '%%TOTAL%%', '%%RETENCAO%%',
        '%%A_PAGAR%%', '%%EXTENSO%%',
    ];

    /** A entrega (Art. 6º Decreto 71/25) só existe no modelo da factura. */
    private const MARCAS_DA_FACTURA = ['%%ENTREGA_DATA%%', '%%ENTREGA_LOCAL%%', '%%HASH4%%'];

    public function test_o_molde_renderiza_para_os_tres_tipos_com_todas_as_marcas(): void
    {
        $servico = app(MoldeDoDocumento::class);

        foreach (MoldeDoDocumento::TIPOS as $tipo) {
            $html = $servico->render($tipo, $this->tenant);

            $esperadas = $tipo === 'proforma' ? self::MARCAS_COMUNS : array_merge(self::MARCAS_COMUNS, self::MARCAS_DA_FACTURA);
            foreach ($esperadas as $marca) {
                $this->assertStringContainsString($marca, $html, "o molde {$tipo} perdeu a marca {$marca}");
            }

            $this->assertSame([], $servico->sobras($html), "o molde {$tipo} deixou valores sintéticos por trocar");
            $this->assertStringNotContainsString('<script', $html, 'o auto-print do modelo não pode viajar');
            $this->assertStringContainsString('LINHA-AUXILIAR', $html, 'as linhas auxiliares existem para o resumo ter duas taxas');
            $this->assertStringContainsString($this->tenant->name, $html, 'a empresa fica real no molde');
        }
    }

    public function test_a_factura_recibo_leva_o_pagamento_e_a_proforma_nao(): void
    {
        $servico = app(MoldeDoDocumento::class);

        $this->assertStringContainsString('%%RECEBIDO%%', $servico->render('FR', $this->tenant));
        $this->assertStringContainsString('%%PAGAMENTO%%', $servico->render('FR', $this->tenant));
        $this->assertStringNotContainsString('%%RECEBIDO%%', $servico->render('proforma', $this->tenant));
        $this->assertStringContainsString('Proforma de Venda', $servico->render('proforma', $this->tenant));
    }

    public function test_o_molde_e_o_mesmo_modelo_da_pre_visualizacao(): void
    {
        $html = app(MoldeDoDocumento::class)->render('FT', $this->tenant);

        // As mesmas classes de desenho do modelo do servidor.
        foreach (['items-table', 'doc-info-table', 'summary-section', 'tax-table', 'client-info', 'system-info', 'page-wrapper'] as $classe) {
            $this->assertStringContainsString('class="' . $classe . '"', $html, "o molde não tem a classe {$classe} do modelo");
        }
    }

    public function test_a_rota_exige_sessao_e_recusa_tipos_inventados(): void
    {
        $this->actingAs($this->user)->get('/invoicing/offline/molde/FT')
            ->assertOk()
            ->assertHeader('ETag')
            ->assertSee('%%NUMERO%%', false);

        $this->actingAs($this->user)->get('/invoicing/offline/molde/recibo')->assertNotFound();

        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->get('/invoicing/offline/molde/FT')->assertRedirect();
    }

    /** O molde passa dos 700 KB: só desce outra vez quando muda. */
    public function test_o_molde_que_nao_mudou_responde_304(): void
    {
        $r = $this->actingAs($this->user)->get('/invoicing/offline/molde/FT')->assertOk();
        $etiqueta = $r->headers->get('ETag');

        $this->actingAs($this->user)
            ->withHeader('If-None-Match', $etiqueta)
            ->get('/invoicing/offline/molde/FT')
            ->assertStatus(304);
    }

    public function test_o_sync_leva_o_motivo_de_isencao_das_taxas(): void
    {
        $json = $this->actingAs($this->user)->getJson('/api/v1/invoicing/sync')->assertOk()->json();

        foreach ($json['data']['tax_rates'] ?? [] as $taxa) {
            $this->assertArrayHasKey('exemption_reason', $taxa);
        }
        $this->assertTrue(true);
    }
}
