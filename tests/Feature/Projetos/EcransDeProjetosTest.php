<?php

namespace Tests\Feature\Projetos;

use App\Livewire\Projetos\Dashboard;
use App\Livewire\Projetos\Projetos;
use App\Livewire\Projetos\Tarefas;
use App\Livewire\Projetos\Timesheet;
use App\Models\Projetos\HoraLancada;
use App\Models\Projetos\Projeto;
use App\Models\Projetos\Tarefa;
use App\Models\User;
use App\Services\Projetos\FluxoDoProjeto;
use App\Services\Projetos\RegistoDeHoras;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Os ecrãs dos Projetos: que abrem, que lançam horas, e que não deixam ver a
 * folha de horas dos colegas nem facturar sem autoridade.
 */
class EcransDeProjetosTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('projetos');
    }

    private function projetoActivo(): Projeto
    {
        $p = app(FluxoDoProjeto::class)->criar($this->tenant->id, $this->user->id, [
            'nome' => 'Obra nova',
            'client_id' => $this->clienteEmpresa()->id,
            'orcamento' => 80000,
            'valor_hora' => 4000,
        ]);

        return app(FluxoDoProjeto::class)->mudarEstado($p, $this->tenant->id, 'activo');
    }

    /** @test */
    public function os_quatro_ecras_abrem(): void
    {
        $this->comPermissoes('projetos.view', 'projetos.tarefas.view', 'projetos.horas.registar');

        $this->get(route('projetos.dashboard'))->assertOk();
        $this->get(route('projetos.lista'))->assertOk();
        $this->get(route('projetos.tarefas'))->assertOk();
        $this->get(route('projetos.timesheet'))->assertOk();
    }

    /** Sem a permissão de ver, o ecrã não abre. */
    public function test_sem_permissao_o_ecra_fecha(): void
    {
        $this->comPermissoes('projetos.view');

        $this->get(route('projetos.tarefas'))->assertForbidden();
        $this->get(route('projetos.timesheet'))->assertForbidden();
    }

    /** @test */
    public function criar_um_projeto_pelo_ecra(): void
    {
        $this->comPermissoes('projetos.view', 'projetos.gerir');

        Livewire::actingAs($this->user)->test(Projetos::class)
            ->call('novoProjeto')
            ->set('nome', 'Migração do ERP')
            ->set('orcamento', '250000')
            ->set('valorHora', '7500')
            ->call('guardar')
            ->assertSet('showForm', false);

        $p = Projeto::where('tenant_id', $this->tenant->id)->latest()->first();

        $this->assertNotNull($p);
        $this->assertSame('Migração do ERP', $p->nome);
        $this->assertSame('rascunho', $p->estado);
        $this->assertSame(7500.0, (float) $p->valor_hora);
    }

    /** Sem `projetos.gerir` não se cria projeto, mesmo forçando a chamada. */
    public function test_sem_autoridade_nao_se_cria_projeto(): void
    {
        $this->comPermissoes('projetos.view');

        Livewire::actingAs($this->user)->test(Projetos::class)
            ->call('novoProjeto')
            ->set('nome', 'Não devia nascer')
            ->call('guardar');

        $this->assertSame(0, Projeto::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function lancar_horas_pelo_ecra(): void
    {
        $this->comPermissoes('projetos.horas.registar');
        $p = $this->projetoActivo();

        Livewire::actingAs($this->user)->test(Timesheet::class)
            ->call('lancarEm', now()->toDateString())
            ->set('projetoId', $p->id)
            ->set('horas', '6.5')
            ->set('descricao', 'Levantamento de requisitos')
            ->call('guardar')
            ->assertSet('showForm', false);

        $linha = HoraLancada::where('projeto_id', $p->id)->first();

        $this->assertNotNull($linha);
        $this->assertSame(6.5, (float) $linha->horas);
        $this->assertSame($this->user->id, $linha->user_id);
        // O preço congelou do projeto.
        $this->assertSame(4000.0, (float) $linha->valor_hora);
    }

    /**
     * A FOLHA DE HORAS É PESSOAL.
     *
     * Sem `projetos.horas.gerir`, um id no browser não abre o lançamento de
     * outra pessoa.
     *
     * @test
     */
    public function nao_se_mexe_nas_horas_de_outra_pessoa(): void
    {
        $this->comPermissoes('projetos.horas.registar');
        $p = $this->projetoActivo();

        $colega = User::create([
            'name' => 'Colega', 'email' => 'colega'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);
        $colega->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $doColega = app(RegistoDeHoras::class)->lancar($this->tenant->id, $colega->id, [
            'projeto_id' => $p->id, 'horas' => 4, 'user_id' => $colega->id,
        ]);

        // O `findOrFail` recusa: o lançamento existe, mas não é desta pessoa.
        // Em produção isto é um 404 — que é a resposta honesta a um id forjado.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($this->user)->test(Timesheet::class)
            ->call('editar', $doColega->id);
    }

    /** Com `projetos.horas.gerir` já se pode corrigir a folha da equipa. */
    public function test_com_autoridade_ve_as_horas_da_equipa(): void
    {
        $this->comPermissoes('projetos.horas.registar', 'projetos.horas.gerir');
        $p = $this->projetoActivo();

        $colega = User::create([
            'name' => 'Colega 2', 'email' => 'colega'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);
        $colega->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $doColega = app(RegistoDeHoras::class)->lancar($this->tenant->id, $colega->id, [
            'projeto_id' => $p->id, 'horas' => 4, 'user_id' => $colega->id,
        ]);

        Livewire::actingAs($this->user)->test(Timesheet::class)
            ->call('editar', $doColega->id)
            ->assertSet('editandoId', $doColega->id);
    }

    /** @test */
    public function criar_e_concluir_uma_tarefa_pelo_ecra(): void
    {
        $this->comPermissoes('projetos.tarefas.view', 'projetos.tarefas.manage');
        $p = $this->projetoActivo();

        $comp = Livewire::actingAs($this->user)->test(Tarefas::class)
            ->call('novaTarefa')
            ->set('formProjetoId', $p->id)
            ->set('titulo', 'Desenhar o modelo de dados')
            ->set('prioridade', 'alta')
            ->call('guardar')
            ->assertSet('showForm', false);

        $t = Tarefa::where('projeto_id', $p->id)->first();

        $this->assertNotNull($t);
        $this->assertSame('por_fazer', $t->estado);
        $this->assertSame('alta', $t->prioridade);

        $comp->call('mudarEstado', $t->id, 'concluida');

        $this->assertSame('concluida', $t->fresh()->estado);
        $this->assertNotNull($t->fresh()->concluida_em);

        // Reabrir limpa a data de fecho.
        $comp->call('mudarEstado', $t->id, 'por_fazer');
        $this->assertNull($t->fresh()->concluida_em);
    }

    /** Sem `projetos.facturar` não se emite documento nenhum. */
    public function test_sem_autoridade_nao_se_factura(): void
    {
        $this->comPermissoes('projetos.view', 'projetos.gerir');
        $p = $this->projetoActivo();

        app(RegistoDeHoras::class)->lancar($this->tenant->id, $this->user->id, [
            'projeto_id' => $p->id, 'horas' => 5,
        ]);

        Livewire::actingAs($this->user)->test(Projetos::class)
            ->set('confirmarFacturarId', $p->id)
            ->call('facturar');

        $this->assertSame(0, HoraLancada::where('projeto_id', $p->id)->whereNotNull('facturado_em')->count());
    }

    /** O painel conta o que está a fugir. */
    public function test_o_painel_conta_o_que_foge(): void
    {
        $this->comPermissoes('projetos.view');
        $p = $this->projetoActivo();

        app(RegistoDeHoras::class)->lancar($this->tenant->id, $this->user->id, [
            'projeto_id' => $p->id, 'horas' => 3,
        ]);

        Livewire::actingAs($this->user)->test(Dashboard::class)
            ->assertOk()
            ->assertViewHas('resumo', fn ($r) => $r['activos'] === 1
                && $r['horas_mes'] === 3.0
                && $r['valor_por_facturar'] === 12000.0);
    }
}
