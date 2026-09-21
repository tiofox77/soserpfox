<?php

namespace Tests\Feature\Tesouraria;

use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Product;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Models\Treasury\CashRegister;
use App\Services\Restaurant\RestaurantCheckoutService;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * O RESTAURANTE CONTA NO FECHO DE CAIXA (20/09/2026).
 *
 * O `RestaurantCheckoutService` EXIGE turno aberto logo à entrada — e depois
 * nunca lá escrevia nada. Uma casa vendia a noite inteira, o dinheiro entrava
 * na tesouraria, e o fecho de turno dava ZERO: quem contava a gaveta
 * encontrava um excesso do tamanho da noite toda, e não havia como saber de
 * onde vinha.
 *
 * Exigir o turno e ignorá-lo era o pior dos dois mundos: o incómodo de o abrir
 * sem nenhum dos proveitos de o ter.
 */
class RestauranteNoFechoDeCaixaTest extends TenantTestCase
{
    private Venue $venue;

    private DiningTable $mesa;

    private Product $prato;

    private PosShift $turno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant')->comModulo('invoicing');

        RestaurantSettings::forTenant($this->tenant->id)->update([
            'default_warehouse_id' => $this->armazem->id,
            'require_open_shift' => true,
            'use_kitchen_workflow' => false,
        ]);

        CashRegister::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Balcão', 'code' => 'CXR' . random_int(100, 999),
            'user_id' => $this->user->id, 'is_active' => true, 'is_default' => true, 'status' => 'open',
            'opening_balance' => 0, 'current_balance' => 0, 'expected_balance' => 0,
        ]);

        $this->turno = PosShift::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'shift_number' => 'REST-' . strtoupper(substr(uniqid(), -8)),
            'opened_at' => now(), 'opening_balance' => 0, 'status' => 'open',
        ]);

        $this->venue = Venue::create([
            'tenant_id' => $this->tenant->id, 'code' => 'PRINCIPAL',
            'name' => 'Restaurante Principal', 'warehouse_id' => $this->armazem->id,
        ]);

        $area = Area::create([
            'tenant_id' => $this->tenant->id, 'venue_id' => $this->venue->id, 'name' => 'Sala',
        ]);

        $this->mesa = DiningTable::create([
            'tenant_id' => $this->tenant->id, 'venue_id' => $this->venue->id, 'area_id' => $area->id,
            'code' => 'M01', 'name' => 'Mesa 01', 'capacity' => 4,
        ]);

        $this->prato = Product::create([
            'tenant_id' => $this->tenant->id, 'type' => 'produto', 'name' => 'Muamba de galinha',
            'price' => 5000, 'cost' => 0, 'unit' => 'UN', 'manage_stock' => false, 'is_active' => true,
        ]);
    }

    private function comandaPronta(float $preco = 5000): Order
    {
        $ordens = app(RestaurantOrderService::class);

        $this->mesa->refresh()->update(['status' => 'available']);

        $order = $ordens->open([
            'venue_id' => $this->venue->id, 'table_id' => $this->mesa->id,
        ], $this->tenant->id, $this->user->id);

        $this->prato->update(['price' => $preco]);
        $ordens->addItem($order, $this->prato->id, 1, null, $this->tenant->id, $this->user->id);

        $order->refresh();
        $order->update(['status' => 'served']);

        return $order->fresh();
    }

    private function fechar(Order $order, array $extra = [])
    {
        return app(RestaurantCheckoutService::class)->checkout($order, array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'document_type' => 'FR',
            'payment_method_id' => $this->metodo()->id,
        ], $extra), $this->tenant->id, $this->user->id);
    }

    private function metodo()
    {
        return \App\Models\Treasury\PaymentMethod::where('tenant_id', $this->tenant->id)
            ->where('is_active', true)->where('code', 'CASH')->firstOrFail();
    }

    public function test_a_conta_do_restaurante_entra_no_turno(): void
    {
        $factura = $this->fechar($this->comandaPronta(5000));

        $movimento = $this->turno->transactions()
            ->where('reference_id', $factura->id)->first();

        $this->assertNotNull($movimento, 'a conta cobrada tem de aparecer no turno');
        $this->assertSame('invoice', $movimento->type);
        $this->assertEqualsWithDelta((float) $factura->total, (float) $movimento->amount, 0.01);
    }

    public function test_o_dinheiro_do_restaurante_conta_no_esperado_da_gaveta(): void
    {
        $factura = $this->fechar($this->comandaPronta(5000));

        $this->turno->refresh();

        $this->assertEqualsWithDelta((float) $factura->total, (float) $this->turno->cash_sales, 0.01,
            'pago em numerário, o valor tem de subir o esperado na gaveta');
        $this->assertSame(1, (int) $this->turno->total_invoices);
    }

    public function test_a_conta_paga_nao_fica_por_receber(): void
    {
        $factura = $this->fechar($this->comandaPronta(5000));

        $this->assertEqualsWithDelta(
            (float) $factura->total,
            (float) SalesInvoice::withoutGlobalScopes()->find($factura->id)->paid_amount,
            0.01,
            'uma conta de restaurante paga no acto nasce paga',
        );
    }
}
