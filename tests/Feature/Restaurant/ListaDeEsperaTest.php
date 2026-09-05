<?php

namespace Tests\Feature\Restaurant;

use App\Livewire\Restaurant\RestaurantPos;
use App\Models\Invoicing\PosShift;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Models\Restaurant\Waitlist;
use App\Services\Restaurant\ListaDeEspera;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * A lista de espera: quem chega sem reserva numa casa cheia.
 *
 * O que estes ensaios prendem não é a fila em si — é a fila NÃO ser uma porta
 * ao lado das regras. Sentar alguém abre a comanda pelo caminho de sempre, e
 * portanto o turno obrigatório e a mesa ocupada valem aqui como no POS. E o
 * «foi-se embora» fica registado: é a medida de quantos clientes a casa perde
 * por falta de mesa.
 */
class ListaDeEsperaTest extends TenantTestCase
{
    private Venue $venue;

    private DiningTable $mesa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');

        RestaurantSettings::forTenant($this->tenant->id)->update([
            'default_warehouse_id' => $this->armazem->id,
            'require_open_shift' => true,
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
    }

    private function fila(): ListaDeEspera
    {
        return app(ListaDeEspera::class);
    }

    private function turnoAberto(): void
    {
        PosShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'shift_number' => 'REST-'.strtoupper(substr(uniqid(), -8)),
            'opened_at' => now(),
            'opening_balance' => 0,
            'status' => 'open',
        ]);
    }

    /** @test */
    public function quem_chega_entra_na_fila_por_ordem(): void
    {
        $this->fila()->chegar(['venue_id' => $this->venue->id, 'guest_name' => 'Dona Ana', 'guest_count' => 4], $this->tenant->id, $this->user->id);
        $this->travel(1)->minutes();
        $this->fila()->chegar(['venue_id' => $this->venue->id, 'guest_name' => 'Sr. Bento'], $this->tenant->id, $this->user->id);

        $nomes = $this->fila()->fila($this->tenant->id, $this->venue->id)->pluck('guest_name')->all();

        $this->assertSame(['Dona Ana', 'Sr. Bento'], $nomes, 'a ordem de chegada manda');
    }

    /** @test */
    public function sem_nome_nao_ha_fila(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->fila()->chegar(['venue_id' => $this->venue->id, 'guest_name' => '  '], $this->tenant->id, $this->user->id);
    }

    /**
     * A PROVA CENTRAL: sentar abre a comanda pelo caminho de sempre.
     *
     * @test
     */
    public function sentar_abre_a_comanda_na_mesa(): void
    {
        $this->turnoAberto();

        $entrada = $this->fila()->chegar([
            'venue_id' => $this->venue->id, 'guest_name' => 'Dona Ana', 'guest_count' => 3,
        ], $this->tenant->id, $this->user->id);

        $order = $this->fila()->sentar($entrada, $this->mesa->id, $this->tenant->id, $this->user->id);

        $entrada->refresh();

        $this->assertSame('seated', $entrada->status);
        $this->assertSame($order->id, $entrada->order_id);
        $this->assertSame(3, (int) $order->guest_count, 'a comanda herda quantos são');
        $this->assertSame('occupied', $this->mesa->fresh()->status);
    }

    /**
     * E POR ISSO as regras valem: sem turno, ninguém senta — e a entrada
     * FICA na fila, porque sentar meio cliente era perder o registo dele.
     *
     * @test
     */
    public function sem_turno_nao_se_senta_e_a_entrada_fica_na_fila(): void
    {
        $entrada = $this->fila()->chegar([
            'venue_id' => $this->venue->id, 'guest_name' => 'Dona Ana',
        ], $this->tenant->id, $this->user->id);

        try {
            $this->fila()->sentar($entrada, $this->mesa->id, $this->tenant->id, $this->user->id);
            $this->fail('sem turno, sentar tinha de ser recusado');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('turno', $e->getMessage());
        }

        $this->assertSame('waiting', $entrada->fresh()->status);
        $this->assertDatabaseCount('restaurant_orders', 0);
    }

    /** A mesa ocupada também recusa — e a entrada fica na fila. */
    public function test_mesa_ocupada_recusa_e_a_entrada_fica_na_fila(): void
    {
        $this->turnoAberto();
        $this->mesa->update(['status' => 'blocked']);

        $entrada = $this->fila()->chegar([
            'venue_id' => $this->venue->id, 'guest_name' => 'Dona Ana',
        ], $this->tenant->id, $this->user->id);

        try {
            $this->fila()->sentar($entrada, $this->mesa->id, $this->tenant->id, $this->user->id);
            $this->fail('mesa bloqueada tinha de recusar');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame('waiting', $entrada->fresh()->status);
    }

    /** @test */
    public function quem_desiste_fica_registado_como_perdido(): void
    {
        $entrada = $this->fila()->chegar([
            'venue_id' => $this->venue->id, 'guest_name' => 'Sr. Bento',
        ], $this->tenant->id, $this->user->id);

        $this->fila()->desistir($entrada, $this->tenant->id);

        $entrada->refresh();

        $this->assertSame('left', $entrada->status);
        $this->assertNotNull($entrada->resolved_at, 'sem a hora, não se mede quanto tempo aguentou');

        // E saiu da fila.
        $this->assertCount(0, $this->fila()->fila($this->tenant->id, $this->venue->id));
    }

    /** Sentar duas vezes a mesma entrada não abre duas comandas. */
    public function test_uma_entrada_ja_resolvida_nao_volta_a_sentar(): void
    {
        $this->turnoAberto();

        $entrada = $this->fila()->chegar([
            'venue_id' => $this->venue->id, 'guest_name' => 'Dona Ana',
        ], $this->tenant->id, $this->user->id);

        $this->fila()->sentar($entrada, $this->mesa->id, $this->tenant->id, $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->fila()->sentar($entrada->fresh(), $this->mesa->id, $this->tenant->id, $this->user->id);
    }

    /** O ecrã do POS: entra na fila, senta pelo toque na mesa. */
    public function test_o_ecra_poe_na_fila_e_senta_pelo_toque_na_mesa(): void
    {
        $this->turnoAberto();

        $componente = Livewire::actingAs($this->user)->test(RestaurantPos::class)
            ->set('venueId', $this->venue->id)
            ->set('esperaNome', 'Dona Ana')
            ->set('esperaPessoas', 2)
            ->call('chegouParaEspera')
            ->assertHasNoErrors();

        $entrada = Waitlist::where('tenant_id', $this->tenant->id)->firstOrFail();

        // Sentar: escolhe-se quem, e o toque na mesa passa a ser para isso.
        $componente
            ->call('prepararSentar', $entrada->id)
            ->assertSet('esperaParaSentar', $entrada->id)
            ->call('chooseTable', $this->mesa->id)
            ->assertHasNoErrors()
            ->assertSet('esperaParaSentar', null)
            ->assertSet('showTables', false);

        $this->assertSame('seated', $entrada->fresh()->status);
        $this->assertDatabaseHas('restaurant_orders', [
            'tenant_id' => $this->tenant->id,
            'table_id' => $this->mesa->id,
        ]);
    }
}
