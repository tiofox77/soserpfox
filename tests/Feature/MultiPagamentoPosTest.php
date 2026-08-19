<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalePayment;
use App\Models\Product;
use App\Services\POS\PosSaleService;
use Tests\TenantTestCase;

/**
 * Uma venda POS pode ser paga em várias formas (multi-tender).
 */
class MultiPagamentoPosTest extends TenantTestCase
{
    private function produto(): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo', 'code' => 'A' . uniqid(),
            'type' => 'produto', 'price' => 1000, 'manage_stock' => false,
            'is_active' => true, 'tax_type' => 'isento',
        ]);
    }

    private function vender(array $payments = null): \App\Models\Invoicing\SalesInvoice
    {
        $p = $this->produto();
        $payload = [
            'local_uuid' => 'mp-' . uniqid(),
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $p->id, 'product_name' => $p->name,
                'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0,
            ]],
        ];
        if ($payments !== null) {
            $payload['payments'] = $payments;
        }
        return app(PosSaleService::class)->createFromPayload($payload, $this->tenant->id, $this->user->id);
    }

    public function test_venda_com_uma_forma_grava_um_pagamento(): void
    {
        $inv = $this->vender(); // sem payments → single tender pelo total

        $pagamentos = SalePayment::where('sales_invoice_id', $inv->id)->get();
        $this->assertCount(1, $pagamentos);
        $this->assertEqualsWithDelta(1000, (float) $pagamentos->sum('amount'), 0.01);
    }

    public function test_venda_repartida_grava_dois_pagamentos_e_soma_o_total(): void
    {
        $inv = $this->vender([
            ['method' => 'cash', 'amount' => 600],
            ['method' => 'multicaixa', 'amount' => 400],
        ]);

        $pagamentos = SalePayment::where('sales_invoice_id', $inv->id)->get();
        $this->assertCount(2, $pagamentos);
        $this->assertEqualsWithDelta(1000, (float) $pagamentos->sum('amount'), 0.01);
        // A factura marca-se como múltipla.
        $this->assertSame('multiple', $inv->fresh()->payment_method);
    }

    public function test_a_tesouraria_recebe_uma_linha_por_forma(): void
    {
        $inv = $this->vender([
            ['method' => 'cash', 'amount' => 700],
            ['method' => 'multicaixa', 'amount' => 300],
        ]);

        $linhas = \App\Models\Treasury\Transaction::where('invoice_id', $inv->id)->get();
        $this->assertCount(2, $linhas);
        $this->assertEqualsWithDelta(1000, (float) $linhas->sum('amount'), 0.01);
    }

    public function test_pagamentos_que_nao_somam_o_total_sao_recusados(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->vender([
            ['method' => 'cash', 'amount' => 500],
            ['method' => 'multicaixa', 'amount' => 300], // soma 800 ≠ 1000
        ]);
    }
}
