<?php

namespace Tests\Feature\Hotel;

use App\Models\Client;
use App\Models\Hotel\MaintenanceOrder;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use Tests\TenantTestCase;

/**
 * O PAINEL E OS CINCO MAPAS DO HOTEL.
 *
 * O que estes ensaios guardam, para além de «abre e responde»:
 *
 *  1. O NOME DO HÓSPEDE APARECE. Os dois ecrãs liam `->guest`, que aponta para
 *     `hotel_guests`; as reservas gravam `client_id` e deixam `guest_id` a
 *     nulo. O painel mostrava as chegadas do dia sem o nome de ninguém, e o
 *     mapa de hóspedes dizia sempre zero.
 *
 *  2. OS TRÊS NÚMEROS DA HOTELARIA — ocupação, ADR e RevPAR — batem certo.
 *     São a linguagem do negócio: o RevPAR é o que se compara com o hotel do
 *     lado, e uma conta errada aqui é uma decisão errada lá fora.
 *
 *  3. A OCUPAÇÃO CONTA-SE POR NOITE. A noite de saída não conta: quem sai de
 *     manhã liberta o quarto nesse dia.
 *
 *  4. O PAPEL E O EXCEL EXISTEM. Os dois botões respondiam «Exportação em
 *     desenvolvimento».
 */
class PainelEMapasDoHotelTest extends TenantTestCase
{
    private const PAINEL = '/api/v1/invoicing/react/hotel/painel';
    private const MAPAS = '/api/v1/invoicing/react/hotel/relatorios';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');
    }

    /* ─── O painel ────────────────────────────────────────────────────── */

    /**
     * O NOME DE QUEM CHEGA APARECE — e vem do CLIENTE.
     */
    public function test_as_chegadas_de_hoje_trazem_o_nome_do_hospede(): void
    {
        $this->comPermissoes('hotel.dashboard.view');

        $cliente = Client::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Dona Ana Kiluanje', 'type' => 'pessoa_fisica',
        ]);

        $this->reserva([
            'client_id' => $cliente->id,
            'check_in_date' => today(),
            'check_out_date' => today()->addDays(2),
            'status' => 'confirmed',
        ]);

        $chegadas = $this->getJson(self::PAINEL)->assertOk()->json('hoje.chegadas');

        $this->assertCount(1, $chegadas);
        $this->assertSame('Dona Ana Kiluanje', $chegadas[0]['hospede']);
    }

    /** Sem ninguém ligado, escreve-se um traço — nunca uma linha em branco. */
    public function test_uma_reserva_sem_hospede_mostra_um_traco(): void
    {
        $this->comPermissoes('hotel.dashboard.view');

        $this->reserva(['check_in_date' => today(), 'check_out_date' => today()->addDay(), 'status' => 'confirmed']);

        $this->assertSame('—', $this->getJson(self::PAINEL)->assertOk()->json('hoje.chegadas.0.hospede'));
    }

    /** O mapa da casa vem agrupado por piso, com o estado de cada quarto. */
    public function test_o_mapa_da_casa_vem_por_piso(): void
    {
        $this->comPermissoes('hotel.dashboard.view');

        $tipo = $this->tipo();
        $this->quarto($tipo, ['number' => '101', 'floor' => '1', 'status' => 'available']);
        $this->quarto($tipo, ['number' => '201', 'floor' => '2', 'status' => 'occupied', 'housekeeping_status' => 'dirty']);

        $mapa = $this->getJson(self::PAINEL)->assertOk()->json('mapa');

        $this->assertCount(2, $mapa);
        $this->assertSame('1', $mapa[0]['piso']);
        $this->assertSame('101', $mapa[0]['quartos'][0]['numero']);
        $this->assertSame(__('Livre'), $mapa[0]['quartos'][0]['estado_rotulo']);
        $this->assertSame('dirty', $mapa[1]['quartos'][0]['limpeza']);
    }

    /** A taxa de ocupação é a razão entre ocupados e o total de quartos. */
    public function test_a_taxa_de_ocupacao_conta_os_quartos(): void
    {
        $this->comPermissoes('hotel.dashboard.view');

        $tipo = $this->tipo();
        $this->quarto($tipo, ['number' => '101', 'status' => 'occupied']);
        $this->quarto($tipo, ['number' => '102', 'status' => 'occupied']);
        $this->quarto($tipo, ['number' => '103', 'status' => 'available']);
        $this->quarto($tipo, ['number' => '104', 'status' => 'available']);

        $r = $this->getJson(self::PAINEL)->assertOk();

        $this->assertSame(4, $r->json('quartos.total'));
        $this->assertSame(2, $r->json('quartos.ocupados'));
        $this->assertEquals(50.0, $r->json('quartos.ocupacao'));
    }

    /**
     * A MANUTEÇÃO ABERTA VEM AO PAINEL DO HOTEL.
     *
     * Um quarto com uma fuga de água não se vende, e quem está na recepção não
     * abre o ecrã da manutenção para saber disso.
     */
    public function test_o_painel_conta_a_manutencao_aberta(): void
    {
        $this->comPermissoes('hotel.dashboard.view');

        MaintenanceOrder::create(['tenant_id' => $this->tenant->id, 'title' => 'A', 'category' => 'other', 'priority' => 'urgent', 'status' => 'pending']);
        MaintenanceOrder::create(['tenant_id' => $this->tenant->id, 'title' => 'B', 'category' => 'other', 'priority' => 'low', 'status' => 'in_progress']);
        MaintenanceOrder::create(['tenant_id' => $this->tenant->id, 'title' => 'C', 'category' => 'other', 'priority' => 'urgent', 'status' => 'completed']);

        $r = $this->getJson(self::PAINEL)->assertOk();

        $this->assertSame(2, $r->json('manutencao.abertas'), 'as concluídas não estão abertas');
        $this->assertSame(1, $r->json('manutencao.urgentes'));
    }

    /** Sem permissão de relatórios, o dinheiro não sai do servidor. */
    public function test_sem_permissao_de_relatorios_o_dinheiro_nao_sai(): void
    {
        $this->comPermissoes('hotel.dashboard.view');

        $this->reserva([
            'check_in_date' => today()->subDays(3), 'check_out_date' => today(),
            'status' => 'checked_out', 'total' => 250000,
        ]);

        $r = $this->getJson(self::PAINEL)->assertOk();

        $this->assertFalse($r->json('ve_dinheiro'));
        $this->assertNull($r->json('dinheiro'));
        $this->assertStringNotContainsString('250000', $r->getContent());
    }

    public function test_o_painel_pede_a_sua_permissao(): void
    {
        $this->getJson(self::PAINEL)->assertForbidden();
    }

    /* ─── Os mapas ────────────────────────────────────────────────────── */

    public function test_os_cinco_mapas_respondem_com_colunas(): void
    {
        $this->comPermissoes('hotel.reports.view');

        $this->comAlgumaOcupacao();

        foreach (['ocupacao', 'receita_por_dia', 'receita_por_tipo', 'receita_por_origem', 'hospedes'] as $qual) {
            $r = $this->getJson(self::MAPAS . '?mapa=' . $qual)->assertOk();

            $this->assertNotEmpty($r->json('colunas'), "{$qual}: sem colunas");
            $this->assertNotEmpty($r->json('linhas'), "{$qual}: sem linhas");

            foreach ($r->json('colunas') as $c) {
                $this->assertArrayHasKey($c['chave'], $r->json('linhas.0'),
                    "{$qual}: a coluna {$c['chave']} não vem nas linhas");
            }
        }
    }

    /**
     * OS TRÊS NÚMEROS DA HOTELARIA.
     *
     * Dois quartos, dez dias de período, uma estada de 4 noites:
     *   · noites disponíveis = 2 × 10 = 20
     *   · ocupação = 4 / 20 = 20%
     *   · ADR = receita / 4 noites vendidas
     *   · RevPAR = receita / 20 noites disponíveis
     */
    public function test_a_ocupacao_o_adr_e_o_revpar(): void
    {
        $this->comPermissoes('hotel.reports.view');

        $tipo = $this->tipo();
        $this->quarto($tipo, ['number' => '101']);
        $this->quarto($tipo, ['number' => '102']);

        $de = today()->subDays(9);
        $ate = today();

        $this->reserva([
            'room_type_id' => $tipo->id,
            'check_in_date' => $de->copy()->addDays(2),
            'check_out_date' => $de->copy()->addDays(6),
            'nights' => 4,
            'status' => 'checked_out',
            'total' => 100000,
        ]);

        $kpis = $this->getJson(self::MAPAS . "?mapa=ocupacao&de={$de->toDateString()}&ate={$ate->toDateString()}")
            ->assertOk()->json('kpis');

        $this->assertSame(2, $kpis['quartos']);
        $this->assertSame(10, $kpis['dias']);
        $this->assertEquals(4, $kpis['noites_vendidas']);
        $this->assertEquals(20, $kpis['noites_disponiveis']);
        $this->assertEquals(20.0, $kpis['ocupacao']);

        /*
         * O TOTAL DA RESERVA É DO MODELO — quarto × noites, mais extras, mais
         * o imposto do regime da empresa. O ADR e o RevPAR conferem-se contra
         * o que ficou gravado, e não contra um número escrito à mão que o
         * modelo ia recalcular por baixo.
         */
        $total = (float) Reservation::sum('total');

        $this->assertEquals(round($total / 4, 2), $kpis['adr']);
        $this->assertEquals(round($total / 20, 2), $kpis['revpar']);
    }

    /**
     * A NOITE DE SAÍDA NÃO CONTA — quem sai de manhã liberta o quarto.
     *
     * Uma estada de 10 a 12 ocupa os dias 10 e 11, e não o 12.
     */
    public function test_a_noite_de_saida_nao_conta_na_ocupacao(): void
    {
        $this->comPermissoes('hotel.reports.view');

        $tipo = $this->tipo();
        $this->quarto($tipo, ['number' => '101']);

        $de = today()->subDays(5);

        $this->reserva([
            'room_type_id' => $tipo->id,
            'check_in_date' => $de->copy()->addDays(1),
            'check_out_date' => $de->copy()->addDays(3),
            'nights' => 2,
            'status' => 'checked_out',
        ]);

        $linhas = collect($this->getJson(self::MAPAS . "?mapa=ocupacao&de={$de->toDateString()}&ate=" . today()->toDateString())
            ->assertOk()->json('linhas'))->keyBy('dia');

        $this->assertSame(0, $linhas[$de->toDateString()]['ocupados']);
        $this->assertSame(1, $linhas[$de->copy()->addDays(1)->toDateString()]['ocupados']);
        $this->assertSame(1, $linhas[$de->copy()->addDays(2)->toDateString()]['ocupados']);
        $this->assertSame(0, $linhas[$de->copy()->addDays(3)->toDateString()]['ocupados'], 'o dia de saída está livre');
    }

    /**
     * O MAPA DE HÓSPEDES CONTA PELO CLIENTE.
     *
     * Lia `hotel_guests`, que está vazia: mostrava sempre zero hóspedes e zero
     * nacionalidades.
     */
    public function test_o_mapa_de_hospedes_conta_pelo_cliente(): void
    {
        $this->comPermissoes('hotel.reports.view');

        $cliente = Client::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Sr. Bento Cruz',
            'type' => 'pessoa_fisica', 'nationality' => 'Angola',
        ]);

        $this->reserva([
            'client_id' => $cliente->id, 'status' => 'checked_out',
            'check_in_date' => today()->subDays(3), 'check_out_date' => today()->subDay(),
            'nights' => 2, 'total' => 56000,
        ]);
        $this->reserva([
            'client_id' => $cliente->id, 'status' => 'checked_out',
            'check_in_date' => today()->subDays(10), 'check_out_date' => today()->subDays(9),
            'nights' => 1, 'total' => 28000,
        ]);

        $linhas = $this->getJson(self::MAPAS . '?mapa=hospedes&de=' . today()->subMonth()->toDateString()
            . '&ate=' . today()->toDateString())->assertOk()->json('linhas');

        $this->assertCount(1, $linhas);
        $this->assertSame('Sr. Bento Cruz', $linhas[0]['nome']);
        $this->assertSame('Angola', $linhas[0]['nacionalidade']);
        $this->assertSame(2, $linhas[0]['estadas']);
        // O total de uma reserva é recalculado pelo modelo (quarto × noites,
        // mais extras, mais imposto do regime): a soma confere-se contra o
        // que ficou gravado, e não contra um número escrito à mão.
        $this->assertEquals(
            (float) Reservation::where('client_id', $cliente->id)->sum('total'),
            $linhas[0]['receita']
        );
    }

    /** Uma reserva cancelada não ocupou nada e não rendeu nada. */
    public function test_uma_reserva_cancelada_nao_conta(): void
    {
        $this->comPermissoes('hotel.reports.view');

        $tipo = $this->tipo();
        $this->quarto($tipo, ['number' => '101']);

        $this->reserva([
            'room_type_id' => $tipo->id, 'status' => 'cancelled',
            'check_in_date' => today()->subDays(2), 'check_out_date' => today(),
            'nights' => 2, 'total' => 99999,
        ]);

        $kpis = $this->getJson(self::MAPAS . '?mapa=ocupacao')->assertOk()->json('kpis');

        $this->assertEquals(0, $kpis['noites_vendidas']);
        $this->assertEquals(0, $kpis['receita']);
    }

    public function test_um_mapa_desconhecido_e_recusado(): void
    {
        $this->comPermissoes('hotel.reports.view');

        $this->getJson(self::MAPAS . '?mapa=inventado')->assertStatus(422);
    }

    public function test_os_mapas_pedem_a_sua_permissao(): void
    {
        $this->getJson(self::MAPAS . '/opcoes')->assertForbidden();
        $this->getJson(self::MAPAS . '?mapa=ocupacao')->assertForbidden();
    }

    /* ─── O papel e o Excel ───────────────────────────────────────────── */

    public function test_o_papel_sai_com_os_tres_numeros(): void
    {
        $this->comPermissoes('hotel.reports.view');

        $this->comAlgumaOcupacao();

        $html = $this->get(route('hotel.reports.imprimir', ['mapa' => 'receita_por_tipo']))
            ->assertOk()->getContent();

        // Os três números da hotelaria vão no cabeçalho: é por eles que se
        // imprime um mapa destes.
        $this->assertStringContainsString(__('Ocupação'), $html);
        $this->assertStringContainsString('ADR', $html);
        $this->assertStringContainsString('RevPAR', $html);
    }

    public function test_o_excel_descarrega(): void
    {
        $this->comPermissoes('hotel.reports.view');

        $this->comAlgumaOcupacao();

        $r = $this->get(route('hotel.reports.excel', ['mapa' => 'ocupacao']))->assertOk();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $r->headers->get('Content-Type')
        );
    }

    public function test_o_papel_e_o_excel_pedem_permissao(): void
    {
        $this->get(route('hotel.reports.imprimir', ['mapa' => 'ocupacao']))->assertForbidden();
        $this->get(route('hotel.reports.excel', ['mapa' => 'ocupacao']))->assertForbidden();
    }

    /** E as duas moradas abrem com o ecrã React montado. */
    public function test_as_moradas_abrem_em_react(): void
    {
        $this->comPermissoes('hotel.dashboard.view', 'hotel.reports.view');

        $this->get('/hotel/dashboard')->assertOk()->assertSee('data-ecra="hotel/painel"', false);
        $this->get('/hotel/reports')->assertOk()->assertSee('data-ecra="hotel/relatorios"', false);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function tipo(): RoomType
    {
        return RoomType::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Duplo',
            'code' => 'D-' . substr(uniqid(), -4), 'base_price' => 28000,
            'capacity' => 2, 'extra_bed_capacity' => 0, 'extra_bed_price' => 0, 'is_active' => true,
        ]);
    }

    private function quarto(RoomType $tipo, array $campos = []): Room
    {
        return Room::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'room_type_id' => $tipo->id,
            'number' => (string) random_int(100, 999),
            'status' => 'available',
            'housekeeping_status' => 'clean',
            'is_active' => true,
        ], $campos));
    }

    private function reserva(array $campos = []): Reservation
    {
        $tipo = $campos['room_type_id'] ?? $this->tipo()->id;

        return Reservation::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -6),
            'room_type_id' => $tipo,
            'check_in_date' => today(),
            'check_out_date' => today()->addDays(2),
            'room_rate' => 28000,
            'nights' => 2,
            'status' => 'confirmed',
            'source' => 'direct',
            'total' => 56000,
        ], $campos));
    }

    /** Um quarto e uma estada fechada — o mínimo para os cinco mapas. */
    private function comAlgumaOcupacao(): void
    {
        $tipo = $this->tipo();
        $this->quarto($tipo, ['number' => '101']);

        $cliente = Client::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Hóspede de Ensaio',
            'type' => 'pessoa_fisica', 'nationality' => 'Angola',
        ]);

        $this->reserva([
            'room_type_id' => $tipo->id,
            'client_id' => $cliente->id,
            'check_in_date' => today()->subDays(3),
            'check_out_date' => today()->subDay(),
            'nights' => 2,
            'status' => 'checked_out',
            'total' => 56000,
            'paid_amount' => 56000,
        ]);
    }
}
