<?php

namespace Tests\Feature\Restaurant;

use App\Models\Invoicing\PosShift;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Venue;
use Tests\TenantTestCase;

/**
 * O turno no balcão do restaurante — os caminhos para abrir uma comanda.
 *
 * PORQUE EXISTE. O serviço recusa abrir comandas sem turno, e faz bem. Mas os
 * botões que lhe chamam respondiam de maneiras diferentes à MESMA recusa: a
 * mesa apanhava a excepção e avisava; o balcão deixava-a subir e dava o ecrã de
 * erro — a um empregado, a meio de um serviço, com o cliente à frente.
 *
 * EM REACT A RESPOSTA É UMA SÓ: um **409 com `falta_turno`**, que o ecrã
 * reconhece e transforma na janela que diz onde se abre o turno. Não é um erro
 * de validação como os outros — é uma porta fechada com indicação do caminho, e
 * por isso tem um estado próprio.
 */
class FaturarNoPosDoRestauranteTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/restaurant/sala';

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');
        $this->comPermissoes(
            'restaurant.floor.view', 'restaurant.floor.manage',
            'restaurant.orders.view', 'restaurant.orders.create', 'restaurant.orders.edit',
            'restaurant.checkout.charge',
        );

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

    private function mesa(): DiningTable
    {
        return DiningTable::where('tenant_id', $this->tenant->id)->firstOrFail();
    }

    /**
     * A PROVA: sem turno, o balcão recusa com o sinal que o ecrã sabe ler.
     *
     * Um 422 genérico dava um aviso que se apaga sozinho e não diz para onde
     * ir; o 409 com `falta_turno` abre a janela que leva ao ecrã dos turnos.
     */
    public function test_sem_turno_o_balcao_pede_o_turno_em_vez_de_rebentar(): void
    {
        $this->postJson(self::RAIZ . '/abrir-sem-mesa', [
            'venue_id' => $this->venue->id, 'channel' => 'counter',
        ])
            ->assertStatus(409)
            ->assertJsonPath('falta_turno', true);

        $this->assertDatabaseCount('restaurant_orders', 0);
    }

    /** E a mesa faz o mesmo — era um aviso que desaparecia e não dizia para onde ir. */
    public function test_sem_turno_a_mesa_manda_abrir_o_turno(): void
    {
        $this->postJson(self::RAIZ . '/abrir', ['table_id' => $this->mesa()->id, 'guest_count' => 2])
            ->assertStatus(409)
            ->assertJsonPath('falta_turno', true);

        $this->assertDatabaseCount('restaurant_orders', 0);
    }

    /** E o take-away também: mais vale dizê-lo antes de escrever a morada toda. */
    public function test_sem_turno_a_venda_para_fora_pede_o_turno(): void
    {
        $this->postJson(self::RAIZ . '/abrir-sem-mesa', [
            'venue_id' => $this->venue->id, 'channel' => 'takeaway', 'customer_phone' => '923000000',
        ])
            ->assertStatus(409)
            ->assertJsonPath('falta_turno', true);

        $this->assertDatabaseCount('restaurant_orders', 0);
    }

    /** Com turno, abrir uma comanda ao balcão funciona. */
    public function test_com_turno_abre_a_comanda_ao_balcao(): void
    {
        $this->turnoAberto();

        $this->postJson(self::RAIZ . '/abrir-sem-mesa', [
            'venue_id' => $this->venue->id, 'channel' => 'counter',
        ])->assertCreated()->assertJsonStructure(['order_id']);

        $this->assertDatabaseHas('restaurant_orders', [
            'tenant_id' => $this->tenant->id,
            'channel' => 'counter',
        ]);
    }

    /** E na mesa — que passa a ocupada. */
    public function test_com_turno_a_mesa_abre_e_fica_ocupada(): void
    {
        $this->turnoAberto();

        $mesa = $this->mesa();

        $this->postJson(self::RAIZ . '/abrir', ['table_id' => $mesa->id, 'guest_count' => 3])
            ->assertCreated();

        $this->assertDatabaseHas('restaurant_orders', [
            'tenant_id' => $this->tenant->id, 'table_id' => $mesa->id, 'guest_count' => 3,
        ]);

        $this->assertNotSame('available', $mesa->fresh()->status);
    }

    /**
     * UMA MESA NÃO TEM DUAS COMANDAS.
     *
     * Dois atendimentos na mesma mesa é uma conta partida ao meio sem ninguém
     * ter pedido. O segundo toque devolve a comanda que já lá está.
     */
    public function test_a_mesa_ocupada_devolve_a_comanda_que_ja_tem(): void
    {
        $this->turnoAberto();

        $mesa = $this->mesa();

        $primeira = $this->postJson(self::RAIZ . '/abrir', ['table_id' => $mesa->id, 'guest_count' => 2])
            ->assertCreated()->json('order_id');

        $segunda = $this->postJson(self::RAIZ . '/abrir', ['table_id' => $mesa->id, 'guest_count' => 2])
            ->assertOk()->json('order_id');

        $this->assertSame($primeira, $segunda);
        $this->assertDatabaseCount('restaurant_orders', 1);
    }

    /** A entrega sem morada não abre: é uma comanda que ninguém sabe entregar. */
    public function test_a_entrega_sem_morada_nao_abre(): void
    {
        $this->turnoAberto();

        $this->postJson(self::RAIZ . '/abrir-sem-mesa', [
            'venue_id' => $this->venue->id, 'channel' => 'delivery', 'customer_phone' => '923000000',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('delivery_address');

        $this->assertDatabaseCount('restaurant_orders', 0);
    }

    /** Sem turno não se factura, e diz-se com o mesmo sinal. */
    public function test_sem_turno_nao_se_fecha_a_conta(): void
    {
        $this->turnoAberto();

        $mesa = $this->mesa();
        $comanda = $this->postJson(self::RAIZ . '/abrir', ['table_id' => $mesa->id, 'guest_count' => 2])
            ->assertCreated()->json('order_id');

        // O turno fecha-se depois de a comanda estar aberta.
        PosShift::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)
            ->update(['status' => 'closed', 'closed_at' => now()]);

        $this->postJson("/api/v1/invoicing/react/restaurant/comandas/{$comanda}/fechar", [
            'document_type' => 'FR',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'item_ids' => [1],
        ])
            ->assertStatus(409)
            ->assertJsonPath('falta_turno', true);
    }
}
