<?php

namespace Tests\Feature\Salao;

use App\Models\Salon\Appointment;
use App\Models\Salon\Client as ClienteDeSalao;
use App\Models\Salon\Professional;
use App\Models\Salon\Service;
use App\Models\Salon\ServiceCategory;
use Tests\TenantTestCase;

/**
 * AS MARCAÇÕES DO SALÃO, pela porta que os ecrãs em React usam.
 *
 * O QUE ESTA MIGRAÇÃO CORRIGE, e é o principal: **não havia tabela de
 * transições nem verificação de sobreposição**.
 *
 *   · Os métodos do modelo escreviam o estado sem perguntar de onde vinham, e o
 *     ecrã mostrava os botões todos: dava para CONCLUIR uma marcação cancelada
 *     — e ela passava a contar na receita — ou para COMEÇAR um atendimento que
 *     já tinha acabado, apagando a hora de início e com ela o tempo real.
 *   · Duas marcações à mesma hora com o mesmo profissional entravam as duas, e
 *     só se descobria com as duas clientes sentadas à espera da mesma pessoa.
 *   · E O CLIENTE ERA «OPCIONAL» numa coluna NOT NULL: marcar sem cliente dava
 *     um erro de base de dados à frente de quem estava ao telefone.
 */
class MarcacoesEmReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/salao/marcacoes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('salon');
        $this->comPermissoes(
            'salon.appointments.view', 'salon.appointments.create',
            'salon.appointments.edit', 'salon.appointments.delete',
            'salon.clients.create',
        );
    }

    private function categoria(): ServiceCategory
    {
        return ServiceCategory::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cabelo', 'slug' => 'cabelo-'.uniqid(),
        ]);
    }

    private function servico(int $duracao = 30, float $preco = 5000): Service
    {
        $s = Service::create(['tenant_id' => $this->tenant->id, 'name' => 'Corte '.uniqid(), 'price' => $preco]);

        $s->updateSalonData(['category_id' => $this->categoria()->id, 'duration' => $duracao]);

        return $s->fresh();
    }

    private function profissional(string $nome = 'Ana'): Professional
    {
        return Professional::create([
            'tenant_id' => $this->tenant->id, 'name' => $nome, 'is_active' => true,
            'working_days' => [1, 2, 3, 4, 5, 6], 'work_start' => '09:00', 'work_end' => '18:00',
        ]);
    }

    private function cliente(): ClienteDeSalao
    {
        return ClienteDeSalao::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Dona Ana',
            'type' => 'pessoa_fisica', 'is_active' => true,
        ]);
    }

    /** Uma marcação criada pela porta nova. */
    private function marcar(array $extra = []): int
    {
        $dados = array_merge([
            'client_id' => $this->cliente()->id,
            'professional_id' => $this->profissional()->id,
            'date' => today()->addDay()->toDateString(),
            'start_time' => '10:00',
            'service_ids' => [$this->servico()->id],
        ], $extra);

        return (int) $this->postJson(self::RAIZ, $dados)->assertCreated()->json('data.id');
    }

    /* ─── Gravar ───────────────────────────────────────────────────────── */

    /**
     * A DURAÇÃO E O PREÇO SAEM DOS SERVIÇOS.
     *
     * Quem marca escolhe serviços; é o catálogo que diz quanto tempo levam e
     * quanto custam. Um preço vindo do browser era um desconto que ninguém deu.
     */
    public function test_a_duracao_e_o_preco_saem_dos_servicos(): void
    {
        $a = $this->servico(30, 5000);
        $b = $this->servico(45, 3000);

        $id = $this->marcar(['start_time' => '10:00', 'service_ids' => [$a->id, $b->id]]);

        $m = Appointment::withoutGlobalScopes()->findOrFail($id);

        $this->assertSame(75, (int) $m->total_duration);
        $this->assertSame('11:15', $m->end_time->format('H:i'));
        $this->assertEqualsWithDelta(8000, (float) $m->total, 0.01);
        $this->assertSame(2, $m->services()->count());
    }

    /**
     * O CLIENTE É OBRIGATÓRIO — e sempre foi.
     *
     * `salon_appointments.client_id` é NOT NULL. O ecrã em Livewire dava-o como
     * opcional, e marcar sem cliente rebentava com «Column 'client_id' cannot
     * be null»: um erro de base de dados à frente de quem estava a marcar.
     */
    public function test_sem_cliente_a_marcacao_e_recusada_a_tempo(): void
    {
        $this->postJson(self::RAIZ, [
            'professional_id' => $this->profissional()->id,
            'date' => today()->addDay()->toDateString(),
            'start_time' => '10:00',
            'service_ids' => [$this->servico()->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('client_id');

        $this->assertSame(0, Appointment::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /** Editar substitui os serviços em vez de os somar. */
    public function test_editar_substitui_os_servicos(): void
    {
        $a = $this->servico(30, 5000);
        $b = $this->servico(60, 9000);

        $id = $this->marcar(['service_ids' => [$a->id]]);
        $m = Appointment::withoutGlobalScopes()->findOrFail($id);

        $this->putJson(self::RAIZ."/{$id}", [
            'client_id' => $m->client_id,
            'professional_id' => $m->professional_id,
            'date' => today()->addDay()->toDateString(),
            'start_time' => '14:00',
            'service_ids' => [$b->id],
        ])->assertOk();

        $m = Appointment::withoutGlobalScopes()->findOrFail($id);

        $this->assertSame(1, $m->services()->count());
        $this->assertSame(60, (int) $m->total_duration);
        $this->assertEqualsWithDelta(9000, (float) $m->total, 0.01);
    }

    /** O serviço de outra empresa não entra numa marcação desta. */
    public function test_o_servico_de_outra_empresa_nao_entra(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $alheio = Service::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Alheio', 'price' => 1000,
            'type' => 'servico', 'module' => 'salon',
        ]);

        $this->postJson(self::RAIZ, [
            'client_id' => $this->cliente()->id,
            'professional_id' => $this->profissional()->id,
            'date' => today()->addDay()->toDateString(),
            'start_time' => '10:00',
            'service_ids' => [$alheio->id],
        ])->assertStatus(422);

        $this->assertSame(0, Appointment::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /* ─── A sobreposição ───────────────────────────────────────────────── */

    /** Uma marcação numa hora, para os ensaios da agenda. */
    private function as(Professional $p, string $dia, string $hora, Service $servico): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(self::RAIZ, [
            'client_id' => $this->cliente()->id,
            'professional_id' => $p->id,
            'date' => $dia,
            'start_time' => $hora,
            'service_ids' => [$servico->id],
        ]);
    }

    /**
     * O PROFISSIONAL NÃO SE DESDOBRA.
     *
     * Duas marcações sobrepostas com a mesma pessoa entravam as duas — e só se
     * descobria com as duas clientes sentadas à espera dela.
     */
    public function test_o_profissional_nao_atende_duas_ao_mesmo_tempo(): void
    {
        $ana = $this->profissional();
        $servico = $this->servico(60);
        $dia = today()->addDay()->toDateString();

        $this->as($ana, $dia, '10:00', $servico)->assertCreated();

        // Começa a meio da primeira: não cabe.
        $this->as($ana, $dia, '10:30', $servico)->assertStatus(422);

        $this->assertSame(1, Appointment::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /** Encostadas cabem: uma acaba às 11:00, a outra começa às 11:00. */
    public function test_duas_encostadas_cabem(): void
    {
        $ana = $this->profissional();
        $servico = $this->servico(60);
        $dia = today()->addDay()->toDateString();

        $this->as($ana, $dia, '10:00', $servico)->assertCreated();
        $this->as($ana, $dia, '11:00', $servico)->assertCreated();

        $this->assertSame(2, Appointment::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /** Uma marcação cancelada liberta a hora. */
    public function test_a_cancelada_liberta_a_hora(): void
    {
        $ana = $this->profissional();
        $servico = $this->servico(60);
        $dia = today()->addDay()->toDateString();

        $id = $this->as($ana, $dia, '10:00', $servico)->assertCreated()->json('data.id');

        $this->postJson(self::RAIZ."/{$id}/estado", ['estado' => 'cancelled'])->assertOk();

        $this->as($ana, $dia, '10:00', $servico)->assertCreated();
    }

    /** E outra pessoa pode atender à mesma hora — é outra cadeira. */
    public function test_outro_profissional_atende_a_mesma_hora(): void
    {
        $servico = $this->servico(60);
        $dia = today()->addDay()->toDateString();

        foreach ([$this->profissional('Ana'), $this->profissional('Rita')] as $p) {
            $this->as($p, $dia, '10:00', $servico)->assertCreated();
        }

        $this->assertSame(2, Appointment::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /* ─── As transições ────────────────────────────────────────────────── */

    /** O percurso normal: marcada → confirmada → chegou → em curso → concluída. */
    public function test_o_percurso_normal_anda(): void
    {
        $id = $this->marcar();

        foreach (['confirmed', 'arrived', 'in_progress', 'completed'] as $estado) {
            $this->postJson(self::RAIZ."/{$id}/estado", ['estado' => $estado])->assertOk();
        }

        $m = Appointment::withoutGlobalScopes()->findOrFail($id);

        $this->assertSame('completed', $m->status);

        // E CADA PASSO DEIXOU A SUA HORA — é delas que sai o tempo real.
        $this->assertNotNull($m->confirmed_at);
        $this->assertNotNull($m->arrived_at);
        $this->assertNotNull($m->started_at);
        $this->assertNotNull($m->completed_at);
        $this->assertNotNull($m->actual_duration);
    }

    /**
     * UMA CANCELADA NÃO SE CONCLUI.
     *
     * Era o caminho por onde uma marcação cancelada voltava a contar na receita
     * do mês.
     */
    public function test_uma_cancelada_nao_se_conclui(): void
    {
        $id = $this->marcar();

        $this->postJson(self::RAIZ."/{$id}/estado", ['estado' => 'cancelled'])->assertOk();
        $this->postJson(self::RAIZ."/{$id}/estado", ['estado' => 'completed'])->assertStatus(422);

        $this->assertSame('cancelled', Appointment::withoutGlobalScopes()->findOrFail($id)->status);
    }

    /** E uma concluída não recomeça — recomeçar apagava o tempo real. */
    public function test_uma_concluida_nao_recomeca(): void
    {
        $id = $this->marcar();

        foreach (['confirmed', 'in_progress', 'completed'] as $estado) {
            $this->postJson(self::RAIZ."/{$id}/estado", ['estado' => $estado])->assertOk();
        }

        $antes = Appointment::withoutGlobalScopes()->findOrFail($id)->started_at;

        $this->postJson(self::RAIZ."/{$id}/estado", ['estado' => 'in_progress'])->assertStatus(422);

        $this->assertEquals($antes, Appointment::withoutGlobalScopes()->findOrFail($id)->started_at);
    }

    /** Quem não compareceu não se confirma depois. */
    public function test_quem_nao_compareceu_nao_se_confirma(): void
    {
        $id = $this->marcar();

        $this->postJson(self::RAIZ."/{$id}/estado", ['estado' => 'no_show'])->assertOk();
        $this->postJson(self::RAIZ."/{$id}/estado", ['estado' => 'confirmed'])->assertStatus(422);
    }

    /** A resposta diz o que se pode fazer a seguir — e o ecrã só mostra isso. */
    public function test_a_resposta_diz_o_que_se_pode_fazer_a_seguir(): void
    {
        $id = $this->marcar();

        $pode = collect($this->getJson(self::RAIZ."/{$id}")->assertOk()->json('data.pode'))
            ->pluck('valor')->all();

        $this->assertEqualsCanonicalizing(['confirmed', 'arrived', 'cancelled', 'no_show'], $pode);

        $this->postJson(self::RAIZ."/{$id}/estado", ['estado' => 'cancelled'])->assertOk();

        $this->assertSame([], $this->getJson(self::RAIZ."/{$id}")->assertOk()->json('data.pode'),
            'de uma cancelada não se vai a lado nenhum');
    }

    /* ─── O calendário ─────────────────────────────────────────────────── */

    /** O mês desenha-se em semanas inteiras, para o quadro não ter buracos. */
    public function test_o_calendario_do_mes_comeca_e_acaba_em_semanas_inteiras(): void
    {
        $c = $this->getJson(self::RAIZ.'/calendario?vista=mes&dia='.today()->toDateString())
            ->assertOk()->json();

        $this->assertSame(1, \Carbon\Carbon::parse($c['de'])->dayOfWeekIso, 'começa numa segunda');
        $this->assertSame(7, \Carbon\Carbon::parse($c['ate'])->dayOfWeekIso, 'acaba num domingo');
    }

    /** E agrupa por dia, que é como o quadro o desenha. */
    public function test_o_calendario_agrupa_por_dia(): void
    {
        $dia = today()->addDay()->toDateString();

        $this->marcar(['date' => $dia, 'start_time' => '10:00']);

        $dias = $this->getJson(self::RAIZ.'/calendario?vista=mes&dia='.$dia)->assertOk()->json('dias');

        $this->assertArrayHasKey($dia, $dias);
        $this->assertCount(1, $dias[$dia]);
    }

    /* ─── As guardas ───────────────────────────────────────────────────── */

    public function test_sem_permissao_nao_se_ve_nem_se_marca(): void
    {
        $outro = \App\Models\User::create([
            'name' => 'Sem nada', 'email' => 'sn'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);

        $outro->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $this->actingAs($outro)->getJson(self::RAIZ)->assertForbidden();
        $this->actingAs($outro)->postJson(self::RAIZ, [])->assertForbidden();
    }

    /** A marcação de outra empresa não se vê por aqui. */
    public function test_a_marcacao_de_outra_empresa_nao_se_ve(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $cliente = ClienteDeSalao::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Alheia', 'type' => 'pessoa_fisica', 'is_active' => true,
        ]);

        $profissional = Professional::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Alheia', 'is_active' => true,
        ]);

        $alheia = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'client_id' => $cliente->id,
            'professional_id' => $profissional->id,
            'date' => today(), 'start_time' => '10:00', 'end_time' => '10:30',
            'status' => 'scheduled', 'subtotal' => 0, 'total' => 0,
        ]);

        $this->getJson(self::RAIZ."/{$alheia->id}")->assertNotFound();
    }
}
