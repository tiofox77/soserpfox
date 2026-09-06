<?php

namespace Tests\Feature;

use App\Models\Invoicing\Import;
use App\Models\Supplier;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * A API DAS IMPORTAÇÕES, para o ecrã em React.
 *
 * O que ela promete e estes ensaios guardam: cada verbo tem a sua
 * permissão; a importação nasce numerada e em cotação; o CIF é sempre
 * FOB + frete + seguro, calculado no servidor; o fornecedor tem de ser
 * desta empresa; e o estado só muda para um do catálogo.
 */
class ApiDasImportacoesParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/importacoes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function fornecedor(?int $tenantId = null): Supplier
    {
        return Supplier::create(['tenant_id' => $tenantId ?? $this->tenant->id, 'name' => 'Fornecedor ' . uniqid(), 'type' => 'pessoa_juridica', 'is_active' => true]);
    }

    private function corpo(array $por = []): array
    {
        return array_merge([
            'supplier_id' => $this->fornecedor()->id,
            'order_date' => now()->toDateString(),
            'origin_country' => 'China',
            'transport_type' => 'maritime',
            'fob_value' => 10000,
            'freight_cost' => 1500,
            'insurance_cost' => 250,
            'cif_value' => 1,
        ], $por);
    }

    /** @test */
    public function cada_verbo_tem_a_sua_permissao(): void
    {
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();

        $this->comPermissoes('invoicing.imports.view');

        $r = $this->getJson(self::RAIZ . '/opcoes')->assertOk();
        $this->assertFalse($r->json('permissoes.pode_criar'));
        $this->assertCount(11, $r->json('estados'));

        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
    }

    /** O CIF é FOB + frete + seguro, calculado no servidor. @test */
    public function nasce_em_cotacao_com_o_cif_calculado_no_servidor(): void
    {
        $this->comPermissoes('invoicing.imports.view', 'invoicing.imports.create');

        $r = $this->postJson(self::RAIZ, $this->corpo())->assertCreated();

        $i = Import::find($r->json('data.id'));

        $this->assertStringStartsWith('IMP/' . date('Y') . '/', $i->import_number);
        $this->assertSame('quotation', $i->status);
        $this->assertEqualsWithDelta(11750, $i->cif_value, 0.01, 'o cif_value do pedido é ignorado');
        $this->assertSame('Luanda', $i->destination_port);

        $this->postJson(self::RAIZ, $this->corpo(['transport_type' => 'rocket', 'fob_value' => -1]))
            ->assertStatus(422)->assertJsonValidationErrors(['transport_type', 'fob_value']);
    }

    /** @test */
    public function o_fornecedor_tem_de_ser_desta_empresa(): void
    {
        $this->comPermissoes('invoicing.imports.view', 'invoicing.imports.create');

        $outra = Tenant::create(['name' => 'Vizinha', 'slug' => 'viz-' . uniqid(), 'nif' => (string) random_int(500000000, 599999999), 'email' => 'v' . uniqid() . '@x.ao', 'is_active' => true]);

        $this->postJson(self::RAIZ, $this->corpo(['supplier_id' => $this->fornecedor($outra->id)->id]))->assertStatus(422);
        $this->assertSame(0, Import::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function o_estado_so_muda_para_um_do_catalogo_e_a_edicao_recalcula_o_cif(): void
    {
        $this->comPermissoes('invoicing.imports.view', 'invoicing.imports.create', 'invoicing.imports.edit', 'invoicing.imports.delete');

        $id = $this->postJson(self::RAIZ, $this->corpo())->assertCreated()->json('data.id');

        $this->postJson(self::RAIZ . "/$id/estado", ['estado' => 'voando'])->assertStatus(422);
        $this->postJson(self::RAIZ . "/$id/estado", ['estado' => 'in_transit'])->assertOk()->assertJsonPath('data.estado', 'in_transit');

        $this->putJson(self::RAIZ . "/$id", $this->corpo(['fob_value' => 20000, 'freight_cost' => 0, 'insurance_cost' => 0]))->assertOk();
        $this->assertEqualsWithDelta(20000, Import::find($id)->cif_value, 0.01);

        $lista = $this->getJson(self::RAIZ . '?estado=in_transit')->assertOk();
        $this->assertSame(1, $lista->json('meta.total'));
        $this->assertSame(1, $lista->json('resumo.em_transito'));

        $this->deleteJson(self::RAIZ . "/$id")->assertOk();
        $this->assertNull(Import::find($id));
    }
}
