<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\QuoteTemplate;
use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\SalesQuote;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * O EMISSOR DE PROPOSTAS, e as três promessas que ele faz.
 *
 * 1. A conta é do servidor. O que o browser mandar de totais é ignorado.
 * 2. A taxa vem do `TaxResolver`, nunca do pedido.
 * 3. O número é do modelo, com a série da empresa — nunca escrito à mão.
 *
 * E a quarta, por omissão: só se emitem PROPOSTAS. Uma factura de venda pedida
 * a esta rota dá 404, porque o editor ainda não sabe assiná-la.
 */
class ApiDoEmissorParaReactTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function rota(string $tipo, string $cauda = ''): string
    {
        return "/api/v1/invoicing/react/emissor/{$tipo}{$cauda}";
    }

    private function artigo(array $por = []): Product
    {
        $categoria = Category::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Geral'],
            ['is_active' => true]
        );

        return Product::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Artigo ' . uniqid(),
            'type' => 'produto',
            'price' => 1000,
            'unit' => 'un',
            'category_id' => $categoria->id,
            'tax_type' => 'isento',
            'exemption_reason' => 'M99',
            'is_active' => true,
        ], $por));
    }

    private function taxaDe14(): Tax
    {
        return Tax::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'],
            ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']
        );
    }

    /* ─── O que ainda não se emite por aqui ───────────────────────────── */

    /**
     * UMA FACTURA DE VENDA NÃO SE EMITE POR ESTA ROTA.
     *
     * Tem número de série, hash encadeado e assinatura AGT. 404 e não 403: não
     * é falta de permissão, é o editor ainda não saber fazê-lo.
     *
     * @test
     */
    public function so_se_emitem_propostas(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create', 'invoicing.sales.proformas.view');

        foreach (['facturas-compra', 'recibos'] as $aindaNao) {
            $this->postJson($this->rota($aindaNao), [])->assertNotFound();
        }

        // E as três que se emitem existem — não dão 404. A que tem permissão
        // responde 200; as outras duas respondem 403, que é outra conversa.
        $this->postJson($this->rota('proformas-venda', '/calcular'), ['linhas' => []])->assertOk();

        foreach (['orcamentos', 'proformas-compra'] as $tipo) {
            $this->postJson($this->rota($tipo, '/calcular'), ['linhas' => []])->assertForbidden();
        }
    }

    /* ─── A conta ─────────────────────────────────────────────────────── */

    /**
     * A TAXA VEM DO ARTIGO, NÃO DO PEDIDO.
     *
     * @test
     */
    public function a_taxa_ignora_o_que_o_browser_manda(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $comIva = $this->artigo(['tax_type' => 'iva', 'tax_rate_id' => $this->taxaDe14()->id]);

        $r = $this->postJson($this->rota('proformas-venda', '/calcular'), [
            'linhas' => [[
                'product_id' => $comIva->id,
                'quantity' => 1,
                'price' => 1000,
                // O browser a tentar impor isenção num artigo com IVA.
                'tax_rate' => 0,
            ]],
        ])->assertOk();

        $this->assertEqualsWithDelta(14, $r->json('linhas.0.tax_rate'), 0.01,
            'a taxa é lida do artigo, não do que veio no corpo');
        $this->assertGreaterThan(0, (float) $r->json('linhas.0.imposto'));
    }

    /**
     * O IMPOSTO É `CEIL` AO CÊNTIMO, não `round`.
     *
     * É a regra do DS.120 §4.1 que a AGT verifica. Com `round`, certas linhas
     * saem um cêntimo abaixo e o documento é recusado com E70.
     *
     * @test
     */
    public function o_imposto_arredonda_para_cima_ao_centimo(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $a = $this->artigo(['tax_type' => 'iva', 'tax_rate_id' => $this->taxaDe14()->id]);

        // 1 × 10,01 a 14% = 1,4014 → 1,41 e não 1,40.
        $r = $this->postJson($this->rota('proformas-venda', '/calcular'), [
            'linhas' => [['product_id' => $a->id, 'quantity' => 1, 'price' => 10.01]],
        ])->assertOk();

        $this->assertEqualsWithDelta(1.41, $r->json('linhas.0.imposto'), 0.001);
    }

    /** Uma linha isenta não cobra imposto nenhum. @test */
    public function uma_linha_isenta_nao_cobra_imposto(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $r = $this->postJson($this->rota('proformas-venda', '/calcular'), [
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 2, 'price' => 500]],
        ])->assertOk();

        $this->assertSame(0, (int) $r->json('linhas.0.imposto'));
        $this->assertEqualsWithDelta(1000, $r->json('totais.total'), 0.01);
    }

    /** O desconto da linha baixa a base antes do imposto. @test */
    public function o_desconto_da_linha_baixa_a_base(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $r = $this->postJson($this->rota('proformas-venda', '/calcular'), [
            'linhas' => [[
                'product_id' => $this->artigo()->id,
                'quantity' => 1,
                'price' => 1000,
                'discount_percent' => 10,
            ]],
        ])->assertOk();

        $this->assertEqualsWithDelta(1000, $r->json('linhas.0.bruto'), 0.01);
        $this->assertEqualsWithDelta(100, $r->json('linhas.0.desconto'), 0.01);
        $this->assertEqualsWithDelta(900, $r->json('linhas.0.base'), 0.01);
    }

    /* ─── Gravar ──────────────────────────────────────────────────────── */

    /** @test */
    public function sem_permissao_de_criar_nao_se_grava(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $this->postJson($this->rota('proformas-venda'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'data' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 100]],
        ])->assertForbidden();
    }

    /** Um documento sem linhas não é um documento. @test */
    public function nao_se_grava_um_documento_vazio(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create');

        $this->postJson($this->rota('proformas-venda'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'data' => now()->toDateString(),
            'linhas' => [],
        ])->assertJsonValidationErrors('linhas');
    }

    /**
     * GRAVA, NUMERA-SE SOZINHO, E OS TOTAIS SÃO OS DO SERVIDOR.
     *
     * @test
     */
    public function grava_uma_proforma_com_o_numero_da_serie(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create');

        $a = $this->artigo(['tax_type' => 'iva', 'tax_rate_id' => $this->taxaDe14()->id]);

        $r = $this->postJson($this->rota('proformas-venda'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'data' => now()->toDateString(),
            'linhas' => [['product_id' => $a->id, 'quantity' => 2, 'price' => 1000]],
            // O browser a mentir nos totais. Tem de ser ignorado.
            'total' => 1,
            'subtotal' => 1,
        ])->assertCreated();

        $proforma = SalesProforma::find($r->json('id'));

        $this->assertNotEmpty($proforma->proforma_number, 'o modelo numera-se a si próprio');
        $this->assertEqualsWithDelta(2280, $proforma->total, 0.01,
            '2 × 1000 = 2000, mais 14% = 2280 — e não o que o browser mandou');
        $this->assertSame(1, $proforma->items()->count());
        $this->assertSame('draft', $proforma->status, 'uma proposta nasce em rascunho');
    }

    /** O documento fica preso a esta empresa e a quem o gravou. @test */
    public function o_documento_fica_desta_empresa_e_deste_autor(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create');

        $id = $this->postJson($this->rota('proformas-venda'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'data' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 100]],
        ])->assertCreated()->json('id');

        $p = SalesProforma::find($id);

        $this->assertSame($this->tenant->id, (int) $p->tenant_id);
        $this->assertSame($this->user->id, (int) $p->created_by);
    }

    /* ─── A outra parte, criada sem largar o documento ─────────────────── */

    /**
     * AS OPÇÕES DIZEM SE A OUTRA PARTE SE PODE CRIAR AQUI — e qual é ela.
     *
     * Numa proposta de venda a parte é o CLIENTE; numa de compra é o
     * FORNECEDOR. E a permissão é a de criar a FICHA, que é outra coisa que
     * não a de emitir a proposta: quem emite orçamentos não fica, por isso,
     * com autorização para abrir fichas de clientes. Sem ela o ecrã não mostra
     * o botão — e a porta de criação recusa na mesma.
     *
     * @test
     */
    public function as_opcoes_dizem_se_a_outra_parte_se_pode_criar_aqui(): void
    {
        $this->comPermissoes(
            'invoicing.sales.proformas.view',
            'invoicing.purchases.proformas.view'
        );

        $venda = $this->getJson($this->rota('proformas-venda', '/opcoes'))->assertOk();
        $venda->assertJsonPath('criar_parte.tipo', 'cliente')
            ->assertJsonPath('criar_parte.pode', false)
            ->assertJsonPath('criar_parte.pais_padrao', 'AO');

        $compra = $this->getJson($this->rota('proformas-compra', '/opcoes'))->assertOk();
        $compra->assertJsonPath('criar_parte.tipo', 'fornecedor')
            ->assertJsonPath('criar_parte.pode', false);

        // Cada uma acende com a SUA permissão, e não com a da outra.
        $this->comPermissoes('invoicing.clients.create');

        $this->getJson($this->rota('proformas-venda', '/opcoes'))
            ->assertOk()->assertJsonPath('criar_parte.pode', true);
        $this->getJson($this->rota('proformas-compra', '/opcoes'))
            ->assertOk()->assertJsonPath('criar_parte.pode', false);

        $this->comPermissoes('invoicing.suppliers.create');

        $this->getJson($this->rota('proformas-compra', '/opcoes'))
            ->assertOk()->assertJsonPath('criar_parte.pode', true);
    }

    /* ─── Os campos que a migração tinha deixado para trás ────────────── */

    /**
     * O ARMAZÉM, AS CONDIÇÕES E A PRESTAÇÃO DE SERVIÇO GRAVAM E VOLTAM.
     *
     * O ecrã em Blade recolhia os três e o emissor em React nem os aceitava:
     * uma proposta gravada por aqui perdia as condições que saem no papel, o
     * armazém e a marca de serviço. Grava-se com os três e reabre-se com os
     * três.
     *
     * @test
     */
    public function o_armazem_as_condicoes_e_o_servico_gravam_e_voltam_no_abrir(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create', 'invoicing.sales.proformas.view');

        $r = $this->postJson($this->rota('proformas-venda'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'data' => now()->toDateString(),
            'condicoes' => 'Pagamento a 30 dias. Garantia de 12 meses.',
            'is_service' => true,
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 1000]],
        ])->assertCreated();

        $p = SalesProforma::findOrFail($r->json('id'));

        $this->assertSame($this->armazem->id, (int) $p->warehouse_id);
        $this->assertSame('Pagamento a 30 dias. Garantia de 12 meses.', $p->terms);
        $this->assertTrue((bool) $p->is_service);

        $aberta = $this->getJson($this->rota('proformas-venda', '/' . $p->id))->assertOk();

        $this->assertSame($this->armazem->id, $aberta->json('documento.warehouse_id'));
        $this->assertSame('Pagamento a 30 dias. Garantia de 12 meses.', $aberta->json('documento.condicoes'));
        $this->assertTrue($aberta->json('documento.is_service'));
    }

    /**
     * A PRESTAÇÃO DE SERVIÇO RETÉM IRT — e o total baixa por isso.
     *
     * A conta é a do servidor nos dois sítios: no `/calcular` que o ecrã
     * pergunta enquanto se escreve e no que grava. Se `is_service` não
     * viajasse, o ecrã mostrava um total e o documento guardava outro.
     *
     * @test
     */
    public function marcar_como_servico_retem_irt_no_calculo_e_no_documento(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create', 'invoicing.sales.proformas.view');

        $linhas = [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 1000]];

        $sem = $this->postJson($this->rota('proformas-venda', '/calcular'), ['linhas' => $linhas])->assertOk();
        $com = $this->postJson($this->rota('proformas-venda', '/calcular'), ['linhas' => $linhas, 'is_service' => true])->assertOk();

        $this->assertEqualsWithDelta(0, $sem->json('totais.retencao'), 0.01);
        $this->assertEqualsWithDelta(65, $com->json('totais.retencao'), 0.01, '6,5% de 1000');
        $this->assertEqualsWithDelta(935, $com->json('totais.total'), 0.01);

        $id = $this->postJson($this->rota('proformas-venda'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'data' => now()->toDateString(),
            'is_service' => true,
            'linhas' => $linhas,
        ])->assertCreated()->json('id');

        $p = SalesProforma::findOrFail($id);

        $this->assertEqualsWithDelta(65, $p->irt_amount, 0.01);
        $this->assertEqualsWithDelta(935, $p->total, 0.01, 'o documento fica com o total que o ecrã mostrou');
    }

    /**
     * UMA PROPOSTA DE SERVIÇO NÃO EXIGE ARMAZÉM.
     *
     * Com mercadoria e o armazém apagado à mão, o servidor recusa e diz onde
     * — é o que o `save()` do Livewire fazia. Marcada como prestação de
     * serviço, ou só com serviços no documento, passa.
     *
     * @test
     */
    public function uma_proposta_de_servico_nao_exige_armazem(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create');

        $fisico = $this->artigo();

        $corpo = fn (array $por = []) => array_merge([
            'parte_id' => $this->clienteEmpresa()->id,
            'data' => now()->toDateString(),
            'warehouse_id' => null,
            'linhas' => [['product_id' => $fisico->id, 'quantity' => 1, 'price' => 1000]],
        ], $por);

        $this->postJson($this->rota('proformas-venda'), $corpo())
            ->assertStatus(422)
            ->assertJsonValidationErrors('warehouse_id');

        // Marcada como prestação de serviço: o armazém deixa de fazer falta.
        $this->postJson($this->rota('proformas-venda'), $corpo(['is_service' => true]))->assertCreated();

        // E um documento só de serviços também não o exige.
        $servico = $this->artigo(['type' => 'servico']);

        $this->postJson($this->rota('proformas-venda'), $corpo([
            'linhas' => [['product_id' => $servico->id, 'quantity' => 1, 'price' => 500]],
        ]))->assertCreated();
    }

    /* ─── O modelo de proposta ────────────────────────────────────────── */

    /**
     * O MODELO ESCOLHIDO FICA NO ORÇAMENTO — E É O QUE SAI NO PAPEL.
     *
     * Havia um ecrã inteiro de modelos de proposta e o orçamento não podia
     * escolher nenhum: o documento caía sempre no padrão da empresa. Agora a
     * lista chega ao editor com os campos que cada modelo pede, o escolhido
     * grava-se, volta ao reabrir, e é ele que desenha o documento.
     *
     * @test
     */
    public function o_modelo_escolhido_fica_no_orcamento_e_e_o_que_desenha_o_papel(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.create', 'invoicing.sales.quotes.view');

        $modelo = QuoteTemplate::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Modelo de bancada',
            'blocos' => [
                ['id' => 'c1', 'tipo' => 'campo_livre', 'chave' => 'ambito',
                 'rotulo' => 'Âmbito', 'titulo' => 'Âmbito do trabalho', 'linhas' => 5],
                ['id' => 'i1', 'tipo' => 'itens', 'titulo' => 'Investimento'],
            ],
            'estilos' => QuoteTemplate::ESTILOS_PADRAO,
            'is_active' => true,
        ]);

        // A lista chega ao editor, com os campos que o modelo pede.
        $opcoes = $this->getJson($this->rota('orcamentos', '/opcoes'))->assertOk();

        $this->assertSame($modelo->id, $opcoes->json('modelos.0.id'));
        $this->assertSame('ambito', $opcoes->json('modelos.0.campos.0.chave'));

        $id = $this->postJson($this->rota('orcamentos'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'data' => now()->toDateString(),
            'quote_template_id' => $modelo->id,
            'campos_proposta' => [
                'ambito' => 'Migrar 3 servidores para a nuvem.',
                // Um campo que este modelo não pede não se guarda.
                'orfao' => 'texto de um modelo antigo',
            ],
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 100000]],
        ])->assertCreated()->json('id');

        $orcamento = SalesQuote::findOrFail($id);

        $this->assertSame($modelo->id, (int) $orcamento->quote_template_id);
        $this->assertSame(['ambito' => 'Migrar 3 servidores para a nuvem.'], $orcamento->campos_proposta,
            'só os campos que o modelo escolhido pede');

        // Volta ao reabrir…
        $aberto = $this->getJson($this->rota('orcamentos', '/' . $id))->assertOk();

        $this->assertSame($modelo->id, $aberto->json('documento.quote_template_id'));
        $this->assertSame('Migrar 3 servidores para a nuvem.', $aberto->json('documento.campos_proposta.ambito'));

        // …e é ELE que desenha o documento.
        $html = $this->get('/invoicing/sales/quotes/' . $id . '/preview')->assertOk()->getContent();

        $this->assertStringContainsString('Âmbito do trabalho', $html);
        $this->assertStringContainsString('Migrar 3 servidores', $html);
    }

    /**
     * O MODELO SÓ EXISTE NO ORÇAMENTO, e um id de fora não entra.
     *
     * As proformas saem sempre pelo desenho da casa: a lista vem vazia e o
     * campo nem chega a aparecer. E um modelo que não é desta empresa fica em
     * nada, em vez de marcar o documento com o desenho de outra.
     *
     * @test
     */
    public function so_o_orcamento_tem_modelo_e_um_id_de_fora_nao_entra(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view', 'invoicing.sales.quotes.create');

        $proformas = $this->getJson($this->rota('proformas-venda', '/opcoes'))->assertOk();

        $this->assertSame([], $proformas->json('modelos'));
        $this->assertNull($proformas->json('modelo_padrao'));

        $id = $this->postJson($this->rota('orcamentos'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'data' => now()->toDateString(),
            'quote_template_id' => 999999,
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 100]],
        ])->assertCreated()->json('id');

        $this->assertNull(SalesQuote::findOrFail($id)->quote_template_id);
    }

    /* ─── Os descontos do documento ───────────────────────────────────── */

    /**
     * OS TRÊS DESCONTOS ENTRAM NA CONTA, FICAM GRAVADOS E VOLTAM NO ABRIR.
     *
     * O ecrã de sempre pedia os três — comercial (antes do IVA), o legado
     * (`discount_amount`, que SOMA ao comercial) e o financeiro (depois do
     * IVA) — e a base guarda os três há muito. O editor em React não os
     * oferecia e o servidor mandava zero fixo no lugar do legado: uma proposta
     * com desconto aberta para editar mostrava as caixas vazias, e a primeira
     * gravação apagava o desconto que ela tinha.
     *
     * A conta: 1000 de bruto, menos 100 de comercial e 50 de legado, dá 850 de
     * incidência de IVA — o financeiro NÃO baixa a incidência, entra depois do
     * imposto apurado. Isento de IVA, o total é 850 − 50 = 800.
     *
     * @test
     */
    public function os_tres_descontos_contam_gravam_e_voltam(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create', 'invoicing.sales.proformas.view', 'invoicing.sales.proformas.edit');

        $linhas = [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 1000]];

        $conta = $this->postJson($this->rota('proformas-venda', '/calcular'), [
            'linhas' => $linhas,
            'desconto_comercial' => 100,
            'desconto_legado' => 50,
            'desconto_financeiro' => 50,
        ])->assertOk();

        $this->assertEqualsWithDelta(150, $conta->json('totais.desconto_comercial'), 0.01, 'o legado soma ao comercial');
        $this->assertEqualsWithDelta(850, $conta->json('totais.base'), 0.01, 'o financeiro não baixa a incidência');
        $this->assertEqualsWithDelta(800, $conta->json('totais.total'), 0.01);

        $id = $this->postJson($this->rota('proformas-venda'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'data' => now()->toDateString(),
            'desconto_comercial' => 100,
            'desconto_legado' => 50,
            'desconto_financeiro' => 50,
            'linhas' => $linhas,
        ])->assertCreated()->json('id');

        $p = SalesProforma::findOrFail($id);

        $this->assertEqualsWithDelta(100, (float) $p->discount_commercial, 0.01);
        $this->assertEqualsWithDelta(50, (float) $p->discount_amount, 0.01);
        $this->assertEqualsWithDelta(50, (float) $p->discount_financial, 0.01);
        $this->assertEqualsWithDelta(800, (float) $p->total, 0.01);

        $aberta = $this->getJson($this->rota('proformas-venda', '/' . $id))->assertOk();

        $this->assertEqualsWithDelta(100, $aberta->json('documento.desconto_comercial'), 0.01);
        $this->assertEqualsWithDelta(50, $aberta->json('documento.desconto_legado'), 0.01);
        $this->assertEqualsWithDelta(50, $aberta->json('documento.desconto_financeiro'), 0.01);
    }

    /* ─── Guardar, enviar, e o que já não se mexe ─────────────────────── */

    /**
     * «GUARDAR E ENVIAR» PÕE A PROPOSTA EM ENVIADA — E ELA AINDA SE CORRIGE.
     *
     * Eram os dois botões do ecrã de sempre: guardar deixa em rascunho para se
     * acabar depois, enviar diz que a proposta saiu para a outra parte. Uma
     * proposta NÃO é documento fiscal — não tem hash nem cadeia e a AGT nunca a
     * vê — por isso enviá-la não a fecha: quando o cliente responde «troque a
     * quantidade», corrige-se a mesma e reenvia-se.
     *
     * E guardar de novo NÃO a devolve a rascunho: quem corrige uma proposta que
     * saiu continua a ter uma proposta que saiu.
     *
     * @test
     */
    public function guardar_e_enviar_marca_como_enviada_e_ainda_se_corrige(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create', 'invoicing.sales.proformas.view', 'invoicing.sales.proformas.edit');

        $corpo = [
            'parte_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'data' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 1000]],
        ];

        $r = $this->postJson($this->rota('proformas-venda'), $corpo + ['estado' => 'sent'])->assertCreated();

        $id = $r->json('id');

        $this->assertSame('sent', $r->json('estado'));
        $this->assertSame('sent', SalesProforma::findOrFail($id)->status);

        // Enviada, continua a abrir para edição — e a dizer que se pode mexer.
        $this->getJson($this->rota('proformas-venda', '/' . $id))
            ->assertOk()
            ->assertJsonPath('documento.pode_editar', true);

        // Guardar de novo sem falar do estado deixa-a enviada.
        $corpo['linhas'][0]['quantity'] = 3;

        $this->putJson($this->rota('proformas-venda', '/' . $id), $corpo)->assertOk();

        $p = SalesProforma::findOrFail($id);

        $this->assertSame('sent', $p->status, 'corrigir uma proposta enviada não a devolve a rascunho');
        $this->assertEqualsWithDelta(3000, (float) $p->total, 0.01);
    }

    /**
     * UMA PROPOSTA CONVERTIDA JÁ NÃO SE ALTERA.
     *
     * Convertida deu origem a uma factura: mudá-la agora punha a factura a
     * divergir da proposta que a justifica. É o mesmo travão que a lista já
     * aplica a eliminar — e a guarda cobre também `cancelled`, que hoje o enum
     * da coluna nem aceita mas que os outros documentos usam.
     *
     * @test
     */
    public function uma_proposta_convertida_nao_se_altera(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create', 'invoicing.sales.proformas.view', 'invoicing.sales.proformas.edit');

        $corpo = [
            'parte_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'data' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 1000]],
        ];

        $id = $this->postJson($this->rota('proformas-venda'), $corpo)->assertCreated()->json('id');

        SalesProforma::findOrFail($id)->update(['status' => 'converted']);

        $this->putJson($this->rota('proformas-venda', '/' . $id), $corpo)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este documento já foi convertido ou anulado e não se altera.');

        $this->getJson($this->rota('proformas-venda', '/' . $id))
            ->assertOk()
            ->assertJsonPath('documento.pode_editar', false);
    }

    /**
     * O ESTADO QUE SE ESCOLHE DAQUI SÃO DOIS, E SÓ DOIS.
     *
     * Convertida põe-na o conversor (que cria mesmo a factura) e anulada põe-na
     * quem anula. Deixar o pedido escrever qualquer estado era oferecer um
     * atalho para marcar uma proposta como convertida sem factura nenhuma do
     * outro lado.
     *
     * @test
     */
    public function o_pedido_nao_escreve_um_estado_qualquer(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create', 'invoicing.sales.proformas.view');

        $this->postJson($this->rota('proformas-venda'), [
            'parte_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'data' => now()->toDateString(),
            'estado' => 'converted',
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 1000]],
        ])->assertStatus(422)->assertJsonValidationErrors('estado');
    }
}
