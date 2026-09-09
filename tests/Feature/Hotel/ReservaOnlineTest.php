<?php

namespace Tests\Feature\Hotel;

use App\Models\Client;
use App\Models\Hotel\HotelSettings;
use App\Models\Hotel\RateSeason;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

/**
 * A PÁGINA PÚBLICA DE RESERVAS — a casa vista de fora.
 *
 * Não há sessão nem empresa activa: QUEM MANDA É O SLUG. É a única página do
 * produto onde um estranho escreve na base de dados, e por isso a que mais
 * precisa de guardas.
 *
 * TRÊS DEFEITOS que esta migração apanhou:
 *
 *  · O preço ignorava as tarifas da casa — era `base_price × noites`.
 *  · A senha da conta escrevia-se em `hotel_data`, que não é coluna nem
 *    acessor: nunca era guardada, e entrar só pedia o TELEFONE.
 *  · O sinal era calculado e não chegava a lado nenhum.
 */
class ReservaOnlineTest extends TenantTestCase
{
    private HotelSettings $definicoes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');

        $this->definicoes = HotelSettings::getForTenant($this->tenant->id);
        $this->definicoes->forceFill([
            'hotel_name' => 'Hotel de Ensaio',
            'booking_slug' => 'hotel-de-ensaio-' . substr(uniqid(), -6),
            'online_booking_enabled' => true,
            'min_advance_booking_hours' => 0,
            'max_advance_booking_days' => 365,
        ])->save();

        // A PÁGINA É PÚBLICA: ninguém autenticado.
        auth()->logout();
        session()->forget('active_tenant_id');
    }

    private function api(string $cauda = ''): string
    {
        return '/api/publico/hotel/' . $this->definicoes->booking_slug . $cauda;
    }

    /* ─── A casa ──────────────────────────────────────────────────────── */

    /** A página abre sem sessão nenhuma. */
    public function test_a_casa_abre_sem_sessao(): void
    {
        $this->tipo(20000);

        $this->getJson($this->api())->assertOk()
            ->assertJsonPath('casa.nome', 'Hotel de Ensaio')
            ->assertJsonCount(1, 'tipos');
    }

    /** Um slug que não existe é 404, e não a casa de outra empresa. */
    public function test_um_slug_desconhecido_nao_abre_nada(): void
    {
        $this->getJson('/api/publico/hotel/nao-existe')->assertNotFound();
    }

    /** Com as reservas desligadas, a porta fecha-se. */
    public function test_com_as_reservas_desligadas_nao_se_reserva(): void
    {
        $this->definicoes->forceFill(['online_booking_enabled' => false])->save();

        $this->getJson($this->api())->assertForbidden();
        $this->postJson($this->api('/reservar'), [])->assertForbidden();
    }

    /* ─── O preço ─────────────────────────────────────────────────────── */

    /**
     * O PREÇO SAI DAS TARIFAS DA CASA.
     *
     * Era `base_price × noites`, e a época alta, o fim-de-semana e os dias
     * especiais não mexiam no que o hóspede pagava — justamente na única
     * página onde o preço é uma promessa a um estranho.
     */
    public function test_o_preco_publico_respeita_a_epoca(): void
    {
        $tipo = $this->tipo(20000);
        $this->quarto($tipo);

        $de = today()->addDays(10);
        $ate = today()->addDays(12);

        RateSeason::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Alta',
            'start_date' => $de->copy()->subDays(5), 'end_date' => $ate->copy()->addDays(5),
            'price_modifier' => 2, 'modifier_type' => 'multiplier',
            'priority' => 0, 'is_active' => true,
        ]);

        $r = $this->getJson($this->api('/disponibilidade?de=' . $de->toDateString() . '&ate=' . $ate->toDateString()))
            ->assertOk();

        $this->assertSame(40000, $r->json('tipos.0.preco_por_noite'), 'a época dobra o preço da noite');
        $this->assertSame(80000, $r->json('tipos.0.preco_total'));
        $this->assertSame(1, $r->json('tipos.0.livres'));
    }

    /** E a reserva grava a taxa que o hóspede viu. */
    public function test_a_reserva_grava_o_preco_que_o_hospede_viu(): void
    {
        $tipo = $this->tipo(20000);
        $this->quarto($tipo);

        $de = today()->addDays(10);
        $ate = today()->addDays(12);

        RateSeason::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Alta',
            'start_date' => $de->copy()->subDays(5), 'end_date' => $ate->copy()->addDays(5),
            'price_modifier' => 2, 'modifier_type' => 'multiplier',
            'priority' => 0, 'is_active' => true,
        ]);

        $this->postJson($this->api('/reservar'), [
            'tipo' => $tipo->id,
            'de' => $de->toDateString(), 'ate' => $ate->toDateString(),
            'adultos' => 2, 'criancas' => 0,
            'nome' => 'Aurora Kiala', 'telefone' => '923000111',
        ])->assertCreated()->assertJsonPath('reserva.preco_por_noite', 40000);

        $reserva = Reservation::withoutGlobalScopes()->first();

        $this->assertEquals(40000, $reserva->room_rate);
        $this->assertSame('website', $reserva->source, 'o enum não tem «online»');
        $this->assertSame('pending', $reserva->status);
        $this->assertNotNull($reserva->client_id, 'sem adquirente não há a quem facturar');
    }

    /**
     * O SINAL VAI NA RESPOSTA.
     *
     * Era calculado e não chegava a lado nenhum: o hóspede não sabia quanto
     * tinha de adiantar para a reserva ficar de pé.
     */
    public function test_o_sinal_e_dito_ao_hospede(): void
    {
        $this->definicoes->forceFill(['require_deposit' => true, 'deposit_percent' => 30])->save();

        $tipo = $this->tipo(10000);
        $this->quarto($tipo);

        $r = $this->postJson($this->api('/reservar'), [
            'tipo' => $tipo->id,
            'de' => today()->addDays(5)->toDateString(),
            'ate' => today()->addDays(7)->toDateString(),
            'adultos' => 1, 'criancas' => 0,
            'nome' => 'Bento Mavungo', 'telefone' => '923000222',
        ])->assertCreated();

        $total = (float) $r->json('reserva.total');

        $this->assertGreaterThan(0, $total);
        $this->assertEqualsWithDelta($total * 0.30, (float) $r->json('reserva.sinal'), 0.01);
        $this->assertSame(30, $r->json('reserva.sinal_percentagem'));
    }

    /* ─── A disponibilidade ───────────────────────────────────────────── */

    /** Sem quarto livre, não se reserva — e não se cria um a martelo. */
    public function test_sem_quarto_livre_nao_se_reserva(): void
    {
        $tipo = $this->tipo(10000);
        $quarto = $this->quarto($tipo);

        Reservation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -6),
            'client_id' => $this->cliente->id,
            'room_type_id' => $tipo->id, 'room_id' => $quarto->id,
            'check_in_date' => today()->addDays(5), 'check_out_date' => today()->addDays(8),
            'nights' => 3, 'room_rate' => 10000,
            'status' => 'confirmed', 'source' => 'direct',
        ]);

        $this->postJson($this->api('/reservar'), [
            'tipo' => $tipo->id,
            'de' => today()->addDays(6)->toDateString(),
            'ate' => today()->addDays(7)->toDateString(),
            'adultos' => 1, 'criancas' => 0,
            'nome' => 'Carla Dias', 'telefone' => '923000333',
        ])->assertStatus(422)->assertJsonValidationErrors('tipo');

        $this->assertSame(1, Reservation::withoutGlobalScopes()->count());
        $this->assertSame(1, Room::withoutGlobalScopes()->count(), 'não se cria quarto nenhum');
    }

    /** A janela de antecedência da casa é respeitada. */
    public function test_a_antecedencia_da_casa_e_respeitada(): void
    {
        $this->definicoes->forceFill(['max_advance_booking_days' => 30])->save();

        $tipo = $this->tipo(10000);
        $this->quarto($tipo);

        $this->postJson($this->api('/reservar'), [
            'tipo' => $tipo->id,
            'de' => today()->addDays(90)->toDateString(),
            'ate' => today()->addDays(92)->toDateString(),
            'adultos' => 1, 'criancas' => 0,
            'nome' => 'Longe Demais', 'telefone' => '923000444',
        ])->assertStatus(422)->assertJsonValidationErrors('de');
    }

    /* ─── A conta do hóspede ──────────────────────────────────────────── */

    /**
     * A SENHA É MESMO GUARDADA — e é mesmo verificada.
     *
     * Escrevia-se em `hotel_data`, que não é coluna nem acessor: o Eloquent
     * descartava a escrita em silêncio. Quem «criava conta com senha» ficava
     * sem senha nenhuma, e entrar só pedia o TELEFONE — qualquer pessoa que
     * soubesse o número entrava na ficha do hóspede.
     */
    public function test_a_senha_da_conta_e_guardada_e_verificada(): void
    {
        $this->postJson($this->api('/registar'), [
            'nome' => 'Domingos Kiala', 'telefone' => '923777888',
            'email' => 'domingos@exemplo.ao', 'senha' => 'segredo123',
        ])->assertCreated();

        $cliente = Client::withoutGlobalScopes()->where('phone', '923777888')->first();

        $this->assertNotNull($cliente->senha_de_reservas, 'a senha tem de ficar guardada');
        $this->assertTrue(Hash::check('segredo123', $cliente->senha_de_reservas));

        // E sem ela não se entra.
        $this->postJson($this->api('/entrar'), ['telefone' => '923777888'])
            ->assertStatus(422)->assertJsonValidationErrors('senha');

        $this->postJson($this->api('/entrar'), ['telefone' => '923777888', 'senha' => 'errada'])
            ->assertStatus(422)->assertJsonValidationErrors('senha');

        $this->postJson($this->api('/entrar'), ['telefone' => '923777888', 'senha' => 'segredo123'])
            ->assertOk()->assertJsonPath('hospede.nome', 'Domingos Kiala');
    }

    /** Quem nunca definiu senha entra só com o telefone — e é de propósito. */
    public function test_quem_nao_tem_senha_entra_com_o_telefone(): void
    {
        Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Sem Senha',
            'phone' => '923999000', 'is_active' => true,
        ]);

        $this->postJson($this->api('/entrar'), ['telefone' => '923999000'])
            ->assertOk()->assertJsonPath('hospede.nome', 'Sem Senha');
    }

    /** Não se cria uma segunda ficha para o mesmo telefone. */
    public function test_nao_se_repete_a_ficha_do_mesmo_telefone(): void
    {
        $this->postJson($this->api('/registar'), [
            'nome' => 'Primeiro', 'telefone' => '923111222',
        ])->assertCreated();

        $this->postJson($this->api('/registar'), [
            'nome' => 'Segundo', 'telefone' => '923111222',
        ])->assertStatus(422)->assertJsonValidationErrors('telefone');

        $this->assertSame(1, Client::withoutGlobalScopes()->where('phone', '923111222')->count());
    }

    /* ─── Fixtures ────────────────────────────────────────────────────── */

    private function tipo(float $preco): RoomType
    {
        return RoomType::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Duplo',
            'code' => 'D-' . substr(uniqid(), -4), 'base_price' => $preco,
            'capacity' => 2, 'is_active' => true,
        ]);
    }

    private function quarto(RoomType $tipo): Room
    {
        return Room::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'room_type_id' => $tipo->id,
            'number' => (string) random_int(100, 999), 'floor' => '1',
            'status' => 'available', 'housekeeping_status' => 'clean', 'is_active' => true,
        ]);
    }
}
