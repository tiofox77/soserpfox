<?php

namespace Tests\Feature\Hotel;

use App\Models\Client;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use Tests\TenantTestCase;

/**
 * O CALENDÁRIO E O BALCÃO — e a estada que nascia sem adquirente.
 *
 * Os dois ecrãs criavam reservas com uma ficha da tabela antiga
 * (`hotel_guests`), que a facturação não conhece, e deixavam `client_id` a
 * nulo: no check-out não havia a quem facturar e o hóspede saía sem documento.
 * É o defeito mais caro que esta migração apanhou, e o primeiro ensaio deste
 * ficheiro é a trave que o impede de voltar.
 */
class CalendarioEBalcaoTest extends TenantTestCase
{
    private const CALENDARIO = '/api/v1/invoicing/react/hotel/calendario';
    private const BALCAO = '/api/v1/invoicing/react/hotel/balcao';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');
    }

    /* ─── O balcão ────────────────────────────────────────────────────── */

    /**
     * A ESTADA DO BALCÃO NASCE COM ADQUIRENTE.
     *
     * Sem cliente não há a quem facturar no check-out — e era assim que ela
     * nascia. A porta exige-o, e a entrada fica dada.
     */
    public function test_o_balcao_regista_a_entrada_com_hospede_que_se_pode_facturar(): void
    {
        $this->comPermissoes('hotel.walk-in.create');

        $quarto = $this->quarto();

        $r = $this->postJson(self::BALCAO, [
            'client_id' => $this->cliente->id,
            'room_type_id' => $quarto->room_type_id,
            'room_id' => $quarto->id,
            'check_in_date' => today()->toDateString(),
            'check_out_date' => today()->addDays(2)->toDateString(),
            'adults' => 2, 'children' => 0,
            'room_rate' => 30000, 'discount' => 0, 'paid_amount' => 0,
        ])->assertCreated();

        $reserva = Reservation::first();

        $this->assertSame($this->cliente->id, $reserva->client_id, 'sem cliente não há a quem facturar');
        $this->assertSame('walk_in', $reserva->source);
        $this->assertSame('checked_in', $reserva->status, 'a pessoa está ali de mala na mão');
        $this->assertNotNull($reserva->actual_check_in);
        $this->assertSame(2, (int) $reserva->nights);

        // A ENTRADA PELA PORTA DO MODELO: o quarto fica ocupado.
        $this->assertSame('occupied', $quarto->fresh()->status);
        $this->assertSame($quarto->number, $r->json('data.quarto'));
    }

    /**
     * OS QUARTOS «LIVRES» SÃO OS QUE ESTÃO MESMO LIVRES.
     *
     * O ecrã de sempre listava os de estado `available` e mais nada — um quarto
     * livre hoje mas reservado para amanhã aparecia na lista, e o walk-in de
     * três noites entrava por cima da reserva de amanhã.
     */
    public function test_um_quarto_reservado_para_amanha_nao_aparece_como_livre(): void
    {
        $this->comPermissoes('hotel.walk-in.create');

        $quarto = $this->quarto();

        Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -6),
            'client_id' => $this->cliente->id,
            'room_type_id' => $quarto->room_type_id,
            'room_id' => $quarto->id,
            'check_in_date' => today()->addDay(),
            'check_out_date' => today()->addDays(3),
            'nights' => 2, 'room_rate' => 30000,
            'status' => 'confirmed', 'source' => 'direct',
        ]);

        // Uma noite só, hoje: cabe antes da reserva de amanhã.
        $this->getJson(self::BALCAO . "/quartos?tipo={$quarto->room_type_id}"
            . '&de=' . today()->toDateString() . '&ate=' . today()->addDay()->toDateString())
            ->assertOk()->assertJsonCount(1, 'data');

        // Três noites: passa por cima da reserva de amanhã.
        $this->getJson(self::BALCAO . "/quartos?tipo={$quarto->room_type_id}"
            . '&de=' . today()->toDateString() . '&ate=' . today()->addDays(3)->toDateString())
            ->assertOk()->assertJsonCount(0, 'data');
    }

    /** E a última palavra é ao registar, não na lista. */
    public function test_o_balcao_recusa_um_quarto_que_entretanto_ficou_ocupado(): void
    {
        $this->comPermissoes('hotel.walk-in.create');

        $quarto = $this->quarto();

        Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -6),
            'client_id' => $this->cliente->id,
            'room_type_id' => $quarto->room_type_id,
            'room_id' => $quarto->id,
            'check_in_date' => today(),
            'check_out_date' => today()->addDays(2),
            'nights' => 2, 'room_rate' => 30000,
            'status' => 'confirmed', 'source' => 'direct',
        ]);

        $this->postJson(self::BALCAO, [
            'client_id' => $this->cliente->id,
            'room_type_id' => $quarto->room_type_id,
            'room_id' => $quarto->id,
            'check_in_date' => today()->toDateString(),
            'check_out_date' => today()->addDay()->toDateString(),
            'adults' => 1, 'children' => 0,
            'room_rate' => 30000, 'discount' => 0, 'paid_amount' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('room_id');
    }

    /** O quarto tem de ser desta casa, e do tipo escolhido. */
    public function test_o_balcao_nao_aceita_quarto_de_outra_casa(): void
    {
        $this->comPermissoes('hotel.walk-in.create');

        $outra = \App\Models\Tenant::create([
            'name' => 'Hotel do Lado', 'email' => uniqid() . '@exemplo.ao',
            'nif' => (string) random_int(500000000, 599999999), 'is_active' => true,
        ]);

        $tipoAlheio = RoomType::create([
            'tenant_id' => $outra->id, 'name' => 'Duplo', 'code' => 'D-' . substr(uniqid(), -4),
            'base_price' => 20000, 'capacity' => 2,
        ]);

        $alheio = Room::create([
            'tenant_id' => $outra->id, 'room_type_id' => $tipoAlheio->id, 'number' => '903',
        ]);

        $meu = $this->quarto();

        $this->postJson(self::BALCAO, [
            'client_id' => $this->cliente->id,
            'room_type_id' => $meu->room_type_id,
            'room_id' => $alheio->id,
            'check_in_date' => today()->toDateString(),
            'check_out_date' => today()->addDay()->toDateString(),
            'adults' => 1, 'children' => 0,
            'room_rate' => 30000, 'discount' => 0, 'paid_amount' => 0,
        ])->assertStatus(422);

        $this->assertSame(0, Reservation::withoutGlobalScopes()->count());
    }

    /** O balcão tem a sua própria permissão. */
    public function test_o_balcao_pede_a_sua_permissao(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $this->getJson(self::BALCAO . '/opcoes')->assertForbidden();
        $this->postJson(self::BALCAO, [])->assertForbidden();
    }

    /* ─── O calendário ────────────────────────────────────────────────── */

    /**
     * UMA ESTADA SEM QUARTO NÃO SE REPETE POR TODOS OS QUARTOS DO TIPO.
     *
     * O ecrã de sempre punha a mesma reserva em cada linha do tipo: cinco
     * barras para uma estada, e cinco quartos a parecer vendidos. Fica numa
     * faixa própria, que é o que ela é — procura por atribuir.
     */
    public function test_uma_estada_sem_quarto_fica_a_parte(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $tipo = $this->tipo();

        foreach (['101', '102', '103'] as $n) {
            Room::create([
                'tenant_id' => $this->tenant->id, 'room_type_id' => $tipo->id, 'number' => $n,
                'status' => 'available', 'is_active' => true,
            ]);
        }

        Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -6),
            'client_id' => $this->cliente->id,
            'room_type_id' => $tipo->id,
            'room_id' => null,
            'check_in_date' => today(),
            'check_out_date' => today()->addDays(2),
            'nights' => 2, 'room_rate' => 30000,
            'status' => 'confirmed', 'source' => 'direct',
        ]);

        $r = $this->getJson(self::CALENDARIO . '?dia=' . today()->toDateString())->assertOk();

        $this->assertCount(1, $r->json('por_atribuir'));

        foreach ($r->json('quartos') as $quarto) {
            $this->assertCount(0, $quarto['barras'], 'nenhum quarto tem barra: a estada não tem quarto');
        }
    }

    /** E o nome do hóspede sai da ficha certa — todas as barras diziam «Sem hóspede». */
    public function test_a_barra_mostra_o_hospede(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $cliente = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Domingos Kiala']);
        $quarto = $this->quarto();

        Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -6),
            'client_id' => $cliente->id,
            'room_type_id' => $quarto->room_type_id,
            'room_id' => $quarto->id,
            'check_in_date' => today(),
            'check_out_date' => today()->addDays(2),
            'nights' => 2, 'room_rate' => 30000,
            'status' => 'confirmed', 'source' => 'direct',
        ]);

        $r = $this->getJson(self::CALENDARIO . '?dia=' . today()->toDateString())->assertOk();

        $barras = collect($r->json('quartos'))->flatMap(fn ($q) => $q['barras']);

        $this->assertCount(1, $barras);
        $this->assertSame('Domingos Kiala', $barras->first()['hospede']);
    }

    /**
     * ARRASTAR UMA BARRA VERIFICA O DESTINO.
     *
     * O ecrã de sempre gravava sem perguntar se o quarto estava livre: duas
     * estadas ficavam por cima uma da outra, e o conflito só aparecia com os
     * dois hóspedes ao balcão.
     */
    public function test_mover_para_um_quarto_ocupado_e_recusado(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.edit');

        $tipo = $this->tipo();

        $origem = Room::create([
            'tenant_id' => $this->tenant->id, 'room_type_id' => $tipo->id, 'number' => '201',
            'status' => 'available', 'is_active' => true,
        ]);

        $destino = Room::create([
            'tenant_id' => $this->tenant->id, 'room_type_id' => $tipo->id, 'number' => '202',
            'status' => 'available', 'is_active' => true,
        ]);

        $aMover = $this->estada($origem, today()->addDays(5), today()->addDays(7));
        $this->estada($destino, today()->addDays(5), today()->addDays(7));

        $this->postJson(self::CALENDARIO . "/{$aMover->id}/mover", [
            'quarto' => $destino->id, 'dia' => today()->addDays(5)->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('quarto');

        $this->assertSame($origem->id, $aMover->fresh()->room_id);
    }

    /** Mover para um quarto livre leva o TIPO atrás — a taxa segue o quarto. */
    public function test_mover_leva_o_tipo_do_quarto_de_destino(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.edit');

        $duplo = $this->tipo();
        $suite = RoomType::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Suite', 'code' => 'S-' . substr(uniqid(), -4),
            'base_price' => 55000, 'capacity' => 4, 'is_active' => true,
        ]);

        $origem = Room::create([
            'tenant_id' => $this->tenant->id, 'room_type_id' => $duplo->id, 'number' => '301',
            'status' => 'available', 'is_active' => true,
        ]);

        $destino = Room::create([
            'tenant_id' => $this->tenant->id, 'room_type_id' => $suite->id, 'number' => '302',
            'status' => 'available', 'is_active' => true,
        ]);

        $reserva = $this->estada($origem, today()->addDays(5), today()->addDays(7));

        $this->postJson(self::CALENDARIO . "/{$reserva->id}/mover", [
            'quarto' => $destino->id, 'dia' => today()->addDays(8)->toDateString(),
        ])->assertOk();

        $fresca = $reserva->fresh();

        $this->assertSame($destino->id, $fresca->room_id);
        $this->assertSame($suite->id, $fresca->room_type_id, 'o tipo segue o quarto');
        $this->assertSame(today()->addDays(8)->toDateString(), $fresca->check_in_date->toDateString());
        // As noites mantêm-se: o check-out anda com o check-in.
        $this->assertSame(today()->addDays(10)->toDateString(), $fresca->check_out_date->toDateString());
    }

    /**
     * QUEM JÁ ENTROU NÃO SE ARRASTA.
     *
     * Mudar as datas de uma estada a decorrer reescreve o que já aconteceu — e
     * o quarto onde o hóspede está a dormir passava a ser outro.
     */
    public function test_nao_se_move_quem_ja_entrou(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.edit');

        $tipo = $this->tipo();

        $onde = Room::create([
            'tenant_id' => $this->tenant->id, 'room_type_id' => $tipo->id, 'number' => '401',
            'status' => 'occupied', 'is_active' => true,
        ]);

        $outro = Room::create([
            'tenant_id' => $this->tenant->id, 'room_type_id' => $tipo->id, 'number' => '402',
            'status' => 'available', 'is_active' => true,
        ]);

        $reserva = $this->estada($onde, today(), today()->addDays(2), 'checked_in');

        $this->postJson(self::CALENDARIO . "/{$reserva->id}/mover", [
            'quarto' => $outro->id, 'dia' => today()->addDay()->toDateString(),
        ])->assertStatus(422);

        $this->assertSame($onde->id, $reserva->fresh()->room_id);
    }

    /** Ver o calendário não é mexer nele. */
    public function test_quem_so_ve_o_calendario_nao_move(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $quarto = $this->quarto();
        $reserva = $this->estada($quarto, today()->addDays(5), today()->addDays(7));

        $this->getJson(self::CALENDARIO)->assertOk();
        $this->getJson(self::CALENDARIO . '/opcoes')->assertOk()
            ->assertJsonPath('permissoes.pode_editar', false);

        $this->postJson(self::CALENDARIO . "/{$reserva->id}/mover", [
            'quarto' => $quarto->id, 'dia' => today()->toDateString(),
        ])->assertForbidden();
    }

    /* ─── Fixtures ────────────────────────────────────────────────────── */

    private function tipo(): RoomType
    {
        return RoomType::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Duplo',
            'code' => 'D-' . substr(uniqid(), -4), 'base_price' => 30000,
            'capacity' => 2, 'is_active' => true,
        ]);
    }

    private function quarto(): Room
    {
        return Room::create([
            'tenant_id' => $this->tenant->id, 'room_type_id' => $this->tipo()->id,
            'number' => (string) random_int(100, 999), 'floor' => '1',
            'status' => 'available', 'housekeeping_status' => 'clean', 'is_active' => true,
        ]);
    }

    private function estada(Room $quarto, $de, $ate, string $estado = 'confirmed'): Reservation
    {
        return Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -6),
            'client_id' => $this->cliente->id,
            'room_type_id' => $quarto->room_type_id,
            'room_id' => $quarto->id,
            'check_in_date' => $de,
            'check_out_date' => $ate,
            'nights' => 2, 'room_rate' => 30000,
            'status' => $estado, 'source' => 'direct',
        ]);
    }
}
