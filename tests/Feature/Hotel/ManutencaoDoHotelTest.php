<?php

namespace Tests\Feature\Hotel;

use App\Models\Hotel\MaintenanceOrder;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Hotel\Staff;
use Tests\TenantTestCase;

/**
 * A MANUTENÇÃO DO HOTEL — e o ecrã que nunca conseguiu gravar uma ordem.
 *
 * O modelo declarava oito colunas que a tabela não tem (`type`,
 * `estimated_cost`, `actual_cost`, `estimated_time`, `actual_time`,
 * `resolution_notes`, `images`) e o formulário mandava-as todas. O insert saía
 * com elas e o MySQL respondia:
 *
 *     SQLSTATE[42S22]: Column not found: 1054 Unknown column 'type'
 *
 * Não era um caso de canto: era o botão de gravar, em qualquer ordem, sempre.
 * O primeiro ensaio deste ficheiro é a trave que impede isso de voltar.
 */
class ManutencaoDoHotelTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/hotel/manutencao';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');
    }

    /* ─── Gravar ──────────────────────────────────────────────────────── */

    /**
     * ABRIR UMA ORDEM GRAVA — com todos os campos que o formulário pede.
     */
    public function test_abrir_uma_ordem_grava_com_todos_os_campos(): void
    {
        $this->comPermissoes('hotel.maintenance.view', 'hotel.maintenance.create');

        $quarto = $this->quarto();

        $this->postJson(self::API, [
            'title' => 'Torneira do 203 a pingar',
            'type' => 'corrective',
            'priority' => 'high',
            'category' => 'plumbing',
            'room_id' => $quarto->id,
            'location' => 'Casa de banho',
            'description' => 'Pinga desde ontem.',
            'estimated_cost' => 12000,
            'estimated_time' => 45,
            'scheduled_date' => now()->addDay()->toDateString(),
        ])->assertCreated();

        $o = MaintenanceOrder::first();

        $this->assertSame('corrective', $o->type);
        $this->assertEquals(12000, $o->estimated_cost);
        $this->assertSame(45, $o->estimated_time);
        $this->assertSame('pending', $o->status);
        // O número gera-se sozinho — é o `creating` do modelo.
        $this->assertStringStartsWith('MNT', $o->order_number);
    }

    /** Uma ordem numa zona comum não tem quarto, e grava na mesma. */
    public function test_uma_ordem_sem_quarto_grava(): void
    {
        $this->comPermissoes('hotel.maintenance.view', 'hotel.maintenance.create');

        $this->postJson(self::API, [
            'title' => 'Lâmpada do corredor',
            'type' => 'corrective', 'priority' => 'low', 'category' => 'electrical',
            'room_id' => '', 'assigned_to' => '', 'scheduled_date' => '',
            'estimated_cost' => '', 'estimated_time' => '',
        ])->assertCreated();

        $o = MaintenanceOrder::first();

        $this->assertNull($o->room_id);
        $this->assertNull($o->scheduled_date);
        $this->assertNull($o->estimated_cost);
    }

    /** Um quarto de outra empresa não entra numa ordem desta. */
    public function test_o_quarto_tem_de_ser_desta_empresa(): void
    {
        $this->comPermissoes('hotel.maintenance.view', 'hotel.maintenance.create');

        $outra = \App\Models\Tenant::create([
            'name' => 'Hotel do Lado', 'slug' => 'lado-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'lado' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        $tipo = RoomType::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Duplo', 'base_price' => 1000,
            'capacity' => 2, 'extra_bed_capacity' => 0, 'extra_bed_price' => 0,
        ]);

        $alheio = Room::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'room_type_id' => $tipo->id, 'number' => '999',
        ]);

        $this->postJson(self::API, [
            'title' => 'Alheia', 'type' => 'corrective', 'priority' => 'low',
            'category' => 'other', 'room_id' => $alheio->id,
        ])->assertStatus(422);
    }

    /* ─── Estados ─────────────────────────────────────────────────────── */

    /**
     * COMEÇAR MARCA A HORA, CONCLUIR MARCA A OUTRA — e é daí que sai o tempo
     * real do arranjo.
     */
    public function test_comecar_e_concluir_marcam_as_horas(): void
    {
        $this->comPermissoes('hotel.maintenance.view', 'hotel.maintenance.edit');

        $o = $this->ordem();

        $this->postJson(self::API . "/{$o->id}/estado", ['estado' => 'in_progress'])->assertOk();
        $this->assertNotNull($o->fresh()->started_at);
        $this->assertNull($o->fresh()->completed_at);

        $this->postJson(self::API . "/{$o->id}/estado", ['estado' => 'completed'])->assertOk();
        $this->assertNotNull($o->fresh()->completed_at);
    }

    /**
     * FECHAR A ORDEM ESCREVE O QUE SE FEZ.
     *
     * O ecrã de sempre gravava-o em `resolution_notes` e `actual_cost`, que
     * não são colunas: a ordem fechava sempre sem a resolução escrita — e
     * ninguém dava por isso porque também não a mostrava.
     */
    public function test_concluir_escreve_a_resolucao_e_o_custo(): void
    {
        $this->comPermissoes('hotel.maintenance.view', 'hotel.maintenance.edit');

        $o = $this->ordem();

        $this->postJson(self::API . "/{$o->id}/estado", [
            'estado' => 'completed',
            'resolucao' => 'Substituída a válvula.',
            'custo' => 8500,
        ])->assertOk();

        $o->refresh();

        $this->assertSame('Substituída a válvula.', $o->resolution);
        $this->assertEquals(8500, $o->cost);
    }

    /** O tempo gasto sai das duas datas, e não de uma coluna à parte. */
    public function test_o_tempo_gasto_sai_das_duas_datas(): void
    {
        $this->comPermissoes('hotel.maintenance.view', 'hotel.maintenance.edit');

        $o = $this->ordem(['started_at' => now()->subMinutes(90)]);

        $r = $this->postJson(self::API . "/{$o->id}/estado", ['estado' => 'completed'])->assertOk();

        $this->assertEqualsWithDelta(90, $r->json('data.minutos_gastos'), 1);
    }

    public function test_um_estado_inventado_e_recusado(): void
    {
        $this->comPermissoes('hotel.maintenance.view', 'hotel.maintenance.edit');

        $this->postJson(self::API . "/{$this->ordem()->id}/estado", ['estado' => 'inventado'])->assertStatus(422);
    }

    /* ─── Atribuir-me ─────────────────────────────────────────────────── */

    /**
     * «ATRIBUIR-ME» SÓ FUNCIONA A QUEM TEM FICHA DE PESSOAL — e diz porquê a
     * quem não tem. O ecrã de sempre mostrava o botão a toda a gente, e quem
     * não tinha carregava e não acontecia nada.
     */
    public function test_atribuir_me_precisa_de_ficha_de_pessoal(): void
    {
        $this->comPermissoes('hotel.maintenance.view', 'hotel.maintenance.edit');

        $o = $this->ordem();

        $this->postJson(self::API . "/{$o->id}/atribuir-me")->assertStatus(422);
        $this->getJson(self::API . '/opcoes')->assertOk()->assertJsonPath('tenho_ficha', false);

        Staff::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'name' => 'Eu Próprio', 'position' => 'maintenance', 'department' => 'maintenance',
        ]);

        $this->postJson(self::API . "/{$o->id}/atribuir-me")->assertOk();
        $this->assertNotNull($o->fresh()->assigned_to);
        $this->getJson(self::API . '/opcoes')->assertOk()->assertJsonPath('tenho_ficha', true);
    }

    /* ─── A lista e o quadro ──────────────────────────────────────────── */

    /** O urgente vem primeiro: uma fuga de água não fica abaixo de uma lâmpada. */
    public function test_o_urgente_vem_primeiro(): void
    {
        $this->comPermissoes('hotel.maintenance.view');

        $this->ordem(['title' => 'Lâmpada', 'priority' => 'low']);
        $this->ordem(['title' => 'Fuga de água', 'priority' => 'urgent']);

        $nomes = collect($this->getJson(self::API)->assertOk()->json('data'))->pluck('titulo');

        $this->assertSame('Fuga de água', $nomes->first());
    }

    /** O resumo conta a casa e não a página filtrada. */
    public function test_o_resumo_conta_a_casa(): void
    {
        $this->comPermissoes('hotel.maintenance.view');

        $this->ordem(['status' => 'pending']);
        $this->ordem(['status' => 'in_progress']);
        $this->ordem(['status' => 'completed']);
        $this->ordem(['status' => 'pending', 'priority' => 'urgent']);

        $r = $this->getJson(self::API . '?estado=completed')->assertOk();

        $this->assertCount(1, $r->json('data'));
        $this->assertSame(4, $r->json('resumo.total'));
        $this->assertSame(2, $r->json('resumo.pendentes'));
        $this->assertSame(1, $r->json('resumo.em_curso'));
        // As urgentes contam-se só entre as que estão POR FECHAR.
        $this->assertSame(1, $r->json('resumo.urgentes'));
    }

    /** O quadro traz as quatro colunas, e as concluídas vêm limitadas. */
    public function test_o_quadro_traz_as_quatro_colunas(): void
    {
        $this->comPermissoes('hotel.maintenance.view');

        $this->ordem(['status' => 'pending']);
        $this->ordem(['status' => 'waiting_parts']);

        $colunas = collect($this->getJson(self::API . '/quadro')->assertOk()->json('colunas'));

        $this->assertSame(['pending', 'in_progress', 'waiting_parts', 'completed'], $colunas->pluck('estado')->all());
        $this->assertCount(1, $colunas->firstWhere('estado', 'pending')['ordens']);
        $this->assertCount(0, $colunas->firstWhere('estado', 'in_progress')['ordens']);
    }

    /* ─── As permissões ───────────────────────────────────────────────── */

    public function test_cada_verbo_pede_a_sua_permissao(): void
    {
        $this->comPermissoes('hotel.maintenance.view');

        $o = $this->ordem();

        $this->getJson(self::API)->assertOk();
        $this->getJson(self::API . '/opcoes')->assertOk()
            ->assertJsonPath('permissoes.pode_criar', false);

        $this->postJson(self::API, ['title' => 'x', 'type' => 'corrective', 'priority' => 'low', 'category' => 'other'])
            ->assertForbidden();
        $this->putJson(self::API . "/{$o->id}", [])->assertForbidden();
        $this->postJson(self::API . "/{$o->id}/estado", ['estado' => 'completed'])->assertForbidden();
        $this->deleteJson(self::API . "/{$o->id}")->assertForbidden();
    }

    public function test_sem_permissao_de_ver_nada_abre(): void
    {
        $this->getJson(self::API)->assertForbidden();
        $this->getJson(self::API . '/quadro')->assertForbidden();
        $this->getJson(self::API . '/opcoes')->assertForbidden();
    }

    /** E a morada abre com o ecrã React montado. */
    public function test_a_morada_abre_em_react(): void
    {
        $this->comPermissoes('hotel.maintenance.view');

        $this->get('/hotel/maintenance')->assertOk()
            ->assertSee('data-ecra="hotel/manutencao"', false);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function quarto(): Room
    {
        $tipo = RoomType::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Duplo',
            'code' => 'D-' . substr(uniqid(), -4), 'base_price' => 20000,
            'capacity' => 2, 'extra_bed_capacity' => 0, 'extra_bed_price' => 0,
        ]);

        return Room::create([
            'tenant_id' => $this->tenant->id, 'room_type_id' => $tipo->id,
            'number' => (string) random_int(100, 999),
        ]);
    }

    private function ordem(array $campos = []): MaintenanceOrder
    {
        return MaintenanceOrder::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'title' => 'Uma avaria',
            'type' => 'corrective',
            'priority' => 'normal',
            'category' => 'other',
            'status' => 'pending',
        ], $campos));
    }
}
