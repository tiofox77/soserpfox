<?php

namespace Tests\Feature\Hotel;

use App\Models\Client;
use App\Models\Hotel\HousekeepingTask;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * A LIMPEZA DOS QUARTOS.
 *
 * O que este ficheiro guarda são os quatro defeitos que a migração encontrou —
 * a coluna «Com problemas» sem maneira de lá pôr nada, o hóspede que o cartão
 * nunca mostrou, a urgente ordenada em último e o quarto de outra empresa que
 * o `exists:hotel_rooms,id` deixava passar — mais as regras que já lá estavam
 * e não podem perder-se ao mudar de ecrã.
 */
class LimpezaDoHotelTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/hotel/limpeza';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');
    }

    /* ─── Gerar o dia ─────────────────────────────────────────────────── */

    /**
     * QUEM SAI HOJE DEIXA LIMPEZA DE SAÍDA; QUEM FICA, DE ESTADIA.
     *
     * É o botão que a governanta carrega de manhã, e é ele que evita abrir
     * vinte tarefas à mão.
     */
    public function test_gerar_abre_as_tarefas_das_saidas_e_das_estadas(): void
    {
        $this->comPermissoes('hotel.housekeeping.view', 'hotel.housekeeping.manage');

        $tipo = $this->tipo();
        $sai = $this->quarto($tipo);
        $fica = $this->quarto($tipo);

        $this->reserva(['room_id' => $sai->id, 'status' => 'checked_in', 'check_out_date' => today()]);
        $this->reserva(['room_id' => $fica->id, 'status' => 'checked_in', 'check_out_date' => today()->addDays(3)]);

        $this->postJson(self::API . '/gerar')->assertOk()->assertJsonPath('quantas', 2);

        $this->assertSame('checkout_clean', HousekeepingTask::where('room_id', $sai->id)->value('task_type'));
        $this->assertSame('high', HousekeepingTask::where('room_id', $sai->id)->value('priority'));
        $this->assertSame('stay_clean', HousekeepingTask::where('room_id', $fica->id)->value('task_type'));

        // QUEM SAI DEIXA O QUARTO SUJO — é isso que o tira dos disponíveis.
        $this->assertSame('dirty', $sai->fresh()->housekeeping_status);
    }

    /** Carregar duas vezes não abre a mesma tarefa duas vezes. */
    public function test_gerar_duas_vezes_nao_duplica(): void
    {
        $this->comPermissoes('hotel.housekeeping.view', 'hotel.housekeeping.manage');

        $quarto = $this->quarto($this->tipo());
        $this->reserva(['room_id' => $quarto->id, 'status' => 'checked_in', 'check_out_date' => today()]);

        $this->postJson(self::API . '/gerar')->assertOk()->assertJsonPath('quantas', 1);
        $this->postJson(self::API . '/gerar')->assertOk()->assertJsonPath('quantas', 0);

        $this->assertSame(1, HousekeepingTask::count());
    }

    /** Uma reserva por confirmar não gera limpeza nenhuma: ninguém lá dormiu. */
    public function test_gerar_ignora_quem_nao_deu_entrada(): void
    {
        $this->comPermissoes('hotel.housekeeping.view', 'hotel.housekeeping.manage');

        $quarto = $this->quarto($this->tipo());
        $this->reserva(['room_id' => $quarto->id, 'status' => 'confirmed', 'check_out_date' => today()]);

        $this->postJson(self::API . '/gerar')->assertOk()->assertJsonPath('quantas', 0);
    }

    /* ─── A fila ──────────────────────────────────────────────────────── */

    /**
     * A URGENTE VEM PRIMEIRO.
     *
     * A ordenação de sempre era `orderBy('priority')` — alfabética sobre a
     * chave, «high, low, normal, urgent» — e punha a urgente em último no ecrã
     * que existe justamente para a pôr à frente.
     */
    public function test_a_urgente_vem_primeiro(): void
    {
        $this->comPermissoes('hotel.housekeeping.view');

        $tipo = $this->tipo();

        $this->tarefa(['room_id' => $this->quarto($tipo)->id, 'priority' => 'low']);
        $this->tarefa(['room_id' => $this->quarto($tipo)->id, 'priority' => 'normal']);
        $this->tarefa(['room_id' => $this->quarto($tipo)->id, 'priority' => 'urgent']);
        $this->tarefa(['room_id' => $this->quarto($tipo)->id, 'priority' => 'high']);

        $r = $this->getJson(self::API)->assertOk();

        $this->assertSame(
            ['urgent', 'high', 'normal', 'low'],
            array_column($r->json('tarefas'), 'prioridade')
        );
    }

    /**
     * O NOME DO HÓSPEDE VEM DA FICHA CERTA.
     *
     * O cartão do quadro lia `reservation->guest` — a ficha antiga
     * (`hotel_guests`), que está vazia — e nunca mostrou nome nenhum, enquanto
     * o modal ao lado lia `reservation->client` e mostrava. Duas leituras da
     * mesma coisa, uma delas sempre em branco.
     */
    public function test_o_cartao_mostra_o_hospede(): void
    {
        $this->comPermissoes('hotel.housekeeping.view');

        $cliente = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Joana Kiala']);
        $quarto = $this->quarto($this->tipo());

        $reserva = $this->reserva([
            'room_id' => $quarto->id, 'client_id' => $cliente->id, 'status' => 'checked_in',
        ]);

        $this->tarefa(['room_id' => $quarto->id, 'reservation_id' => $reserva->id]);

        $this->getJson(self::API)->assertOk()->assertJsonPath('tarefas.0.hospede', 'Joana Kiala');
    }

    /**
     * OS NÚMEROS SÃO OS DO DIA INTEIRO, e não os do filtro.
     *
     * Uma contagem que encolhe quando se filtra por pessoa deixa de dizer
     * quanto falta limpar na casa, que é a pergunta que os cartões respondem.
     */
    public function test_os_numeros_nao_encolhem_com_o_filtro(): void
    {
        $this->comPermissoes('hotel.housekeeping.view');

        $tipo = $this->tipo();
        $quem = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->tarefa(['room_id' => $this->quarto($tipo)->id, 'assigned_to' => $quem->id]);
        $this->tarefa(['room_id' => $this->quarto($tipo)->id]);
        $this->tarefa(['room_id' => $this->quarto($tipo)->id]);

        $r = $this->getJson(self::API . '?responsavel=' . $quem->id)->assertOk();

        $this->assertCount(1, $r->json('tarefas'), 'a lista segue o filtro');
        $this->assertSame(3, $r->json('resumo.pendentes'), 'os cartões contam a casa toda');
    }

    /**
     * ATRASADAS SÃO DE TODOS OS DIAS.
     *
     * Uma tarefa de anteontem por acabar é justamente o que se quer ver — e no
     * dia de hoje ela não aparece.
     */
    public function test_as_atrasadas_sao_de_dias_anteriores(): void
    {
        $this->comPermissoes('hotel.housekeeping.view');

        $tipo = $this->tipo();

        $this->tarefa(['room_id' => $this->quarto($tipo)->id, 'scheduled_date' => today()->subDays(2)]);
        $this->tarefa([
            'room_id' => $this->quarto($tipo)->id,
            'scheduled_date' => today()->subDays(3), 'status' => 'verified',
        ]);
        $this->tarefa(['room_id' => $this->quarto($tipo)->id]);

        $r = $this->getJson(self::API)->assertOk();

        $this->assertSame(1, $r->json('resumo.atrasadas'), 'a verificada já não está atrasada');
        $this->assertCount(1, $r->json('tarefas'), 'a lista é a do dia escolhido');
    }

    /**
     * A PLANTA MOSTRA O PINO MESMO COM FILTRO.
     *
     * O quadro segue o filtro; a planta pinta-se pelo estado do quarto, e um
     * filtro por prioridade não pode fazer desaparecer o pino de um quarto que
     * continua sujo.
     */
    public function test_o_filtro_nao_apaga_o_pino_da_planta(): void
    {
        $this->comPermissoes('hotel.housekeeping.view');

        $quarto = $this->quarto($this->tipo(), ['housekeeping_status' => 'dirty']);
        $this->tarefa(['room_id' => $quarto->id, 'priority' => 'low']);

        $r = $this->getJson(self::API . '?prioridade=urgent')->assertOk();

        $this->assertCount(0, $r->json('tarefas'));
        $this->assertNotNull($r->json('quartos.0.tarefa'), 'o quarto continua sujo e com tarefa');
        $this->assertSame('dirty', $r->json('quartos.0.limpeza'));
    }

    /* ─── O ciclo da tarefa ───────────────────────────────────────────── */

    /**
     * COMEÇAR, ACABAR E VERIFICAR MEXEM NO QUARTO.
     *
     * É por isto que não são um `update` de uma coluna: a limpeza e o estado do
     * quarto andam juntos, e quem os separasse ficava com dois sítios a dizer
     * coisas diferentes sobre o mesmo quarto.
     */
    public function test_o_ciclo_da_limpeza_arrasta_o_estado_do_quarto(): void
    {
        $this->comPermissoes('hotel.housekeeping.view', 'hotel.housekeeping.manage');

        $quarto = $this->quarto($this->tipo(), ['housekeeping_status' => 'dirty', 'status' => 'available']);
        $tarefa = $this->tarefa(['room_id' => $quarto->id]);

        $this->postJson(self::API . "/{$tarefa->id}/estado", ['accao' => 'comecar'])->assertOk();

        $this->assertSame('in_progress', $quarto->fresh()->housekeeping_status);
        $this->assertSame('cleaning', $quarto->fresh()->status);

        $this->postJson(self::API . "/{$tarefa->id}/estado", ['accao' => 'acabar'])->assertOk();

        $this->assertSame('clean', $quarto->fresh()->housekeeping_status);
        $this->assertSame('available', $quarto->fresh()->status);

        $r = $this->postJson(self::API . "/{$tarefa->id}/estado", ['accao' => 'verificar'])->assertOk();

        $this->assertSame('verified', $tarefa->fresh()->status);
        $this->assertSame($this->user->id, $tarefa->fresh()->verified_by);
        $this->assertSame($this->user->name, $r->json('data.verificada_por'));
    }

    /**
     * MARCAR UM PROBLEMA — que era a coluna que nunca se enchia.
     *
     * O quadro tinha a coluna «Com Problemas» e NENHUMA maneira de lá pôr uma
     * tarefa: o `reportIssue()` do modelo existia e nada o chamava. Marcar um
     * problema deixa também o quarto fora de serviço, que é o que impede a
     * recepção de o vender.
     */
    public function test_marcar_um_problema_tira_o_quarto_de_servico(): void
    {
        $this->comPermissoes('hotel.housekeeping.view', 'hotel.housekeeping.manage');

        $quarto = $this->quarto($this->tipo());
        $tarefa = $this->tarefa(['room_id' => $quarto->id]);

        $this->postJson(self::API . "/{$tarefa->id}/estado", [
            'accao' => 'problema', 'problema' => 'Chuveiro partido.',
        ])->assertOk()->assertJsonPath('data.estado', 'issue');

        $this->assertSame('Chuveiro partido.', $tarefa->fresh()->issues);
        $this->assertSame('out_of_order', $quarto->fresh()->housekeeping_status);
    }

    /** Marcar um ponto da lista de verificação, e desmarcá-lo. */
    public function test_marcar_e_desmarcar_um_ponto_da_lista(): void
    {
        $this->comPermissoes('hotel.housekeeping.view', 'hotel.housekeeping.manage');

        $tarefa = $this->tarefa(['room_id' => $this->quarto($this->tipo())->id, 'task_type' => 'stay_clean']);

        // A lista sai do tipo escolhido — é o `creating` do modelo.
        $this->assertCount(6, $tarefa->checklist);

        $r = $this->postJson(self::API . "/{$tarefa->id}/ponto", ['indice' => 0])->assertOk();

        $this->assertTrue($r->json('data.lista.0.feito'));
        $this->assertSame(1, $r->json('data.feitos'));
        $this->assertSame(17, $r->json('data.progresso'));

        $r = $this->postJson(self::API . "/{$tarefa->id}/ponto", ['indice' => 0])->assertOk();

        $this->assertFalse($r->json('data.lista.0.feito'));
        $this->assertSame(0, $r->json('data.progresso'));
    }

    /**
     * UMA TAREFA VERIFICADA NÃO SE MEXE.
     *
     * O ecrã desactivava as caixas, mas a acção do servidor aceitava na mesma —
     * e quem soubesse o pedido reescrevia uma lista já dada por boa.
     */
    public function test_uma_tarefa_verificada_nao_se_altera(): void
    {
        $this->comPermissoes('hotel.housekeeping.view', 'hotel.housekeeping.manage');

        $tarefa = $this->tarefa(['room_id' => $this->quarto($this->tipo())->id, 'status' => 'verified']);

        $this->postJson(self::API . "/{$tarefa->id}/ponto", ['indice' => 0])->assertStatus(422);

        $this->assertFalse($tarefa->fresh()->checklist[0]['done']);
    }

    /* ─── Gravar e atribuir ───────────────────────────────────────────── */

    /** Abrir uma tarefa à mão grava com todos os campos do formulário. */
    public function test_abrir_uma_tarefa_grava_com_todos_os_campos(): void
    {
        $this->comPermissoes('hotel.housekeeping.view', 'hotel.housekeeping.manage');

        $quarto = $this->quarto($this->tipo(), ['housekeeping_status' => 'clean']);
        $quem = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson(self::API, [
            'room_id' => $quarto->id,
            'task_type' => 'deep_clean',
            'priority' => 'urgent',
            'assigned_to' => $quem->id,
            'scheduled_date' => today()->toDateString(),
            'scheduled_time' => '09:30',
            'estimated_duration' => 90,
            'notes' => 'Depois da pintura.',
        ])->assertCreated();

        $t = HousekeepingTask::first();

        $this->assertSame('deep_clean', $t->task_type);
        $this->assertSame('urgent', $t->priority);
        $this->assertSame($quem->id, $t->assigned_to);
        $this->assertSame(90, $t->estimated_duration);
        $this->assertSame('Depois da pintura.', $t->notes);
        $this->assertCount(7, $t->checklist, 'a lista da limpeza profunda');

        // Abrir uma tarefa à mão marca o quarto como sujo: é isso que ela quer
        // dizer.
        $this->assertSame('dirty', $quarto->fresh()->housekeeping_status);
    }

    /** Atribuir e depois tirar o responsável. */
    public function test_atribuir_e_tirar_o_responsavel(): void
    {
        $this->comPermissoes('hotel.housekeeping.view', 'hotel.housekeeping.manage');

        $tarefa = $this->tarefa(['room_id' => $this->quarto($this->tipo())->id]);
        $quem = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson(self::API . "/{$tarefa->id}/atribuir", ['pessoa' => $quem->id])
            ->assertOk()->assertJsonPath('data.responsavel', $quem->name);

        $this->postJson(self::API . "/{$tarefa->id}/atribuir", ['pessoa' => ''])
            ->assertOk()->assertJsonPath('data.responsavel', null);
    }

    /* ─── As guardas ──────────────────────────────────────────────────── */

    /**
     * O QUARTO TEM DE SER DESTA CASA.
     *
     * O ecrã de sempre validava `exists:hotel_rooms,id` — a tabela inteira, sem
     * empresa nenhuma — e o id vinha do browser: abrir uma tarefa sobre o
     * quarto de outro hotel era escrever um número diferente.
     */
    public function test_nao_se_abre_tarefa_no_quarto_de_outra_empresa(): void
    {
        $this->comPermissoes('hotel.housekeeping.view', 'hotel.housekeeping.manage');

        $outra = Tenant::create([
            'name' => 'Hotel do Lado', 'email' => uniqid() . '@exemplo.ao',
            'nif' => (string) random_int(500000000, 599999999), 'is_active' => true,
        ]);

        $tipoAlheio = RoomType::create([
            'tenant_id' => $outra->id, 'name' => 'Duplo', 'code' => 'D-' . substr(uniqid(), -4),
            'base_price' => 20000, 'capacity' => 2, 'extra_bed_capacity' => 0, 'extra_bed_price' => 0,
        ]);

        $alheio = Room::create([
            'tenant_id' => $outra->id, 'room_type_id' => $tipoAlheio->id, 'number' => '901',
        ]);

        $this->postJson(self::API, [
            'room_id' => $alheio->id, 'task_type' => 'stay_clean', 'priority' => 'normal',
            'scheduled_date' => today()->toDateString(),
        ])->assertStatus(422);

        $this->assertSame(0, HousekeepingTask::withoutGlobalScopes()->count());
    }

    /** Ver a limpeza não é geri-la: sem «manage» não se mexe em nada. */
    public function test_quem_so_ve_nao_mexe(): void
    {
        $this->comPermissoes('hotel.housekeeping.view');

        $tarefa = $this->tarefa(['room_id' => $this->quarto($this->tipo())->id]);

        $this->getJson(self::API)->assertOk();
        $this->getJson(self::API . '/opcoes')->assertOk()->assertJsonPath('permissoes.pode_gerir', false);

        $this->postJson(self::API . '/gerar')->assertForbidden();
        $this->postJson(self::API . "/{$tarefa->id}/estado", ['accao' => 'comecar'])->assertForbidden();
        $this->postJson(self::API . "/{$tarefa->id}/ponto", ['indice' => 0])->assertForbidden();
        $this->deleteJson(self::API . "/{$tarefa->id}")->assertForbidden();

        $this->assertSame('pending', $tarefa->fresh()->status);
    }

    /** Sem permissão nenhuma não se vê a limpeza. */
    public function test_sem_permissao_nao_se_ve(): void
    {
        $this->getJson(self::API)->assertForbidden();
        $this->getJson(self::API . '/opcoes')->assertForbidden();
    }

    /* ─── Fixtures ────────────────────────────────────────────────────── */

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
            'floor' => '1',
            'status' => 'available',
            'housekeeping_status' => 'clean',
            'is_active' => true,
        ], $campos));
    }

    private function reserva(array $campos = []): Reservation
    {
        return Reservation::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -6),
            'room_type_id' => $this->tipo()->id,
            'check_in_date' => today(),
            'check_out_date' => today()->addDays(2),
            'room_rate' => 28000,
            'nights' => 2,
            'status' => 'confirmed',
            'source' => 'direct',
            'total' => 56000,
        ], $campos));
    }

    private function tarefa(array $campos = []): HousekeepingTask
    {
        return HousekeepingTask::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'task_type' => 'checkout_clean',
            'priority' => 'normal',
            'status' => 'pending',
            'scheduled_date' => today(),
        ], $campos));
    }
}
