<?php

namespace Tests\Feature\Tesouraria;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Product;
use App\Services\Invoicing\ModuleInvoiceService;
use App\Services\Invoicing\SomasDasFacturas;
use Tests\TenantTestCase;

/**
 * UM DOCUMENTO PAGO NÃO É UMA DÍVIDA (20/09/2026).
 *
 * O `ModuleInvoiceService` — a porta por onde o restaurante, o hotel, o salão
 * e a oficina facturam — nunca escrevia `paid_amount`. Três chamadores
 * marcavam `status = 'paid'` e ficava-se por aí.
 *
 * Só que nesta casa o «por receber» conta-se pelo SALDO e não pelo nome do
 * estado (ver `SomasDasFacturas::sqlPorReceber`): cada conta de restaurante
 * paga em dinheiro, cada sinal de hotel e cada fecho de estada ficava na
 * dívida do cliente, pelo valor inteiro, para sempre.
 */
class DocumentoPagoNaoEDividaTest extends TenantTestCase
{
    private Product $artigo;

    private Client $freguês;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        $this->freguês = $this->clienteEmpresa();

        $this->artigo = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo', 'code' => 'ART-PAG',
            'sale_price' => 1000, 'type' => 'produto', 'manage_stock' => false, 'is_active' => true,
        ]);
    }

    private function emitir(array $troca = []): SalesInvoice
    {
        return app(ModuleInvoiceService::class)->emitir(array_merge([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->freguês->id,
            'invoice_type' => 'FR',
            'status' => 'paid',
            'lines' => [[
                'product_id' => $this->artigo->id, 'name' => $this->artigo->name,
                'quantity' => 1, 'unit_price' => 5000,
            ]],
        ], $troca));
    }

    /** O «por receber» tal como os painéis e as listas o contam. */
    private function porReceber(): float
    {
        return (float) (new SomasDasFacturas())
            ->de(SalesInvoice::where('tenant_id', $this->tenant->id))['por_receber'];
    }

    public function test_uma_factura_paga_nasce_com_o_valor_pago(): void
    {
        $f = $this->emitir();

        $this->assertEqualsWithDelta((float) $f->total, (float) $f->paid_amount, 0.01,
            'quem marca o documento como pago tem de dizer quanto foi pago');
        $this->assertEqualsWithDelta(0, (float) $f->balance, 0.01);
    }

    public function test_uma_conta_paga_nao_entra_na_divida_do_cliente(): void
    {
        $this->emitir();

        $this->assertEqualsWithDelta(0, $this->porReceber(), 0.01,
            'uma conta paga ao balcão não é dívida de ninguém');
    }

    /**
     * O PERIGO A SÉRIO: receber duas vezes o mesmo dinheiro.
     *
     * O ecrã dos Recibos oferece as facturas com saldo, e exclui `draft`,
     * `cancelled` e `credited` — mas NÃO `paid`. Uma factura marcada como paga
     * com `paid_amount` a zero passava as duas peneiras e aparecia na lista
     * como se houvesse alguma coisa a receber: o sinal do hotel que o hóspede
     * já tinha pago ficava ali, à espera de ser cobrado outra vez.
     */
    public function test_uma_factura_ja_paga_nao_se_oferece_para_receber_outra_vez(): void
    {
        $this->comPermissoes('invoicing.receipts.view', 'invoicing.receipts.create');

        $factura = $this->emitir(['invoice_type' => 'FT']);

        $resposta = $this->getJson('/api/v1/invoicing/react/recibos/facturas?tipo=sale')
            ->assertOk();

        $numeros = collect($resposta->json('data'))->pluck('numero')->all();

        $this->assertNotContains($factura->invoice_number, $numeros,
            'uma factura já paga não pode aparecer na lista do que falta receber');
    }

    public function test_um_pagamento_parcial_deixa_so_o_que_falta(): void
    {
        $f = $this->emitir(['status' => 'partial', 'pago' => 2000]);

        $this->assertEqualsWithDelta(2000, (float) $f->paid_amount, 0.01);
        $this->assertEqualsWithDelta((float) $f->total - 2000, (float) $f->balance, 0.01);
    }

    public function test_uma_factura_a_prazo_continua_a_ser_divida(): void
    {
        $f = $this->emitir(['invoice_type' => 'FT', 'status' => 'sent']);

        $this->assertEqualsWithDelta(0, (float) $f->paid_amount, 0.01);
        $this->assertEqualsWithDelta((float) $f->total, $this->porReceber(), 0.01,
            'uma FT por pagar continua inteira na dívida');
    }
}
