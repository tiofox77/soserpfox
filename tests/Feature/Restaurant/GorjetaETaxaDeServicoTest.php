<?php

namespace Tests\Feature\Restaurant;

use App\Models\Invoicing\PosShift;
use App\Models\Product;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Models\Treasury\Transaction;
use App\Services\Restaurant\RestaurantCheckoutService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\TaxaDeServico;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * A gorjeta e a taxa de serviço.
 *
 * PORQUE EXISTEM. Não havia nem uma nem outra. Quem quisesse registar gorjeta
 * metia-a como artigo — e nesse momento passava a ser venda da casa: inflava
 * as vendas do dia, era tributada, e ninguém percebia porquê.
 *
 * SÃO DUAS COISAS DIFERENTES, e a diferença é o que estes ensaios prendem:
 *
 *   · a TAXA DE SERVIÇO é receita da casa → vai à factura, é tributada;
 *   · a GORJETA é do pessoal → NÃO vai à factura, mas passa pela caixa.
 *
 * Trocá-las é um erro fiscal num sentido e uma caixa que não fecha no outro.
 */
class GorjetaETaxaDeServicoTest extends TenantTestCase
{
    private Venue $venue;

    private DiningTable $mesa;

    private Product $prato;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');
        $this->comModulo('invoicing');

        RestaurantSettings::forTenant($this->tenant->id)->update([
            'default_warehouse_id' => $this->armazem->id,
            'require_open_shift' => true,
            'use_kitchen_workflow' => false,
        ]);

        PosShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'shift_number' => 'REST-'.strtoupper(substr(uniqid(), -8)),
            'opened_at' => now(),
            'opening_balance' => 0,
            'status' => 'open',
        ]);

        $this->venue = Venue::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRINCIPAL',
            'name' => 'Restaurante Principal',
            'warehouse_id' => $this->armazem->id,
        ]);

        $area = Area::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $this->venue->id,
            'name' => 'Sala',
        ]);

        $this->mesa = DiningTable::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $this->venue->id,
            'area_id' => $area->id,
            'code' => 'M01',
            'name' => 'Mesa 01',
            'capacity' => 4,
        ]);

        $this->prato = Product::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'produto',
            'name' => 'Muamba de galinha',
            'price' => 5000,
            'cost' => 0,
            'unit' => 'UN',
            'manage_stock' => false,
            'is_active' => true,
        ]);
    }

    private function comandaPronta(float $preco = 5000): Order
    {
        $ordens = app(RestaurantOrderService::class);

        // O fecho anterior deixa a mesa em limpeza; para o ensaio seguinte,
        // ela volta ao serviço — como faria o empregado. O refresh() é
        // obrigatório: o fecho mudou a mesa POR BAIXO desta instância, e um
        // update() com o valor que ela julga já ter não escreve nada.
        $this->mesa->refresh()->update(['status' => 'available']);

        $order = $ordens->open([
            'venue_id' => $this->venue->id,
            'table_id' => $this->mesa->id,
        ], $this->tenant->id, $this->user->id);

        // O preço vem do artigo: o addItem não aceita preço por fora.
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
            ->where('is_active', true)->orderBy('sort_order')->firstOrFail();
    }

    /* ── A TAXA DE SERVIÇO ──────────────────────────────────────────── */

    /** Sem taxa configurada, nada muda — é o caso de quase todas as casas. */
    public function test_sem_taxa_configurada_a_factura_so_tem_os_pratos(): void
    {
        $factura = $this->fechar($this->comandaPronta());

        $this->assertCount(1, $factura->items);
        $this->assertSame('Muamba de galinha', $factura->items[0]->product_name);
    }

    /** @test */
    public function a_taxa_de_servico_entra_na_factura_como_linha(): void
    {
        RestaurantSettings::forTenant($this->tenant->id)->update(['service_charge_percent' => 10]);

        $factura = $this->fechar($this->comandaPronta(5000));

        $this->assertCount(2, $factura->items, 'os pratos mais a taxa');

        $taxa = collect($factura->items)->firstWhere('product_name', 'like', '%Taxa de serviço%')
            ?? collect($factura->items)->first(fn ($i) => str_contains($i->product_name, 'Taxa de serviço'));

        $this->assertNotNull($taxa, 'a taxa de serviço tem de aparecer na factura');
        $this->assertEqualsWithDelta(500, (float) $taxa->unit_price, 0.01, '10% de 5000');
    }

    /**
     * A taxa é RECEITA e por isso é tributada como o resto.
     *
     * Uma taxa de serviço fora da factura era receita não declarada.
     */
    public function test_a_taxa_de_servico_e_tributada(): void
    {
        RestaurantSettings::forTenant($this->tenant->id)->update(['service_charge_percent' => 10]);

        $factura = $this->fechar($this->comandaPronta(5000));

        $taxa = collect($factura->items)->first(fn ($i) => str_contains($i->product_name, 'Taxa de serviço'));

        $this->assertGreaterThan(0, (float) $taxa->tax_amount,
            'a taxa de serviço é venda da casa: leva imposto como qualquer linha');
    }

    /** O artigo da taxa é criado uma vez e reaproveitado. */
    public function test_o_artigo_da_taxa_nao_se_multiplica(): void
    {
        RestaurantSettings::forTenant($this->tenant->id)->update(['service_charge_percent' => 10]);

        $this->fechar($this->comandaPronta());
        $this->fechar($this->comandaPronta());

        $this->assertSame(1, Product::where('tenant_id', $this->tenant->id)
            ->where('code', TaxaDeServico::CODIGO)->count());
    }

    /** A taxa de entrega tem artigo PRÓPRIO: são duas receitas diferentes. */
    public function test_a_entrega_e_a_taxa_de_servico_sao_artigos_diferentes(): void
    {
        $servico = app(TaxaDeServico::class);

        $this->assertNotSame(
            $servico->artigo($this->tenant->id)->id,
            $servico->artigoDeEntrega($this->tenant->id)->id,
            'somadas no mesmo artigo, ninguém consegue dizer quanto rendeu cada uma'
        );
    }

    /* ── A GORJETA ──────────────────────────────────────────────────── */

    /**
     * A PROVA CENTRAL: a gorjeta NÃO vai à factura.
     *
     * Pô-la lá era declarar como receita da empresa dinheiro que é do pessoal.
     *
     * @test
     */
    public function a_gorjeta_nao_entra_na_factura(): void
    {
        $order = $this->comandaPronta(5000);

        $factura = $this->fechar($order, ['tip_amount' => 1000]);

        $this->assertCount(1, $factura->items, 'a gorjeta não é uma linha');
        $this->assertEqualsWithDelta(
            5000,
            (float) $factura->total - (float) $factura->tax_amount,
            0.01,
            'a gorjeta não pode inflar o valor facturado'
        );
    }

    /**
     * E A OUTRA METADE: passa pela caixa.
     *
     * Sem isto, o dinheiro contado ao fecho do turno nunca batia com o que o
     * sistema dizia que devia lá estar, e o operador ficava com uma diferença
     * que não sabia explicar.
     *
     * @test
     */
    public function a_gorjeta_entra_na_caixa(): void
    {
        $this->fechar($this->comandaPronta(5000), ['tip_amount' => 1000]);

        $gorjeta = Transaction::where('tenant_id', $this->tenant->id)
            ->where('category', 'tip')->first();

        $this->assertNotNull($gorjeta, 'a gorjeta tem de aparecer na tesouraria');
        $this->assertEqualsWithDelta(1000, (float) $gorjeta->amount, 0.01);
        $this->assertNull($gorjeta->invoice_id,
            'ligada a uma factura, o recebido passava o facturado e a factura ficava paga a mais');
    }

    /** E fica na comanda, para o relatório do turno. */
    public function test_a_gorjeta_fica_registada_na_comanda(): void
    {
        $order = $this->comandaPronta(5000);

        $this->fechar($order, ['tip_amount' => 750]);

        $this->assertEqualsWithDelta(750, (float) $order->fresh()->tip_amount, 0.01);
    }

    /** Sem gorjeta não se inventa movimento nenhum. */
    public function test_sem_gorjeta_nao_ha_movimento_de_gorjeta(): void
    {
        $this->fechar($this->comandaPronta());

        $this->assertSame(0, Transaction::where('tenant_id', $this->tenant->id)
            ->where('category', 'tip')->count());
    }

    /** Desligadas nas definições, as gorjetas são ignoradas. */
    public function test_com_as_gorjetas_desligadas_nao_se_regista_nada(): void
    {
        RestaurantSettings::forTenant($this->tenant->id)->update(['tips_enabled' => false]);

        $this->fechar($this->comandaPronta(), ['tip_amount' => 1000]);

        $this->assertSame(0, Transaction::where('tenant_id', $this->tenant->id)
            ->where('category', 'tip')->count());
    }

    /* ── A TAXA DE ENTREGA ──────────────────────────────────────────── */

    /**
     * A taxa de entrega ia no total da comanda e NÃO na factura: o cliente
     * pagava uma coisa e o documento dizia outra.
     *
     * @test
     */
    public function a_taxa_de_entrega_entra_na_factura(): void
    {
        $ordens = app(RestaurantOrderService::class);

        $order = $ordens->open([
            'venue_id' => $this->venue->id,
            'channel' => 'delivery',
            'customer_phone' => '923000111',
            'delivery_address' => 'Rua da Missão',
            'delivery_fee' => 1500,
        ], $this->tenant->id, $this->user->id);

        $this->prato->update(['price' => 5000]);
        $ordens->addItem($order, $this->prato->id, 1, null, $this->tenant->id, $this->user->id);

        $order->refresh();
        $order->update(['status' => 'served']);

        $factura = $this->fechar($order->fresh());

        $entrega = collect($factura->items)->first(fn ($i) => $i->product_name === TaxaDeServico::NOME_ENTREGA);

        $this->assertNotNull($entrega, 'o transporte tem de estar no documento que o cliente recebe');
        $this->assertEqualsWithDelta(1500, (float) $entrega->unit_price, 0.01);
    }
}
