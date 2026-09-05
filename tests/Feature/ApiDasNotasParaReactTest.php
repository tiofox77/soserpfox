<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * A API DAS NOTAS, para o ecrã em React.
 *
 * O que ela promete e estes ensaios guardam: não há lógica fiscal aqui — tudo
 * passa pelo `EmissorDeNotas`. O browser manda a QUANTIDADE; a taxa, o código
 * SAFT e a região vêm da linha original mesmo que o pedido diga outra coisa. E
 * o E43 chega como 422 com a razão no campo das linhas, sem nota nascida.
 */
class ApiDasNotasParaReactTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        foreach ([['NC', 'credit_note'], ['ND', 'debit_note']] as [$codigo, $tipo]) {
            InvoicingSeries::create([
                'tenant_id' => $this->tenant->id, 'series_code' => $codigo, 'name' => "{$codigo} (ensaio)",
                'document_type' => $tipo, 'agt_environment' => 'sandbox', 'is_default' => true, 'is_active' => true,
            ]);
        }
    }

    private function rota(string $tipo, string $cauda = ''): string
    {
        return "/api/v1/invoicing/react/notas/{$tipo}{$cauda}";
    }

    /** Uma factura com uma linha de 2 × 1000 a 14%, região AO-CAB. */
    private function factura(?int $autor = null): SalesInvoice
    {
        $categoria = Category::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Geral'], ['is_active' => true]);
        $taxa = Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'], ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']);

        $artigo = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => 'produto', 'price' => 1000,
            'unit' => 'un', 'category_id' => $categoria->id, 'tax_type' => 'iva', 'tax_rate_id' => $taxa->id, 'is_active' => true,
        ]);

        $f = SalesInvoice::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT/' . random_int(1000, 9999), 'invoice_date' => now()->toDateString(),
            'status' => 'sent', 'subtotal' => 2000, 'tax_amount' => 280, 'total' => 2280, 'paid_amount' => 0,
            'created_by' => $autor ?? $this->user->id,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id, 'product_id' => $artigo->id, 'product_name' => $artigo->name,
            'description' => $artigo->name, 'quantity' => 2, 'unit_price' => 1000, 'subtotal' => 2000,
            'tax_rate' => 14, 'tax_amount' => 280, 'total' => 2280, 'tax_code' => 'NOR',
            'tax_country_region' => 'AO-CAB', 'order' => 1,
        ]);

        return $f->fresh(['items']);
    }

    /** @test */
    public function um_tipo_inventado_da_404_e_ver_nao_da_direito_a_emitir(): void
    {
        $this->comPermissoes('invoicing.credit-notes.view');

        $this->getJson($this->rota('reembolso', '/opcoes'))->assertNotFound();
        $this->getJson($this->rota('credito', '/opcoes'))->assertOk();
        $this->getJson($this->rota('debito', '/opcoes'))->assertForbidden();
        $this->postJson($this->rota('credito'), [])->assertForbidden();
    }

    /**
     * AS LINHAS VÊM COM O IMPOSTO DA LINHA ORIGINAL.
     *
     * @test
     */
    public function as_linhas_da_factura_trazem_a_taxa_e_a_regiao_originais(): void
    {
        $this->comPermissoes('invoicing.credit-notes.view');

        $f = $this->factura();

        $linha = $this->getJson($this->rota('credito', "/facturas/{$f->id}/linhas"))
            ->assertOk()
            ->json('data.0');

        $this->assertSame($f->items->first()->id, $linha['origem_line_id']);
        $this->assertEqualsWithDelta(14, $linha['tax_rate'], 0.01);
        $this->assertSame('AO-CAB', $linha['tax_country_region']);
    }

    /** A factura de um colega não se vê nem se credita. @test */
    public function a_factura_do_colega_nao_esta_ao_alcance(): void
    {
        $this->comPermissoes('invoicing.credit-notes.view', 'invoicing.credit-notes.create');

        $colega = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $dele = $this->factura($colega->id);

        $this->getJson($this->rota('credito', "/facturas/{$dele->id}/linhas"))->assertNotFound();

        $this->postJson($this->rota('credito'), [
            'client_id' => $dele->client_id, 'invoice_id' => $dele->id, 'issue_date' => now()->toDateString(),
            'reason' => 'return', 'type' => 'total',
            'linhas' => [['origem_line_id' => $dele->items->first()->id, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    /**
     * O BROWSER SÓ MANDA A QUANTIDADE. O resto herda-se, mesmo que ele minta.
     *
     * @test
     */
    public function emite_uma_nota_de_credito_herdando_o_imposto_mesmo_que_o_pedido_minta(): void
    {
        $this->comPermissoes('invoicing.credit-notes.create');

        $f = $this->factura();

        $r = $this->postJson($this->rota('credito'), [
            'client_id' => $f->client_id, 'invoice_id' => $f->id, 'issue_date' => now()->toDateString(),
            'reason' => 'return', 'type' => 'partial',
            'linhas' => [[
                'origem_line_id' => $f->items->first()->id,
                'quantity' => 1,
                // A mentir: isenção e preço a zero. Tem de ser ignorado.
                'tax_rate' => 0, 'price' => 0,
            ]],
        ])->assertCreated();

        $this->assertNotEmpty($r->json('numero'));
        $this->assertEqualsWithDelta(1140, $r->json('total'), 0.01, '1 × 1000 a 14% — o preço e a taxa são os da factura');

        $item = \App\Models\Invoicing\CreditNoteItem::where('credit_note_id', $r->json('id'))->first();
        $this->assertSame('AO-CAB', $item->tax_country_region);
        $this->assertSame('NOR', $item->tax_code);
    }

    /**
     * O E43 CHEGA COMO 422, NO CAMPO DAS LINHAS, SEM NOTA NASCIDA.
     *
     * @test
     */
    public function o_travao_do_e43_responde_422_e_nao_deixa_nota_a_meio(): void
    {
        $this->comPermissoes('invoicing.credit-notes.create');

        $f = $this->factura();
        $antes = \App\Models\Invoicing\CreditNote::count();

        $this->postJson($this->rota('credito'), [
            'client_id' => $f->client_id, 'invoice_id' => $f->id, 'issue_date' => now()->toDateString(),
            'reason' => 'return', 'type' => 'partial',
            // 69 de uma linha que teve 2.
            'linhas' => [['origem_line_id' => $f->items->first()->id, 'quantity' => 69]],
        ])->assertStatus(422)->assertJsonValidationErrors('linhas');

        $this->assertSame($antes, \App\Models\Invoicing\CreditNote::count(), 'nenhuma nota nasceu');
    }

    /** Uma factura já totalmente creditada não aparece para creditar; para debitar, aparece. @test */
    public function a_lista_de_facturas_respeita_o_saldo_so_no_credito(): void
    {
        $this->comPermissoes('invoicing.credit-notes.view', 'invoicing.credit-notes.create', 'invoicing.debit-notes.view');

        $f = $this->factura();

        $this->postJson($this->rota('credito'), [
            'client_id' => $f->client_id, 'invoice_id' => $f->id, 'issue_date' => now()->toDateString(),
            'reason' => 'return', 'type' => 'total',
            'linhas' => [['origem_line_id' => $f->items->first()->id, 'quantity' => 2]],
        ])->assertCreated();

        $paraCreditar = collect($this->getJson($this->rota('credito', '/facturas?cliente_id=' . $f->client_id))->json('data'))->pluck('id');
        $paraDebitar = collect($this->getJson($this->rota('debito', '/facturas?cliente_id=' . $f->client_id))->json('data'))->pluck('id');

        $this->assertFalse($paraCreditar->contains($f->id), 'já não há nada por anular');
        $this->assertTrue($paraDebitar->contains($f->id), 'mas debitar não tem tecto');
    }

    /** Uma nota de débito emite-se pela mesma porta. @test */
    public function emite_uma_nota_de_debito(): void
    {
        $this->comPermissoes('invoicing.debit-notes.create');

        $f = $this->factura();

        $r = $this->postJson($this->rota('debito'), [
            'client_id' => $f->client_id, 'invoice_id' => $f->id, 'issue_date' => now()->toDateString(),
            'reason' => 'correction',
            'linhas' => [['origem_line_id' => $f->items->first()->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertNotEmpty($r->json('numero'));
        $this->assertNotEmpty(\App\Models\Invoicing\DebitNote::find($r->json('id'))->saft_hash, 'entra na cadeia');
    }
}
