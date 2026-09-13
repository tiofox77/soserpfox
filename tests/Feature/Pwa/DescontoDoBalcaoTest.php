<?php

namespace Tests\Feature\Pwa;

use App\Models\Product;
use App\Services\POS\PosSaleService;
use Tests\TenantTestCase;

/**
 * O DESCONTO DO BALCÃO É UMA PERCENTAGEM — e a factura tem de o tratar assim.
 *
 * Os dois balcões (o POS em React e o PWA sem rede) mandam o
 * `discount_commercial` em PERCENTAGEM: é o que o operador escreve («10» =
 * 10%), é o que as duas validações aceitam (0 a 100), e é com essa percentagem
 * que o talão provisório do aparelho faz as contas. O `PosSaleService` passava-o
 * ao cálculo como VALOR EM KZ.
 *
 * O que se via: 10% sobre 5.000 Kz dava 500 Kz de desconto no talão do
 * aparelho e 10 Kz na factura emitida — o cliente pagava uma coisa e o
 * documento fiscal dizia outra. E o papel imprimia «Desconto 10,00 Kz».
 *
 * O desconto POR VALOR do balcão online nem chegava ao servidor: ia a zero.
 * Passa a ir em `discount_value` (Kz).
 */
class DescontoDoBalcaoTest extends TenantTestCase
{
    private Product $artigo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        $this->artigo = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Água 1,5L',
            'code' => 'AGUA-' . uniqid(),
            'price' => 500,
            'cost_price' => 300,
            'type' => 'produto',
            'is_active' => true,
            'tax_id' => $this->imposto->id,
        ]);
    }

    private function venda(array $extra = []): array
    {
        return [
            'local_uuid' => 'desconto-' . uniqid(),
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $this->artigo->id,
                'product_name' => $this->artigo->name,
                'quantity' => 10,
                'unit_price' => 500,
            ]],
        ] + $extra;
    }

    public function test_dez_por_cento_sobre_cinco_mil_sao_quinhentos_kwanzas(): void
    {
        $factura = app(PosSaleService::class)->createFromPayload(
            $this->venda(['discount_commercial' => 10]),
            $this->tenant->id,
            $this->user->id,
        );

        // 5.000 − 10% = 4.500 de base; IVA 14% = 630; total 5.130.
        $this->assertEqualsWithDelta(500, (float) $factura->discount_amount, 0.01, 'o desconto é 10% do líquido, não 10 Kz');
        $this->assertEqualsWithDelta(500, (float) $factura->discount_commercial, 0.01, 'o papel imprime o desconto em Kz');
        $this->assertEqualsWithDelta(4500, (float) $factura->net_total, 0.01);
        $this->assertEqualsWithDelta(630, (float) $factura->tax_amount, 0.01);
        $this->assertEqualsWithDelta(5130, (float) $factura->total, 0.01);
    }

    /** E bate com o talão que o aparelho imprimiu sem rede (`totaisDaVenda` do motor). */
    public function test_a_factura_bate_com_o_talao_do_aparelho(): void
    {
        $motor = file_get_contents(resource_path('js/pwa/motor/vendas.ts'));
        $this->assertStringContainsString('const desconto = subtotal * pct / 100;', $motor,
            'o aparelho faz o desconto em percentagem — se isto mudar, este ensaio tem de mudar com ele');

        $factura = app(PosSaleService::class)->createFromPayload(
            $this->venda(['discount_commercial' => 12.5]),
            $this->tenant->id,
            $this->user->id,
        );

        $subtotal = 5000;
        $desconto = $subtotal * 12.5 / 100;
        $imposto = ($subtotal * 0.14) * (($subtotal - $desconto) / $subtotal);

        $this->assertEqualsWithDelta($subtotal - $desconto + $imposto, (float) $factura->total, 0.01);
    }

    public function test_o_desconto_por_valor_do_balcao_online_chega_a_factura(): void
    {
        $factura = app(PosSaleService::class)->createFromPayload(
            $this->venda(['discount_value' => 250]),
            $this->tenant->id,
            $this->user->id,
        );

        $this->assertEqualsWithDelta(250, (float) $factura->discount_amount, 0.01);
        $this->assertEqualsWithDelta(4750 * 1.14, (float) $factura->total, 0.01);
    }

    public function test_nenhum_desconto_passa_do_valor_da_venda(): void
    {
        $factura = app(PosSaleService::class)->createFromPayload(
            $this->venda(['discount_value' => 999999]),
            $this->tenant->id,
            $this->user->id,
        );

        $this->assertEqualsWithDelta(5000, (float) $factura->discount_amount, 0.01);
        $this->assertEqualsWithDelta(0, (float) $factura->total, 0.01);
    }

    public function test_sem_desconto_nada_muda(): void
    {
        $factura = app(PosSaleService::class)->createFromPayload($this->venda(), $this->tenant->id, $this->user->id);

        $this->assertEqualsWithDelta(0, (float) $factura->discount_amount, 0.01);
        $this->assertEqualsWithDelta(5700, (float) $factura->total, 0.01);
    }

    /** A porta da API do PWA aceita a percentagem e recusa o que não é percentagem. */
    public function test_a_api_do_pwa_recusa_mais_de_cem_por_cento(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/invoicing/pos/sale', $this->venda(['discount_commercial' => 150]))
            ->assertStatus(422);
    }
}
