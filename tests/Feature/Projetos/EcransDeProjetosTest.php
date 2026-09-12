<?php

namespace Tests\Feature\Projetos;

use App\Models\Projetos\HoraLancada;
use App\Models\Projetos\Projeto;
use App\Models\Projetos\Tarefa;
use App\Models\User;
use App\Services\Projetos\FluxoDoProjeto;
use App\Services\Projetos\RegistoDeHoras;
use Tests\TenantTestCase;

/**
 * OS ECRÃS DOS PROJETOS EM REACT: que abrem, que lançam horas, e que não
 * deixam ver a folha de horas dos colegas nem facturar sem autoridade.
 *
 * O QUE A MIGRAÇÃO DESTAPOU, e é o principal deste módulo: das SETE permissões
 * que os ecrãs usam, SEIS não existiam na base de dados desta instalação. Com
 * os curingas desligados, uma permissão que não existe é sempre falsa — e isso
 * quer dizer que `/projetos/tarefas` e `/projetos/timesheet` respondiam 403 a
 * TODA A GENTE, e que criar um projeto, criar uma tarefa ou lançar uma hora não
 * fazia nada e dizia «não tem permissão para isto» a quem tinha tudo.
 *
 * O seeder declara-as; o `modules:sync-permissions` cria-as. Ficou na lista do
 * que correr ao instalar.
 */
class EcransDeProjetosTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/projetos';

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
    public function os_quatro_ecras_abrem_e_montam_o_react(): void
    {
        $this->comPermissoes('projetos.view', 'projetos.tarefas.view', 'projetos.horas.registar');

        foreach ([
            'projetos.dashboard' => 'projetos/painel',
            'projetos.lista' => 'projetos/lista',
            'projetos.tarefas' => 'projetos/tarefas',
            'projetos.timesheet' => 'projetos/horas',
        ] as $rota => $ecra) {
            $this->get(route($rota))->assertOk()->assertSee($ecra, false);
        }
    }

    /** Sem a permissão de ver, o ecrã não abre. */
    public function test_sem_permissao_o_ecra_fecha(): void
    {
        $this->comPermissoes('projetos.view');

        $this->get(route('projetos.tarefas'))->assertForbidden();
        $this->get(route('projetos.timesheet'))->assertForbidden();
    }

    /** @test */
    public function criar_um_projeto_pela_porta(): void
    {
        $this->comPermissoes('projetos.view', 'projetos.gerir');

        $this->postJson(self::RAIZ.'/lista', [
            'nome' => 'Migração do ERP',
            'orcamento' => 250000,
            'valor_hora' => 7500,
        ])->assertCreated();

        $p = Projeto::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->latest()->first();

        $this->assertNotNull($p);
        $this->assertSame('Migração do ERP', $p->nome);
        $this->assertSame('rascunho', $p->estado);
        $this->assertSame(7500.0, (float) $p->valor_hora);
        $this->assertNotEmpty($p->codigo);
    }

    /** Sem `projetos.gerir` não se cria projeto — e a porta di-lo com um 403. */
    public function test_sem_autoridade_nao_se_cria_projeto(): void
    {
        $this->comPermissoes('projetos.view');

        $this->postJson(self::RAIZ.'/lista', ['nome' => 'Não devia nascer'])->assertForbidden();

        $this->assertSame(0, Projeto::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function lancar_horas_pela_porta(): void
    {
        $this->comPermissoes('projetos.horas.registar');
        $p = $this->projetoActivo();

        $this->postJson(self::RAIZ.'/horas', [
            'projeto_id' => $p->id,
            'data' => today()->toDateString(),
            'horas' => 6.5,
            'descricao' => 'Levantamento de requisitos',
        ])->assertCreated();

        $linha = HoraLancada::withoutGlobalScopes()->where('projeto_id', $p->id)->first();

        $this->assertNotNull($linha);
        $this->assertSame(6.5, (float) $linha->horas);
        $this->assertSame($this->user->id, $linha->user_id);
        // O PREÇO CONGELOU do projeto: mudá-lo no projeto não reescreve isto.
        $this->assertSame(4000.0, (float) $linha->valor_hora);
    }

    /**
     * A FOLHA DE HORAS É PESSOAL.
     *
     * Sem `projetos.horas.gerir`, um id no browser não abre nem corrige o
     * lançamento de outra pessoa. A porta responde 404 — que é a resposta
     * honesta a um id forjado: nem sequer confirma que ele existe.
     */
    public function test_nao_se_mexe_nas_horas_de_outra_pessoa(): void
    {
        $this->comPermissoes('projetos.horas.registar');
        $p = $this->projetoActivo();

        $doColega = $this->horaDoColega($p);

        $this->putJson(self::RAIZ."/horas/{$doColega->id}", [
            'projeto_id' => $p->id,
            'data' => today()->toDateString(),
            // UM VALOR VÁLIDO, de propósito: com 99 horas a validação
            // recusava primeiro, e o 404 que aqui se quer medir nunca chegava
            // a ser dado.
            'horas' => 9,
        ])->assertNotFound();

        $this->deleteJson(self::RAIZ."/horas/{$doColega->id}")->assertNotFound();

        $this->assertSame(4.0, (float) $doColega->fresh()->horas);
    }

    /** E a semana devolvida é a de quem pergunta, e não a da casa toda. */
    public function test_a_semana_e_a_de_quem_pergunta(): void
    {
        $this->comPermissoes('projetos.horas.registar');
        $p = $this->projetoActivo();

        $this->horaDoColega($p);

        app(RegistoDeHoras::class)->lancar($this->tenant->id, $this->user->id, [
            'projeto_id' => $p->id, 'horas' => 2, 'data' => today()->toDateString(),
        ]);

        $semana = $this->getJson(self::RAIZ.'/horas')->assertOk()->json();

        $this->assertEqualsWithDelta(2, $semana['resumo']['total'], 0.01,
            'a folha mostra só as horas de quem a abre');
    }

    /** Com `projetos.horas.gerir` já se corrige a folha da equipa. */
    public function test_com_autoridade_corrige_as_horas_da_equipa(): void
    {
        $this->comPermissoes('projetos.horas.registar', 'projetos.horas.gerir');
        $p = $this->projetoActivo();

        $doColega = $this->horaDoColega($p);

        $this->putJson(self::RAIZ."/horas/{$doColega->id}", [
            'projeto_id' => $p->id,
            'data' => today()->toDateString(),
            'horas' => 5,
        ])->assertOk();

        $this->assertSame(5.0, (float) $doColega->fresh()->horas);
    }

    private function horaDoColega(Projeto $p): HoraLancada
    {
        $colega = User::create([
            'name' => 'Colega', 'email' => 'colega'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);

        $colega->tenants()->syncWithoutDetaching([$this->tenant->id]);

        return app(RegistoDeHoras::class)->lancar($this->tenant->id, $colega->id, [
            'projeto_id' => $p->id, 'horas' => 4, 'data' => today()->toDateString(),
        ]);
    }

    /** @test */
    public function criar_e_concluir_uma_tarefa_pela_porta(): void
    {
        $this->comPermissoes('projetos.tarefas.view', 'projetos.tarefas.manage');
        $p = $this->projetoActivo();

        $this->postJson(self::RAIZ.'/tarefas', [
            'projeto_id' => $p->id,
            'titulo' => 'Desenhar o modelo de dados',
            'prioridade' => 'alta',
        ])->assertCreated();

        $t = Tarefa::withoutGlobalScopes()->where('projeto_id', $p->id)->firstOrFail();

        $this->assertSame('por_fazer', $t->estado);
        $this->assertSame('alta', $t->prioridade);

        $this->postJson(self::RAIZ."/tarefas/{$t->id}/estado", ['estado' => 'concluida'])->assertOk();

        $this->assertSame('concluida', $t->fresh()->estado);
        $this->assertNotNull($t->fresh()->concluida_em);

        // REABRIR LIMPA A DATA DE FECHO: uma tarefa que voltou a andar não pode
        // continuar a dizer que fechou naquele dia.
        $this->postJson(self::RAIZ."/tarefas/{$t->id}/estado", ['estado' => 'por_fazer'])->assertOk();

        $this->assertNull($t->fresh()->concluida_em);
    }

    /** Sem `projetos.tarefas.manage`, ver a lista não é mexer nela. */
    public function test_ver_tarefas_nao_e_mexer_nelas(): void
    {
        $this->comPermissoes('projetos.tarefas.view');
        $p = $this->projetoActivo();

        $this->getJson(self::RAIZ.'/tarefas')->assertOk();

        $this->postJson(self::RAIZ.'/tarefas', [
            'projeto_id' => $p->id, 'titulo' => 'Tentativa', 'prioridade' => 'normal',
        ])->assertForbidden();
    }

    /**
     * SEM `projetos.facturar` NÃO SE EMITE DOCUMENTO NENHUM.
     *
     * É uma fronteira de autoridade à parte da de gerir projetos: quem organiza
     * o trabalho não é necessariamente quem pode cobrar por ele.
     */
    public function test_sem_autoridade_nao_se_factura(): void
    {
        $this->comPermissoes('projetos.view', 'projetos.gerir');
        $p = $this->projetoActivo();

        app(RegistoDeHoras::class)->lancar($this->tenant->id, $this->user->id, [
            'projeto_id' => $p->id, 'horas' => 5,
        ]);

        $this->postJson(self::RAIZ."/lista/{$p->id}/facturar")->assertForbidden();

        $this->assertSame(0, HoraLancada::withoutGlobalScopes()
            ->where('projeto_id', $p->id)->whereNotNull('facturado_em')->count());
    }

    /** O painel conta o que está a fugir. */
    public function test_o_painel_conta_o_que_foge(): void
    {
        $this->comPermissoes('projetos.view');
        $p = $this->projetoActivo();

        app(RegistoDeHoras::class)->lancar($this->tenant->id, $this->user->id, [
            'projeto_id' => $p->id, 'horas' => 3,
        ]);

        $r = $this->getJson(self::RAIZ.'/painel')->assertOk()->json('resumo');

        $this->assertSame(1, $r['activos']);
        $this->assertEqualsWithDelta(3, $r['horas_mes'], 0.01);
        // 3 horas × 4000 Kz — trabalhadas e por cobrar.
        $this->assertEqualsWithDelta(12000, $r['valor_por_facturar'], 0.01);
    }

    /** E a ficha põe os três números lado a lado. */
    public function test_a_ficha_separa_o_orcamento_do_consumido_e_do_facturado(): void
    {
        $this->comPermissoes('projetos.view');
        $p = $this->projetoActivo();

        app(RegistoDeHoras::class)->lancar($this->tenant->id, $this->user->id, [
            'projeto_id' => $p->id, 'horas' => 3,
        ]);

        $ficha = $this->getJson(self::RAIZ."/lista/{$p->id}")->assertOk()->json('data');

        $this->assertEqualsWithDelta(80000, $ficha['orcamento'], 0.01);
        $this->assertEqualsWithDelta(12000, $ficha['consumido'], 0.01);
        $this->assertEqualsWithDelta(0, $ficha['facturado'], 0.01, 'ainda não saiu documento nenhum');
        $this->assertEqualsWithDelta(12000, $ficha['por_facturar'], 0.01);
    }
}
