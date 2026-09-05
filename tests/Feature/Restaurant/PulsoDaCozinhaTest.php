<?php

namespace Tests\Feature\Restaurant;

use App\Models\Restaurant\Area;
use App\Models\Restaurant\KitchenStation;
use App\Models\Restaurant\KitchenTicket;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\Venue;
use Tests\TenantTestCase;

/**
 * O pulso da cozinha.
 *
 * PORQUE EXISTE. O ecrã da cozinha refazia-se de 15 em 15 segundos, sempre.
 * Duas contas más: um prato podia esperar quinze segundos para ser visto, e
 * uma cozinha parada pagava a mesma consulta grande que uma cozinha cheia.
 *
 * O pulso responde à pergunta barata — mudou alguma coisa? — e o ecrã só se
 * refaz quando a resposta muda. O que estes ensaios prendem é a única coisa
 * que o pode estragar em silêncio: um pulso que NÃO muda quando devia. Nesse
 * caso o ecrã da cozinha fica parado a mostrar pedidos velhos, e ninguém dá
 * por isso até um cliente reclamar.
 */
class PulsoDaCozinhaTest extends TenantTestCase
{
    private Venue $venue;

    private KitchenStation $posto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');
        $this->comPermissoes('restaurant.kitchen.view');

        $this->venue = Venue::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRINCIPAL',
            'name' => 'Restaurante Principal',
            'warehouse_id' => $this->armazem->id,
        ]);

        Area::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $this->venue->id,
            'name' => 'Sala',
        ]);

        $this->posto = KitchenStation::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $this->venue->id,
            'code' => 'GRELHA',
            'name' => 'Grelha',
            'is_active' => true,
        ]);
    }

    private function comanda(): Order
    {
        return Order::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $this->venue->id,
            'waiter_id' => $this->user->id,
            'order_number' => 'CMD-'.uniqid(),
            'channel' => 'counter',
            'status' => 'confirmed',
            'guest_count' => 1,
        ]);
    }

    private function bilhete(string $estado = 'queued'): KitchenTicket
    {
        return KitchenTicket::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->comanda()->id,
            'venue_id' => $this->venue->id,
            'station_id' => $this->posto->id,
            'ticket_number' => 'KT-'.uniqid(),
            'status' => $estado,
            'priority' => 0,
            'queued_at' => now(),
        ]);
    }

    private function pulso(): string
    {
        return $this->actingAs($this->user)
            ->getJson('/restaurant/kitchen/pulso')
            ->assertOk()
            ->json('pulso');
    }

    /** @test */
    public function o_pulso_responde_com_uma_assinatura_curta(): void
    {
        $resposta = $this->actingAs($this->user)
            ->getJson('/restaurant/kitchen/pulso')
            ->assertOk()
            ->assertJsonStructure(['pulso', 'bilhetes']);

        $this->assertSame(0, $resposta->json('bilhetes'));
        $this->assertNotEmpty($resposta->json('pulso'));
    }

    /** Sem nada mudar, o pulso é o mesmo — senão o ecrã refazia-se sempre. */
    public function test_sem_mudancas_o_pulso_e_estavel(): void
    {
        $this->bilhete();

        $this->assertSame($this->pulso(), $this->pulso());
    }

    /** @test */
    public function um_bilhete_novo_muda_o_pulso(): void
    {
        $antes = $this->pulso();

        $this->bilhete();

        $this->assertNotSame($antes, $this->pulso(),
            'um prato novo tem de acordar o ecrã da cozinha');
    }

    /**
     * O ENSAIO QUE IMPORTA: mudar de estado SEM mudar a contagem.
     *
     * É a metade que uma contagem sozinha deixava passar. O bilhete continua
     * na fila, muda de «na fila» para «a preparar», e o ecrã tem de o mostrar
     * — se o pulso não mexer, fica lá o estado antigo até alguém recarregar.
     *
     * @test
     */
    public function mudar_de_estado_sem_mudar_a_contagem_muda_o_pulso(): void
    {
        $bilhete = $this->bilhete('queued');

        $antes = $this->pulso();

        // Sem isto, o `updated_at` ficaria no mesmo segundo e o ensaio passava
        // por acaso — provava o relógio, não o mecanismo.
        $this->travel(2)->seconds();
        $bilhete->update(['status' => 'preparing']);

        $this->assertSame(
            1,
            KitchenTicket::where('tenant_id', $this->tenant->id)->whereIn('status', ['queued', 'accepted', 'preparing', 'ready'])->count(),
            'a contagem tem de ficar igual, senão este ensaio não prova nada'
        );

        $this->assertNotSame($antes, $this->pulso(),
            'mudar de estado tem de acordar o ecrã: a contagem sozinha não chega');
    }

    /** Um bilhete que sai da fila também muda o pulso. */
    public function test_um_bilhete_servido_muda_o_pulso(): void
    {
        $bilhete = $this->bilhete();
        $antes = $this->pulso();

        $bilhete->update(['status' => 'served']);

        $this->assertNotSame($antes, $this->pulso());
    }

    /** Cada posto tem o seu pulso: a grelha não acorda com um prato da copa. */
    public function test_o_pulso_e_por_posto(): void
    {
        $copa = KitchenStation::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $this->venue->id,
            'code' => 'COPA',
            'name' => 'Copa',
            'is_active' => true,
        ]);

        $daGrelha = $this->actingAs($this->user)
            ->getJson('/restaurant/kitchen/pulso?station='.$this->posto->id)
            ->assertOk()->json('pulso');

        // Um bilhete que não é deste posto.
        KitchenTicket::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->comanda()->id,
            'venue_id' => $this->venue->id,
            'station_id' => $copa->id,
            'ticket_number' => 'KT-'.uniqid(),
            'status' => 'queued',
            'priority' => 0,
            'queued_at' => now(),
        ]);

        $this->assertSame(
            $daGrelha,
            $this->actingAs($this->user)
                ->getJson('/restaurant/kitchen/pulso?station='.$this->posto->id)
                ->assertOk()->json('pulso'),
            'o ecrã da grelha não se pode refazer por causa de um prato da copa'
        );
    }

    /** O pulso não pode ser servido de cache: responderia sobre o passado. */
    public function test_o_pulso_nunca_vem_de_cache(): void
    {
        $cabecalho = $this->actingAs($this->user)
            ->getJson('/restaurant/kitchen/pulso')
            ->assertOk()
            ->headers->get('Cache-Control');

        // Só o `no-store` interessa — o resto do cabeçalho muda conforme o
        // que outros middlewares acrescentam, e prendê-lo letra a letra fazia
        // este ensaio falhar por ordem de execução.
        $this->assertStringContainsString('no-store', (string) $cabecalho);
    }

    /** E é do restaurante: sem a permissão da cozinha não se lê. */
    public function test_sem_permissao_da_cozinha_nao_se_le_o_pulso(): void
    {
        $outro = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id]);
        $outro->tenants()->attach($this->tenant->id);

        $this->actingAs($outro)->get('/restaurant/kitchen/pulso')->assertForbidden();
    }
}
