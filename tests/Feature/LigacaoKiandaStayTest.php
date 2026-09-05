<?php

namespace Tests\Feature;

use App\Models\Hotel\LigacaoKiandaStay;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\RoomType;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

/**
 * A ligação do módulo de hotel ao KiandaStay.
 *
 * O KiandaStay é o site onde o hóspede reserva; isto é a casa onde ele fica.
 * O que se guarda aqui é o contrato entre os dois, lido do código do próprio
 * site: o evento assinado com HMAC-SHA256 sobre o corpo cru, o payload do
 * `ReservationResource`, e o facto de o site entregar UMA vez e não repetir.
 */
class LigacaoKiandaStayTest extends TenantTestCase
{
    private LigacaoKiandaStay $ligacao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');

        $this->ligacao = LigacaoKiandaStay::paraTenant($this->tenant->id);

        $this->ligacao->forceFill([
            'activa'         => true,
            'base_url'       => 'https://kiandastay.exemplo',
            'api_key'        => 'okb_chave',
            'webhook_secret' => 'segredo',
            'property_id'    => 7,
            'estado_inicial' => 'pending',
            'criar_hospede'  => true,
            'mapa_tipos'     => [],
        ])->save();
    }

    /** O payload tal como o site o monta (ReservationResource). */
    private function evento(array $substituir = [], string $tipo = 'reservation.created'): array
    {
        return [
            'event'     => $tipo,
            'timestamp' => now()->toIso8601String(),
            'data'      => array_merge([
                'id'                => 42,
                'confirmation_code' => 'OKB-ABCD1234',
                'status'            => 'pending',
                'payment_status'    => 'pending',
                'hotel'             => ['id' => 7, 'name' => 'Hotel do Ensaio', 'slug' => 'hotel'],
                'room_type_id'      => 3,
                'check_in'          => today()->addDays(5)->toDateString(),
                'check_out'         => today()->addDays(8)->toDateString(),
                'guests'            => 2,
                'total_price'       => 150000.0,
                'currency'          => 'AKZ',
                'special_requests'  => 'Andar alto | Tel: +244923000000',
            ], $substituir),
        ];
    }

    private function entregar(array $evento, ?string $segredo = 'segredo')
    {
        $corpo = json_encode($evento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $cabecalhos = $segredo === null
            ? []
            : ['X-Webhook-Signature' => 'sha256=' . hash_hmac('sha256', $corpo, $segredo)];

        return $this->call(
            'POST',
            '/webhooks/kiandastay/' . $this->tenant->id,
            [], [], [],
            $this->transformHeadersToServerVars($cabecalhos + ['CONTENT_TYPE' => 'application/json']),
            $corpo
        );
    }

    /**
     * SEM ASSINATURA VÁLIDA NÃO ENTRA NADA.
     *
     * A porta é pública — é o site que chama, máquina a máquina. O que a fecha
     * é o segredo que o próprio site devolveu ao registar o webhook. Sem esta
     * guarda, quem soubesse o número da empresa inventava reservas.
     *
     * @test
     */
    public function um_evento_sem_assinatura_certa_nao_cria_nada(): void
    {
        $this->entregar($this->evento(), 'outro-segredo')->assertStatus(403);

        $this->assertSame(0, Reservation::where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * UMA RESERVA DO SITE APARECE NA RECEPÇÃO, COM O TOTAL CERTO.
     *
     * O preço guarda-se por noite e SEM imposto: esta casa acrescenta-o ao
     * calcular o total. Pôr o total do site dividido pelas noites fazia a
     * factura do check-out sair mais cara do que o hóspede reservou.
     *
     * @test
     */
    public function uma_reserva_do_site_entra_com_o_preco_que_o_hospede_aceitou(): void
    {
        $this->entregar($this->evento())->assertOk();

        $r = Reservation::where('tenant_id', $this->tenant->id)->first();

        $this->assertNotNull($r, 'a reserva tem de entrar');
        $this->assertSame('kiandastay', $r->source, 'o canal tem de se poder medir');
        $this->assertSame('kiandastay', $r->external_source);
        $this->assertSame('OKB-ABCD1234', $r->external_id);
        $this->assertSame('OKB-ABCD1234', $r->confirmation_code, 'é o código que o hóspede traz na mão');
        $this->assertSame(3, (int) $r->nights);
        $this->assertSame(2, (int) $r->adults);

        $this->assertEqualsWithDelta(150000.0, (float) $r->total, 0.05,
            'o total tem de bater certo com o que o site cobrou');
    }

    /**
     * O MESMO EVENTO DUAS VEZES NÃO DÁ DUAS RESERVAS.
     *
     * O site não tem repetição controlada, mas uma entrega repetida — ou um
     * `status_changed` a seguir ao `created` — não pode duplicar o hóspede.
     *
     * @test
     */
    public function o_mesmo_evento_repetido_actualiza_em_vez_de_duplicar(): void
    {
        $this->entregar($this->evento())->assertOk();
        $this->entregar($this->evento())->assertOk();

        $this->assertSame(1, Reservation::where('tenant_id', $this->tenant->id)->count());

        // E o cancelamento do site chega à reserva que já cá está.
        $this->entregar(
            $this->evento(['status' => 'cancelled'], 'reservation.cancelled')
        )->assertOk();

        $this->assertSame('cancelled', Reservation::where('tenant_id', $this->tenant->id)->value('status'));
        $this->assertSame(1, Reservation::where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * AS RESERVAS DOS OUTROS HOTÉIS DO SITE NÃO ENTRAM AQUI.
     *
     * O webhook do KiandaStay é global — não é por hotel. Sem esta guarda, uma
     * casa via as reservas de todas as outras do mesmo site.
     *
     * @test
     */
    public function so_entram_as_reservas_do_hotel_desta_casa(): void
    {
        $this->entregar($this->evento(['hotel' => ['id' => 99, 'name' => 'Outro']]))->assertOk();

        $this->assertSame(0, Reservation::where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * UMA RESERVA NUNCA SE PERDE POR FALTA DE CONFIGURAÇÃO.
     *
     * A coluna `room_type_id` não aceita nulo: uma casa que ainda não tenha
     * classificado os quartos, ou que não tenha mapeado aquele tipo, perdia a
     * reserva com um erro de base de dados — e o hóspede aparecia à porta sem
     * ninguém saber.
     *
     * @test
     */
    public function sem_tipos_de_quarto_a_reserva_entra_a_mesma(): void
    {
        $this->assertSame(0, RoomType::where('tenant_id', $this->tenant->id)->count(),
            'a casa começa sem tipos de quarto nenhuns');

        $this->entregar($this->evento())->assertOk();

        $r = Reservation::where('tenant_id', $this->tenant->id)->first();

        $this->assertNotNull($r);
        $this->assertNotNull($r->room_type_id, 'foi criado um tipo para a reserva poder entrar');
        $this->assertStringContainsString('KiandaStay', (string) $r->internal_notes);
    }

    /** O mapa manda: o tipo do site vira o tipo desta casa. @test */
    public function o_mapa_de_tipos_de_quarto_e_respeitado(): void
    {
        $suite = RoomType::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Suite',
            'base_price' => 90000,
            'capacity'   => 2,
            'is_active'  => true,
        ]);

        $this->ligacao->forceFill(['mapa_tipos' => ['3' => $suite->id]])->save();

        $this->entregar($this->evento())->assertOk();

        $this->assertSame($suite->id, (int) Reservation::where('tenant_id', $this->tenant->id)->value('room_type_id'));
    }

    /**
     * CANCELAR AQUI CANCELA NO SITE.
     *
     * Sem isto o quarto ficava à venda como reservado no site depois de a
     * recepção já o ter libertado.
     *
     * @test
     */
    public function cancelar_a_reserva_avisa_o_site(): void
    {
        Http::fake([
            '*/api/v1/bookings/*/cancel' => Http::response(['data' => ['status' => 'cancelled']], 200),
        ]);

        $this->entregar($this->evento())->assertOk();

        $r = Reservation::where('tenant_id', $this->tenant->id)->first();
        $r->cancel('Quarto avariado');

        Http::assertSent(fn ($p) => str_contains($p->url(), '/api/v1/bookings/OKB-ABCD1234/cancel')
            && $p->hasHeader('X-API-Key', 'okb_chave'));

        $this->assertSame('cancelled', $r->fresh()->status);
    }

    /**
     * LIGAR REGISTA-SE NO SITE E GUARDA O SEGREDO.
     *
     * É este passo que torna a ligação automática: a partir daqui o site
     * entrega cada reserva no momento em que é feita. O segredo só vem nesta
     * resposta, e é ele que valida todos os eventos seguintes.
     *
     * @test
     */
    public function ligar_regista_o_webhook_e_guarda_o_segredo(): void
    {
        Http::fake([
            '*/api/v1/webhooks' => Http::response([
                'data' => ['id' => 12, 'secret' => 'segredo-novo', 'url' => 'x'],
            ], 201),
        ]);

        $r = \App\Services\Hotel\KiandaStay::para($this->ligacao)->registarWebhook();

        $this->assertTrue($r['ok']);
        $this->assertSame('segredo-novo', $this->ligacao->fresh()->webhook_secret);
        $this->assertSame('12', $this->ligacao->fresh()->webhook_id);

        Http::assertSent(fn ($p) => str_contains($p->url(), '/api/v1/webhooks')
            && $p['url'] === $this->ligacao->urlDoWebhook()
            && in_array('reservation.created', $p['events'], true));
    }

    /** Os segredos ficam cifrados na base — nunca em claro. @test */
    public function os_segredos_nao_ficam_em_claro_na_base(): void
    {
        $linha = \Illuminate\Support\Facades\DB::table('hotel_ligacao_kiandastay')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertNotSame('okb_chave', $linha->api_key);
        $this->assertNotSame('segredo', $linha->webhook_secret);
        $this->assertSame('okb_chave', $this->ligacao->fresh()->api_key, 'e decifram-se ao ler');
    }
}
