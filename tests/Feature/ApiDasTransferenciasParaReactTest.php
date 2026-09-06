<?php

namespace Tests\Feature;

use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * A API DAS TRANSFERÊNCIAS, para os ecrãs em React.
 *
 * O que ela promete e estes ensaios guardam: a transferência entre
 * armazéns tira de um lado e põe no outro com os quatro saldos nas duas
 * pernas e os lotes a seguir por FEFO; o ajuste em lote entra e sai (e a
 * saída não negativa); entre empresas o artigo é copiado quando não existe
 * lá, cada empresa fica com o seu documento, e só se transfere para uma
 * empresa do utilizador.
 */
class ApiDasTransferenciasParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/transferencias';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function artigo(bool $lotes = false): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'code' => 'ART-' . uniqid(), 'type' => 'produto', 'price' => 100, 'cost' => 30,
            'unit' => 'un', 'tax_type' => 'isento', 'exemption_reason' => 'M99', 'manage_stock' => true, 'stock_quantity' => 0, 'track_batches' => $lotes, 'is_active' => true,
        ]);
    }

    private function comStock(Product $p, float $qtd, ?Warehouse $onde = null): void
    {
        $this->comPermissoes('invoicing.stock.edit');
        $this->postJson('/api/v1/invoicing/react/stock/entrada', [
            'armazem_id' => ($onde ?? $this->armazem)->id,
            'itens' => [['product_id' => $p->id, 'op' => 'add', 'quantity' => $qtd, 'unit_cost' => 30]],
        ])->assertCreated();
    }

    private function outroArmazem(): Warehouse
    {
        return Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Norte ' . uniqid(), 'code' => 'ARM-N-' . uniqid(), 'is_active' => true, 'is_default' => false]);
    }

    /** @test */
    public function cada_verbo_tem_a_sua_permissao(): void
    {
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();

        $this->comPermissoes('invoicing.stock.edit');
        $this->getJson(self::RAIZ . '/opcoes')->assertOk()->assertJsonPath('permissoes.pode_transferir', false);
        $this->postJson(self::RAIZ . '/entre-armazens', [])->assertForbidden();
        $this->postJson(self::RAIZ . '/entre-empresas', [])->assertForbidden();
    }

    /** @test */
    public function a_transferencia_tira_de_um_lado_e_poe_no_outro_com_os_saldos_nas_duas_pernas(): void
    {
        $this->comPermissoes('invoicing.warehouse-transfer.create');
        $norte = $this->outroArmazem();
        $a = $this->artigo(lotes: true);
        $this->comStock($a, 10);
        ProductBatch::create(['tenant_id' => $this->tenant->id, 'product_id' => $a->id, 'warehouse_id' => $this->armazem->id, 'batch_number' => 'L1', 'expiry_date' => now()->addMonth(), 'quantity' => 4, 'quantity_available' => 4, 'alert_days' => 30, 'status' => 'active']);
        ProductBatch::create(['tenant_id' => $this->tenant->id, 'product_id' => $a->id, 'warehouse_id' => $this->armazem->id, 'batch_number' => 'L2', 'expiry_date' => now()->addYear(), 'quantity' => 6, 'quantity_available' => 6, 'alert_days' => 30, 'status' => 'active']);

        $r = $this->postJson(self::RAIZ . '/entre-armazens', [
            'de' => $this->armazem->id, 'para' => $norte->id, 'notas' => 'Reposição',
            'itens' => [['product_id' => $a->id, 'product_name' => $a->name, 'quantity' => 6]],
        ])->assertCreated();

        $this->assertMatchesRegularExpression('#^MOV/\d{4}/\d+$#', $r->json('referencia'));
        $this->assertEqualsWithDelta(4, Stock::where('warehouse_id', $this->armazem->id)->where('product_id', $a->id)->value('quantity'), 0.001);
        $this->assertEqualsWithDelta(6, Stock::where('warehouse_id', $norte->id)->where('product_id', $a->id)->value('quantity'), 0.001);
        $this->assertEqualsWithDelta(10, $a->fresh()->stock_quantity, 0.001, 'o total do artigo não muda');

        // As duas pernas, com os saldos explícitos.
        $pernas = StockMovement::where('product_id', $a->id)->where('type', 'transfer')->orderBy('id')->get();
        $this->assertCount(2, $pernas);
        $this->assertEqualsWithDelta(10, $pernas[0]->balance_before, 0.001);
        $this->assertEqualsWithDelta(4, $pernas[0]->balance_after, 0.001);
        $this->assertEqualsWithDelta(0, $pernas[1]->balance_before, 0.001);
        $this->assertEqualsWithDelta(6, $pernas[1]->balance_after, 0.001);

        // FEFO: o L1 (expira primeiro) sai inteiro, o L2 dá o resto.
        $this->assertEqualsWithDelta(0, ProductBatch::where('batch_number', 'L1')->where('warehouse_id', $this->armazem->id)->value('quantity_available'), 0.001);
        $this->assertEqualsWithDelta(4, ProductBatch::where('batch_number', 'L2')->where('warehouse_id', $this->armazem->id)->value('quantity_available'), 0.001);
        $this->assertEqualsWithDelta(4, ProductBatch::where('batch_number', 'L1')->where('warehouse_id', $norte->id)->value('quantity_available'), 0.001);

        $this->postJson(self::RAIZ . '/entre-armazens', ['de' => $this->armazem->id, 'para' => $norte->id, 'itens' => [['product_id' => $a->id, 'quantity' => 50]]])
            ->assertStatus(422)->assertJsonValidationErrors('itens');

        $this->assertCount(1, $this->getJson(self::RAIZ . '/historico')->assertOk()->json('data'));
        $this->assertCount(2, $this->getJson(self::RAIZ . '/detalhes?referencia=' . $r->json('referencia'))->assertOk()->json('data'));
    }

    /** @test */
    public function o_ajuste_em_lote_entra_e_sai_sem_negativar(): void
    {
        $this->comPermissoes('invoicing.stock.edit');
        $a = $this->artigo();

        $r = $this->postJson(self::RAIZ . '/ajuste', ['armazem' => $this->armazem->id, 'tipo' => 'in', 'motivo' => 'Contagem', 'itens' => [['product_id' => $a->id, 'product_name' => $a->name, 'quantity' => 8]]])->assertCreated();
        $this->assertEqualsWithDelta(8, $a->fresh()->stock_quantity, 0.001);
        $this->assertEqualsWithDelta(0, $r->json('resumo.0.antes'), 0.001);
        $this->assertEqualsWithDelta(8, $r->json('resumo.0.depois'), 0.001);

        $this->postJson(self::RAIZ . '/ajuste', ['armazem' => $this->armazem->id, 'tipo' => 'out', 'motivo' => 'Avaria', 'itens' => [['product_id' => $a->id, 'product_name' => $a->name, 'quantity' => 20]]])
            ->assertStatus(422)->assertJsonValidationErrors('itens');
        $this->assertEqualsWithDelta(8, $a->fresh()->stock_quantity, 0.001, 'nada mudou');

        $this->postJson(self::RAIZ . '/ajuste', ['armazem' => $this->armazem->id, 'tipo' => 'out', 'motivo' => 'Avaria', 'itens' => [['product_id' => $a->id, 'quantity' => 3]]])->assertCreated();
        $this->assertEqualsWithDelta(5, $a->fresh()->stock_quantity, 0.001);
    }

    /** @test */
    public function entre_empresas_copia_o_artigo_e_cada_uma_fica_com_o_seu_documento(): void
    {
        $this->comPermissoes('invoicing.inter-company-transfer.create');

        $outra = Tenant::create(['name' => 'Filial', 'slug' => 'fil-' . uniqid(), 'nif' => (string) random_int(500000000, 599999999), 'email' => 'f' . uniqid() . '@x.ao', 'is_active' => true]);
        $this->user->tenants()->attach($outra->id);
        $armazemLa = Warehouse::withoutEvents(fn () => Warehouse::create(['tenant_id' => $outra->id, 'name' => 'Loja', 'code' => 'ARM-L-' . uniqid(), 'is_active' => true, 'is_default' => true]));

        $a = $this->artigo();
        $this->comStock($a, 10);

        // A empresa nova nasce com o seu armazém principal; o da loja é mais um.
        $this->assertContains($armazemLa->id, collect($this->getJson(self::RAIZ . "/empresas/{$outra->id}/armazens")->assertOk()->json('data'))->pluck('id')->all());

        $r = $this->postJson(self::RAIZ . '/entre-empresas', [
            'de_armazem' => $this->armazem->id, 'para_empresa' => $outra->id, 'para_armazem' => $armazemLa->id, 'notas' => 'Abastecer a loja',
            'itens' => [['product_id' => $a->id, 'product_name' => $a->name, 'quantity' => 4, 'unit_cost' => 30]],
        ])->assertCreated();

        $this->assertNotSame($r->json('referencia_origem'), $r->json('referencia_destino'), 'dois documentos, um por empresa');
        $this->assertEqualsWithDelta(6, $a->fresh()->stock_quantity, 0.001);

        $copia = Product::withoutGlobalScopes()->where('tenant_id', $outra->id)->where('name', $a->name)->first();
        $this->assertNotNull($copia, 'o artigo foi copiado para a empresa destino');
        $this->assertEqualsWithDelta(4, Stock::withoutGlobalScope('tenant')->where('tenant_id', $outra->id)->where('product_id', $copia->id)->value('quantity'), 0.001);
        $this->assertEqualsWithDelta(4, $copia->fresh()->stock_quantity, 0.001, 'o agregado do destino é mantido pelo observer');
    }

    /** @test */
    public function so_se_transfere_para_uma_empresa_do_utilizador(): void
    {
        $this->comPermissoes('invoicing.inter-company-transfer.create');

        $alheia = Tenant::create(['name' => 'Alheia', 'slug' => 'alh-' . uniqid(), 'nif' => (string) random_int(500000000, 599999999), 'email' => 'a' . uniqid() . '@x.ao', 'is_active' => true]);
        $armazemLa = Warehouse::withoutEvents(fn () => Warehouse::create(['tenant_id' => $alheia->id, 'name' => 'Loja', 'code' => 'ARM-A-' . uniqid(), 'is_active' => true, 'is_default' => true]));
        $a = $this->artigo();
        $this->comStock($a, 10);

        $this->getJson(self::RAIZ . "/empresas/{$alheia->id}/armazens")->assertNotFound();

        $this->postJson(self::RAIZ . '/entre-empresas', [
            'de_armazem' => $this->armazem->id, 'para_empresa' => $alheia->id, 'para_armazem' => $armazemLa->id, 'notas' => 'x',
            'itens' => [['product_id' => $a->id, 'quantity' => 1]],
        ])->assertStatus(422);

        $this->assertEqualsWithDelta(10, $a->fresh()->stock_quantity, 0.001);
    }
}
