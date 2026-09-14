<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Invoicing\PosShift;
use App\Models\Product;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\Tax;
use Tests\TenantTestCase;

/**
 * O BALCÃO EM REACT — a API.
 *
 * O que este ensaio guarda, por ordem de importância:
 *
 * 1. **HÁ UMA PORTA SÓ PARA A VENDA.** O ecrã novo fecha pelo
 *    `PosSaleService`, o mesmo do PWA offline. Havia duas implementações do
 *    mesmo facto — o `completeSale` do Livewire e o serviço — e duas maneiras
 *    de gravar a mesma venda acabam por divergir numa delas.
 * 2. **SEM TURNO NÃO SE VENDE.** O dinheiro tem de cair no turno de alguém.
 * 3. **A VENDA É IDEMPOTENTE.** Carregar duas vezes em «Finalizar» com a rede
 *    a oscilar não pode dar duas facturas, dois descontos de stock e duas
 *    linhas de tesouraria. O ecrã em Livewire não tinha protecção nenhuma.
 */
class ApiDoPosParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/pos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function turno(): PosShift
    {
        return PosShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'shift_number' => 'T' . random_int(10000, 99999),
            'opened_at' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);
    }

    private function artigo(array $por = []): Product
    {
        $categoria = Category::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Balcão'],
            ['is_active' => true]
        );

        $taxa = Tax::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'],
            ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']
        );

        return Product::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Artigo ' . uniqid(),
            'type' => 'produto',
            'price' => 1000,
            'cost' => 400,
            'unit' => 'UN',
            'category_id' => $categoria->id,
            'tax_type' => 'iva',
            'tax_rate_id' => $taxa->id,
            'manage_stock' => false,
            'stock_quantity' => 0,
            'is_active' => true,
        ], $por));
    }

    /** @return array<string, mixed> */
    private function venda(Product $artigo, array $por = []): array
    {
        return array_merge([
            'local_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'payment_method' => 'cash',
            'amount_received' => 1140,
            'items' => [[
                'product_id' => $artigo->id,
                'product_name' => $artigo->name,
                'quantity' => 1,
                'unit_price' => 1000,
                'is_service' => false,
                'unit' => 'UN',
            ]],
        ], $por);
    }

    /* ─── As opções ───────────────────────────────────────────────────── */

    /** @test */
    public function as_opcoes_dizem_se_ha_turno_aberto(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $this->getJson(self::RAIZ . '/opcoes')->assertOk()->assertJsonPath('turno', null);

        $t = $this->turno();

        $this->getJson(self::RAIZ . '/opcoes')
            ->assertOk()
            ->assertJsonPath('turno.id', $t->id)
            ->assertJsonPath('turno.numero', $t->shift_number);
    }

    /**
     * A GRELHA VEM AOS BOCADOS, E VEM TODA.
     *
     * Vinham só os primeiros 50 e o resto não se via sem procurar. Agora vêm 60
     * de cada vez, sem repetir nem saltar nenhum, e o `meta.mais` diz se há
     * mais — com dois artigos de nome igual a desempatar pelo id.
     *
     * @test
     */
    public function a_grelha_vem_por_paginas_e_chega_a_todos(): void
    {
        $this->comPermissoes('invoicing.pos.view');

        foreach (range(1, 130) as $n) {
            $this->artigo(['name' => 'Ampola ' . str_pad((string) ($n % 125), 3, '0', STR_PAD_LEFT)]);
        }

        $vistos = [];

        foreach ([1 => [60, true], 2 => [60, true], 3 => [10, false]] as $pagina => [$quantos, $mais]) {
            $r = $this->getJson(self::RAIZ . '/artigos?armazem=0&pagina=' . $pagina)->assertOk()
                ->assertJsonPath('meta.pagina', $pagina)
                ->assertJsonPath('meta.mais', $mais)
                ->assertJsonCount($quantos, 'data');

            $vistos = array_merge($vistos, array_column($r->json('data'), 'id'));
        }

        $this->assertCount(130, $vistos, 'chega a todos');
        $this->assertCount(130, array_unique($vistos), 'e nenhum aparece duas vezes');

        // Sem `pagina` é a primeira, como antes.
        $this->getJson(self::RAIZ . '/artigos')->assertOk()->assertJsonCount(60, 'data')->assertJsonPath('meta.mais', true);
    }

    /** A procura acha também pelo código do artigo. @test */
    public function a_procura_acha_pelo_codigo_do_artigo(): void
    {
        $this->comPermissoes('invoicing.pos.view');
        $this->artigo(['name' => 'Soro fisiológico', 'code' => '5601234567890']);

        $this->getJson(self::RAIZ . '/artigos?procura=5601234567890')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Soro fisiológico');
    }

    /** O armazém e as formas de pagamento vêm decididos do servidor. @test */
    public function as_opcoes_trazem_o_armazem_as_formas_e_os_montantes(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $r = $this->getJson(self::RAIZ . '/opcoes')->assertOk();

        $this->assertSame($this->armazem->id, $r->json('armazem.id'));
        $this->assertIsArray($r->json('formas_de_pagamento'));
        $this->assertNotEmpty($r->json('definicoes.montantes_rapidos'), 'os botões de troco rápido');
        $this->assertIsBool($r->json('definicoes.esconde_sem_stock'));
    }

    /**
     * AS CATEGORIAS COMO O OPERADOR AS LÊ.
     *
     * Uma farmácia importou «├üLCOOL» (acentos estragados) e depois criou à
     * mão «ÁLCOOL»: o balcão mostrava as duas, com os artigos repartidos, e a
     * estragada ia para o topo da lista. Passa a um botão só, que filtra pelas
     * duas, em ordem alfabética portuguesa.
     *
     * @test
     */
    public function as_categorias_repetidas_por_acentos_estragados_sao_um_botao_so(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $estragada = Category::create(['tenant_id' => $this->tenant->id, 'name' => '├üLCOOL', 'is_active' => true]);
        $certa = Category::create(['tenant_id' => $this->tenant->id, 'name' => 'ÁLCOOL', 'is_active' => true]);
        Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Adesivo', 'is_active' => true]);
        Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Xarope', 'is_active' => true]);

        $a = $this->artigo(['name' => 'Álcool 70º 1L']);
        $a->forceFill(['category_id' => $estragada->id])->save();
        $b = $this->artigo(['name' => 'Álcool gel']);
        $b->forceFill(['category_id' => $estragada->id])->save();
        $c = $this->artigo(['name' => 'Álcool 96º']);
        $c->forceFill(['category_id' => $certa->id])->save();

        $categorias = collect($this->getJson(self::RAIZ . '/opcoes')->assertOk()->json('categorias'))
            // A empresa de ensaio nasce com as categorias do catálogo inicial.
            ->filter(fn ($x) => in_array($x['nome'], ['Adesivo', 'ÁLCOOL', '├üLCOOL', 'Xarope'], true))->values();

        $this->assertSame(['Adesivo', 'ÁLCOOL', 'Xarope'], $categorias->pluck('nome')->all(), 'acentos reparados, uma vez só, e por ordem portuguesa');

        $alcool = $categorias->firstWhere('nome', 'ÁLCOOL');
        $this->assertSame(3, $alcool['artigos']);
        $this->assertSame($estragada->id, $alcool['id'], 'a principal é a que tem mais artigos');
        $this->assertEqualsCanonicalizing([$estragada->id, $certa->id], $alcool['ids']);

        $artigos = $this->getJson(self::RAIZ . '/artigos?categoria=' . implode(',', $alcool['ids']))->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], array_column($artigos, 'id'));

        $this->getJson(self::RAIZ . '/artigos?categoria=1,x')->assertStatus(422);
    }

    /**
     * A IMAGEM DO CARTÃO É UM ENDEREÇO. Mandava-se o caminho gravado
     * («products/x.jpg») e o browser pedia-o relativo à página do balcão —
     * todas as imagens saíam partidas. E o logótipo vai nas opções, para o
     * cartão sem imagem.
     *
     * @test
     */
    public function a_imagem_do_artigo_chega_como_endereco_e_o_logotipo_nas_opcoes(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $artigo = $this->artigo(['name' => 'Vaselina Pura 30g', 'featured_image' => 'products/vaselina.jpg']);

        $imagem = collect($this->getJson(self::RAIZ . '/artigos?procura=Vaselina')->assertOk()->json('data'))->firstWhere('id', $artigo->id)['imagem'];

        $this->assertSame('/storage/products/vaselina.jpg', $imagem, 'a partir da raiz: não depende do APP_URL');
        $this->assertNotEmpty($this->getJson(self::RAIZ . '/opcoes')->assertOk()->json('logotipo'));
    }

    /* ─── A grelha de artigos ─────────────────────────────────────────── */

    /**
     * A PROCURA DO BALCÃO é mais larga do que o nome.
     *
     * Numa farmácia pergunta-se pela substância («paracetamol») sem saber que
     * a caixa diz outra coisa; numa loja de roupa pergunta-se pelo tamanho
     * («38»), que não está no nome nem no código. Era assim no ecrã de sempre.
     *
     * @test
     */
    public function a_procura_apanha_a_substancia_e_o_tamanho(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $this->artigo(['name' => 'Ben-u-ron 500', 'active_ingredient' => 'paracetamol']);
        $this->artigo(['name' => 'Camisa Azul', 'size' => '38']);
        $this->artigo(['name' => 'Outra coisa qualquer']);

        $porSubstancia = $this->getJson(self::RAIZ . '/artigos?procura=paracetamol')->assertOk()->json('data');
        $this->assertCount(1, $porSubstancia);
        $this->assertSame('Ben-u-ron 500', $porSubstancia[0]['nome']);

        $porTamanho = $this->getJson(self::RAIZ . '/artigos?procura=38')->assertOk()->json('data');
        $this->assertCount(1, $porTamanho);
        $this->assertSame('Camisa Azul', $porTamanho[0]['nome']);
    }

    /**
     * «PREÇO NO POS» É UM INTERRUPTOR, e não um preço.
     *
     * A coluna é um `tinyint` e quer dizer «perguntar o preço ao balcão». Lê-la
     * como valor punha 1,00 Kz no ecrã em vez do preço do artigo.
     *
     * @test
     */
    public function o_preco_no_pos_e_uma_pergunta_e_nao_um_valor(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $a = $this->artigo(['price' => 2500, 'preco_no_pos' => true]);

        $linha = collect($this->getJson(self::RAIZ . '/artigos')->assertOk()->json('data'))
            ->firstWhere('id', $a->id);

        $this->assertEqualsWithDelta(2500, $linha['preco'], 0.01, 'o preço é o do catálogo');
        $this->assertTrue($linha['pergunta_preco'], 'e o ecrã tem de perguntar antes de o meter no carrinho');
    }

    /* ─── A venda ─────────────────────────────────────────────────────── */

    /** @test */
    public function o_caixa_vende_com_as_permissoes_do_balcao(): void
    {
        // O papel «Caixa»: entra no POS e vende no POS, mas não emite facturas
        // no editor. Ficou com o «Finalizar Venda» cinzento em todas as
        // empresas quando o balcão passou a React (Farmácia Luk Simões).
        $this->comPermissoes('invoicing.pos.access', 'invoicing.pos.sell');
        $this->turno();
        $a = $this->artigo();

        $this->getJson(self::RAIZ . '/opcoes')->assertOk()->assertJsonPath('permissoes.pode_vender', true);
        $this->postJson(self::RAIZ . '/vender', $this->venda($a))->assertSuccessful();

        $this->assertSame(1, SalesInvoice::where('tenant_id', $this->tenant->id)->count());

        // E o cliente rápido do balcão, que o POS de sempre também não negava.
        $this->getJson(self::RAIZ . '/opcoes')->assertJsonPath('permissoes.pode_criar_cliente', true);
        $this->postJson(self::RAIZ . '/clientes', ['name' => 'Cliente do Balcão'])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Cliente do Balcão')
            ->assertJsonPath('data.nif', null);
    }

    /**
     * O CLIENTE RÁPIDO NASCE AO BALCÃO.
     *
     * O React chamava o catálogo `clientes`, que não existe: 404 para toda a
     * gente. Nome obrigatório, NIF opcional, e o NIF genérico não identifica.
     *
     * @test
     */
    public function o_cliente_rapido_nasce_sem_nif_e_nao_se_duplica_pelo_nif(): void
    {
        $this->comPermissoes('invoicing.pos.access');

        $this->postJson(self::RAIZ . '/clientes', ['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');

        $maria = $this->postJson(self::RAIZ . '/clientes', ['name' => 'Maria da Esquina', 'nif' => '999999999'])
            ->assertCreated()->json('data');
        $this->assertNull($maria['nif'], 'o NIF genérico fica nulo');
        $this->assertSame('AO', Client::find($maria['id'])->country);

        $joao = $this->postJson(self::RAIZ . '/clientes', ['name' => 'João', 'nif' => '5417000001', 'phone' => '923000000'])
            ->assertCreated()->json('data');

        $this->postJson(self::RAIZ . '/clientes', ['name' => 'Outro nome', 'nif' => '5417000001'])
            ->assertOk()
            ->assertJsonPath('existente', true)
            ->assertJsonPath('data.id', $joao['id']);

        $this->assertSame(1, Client::where('tenant_id', $this->tenant->id)->where('nif', '5417000001')->count());
    }

    /**
     * O BALCÃO DO SALÃO VENDE SERVIÇOS.
     *
     * `/salon/pos` passou a abrir o POS da facturação, que esconde e recusa o
     * que é de um módulo: o salão ficou sem onde cobrar um corte de cabelo.
     *
     * @test
     */
    public function o_balcao_do_salao_mostra_e_vende_os_servicos(): void
    {
        $this->comModulo('salon');
        $this->comPermissoes('salon.pos.access', 'salon.pos.sell');
        $this->turno();

        $categoria = \App\Models\Salon\ServiceCategory::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cabelo', 'slug' => 'cabelo-' . uniqid(), 'is_active' => true,
        ]);
        $corte = \App\Models\Salon\Service::create(['tenant_id' => $this->tenant->id, 'name' => 'Corte', 'price' => 5000, 'is_active' => true]);
        $corte->updateSalonData(['category_id' => $categoria->id, 'duration' => 45]);

        // Fora do salão, o serviço não aparece e não se vende.
        $this->assertNotContains($corte->id, collect($this->getJson(self::RAIZ . '/artigos')->json('data'))->pluck('id'));

        $o = $this->getJson(self::RAIZ . '/opcoes?modulo=salon')->assertOk()
            ->assertJsonPath('modulo', 'salon')
            ->assertJsonPath('permissoes.pode_vender', true);
        $this->assertSame(1, collect($o->json('categorias_de_servicos'))->firstWhere('id', $categoria->id)['artigos']);

        $servicos = $this->getJson(self::RAIZ . '/artigos?modulo=salon&tipo=servicos&categoria=' . $categoria->id)->assertOk()->json('data');
        $this->assertSame([$corte->id], collect($servicos)->pluck('id')->all());
        $this->assertSame(45, $servicos[0]['duracao']);

        $venda = [
            'local_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'payment_method' => 'cash',
            'amount_received' => 10000,
            'items' => [[
                'product_id' => $corte->id, 'product_name' => 'Corte', 'quantity' => 1,
                'unit_price' => 5000, 'is_service' => true, 'unit' => 'UN',
            ]],
        ];

        // Sem dizer que é o salão, a porta da facturação recusa o serviço.
        $this->postJson(self::RAIZ . '/vender', $venda)->assertForbidden();

        $this->postJson(self::RAIZ . '/vender', $venda + ['modulo' => 'salon'])->assertSuccessful();

        $linha = \App\Models\Invoicing\SalesInvoiceItem::where('product_id', $corte->id)->first();
        $this->assertNotNull($linha, 'a linha leva o serviço do catálogo');
    }

    /** @test */
    public function sem_o_modulo_o_pedido_do_salao_nao_abre_nada(): void
    {
        $this->comPermissoes('invoicing.pos.access', 'salon.pos.access');

        $this->getJson(self::RAIZ . '/opcoes?modulo=salon')->assertOk()->assertJsonPath('modulo', null);
    }

    /** @test */
    public function sem_permissao_o_cliente_rapido_nao_nasce(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->postJson(self::RAIZ . '/clientes', ['name' => 'Intruso'])->assertForbidden();
    }

    /** @test */
    public function sem_permissao_de_vender_a_porta_esta_fechada(): void
    {
        $this->turno();
        $a = $this->artigo();

        $this->postJson(self::RAIZ . '/vender', $this->venda($a))->assertForbidden();

        $this->assertSame(0, SalesInvoice::where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * SEM TURNO ABERTO NÃO SE VENDE.
     *
     * A guarda estava no `mount` do ecrã em Livewire, que redireccionava. Numa
     * porta HTTP não há redireccionamento que valha: tem de recusar.
     *
     * @test
     */
    public function sem_turno_aberto_o_servidor_recusa_a_venda(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $a = $this->artigo();

        $this->postJson(self::RAIZ . '/vender', $this->venda($a))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não há turno de caixa aberto. Abra um turno antes de vender.');

        $this->assertSame(0, SalesInvoice::where('tenant_id', $this->tenant->id)->count());
    }

    /** A venda sai numerada, com total, e o ecrã recebe por onde a ver. @test */
    public function a_venda_fecha_e_devolve_o_documento(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');
        $this->turno();

        $a = $this->artigo();

        $r = $this->postJson(self::RAIZ . '/vender', $this->venda($a))->assertCreated();

        $this->assertNotEmpty($r->json('numero'));
        $this->assertEqualsWithDelta(1140, $r->json('total'), 0.01, '1000 + 14% de IVA');
        // OS DOIS PAPÉIS, e qual deles a empresa configurou. O modal do balcão
        // abre já na pré-visualização deste, com a impressão pronta.
        $this->assertContains($r->json('formato'), ['talao', 'a4']);
        $this->assertStringContainsString('/talao', (string) $r->json('papeis.talao'));
        $this->assertStringContainsString('/preview', (string) $r->json('papeis.a4'));

        $f = SalesInvoice::findOrFail($r->json('id'));

        $this->assertSame($this->tenant->id, $f->tenant_id);
        $this->assertSame(1, $f->items()->count());
    }

    /**
     * O DESCONTO DO BALCÃO: a percentagem é percentagem, e o valor chega.
     *
     * O ecrã manda `discount_commercial` em % e `discount_value` em Kz. O
     * serviço tratava a percentagem como Kz (10% davam 10 Kz de desconto) e o
     * desconto por valor ia a zero. Ver Tests\Feature\Pwa\DescontoDoBalcaoTest.
     *
     * @test
     */
    public function o_desconto_em_percentagem_e_em_valor_chega_a_factura(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');
        $this->turno();

        $a = $this->artigo();

        $pct = $this->postJson(self::RAIZ . '/vender', $this->venda($a, ['discount_commercial' => 10]))->assertCreated();
        $this->assertEqualsWithDelta(900 * 1.14, $pct->json('total'), 0.01, '10% de 1000 são 100 Kz, não 10');

        $valor = $this->postJson(self::RAIZ . '/vender', $this->venda($a, ['discount_value' => 250]))->assertCreated();
        $this->assertEqualsWithDelta(750 * 1.14, $valor->json('total'), 0.01, 'o desconto por valor tem de chegar à factura');
    }

    /**
     * A MESMA VENDA DUAS VEZES DÁ UMA FACTURA SÓ.
     *
     * É o `local_uuid` que o garante. Com a rede a oscilar, o operador carrega
     * outra vez em «Finalizar» — e sem isto saía uma segunda factura, com o
     * mesmo stock descontado e a mesma linha de tesouraria. O ecrã em Livewire
     * não tinha protecção nenhuma contra isso.
     *
     * @test
     */
    public function a_mesma_venda_repetida_nao_grava_duas_vezes(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');
        $this->turno();

        $a = $this->artigo();
        $corpo = $this->venda($a);

        $primeira = $this->postJson(self::RAIZ . '/vender', $corpo)->assertCreated()->json('id');
        $segunda = $this->postJson(self::RAIZ . '/vender', $corpo)->assertCreated()->json('id');

        $this->assertSame($primeira, $segunda, 'o mesmo local_uuid devolve a factura que já existe');
        $this->assertSame(1, SalesInvoice::where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * O IMPOSTO É O DO ARTIGO, não o que o ecrã disser.
     *
     * O `PosSaleService` resolve-o pelo `TaxResolver` a partir da base. Um
     * balcão com o catálogo desactualizado — ou um pedido forjado — não muda
     * o que a AGT vai receber.
     *
     * @test
     */
    public function o_imposto_vem_da_base_e_nao_do_pedido(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');
        $this->turno();

        $a = $this->artigo();

        $r = $this->postJson(self::RAIZ . '/vender', $this->venda($a, [
            // O ecrã mente: diz que a linha não leva imposto nenhum.
            'items' => [[
                'product_id' => $a->id,
                'product_name' => $a->name,
                'quantity' => 1,
                'unit_price' => 1000,
                'tax_rate' => 0,
            ]],
        ]))->assertCreated();

        $this->assertEqualsWithDelta(1140, $r->json('total'), 0.01, 'a taxa do artigo é que manda');
    }

    /** Sem linhas não há venda, e o servidor diz onde. @test */
    public function sem_linhas_nao_ha_venda(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');
        $this->turno();

        $this->postJson(self::RAIZ . '/vender', [
            'local_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'items' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('items');
    }

    /** O turno de OUTRO operador não serve para eu vender. @test */
    public function o_turno_tem_de_ser_do_proprio_operador(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $outro = \App\Models\User::factory()->create();

        PosShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $outro->id,
            'shift_number' => 'T-OUTRO',
            'opened_at' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $a = $this->artigo();

        $this->postJson(self::RAIZ . '/vender', $this->venda($a))->assertStatus(422);
        $this->assertSame(0, SalesInvoice::where('tenant_id', $this->tenant->id)->count());
    }
}
