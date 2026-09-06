<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\TransportGuide;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * A API DAS GUIAS DE TRANSPORTE, para o ecrã em React.
 *
 * O que ela promete e estes ensaios guardam: a guia nasce numerada por
 * tipo e ano, emitida, com as linhas; sem linhas com quantidade não há
 * guia; a factura de origem dá o cliente e as linhas; anular gasta o
 * número; e a permissão é a do menu.
 */
class ApiDasGuiasParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/guias';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function artigo(): Product
    {
        $categoria = Category::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Geral'], ['is_active' => true]);
        $taxa = Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'], ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']);

        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => 'produto', 'price' => 1000, 'cost' => 0,
            'unit' => 'cx', 'category_id' => $categoria->id, 'tax_type' => 'iva', 'tax_rate_id' => $taxa->id,
            'manage_stock' => true, 'stock_quantity' => 100, 'is_active' => true,
        ]);
    }

    private function corpo(array $por = []): array
    {
        return array_merge([
            'type' => 'GT',
            'client_id' => $this->clienteEmpresa()->id,
            'issue_date' => now()->toDateString(),
            'vehicle_plate' => 'LD-12-34-AB',
            'linhas' => [['product_id' => $this->artigo()->id, 'product_name' => 'Caixas', 'description' => 'Caixas', 'quantity' => 3, 'unit' => 'cx']],
        ], $por);
    }

    /** @test */
    public function sem_permissao_nao_ha_nada(): void
    {
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
    }

    /** A permissão é a do menu: ver as notas de débito também abre as guias. @test */
    public function a_permissao_e_a_do_menu(): void
    {
        $this->comPermissoes('invoicing.debit-notes.view');

        $this->getJson(self::RAIZ . '/opcoes')->assertOk()->assertJsonPath('tipos.0.valor', 'GT');
    }

    /** @test */
    public function nasce_numerada_por_tipo_e_ano_e_com_as_linhas(): void
    {
        $this->comPermissoes('invoicing.debit-notes.view');

        $r = $this->postJson(self::RAIZ, $this->corpo())->assertCreated();

        $g = TransportGuide::find($r->json('data.id'));

        $this->assertSame('GT-' . date('Y') . '-0001', $g->guide_number);
        $this->assertSame('issued', $g->status);
        $this->assertSame(1, $g->items()->count());
        $this->assertEqualsWithDelta(3, $g->items()->first()->quantity, 0.001);

        $r2 = $this->postJson(self::RAIZ, $this->corpo(['type' => 'GR']))->assertCreated();
        $this->assertSame('GR-' . date('Y') . '-0001', TransportGuide::find($r2->json('data.id'))->guide_number, 'a remessa conta à parte');
    }

    /** @test */
    public function sem_linhas_com_quantidade_nao_ha_guia(): void
    {
        $this->comPermissoes('invoicing.debit-notes.view');

        $this->postJson(self::RAIZ, $this->corpo(['linhas' => []]))->assertStatus(422)->assertJsonValidationErrors('linhas');

        $this->postJson(self::RAIZ, $this->corpo(['linhas' => [['description' => 'Nada', 'quantity' => 0]]]))
            ->assertStatus(422)->assertJsonValidationErrors('linhas');

        $this->assertSame(0, TransportGuide::where('tenant_id', $this->tenant->id)->count());
    }

    /** A factura de origem dá o cliente e as linhas. @test */
    public function a_factura_de_origem_da_o_cliente_e_as_linhas(): void
    {
        $this->comPermissoes('invoicing.debit-notes.view', 'invoicing.sales.invoices.create');

        $artigo = $this->artigo();
        $cliente = $this->clienteEmpresa();

        $factura = $this->postJson('/api/v1/invoicing/react/factura', [
            'client_id' => $cliente->id, 'warehouse_id' => $this->armazem->id, 'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(),
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 4, 'price' => 1000]],
        ])->assertCreated()->json('id');

        $r = $this->getJson(self::RAIZ . "/facturas/$factura/linhas")->assertOk();

        $this->assertSame($cliente->id, $r->json('client_id'));
        $this->assertEqualsWithDelta(4, $r->json('linhas.0.quantity'), 0.001);
        $this->assertSame($artigo->id, $r->json('linhas.0.product_id'));

        $g = $this->postJson(self::RAIZ, $this->corpo(['invoice_id' => $factura, 'linhas' => $r->json('linhas')]))->assertCreated();

        $this->assertEqualsWithDelta(4560, TransportGuide::find($g->json('data.id'))->gross_total, 0.01, 'o valor da mercadoria é o da factura');
    }

    /** Anular gasta o número. @test */
    public function anular_gasta_o_numero(): void
    {
        $this->comPermissoes('invoicing.debit-notes.view');

        $id = $this->postJson(self::RAIZ, $this->corpo())->assertCreated()->json('data.id');

        $this->deleteJson(self::RAIZ . '/' . $id)->assertOk();

        $this->assertNull(TransportGuide::find($id), 'sai da lista');
        $this->assertSame('cancelled', TransportGuide::withTrashed()->find($id)->status);

        $r = $this->postJson(self::RAIZ, $this->corpo())->assertCreated();
        $this->assertSame('GT-' . date('Y') . '-0002', TransportGuide::find($r->json('data.id'))->guide_number);
    }
}
