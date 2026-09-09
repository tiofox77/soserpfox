<?php

namespace Tests\Feature\Hotel;

use App\Models\Hotel\Guest;
use App\Models\Hotel\HotelSettings;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * O HOTEL DE UMA EMPRESA NÃO SE ALCANÇA DE OUTRA.
 *
 * Era o maior dos cinco módulos sem guarda: vinte e cinco rotas com
 * `['auth', 'tenant.module:hotel']` e mais nada, dez modelos sem escopo de
 * empresa, e vinte e três `find()` com o id vindo do browser — hóspedes,
 * reservas, quartos, tarefas de limpeza, ordens de manutenção.
 *
 * E GUARDA O QUE NÃO PODE PARTIR. O hotel tem TRÊS caminhos públicos, e todos
 * eles identificam a empresa por outra coisa que não a sessão:
 *
 *  · a página de reservas, pelo SLUG;
 *  · o check-in por QR, pelo `confirmation_code` do URL;
 *  · e `getForTenant($id)`, que resolve o hotel a partir da reserva.
 *
 * O último era o mais perigoso: com o escopo a filtrar pela empresa ACTIVA, o
 * `firstOrCreate` não encontrava a linha de definições que existe e criava uma
 * SEGUNDA para essa empresa.
 */
class HotelNaoAtravessaEmpresasTest extends TenantTestCase
{
    private Tenant $outra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');

        $this->outra = Tenant::create([
            'name' => 'Hotel do Lado',
            'slug' => 'lado-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'lado' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);
    }

    private function alheio(string $classe, array $campos)
    {
        return $classe::withoutGlobalScopes()->create($campos + ['tenant_id' => $this->outra->id]);
    }

    private function reservaAlheia(): Reservation
    {
        $tipo = $this->alheio(RoomType::class, ['name' => 'Duplo do Lado', 'base_price' => 20000]);

        $quarto = $this->alheio(Room::class, [
            'room_type_id' => $tipo->id, 'number' => '101', 'status' => 'available',
        ]);

        $hospede = $this->alheio(Guest::class, ['name' => 'Hóspede do Lado']);

        return $this->alheio(Reservation::class, [
            'reservation_number' => 'RES-' . substr(uniqid(), -8),
            'guest_id' => $hospede->id,
            'room_id' => $quarto->id,
            'room_type_id' => $tipo->id,
            'check_in_date' => today(),
            'check_out_date' => today()->addDay(),
            'room_rate' => 20000,
            'nights' => 1,
            'adults' => 1,
            'total_amount' => 20000,
            'status' => 'confirmed',
            'confirmation_code' => 'CODIGO123',
        ]);
    }

    /* ─── O escopo ────────────────────────────────────────────────────── */

    public function test_um_hospede_e_uma_reserva_de_outra_empresa_nao_se_leem(): void
    {
        $reserva = $this->reservaAlheia();

        $this->assertNull(Reservation::find($reserva->id));
        $this->assertNull(Guest::find($reserva->guest_id));
        $this->assertNull(Room::find($reserva->room_id));
        $this->assertNull(RoomType::find($reserva->room_type_id));

        $this->assertSame(0, Reservation::count() + Guest::count() + Room::count() + RoomType::count());
    }

    public function test_os_documentos_de_uma_reserva_de_outra_empresa_nao_abrem(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $reserva = $this->reservaAlheia();

        foreach (['voucher', 'folio.pdf', 'sef', 'qr'] as $documento) {
            $this->get(route("hotel.reservations.{$documento}", $reserva->id))
                ->assertNotFound();
        }
    }

    public function test_o_que_e_desta_empresa_continua_a_ver_se(): void
    {
        $tipo = RoomType::create(['name' => 'Duplo da Casa', 'base_price' => 15000]);

        $this->assertSame($this->tenant->id, $tipo->tenant_id, 'o escopo preenche a empresa ao criar');
        $this->assertNotNull(RoomType::find($tipo->id));
    }

    /* ─── O que o escopo não pode partir ──────────────────────────────── */

    /** A página pública de reservas abre pelo SLUG, mesmo com outra empresa activa. */
    public function test_a_pagina_publica_abre_com_outra_empresa_activa(): void
    {
        $definicoes = HotelSettings::withoutGlobalScopes()->create([
            'tenant_id' => $this->outra->id,
            'hotel_name' => 'Hotel do Lado',
            'booking_slug' => 'hotel-do-lado-' . uniqid(),
            'online_booking_enabled' => true,
        ]);

        $this->assertNotNull(
            HotelSettings::findBySlug($definicoes->booking_slug),
            'a morada pública é do slug, não da empresa activa'
        );
    }

    /** E o slug é único no MUNDO: duas moradas iguais é a segunda a levar as reservas da primeira. */
    public function test_o_slug_publico_nao_se_repete_entre_empresas(): void
    {
        HotelSettings::withoutGlobalScopes()->create([
            'tenant_id' => $this->outra->id,
            'hotel_name' => 'Miramar',
            'booking_slug' => 'miramar',
        ]);

        $this->assertSame('miramar-1', HotelSettings::generateUniqueSlug('Miramar'));
    }

    /**
     * `getForTenant($outra)` NÃO PODE CRIAR UMA SEGUNDA LINHA.
     *
     * É o caminho que a página pública de check-in percorre: resolve o hotel a
     * partir da reserva, que é de outra empresa.
     */
    public function test_as_definicoes_de_outra_empresa_nao_se_duplicam(): void
    {
        $existente = HotelSettings::withoutGlobalScopes()->create([
            'tenant_id' => $this->outra->id,
            'hotel_name' => 'Hotel do Lado',
        ]);

        $lidas = HotelSettings::getForTenant($this->outra->id);

        $this->assertSame($existente->id, $lidas->id, 'tem de devolver a linha que existe');
        $this->assertSame(
            1,
            HotelSettings::withoutGlobalScopes()->where('tenant_id', $this->outra->id)->count(),
            'e não criar uma segunda'
        );
    }

    /**
     * O CHECK-IN POR QR é público: quem autoriza é o código do URL.
     *
     * Com sessão aberta noutra empresa, a página tem de continuar a abrir — é
     * o recepcionista a conferir o link que o hóspede recebeu.
     */
    public function test_o_check_in_por_qr_abre_mesmo_com_outra_empresa_activa(): void
    {
        $reserva = $this->reservaAlheia();

        $this->get(route('hotel.express-checkin', [$reserva->id, 'CODIGO123']))->assertOk();

        // E o código errado continua a ser recusado.
        $this->get(route('hotel.express-checkin', [$reserva->id, 'ERRADO']))->assertForbidden();
    }

    /* ─── As rotas ────────────────────────────────────────────────────── */

    public function test_cada_rota_do_hotel_pede_a_sua_permissao(): void
    {
        $rotas = [
            'hotel.dashboard' => 'hotel.dashboard.view',
            'hotel.room-types' => 'hotel.room-types.view',
            'hotel.rooms' => 'hotel.rooms.view',
            'hotel.guests' => 'hotel.guests.view',
            'hotel.reservations' => 'hotel.reservations.view',
            'hotel.walk-in' => 'hotel.walk-in.create',
            'hotel.calendar' => 'hotel.reservations.view',
            'hotel.housekeeping' => 'hotel.housekeeping.view',
            'hotel.maintenance' => 'hotel.maintenance.view',
            'hotel.staff' => 'hotel.staff.view',
            'hotel.reports' => 'hotel.reports.view',
            'hotel.rates' => 'hotel.rates.view',
            'hotel.packages' => 'hotel.packages.view',
            'hotel.settings' => 'hotel.settings.view',
        ];

        foreach (array_keys($rotas) as $rota) {
            $this->get(route($rota))->assertForbidden();
        }

        $this->comPermissoes(...array_unique(array_values($rotas)));

        foreach (array_keys($rotas) as $rota) {
            $this->get(route($rota))->assertOk();
        }
    }
}
