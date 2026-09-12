<?php

namespace Tests\Feature\Eventos;

use App\Models\Client;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Events\Event;
use App\Models\Events\EventType;
use App\Models\Events\Technician;
use App\Models\Events\Venue;
use Tests\TenantTestCase;

/**
 * OS ECRÃS DOS EVENTOS EM REACT — as portas e as guardas.
 *
 * O QUE ESTE ENSAIO PRENDE:
 *
 *  · O ERRO 500 DO NÚMERO DE SÉRIE. Gravar um equipamento com número de série
 *    rebentava: a regra dizia `unique:equipment,serial_number` e a tabela
 *    chama-se `events_equipments_manager`. Quem tem material caro escreve
 *    sempre o número de série — ou seja, toda a gente.
 *
 *  · AS PERMISSÕES DE ESCRITA. Existiam DOZE permissões — seis pares
 *    `view`/`manage` — e as rotas só verificavam as de ver. Quem chegasse à
 *    página apagava, criava e emprestava tudo o que ela mostrava; o `manage`
 *    nunca era perguntado a ninguém.
 *
 *  · A CAPACIDADE DO LOCAL. Marcava-se um evento de 500 pessoas numa sala de 80
 *    e ninguém dizia nada — descobria-se no dia, com as pessoas à porta.
 */
class EcrasDosEventosEmReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/eventos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('eventos');
    }

    /** As páginas e o ecrã que cada uma monta. */
    public static function paginas(): array
    {
        return [
            'painel' => ['/events/dashboard', 'eventos/painel', 'events.dashboard.view'],
            'agenda' => ['/events/calendar', 'eventos/agenda', 'events.calendar.view'],
            'relatórios' => ['/events/reports', 'eventos/relatorios', 'events.reports.view'],
            'equipamentos' => ['/events/equipment', 'eventos/equipamentos', 'events.equipment.view'],
            'painel dos equipamentos' => ['/events/equipment/dashboard', 'eventos/equipamentos-painel', 'events.equipment.view'],
            'conjuntos' => ['/events/equipment/sets', 'eventos/equipamentos', 'events.equipment.view'],
            'categorias' => ['/events/equipment/categories', 'eventos/equipamentos', 'events.equipment.view'],
            'locais' => ['/events/venues', 'eventos/locais', 'events.venues.view'],
            'tipos' => ['/events/types', 'eventos/tipos', 'events.types.view'],
            'técnicos' => ['/events/technicians', 'eventos/tecnicos', 'events.technicians.view'],
        ];
    }

    /**
     * @dataProvider paginas
     */
    public function test_cada_pagina_monta_o_seu_ecra(string $morada, string $ecra, string $permissao): void
    {
        $this->get($morada)->assertForbidden();

        $this->comPermissoes($permissao);

        $this->get($morada)->assertOk()->assertSee($ecra, false);
    }

    public function test_os_ecras_pedidos_existem_no_registo(): void
    {
        $registo = file_get_contents(resource_path('js/ecras/registo.ts'));

        foreach (self::paginas() as [$morada, $ecra]) {
            $this->assertStringContainsString("'{$ecra}':", $registo,
                "A página {$morada} pede o ecrã {$ecra}, que não está no registo — a página montaria um buraco branco.");
        }
    }

    public function test_os_ficheiros_dos_ecras_existem(): void
    {
        foreach ([
            'Painel', 'Agenda', 'Equipamentos', 'EquipamentosPainel',
            'Locais', 'Tipos', 'Tecnicos', 'Relatorios',
        ] as $f) {
            $this->assertFileExists(resource_path("js/ecras/eventos/{$f}.tsx"));
        }
    }

    /** O Livewire dos eventos foi-se todo — não sobrou página pública nenhuma. */
    public function test_nao_sobrou_livewire_nos_eventos(): void
    {
        $this->assertDirectoryDoesNotExist(app_path('Livewire/Events'),
            'os eventos não têm páginas sem sessão: passaram todas a React');
    }

    /**
     * A ROTA DE DIAGNÓSTICO DO QR DESAPARECEU.
     *
     * Era um `/events/equipment/test-qrcode` que, ao falhar, devolvia a
     * mensagem da excepção, o ficheiro e a linha a QUALQUER utilizador
     * autenticado — um diagnóstico de programador exposto em produção.
     */
    public function test_a_rota_de_diagnostico_do_qr_nao_existe(): void
    {
        $this->comPermissoes('events.equipment.view');

        $this->get('/events/equipment/test-qrcode')->assertNotFound();
    }

    /* ─── O painel ─────────────────────────────────────────────────────── */

    public function test_o_painel_exige_a_sua_permissao(): void
    {
        $this->getJson(self::RAIZ.'/painel')->assertForbidden();

        $this->comPermissoes('events.dashboard.view');

        $this->getJson(self::RAIZ.'/painel')
            ->assertOk()
            ->assertJsonStructure([
                'resumo' => ['do_mes', 'confirmados', 'a_decorrer', 'valor_do_mes', 'equipamento_em_uso'],
                'a_seguir', 'em_atraso', 'por_mes', 'por_estado', 'por_fase', 'por_tipo',
            ]);
    }

    /** Os doze meses do gráfico estão lá todos — os vazios a zero. */
    public function test_o_painel_devolve_os_doze_meses(): void
    {
        $this->comPermissoes('events.dashboard.view');

        $dados = $this->getJson(self::RAIZ.'/painel')->assertOk()->json();

        $this->assertCount(12, $dados['por_mes']['etiquetas']);
        $this->assertCount(12, $dados['por_mes']['valores']);
    }

    /* ─── A agenda ─────────────────────────────────────────────────────── */

    private function tipo(string $nome = 'Conferência'): EventType
    {
        return EventType::create([
            'tenant_id' => $this->tenant->id, 'name' => $nome,
            'icon' => '🎤', 'color' => '#8b5cf6', 'order' => 0, 'is_active' => true,
        ]);
    }

    private function local(string $nome = 'Sala', ?int $capacidade = null): Venue
    {
        return Venue::create([
            'tenant_id' => $this->tenant->id, 'name' => $nome,
            'capacity' => $capacidade, 'is_active' => true,
        ]);
    }

    private function marcar(array $extra = []): int
    {
        $dados = array_merge([
            'name' => 'Evento dos ensaios',
            'start_date' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_date' => now()->addDays(10)->addHours(6)->format('Y-m-d H:i:s'),
            'type_id' => $this->tipo()->id,
        ], $extra);

        $this->postJson(self::RAIZ.'/agenda', $dados)->assertCreated();

        return (int) Event::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail()->id;
    }

    public function test_a_agenda_separa_ver_de_gerir(): void
    {
        $this->comPermissoes('events.calendar.view');

        $this->getJson(self::RAIZ.'/agenda')->assertOk();
        $this->getJson(self::RAIZ.'/agenda/calendario')->assertOk();

        $this->postJson(self::RAIZ.'/agenda', [
            'name' => 'Tentativa', 'start_date' => now()->addDay()->toDateTimeString(),
            'end_date' => now()->addDays(2)->toDateTimeString(), 'type_id' => $this->tipo()->id,
        ])->assertForbidden();
    }

    /**
     * O EVENTO NASCE COM O CHECKLIST DA PRIMEIRA FASE.
     *
     * Sem ele, a guarda do «avançar de fase» não guardava nada: não havia
     * tarefas obrigatórias por cumprir, e a fase passava sempre.
     */
    public function test_o_evento_nasce_com_o_checklist_do_planeamento(): void
    {
        $this->comPermissoes('events.calendar.view', 'events.calendar.manage');

        $id = $this->marcar();

        $evento = Event::withoutGlobalScopes()->findOrFail($id);

        $this->assertSame('orcamento', $evento->status);
        $this->assertSame('planejamento', $evento->phase);
        $this->assertGreaterThan(0, $evento->checklists()->count());
        $this->assertGreaterThan(0, $evento->checklists()->where('is_required', true)->count());
        $this->assertNotEmpty($evento->event_number);
    }

    /**
     * O LOCAL TEM CAPACIDADE, E ELA VALE.
     *
     * Marcava-se um evento de 500 pessoas numa sala de 80 e ninguém dizia nada.
     */
    public function test_o_evento_nao_cabe_num_local_pequeno(): void
    {
        $this->comPermissoes('events.calendar.view', 'events.calendar.manage');

        $local = $this->local('Salinha', 80);

        $this->postJson(self::RAIZ.'/agenda', [
            'name' => 'Grande demais',
            'start_date' => now()->addDays(5)->toDateTimeString(),
            'end_date' => now()->addDays(5)->addHours(4)->toDateTimeString(),
            'type_id' => $this->tipo()->id,
            'venue_id' => $local->id,
            'expected_attendees' => 500,
        ])->assertStatus(422);

        $this->assertSame(0, Event::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /** E cabe quando cabe. */
    public function test_o_evento_entra_quando_cabe(): void
    {
        $this->comPermissoes('events.calendar.view', 'events.calendar.manage');

        $this->marcar(['venue_id' => $this->local('Salão', 500)->id, 'expected_attendees' => 300]);

        $this->assertSame(1, Event::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * O ESTADO PASSOU A TER TABELA DE TRANSIÇÕES.
     *
     * O ecrã antigo mostrava os estados todos num `<select>`: dava para pôr um
     * evento CONCLUÍDO de volta em ORÇAMENTO — e com isso apagar a data de
     * conclusão que os relatórios do mês usam.
     */
    public function test_o_percurso_do_estado_anda_para_a_frente(): void
    {
        $this->comPermissoes('events.calendar.view', 'events.calendar.manage');

        $id = $this->marcar();

        foreach (['confirmado', 'em_montagem', 'em_andamento', 'concluido'] as $estado) {
            $this->postJson(self::RAIZ."/agenda/{$id}/estado", ['estado' => $estado])->assertOk();
        }

        $evento = Event::withoutGlobalScopes()->findOrFail($id);

        $this->assertSame('concluido', $evento->status);
        $this->assertNotNull($evento->completed_at);
        $this->assertNotNull($evento->confirmed_at);

        // E de concluído não se volta atrás.
        $this->postJson(self::RAIZ."/agenda/{$id}/estado", ['estado' => 'orcamento'])->assertStatus(422);

        $this->assertNotNull(Event::withoutGlobalScopes()->findOrFail($id)->completed_at);
    }

    /** Um evento cancelado não se conclui nem avança de fase. */
    public function test_um_evento_cancelado_nao_anda(): void
    {
        $this->comPermissoes('events.calendar.view', 'events.calendar.manage');

        $id = $this->marcar();

        $this->postJson(self::RAIZ."/agenda/{$id}/estado", ['estado' => 'cancelado'])->assertOk();
        $this->postJson(self::RAIZ."/agenda/{$id}/estado", ['estado' => 'confirmado'])->assertStatus(422);
        $this->postJson(self::RAIZ."/agenda/{$id}/fase")->assertStatus(422);
    }

    /**
     * A FASE SÓ AVANÇA COM AS TAREFAS OBRIGATÓRIAS FEITAS.
     *
     * Existia no modelo; o que faltava era a resposta dizer que faltam, em vez
     * de o ecrã oferecer o botão e a chamada não fazer nada.
     */
    public function test_a_fase_so_avanca_com_as_tarefas_obrigatorias_feitas(): void
    {
        $this->comPermissoes('events.calendar.view', 'events.calendar.manage');

        $id = $this->marcar();
        $evento = Event::withoutGlobalScopes()->findOrFail($id);

        $this->postJson(self::RAIZ."/agenda/{$id}/fase")->assertStatus(422);

        foreach ($evento->checklists()->where('is_required', true)->get() as $tarefa) {
            $this->postJson(self::RAIZ."/agenda/tarefas/{$tarefa->id}")->assertOk();
        }

        $this->postJson(self::RAIZ."/agenda/{$id}/fase")->assertOk();

        $this->assertSame('pre_producao', Event::withoutGlobalScopes()->findOrFail($id)->phase);
    }

    /** Marcar uma tarefa mexe no progresso do evento. */
    public function test_marcar_uma_tarefa_mexe_no_progresso(): void
    {
        $this->comPermissoes('events.calendar.view', 'events.calendar.manage');

        $id = $this->marcar();
        $tarefa = Event::withoutGlobalScopes()->findOrFail($id)->checklists()->firstOrFail();

        $this->assertSame(0, (int) Event::withoutGlobalScopes()->findOrFail($id)->checklist_progress);

        $this->postJson(self::RAIZ."/agenda/tarefas/{$tarefa->id}")->assertOk()->assertJson(['feita' => true]);

        $this->assertGreaterThan(0, (int) Event::withoutGlobalScopes()->findOrFail($id)->checklist_progress);

        // E desmarcar volta atrás.
        $this->postJson(self::RAIZ."/agenda/tarefas/{$tarefa->id}")->assertOk()->assertJson(['feita' => false]);
    }

    /**
     * UM EVENTO DE TRÊS DIAS APARECE NOS TRÊS.
     *
     * A consulta antiga comparava só a data de início: um evento que começasse
     * a 31 de Janeiro e acabasse a 2 de Fevereiro desaparecia do calendário de
     * Fevereiro — o mês em que estava a acontecer.
     */
    public function test_o_calendario_repete_o_evento_em_todos_os_dias(): void
    {
        $this->comPermissoes('events.calendar.view', 'events.calendar.manage');

        $inicio = now()->startOfMonth()->addDays(9)->setTime(9, 0);

        $this->marcar([
            'start_date' => $inicio->toDateTimeString(),
            'end_date' => $inicio->copy()->addDays(2)->setTime(20, 0)->toDateTimeString(),
        ]);

        $dias = collect($this->getJson(self::RAIZ.'/agenda/calendario?mes='.now()->format('Y-m'))
            ->assertOk()->json('dias'));

        $comEvento = $dias->filter(fn ($d) => count($d['eventos']) > 0);

        $this->assertSame(3, $comEvento->count(), 'um evento de três dias ocupa três dias da grelha');
    }

    /** E a grelha do mês começa e acaba em semanas inteiras. */
    public function test_o_calendario_comeca_e_acaba_em_semanas_inteiras(): void
    {
        $this->comPermissoes('events.calendar.view');

        $dias = $this->getJson(self::RAIZ.'/agenda/calendario?mes='.now()->format('Y-m'))
            ->assertOk()->json('dias');

        $this->assertSame(0, count($dias) % 7, 'a grelha tem de ter semanas inteiras');
        $this->assertTrue($dias[0]['do_mes'] === false || (int) $dias[0]['numero'] === 1);
    }

    /** Um evento concluído não se apaga — leva o historial com ele. */
    public function test_um_evento_concluido_nao_se_apaga(): void
    {
        $this->comPermissoes('events.calendar.view', 'events.calendar.manage');

        $id = $this->marcar();

        foreach (['confirmado', 'em_montagem', 'em_andamento', 'concluido'] as $e) {
            $this->postJson(self::RAIZ."/agenda/{$id}/estado", ['estado' => $e])->assertOk();
        }

        $this->deleteJson(self::RAIZ."/agenda/{$id}")->assertStatus(422);

        $this->assertNotNull(Event::withoutGlobalScopes()->find($id));
    }

    /* ─── Os equipamentos ──────────────────────────────────────────────── */

    private function categoria(string $nome = 'Som'): EquipmentCategory
    {
        return EquipmentCategory::create([
            'tenant_id' => $this->tenant->id, 'name' => $nome,
            'icon' => '🔊', 'color' => '#6366f1', 'sort_order' => 0, 'is_active' => true,
        ]);
    }

    /**
     * O ERRO 500 DO NÚMERO DE SÉRIE.
     *
     * A regra dizia `unique:equipment,serial_number` — uma tabela que NÃO
     * EXISTE — e a gravação morria com «Base table or view not found». Quem
     * escrevia o número de série, que é toda a gente com material caro, não
     * conseguia gravar de todo.
     */
    public function test_o_equipamento_grava_se_com_numero_de_serie(): void
    {
        $this->comPermissoes('events.equipment.view', 'events.equipment.manage');

        $this->postJson(self::RAIZ.'/equipamentos', [
            'name' => 'Mesa de som',
            'category_id' => $this->categoria()->id,
            'serial_number' => 'SN-0001',
            'status' => 'disponivel',
            'current_value' => 250000,
        ])->assertCreated();

        $this->assertSame(1, Equipment::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('serial_number', 'SN-0001')->count());
    }

    /** E o número de série não se repete DENTRO da empresa. */
    public function test_o_numero_de_serie_nao_se_repete_na_empresa(): void
    {
        $this->comPermissoes('events.equipment.view', 'events.equipment.manage');

        $categoria = $this->categoria();

        $this->postJson(self::RAIZ.'/equipamentos', [
            'name' => 'Um', 'category_id' => $categoria->id,
            'serial_number' => 'SN-REP', 'status' => 'disponivel',
        ])->assertCreated();

        $this->postJson(self::RAIZ.'/equipamentos', [
            'name' => 'Outro', 'category_id' => $categoria->id,
            'serial_number' => 'SN-REP', 'status' => 'disponivel',
        ])->assertStatus(422)->assertJsonValidationErrors('serial_number');
    }

    /**
     * MAS O NÚMERO DE SÉRIE É ÚNICO POR EMPRESA, E NÃO NO MUNDO.
     *
     * A regra global impedia a segunda casa de registar o mesmo aparelho — e
     * duas empresas podem ter, e têm, o mesmo modelo.
     */
    public function test_o_mesmo_numero_de_serie_cabe_noutra_empresa(): void
    {
        $this->comPermissoes('events.equipment.view', 'events.equipment.manage');

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        Equipment::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Alheio', 'serial_number' => 'SN-MESMO',
            'status' => 'disponivel',
        ]);

        $this->postJson(self::RAIZ.'/equipamentos', [
            'name' => 'Meu', 'category_id' => $this->categoria()->id,
            'serial_number' => 'SN-MESMO', 'status' => 'disponivel',
        ])->assertCreated();
    }

    public function test_os_equipamentos_separam_ver_de_gerir(): void
    {
        $this->comPermissoes('events.equipment.view');

        $this->getJson(self::RAIZ.'/equipamentos')->assertOk();
        $this->getJson(self::RAIZ.'/equipamentos/painel')->assertOk();

        $this->postJson(self::RAIZ.'/equipamentos', [
            'name' => 'Tentativa', 'category_id' => $this->categoria()->id, 'status' => 'disponivel',
        ])->assertForbidden();

        $equipamento = Equipment::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Coluna', 'status' => 'disponivel',
        ]);

        $this->deleteJson(self::RAIZ."/equipamentos/{$equipamento->id}")->assertForbidden();
        $this->postJson(self::RAIZ."/equipamentos/{$equipamento->id}/emprestar", [])->assertForbidden();
    }

    /**
     * O MESMO APARELHO NÃO SAI DUAS VEZES.
     *
     * Emprestava-se um equipamento já emprestado e o registo anterior era
     * simplesmente reescrito: o primeiro cliente desaparecia da ficha e ninguém
     * sabia com quem o material estava afinal.
     */
    public function test_o_equipamento_emprestado_nao_sai_outra_vez(): void
    {
        $this->comPermissoes('events.equipment.view', 'events.equipment.manage');

        $equipamento = Equipment::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Projector',
            'category_id' => $this->categoria()->id, 'status' => 'disponivel',
        ]);

        $cliente = Client::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Dona Ana',
            'nif' => (string) random_int(500000000, 599999999), 'is_active' => true,
        ]);

        $emprestimo = [
            'para' => 'cliente',
            'borrowed_to_client_id' => $cliente->id,
            'borrow_date' => today()->toDateString(),
            'return_due_date' => today()->addDays(3)->toDateString(),
        ];

        $this->postJson(self::RAIZ."/equipamentos/{$equipamento->id}/emprestar", $emprestimo)->assertOk();

        $this->assertSame('emprestado', $equipamento->fresh()->status);

        $this->postJson(self::RAIZ."/equipamentos/{$equipamento->id}/emprestar", $emprestimo)->assertStatus(422);

        // E devolver liberta-o, contando uma utilização.
        $this->postJson(self::RAIZ."/equipamentos/{$equipamento->id}/devolver")->assertOk();

        $fresco = $equipamento->fresh();

        $this->assertSame('disponivel', $fresco->status);
        $this->assertNull($fresco->borrowed_to_client_id);
        $this->assertSame(1, (int) $fresco->total_uses);
    }

    /** Um técnico da casa não paga aluguer do material da casa. */
    public function test_o_emprestimo_a_um_tecnico_nao_leva_preco(): void
    {
        $this->comPermissoes('events.equipment.view', 'events.equipment.manage');

        $equipamento = Equipment::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cabo',
            'category_id' => $this->categoria()->id, 'status' => 'disponivel',
        ]);

        $tecnico = Technician::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Zé', 'phone' => '923000000',
            'specialties' => ['audio'], 'level' => 'pleno', 'is_active' => true,
        ]);

        $this->postJson(self::RAIZ."/equipamentos/{$equipamento->id}/emprestar", [
            'para' => 'tecnico',
            'borrowed_to_technician_id' => $tecnico->id,
            'borrow_date' => today()->toDateString(),
            'return_due_date' => today()->addDay()->toDateString(),
            'rental_price_per_day' => 5000,
        ])->assertOk();

        $fresco = $equipamento->fresh();

        $this->assertSame($tecnico->id, $fresco->borrowed_to_technician_id);
        $this->assertNull($fresco->borrowed_to_client_id);
        $this->assertNull($fresco->rental_price_per_day, 'um técnico da casa não paga aluguer');
    }

    /** O que está fora de casa não se apaga — perdia-se o rasto. */
    public function test_o_equipamento_emprestado_nao_se_apaga(): void
    {
        $this->comPermissoes('events.equipment.view', 'events.equipment.manage');

        $equipamento = Equipment::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Microfone',
            'category_id' => $this->categoria()->id, 'status' => 'emprestado',
        ]);

        $this->deleteJson(self::RAIZ."/equipamentos/{$equipamento->id}")->assertStatus(422);

        $this->assertNotNull(Equipment::withoutGlobalScopes()->find($equipamento->id));
    }

    /** O conjunto não leva material de outra empresa. */
    public function test_o_conjunto_nao_leva_material_alheio(): void
    {
        $this->comPermissoes('events.equipment.view', 'events.equipment.manage');

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $alheio = Equipment::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Alheio', 'status' => 'disponivel',
        ]);

        $this->postJson(self::RAIZ.'/equipamentos/conjuntos', [
            'name' => 'Kit de som', 'category_id' => $this->categoria()->id,
        ])->assertCreated();

        $conjunto = \App\Models\EquipmentSet::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->postJson(self::RAIZ."/equipamentos/conjuntos/{$conjunto->id}/itens", [
            'equipment_id' => $alheio->id, 'quantity' => 1,
        ])->assertStatus(422);

        $this->assertSame(0, $conjunto->equipments()->count());
    }

    /* ─── Os locais, os tipos e os técnicos ────────────────────────────── */

    public function test_os_locais_separam_ver_de_gerir(): void
    {
        $this->comPermissoes('events.venues.view');

        $this->getJson(self::RAIZ.'/locais')->assertOk();

        $this->postJson(self::RAIZ.'/locais', ['name' => 'Sala'])->assertForbidden();

        $local = $this->local();

        $this->putJson(self::RAIZ."/locais/{$local->id}", ['name' => 'Outra'])->assertForbidden();
        $this->deleteJson(self::RAIZ."/locais/{$local->id}")->assertForbidden();
    }

    /** Um local com eventos não se apaga. */
    public function test_o_local_com_eventos_nao_se_apaga(): void
    {
        $this->comPermissoes('events.venues.view', 'events.venues.manage',
            'events.calendar.view', 'events.calendar.manage');

        $local = $this->local('Com eventos', 500);

        $this->marcar(['venue_id' => $local->id, 'expected_attendees' => 100]);

        $this->deleteJson(self::RAIZ."/locais/{$local->id}")->assertStatus(422);
    }

    /**
     * A PROCURA NÃO ATRAVESSA EMPRESAS.
     *
     * Era `where(nome)->orWhere(cidade)->orWhere(morada)` encostado ao filtro
     * da empresa — e um `or` solto rompe o `and` que está antes: procurar
     * «Luanda» trazia os locais de toda a gente.
     */
    public function test_a_procura_dos_locais_nao_atravessa_empresas(): void
    {
        $this->comPermissoes('events.venues.view');

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        Venue::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Sala Alheia',
            'city' => 'Luanda', 'is_active' => true,
        ]);

        $this->getJson(self::RAIZ.'/locais?procura=Luanda')
            ->assertOk()
            ->assertJsonMissing(['nome' => 'Sala Alheia']);
    }

    public function test_os_tipos_separam_ver_de_gerir(): void
    {
        $this->comPermissoes('events.types.view');

        $this->getJson(self::RAIZ.'/tipos')->assertOk();

        $this->postJson(self::RAIZ.'/tipos', ['name' => 'Festa', 'icon' => '🎉', 'color' => '#ff0000'])
            ->assertForbidden();

        $tipo = $this->tipo();

        $this->postJson(self::RAIZ."/tipos/{$tipo->id}/alternar")->assertForbidden();
        $this->deleteJson(self::RAIZ."/tipos/{$tipo->id}")->assertForbidden();
    }

    /**
     * A ORDEM RENUMERA-SE DO PRINCÍPIO.
     *
     * Trocar dois números presume que eles são diferentes — e uma lista criada
     * de enfiada fica toda a ZERO, pelo que a troca não mexia em nada.
     */
    public function test_a_ordem_dos_tipos_muda_mesmo_quando_estao_todos_a_zero(): void
    {
        $this->comPermissoes('events.types.view', 'events.types.manage');

        $a = EventType::create(['tenant_id' => $this->tenant->id, 'name' => 'AAA', 'icon' => '🎤', 'color' => '#111111', 'order' => 0, 'is_active' => true]);
        $b = EventType::create(['tenant_id' => $this->tenant->id, 'name' => 'BBB', 'icon' => '🎉', 'color' => '#222222', 'order' => 0, 'is_active' => true]);

        $this->postJson(self::RAIZ."/tipos/{$b->id}/mover", ['direccao' => 'cima'])->assertOk();

        $ordem = collect($this->getJson(self::RAIZ.'/tipos')->assertOk()->json('data'))->pluck('nome')->all();

        $this->assertSame(['BBB', 'AAA'], $ordem);
    }

    public function test_os_tecnicos_separam_ver_de_gerir(): void
    {
        $this->comPermissoes('events.technicians.view');

        $this->getJson(self::RAIZ.'/tecnicos')->assertOk();

        $this->postJson(self::RAIZ.'/tecnicos', ['name' => 'Zé'])->assertForbidden();
        $this->getJson(self::RAIZ.'/tecnicos/do-rh')->assertForbidden();
    }

    /** A especialidade é obrigatória — é por ela que se escala. */
    public function test_o_tecnico_precisa_de_pelo_menos_uma_especialidade(): void
    {
        $this->comPermissoes('events.technicians.view', 'events.technicians.manage');

        $this->postJson(self::RAIZ.'/tecnicos', [
            'name' => 'Zé', 'phone' => '923000000', 'level' => 'pleno',
        ])->assertStatus(422)->assertJsonValidationErrors('specialties');

        $this->postJson(self::RAIZ.'/tecnicos', [
            'name' => 'Zé', 'phone' => '923000000', 'level' => 'pleno',
            'specialties' => ['audio', 'streaming'],
        ])->assertCreated();

        $tecnico = Technician::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame(['audio', 'streaming'], $tecnico->specialties);
    }

    /** Quem tem material por devolver não se apaga. */
    public function test_o_tecnico_com_material_em_mao_nao_se_apaga(): void
    {
        $this->comPermissoes('events.technicians.view', 'events.technicians.manage');

        $tecnico = Technician::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Zé', 'phone' => '923000000',
            'specialties' => ['audio'], 'level' => 'pleno', 'is_active' => true,
        ]);

        Equipment::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Coluna',
            'status' => 'emprestado', 'borrowed_to_technician_id' => $tecnico->id,
        ]);

        $this->deleteJson(self::RAIZ."/tecnicos/{$tecnico->id}")->assertStatus(422);

        $this->assertNotNull(Technician::withoutGlobalScopes()->find($tecnico->id));
    }

    /* ─── Os relatórios ────────────────────────────────────────────────── */

    public function test_os_relatorios_exigem_a_permissao_e_somam_o_periodo(): void
    {
        $this->getJson(self::RAIZ.'/relatorios')->assertForbidden();

        $this->comPermissoes('events.reports.view', 'events.calendar.view', 'events.calendar.manage');

        $this->marcar(['total_value' => 150000, 'expected_attendees' => 200]);

        // O evento dos ensaios é daqui a dez dias, e o período por omissão vai
        // dos últimos doze meses até HOJE: sem alargar o «até», não conta.
        $ate = now()->addMonth()->format('Y-m-d');

        $r = $this->getJson(self::RAIZ."/relatorios?ate={$ate}")->assertOk()->json();

        $this->assertSame(1, $r['resumo']['eventos']);
        $this->assertEqualsWithDelta(150000, (float) $r['resumo']['valor'], 0.01);
        $this->assertSame(200, (int) $r['resumo']['pessoas']);
        $this->assertEqualsWithDelta(150000, (float) $r['resumo']['valor_medio'], 0.01);
    }

    /** E o CSV sai com o BOM, senão o Excel come os acentos. */
    public function test_o_csv_sai_com_o_bom(): void
    {
        $this->comPermissoes('events.reports.view');

        $resposta = $this->get(self::RAIZ.'/relatorios/csv');

        $resposta->assertOk();

        $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $resposta->streamedContent());
    }

    /* ─── O escopo de empresa ──────────────────────────────────────────── */

    public function test_nada_de_outra_empresa_aparece(): void
    {
        $this->comPermissoes(
            'events.calendar.view', 'events.equipment.view',
            'events.venues.view', 'events.types.view', 'events.technicians.view',
        );

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        Equipment::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Equipamento Alheio', 'status' => 'disponivel',
        ]);

        Venue::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Local Alheio', 'is_active' => true,
        ]);

        EventType::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Tipo Alheio',
            'icon' => '📌', 'color' => '#000000', 'order' => 0, 'is_active' => true,
        ]);

        Technician::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Técnico Alheio', 'phone' => '900000000',
            'specialties' => ['audio'], 'level' => 'pleno', 'is_active' => true,
        ]);

        $this->getJson(self::RAIZ.'/equipamentos')->assertOk()->assertJsonMissing(['nome' => 'Equipamento Alheio']);
        $this->getJson(self::RAIZ.'/locais')->assertOk()->assertJsonMissing(['nome' => 'Local Alheio']);
        $this->getJson(self::RAIZ.'/tipos')->assertOk()->assertJsonMissing(['nome' => 'Tipo Alheio']);
        $this->getJson(self::RAIZ.'/tecnicos')->assertOk()->assertJsonMissing(['nome' => 'Técnico Alheio']);
    }
}
