<?php

namespace Tests\Feature\Restaurant;

use App\Livewire\Restaurant\Reports;
use App\Livewire\Restaurant\RestaurantPos;
use App\Models\Invoicing\PosShift;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Services\Restaurant\RestaurantOrderService;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * A venda para fora: take-away e entrega.
 *
 * PORQUE EXISTE. A coluna `channel` aceitava os quatro canais desde o
 * princípio — e o produto só escrevia dois. Um restaurante que vendesse para
 * fora não tinha por onde registar essas vendas, e elas ficavam de fora dos
 * relatórios por canal. A base dizia que a funcionalidade existia; não existia.
 *
 * O que estes ensaios prendem é o que uma venda para fora tem de diferente de
 * uma venda de mesa: vai para longe do balcão, e por isso precisa de saber
 * para quem é e para onde.
 */
class VendaParaForaTest extends TenantTestCase
{
    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');

        RestaurantSettings::forTenant($this->tenant->id)->update([
            'default_warehouse_id' => $this->armazem->id,
            'require_open_shift' => true,
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

        DiningTable::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $this->venue->id,
            'area_id' => $area->id,
            'code' => 'M01',
            'name' => 'Mesa 01',
            'capacity' => 4,
        ]);
    }

    private function servico(): RestaurantOrderService
    {
        return app(RestaurantOrderService::class);
    }

    /** @test */
    public function abre_um_take_away_com_o_canal_certo(): void
    {
        $order = $this->servico()->open([
            'venue_id' => $this->venue->id,
            'channel' => 'takeaway',
            'customer_name' => 'Dona Ana',
            'customer_phone' => '923000111',
        ], $this->tenant->id, $this->user->id);

        $this->assertSame('takeaway', $order->channel);
        $this->assertSame('Dona Ana', $order->customer_name);
        $this->assertNull($order->table_id, 'uma venda para fora não ocupa mesa');
        $this->assertTrue($order->paraFora());
    }

    /**
     * A PROVA QUE IMPORTA: uma entrega sem morada é recusada.
     *
     * Não vale recusar só no ecrã — a comanda também entra pela API e pelo
     * PWA. Uma entrega sem destino descobre-se quando o estafeta está à porta
     * a perguntar para onde vai.
     *
     * @test
     */
    public function uma_entrega_sem_morada_e_recusada(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('morada');

        $this->servico()->open([
            'venue_id' => $this->venue->id,
            'channel' => 'delivery',
            'customer_phone' => '923000111',
        ], $this->tenant->id, $this->user->id);
    }

    /** @test */
    public function uma_entrega_sem_telefone_e_recusada(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('telefone');

        $this->servico()->open([
            'venue_id' => $this->venue->id,
            'channel' => 'delivery',
            'delivery_address' => 'Rua da Missão, ao lado da farmácia',
        ], $this->tenant->id, $this->user->id);
    }

    /** @test */
    public function um_take_away_sem_telefone_e_recusado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('telefone');

        $this->servico()->open([
            'venue_id' => $this->venue->id,
            'channel' => 'takeaway',
        ], $this->tenant->id, $this->user->id);
    }

    /** @test */
    public function um_canal_inventado_e_recusado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servico()->open([
            'venue_id' => $this->venue->id,
            'channel' => 'drone',
        ], $this->tenant->id, $this->user->id);
    }

    /**
     * A TAXA DE ENTREGA ENTRA NO TOTAL.
     *
     * Deixá-la de fora fazia a comanda mostrar um valor e o cliente pagar
     * outro — e a diferença só aparecia ao fechar a caixa.
     *
     * @test
     */
    public function a_taxa_de_entrega_entra_no_total(): void
    {
        $order = $this->servico()->open([
            'venue_id' => $this->venue->id,
            'channel' => 'delivery',
            'customer_phone' => '923000111',
            'delivery_address' => 'Rua da Missão, ao lado da farmácia',
            'delivery_fee' => 1500,
        ], $this->tenant->id, $this->user->id);

        $this->assertSame('1500.00', (string) $order->delivery_fee);
        $this->assertSame('1500.00', (string) $order->fresh()->grand_total,
            'sem pratos, o total da comanda é só a taxa de entrega');
    }

    /** A taxa só existe na entrega: um take-away não a leva à boleia. */
    public function test_o_take_away_nao_leva_taxa_de_entrega(): void
    {
        $order = $this->servico()->open([
            'venue_id' => $this->venue->id,
            'channel' => 'takeaway',
            'customer_phone' => '923000111',
            'delivery_fee' => 2000,
        ], $this->tenant->id, $this->user->id);

        $this->assertSame('0.00', (string) $order->delivery_fee);
    }

    /** @test */
    public function despachar_marca_a_hora_a_que_saiu(): void
    {
        $order = $this->servico()->open([
            'venue_id' => $this->venue->id,
            'channel' => 'delivery',
            'customer_phone' => '923000111',
            'delivery_address' => 'Rua da Missão',
        ], $this->tenant->id, $this->user->id);

        $this->assertNull($order->dispatched_at);

        $depois = $this->servico()->despachar($order, $this->tenant->id, $this->user->id);

        $this->assertNotNull($depois->dispatched_at);
        $this->assertSame('served', $depois->status);

        // Despachar outra vez não muda a hora: a primeira é a verdadeira.
        $hora = $depois->dispatched_at;
        $this->assertEquals(
            $hora,
            $this->servico()->despachar($depois, $this->tenant->id, $this->user->id)->dispatched_at
        );
    }

    /** Uma comanda de mesa não se despacha — não sai para lado nenhum. */
    public function test_uma_comanda_de_mesa_nao_se_despacha(): void
    {
        $mesa = DiningTable::where('tenant_id', $this->tenant->id)->firstOrFail();

        $order = $this->servico()->open([
            'venue_id' => $this->venue->id,
            'table_id' => $mesa->id,
        ], $this->tenant->id, $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->servico()->despachar($order, $this->tenant->id, $this->user->id);
    }

    /** O ecrã do POS abre a comanda pelos dois canais novos. */
    public function test_o_ecra_abre_um_take_away(): void
    {
        Livewire::actingAs($this->user)->test(RestaurantPos::class)
            ->set('venueId', $this->venue->id)
            ->call('prepararVendaParaFora', 'takeaway')
            ->assertSet('showParaFora', true)
            ->set('paraForaNome', 'Dona Ana')
            ->set('paraForaTelefone', '923000111')
            ->call('abrirVendaParaFora')
            ->assertHasNoErrors()
            ->assertSet('showParaFora', false);

        $this->assertDatabaseHas('restaurant_orders', [
            'tenant_id' => $this->tenant->id,
            'channel' => 'takeaway',
            'customer_name' => 'Dona Ana',
        ]);
    }

    /** E recusa a entrega incompleta antes de chegar ao servidor. */
    public function test_o_ecra_exige_morada_na_entrega(): void
    {
        Livewire::actingAs($this->user)->test(RestaurantPos::class)
            ->set('venueId', $this->venue->id)
            ->call('prepararVendaParaFora', 'delivery')
            ->set('paraForaTelefone', '923000111')
            ->call('abrirVendaParaFora')
            ->assertHasErrors(['paraForaMorada' => 'required']);

        $this->assertDatabaseCount('restaurant_orders', 0);
    }

    /** Os relatórios separam o que entrou por cada porta. */
    public function test_o_relatorio_separa_as_vendas_por_canal(): void
    {
        foreach ([['takeaway', '923000111', null], ['delivery', '923000222', 'Rua da Missão']] as [$canal, $tel, $morada]) {
            $order = $this->servico()->open([
                'venue_id' => $this->venue->id,
                'channel' => $canal,
                'customer_phone' => $tel,
                'delivery_address' => $morada,
                'delivery_fee' => $canal === 'delivery' ? 1000 : 0,
            ], $this->tenant->id, $this->user->id);

            $order->update(['status' => 'billed', 'grand_total' => 5000]);
        }

        $canais = collect(Livewire::actingAs($this->user)->test(Reports::class)->viewData('porCanal'))
            ->pluck('channel')->all();

        $this->assertContains('takeaway', $canais);
        $this->assertContains('delivery', $canais);
    }

    /** Uma lista só de canais: divergirem entre ecrãs é um canal que some. */
    public function test_os_canais_sao_uma_lista_so(): void
    {
        $naBase = \DB::selectOne("SHOW COLUMNS FROM restaurant_orders LIKE 'channel'")->Type;

        foreach (array_keys(Order::CANAIS) as $canal) {
            $this->assertStringContainsString("'{$canal}'", $naBase,
                "O canal {$canal} está na lista do produto e não no enum da base.");
        }
    }
}
