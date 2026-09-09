<?php

namespace Tests\Feature\Hotel;

use App\Models\Hotel\HotelSettings;
use App\Models\Hotel\RateSeason;
use App\Models\Hotel\RoomType;
use App\Models\Tenant;
use App\Services\Hotel\Tarifas;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * AS TARIFAS E AS DEFINIÇÕES — e a conta que nunca foi aplicada a nada.
 *
 * A época, o dia da semana e o dia especial existiam, com ecrã e tudo, e a
 * conta que os junta vivia como método estático de um componente Livewire: o
 * ÚNICO sítio que a chamava era o calendário do seu próprio ecrã. Definir uma
 * época alta não mudava uma reserva, nem um preço na página pública.
 */
class TarifasEDefinicoesTest extends TenantTestCase
{
    private const TARIFAS = '/api/v1/invoicing/react/hotel/tarifas';
    private const DEFINICOES = '/api/v1/invoicing/react/hotel/definicoes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');
    }

    /* ─── As três camadas ─────────────────────────────────────────────── */

    /**
     * A ÉPOCA MUDA O PREÇO BASE; o dia da semana multiplica-o; o dia concreto
     * substitui tudo. Por esta ordem.
     */
    public function test_as_tres_camadas_aplicam_se_por_ordem(): void
    {
        $tipo = $this->tipo(20000);
        $motor = app(Tarifas::class);

        // Sem nada, é o preço base.
        $this->assertSame(20000.0, $motor->precoDaNoite($this->tenant->id, $tipo->id, '2026-07-01'));

        // Época alta: +50%.
        RateSeason::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Alta',
            'start_date' => '2026-07-01', 'end_date' => '2026-08-31',
            'price_modifier' => 1.5, 'modifier_type' => 'multiplier',
            'priority' => 0, 'is_active' => true,
        ]);

        $this->assertSame(30000.0, $motor->precoDaNoite($this->tenant->id, $tipo->id, '2026-07-01'));

        // E o dia da semana multiplica o que a época deu. 2026-07-04 é sábado.
        DB::table('hotel_weekday_rates')->insert([
            'tenant_id' => $this->tenant->id, 'room_type_id' => $tipo->id,
            'day_of_week' => 6, 'price_modifier' => 1.2, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(36000.0, $motor->precoDaNoite($this->tenant->id, $tipo->id, '2026-07-04'));

        // A tarifa do dia concreto substitui tudo — não multiplica.
        DB::table('hotel_special_rates')->insert([
            'tenant_id' => $this->tenant->id, 'room_type_id' => null,
            'date' => '2026-07-04', 'price' => 99000, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(99000.0, $motor->precoDaNoite($this->tenant->id, $tipo->id, '2026-07-04'));
    }

    /** Duas épocas sobre o mesmo dia: ganha a de maior prioridade. */
    public function test_a_epoca_de_maior_prioridade_ganha(): void
    {
        $tipo = $this->tipo(10000);

        RateSeason::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Verão',
            'start_date' => '2026-07-01', 'end_date' => '2026-08-31',
            'price_modifier' => 1.5, 'modifier_type' => 'multiplier',
            'priority' => 1, 'is_active' => true,
        ]);

        RateSeason::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Festival',
            'start_date' => '2026-07-10', 'end_date' => '2026-07-12',
            'price_modifier' => 3, 'modifier_type' => 'multiplier',
            'priority' => 9, 'is_active' => true,
        ]);

        $motor = app(Tarifas::class);

        $this->assertSame(30000.0, $motor->precoDaNoite($this->tenant->id, $tipo->id, '2026-07-11'));
        $this->assertSame(15000.0, $motor->precoDaNoite($this->tenant->id, $tipo->id, '2026-07-20'));
    }

    /**
     * A ESTADA CONTA AS NOITES, e não os dias.
     *
     * Quem entra a 3 e sai a 5 dorme duas noites: a noite da saída já é de
     * quem vier a seguir.
     */
    public function test_a_estada_conta_as_noites_e_nao_os_dias(): void
    {
        $tipo = $this->tipo(10000);

        $preco = app(Tarifas::class)->precoDaEstada($this->tenant->id, $tipo->id, '2026-07-03', '2026-07-05');

        $this->assertSame(2, $preco['noites']);
        $this->assertSame(20000.0, $preco['total']);
        $this->assertSame(10000.0, $preco['media']);
        $this->assertCount(2, $preco['dias']);
    }

    /**
     * O FORMULÁRIO DA RESERVA PERGUNTA O PREÇO A ESTA CONTA.
     *
     * É esta porta que põe as tarifas a servir para alguma coisa.
     */
    public function test_a_porta_do_preco_responde_a_reserva(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $tipo = $this->tipo(20000);

        RateSeason::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Alta',
            'start_date' => '2026-07-01', 'end_date' => '2026-08-31',
            'price_modifier' => 25, 'modifier_type' => 'percentage',
            'priority' => 0, 'is_active' => true,
        ]);

        $this->getJson(self::TARIFAS . "/preco?tipo={$tipo->id}&de=2026-07-10&ate=2026-07-12")
            ->assertOk()
            ->assertJsonPath('noites', 2)
            ->assertJsonPath('media', 25000)
            ->assertJsonPath('total', 50000);
    }

    /** E não responde sobre o tipo de outra casa. */
    public function test_o_preco_nao_atravessa_empresas(): void
    {
        $this->comPermissoes('hotel.reservations.view');

        $outra = Tenant::create([
            'name' => 'Hotel do Lado', 'email' => uniqid() . '@exemplo.ao',
            'nif' => (string) random_int(500000000, 599999999), 'is_active' => true,
        ]);

        $alheio = RoomType::create([
            'tenant_id' => $outra->id, 'name' => 'Duplo', 'code' => 'D-' . substr(uniqid(), -4),
            'base_price' => 20000, 'capacity' => 2,
        ]);

        $this->getJson(self::TARIFAS . "/preco?tipo={$alheio->id}&de=2026-07-10&ate=2026-07-12")
            ->assertStatus(422);
    }

    /* ─── Guardar as tarifas ──────────────────────────────────────────── */

    /** Um dia que vale um não é regra nenhuma: não se grava. */
    public function test_um_dia_que_vale_um_nao_se_grava(): void
    {
        $this->comPermissoes('hotel.rates.view', 'hotel.rates.edit');

        $tipo = $this->tipo(10000);

        $this->putJson(self::TARIFAS . '/por-dia', [
            'tipo' => $tipo->id,
            'dias' => [0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1.2, 6 => 1.5],
        ])->assertOk();

        $gravadas = DB::table('hotel_weekday_rates')
            ->where('tenant_id', $this->tenant->id)->where('room_type_id', $tipo->id)->get();

        $this->assertCount(2, $gravadas, 'só a sexta e o sábado mudam alguma coisa');
        $this->assertEqualsCanonicalizing([5, 6], $gravadas->pluck('day_of_week')->all());
    }

    /** E não se guardam tarifas sobre o tipo de outra casa. */
    public function test_nao_se_guarda_tarifa_de_outra_empresa(): void
    {
        $this->comPermissoes('hotel.rates.view', 'hotel.rates.edit');

        $outra = Tenant::create([
            'name' => 'Hotel do Lado', 'email' => uniqid() . '@exemplo.ao',
            'nif' => (string) random_int(500000000, 599999999), 'is_active' => true,
        ]);

        $alheio = RoomType::create([
            'tenant_id' => $outra->id, 'name' => 'Duplo', 'code' => 'D-' . substr(uniqid(), -4),
            'base_price' => 20000, 'capacity' => 2,
        ]);

        $this->putJson(self::TARIFAS . '/por-dia', [
            'tipo' => $alheio->id,
            'dias' => [0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2, 6 => 2],
        ])->assertStatus(422);

        $this->postJson(self::TARIFAS . '/especiais', [
            'dia' => '2026-07-04', 'tipo' => $alheio->id, 'preco' => 50000,
        ])->assertStatus(422);

        $this->assertSame(0, DB::table('hotel_weekday_rates')->count());
        $this->assertSame(0, DB::table('hotel_special_rates')->count());
    }

    /** Uma tarifa de dia de outra casa não se apaga daqui. */
    public function test_nao_se_apaga_tarifa_de_outra_empresa(): void
    {
        $this->comPermissoes('hotel.rates.view', 'hotel.rates.delete');

        $outra = Tenant::create([
            'name' => 'Hotel do Lado', 'email' => uniqid() . '@exemplo.ao',
            'nif' => (string) random_int(500000000, 599999999), 'is_active' => true,
        ]);

        $id = DB::table('hotel_special_rates')->insertGetId([
            'tenant_id' => $outra->id, 'room_type_id' => null,
            'date' => '2026-07-04', 'price' => 50000, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->deleteJson(self::TARIFAS . "/especiais/{$id}")->assertNotFound();

        $this->assertSame(1, DB::table('hotel_special_rates')->where('id', $id)->count());
    }

    /** Ver as tarifas não é mexer nelas. */
    public function test_quem_so_ve_as_tarifas_nao_mexe(): void
    {
        $this->comPermissoes('hotel.rates.view', 'hotel.reservations.view');

        $tipo = $this->tipo(10000);

        $this->getJson(self::TARIFAS . '/calendario')->assertOk();
        $this->getJson(self::TARIFAS . '/opcoes')->assertOk()
            ->assertJsonPath('permissoes.pode_editar', false);

        $this->putJson(self::TARIFAS . '/por-dia', [
            'tipo' => $tipo->id, 'dias' => [0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2, 6 => 2],
        ])->assertForbidden();

        $this->postJson(self::TARIFAS . '/especiais', [
            'dia' => '2026-07-04', 'preco' => 50000,
        ])->assertForbidden();
    }

    /* ─── As definições ───────────────────────────────────────────────── */

    /**
     * VER NÃO É ALTERAR.
     *
     * O ecrã de sempre pedia `hotel.settings.view` na morada e mais nada:
     * chegar lá era poder mudar o preço do check-in tardio, a política de
     * cancelamento e o endereço público da casa.
     */
    public function test_quem_so_ve_as_definicoes_nao_altera(): void
    {
        $this->comPermissoes('hotel.settings.view');

        $this->getJson(self::DEFINICOES)->assertOk()
            ->assertJsonPath('permissoes.pode_editar', false);

        $this->putJson(self::DEFINICOES, [
            'hotel_name' => 'Outro Nome', 'star_rating' => 5,
            'default_check_in_time' => '10:00', 'default_check_out_time' => '09:00',
            'min_advance_booking_hours' => 0, 'max_advance_booking_days' => 30,
        ])->assertForbidden();

        $this->postJson(self::DEFINICOES . '/novo-endereco')->assertForbidden();
    }

    /**
     * O ENDEREÇO PÚBLICO NÃO MUDA SOZINHO.
     *
     * Ele nasce do nome — mas uma vez só. Mudar o nome do hotel não pode
     * partir as ligações que já andam por aí: o cartaz, o Instagram, o
     * WhatsApp. Trocá-lo é uma decisão à parte, com aviso.
     */
    public function test_o_endereco_publico_nao_muda_com_o_nome(): void
    {
        $this->comPermissoes('hotel.settings.view', 'hotel.settings.edit');

        $r = $this->putJson(self::DEFINICOES, $this->corpo(['hotel_name' => 'Mussulo Bay']))->assertOk();

        $slug = $r->json('definicoes.booking_slug');

        $this->assertNotEmpty($slug, 'sem endereço não há página pública');
        $this->assertStringContainsString($slug, $r->json('definicoes.booking_url'));

        $r2 = $this->putJson(self::DEFINICOES, $this->corpo(['hotel_name' => 'Outro Nome Qualquer']))->assertOk();

        $this->assertSame($slug, $r2->json('definicoes.booking_slug'));

        // E trocá-lo de propósito muda-o — é a porta que avisa antes.
        $r3 = $this->postJson(self::DEFINICOES . '/novo-endereco')->assertOk();

        $this->assertNotSame($slug, $r3->json('definicoes.booking_slug'));
    }

    /**
     * OS QUARTOS EM DESTAQUE TÊM DE SER DESTA CASA.
     *
     * A lista vem do browser e vai para a PÁGINA PÚBLICA: um id de outro hotel
     * punha o quarto do concorrente na montra desta casa.
     */
    public function test_os_destaques_de_outra_casa_sao_deitados_fora(): void
    {
        $this->comPermissoes('hotel.settings.view', 'hotel.settings.edit');

        $meu = $this->tipo(10000);

        $outra = Tenant::create([
            'name' => 'Hotel do Lado', 'email' => uniqid() . '@exemplo.ao',
            'nif' => (string) random_int(500000000, 599999999), 'is_active' => true,
        ]);

        $alheio = RoomType::create([
            'tenant_id' => $outra->id, 'name' => 'Suite', 'code' => 'S-' . substr(uniqid(), -4),
            'base_price' => 90000, 'capacity' => 4,
        ]);

        $r = $this->putJson(self::DEFINICOES, $this->corpo([
            'featured_rooms' => [$meu->id, $alheio->id],
            'amenities_list' => ['wifi', 'inventada'],
        ]))->assertOk();

        $this->assertSame([$meu->id], $r->json('definicoes.featured_rooms'));
        $this->assertSame(['wifi'], $r->json('definicoes.amenities_list'),
            'uma comodidade que a casa não conhece não tem ícone na página pública');
    }

    /* ─── Fixtures ────────────────────────────────────────────────────── */

    private function tipo(float $preco): RoomType
    {
        return RoomType::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Duplo',
            'code' => 'D-' . substr(uniqid(), -4), 'base_price' => $preco,
            'capacity' => 2, 'is_active' => true,
        ]);
    }

    private function corpo(array $campos = []): array
    {
        return array_merge([
            'hotel_name' => 'Hotel de Ensaio',
            'star_rating' => 4,
            'default_check_in_time' => '14:00',
            'default_check_out_time' => '12:00',
            'min_advance_booking_hours' => 12,
            'max_advance_booking_days' => 180,
        ], $campos);
    }
}
