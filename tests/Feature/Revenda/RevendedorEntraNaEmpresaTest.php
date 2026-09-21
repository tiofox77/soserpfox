<?php

namespace Tests\Feature\Revenda;

use App\Models\AuditTrail;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Plataforma\Personificacao;
use App\Services\Revenda\LigacaoAoRevendedor;
use App\Services\Revenda\RegraDeComissao;
use App\Services\Revenda\SuporteDoRevendedor;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * O REVENDEDOR ENTRA NA EMPRESA PARA DAR SUPORTE (21/09/2026).
 *
 * Decisão do utilizador: o revendedor entra nas empresas que trouxe, como o
 * super admin — mas só nas que o AUTORIZAREM. É uma pessoa de fora da
 * plataforma; entrar como a empresa dá-lhe tudo dela.
 *
 * O que aqui se prova é a PORTA e os LIMITES, e em particular os que só existem
 * por ser de fora:
 *
 *  · desligado por omissão; só com a autorização da empresa;
 *  · nunca na empresa de outro revendedor;
 *  · as mesmas travas do admin (duas horas, sem senhas nem PIN);
 *  · e nada que lhe deixe um acesso que sobreviva à autorização — criar ou
 *    convidar contas, mudar dados de acesso, subir-se de papel, abrir a porta
 *    a si próprio;
 *  · a empresa retira a autorização a meio: ele sai no clique seguinte;
 *  · a trilha diz que foi ele, sem o confundir com um utilizador.
 */
class RevendedorEntraNaEmpresaTest extends TenantTestCase
{
    private Reseller $revendedor;

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();

        setPermissionsTeamId($this->tenant->id);
        Permission::findOrCreate('invoicing.sales.invoices.view', 'web');

        $papelDoDono = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $papelDoDono->givePermissionTo('invoicing.sales.invoices.view');

        $this->dono = $this->pessoaDaEmpresa('Maria Dona', $papelDoDono);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->revendedor = $this->novoRevendedor();
        $this->assertTrue(LigacaoAoRevendedor::ligar($this->tenant, $this->revendedor, 'link'));
        $this->tenant->refresh();

        // Quem pede é o revendedor, a partir do portal dele.
        auth()->guard('web')->logout();
        session()->flush();
        $this->actingAs($this->revendedor, 'revendedor');
    }

    private function novoRevendedor(): Reseller
    {
        $r = Reseller::create(['name' => 'João Revende', 'email' => 'rev' . uniqid() . '@exemplo.ao', 'password' => 'Senha-forte-1']);
        $r->forceFill(['status' => 'aprovado', 'code' => Reseller::novoCodigo('João'), 'commission' => RegraDeComissao::de([])->paraGuardar()])->save();

        return $r;
    }

    private function pessoaDaEmpresa(string $nome, ?Role $papel = null, ?Tenant $empresa = null): User
    {
        $empresa ??= $this->tenant;

        $u = User::create(['name' => $nome, 'email' => 'p' . uniqid() . '@exemplo.ao', 'password' => bcrypt('segredo-dela'), 'tenant_id' => $empresa->id]);
        $u->tenants()->syncWithoutDetaching([$empresa->id => ['is_active' => true]]);

        if ($papel) {
            setPermissionsTeamId($empresa->id);
            $u->assignRole($papel);
        }

        return $u;
    }

    private function autorizar(bool $sim = true, ?Tenant $empresa = null): void
    {
        SuporteDoRevendedor::definir(($empresa ?? $this->tenant)->fresh(), $sim);
    }

    private function entrar(?int $empresa = null)
    {
        $resposta = $this->postJson('/revendedor/api/empresas/' . ($empresa ?? $this->tenant->id) . '/entrar');

        // O `auth:revendedor` põe o guard dele como o do pedido, e no ensaio a
        // aplicação é a mesma de pedido para pedido. Num pedido verdadeiro o
        // seguinte começa no `web` — é o que se repõe aqui.
        Auth::shouldUse('web');

        return $resposta;
    }

    private function trilha(string $evento): ?AuditTrail
    {
        app(AuditRecorder::class)->despejar();

        return AuditTrail::where('tenant_id', $this->tenant->id)->where('event', $evento)->latest('id')->first();
    }

    /* ─── A porta ─────────────────────────────────────────────────────── */

    public function test_sem_autorizacao_da_empresa_nao_entra(): void
    {
        // Desligado por omissão: é a empresa que decide.
        $this->entrar()->assertStatus(422)->assertJsonValidationErrors('personificacao');

        $this->assertFalse(session()->has(Personificacao::CHAVE_DO_REVENDEDOR));
        $this->assertNull(Auth::guard('web')->user());
    }

    public function test_nunca_na_empresa_de_outro_revendedor(): void
    {
        $outra = Tenant::create(['name' => 'Outra Empresa', 'slug' => 'outra-' . uniqid(), 'email' => 'o' . uniqid() . '@exemplo.ao', 'is_active' => true]);
        $this->pessoaDaEmpresa('Dono da Outra', null, $outra);
        $this->assertTrue(LigacaoAoRevendedor::ligar($outra, $this->novoRevendedor(), 'link'));
        $this->autorizar(true, $outra);

        // A empresa autorizou — o SEU revendedor, e não este.
        $this->entrar($outra->id)->assertNotFound();
        $this->assertNull(Auth::guard('web')->user());
    }

    public function test_com_autorizacao_entra_como_o_dono_da_empresa(): void
    {
        $this->autorizar();

        $this->entrar()->assertOk()->assertJsonPath('seguir_para', '/home');

        $this->assertSame($this->dono->id, Auth::guard('web')->id(), 'entra como o dono, como o super admin');
        $this->assertSame($this->tenant->id, (int) session('active_tenant_id'));

        // A chave é a DELE — nunca a do admin, que é um id de utilizador.
        $this->assertSame($this->revendedor->id, (int) session(Personificacao::CHAVE_DO_REVENDEDOR));
        $this->assertFalse(session()->has(Personificacao::CHAVE_DO_ADMIN));

        // E continua a ser revendedor: é para o portal que volta.
        $this->assertSame($this->revendedor->id, Auth::guard('revendedor')->id());
    }

    /* ─── A trilha ────────────────────────────────────────────────────── */

    /**
     * O acto tem de dizer que foi ELE — sem pôr o id dele no `impersonator_id`,
     * que é uma chave para `users`: isso atribuía o acto ao utilizador com o
     * mesmo número.
     */
    public function test_a_trilha_marca_o_revendedor_sem_o_confundir_com_um_utilizador(): void
    {
        $this->autorizar();
        $this->entrar()->assertOk();

        $linha = $this->trilha('personificacao.revendedor.entrou');

        $this->assertNotNull($linha);
        $this->assertSame('revendedor', $linha->actor_type);
        $this->assertNull($linha->impersonator_id, 'o id do revendedor não vai para a coluna dos utilizadores');
        $this->assertSame($this->dono->id, (int) $linha->user_id);
        $this->assertStringContainsString($this->revendedor->code, (string) $linha->actor_name);
    }

    /* ─── O que ele não faz lá dentro ────────────────────────────────── */

    public function test_nao_se_deixa_uma_porta_aberta_para_depois(): void
    {
        $this->autorizar();
        $this->entrar()->assertOk();

        // Criar uma conta com o email dele: entrava depois pela porta da frente.
        $this->postJson('/api/v1/invoicing/react/utilizadores', ['name' => 'Eu', 'email' => 'eu@revendedor.ao'])
            ->assertForbidden();

        // Subir-se de papel.
        $this->postJson('/api/v1/invoicing/react/papeis/utilizadores/' . $this->dono->id, ['papeis' => ['Super Admin']])
            ->assertForbidden();

        // Abrir a porta a si próprio.
        $this->putJson('/api/v1/invoicing/react/empresa/suporte-do-revendedor', ['permitido' => true])
            ->assertForbidden();
    }

    public function test_as_travas_do_admin_tambem_valem(): void
    {
        $this->autorizar();
        $this->entrar()->assertOk();

        $this->putJson('/api/v1/invoicing/react/conta/senha', [
            'current_password' => 'segredo-dela', 'password' => 'Nova-senha-1', 'password_confirmation' => 'Nova-senha-1',
        ])->assertForbidden();
    }

    /* ─── Quando acaba ────────────────────────────────────────────────── */

    public function test_a_empresa_retira_a_autorizacao_e_ele_sai_no_clique_seguinte(): void
    {
        $this->autorizar();
        $this->entrar()->assertOk();
        $this->assertSame($this->dono->id, Auth::guard('web')->id());

        // Quem gere a empresa desliga — sem esperar pelas duas horas.
        $this->autorizar(false);

        $this->getJson('/api/v1/casca/topo')->assertStatus(401)->assertJsonPath('personificacao_terminou', true);

        $this->assertNull(Auth::guard('web')->user(), 'a conta da empresa fecha');
        $this->assertSame($this->revendedor->id, Auth::guard('revendedor')->id(), 'o portal dele continua aberto');
        $this->assertNotNull($this->trilha('personificacao.revendedor.saiu'));
    }

    public function test_passadas_as_duas_horas_acaba(): void
    {
        $this->autorizar();
        $this->entrar()->assertOk();

        $this->travel(Personificacao::DURACAO_EM_MINUTOS + 1)->minutes();

        $this->getJson('/api/v1/casca/topo')->assertStatus(401)
            ->assertJsonPath('seguir_para', '/revendedor/empresas/' . $this->tenant->id);

        $this->assertNull(Auth::guard('web')->user());
        $this->assertNotNull($this->trilha('personificacao.revendedor.expirou'));
    }

    public function test_sair_volta_ao_portal_do_revendedor(): void
    {
        $this->autorizar();
        $this->entrar()->assertOk();

        $this->postJson('/api/v1/casca/personificacao/sair')->assertOk();

        $this->assertNull(Auth::guard('web')->user());
        $this->assertFalse(session()->has(Personificacao::CHAVE_DO_REVENDEDOR));
        $this->assertSame($this->revendedor->id, Auth::guard('revendedor')->id());
    }

    /**
     * O ACESSO DO REVENDEDOR NÃO CONTA COMO USO DA DONA. Os sinais de vida da
     * plataforma diriam que ela usou o sistema — quando foi o revendedor.
     */
    public function test_o_revendedor_a_navegar_nao_conta_como_acesso_da_dona(): void
    {
        $this->dono->forceFill(['last_login_at' => now()->subDays(10)])->save();

        $this->autorizar();
        $this->entrar()->assertOk();
        $this->getJson('/api/v1/casca/topo')->assertOk();

        $this->assertTrue(
            $this->dono->fresh()->last_login_at->lt(now()->subDays(9)),
            'a última entrada dela continua a de há dez dias',
        );
    }

    /* ─── O lado da empresa ───────────────────────────────────────────── */

    public function test_a_empresa_ve_o_revendedor_e_as_vezes_que_entrou(): void
    {
        $this->autorizar();
        $this->entrar()->assertOk();
        $this->postJson('/api/v1/casca/personificacao/sair')->assertOk();

        // Agora é quem gere a empresa a olhar.
        auth()->guard('revendedor')->logout();
        $gestor = $this->pessoaDaEmpresa('Gestor');
        setPermissionsTeamId($this->tenant->id);
        Permission::findOrCreate('settings.view', 'web');
        $gestor->givePermissionTo('settings.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($gestor)->withSession(['active_tenant_id' => $this->tenant->id]);

        $r = $this->getJson('/api/v1/invoicing/react/empresa/suporte-do-revendedor')->assertOk();

        $this->assertSame($this->revendedor->code, $r->json('revendedor.codigo'));
        $this->assertTrue($r->json('permitido'));
        $this->assertContains('personificacao.revendedor.entrou', collect($r->json('entradas'))->pluck('evento')->all());
        $this->assertFalse($r->json('pode_mudar'), 'ver não é poder mudar');
    }

    public function test_so_quem_gere_a_empresa_liga_ou_desliga(): void
    {
        auth()->guard('revendedor')->logout();
        $leitor = $this->pessoaDaEmpresa('Só Vê');
        setPermissionsTeamId($this->tenant->id);
        Permission::findOrCreate('settings.view', 'web');
        $leitor->givePermissionTo('settings.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($leitor)->withSession(['active_tenant_id' => $this->tenant->id])
            ->putJson('/api/v1/invoicing/react/empresa/suporte-do-revendedor', ['permitido' => true])
            ->assertForbidden();

        $this->assertFalse(SuporteDoRevendedor::permitido($this->tenant->fresh()));
    }
}
