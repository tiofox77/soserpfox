<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Stock;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * A API dos artigos.
 *
 * O QUE ESTES ENSAIOS GUARDAM é a regra que este código já aprendeu à sua
 * custa: **o `stock_quantity` é um agregado derivado das linhas**, mantido
 * pelo `StockObserver`. Escrevê-lo numa edição devolvia o valor que estava no
 * ecrã quando ele abriu — revertendo as vendas que aconteceram entretanto — e
 * criava ajustes fantasma sem movimento nem rasto.
 *
 * E a outra: um artigo já vendido não se apaga, desactiva-se. A linha da
 * factura aponta para ele.
 */
class ApiDosProdutosParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/products';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function categoria(): Category
    {
        return Category::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Geral'],
            ['is_active' => true]
        );
    }

    private function artigo(array $por = []): Product
    {
        return Product::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Artigo ' . uniqid(),
            'type' => 'produto',
            'price' => 1000,
            'unit' => 'un',
            'category_id' => $this->categoria()->id,
            'tax_type' => 'isento',
            'exemption_reason' => 'M99',
            'manage_stock' => true,
            'stock_quantity' => 10,
            'is_active' => true,
        ], $por));
    }

    private function corpo(array $por = []): array
    {
        return array_merge([
            'name' => 'Artigo de Ensaio',
            'type' => 'produto',
            'price' => 1500,
            'unit' => 'un',
            'category_id' => $this->categoria()->id,
            'tax_type' => 'isento',
            'exemption_reason' => 'M99',
        ], $por);
    }

    /* ─── Permissões ──────────────────────────────────────────────────── */

    /** @test */
    public function cada_verbo_tem_a_sua_permissao(): void
    {
        $artigo = $this->artigo();

        $this->getJson(self::RAIZ)->assertForbidden();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
        $this->putJson(self::RAIZ . '/' . $artigo->id, $this->corpo())->assertForbidden();
        $this->deleteJson(self::RAIZ . '/' . $artigo->id)->assertForbidden();

        $this->comPermissoes('invoicing.products.view');

        $this->getJson(self::RAIZ)->assertOk();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
    }

    /* ─── A REGRA DO STOCK ────────────────────────────────────────────── */

    /**
     * EDITAR UM ARTIGO NÃO MEXE NO STOCK. Nunca.
     *
     * @test
     */
    public function a_edicao_nao_toca_no_stock(): void
    {
        $this->comPermissoes('invoicing.products.edit');

        $artigo = $this->artigo(['stock_quantity' => 42]);

        // O corpo até traz `stock_quantity` — e tem de ser ignorado.
        $this->putJson(self::RAIZ . '/' . $artigo->id, $this->corpo([
            'name' => 'Nome Novo',
            'stock_quantity' => 999,
        ]))->assertOk();

        $this->assertSame(42.0, (float) $artigo->fresh()->stock_quantity,
            'o agregado do stock não pode mudar por uma edição de ficha');
        $this->assertSame('Nome Novo', $artigo->fresh()->name);
    }

    /** Na CRIAÇÃO, a quantidade inicial entra — é a única altura em que entra. @test */
    public function a_criacao_aceita_a_quantidade_inicial(): void
    {
        $this->comPermissoes('invoicing.products.create');

        $id = $this->postJson(self::RAIZ, $this->corpo(['stock_quantity' => 7]))
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(7.0, (float) Product::find($id)->stock_quantity);
    }

    /**
     * O STOCK QUE SAI É A SOMA DAS LINHAS, não a coluna agregada.
     *
     * Quando as duas discordam — e discordam quando o agregado ficou para
     * trás — é a soma que diz a verdade.
     *
     * @test
     */
    public function o_stock_mostrado_vem_das_linhas(): void
    {
        $this->comPermissoes('invoicing.products.view');

        $artigo = $this->artigo(['stock_quantity' => 999]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $artigo->id,
            'warehouse_id' => $this->armazem->id,
            'quantity' => 3,
        ]);

        $linha = collect($this->getJson(self::RAIZ)->json('data'))->firstWhere('id', $artigo->id);

        $this->assertSame(3.0, (float) $linha['stock'],
            'o que se mostra é a soma das linhas, não o agregado desactualizado');
    }

    /** Um SERVIÇO não gere stock, mesmo que peçam. @test */
    public function um_servico_nao_gere_stock(): void
    {
        $this->comPermissoes('invoicing.products.create');

        $id = $this->postJson(self::RAIZ, $this->corpo([
            'type' => 'servico',
            'manage_stock' => true,
        ]))->assertCreated()->json('data.id');

        $this->assertFalse((bool) Product::find($id)->manage_stock,
            'um serviço com manage_stock ligado desaparecia do POS ao chegar a zero');
    }

    /**
     * OS CAMPOS DEIXADOS EM BRANCO NÃO REBENTAM A GRAVAÇÃO.
     *
     * `cost` e `stock_min` são NOT NULL *com omissão na base* — e uma omissão
     * só se aplica quando a coluna não vem no INSERT. Mandar `null`
     * explicitamente atropela-a: «Column 'cost' cannot be null», 500 no ecrã.
     * Um campo em branco no formulário chega cá como null.
     *
     * @test
     */
    public function os_campos_numericos_em_branco_gravam_como_zero(): void
    {
        $this->comPermissoes('invoicing.products.create');

        $id = $this->postJson(self::RAIZ, $this->corpo([
            'cost' => null,
            'stock_min' => null,
        ]))->assertCreated()->json('data.id');

        $artigo = Product::find($id);

        $this->assertSame(0.0, (float) $artigo->cost);
        $this->assertSame(0, (int) $artigo->stock_min);
    }

    /* ─── O imposto ───────────────────────────────────────────────────── */

    /** @test */
    public function o_imposto_e_uma_taxa_do_catalogo_ou_uma_isencao_com_motivo(): void
    {
        $this->comPermissoes('invoicing.products.create');

        // IVA sem taxa escolhida: recusa.
        $this->postJson(self::RAIZ, $this->corpo(['tax_type' => 'iva', 'tax_rate_id' => null]))
            ->assertJsonValidationErrors('tax_rate_id');

        // Isento sem motivo: recusa. A AGT exige o motivo da isenção.
        $this->postJson(self::RAIZ, $this->corpo(['tax_type' => 'isento', 'exemption_reason' => null]))
            ->assertJsonValidationErrors('exemption_reason');
    }

    /** Escolher isento limpa a taxa, e vice-versa: nunca os dois. @test */
    public function nao_ficam_os_dois_gravados(): void
    {
        $this->comPermissoes('invoicing.products.create', 'invoicing.products.edit');

        $taxa = \App\Models\Invoicing\Tax::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'],
            ['rate' => 14, 'is_active' => true]
        );

        $id = $this->postJson(self::RAIZ, $this->corpo([
            'tax_type' => 'isento',
            'exemption_reason' => 'M99',
            'tax_rate_id' => $taxa->id,
        ]))->assertCreated()->json('data.id');

        $this->assertNull(Product::find($id)->tax_rate_id,
            'isento não guarda taxa nenhuma');
    }

    /* ─── Apagar ──────────────────────────────────────────────────────── */

    /**
     * UM ARTIGO JÁ VENDIDO NÃO SE APAGA — DESACTIVA-SE.
     *
     * A linha da factura aponta para ele; apagá-lo deixava documentos fiscais
     * a referir um artigo que já não existe.
     *
     * @test
     */
    public function um_artigo_ja_vendido_desactiva_se_em_vez_de_ser_apagado(): void
    {
        $this->comPermissoes('invoicing.products.delete');

        $artigo = $this->artigo();

        $factura = SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT TESTE/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'total' => 1000,
            'created_by' => $this->user->id,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $factura->id,
            'product_id' => $artigo->id,
            'product_name' => $artigo->name,
            'description' => $artigo->name,
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this->deleteJson(self::RAIZ . '/' . $artigo->id)
            ->assertOk()
            ->assertJsonPath('desactivado', true);

        $fresco = $artigo->fresh();

        $this->assertNotNull($fresco, 'não pode ter sido apagado');
        $this->assertFalse((bool) $fresco->is_active, 'tinha de ficar desactivado');
    }

    /** Um artigo nunca vendido apaga-se. @test */
    public function um_artigo_nunca_vendido_apaga_se(): void
    {
        $this->comPermissoes('invoicing.products.delete');

        $artigo = $this->artigo();

        $this->deleteJson(self::RAIZ . '/' . $artigo->id)
            ->assertOk()
            ->assertJsonPath('desactivado', false);

        $this->assertSoftDeleted('invoicing_products', ['id' => $artigo->id]);
    }

    /* ─── Ler ─────────────────────────────────────────────────────────── */

    /** @test */
    public function nao_se_veem_os_artigos_de_outra_empresa(): void
    {
        $this->comPermissoes('invoicing.products.view');

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra',
            'slug' => 'outra-' . uniqid(),
            'email' => 'o' . uniqid() . '@ex.com',
        ]);

        $meu = $this->artigo();
        $alheio = Product::create([
            'tenant_id' => $outra->id,
            'name' => 'Artigo Alheio',
            'type' => 'produto',
            'price' => 1,
            'unit' => 'un',
            'tax_type' => 'isento',
        ]);

        $ids = collect($this->getJson(self::RAIZ)->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($meu->id));
        $this->assertFalse($ids->contains($alheio->id));
    }

    /** «Em falta» só conta em quem gere stock E tem mínimo definido. @test */
    public function em_falta_exige_minimo_definido(): void
    {
        $this->comPermissoes('invoicing.products.view');

        $semMinimo = $this->artigo(['stock_quantity' => 0, 'stock_min' => 0]);
        $comMinimo = $this->artigo(['stock_quantity' => 1, 'stock_min' => 5]);
        $servico = $this->artigo(['type' => 'servico', 'manage_stock' => false]);

        $porId = collect($this->getJson(self::RAIZ)->json('data'))->keyBy('id');

        $this->assertFalse($porId[$semMinimo->id]['em_falta'],
            'sem mínimo definido, todo o artigo a zero aparecia sempre em falta');
        $this->assertTrue($porId[$comMinimo->id]['em_falta']);
        $this->assertFalse($porId[$servico->id]['em_falta'], 'um serviço nunca está em falta');
        $this->assertNull($porId[$servico->id]['stock'], 'e não tem stock nenhum para mostrar');
    }
}
