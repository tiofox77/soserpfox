<?php

namespace Tests\Feature\Hotel;

use App\Models\Client;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * AS RESERVAS — as guardas que o ecrã de sempre não tinha.
 *
 * A dupla tributação, o tecto do adiantamento e a sobreposição de datas estão
 * em `HotelReservationTest`, que é onde sempre estiveram. O que este ficheiro
 * guarda é o que a migração trouxe: o escopo de empresa nos ids que vêm do
 * browser, os botões decididos pela tabela de transições, e a lista negra a
 * aparecer onde importa.
 */
class ReservasDoHotelTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/hotel/reservas';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');
    }

    /* ─── As guardas de empresa ───────────────────────────────────────── */

    /**
     * O HÓSPEDE, O TIPO E O QUARTO TÊM DE SER DESTA CASA.
     *
     * O ecrã de sempre validava com `exists:hotel_rooms,id` e
     * `exists:invoicing_clients,id` — as tabelas inteiras, sem empresa nenhuma
     * — e os ids vêm do browser: reservar o quarto de outro hotel em nome de um
     * cliente de outra empresa era escrever dois números diferentes.
     */
    public function test_nao_se_reserva_com_fichas_de_outra_empresa(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.create');

        $outra = $this->outraEmpresa();

        $tipoAlheio = RoomType::create([
            'tenant_id' => $outra->id, 'name' => 'Duplo', 'code' => 'D-' . substr(uniqid(), -4),
            'base_price' => 20000, 'capacity' => 2,
        ]);

        $quartoAlheio = Room::create([
            'tenant_id' => $outra->id, 'room_type_id' => $tipoAlheio->id, 'number' => '901',
        ]);

        $clienteAlheio = Client::create(['tenant_id' => $outra->id, 'name' => 'Cliente do Lado']);

        $meu = $this->tipo();

        // O quarto de outra casa.
        $this->postJson(self::API, $this->corpo([
            'room_type_id' => $meu->id, 'room_id' => $quartoAlheio->id,
        ]))->assertStatus(422);

        // O tipo de outra casa.
        $this->postJson(self::API, $this->corpo(['room_type_id' => $tipoAlheio->id]))->assertStatus(422);

        // O hóspede de outra empresa.
        $this->postJson(self::API, $this->corpo([
            'room_type_id' => $meu->id, 'client_id' => $clienteAlheio->id,
        ]))->assertStatus(422);

        $this->assertSame(0, Reservation::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /** Nem se dá entrada no quarto de outro hotel. */
    public function test_nao_se_da_entrada_no_quarto_de_outra_empresa(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.edit');

        $outra = $this->outraEmpresa();

        $tipoAlheio = RoomType::create([
            'tenant_id' => $outra->id, 'name' => 'Duplo', 'code' => 'D-' . substr(uniqid(), -4),
            'base_price' => 20000, 'capacity' => 2,
        ]);

        $quartoAlheio = Room::create([
            'tenant_id' => $outra->id, 'room_type_id' => $tipoAlheio->id, 'number' => '902',
        ]);

        $reserva = $this->reserva(['status' => 'confirmed']);

        $this->postJson(self::API . "/{$reserva->id}/estado", [
            'accao' => 'entrada', 'quarto' => $quartoAlheio->id,
        ])->assertStatus(422);

        $this->assertSame('confirmed', $reserva->fresh()->status);
    }

    /**
     * DUAS RESERVAS ENCOSTADAS CABEM NO MESMO QUARTO.
     *
     * O que se vende são as NOITES: uma estada de 3 a 5 ocupa as noites de 3 e
     * de 4, e a de 5 é de quem vier a seguir. A conta de disponibilidade usava
     * `whereBetween` nos dois extremos — inclusiva — e recusava justamente o
     * caso que um hotel quer: quem sai de manhã e quem entra à tarde, com o
     * quarto vendido todas as noites. O ecrã dizia «já está reservado nestas
     * datas» sobre um quarto que ficava vazio.
     */
    public function test_quem_entra_no_dia_da_saida_do_outro_e_aceite(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.create');

        $quarto = $this->quarto();

        $this->reserva([
            'room_id' => $quarto->id,
            'room_type_id' => $quarto->room_type_id,
            'check_in_date' => today()->addDays(3),
            'check_out_date' => today()->addDays(5),
            'status' => 'confirmed',
        ]);

        // Entra no dia 5, que é o dia em que o outro sai.
        $this->postJson(self::API, $this->corpo([
            'room_type_id' => $quarto->room_type_id,
            'room_id' => $quarto->id,
            'check_in_date' => today()->addDays(5)->toDateString(),
            'check_out_date' => today()->addDays(7)->toDateString(),
        ]))->assertCreated();

        // Mas uma noite a meio continua a ser recusada.
        $this->postJson(self::API, $this->corpo([
            'room_type_id' => $quarto->room_type_id,
            'room_id' => $quarto->id,
            'check_in_date' => today()->addDays(4)->toDateString(),
            'check_out_date' => today()->addDays(6)->toDateString(),
        ]))->assertStatus(422)->assertJsonValidationErrors('room_id');
    }

    /* ─── Os botões vêm da tabela de transições ───────────────────────── */

    /**
     * O QUE SE PODE FAZER É DECIDIDO NO SERVIDOR.
     *
     * O ecrã de sempre repetia as condições em `@if`s — `status === 'pending'`,
     * `in_array($status, ['pending','confirmed'])` — e quando a tabela de
     * transições do modelo mudou, os botões não mudaram com ela.
     */
    public function test_o_que_se_pode_fazer_segue_a_tabela_de_transicoes(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $pendente = $this->reserva(['status' => 'pending']);
        $saiu = $this->reserva(['status' => 'checked_out']);

        $p = $this->getJson(self::API . "/{$pendente->id}")->assertOk()->json('data.pode');

        $this->assertTrue($p['confirmar']);
        $this->assertTrue($p['entrada']);
        $this->assertTrue($p['cancelar']);
        $this->assertTrue($p['nao_compareceu']);
        $this->assertTrue($p['editar']);
        $this->assertFalse($p['saida']);

        $s = $this->getJson(self::API . "/{$saiu->id}")->assertOk()->json('data.pode');

        // Quem já saiu não volta atrás: a tabela não tem saída nenhuma daqui.
        $this->assertFalse($s['confirmar']);
        $this->assertFalse($s['entrada']);
        $this->assertFalse($s['cancelar']);
        $this->assertFalse($s['nao_compareceu']);
        $this->assertFalse($s['editar']);
        $this->assertFalse($s['saida']);
    }

    /** Uma transição que a tabela recusa responde 422 com o motivo. */
    public function test_uma_transicao_impossivel_e_recusada_com_motivo(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.edit');

        $saiu = $this->reserva(['status' => 'checked_out']);

        $this->postJson(self::API . "/{$saiu->id}/estado", ['accao' => 'confirmar'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('accao');

        $this->assertSame('checked_out', $saiu->fresh()->status);
    }

    /** Confirmar e dar entrada — o caminho normal, e o quarto que o acompanha. */
    public function test_confirmar_e_dar_entrada_ocupam_o_quarto(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.edit');

        $quarto = $this->quarto();
        $reserva = $this->reserva(['status' => 'pending', 'check_in_date' => today()]);

        $this->postJson(self::API . "/{$reserva->id}/estado", ['accao' => 'confirmar'])
            ->assertOk()->assertJsonPath('data.estado', 'confirmed');

        $this->postJson(self::API . "/{$reserva->id}/estado", ['accao' => 'entrada', 'quarto' => $quarto->id])
            ->assertOk()->assertJsonPath('data.estado', 'checked_in');

        $this->assertSame($quarto->id, $reserva->fresh()->room_id);
        $this->assertSame('occupied', $quarto->fresh()->status);
    }

    /**
     * DAR ENTRADA SEM QUARTO NENHUM É RECUSADO.
     *
     * Uma reserva marcada só pelo tipo não tem quarto atribuído, e entrar sem
     * ele deixava um hóspede hospedado em lado nenhum.
     */
    public function test_dar_entrada_sem_quarto_e_recusado(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.edit');

        $reserva = $this->reserva(['status' => 'confirmed']);

        $this->postJson(self::API . "/{$reserva->id}/estado", ['accao' => 'entrada'])
            ->assertStatus(422)->assertJsonValidationErrors('accao');

        $this->assertSame('confirmed', $reserva->fresh()->status);
    }

    /* ─── Cancelar avisa das facturas ─────────────────────────────────── */

    /**
     * CANCELAR NÃO ANULA UM DOCUMENTO FISCAL.
     *
     * Uma factura emitida não desaparece com o cancelamento da reserva — só uma
     * nota de crédito a anula. A resposta traz o aviso, em vez de deixar a
     * estada cancelada e o documento vivo sem ninguém dar por isso.
     */
    public function test_cancelar_com_factura_emitida_avisa(): void
    {
        $this->comPermissoes('hotel.reservations.view', 'hotel.reservations.edit');

        $reserva = $this->reserva(['status' => 'confirmed']);

        $factura = \App\Models\Invoicing\SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'invoice_number' => 'FT ' . strtoupper(substr(uniqid(), -6)),
            'invoice_date' => today(),
            'status' => 'paid',
            'source_module' => 'hotel',
            'source_reference' => $reserva->reservation_number,
            'created_by' => $this->user->id,
            'subtotal' => 10000, 'tax_amount' => 1400, 'total' => 11400, 'paid_amount' => 11400,
        ]);

        $r = $this->postJson(self::API . "/{$reserva->id}/estado", ['accao' => 'cancelar'])->assertOk();

        $this->assertSame('cancelled', $reserva->fresh()->status);
        $this->assertNotNull($r->json('aviso'), 'o aviso das facturas por regularizar');
        $this->assertStringContainsString($factura->invoice_number, $r->json('aviso'));
    }

    /* ─── A procura de hóspedes ───────────────────────────────────────── */

    /**
     * A LISTA NEGRA VIAJA COM O HÓSPEDE.
     *
     * É uma decisão da casa — quem lá está não volta a ficar hospedado — e o
     * ecrã de sempre só a mostrava na ficha do hóspede, que ninguém ia lá ver
     * antes de dar o quarto.
     */
    public function test_a_procura_de_hospedes_diz_quem_esta_na_lista_negra(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        Client::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Kiala Proibido',
            'hotel_blacklisted' => true, 'is_active' => true,
        ]);

        $r = $this->getJson(self::API . '/hospedes?procura=Kiala')->assertOk();

        $this->assertCount(1, $r->json('data'));
        $this->assertTrue($r->json('data.0.lista_negra'));
    }

    /** A procura não atravessa empresas. */
    public function test_a_procura_de_hospedes_nao_atravessa_empresas(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        Client::create([
            'tenant_id' => $this->outraEmpresa()->id, 'name' => 'Zeca do Lado', 'is_active' => true,
        ]);

        $this->getJson(self::API . '/hospedes?procura=Zeca')->assertOk()->assertJsonCount(0, 'data');
    }

    /* ─── Os números e as permissões ──────────────────────────────────── */

    /**
     * OS CARTÕES SÃO DA CASA e não da página nem do filtro.
     *
     * «Entram hoje» é uma pergunta sobre o hotel, e um número que muda ao virar
     * a página deixa de a responder.
     */
    public function test_os_cartoes_contam_a_casa_toda(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $this->reserva(['status' => 'confirmed', 'check_in_date' => today(), 'check_out_date' => today()->addDays(2)]);
        $this->reserva(['status' => 'pending']);
        $this->reserva(['status' => 'pending']);

        $r = $this->getJson(self::API . '?estado=pending')->assertOk();

        $this->assertCount(2, $r->json('data'), 'a lista segue o filtro');
        $this->assertSame(1, $r->json('resumo.entram_hoje'), 'os cartões contam a casa toda');
        $this->assertSame(2, $r->json('resumo.pendentes'));
    }

    /** Ver as reservas não é mexer nelas. */
    public function test_quem_so_ve_nao_mexe(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $reserva = $this->reserva(['status' => 'pending']);

        $this->getJson(self::API)->assertOk();
        $this->getJson(self::API . '/opcoes')->assertOk()
            ->assertJsonPath('permissoes.pode_criar', false)
            ->assertJsonPath('permissoes.pode_editar', false);

        $this->postJson(self::API, $this->corpo())->assertForbidden();
        $this->postJson(self::API . "/{$reserva->id}/estado", ['accao' => 'confirmar'])->assertForbidden();
        $this->postJson(self::API . "/{$reserva->id}/receber", ['valor' => 100, 'meio' => 1])->assertForbidden();

        $this->assertSame('pending', $reserva->fresh()->status);
    }

    /** Sem permissão nenhuma não se vêem as reservas. */
    public function test_sem_permissao_nao_se_ve(): void
    {
        $this->getJson(self::API)->assertForbidden();
        $this->getJson(self::API . '/opcoes')->assertForbidden();
    }

    /* ─── Fixtures ────────────────────────────────────────────────────── */

    private function outraEmpresa(): Tenant
    {
        return Tenant::create([
            'name' => 'Hotel do Lado', 'email' => uniqid() . '@exemplo.ao',
            'nif' => (string) random_int(500000000, 599999999), 'is_active' => true,
        ]);
    }

    private function tipo(): RoomType
    {
        return RoomType::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Duplo',
            'code' => 'D-' . substr(uniqid(), -4), 'base_price' => 28000,
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

    private function reserva(array $campos = []): Reservation
    {
        return Reservation::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -6),
            'client_id' => $this->cliente->id,
            'room_type_id' => $this->tipo()->id,
            'check_in_date' => today()->addDays(3),
            'check_out_date' => today()->addDays(5),
            'nights' => 2,
            'room_rate' => 28000,
            'status' => 'pending',
            'source' => 'direct',
        ], $campos));
    }

    private function corpo(array $campos = []): array
    {
        return array_merge([
            'client_id' => $this->cliente->id,
            'room_type_id' => $this->tipo()->id,
            'room_id' => '',
            'check_in_date' => today()->addDays(10)->toDateString(),
            'check_out_date' => today()->addDays(12)->toDateString(),
            'adults' => 2, 'children' => 0, 'extra_beds' => 0,
            'source' => 'direct', 'room_rate' => 28000, 'discount' => 0, 'paid_amount' => 0,
        ], $campos);
    }
}
