<?php

namespace Tests\Feature\Plataforma;

use App\Models\AuditTrail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Plataforma\Personificacao;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * A PERSONIFICAÇÃO — o dono da plataforma entra numa empresa em nome de alguém
 * de lá, com as permissões dessa pessoa.
 *
 * O que aqui se prova é a PORTA e os LIMITES: só o super admin da plataforma
 * entra; entra-se como uma pessoa da empresa que sirva (nunca como outro super
 * admin, nunca dentro de outra personificação); a sessão passa a ser mesmo a
 * da pessoa; tudo fica na trilha com o par pessoa/admin; acaba às duas horas;
 * e a senha e o email da pessoa não se mexem.
 */
class PersonificacaoTest extends TenantTestCase
{
    private User $admin;

    private User $dono;

    private User $caixa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Dono da Plataforma',
            'email' => 'plataforma_'.uniqid().'@exemplo.ao',
            'password' => bcrypt('x'),
        ]);
        $this->admin->forceFill(['is_super_admin' => true])->save();

        setPermissionsTeamId($this->tenant->id);
        Permission::findOrCreate('invoicing.sales.invoices.view', 'web');

        $papelDoDono = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $papelDoDono->givePermissionTo('invoicing.sales.invoices.view');
        $papelDeCaixa = Role::firstOrCreate(['name' => 'Caixa', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);

        // O caixa entra PRIMEIRO na empresa: se a escolha por omissão fosse
        // «o primeiro que aparece» e não «o dono», o teste via-o.
        $this->caixa = $this->pessoaDaEmpresa('Zé Caixa', $papelDeCaixa);
        $this->dono = $this->pessoaDaEmpresa('Maria Dona', $papelDoDono);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Quem pede é o admin, a partir da lista das empresas da plataforma.
        $this->actingAs($this->admin);
        session()->forget('active_tenant_id');
    }

    private function pessoaDaEmpresa(string $nome, ?Role $papel = null, ?Tenant $empresa = null): User
    {
        $empresa ??= $this->tenant;

        $u = User::create([
            'name' => $nome,
            'email' => 'p'.uniqid().'@exemplo.ao',
            'password' => bcrypt('segredo-dela'),
            'tenant_id' => $empresa->id,
        ]);
        $u->tenants()->syncWithoutDetaching([$empresa->id => ['is_active' => true]]);

        if ($papel) {
            setPermissionsTeamId($empresa->id);
            $u->assignRole($papel);
        }

        return $u;
    }

    private function entrar(array $dados = [], ?int $empresa = null): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/plataforma/react/painel/empresas/'.($empresa ?? $this->tenant->id).'/entrar', $dados + ['confirmar' => true]);
    }

    private function trilha(string $evento, ?int $empresa = null): ?AuditTrail
    {
        app(AuditRecorder::class)->despejar();

        return AuditTrail::where('tenant_id', $empresa ?? $this->tenant->id)->where('event', $evento)->latest('id')->first();
    }

    /* ─── A porta ─────────────────────────────────────────────────────── */

    public function test_um_utilizador_normal_nao_entra(): void
    {
        $this->actingAs($this->user)->entrar()->assertForbidden();

        $this->assertFalse(session()->has(Personificacao::CHAVE_DO_ADMIN));
    }

    /** O papel «Super Admin» de uma empresa é o dono DELA — não da plataforma. */
    public function test_o_dono_de_uma_empresa_nao_entra_nas_outras(): void
    {
        $outra = Tenant::create([
            'name' => 'Outra Casa', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'outra'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);
        $this->pessoaDaEmpresa('Alguém de lá', null, $outra);

        $this->actingAs($this->dono)->entrar([], $outra->id)->assertForbidden();
    }

    public function test_sem_confirmar_nao_entra(): void
    {
        $this->postJson("/api/v1/plataforma/react/painel/empresas/{$this->tenant->id}/entrar", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirmar');

        $this->assertSame($this->admin->id, auth()->id());
    }

    public function test_uma_pessoa_de_outra_empresa_e_recusada(): void
    {
        $outra = Tenant::create([
            'name' => 'Casa Alheia', 'slug' => 'alheia-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'alheia'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);
        $alheio = $this->pessoaDaEmpresa('Alheio', null, $outra);

        $this->entrar(['utilizador_id' => $alheio->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('utilizador_id');

        $this->assertSame($this->admin->id, auth()->id());
    }

    public function test_um_super_admin_da_plataforma_nao_se_personifica(): void
    {
        $outroAdmin = $this->pessoaDaEmpresa('Outro Admin');
        $outroAdmin->forceFill(['is_super_admin' => true])->save();

        $this->entrar(['utilizador_id' => $outroAdmin->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('utilizador_id');
    }

    public function test_uma_conta_desactivada_nao_se_personifica(): void
    {
        $this->caixa->forceFill(['is_active' => false])->save();

        $this->entrar(['utilizador_id' => $this->caixa->id])->assertStatus(422);
    }

    public function test_uma_empresa_sem_ninguem_e_recusada(): void
    {
        $vazia = Tenant::create([
            'name' => 'Casa Vazia', 'slug' => 'vazia-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'vazia'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $this->entrar([], $vazia->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('utilizador_id');
    }

    public function test_nao_ha_personificacao_dentro_de_personificacao(): void
    {
        $this->entrar()->assertOk();

        // Pela porta: quem está na sessão é a pessoa, e a porta é do super admin.
        $this->entrar(['utilizador_id' => $this->caixa->id])->assertForbidden();

        // E pelo serviço, mesmo que alguém chame com a conta do admin.
        $this->expectException(ValidationException::class);
        app(Personificacao::class)->entrar(request(), $this->admin, $this->tenant, $this->caixa->id);
    }

    /* ─── Lá dentro ───────────────────────────────────────────────────── */

    public function test_por_omissao_entra_como_o_dono_com_as_permissoes_dele(): void
    {
        $this->entrar()
            ->assertOk()
            ->assertJsonPath('seguir_para', route('home'));

        $this->assertSame($this->dono->id, auth()->id(), 'a sessão é a do dono');
        $this->assertSame($this->tenant->id, (int) activeTenantId());
        $this->assertSame($this->admin->id, session(Personificacao::CHAVE_DO_ADMIN));
        $this->assertTrue(auth()->user()->can('invoicing.sales.invoices.view'), 'com as permissões do papel dele');
    }

    public function test_entra_como_a_pessoa_escolhida(): void
    {
        $this->entrar(['utilizador_id' => $this->caixa->id])->assertOk();

        $this->assertSame($this->caixa->id, auth()->id());
        $this->assertFalse(auth()->user()->can('invoicing.sales.invoices.view'), 'o caixa não tem o que o dono tem');
    }

    public function test_a_entrada_e_os_actos_seguintes_ficam_na_trilha_com_o_admin(): void
    {
        $this->entrar()->assertOk();

        $entrada = $this->trilha('personificacao.entrou');

        $this->assertNotNull($entrada, 'o momento da entrada fica na trilha');
        $this->assertSame($this->dono->id, (int) $entrada->user_id);
        $this->assertSame($this->admin->id, (int) $entrada->impersonator_id);
        $this->assertSame($this->dono->id, $entrada->metadata['utilizador']['id']);

        app(AuditRecorder::class)->acto('teste.acto_em_nome_de', $this->tenant->id);

        $acto = $this->trilha('teste.acto_em_nome_de');

        $this->assertSame($this->dono->id, (int) $acto->user_id);
        $this->assertSame($this->admin->id, (int) $acto->impersonator_id);
    }

    public function test_entrar_nao_regista_um_login_da_pessoa(): void
    {
        $this->entrar()->assertOk();

        $this->assertNull($this->trilha('login', $this->dono->tenant_id), 'a pessoa não entrou com a senha — foi o admin');
    }

    public function test_o_topo_traz_a_personificacao(): void
    {
        $this->entrar()->assertOk();

        $this->getJson('/api/v1/casca/topo')
            ->assertOk()
            ->assertJsonPath('personificacao.activa', true)
            ->assertJsonPath('personificacao.utilizador.id', $this->dono->id)
            ->assertJsonPath('personificacao.empresa.id', $this->tenant->id)
            ->assertJsonPath('personificacao.admin.id', $this->admin->id);
    }

    public function test_fora_da_personificacao_o_topo_nao_a_traz(): void
    {
        $this->actingAs($this->user)->getJson('/api/v1/casca/topo')
            ->assertOk()
            ->assertJsonPath('personificacao', null);
    }

    public function test_a_lista_de_pessoas_marca_o_dono_e_quem_nao_serve(): void
    {
        $outroAdmin = $this->pessoaDaEmpresa('Outro Admin');
        $outroAdmin->forceFill(['is_super_admin' => true])->save();

        $lista = collect($this->getJson("/api/v1/plataforma/react/painel/empresas/{$this->tenant->id}/utilizadores")
            ->assertOk()
            ->json('utilizadores'))->keyBy('id');

        $this->assertTrue($lista[$this->dono->id]['dono']);
        $this->assertTrue($lista[$this->dono->id]['pode_entrar']);
        $this->assertContains('Super Admin', $lista[$this->dono->id]['papeis']);
        $this->assertFalse($lista[$this->caixa->id]['dono']);
        $this->assertTrue($lista[$outroAdmin->id]['super_admin_da_plataforma']);
        $this->assertFalse($lista[$outroAdmin->id]['pode_entrar']);
        $this->assertNotEmpty($lista[$outroAdmin->id]['motivo']);
    }

    /* ─── Sair ────────────────────────────────────────────────────────── */

    public function test_sair_devolve_o_admin_e_limpa_a_sessao(): void
    {
        $this->entrar()->assertOk();

        $this->postJson('/api/v1/casca/personificacao/sair')
            ->assertOk()
            ->assertJsonStructure(['message', 'seguir_para']);

        $this->assertSame($this->admin->id, auth()->id());
        $this->assertFalse(session()->has(Personificacao::CHAVE_DO_ADMIN));
        $this->assertFalse(session()->has(Personificacao::CHAVE));

        $saida = $this->trilha('personificacao.saiu');

        $this->assertNotNull($saida, 'a saída fica na trilha');
        $this->assertSame($this->admin->id, (int) $saida->impersonator_id);
        $this->assertArrayHasKey('duracao_em_segundos', $saida->metadata);
    }

    /** Deixou de ser dono da plataforma a meio: não se lhe devolve a conta de admin. */
    public function test_sair_quando_o_admin_ja_nao_o_e_fecha_a_sessao(): void
    {
        $this->entrar()->assertOk();

        $this->admin->forceFill(['is_super_admin' => false])->save();

        $this->postJson('/api/v1/casca/personificacao/sair')
            ->assertOk()
            ->assertJsonPath('seguir_para', route('login'));

        $this->assertNull(auth()->id(), 'nem a pessoa nem o admin');
        $this->assertFalse(session()->has(Personificacao::CHAVE_DO_ADMIN));
    }

    public function test_sair_sem_personificacao_e_recusado(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/casca/personificacao/sair')->assertStatus(422);

        $this->assertSame($this->user->id, auth()->id());
    }

    public function test_o_sair_do_menu_volta_a_plataforma_em_vez_de_fechar_a_sessao_do_admin(): void
    {
        $this->entrar()->assertOk();

        $this->post('/logout')->assertRedirect();

        $this->assertSame($this->admin->id, auth()->id());
        $this->assertNotNull($this->trilha('personificacao.saiu'));
    }

    /* ─── O prazo e a pessoa ──────────────────────────────────────────── */

    public function test_passadas_duas_horas_o_pedido_seguinte_termina_a_personificacao(): void
    {
        $this->entrar()->assertOk();

        session()->put(Personificacao::CHAVE.'.desde', now()->subHours(3)->getTimestamp());

        $this->getJson('/api/v1/casca/topo')
            ->assertStatus(401)
            ->assertJsonPath('personificacao_terminou', true);

        $this->assertSame($this->admin->id, auth()->id(), 'volta à conta do admin, não fica fora');
        $this->assertFalse(session()->has(Personificacao::CHAVE_DO_ADMIN));
        $this->assertNotNull($this->trilha('personificacao.expirou'));
    }

    public function test_a_pessoa_desactivada_a_meio_termina_a_personificacao_sem_por_o_admin_fora(): void
    {
        $this->entrar()->assertOk();

        $this->dono->forceFill(['is_active' => false])->save();
        auth()->user()->forceFill(['is_active' => false]);

        $this->getJson('/api/v1/casca/topo')
            ->assertStatus(401)
            ->assertJsonPath('personificacao_terminou', true);

        $this->assertSame($this->admin->id, auth()->id());
    }

    /* ─── O que não se faz em nome de outra pessoa ────────────────────── */

    public function test_nao_se_muda_a_senha_da_pessoa(): void
    {
        $this->entrar()->assertOk();

        $this->putJson('/api/v1/invoicing/react/conta/senha', [
            'actual' => 'segredo-dela', 'nova' => 'outra-senha-1', 'nova_confirmation' => 'outra-senha-1',
        ])->assertForbidden()->assertJsonPath('message', 'Durante a personificação não se muda a senha.');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('segredo-dela', $this->dono->fresh()->password));
    }

    public function test_nao_se_muda_o_email_da_pessoa(): void
    {
        $this->entrar()->assertOk();
        $email = $this->dono->email;

        $this->putJson('/api/v1/invoicing/react/conta/perfil', [
            'name' => 'Maria Dona', 'email' => 'pirata@exemplo.ao',
        ])->assertForbidden()->assertJsonPath('message', 'Durante a personificação não se mudam o email nem os dados de acesso.');

        $this->assertSame($email, $this->dono->fresh()->email);
    }

    public function test_nao_se_muda_de_empresa(): void
    {
        $this->entrar()->assertOk();

        $this->postJson("/api/v1/casca/empresas/{$this->tenant->id}/entrar")
            ->assertForbidden()
            ->assertJsonPath('message', 'Durante a personificação não se muda de empresa. Volte à plataforma e entre na outra.');
    }

    public function test_nao_se_edita_nem_apaga_a_propria_pessoa_no_ecra_dos_utilizadores(): void
    {
        $this->entrar()->assertOk();

        $this->putJson("/api/v1/invoicing/react/utilizadores/{$this->dono->id}", [
            'name' => 'Maria', 'email' => 'pirata@exemplo.ao', 'password' => 'x12345', 'password_confirmation' => 'x12345',
        ])->assertForbidden();

        $this->deleteJson("/api/v1/invoicing/react/utilizadores/{$this->dono->id}")->assertForbidden();

        $this->assertNotNull($this->dono->fresh());
    }
}
