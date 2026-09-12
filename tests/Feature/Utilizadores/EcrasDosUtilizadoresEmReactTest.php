<?php

namespace Tests\Feature\Utilizadores;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * OS TRÊS ECRÃS DOS UTILIZADORES EM REACT.
 *
 * O QUE A MIGRAÇÃO DESTAPOU: as três páginas estavam TODAS atrás de
 * `users.manage` — a permissão mais larga que existe — enquanto oito permissões
 * mais finas estavam declaradas na base e nenhuma linha de código as pedia.
 * Quem precisava de VER a lista de colegas levava junto o poder de apagar
 * contas. Agora cada porta pede a sua, com o `users.manage` a valer como
 * guarda-chuva para não fechar a porta a quem já cá está.
 *
 * O QUE DEIXOU DE EXISTIR: criar permissões a partir deste ecrã. A permissão
 * nascia GLOBAL, sem empresa nenhuma, escrita de dentro de uma casa — e, com os
 * curingas desligados, uma permissão inventada não é verificada em lado nenhum
 * do código. Era uma escrita que atravessava empresas para não dar poder nenhum
 * a ninguém.
 */
class EcrasDosUtilizadoresEmReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/utilizadores';

    private const PAPEIS = '/api/v1/invoicing/react/papeis';

    private function papel(string $nome = 'Caixa', ?int $empresa = null): Role
    {
        $empresa ??= $this->tenant->id;
        setPermissionsTeamId($empresa);

        return Role::create([
            'name' => $nome.' '.uniqid(), 'guard_name' => 'web', 'tenant_id' => $empresa,
        ]);
    }

    private function colega(string $email = 'colega@empresa.ao'): User
    {
        $u = User::create([
            'name' => 'Colega', 'email' => $email, 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $u->tenants()->syncWithoutDetaching([$this->tenant->id]);

        return $u;
    }

    /* ─── As portas ───────────────────────────────────────────────────── */

    /** @test */
    public function os_tres_ecras_abrem_e_montam_o_react(): void
    {
        $this->comPermissoes('users.manage');

        foreach ([
            'users.index' => 'utilizadores/lista',
            'users.roles-permissions' => 'utilizadores/papeis',
            'users.invitations' => 'utilizadores/convites',
        ] as $rota => $ecra) {
            $this->get(route($rota))->assertOk()->assertSee($ecra, false);
        }
    }

    /**
     * A PERMISSÃO FINA CHEGA, E SÓ ABRE O QUE LHE COMPETE.
     *
     * Quem só tem `users.view` vê a lista e mais nada: os papéis e os convites
     * ficam fechados. Era exactamente esta a separação que existia declarada e
     * que nunca ninguém verificou.
     */
    public function test_a_permissao_de_ver_abre_a_lista_e_so_a_lista(): void
    {
        $this->comPermissoes('users.view');

        $this->get(route('users.index'))->assertOk();
        $this->get(route('users.roles-permissions'))->assertForbidden();
        $this->get(route('users.invitations'))->assertForbidden();
    }

    /** E sem permissão nenhuma, nenhuma das três abre. */
    public function test_sem_permissao_nenhuma_das_tres_abre(): void
    {
        foreach (['users.index', 'users.roles-permissions', 'users.invitations'] as $rota) {
            $this->get(route($rota))->assertForbidden();
        }
    }

    /** Ver não é criar: quem só vê não cria contas. */
    public function test_quem_so_ve_nao_cria(): void
    {
        $this->comPermissoes('users.view');

        $this->actingAs($this->user)->postJson(self::RAIZ, [
            'name' => 'Novo', 'email' => 'novo@empresa.ao',
            'password' => 'segredo1', 'password_confirmation' => 'segredo1',
            'empresas' => [$this->tenant->id],
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'novo@empresa.ao']);
    }

    /* ─── A lista ─────────────────────────────────────────────────────── */

    /**
     * A LISTA É DESTA EMPRESA.
     *
     * Sem o filtro pelo pivot, o ecrã mostrava o nome e o e-mail de toda a
     * gente do sistema — de todas as empresas que partilham a base.
     */
    public function test_a_lista_nao_atravessa_empresas(): void
    {
        $this->comPermissoes('users.manage');
        $this->colega();

        $outra = Tenant::create(['name' => 'Outra Casa', 'email' => 'outra@casa.ao', 'is_active' => true]);
        $fora = User::create([
            'name' => 'De Fora', 'email' => 'fora@outra.ao', 'password' => bcrypt('x'),
            'tenant_id' => $outra->id, 'is_active' => true,
        ]);
        $fora->tenants()->syncWithoutDetaching([$outra->id]);

        $emails = collect($this->actingAs($this->user)->getJson(self::RAIZ)->assertOk()->json('data'))
            ->pluck('email')->all();

        $this->assertContains('colega@empresa.ao', $emails);
        $this->assertNotContains('fora@outra.ao', $emails);
    }

    /** E a ficha de quem é de outra casa responde que não existe. */
    public function test_nao_se_edita_quem_e_de_outra_empresa(): void
    {
        $this->comPermissoes('users.manage');

        $outra = Tenant::create(['name' => 'Outra Casa', 'email' => 'outra2@casa.ao', 'is_active' => true]);
        $fora = User::create([
            'name' => 'De Fora', 'email' => 'fora2@outra.ao', 'password' => bcrypt('x'),
            'tenant_id' => $outra->id, 'is_active' => true,
        ]);
        $fora->tenants()->syncWithoutDetaching([$outra->id]);

        $this->actingAs($this->user)->getJson(self::RAIZ.'/'.$fora->id)->assertNotFound();

        $this->actingAs($this->user)->putJson(self::RAIZ.'/'.$fora->id, [
            'name' => 'Roubado', 'email' => 'fora2@outra.ao',
            'empresas' => [$this->tenant->id],
        ])->assertNotFound();

        $this->assertSame('De Fora', $fora->fresh()->name);
    }

    /* ─── Criar e o papel por empresa ─────────────────────────────────── */

    /**
     * O PAPEL É POR EMPRESA.
     *
     * A mesma pessoa é gerente numa casa e caixa noutra. É esta a razão de o
     * formulário não ter «o papel»: tem um por empresa.
     */
    public function test_criar_grava_um_papel_por_empresa(): void
    {
        $this->comPermissoes('users.manage');

        $aqui = $this->papel('Gerente');

        $this->actingAs($this->user)->postJson(self::RAIZ, [
            'name' => 'Maria Sousa', 'email' => 'maria@empresa.ao',
            'password' => 'segredo1', 'password_confirmation' => 'segredo1',
            'empresas' => [$this->tenant->id],
            'papeis' => [$this->tenant->id => $aqui->id],
        ])->assertCreated();

        $nova = User::where('email', 'maria@empresa.ao')->firstOrFail();

        $this->assertTrue($nova->tenants()->where('tenants.id', $this->tenant->id)->exists());
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $aqui->id, 'model_id' => $nova->id, 'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * SÓ SE ATRIBUI O QUE SE TEM.
     *
     * Um administrador não pode pôr alguém numa empresa a que ele próprio não
     * pertence — seria criar acesso a uma casa que não é a sua.
     */
    public function test_nao_se_poe_ninguem_numa_empresa_que_nao_e_minha(): void
    {
        $this->comPermissoes('users.manage');

        $outra = Tenant::create(['name' => 'Casa Alheia', 'email' => 'alheia@casa.ao', 'is_active' => true]);

        $this->actingAs($this->user)->postJson(self::RAIZ, [
            'name' => 'Intruso', 'email' => 'intruso@empresa.ao',
            'password' => 'segredo1', 'password_confirmation' => 'segredo1',
            'empresas' => [$this->tenant->id, $outra->id],
        ])->assertCreated();

        $nova = User::where('email', 'intruso@empresa.ao')->firstOrFail();

        $this->assertDatabaseMissing('tenant_user', [
            'user_id' => $nova->id, 'tenant_id' => $outra->id,
        ]);
    }

    /** E um papel de outra casa não se cola a ninguém. */
    public function test_o_papel_tem_de_ser_da_empresa(): void
    {
        $this->comPermissoes('users.manage');

        $outra = Tenant::create(['name' => 'Casa Alheia', 'email' => 'alheia2@casa.ao', 'is_active' => true]);
        $papelAlheio = $this->papel('Chefe', $outra->id);

        $this->actingAs($this->user)->postJson(self::RAIZ, [
            'name' => 'Curioso', 'email' => 'curioso@empresa.ao',
            'password' => 'segredo1', 'password_confirmation' => 'segredo1',
            'empresas' => [$this->tenant->id],
            'papeis' => [$this->tenant->id => $papelAlheio->id],
        ])->assertCreated();

        $nova = User::where('email', 'curioso@empresa.ao')->firstOrFail();

        $this->assertDatabaseMissing('model_has_roles', [
            'role_id' => $papelAlheio->id, 'model_id' => $nova->id,
        ]);
    }

    /**
     * A EDIÇÃO NÃO MUDA A CASA DE ENTRADA.
     *
     * O `tenant_id` da ficha é só por onde a pessoa entra. Reescrevê-lo a cada
     * gravação fazia com que mexer nas empresas mudasse a empresa de omissão de
     * quem estava a trabalhar — e ela dava por si noutra casa ao entrar.
     */
    public function test_editar_nao_reescreve_a_empresa_de_entrada(): void
    {
        $this->comPermissoes('users.manage');

        $u = $this->colega();
        $entrada = $u->tenant_id;

        $this->actingAs($this->user)->putJson(self::RAIZ.'/'.$u->id, [
            'name' => 'Colega Novo', 'email' => $u->email,
            'empresas' => [$this->tenant->id],
        ])->assertOk();

        $this->assertSame($entrada, $u->fresh()->tenant_id);
        $this->assertSame('Colega Novo', $u->fresh()->name);
    }

    /** Editar sem palavra-passe não lhe muda a palavra-passe. */
    public function test_editar_sem_palavra_passe_nao_a_muda(): void
    {
        $this->comPermissoes('users.manage');

        $u = $this->colega();
        $antes = $u->password;

        $this->actingAs($this->user)->putJson(self::RAIZ.'/'.$u->id, [
            'name' => 'Colega', 'email' => $u->email, 'password' => '',
            'empresas' => [$this->tenant->id],
        ])->assertOk();

        $this->assertSame($antes, $u->fresh()->password);
    }

    /* ─── Estado e eliminação ─────────────────────────────────────────── */

    public function test_nao_se_desactiva_a_si_proprio(): void
    {
        $this->comPermissoes('users.manage');

        $this->actingAs($this->user)
            ->postJson(self::RAIZ.'/'.$this->user->id.'/estado', [])
            ->assertStatus(422);

        $this->assertTrue((bool) $this->user->fresh()->is_active);
    }

    public function test_alternar_o_estado_de_um_colega(): void
    {
        $this->comPermissoes('users.manage');

        $u = $this->colega();

        $this->actingAs($this->user)->postJson(self::RAIZ.'/'.$u->id.'/estado', [])
            ->assertOk()->assertJson(['activo' => false]);

        $this->assertFalse((bool) $u->fresh()->is_active);
    }

    /**
     * QUEM TEM DOCUMENTOS NÃO SE APAGA.
     *
     * Uma factura emitida guarda quem a emitiu. Apagar a conta deixava o
     * documento assinado por um id que já não aponta para ninguém — e a
     * auditoria pergunta pelo nome, não pelo número.
     */
    public function test_quem_emitiu_documentos_nao_se_elimina(): void
    {
        $this->comPermissoes('users.manage');

        $u = $this->colega();

        DB::table('invoicing_sales_invoices')->insert([
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'FT '.uniqid(),
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'created_by' => $u->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/'.$u->id)->assertStatus(422);

        $this->assertNotNull(User::find($u->id));
    }

    /** Quem nunca emitiu nada elimina-se — e fica em soft delete. */
    public function test_quem_nunca_emitiu_nada_elimina_se(): void
    {
        $this->comPermissoes('users.manage');

        $u = $this->colega();

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/'.$u->id)->assertOk();

        $this->assertNull(User::find($u->id));
        $this->assertNotNull(User::withTrashed()->find($u->id));
    }

    public function test_nao_se_elimina_a_si_proprio(): void
    {
        $this->comPermissoes('users.manage');

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/'.$this->user->id)->assertStatus(422);

        $this->assertNotNull(User::find($this->user->id));
    }

    /* ─── Os convites ─────────────────────────────────────────────────── */

    public function test_convidar_cria_o_convite_com_o_papel(): void
    {
        $this->comPermissoes('users.manage');
        $papel = $this->papel('Recepção');

        $this->actingAs($this->user)->postJson(self::RAIZ.'/convites', [
            'name' => 'Novo Colega', 'email' => 'convidado@empresa.ao', 'role_id' => $papel->id,
        ])->assertCreated();

        $convite = UserInvitation::where('email', 'convidado@empresa.ao')->firstOrFail();

        $this->assertSame($this->tenant->id, (int) $convite->tenant_id);
        $this->assertSame($papel->id, (int) $convite->role_id);
        $this->assertSame('pending', $convite->status);
        $this->assertNotEmpty($convite->token);
    }

    /**
     * O CONVITE SOBREVIVE AO E-MAIL QUE NÃO SAI.
     *
     * O envio depende de SMTP e de um modelo de e-mail gravados na base. Antes,
     * faltar um deles desfazia o convite inteiro e não ficava rasto nenhum do
     * que se tinha tentado fazer. Agora o convite fica e reenvia-se num botão.
     */
    public function test_o_convite_fica_mesmo_que_o_email_nao_saia(): void
    {
        $this->comPermissoes('users.manage');
        $papel = $this->papel();

        // Sem configuração de SMTP na base, o envio rebenta — é o caso real de
        // uma instalação nova.
        \App\Models\SmtpSetting::query()->delete();

        $this->actingAs($this->user)->postJson(self::RAIZ.'/convites', [
            'name' => 'Sem Email', 'email' => 'sememail@empresa.ao', 'role_id' => $papel->id,
        ])->assertCreated();

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'sememail@empresa.ao', 'status' => 'pending',
        ]);
    }

    /** Não se convida quem já cá está. */
    public function test_nao_se_convida_quem_ja_pertence_a_empresa(): void
    {
        $this->comPermissoes('users.manage');
        $papel = $this->papel();
        $u = $this->colega();

        $this->actingAs($this->user)->postJson(self::RAIZ.'/convites', [
            'name' => 'Repetido', 'email' => $u->email, 'role_id' => $papel->id,
        ])->assertStatus(422);
    }

    /** Nem se convida duas vezes o mesmo e-mail enquanto o primeiro vale. */
    public function test_nao_ha_dois_convites_pendentes_para_o_mesmo_email(): void
    {
        $this->comPermissoes('users.manage');
        $papel = $this->papel();

        UserInvitation::create([
            'tenant_id' => $this->tenant->id, 'invited_by' => $this->user->id,
            'email' => 'dobro@empresa.ao', 'name' => 'Dobro', 'role' => $papel->name,
        ]);

        $this->actingAs($this->user)->postJson(self::RAIZ.'/convites', [
            'name' => 'Dobro', 'email' => 'dobro@empresa.ao', 'role_id' => $papel->id,
        ])->assertStatus(422);
    }

    /** Um convite de outra empresa não se cancela daqui. */
    public function test_nao_se_cancela_o_convite_de_outra_empresa(): void
    {
        $this->comPermissoes('users.manage');

        $outra = Tenant::create(['name' => 'Casa Alheia', 'email' => 'alheia3@casa.ao', 'is_active' => true]);

        $convite = UserInvitation::create([
            'tenant_id' => $outra->id, 'invited_by' => $this->user->id,
            'email' => 'alheio@outra.ao', 'name' => 'Alheio', 'role' => 'user',
        ]);

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/convites/'.$convite->id)->assertNotFound();

        $this->assertSame('pending', $convite->fresh()->status);
    }

    /** Reenviar dá-lhe mais sete dias — um link morto era o pior dos mundos. */
    public function test_reenviar_estende_o_prazo(): void
    {
        $this->comPermissoes('users.manage');

        $convite = UserInvitation::create([
            'tenant_id' => $this->tenant->id, 'invited_by' => $this->user->id,
            'email' => 'velho@empresa.ao', 'name' => 'Velho', 'role' => 'user',
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($this->user)->postJson(self::RAIZ.'/convites/'.$convite->id.'/reenviar', [])
            ->assertOk();

        $this->assertTrue($convite->fresh()->expires_at->isFuture());
        $this->assertSame('pending', $convite->fresh()->status);
    }

    /** E a lista diz «expirado» a um pendente cuja data já passou. */
    public function test_um_pendente_fora_do_prazo_le_se_expirado(): void
    {
        $this->comPermissoes('users.manage');

        UserInvitation::create([
            'tenant_id' => $this->tenant->id, 'invited_by' => $this->user->id,
            'email' => 'fora@empresa.ao', 'name' => 'Fora', 'role' => 'user',
            'expires_at' => now()->subDay(),
        ]);

        $lista = $this->actingAs($this->user)->getJson(self::RAIZ.'/convites')->assertOk();

        $this->assertSame('expired', $lista->json('data.0.estado'));
        $this->assertSame(1, $lista->json('resumo.expirados'));
    }

    /* ─── Os papéis ───────────────────────────────────────────────────── */

    /**
     * UM PAPEL COM GENTE NÃO SE APAGA.
     *
     * Quem o tinha ficava sem permissão nenhuma e sem aviso — descobria-o na
     * manhã seguinte, à frente de um cliente, com o ecrã a dizer 403.
     */
    public function test_nao_se_elimina_um_papel_atribuido(): void
    {
        $this->comPermissoes('users.roles.manage');

        $papel = $this->papel();
        $u = $this->colega();

        setPermissionsTeamId($this->tenant->id);
        $u->assignRole($papel);

        $this->actingAs($this->user)->deleteJson(self::PAPEIS.'/'.$papel->id)->assertStatus(422);

        $this->assertNotNull(Role::find($papel->id));
    }

    /** Um papel sem ninguém elimina-se. */
    public function test_um_papel_sem_ninguem_elimina_se(): void
    {
        $this->comPermissoes('users.roles.manage');

        $papel = $this->papel();

        $this->actingAs($this->user)->deleteJson(self::PAPEIS.'/'.$papel->id)->assertOk();

        $this->assertNull(Role::find($papel->id));
    }

    /** E o papel de outra empresa não se toca. */
    public function test_nao_se_edita_o_papel_de_outra_empresa(): void
    {
        $this->comPermissoes('users.roles.manage');

        $outra = Tenant::create(['name' => 'Casa Alheia', 'email' => 'alheia4@casa.ao', 'is_active' => true]);
        $alheio = $this->papel('Chefe', $outra->id);

        $this->actingAs($this->user)->putJson(self::PAPEIS.'/'.$alheio->id, [
            'name' => 'Roubado',
        ])->assertNotFound();

        $this->actingAs($this->user)->deleteJson(self::PAPEIS.'/'.$alheio->id)->assertNotFound();
    }

    /**
     * ATRIBUIR PAPÉIS NÃO APAGA OS DAS OUTRAS EMPRESAS.
     *
     * O `syncRoles` só mexe nos papéis da equipa activa. Sem o contexto da
     * empresa bem posto, mudar-lhe o papel aqui tirava-lhe os papéis nas outras
     * casas — e ela perdia acessos que ninguém tinha mandado tirar.
     */
    public function test_atribuir_papeis_nao_toca_nas_outras_empresas(): void
    {
        $this->comPermissoes('users.roles.manage');

        $outra = Tenant::create(['name' => 'Segunda Casa', 'email' => 'segunda@casa.ao', 'is_active' => true]);

        $u = $this->colega();
        $u->tenants()->syncWithoutDetaching([$outra->id]);

        $la = $this->papel('Gerente', $outra->id);
        setPermissionsTeamId($outra->id);
        $u->assignRole($la);

        $aqui = $this->papel('Caixa');

        $this->actingAs($this->user)->postJson(self::PAPEIS.'/utilizadores/'.$u->id, [
            'papeis' => [$aqui->id],
        ])->assertOk();

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $aqui->id, 'model_id' => $u->id, 'tenant_id' => $this->tenant->id,
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $la->id, 'model_id' => $u->id, 'tenant_id' => $outra->id,
        ]);
    }

    /**
     * O ECRÃ JÁ NÃO CRIA PERMISSÕES.
     *
     * A permissão nascia global, sem empresa, escrita de dentro de uma casa — e
     * com os curingas desligados não era verificada em lado nenhum. Não há rota
     * nenhuma que o faça, e o número de permissões do sistema não mexe.
     */
    public function test_nao_ha_forma_de_criar_uma_permissao_a_partir_daqui(): void
    {
        $this->comPermissoes('users.roles.manage');

        $antes = Permission::count();

        $this->actingAs($this->user)
            ->postJson('/api/v1/invoicing/react/permissoes', ['name' => 'inventada.coisa'])
            ->assertNotFound();

        $this->assertSame($antes, Permission::count());
        $this->assertDatabaseMissing('permissions', ['name' => 'inventada.coisa']);
    }
}
