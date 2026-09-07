<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Invoicing\PaymentTerm;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\Invoicing\EmissorDeCompras;
use Tests\TenantTestCase;

/**
 * A API DOS CATÁLOGOS, para o ecrã em React — seis catálogos, uma API.
 *
 * O que ela promete e estes ensaios guardam: cada verbo tem a sua permissão;
 * a validação é a do Livewire (a morada concorda consigo própria, o nome da
 * condição é único, o código SAFT acompanha o tipo); as guardas de apagar
 * são as do esquema (compras, artigos, subcategorias); e só há um padrão
 * por catálogo.
 */
class ApiDosCatalogosParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/catalogos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function tudoDe(string $prefixo): void
    {
        $this->comPermissoes("$prefixo.view", "$prefixo.create", "$prefixo.edit", "$prefixo.delete");
    }

    /** @test */
    public function um_catalogo_desconhecido_e_404_e_sem_permissao_e_403(): void
    {
        $this->tudoDe('invoicing.suppliers');

        $this->getJson(self::RAIZ . '/xpto/opcoes')->assertNotFound();
        $this->getJson(self::RAIZ . '/categorias/opcoes')->assertForbidden();
    }

    /** Ver é uma permissão; criar é outra. @test */
    public function quem_so_ve_nao_escreve(): void
    {
        $this->comPermissoes('invoicing.suppliers.view');

        $r = $this->getJson(self::RAIZ . '/fornecedores/opcoes')->assertOk();

        $this->assertFalse($r->json('permissoes.pode_escrever'));
        $this->assertSame('Fornecedores', $r->json('titulo'));
        $this->assertNotEmpty($r->json('campos'));
        $this->assertNotEmpty($r->json('geografia.provincias'));

        $this->postJson(self::RAIZ . '/fornecedores', ['type' => 'pessoa_juridica', 'name' => 'Sem direito', 'country' => 'AO'])
            ->assertForbidden();
    }

    /** A morada concorda consigo própria: em Angola a cidade é o município. @test */
    public function o_fornecedor_grava_com_a_morada_normalizada(): void
    {
        $this->tudoDe('invoicing.suppliers');

        $r = $this->postJson(self::RAIZ . '/fornecedores', [
            'type' => 'pessoa_juridica', 'name' => 'Fornecedor Norte', 'nif' => '5000123456',
            'country' => 'ao', 'province' => 'Luanda', 'municipality' => 'Viana', 'city' => 'Ignorada',
        ])->assertCreated();

        $f = Supplier::find($r->json('data.id'));

        $this->assertSame('AO', $f->country);
        $this->assertSame('Viana', $f->city, 'em Angola a cidade é o município');
        $this->assertTrue($r->json('data.pode_apagar'));
        $this->assertSame('Pessoa Colectiva', $r->json('data.rotulos.type'));

        $this->putJson(self::RAIZ . '/fornecedores/' . $f->id, ['type' => 'pessoa_fisica', 'name' => 'Fornecedor Sul', 'country' => 'PT', 'city' => 'Porto'])
            ->assertOk();

        $this->assertSame('Porto', $f->fresh()->city, 'fora de Angola a cidade é o que se escreveu');

        $this->postJson(self::RAIZ . '/fornecedores', ['type' => 'pessoa_juridica', 'name' => 'X', 'country' => 'ZZ'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'country']);
    }

    /** Um fornecedor com compras não se apaga. @test */
    public function o_fornecedor_com_compras_nao_se_apaga(): void
    {
        $this->tudoDe('invoicing.suppliers');

        $f = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Com compras', 'type' => 'pessoa_juridica', 'is_active' => true]);
        $artigo = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => 'produto', 'price' => 100, 'cost' => 0,
            'unit' => 'un', 'tax_type' => 'isento', 'exemption_reason' => 'M99', 'manage_stock' => true, 'stock_quantity' => 0, 'is_active' => true,
        ]);

        app(EmissorDeCompras::class)->emitir([
            'supplier_id' => $f->id, 'warehouse_id' => $this->armazem->id, 'invoice_date' => now()->toDateString(), 'status' => 'draft',
        ], collect([(object) ['id' => $artigo->id, 'name' => $artigo->name, 'price' => 50, 'quantity' => 1, 'attributes' => ['tax_rate' => 0]]]));

        $this->deleteJson(self::RAIZ . '/fornecedores/' . $f->id)->assertStatus(422);
        $this->assertNotNull($f->fresh());

        $livre = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Sem compras', 'type' => 'pessoa_juridica', 'is_active' => true]);

        $this->deleteJson(self::RAIZ . '/fornecedores/' . $livre->id)->assertOk();
        $this->assertNull(Supplier::find($livre->id));
    }

    /** @test */
    public function as_categorias_conhecem_a_mae_e_nao_se_apagam_com_filhas(): void
    {
        $this->tudoDe('invoicing.categories');

        $mae = $this->postJson(self::RAIZ . '/categorias', ['name' => 'Bebidas', 'icon' => 'fa-folder', 'color' => '#112233'])
            ->assertCreated()->json('data.id');

        $filha = $this->postJson(self::RAIZ . '/categorias', ['name' => 'Cervejas', 'parent_id' => $mae, 'icon' => 'fa-folder', 'color' => '#112233'])
            ->assertCreated();

        $this->assertSame('Bebidas', $filha->json('data.rotulos.parent_id'));

        $outra = Tenant::create(['name' => 'Vizinha', 'slug' => 'viz-' . uniqid(), 'nif' => (string) random_int(500000000, 599999999), 'email' => 'v' . uniqid() . '@x.ao', 'is_active' => true]);
        $alheia = Category::create(['tenant_id' => $outra->id, 'name' => 'Alheia', 'icon' => 'fa-folder', 'color' => '#000000']);

        $this->postJson(self::RAIZ . '/categorias', ['name' => 'Errada', 'parent_id' => $alheia->id, 'icon' => 'fa-folder', 'color' => '#112233'])
            ->assertStatus(422)->assertJsonValidationErrors('parent_id');

        $this->deleteJson(self::RAIZ . '/categorias/' . $mae)->assertStatus(422);

        $so = $this->getJson(self::RAIZ . '/categorias?nivel=principal')->assertOk();
        $this->assertTrue(collect($so->json('data'))->every(fn ($c) => $c['parent_id'] === null));
    }

    /** @test */
    public function so_ha_um_armazem_padrao_e_o_estado_alterna(): void
    {
        $this->tudoDe('invoicing.warehouses');

        $a = $this->postJson(self::RAIZ . '/armazens', ['name' => 'Central', 'code' => 'ARM-C-' . uniqid(), 'is_active' => true, 'is_default' => true])
            ->assertCreated()->json('data.id');

        $this->assertSame($a, Warehouse::getDefault($this->tenant->id)?->id);

        $b = $this->postJson(self::RAIZ . '/armazens', ['name' => 'Norte', 'code' => 'ARM-N-' . uniqid(), 'is_active' => true])
            ->assertCreated()->json('data.id');

        $this->postJson(self::RAIZ . "/armazens/$b/padrao", [])->assertOk();

        $this->assertSame($b, Warehouse::getDefault($this->tenant->id)?->id);
        $this->assertFalse((bool) Warehouse::find($a)->is_default, 'só um pode ser o padrão');

        $this->postJson(self::RAIZ . "/armazens/$b/activar", [])->assertOk()->assertJsonPath('data.is_active', false);
    }

    /** @test */
    public function a_condicao_de_pagamento_tem_nome_unico_e_uma_so_padrao(): void
    {
        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $this->postJson(self::RAIZ . '/condicoes-de-pagamento', ['name' => 'A 45 dias', 'days' => 45])->assertCreated();
        $this->postJson(self::RAIZ . '/condicoes-de-pagamento', ['name' => 'A 45 dias', 'days' => 45])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $nova = $this->postJson(self::RAIZ . '/condicoes-de-pagamento', ['name' => 'A 60 dias', 'days' => 60, 'is_default' => true])
            ->assertCreated()->json('data.id');

        $this->assertSame($nova, PaymentTerm::padraoDe($this->tenant->id)?->id);
        $this->assertSame(1, PaymentTerm::where('tenant_id', $this->tenant->id)->where('is_default', true)->count());
    }

    /** O código SAFT acompanha o tipo; um imposto nunca se apaga. @test */
    public function o_imposto_leva_o_codigo_saft_do_tipo_e_nao_se_apaga(): void
    {
        $this->comPermissoes('invoicing.taxes.view');

        $this->postJson(self::RAIZ . '/impostos', ['code' => 'RED', 'name' => 'Reduzida', 'rate' => 7, 'type' => 'iva', 'saft_type' => 'RED'])
            ->assertForbidden();

        $this->comPermissoes('invoicing.taxes.edit');

        $r = $this->postJson(self::RAIZ . '/impostos', ['code' => 'RED', 'name' => 'Reduzida', 'rate' => 7, 'type' => 'iva', 'saft_type' => 'RED', 'is_default' => true])
            ->assertCreated();

        $t = Tax::find($r->json('data.id'));

        $this->assertSame('RED', $t->saft_code, 'é o saft_code que o TaxResolver declara à AGT');
        $this->assertTrue((bool) $t->is_default);
        $this->assertSame(1, Tax::where('tenant_id', $this->tenant->id)->where('is_default', true)->count());
        $this->assertFalse($r->json('data.pode_apagar'));

        $this->deleteJson(self::RAIZ . '/impostos/' . $t->id)->assertStatus(422);
        $this->assertNotNull($t->fresh());
    }

    /** @test */
    public function a_marca_grava_procura_e_apaga(): void
    {
        $this->tudoDe('invoicing.brands');

        $id = $this->postJson(self::RAIZ . '/marcas', ['name' => 'Cuca', 'icon' => 'fa-tag', 'website' => 'https://cuca.ao'])
            ->assertCreated()->json('data.id');

        $this->getJson(self::RAIZ . '/marcas?procura=cuc')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::RAIZ . '/marcas?procura=zzz')->assertOk()->assertJsonPath('meta.total', 0);

        $this->putJson(self::RAIZ . '/marcas/' . $id, ['name' => 'Cuca', 'icon' => 'fa-tag', 'website' => 'isto não é um url'])
            ->assertStatus(422)->assertJsonValidationErrors('website');

        $this->deleteJson(self::RAIZ . '/marcas/' . $id)->assertOk();
        $this->assertNull(Brand::find($id));
    }

    /* ─── Os filtros que a migração deixou cair ───────────────────────── */

    /**
     * A CIDADE DOS FORNECEDORES, escrita à mão e procurada por dentro.
     *
     * Era um filtro próprio no ecrã de sempre (`cityFilter`), e não uma lista:
     * uma lista de cidades não existe. «Luanda» tem de apanhar «Luanda Sul».
     *
     * @test
     */
    public function a_cidade_do_fornecedor_filtra_por_dentro(): void
    {
        $this->tudoDe('invoicing.suppliers');

        $sul = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor do Sul',
            'city' => 'Luanda Sul',
            'country' => 'AO',
        ]);

        $lubango = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor do Lubango',
            'city' => 'Lubango',
            'country' => 'AO',
        ]);

        $ids = collect($this->getJson(self::RAIZ . '/fornecedores?city=Luanda')->assertOk()->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($sul->id), '«Luanda» tem de apanhar «Luanda Sul»');
        $this->assertFalse($ids->contains($lubango->id));
    }

    /**
     * O INTERVALO DE DATAS SÓ ONDE FOI DECLARADO.
     *
     * Faz sentido numa lista de fornecedores («quem entrou este mês») e nenhum
     * numa de marcas. Aceitar o que não se usa convidava a acreditar que
     * filtrava — por isso é recusado, e não ignorado em silêncio.
     *
     * @test
     */
    public function o_intervalo_de_datas_so_existe_onde_o_esquema_o_declara(): void
    {
        $this->tudoDe('invoicing.suppliers');
        $this->tudoDe('invoicing.brands');

        $antigo = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor antigo',
            'country' => 'AO',
        ]);

        Supplier::where('id', $antigo->id)->update(['created_at' => now()->subMonths(3)]);

        $novo = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor de hoje',
            'country' => 'AO',
        ]);

        // O esquema anuncia-o, e é por isso que o ecrã desenha os dois campos.
        $this->getJson(self::RAIZ . '/fornecedores/opcoes')->assertOk()->assertJsonPath('datas', true);
        $this->getJson(self::RAIZ . '/marcas/opcoes')->assertOk()->assertJsonPath('datas', false);

        $ids = collect(
            $this->getJson(self::RAIZ . '/fornecedores?de=' . now()->subDay()->toDateString())
                ->assertOk()->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($novo->id));
        $this->assertFalse($ids->contains($antigo->id));

        // Nas marcas, que não o declaram, o pedido é RECUSADO.
        $this->getJson(self::RAIZ . '/marcas?de=' . now()->toDateString())
            ->assertStatus(422)->assertJsonValidationErrors('de');
    }

    /** O «por página» era aceite desde o primeiro dia — faltava o botão. @test */
    public function o_por_pagina_manda_no_tamanho_da_lista(): void
    {
        $this->tudoDe('invoicing.brands');

        foreach (range(1, 7) as $n) {
            Brand::create(['tenant_id' => $this->tenant->id, 'name' => "Marca {$n}", 'icon' => 'fa-tag']);
        }

        $this->getJson(self::RAIZ . '/marcas?por_pagina=5')->assertOk()->assertJsonCount(5, 'data');
        $this->getJson(self::RAIZ . '/marcas?por_pagina=100')->assertOk()->assertJsonCount(7, 'data');
    }

    /* ─── O extrato do fornecedor ─────────────────────────────────────── */

    /**
     * O EXTRATO — o modal de ver que a migração não trouxe.
     *
     * É o que se olha antes de negociar um preço ou de decidir mudar de
     * fornecedor: quanto já lhe comprámos, quanto lhe devemos, o que mais lhe
     * compramos e de quanto em quanto tempo.
     *
     * @test
     */
    public function o_extrato_do_fornecedor_conta_o_que_lhe_comprei(): void
    {
        $this->tudoDe('invoicing.suppliers');

        $fornecedor = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor com histórico',
            'country' => 'AO',
        ]);

        $artigo = $this->produtoComStock();

        foreach ([['2026-01-05', 2000.0, 500.0], ['2026-03-05', 6000.0, 6000.0]] as [$data, $total, $pago]) {
            $compra = \App\Models\Invoicing\PurchaseInvoice::create([
                'tenant_id' => $this->tenant->id,
                'supplier_id' => $fornecedor->id,
                'invoice_number' => 'FC EXTRATO/' . random_int(1000, 9999),
                'invoice_date' => $data,
                'due_date' => $data,
                'status' => 'pending',
                'total' => $total,
                'paid_amount' => $pago,
                'created_by' => $this->user->id,
            ]);

            \App\Models\Invoicing\PurchaseInvoiceItem::create([
                'purchase_invoice_id' => $compra->id,
                'product_id' => $artigo->id,
                'product_name' => 'Mercadoria do extrato',
                'quantity' => 3,
                'unit_price' => $total / 3,
                'total' => $total,
            ]);
        }

        $e = $this->getJson(self::RAIZ . "/fornecedores/{$fornecedor->id}/extrato")->assertOk()->json();

        $this->assertSame(2, $e['resumo']['documentos']);
        $this->assertEqualsWithDelta(8000, $e['resumo']['facturado'], 0.01);
        $this->assertEqualsWithDelta(6500, $e['resumo']['pago'], 0.01);
        $this->assertEqualsWithDelta(1500, $e['resumo']['pendente'], 0.01);

        // Nunca negativa: o `diffInDays` do Carbon 3 devolve valor com sinal.
        $this->assertGreaterThan(0, $e['resumo']['dias_entre']);

        $this->assertSame('Mercadoria do extrato', $e['artigos'][0]['nome']);
        $this->assertEqualsWithDelta(6, $e['artigos'][0]['quantidade'], 0.001);

        // O FORNECEDOR NÃO TEM EXTRAS: notas de crédito e recibos são do lado
        // do cliente. Uma caixa vazia a dizer «Recibos: 0» seria mentira.
        $this->assertSame([], $e['extras']);
    }

    /**
     * SÓ OS FORNECEDORES TÊM EXTRATO.
     *
     * Uma marca ou uma unidade de medida não têm nada disto, e um endereço que
     * responde a todos os catálogos com listas vazias faz acreditar que o
     * fornecedor não comprou nada.
     *
     * @test
     */
    public function so_os_fornecedores_tem_extrato(): void
    {
        $this->tudoDe('invoicing.brands');

        $marca = Brand::create(['tenant_id' => $this->tenant->id, 'name' => 'Marca sem extrato', 'icon' => 'fa-tag']);

        $this->getJson(self::RAIZ . "/marcas/{$marca->id}/extrato")->assertNotFound();

        // E o esquema anuncia-o, para o ecrã só desenhar o botão onde ele serve.
        $this->getJson(self::RAIZ . '/marcas/opcoes')->assertOk()->assertJsonPath('extrato', false);

        $this->tudoDe('invoicing.suppliers');
        $this->getJson(self::RAIZ . '/fornecedores/opcoes')->assertOk()->assertJsonPath('extrato', true);
    }

    /** O extrato de um fornecedor de outra empresa não abre. @test */
    public function o_extrato_de_um_fornecedor_alheio_nao_abre(): void
    {
        $this->tudoDe('invoicing.suppliers');

        $vizinha = Tenant::create([
            'name' => 'Vizinha',
            'slug' => 'vizinha-' . uniqid(),
            'email' => 'v' . uniqid() . '@ex.com',
        ]);

        $alheio = Supplier::create([
            'tenant_id' => $vizinha->id,
            'name' => 'Fornecedor alheio',
            'country' => 'AO',
        ]);

        $this->getJson(self::RAIZ . "/fornecedores/{$alheio->id}/extrato")->assertNotFound();
    }
}
