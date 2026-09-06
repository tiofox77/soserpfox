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
}
