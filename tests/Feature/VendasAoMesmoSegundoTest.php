<?php

namespace Tests\Feature;

use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * DUAS VENDAS AO MESMO SEGUNDO.
 *
 * A Luk Simões recebeu a FR 003253 e a FR 003254 com um segundo de diferença
 * (23/09/2026): o stock desceu uma vez por duas vendas (as duas leram 11 e
 * gravaram 10), e a 003254 assinou-se com o anterior da 003253, o que bifurcou
 * a cadeia. Na série havia mais 24 bifurcações iguais.
 *
 * A causa é a mesma nos dois sítios. Dentro da transacção, um SELECT simples
 * lê a fotografia tirada no início, e só uma leitura trancada lê o que está
 * gravado. Não se simulam aqui duas ligações ao mesmo tempo; prova-se que as
 * duas leituras que decidem vão trancadas.
 */
class VendasAoMesmoSegundoTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    private Product $artigo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes('invoicing.pos.access', 'invoicing.pos.sell');
        PosShift::createSafely([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'status' => 'open', 'opened_at' => now(), 'opening_balance' => 0,
        ], $this->tenant->id);

        $taxa = Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'], ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']);
        $this->artigo = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Inalador ' . uniqid(), 'type' => 'produto', 'price' => 500, 'cost' => 200,
            'unit' => 'UN', 'tax_type' => 'iva', 'tax_rate_id' => $taxa->id, 'manage_stock' => true, 'is_active' => true,
        ]);

        Stock::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'warehouse_id' => getOrCreateDefaultWarehouse()->id, 'product_id' => $this->artigo->id],
            ['quantity' => 11]
        );
    }

    private function vender(string $uuid): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(self::RAIZ . '/pos/vender', [
            'local_uuid' => $uuid,
            'payment_method' => 'cash',
            'amount_received' => 570,
            'items' => [['product_id' => $this->artigo->id, 'product_name' => $this->artigo->name, 'quantity' => 1, 'unit_price' => 500]],
        ]);
    }

    public function test_o_stock_e_o_anterior_da_cadeia_leem_se_trancados(): void
    {
        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            $consultas[] = strtolower($q->sql);
        });

        $this->vender('venda-' . uniqid())->assertSuccessful();

        $doStock = array_filter($consultas, fn ($s) => str_starts_with($s, 'select') && str_contains($s, '`invoicing_stocks`'));
        $this->assertNotEmpty($doStock);
        $this->assertTrue(
            (bool) array_filter($doStock, fn ($s) => str_contains($s, 'for update')),
            'a linha do stock tem de ser lida sob bloqueio'
        );

        $doAnterior = array_filter($consultas, fn ($s) => str_contains($s, '`saft_hash`') && str_contains($s, '`invoicing_sales_invoices`') && str_starts_with($s, 'select'));
        $this->assertNotEmpty($doAnterior);
        $this->assertTrue(
            (bool) array_filter($doAnterior, fn ($s) => str_contains($s, 'for update')),
            'o documento anterior da cadeia tem de ser lido sob bloqueio'
        );
    }

    public function test_duas_vendas_seguidas_encadeiam_e_descem_o_stock_duas_vezes(): void
    {
        $a = $this->vender('venda-a-' . uniqid())->assertSuccessful()->json('id');
        // Outro cliente ao balcão: fecha-se o talão, lê-se o artigo e confirma-se.
        $this->travel(5)->seconds();
        $b = $this->vender('venda-b-' . uniqid())->assertSuccessful()->json('id');

        $this->assertNotSame($a, $b);
        $primeira = SalesInvoice::withoutGlobalScopes()->find($a);
        $segunda = SalesInvoice::withoutGlobalScopes()->find($b);

        $this->assertSame($primeira->saft_hash, $segunda->hash_previous, 'a segunda liga-se à primeira');
        $this->assertEqualsWithDelta(9, (float) Stock::where('product_id', $this->artigo->id)->value('quantity'), 0.001);
    }

    /**
     * O CASO DA LUK SIMÕES, com o balcão antigo: dois cliques, dois
     * identificadores, a mesma venda. O servidor trava sem depender do ecrã.
     */
    public function test_o_mesmo_gesto_com_identificadores_diferentes_e_uma_venda_so(): void
    {
        $a = $this->vender('clique-1-' . uniqid())->assertSuccessful()->json('id');
        $b = $this->vender('clique-2-' . uniqid())->assertSuccessful()->json('id');

        $this->assertSame($a, $b, 'o segundo clique recebe a venda do primeiro');
        $this->assertSame(1, SalesInvoice::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('created_by', $this->user->id)->count());
        $this->assertEqualsWithDelta(10, (float) Stock::where('product_id', $this->artigo->id)->value('quantity'), 0.001, 'o stock desce uma vez');
        $this->assertSame(1, \App\Models\Invoicing\PosShiftTransaction::where('reference_id', $a)->count(), 'o dinheiro entra uma vez no turno');
    }

    /** Uma venda DIFERENTE no mesmo segundo é outra venda. */
    public function test_outra_venda_no_mesmo_segundo_nao_e_travada(): void
    {
        $a = $this->vender('venda-a-' . uniqid())->assertSuccessful()->json('id');

        $b = $this->postJson(self::RAIZ . '/pos/vender', [
            'local_uuid' => 'venda-b-' . uniqid(),
            'payment_method' => 'cash',
            'amount_received' => 1140,
            'items' => [['product_id' => $this->artigo->id, 'product_name' => $this->artigo->name, 'quantity' => 2, 'unit_price' => 500]],
        ])->assertSuccessful()->json('id');

        $this->assertNotSame($a, $b);
        $this->assertEqualsWithDelta(8, (float) Stock::where('product_id', $this->artigo->id)->value('quantity'), 0.001);
    }

    public function test_o_mesmo_identificador_e_a_mesma_venda(): void
    {
        $uuid = 'venda-' . uniqid();

        $a = $this->vender($uuid)->assertSuccessful()->json('id');
        $b = $this->vender($uuid)->assertSuccessful()->json('id');

        $this->assertSame($a, $b);
        $this->assertSame(1, SalesInvoice::withoutGlobalScopes()->where('local_uuid', $uuid)->count());
        $this->assertEqualsWithDelta(10, (float) Stock::where('product_id', $this->artigo->id)->value('quantity'), 0.001, 'o stock desce uma vez');
    }
}
