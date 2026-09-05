<?php

namespace Tests\Feature\Restaurant;

use App\Livewire\Restaurant\RestaurantPos;
use App\Models\Invoicing\PosShift;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Venue;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O turno no POS do restaurante — os dois caminhos para abrir uma comanda.
 *
 * PORQUE EXISTE. O serviço recusa abrir comandas sem turno, e faz bem. Mas os
 * dois botões que lhe chamam respondiam de maneiras diferentes à MESMA recusa:
 * a mesa apanhava a excepção e avisava; o balcão deixava-a subir e dava o ecrã
 * de erro do Livewire — a um empregado, a meio de um serviço, com o cliente à
 * frente. Um `try/catch` a menos, num sítio só.
 *
 * A regra está certa no servidor desde sempre. O que faltava era o ecrã
 * responder-lhe da mesma maneira nos dois sítios.
 */
class FaturarNoPosDoRestauranteTest extends TenantTestCase
{
    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');

        \App\Models\Restaurant\RestaurantSettings::forTenant($this->tenant->id)->update([
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

        DiningTable::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $this->venue->id,
            'area_id' => $area->id,
            'code' => 'M01',
            'name' => 'Mesa 01',
            'capacity' => 4,
        ]);
    }

    private function turnoAberto(): PosShift
    {
        return PosShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'shift_number' => 'T-'.uniqid(),
            'status' => 'open',
            'opened_at' => now(),
            'opening_balance' => 0,
        ]);
    }

    /**
     * O botão do ecrã chama um método que tem de existir.
     *
     * A vista tem `wire:click="openCheckout"` e usa `$showCheckout`,
     * `$showShiftRequired` e `$multiPayment`. Nenhum deles está escrito no
     * `RestaurantPos` — vêm todos do `OrderManagement`, de quem ele herda.
     * Isso é fácil de perder de vista: quem partir a herança para transformar
     * isto num componente independente leva a vista atrás sem se dar conta.
     */
    public function test_o_ecra_tem_o_checkout_que_a_vista_usa(): void
    {
        $componente = new \ReflectionClass(RestaurantPos::class);

        $this->assertTrue($componente->hasMethod('openCheckout'),
            'A vista tem um botão "Receber e faturar" com wire:click="openCheckout".');

        foreach (['showCheckout', 'showShiftRequired', 'multiPayment'] as $propriedade) {
            $this->assertTrue($componente->hasProperty($propriedade),
                "A vista do POS usa \${$propriedade} — sem ela o bloco nunca aparece.");
        }
    }

    /**
     * A PROVA: sem turno, o balcão avisa em vez de rebentar.
     *
     * O `chooseTable()` apanhava a recusa do serviço; o `openCounterOrder()`
     * deixava-a subir. Sem turno aberto, carregar em «Balcão» dava o ecrã de
     * erro do Livewire — a um empregado, com o cliente à frente. Os dois
     * mostram agora o mesmo ecrã que diz onde se abre o turno.
     */
    public function test_sem_turno_o_balcao_avisa_em_vez_de_rebentar(): void
    {
        Livewire::actingAs($this->user)->test(RestaurantPos::class)
            ->set('venueId', $this->venue->id)
            ->call('openCounterOrder')
            ->assertHasNoErrors()
            ->assertSet('showShiftRequired', true)
            ->assertSet('showTables', true);

        $this->assertDatabaseCount('restaurant_orders', 0);
    }

    /** E a mesa faz o mesmo — era um aviso que desaparecia e não dizia para onde ir. */
    public function test_sem_turno_a_mesa_manda_abrir_o_turno(): void
    {
        $mesa = DiningTable::where('tenant_id', $this->tenant->id)->firstOrFail();

        Livewire::actingAs($this->user)->test(RestaurantPos::class)
            ->set('venueId', $this->venue->id)
            ->call('chooseTable', $mesa->id)
            ->assertHasNoErrors()
            ->assertSet('showShiftRequired', true);

        $this->assertDatabaseCount('restaurant_orders', 0);
    }

    /** Com turno, abrir uma comanda ao balcão funciona. */
    public function test_com_turno_abre_a_comanda_ao_balcao(): void
    {
        $this->turnoAberto();

        Livewire::actingAs($this->user)->test(RestaurantPos::class)
            ->set('venueId', $this->venue->id)
            ->call('openCounterOrder')
            ->assertHasNoErrors()
            ->assertSet('showTables', false);

        $this->assertDatabaseHas('restaurant_orders', [
            'tenant_id' => $this->tenant->id,
            'channel' => 'counter',
        ]);
    }
}
