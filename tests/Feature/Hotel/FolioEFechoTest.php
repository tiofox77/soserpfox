<?php

namespace Tests\Feature\Hotel;

use App\Models\Client;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\ReservationItem;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Treasury\PaymentMethod;
use Tests\TenantTestCase;

/**
 * O FOLIO E O CHECK-OUT — o que a migração encontrou partido.
 *
 * A dedução dos adiantamentos, as linhas sem negativos e o check-out repetido
 * estão em `HotelReservationTest`, que é onde sempre estiveram. Aqui ficam os
 * defeitos que este ecrã escondia: a procura que rebentava, a fidelidade dada
 * a uma ficha vazia, e o quarto que vagava sem a governanta saber.
 */
class FolioEFechoTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/hotel/fecho';

    private PaymentMethod $metodo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');

        $this->metodo = PaymentMethod::where('tenant_id', $this->tenant->id)->where('is_active', true)->first()
            ?? PaymentMethod::create([
                'tenant_id' => $this->tenant->id, 'name' => 'Dinheiro', 'code' => 'CASH', 'is_active' => true,
            ]);
    }

    /**
     * A PROCURA NÃO REBENTA.
     *
     * O filtro procurava por `rooms.room_number` — uma coluna que não existe,
     * a coluna é `number` — e por `guest.name`, a ficha antiga que está vazia.
     * Escrever no campo de procura dava um erro de SQL na cara do
     * recepcionista.
     */
    public function test_procurar_por_quarto_e_por_nome_funciona(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $cliente = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Teresa Cabral']);
        $quarto = $this->quarto('507');

        $this->estada($quarto, $cliente);

        // Pelo número do quarto — era isto que rebentava.
        $this->getJson(self::API . '/por-sair?procura=507')->assertOk()
            ->assertJsonPath('total', 1);

        // E pelo nome, que nunca encontrava nada.
        $this->getJson(self::API . '/por-sair?procura=Teresa')->assertOk()
            ->assertJsonPath('total', 1);

        $this->getJson(self::API . '/por-sair?procura=ninguem')->assertOk()
            ->assertJsonPath('total', 0);
    }

    /** Quem já devia ter saído vem no grupo dos atrasados. */
    public function test_quem_ja_devia_ter_saido_fica_a_parte(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $this->estada($this->quarto('101'), null, today()->subDays(3), today()->subDay());
        $this->estada($this->quarto('102'), null, today()->subDay(), today());
        $this->estada($this->quarto('103'), null, today(), today()->addDays(3));

        $r = $this->getJson(self::API . '/por-sair')->assertOk();

        $this->assertCount(1, $r->json('atrasados'));
        $this->assertCount(1, $r->json('hoje'));
        $this->assertCount(1, $r->json('depois'));
    }

    /* ─── O folio ─────────────────────────────────────────────────────── */

    /** Lançar um consumo faz subir o total da reserva. */
    public function test_um_consumo_lancado_sobe_o_total_da_reserva(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.edit');

        $estada = $this->estada($this->quarto('201'));
        $antes = (float) $estada->total;

        $this->postJson(self::API . "/{$estada->id}/consumos", [
            'category' => 'minibar', 'description' => 'Duas águas', 'quantity' => 2, 'unit_price' => 500,
        ])->assertCreated()->assertJsonPath('conta.consumos', 1000);

        $this->assertGreaterThan($antes, (float) $estada->fresh()->total,
            'o saldo em dívida tem de ver o consumo');
    }

    /** E apagá-lo volta a baixar. */
    public function test_apagar_um_consumo_baixa_o_total(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.edit');

        $estada = $this->estada($this->quarto('202'));

        $r = $this->postJson(self::API . "/{$estada->id}/consumos", [
            'category' => 'laundry', 'description' => 'Lavandaria', 'quantity' => 1, 'unit_price' => 3000,
        ])->assertCreated();

        $consumo = $r->json('consumos.0.id');

        $this->deleteJson(self::API . "/{$estada->id}/consumos/{$consumo}")
            ->assertOk()->assertJsonPath('conta.consumos', 0);

        $this->assertSame(0, ReservationItem::where('reservation_id', $estada->id)->count());
    }

    /**
     * O FOLIO FECHA COM A ESTADA.
     *
     * Depois do check-out continuava a aceitar consumos: o total subia, o
     * pagamento caía de «Pago» para «Parcial», e ficava um saldo de um hóspede
     * que já tinha ido embora — sem relação nenhuma com a factura emitida.
     */
    public function test_o_folio_de_uma_reserva_cancelada_esta_fechado(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.edit');

        $estada = $this->estada($this->quarto('203'));
        $estada->update(['status' => Reservation::STATUS_CANCELLED]);

        $this->getJson(self::API . "/{$estada->id}")->assertOk()
            ->assertJsonPath('aberto', false);

        $this->postJson(self::API . "/{$estada->id}/consumos", [
            'category' => 'minibar', 'description' => 'Cerveja', 'quantity' => 1, 'unit_price' => 1000,
        ])->assertStatus(422);
    }

    /* ─── O fecho ─────────────────────────────────────────────────────── */

    /**
     * FECHAR A ESTADA DEIXA O QUARTO SUJO E EM LIMPEZA — os dois campos.
     *
     * O `checkOut()` do modelo põe o `status` em limpeza e deixa o
     * `housekeeping_status` como estava: o quadro da governanta não via o
     * quarto que acabou de vagar.
     */
    public function test_fechar_manda_o_quarto_para_a_limpeza(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.checkout.manage');

        $quarto = $this->quarto('301', ['status' => 'occupied', 'housekeeping_status' => 'clean']);
        $estada = $this->estada($quarto);

        $this->postJson(self::API . "/{$estada->id}/fechar", [
            'pagamento' => 0, 'meio' => $this->metodo->id, 'facturar' => false,
        ])->assertOk();

        $fresco = $quarto->fresh();

        $this->assertSame('cleaning', $fresco->status);
        $this->assertSame('dirty', $fresco->housekeeping_status, 'a governanta tem de ver o quarto que vagou');
        $this->assertSame('checked_out', $estada->fresh()->status);
    }

    /**
     * A FIDELIDADE CONTA O QUE SE GASTOU, e é do CLIENTE.
     *
     * O ecrã de sempre dava-a ao `guest` — a ficha antiga, que está vazia —
     * pelo que uma estada de um cliente nunca contava para nada. E contava uma
     * VISITA nova a cada pagamento: quem pagasse sinal e depois a conta ficava
     * com três estadas por uma noite passada cá.
     */
    public function test_a_fidelidade_conta_o_gasto_e_nao_uma_visita_nova(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.edit', 'hotel.checkout.manage');

        $cliente = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Nzuzi Miguel', 'is_active' => true]);
        $estada = $this->estada($this->quarto('302'), $cliente);

        // A visita conta-se à entrada, uma vez.
        $cliente->incrementStays();
        $this->assertSame(1, $cliente->fresh()->loyalty_data['total_visits']);

        $this->postJson(self::API . "/{$estada->id}/fechar", [
            'pagamento' => 10000, 'meio' => $this->metodo->id, 'facturar' => false,
        ])->assertOk();

        $depois = $cliente->fresh()->loyalty_data;

        $this->assertSame(1, $depois['total_visits'], 'fechar a conta não é uma estada nova');
        $this->assertGreaterThan(0, $depois['total_spent'], 'o que gastou conta');
    }

    /**
     * FACTURAR SEM HÓSPEDE É RECUSADO ANTES DE MEXER EM NADA.
     *
     * A emissão rebentava DENTRO da transacção, e o rollback desfazia o
     * check-out inteiro: a reserva não passava a fechada, o quarto não ia para
     * limpeza e os consumos lançados desapareciam. O operador via um erro e
     * perdia o trabalho todo.
     */
    public function test_facturar_sem_hospede_nao_desfaz_o_check_out(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.checkout.manage');

        $quarto = $this->quarto('303', ['status' => 'occupied']);

        $estada = Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -6),
            'client_id' => null,
            'room_type_id' => $quarto->room_type_id,
            'room_id' => $quarto->id,
            'check_in_date' => today()->subDay(),
            'check_out_date' => today(),
            'nights' => 1, 'room_rate' => 20000,
            'status' => Reservation::STATUS_CHECKED_IN, 'source' => 'direct',
        ]);

        $this->postJson(self::API . "/{$estada->id}/fechar", [
            'pagamento' => 0, 'meio' => $this->metodo->id, 'facturar' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('pagamento');

        // Nada foi tocado: a estada continua aberta e o quarto ocupado.
        $this->assertSame('checked_in', $estada->fresh()->status);
        $this->assertSame('occupied', $quarto->fresh()->status);

        // E sem facturar, fecha.
        $this->postJson(self::API . "/{$estada->id}/fechar", [
            'pagamento' => 0, 'meio' => $this->metodo->id, 'facturar' => false,
        ])->assertOk();

        $this->assertSame('checked_out', $estada->fresh()->status);
    }

    /** Ver a conta não é fechá-la. */
    public function test_quem_so_ve_nao_fecha(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $estada = $this->estada($this->quarto('304'));

        $this->getJson(self::API . '/por-sair')->assertOk();
        $this->getJson(self::API . "/{$estada->id}")->assertOk();
        $this->getJson(self::API . '/opcoes')->assertOk()
            ->assertJsonPath('permissoes.pode_fechar', false)
            ->assertJsonPath('permissoes.pode_lancar', false);

        $this->postJson(self::API . "/{$estada->id}/fechar", ['pagamento' => 0])->assertForbidden();
        $this->postJson(self::API . "/{$estada->id}/consumos", [
            'category' => 'minibar', 'description' => 'Cerveja', 'quantity' => 1, 'unit_price' => 1000,
        ])->assertForbidden();

        $this->assertSame('checked_in', $estada->fresh()->status);
    }

    /** Uma estada de outra empresa não se vê nem se fecha. */
    public function test_nao_se_fecha_a_estada_de_outra_empresa(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.checkout.manage');

        $outra = \App\Models\Tenant::create([
            'name' => 'Hotel do Lado', 'email' => uniqid() . '@exemplo.ao',
            'nif' => (string) random_int(500000000, 599999999), 'is_active' => true,
        ]);

        $tipoAlheio = RoomType::create([
            'tenant_id' => $outra->id, 'name' => 'Duplo', 'code' => 'D-' . substr(uniqid(), -4),
            'base_price' => 20000, 'capacity' => 2,
        ]);

        $alheia = Reservation::create([
            'tenant_id' => $outra->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -6),
            'client_id' => Client::create(['tenant_id' => $outra->id, 'name' => 'Alheio'])->id,
            'room_type_id' => $tipoAlheio->id,
            'check_in_date' => today()->subDay(),
            'check_out_date' => today(),
            'nights' => 1, 'room_rate' => 20000,
            'status' => Reservation::STATUS_CHECKED_IN, 'source' => 'direct',
        ]);

        $this->getJson(self::API . "/{$alheia->id}")->assertNotFound();
        $this->postJson(self::API . "/{$alheia->id}/fechar", [
            'pagamento' => 0, 'facturar' => false,
        ])->assertNotFound();

        $this->assertSame('checked_in', $alheia->fresh()->status);
    }

    /* ─── Fixtures ────────────────────────────────────────────────────── */

    private function tipo(): RoomType
    {
        return RoomType::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Duplo',
            'code' => 'D-' . substr(uniqid(), -4), 'base_price' => 20000,
            'capacity' => 2, 'is_active' => true,
        ]);
    }

    private function quarto(string $numero, array $campos = []): Room
    {
        return Room::create(array_merge([
            'tenant_id' => $this->tenant->id, 'room_type_id' => $this->tipo()->id,
            'number' => $numero, 'floor' => '1',
            'status' => 'occupied', 'housekeeping_status' => 'clean', 'is_active' => true,
        ], $campos));
    }

    private function estada(Room $quarto, ?Client $cliente = null, $de = null, $ate = null): Reservation
    {
        return Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -6),
            'client_id' => ($cliente ?? $this->cliente)->id,
            'room_type_id' => $quarto->room_type_id,
            'room_id' => $quarto->id,
            'check_in_date' => $de ?? today()->subDay(),
            'check_out_date' => $ate ?? today(),
            'nights' => 1, 'room_rate' => 20000,
            'status' => Reservation::STATUS_CHECKED_IN, 'source' => 'direct',
        ]);
    }
}
