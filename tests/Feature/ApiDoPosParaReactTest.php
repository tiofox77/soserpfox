<?php

namespace Tests\Feature;

use App\Models\Category;
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
