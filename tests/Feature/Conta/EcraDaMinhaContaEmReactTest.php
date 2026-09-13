<?php

namespace Tests\Feature\Conta;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ContaDaPlataforma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * O ECRÃ DA MINHA CONTA EM REACT.
 *
 * O QUE A MIGRAÇÃO DESTAPOU, e ninguém via: o direito de EDITAR e de REMOVER
 * uma empresa era decidido por `pivot.role_id == 2` — um número escrito à mão.
 * Cada empresa cria os seus próprios papéis, com os seus próprios ids: o de
 * dono é o 2 na primeira empresa da base e é outro em todas as seguintes.
 *
 * Consequência: quem tivesse três empresas via os botões de editar e de remover
 * NUMA delas e em nenhuma das outras — e o servidor recusava na mesma se se
 * tentasse. A regra certa já existia ao lado, para a remoção: o papel diz-se
 * pelo NOME, não pelo número.
 *
 * A SEGUNDA COISA que a migração destapou: a área de conta é onde se PAGA, e a
 * API dela tinha de ficar aberta a quem já não tem subscrição — senão o cliente
 * fica trancado do lado de fora com a chave lá dentro.
 */
class EcraDaMinhaContaEmReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/conta';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->comoDono($this->tenant->id);
    }

    /** Dá ao utilizador o papel de dono nesta empresa — pelo NOME. */
    private function comoDono(int $tenantId): Role
    {
        $papel = Role::firstOrCreate([
            'name' => 'Super Admin', 'guard_name' => 'web', 'tenant_id' => $tenantId,
        ]);

        setPermissionsTeamId($tenantId);
        $this->user->assignRole($papel);

        DB::table('tenant_user')
            ->where('user_id', $this->user->id)->where('tenant_id', $tenantId)
            ->update(['role_id' => $papel->id]);

        return $papel;
    }

    /** Uma segunda empresa da mesma conta, com o papel que se disser. */
    private function segundaEmpresa(string $papel = 'Super Admin'): Tenant
    {
        $t = Tenant::create([
            'name' => 'Segunda Casa', 'slug' => 'segunda-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'segunda'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $p = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web', 'tenant_id' => $t->id]);

        $this->user->tenants()->attach($t->id, [
            'role_id' => $p->id, 'is_active' => true, 'joined_at' => now(),
        ]);

        return $t;
    }

    /* ─── A porta ─────────────────────────────────────────────────────── */

    /** @test */
    public function o_ecra_abre_e_monta_o_react(): void
    {
        $this->actingAs($this->user)->get(route('my-account'))
            ->assertOk()->assertSee('conta/minha-conta', false);
    }

    /**
     * A ÁREA DE CONTA É ONDE SE PAGA — e não fecha a quem deixou de pagar.
     *
     * A página estava na lista de portas sempre abertas; a API que a alimenta
     * não estava, e sem ela a página abria vazia: o cliente com a subscrição
     * expirada não tinha como renovar.
     */
    public function test_a_api_da_conta_abre_sem_subscricao_valida(): void
    {
        \App\Models\Subscription::where('tenant_id', $this->tenant->id)->delete();

        $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk();
    }

    /* ─── O dono, pelo nome do papel ──────────────────────────────────── */

    /**
     * O PAPEL DIZ-SE PELO NOME.
     *
     * Com `role_id == 2`, a segunda empresa de uma conta nunca mostrava os
     * botões de editar nem de remover — porque o papel de dono dela tem outro
     * id.
     */
    public function test_o_dono_e_dono_em_todas_as_suas_empresas(): void
    {
        $segunda = $this->segundaEmpresa();

        $empresas = collect($this->actingAs($this->user)->getJson(self::RAIZ)->assertOk()->json('empresas'));

        $this->assertTrue($empresas->firstWhere('id', $this->tenant->id)['sou_dono']);
        $this->assertTrue(
            $empresas->firstWhere('id', $segunda->id)['sou_dono'],
            'o papel de dono da segunda empresa tem outro id — e continua a ser dono',
        );
    }

    /** Quem não é dono não é dono, por muito que o id calhe. */
    public function test_quem_nao_e_dono_nao_edita_nem_remove(): void
    {
        $segunda = $this->segundaEmpresa('Caixa');

        $empresas = collect($this->actingAs($this->user)->getJson(self::RAIZ)->assertOk()->json('empresas'));

        $this->assertFalse($empresas->firstWhere('id', $segunda->id)['sou_dono']);

        $this->actingAs($this->user)->putJson(self::RAIZ.'/empresas/'.$segunda->id, [
            'name' => 'Roubada', 'nif' => '5000123456', 'regime' => 'regime_geral',
        ])->assertForbidden();

        $this->assertSame('Segunda Casa', $segunda->fresh()->name);
    }

    /** E a empresa de outra conta não se toca nem se lê. */
    public function test_a_empresa_de_outra_conta_nao_se_toca(): void
    {
        $alheia = Tenant::create([
            'name' => 'Casa Alheia', 'slug' => 'alheia-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'alheia'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $this->actingAs($this->user)->putJson(self::RAIZ.'/empresas/'.$alheia->id, [
            'name' => 'Roubada', 'nif' => '5000123456', 'regime' => 'regime_geral',
        ])->assertNotFound();

        $this->actingAs($this->user)
            ->getJson(self::RAIZ.'/empresas/'.$alheia->id.'/pode-arquivar')->assertNotFound();
    }

    /* ─── Editar a empresa ────────────────────────────────────────────── */

    public function test_o_dono_edita_a_empresa(): void
    {
        $this->actingAs($this->user)->putJson(self::RAIZ.'/empresas/'.$this->tenant->id, [
            'name' => 'Nome Novo Lda',
            'nif' => '5000123456',
            'regime' => 'regime_geral',
            'phone' => '923111222',
        ])->assertOk();

        $t = $this->tenant->fresh();

        $this->assertSame('Nome Novo Lda', $t->name);
        $this->assertSame('923111222', $t->phone);
    }

    /**
     * O NIF DE EMPRESA vale nos DOIS caminhos.
     *
     * Criar usava a regra certa; editar usava `min:9|max:14`, que deixava
     * passar o número do bilhete de identidade.
     */
    public function test_editar_usa_a_mesma_regra_de_nif_que_criar(): void
    {
        $antes = $this->tenant->nif;

        $this->actingAs($this->user)->putJson(self::RAIZ.'/empresas/'.$this->tenant->id, [
            'name' => $this->tenant->name,
            'nif' => '004512345LA041',
            'regime' => 'regime_geral',
        ])->assertStatus(422)->assertJsonValidationErrors('nif');

        $this->assertSame($antes, $this->tenant->fresh()->nif);
    }

    /* ─── Arquivar ────────────────────────────────────────────────────── */

    /** Não se remove a única empresa da conta. */
    public function test_nao_se_remove_a_unica_empresa(): void
    {
        $r = $this->actingAs($this->user)
            ->getJson(self::RAIZ.'/empresas/'.$this->tenant->id.'/pode-arquivar')->assertOk();

        $this->assertFalse($r->json('pode'));

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/empresas/'.$this->tenant->id, [
            'confirmacao' => $this->tenant->name,
        ])->assertStatus(422);

        $this->assertNotNull(Tenant::find($this->tenant->id));
    }

    /**
     * ESCREVER O NOME é a única confirmação que não se carrega por engano.
     */
    public function test_arquivar_exige_o_nome_escrito(): void
    {
        $segunda = $this->segundaEmpresa();

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/empresas/'.$segunda->id, [
            'confirmacao' => 'qualquer coisa',
        ])->assertStatus(422)->assertJsonValidationErrors('confirmacao');

        $this->assertNotNull(Tenant::find($segunda->id));
    }

    /** Com o nome escrito, a empresa sai da conta — e fica arquivada. */
    public function test_arquivar_e_soft_delete(): void
    {
        $segunda = $this->segundaEmpresa();

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/empresas/'.$segunda->id, [
            'confirmacao' => $segunda->name,
        ])->assertOk();

        $this->assertNull(Tenant::find($segunda->id));
        $this->assertNotNull(Tenant::withTrashed()->find($segunda->id),
            'nada se destrói: os dados ficam recuperáveis');
    }

    /* ─── O perfil e a senha ──────────────────────────────────────────── */

    public function test_o_perfil_guarda_se(): void
    {
        $this->actingAs($this->user)->putJson(self::RAIZ.'/perfil', [
            'name' => 'Maria Sousa',
            'email' => 'maria.sousa@empresa.ao',
            'phone' => '923000111',
            'bio' => 'Responsável pela facturação.',
        ])->assertOk();

        $u = $this->user->fresh();

        $this->assertSame('Maria Sousa', $u->name);
        $this->assertSame('maria.sousa@empresa.ao', $u->email);
    }

    /** Um e-mail que já é de outra pessoa não passa. */
    public function test_o_email_do_perfil_nao_se_repete(): void
    {
        $outro = User::create([
            'name' => 'Outro', 'email' => 'ocupado@empresa.ao', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);

        $this->actingAs($this->user)->putJson(self::RAIZ.'/perfil', [
            'name' => $this->user->name, 'email' => $outro->email,
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_a_fotografia_grava_se_e_apaga_se(): void
    {
        $this->actingAs($this->user)->postJson(self::RAIZ.'/avatar', [
            'avatar' => UploadedFile::fake()->image('eu.png'),
        ])->assertOk();

        $caminho = $this->user->fresh()->avatar;

        $this->assertNotNull($caminho);
        Storage::disk('public')->assertExists($caminho);

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/avatar')->assertOk();

        $this->assertNull($this->user->fresh()->avatar);
        Storage::disk('public')->assertMissing($caminho);
    }

    /** A SENHA ACTUAL confirma-se antes de se aceitar a nova. */
    public function test_a_senha_muda_se_com_a_actual_certa(): void
    {
        $this->user->update(['password' => bcrypt('a-minha-senha')]);

        $this->actingAs($this->user)->putJson(self::RAIZ.'/senha', [
            'actual' => 'a-errada',
            'nova' => 'uma-senha-nova',
            'nova_confirmation' => 'uma-senha-nova',
        ])->assertStatus(422)->assertJsonValidationErrors('actual');

        $this->actingAs($this->user)->putJson(self::RAIZ.'/senha', [
            'actual' => 'a-minha-senha',
            'nova' => 'uma-senha-nova',
            'nova_confirmation' => 'uma-senha-nova',
        ])->assertOk();

        $this->assertTrue(\Hash::check('uma-senha-nova', $this->user->fresh()->password));
        $this->assertNotNull($this->user->fresh()->last_password_changed);
    }

    /** E a senha nova não pode ser a mesma. */
    public function test_a_senha_nova_tem_de_ser_diferente(): void
    {
        $this->user->update(['password' => bcrypt('a-minha-senha')]);

        $this->actingAs($this->user)->putJson(self::RAIZ.'/senha', [
            'actual' => 'a-minha-senha',
            'nova' => 'a-minha-senha',
            'nova_confirmation' => 'a-minha-senha',
        ])->assertStatus(422)->assertJsonValidationErrors('nova');
    }

    /* ─── Os pedidos ──────────────────────────────────────────────────── */

    /**
     * O COMPROVATIVO É DO PEDIDO DE QUEM O FEZ.
     *
     * Um id escrito à mão não anexa nada ao pedido de outra pessoa.
     */
    public function test_nao_se_anexa_comprovativo_ao_pedido_de_outra_pessoa(): void
    {
        $outro = User::create([
            'name' => 'Outro', 'email' => 'outro-pedido@empresa.ao', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);

        $pedido = Order::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $outro->id,
            'plan_id' => $this->tenant->activeSubscription?->plan_id,
            'amount' => 1000, 'billing_cycle' => 'monthly', 'status' => 'pending',
            'payment_method' => 'bank_transfer',
        ]);

        $this->actingAs($this->user)->postJson(self::RAIZ.'/pedidos/'.$pedido->id.'/comprovativo', [
            'comprovativo' => UploadedFile::fake()->create('recibo.pdf', 10, 'application/pdf'),
        ])->assertNotFound();

        $this->assertNull($pedido->fresh()->payment_proof);
    }

    /** Um ficheiro que não é comprovativo não vai para o disco público. */
    public function test_o_comprovativo_tem_de_ser_pdf_ou_imagem(): void
    {
        $pedido = Order::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'plan_id' => $this->tenant->activeSubscription?->plan_id,
            'amount' => 1000, 'billing_cycle' => 'monthly', 'status' => 'pending',
            'payment_method' => 'bank_transfer',
        ]);

        $this->actingAs($this->user)->postJson(self::RAIZ.'/pedidos/'.$pedido->id.'/comprovativo', [
            'comprovativo' => UploadedFile::fake()->create('coisa.exe', 10, 'application/octet-stream'),
        ])->assertStatus(422)->assertJsonValidationErrors('comprovativo');

        $this->assertNull($pedido->fresh()->payment_proof);
    }

    /** O próprio pedido aceita o comprovativo. */
    public function test_o_comprovativo_anexa_se_ao_proprio_pedido(): void
    {
        $pedido = Order::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'plan_id' => $this->tenant->activeSubscription?->plan_id,
            'amount' => 1000, 'billing_cycle' => 'monthly', 'status' => 'pending',
            'payment_method' => 'bank_transfer',
        ]);

        $this->actingAs($this->user)->postJson(self::RAIZ.'/pedidos/'.$pedido->id.'/comprovativo', [
            'comprovativo' => UploadedFile::fake()->create('recibo.pdf', 10, 'application/pdf'),
        ])->assertOk();

        $this->assertNotNull($pedido->fresh()->payment_proof);
        Storage::disk('public')->assertExists($pedido->fresh()->payment_proof);
    }

    /* ─── A conta para onde se transfere ──────────────────────────────── */

    /**
     * O IBAN VIVE NUM SÍTIO SÓ.
     *
     * Estava escrito à mão em dois Blade — o assistente de registo e o modal
     * de mudança de plano — e as duas cópias já não diziam o mesmo. O que está
     * errado num IBAN é dinheiro de um cliente que não chega a lado nenhum.
     */
    public function test_a_conta_da_plataforma_vem_de_um_sitio_so(): void
    {
        $r = $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk();

        $this->assertSame(ContaDaPlataforma::dados()['iban'], $r->json('conta_da_plataforma.iban'));

        // E o assistente de registo lê da mesma fonte, em vez de ter o seu:
        // a página passa-lhe a conta, e o ecrã não tem IBAN nenhum escrito.
        $this->assertStringContainsString('ContaDaPlataforma::dados', file_get_contents(app_path('Http/Controllers/Registo/RegistoController.php')));
        $this->assertStringNotContainsString('AO06', file_get_contents(resource_path('js/ecras/registo/Assistente.tsx')),
            'o IBAN voltou a estar escrito à mão no assistente de registo');
    }

    /** E muda-se num sítio só. */
    public function test_mudar_a_conta_nas_definicoes_muda_em_todo_o_lado(): void
    {
        \App\Models\SystemSetting::set('billing_iban', 'AO06 1111 2222 3333 4444 5555 6');
        \App\Models\SystemSetting::forgetMemoria();

        $this->assertSame('AO06 1111 2222 3333 4444 5555 6', ContaDaPlataforma::dados()['iban']);

        $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk()
            ->assertJsonPath('conta_da_plataforma.iban', 'AO06 1111 2222 3333 4444 5555 6');
    }

    /* ─── Quem não gere a conta ───────────────────────────────────────── */

    /**
     * O PERFIL É DE CADA UM; O PLANO É DE QUEM GERE.
     *
     * Um caixa muda a sua própria senha e a sua fotografia — e não vê o plano,
     * as facturas nem os pedidos da casa.
     */
    public function test_quem_nao_gere_a_conta_ve_so_o_seu(): void
    {
        $caixa = User::create([
            'name' => 'Caixa', 'email' => 'caixa-minha-conta@empresa.ao', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $caixa->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $r = $this->actingAs($caixa)->getJson(self::RAIZ)->assertOk();

        $this->assertFalse($r->json('permissoes.gerir_conta'));
        $this->assertSame([], $r->json('planos'));
        $this->assertSame([], $r->json('facturas'));
        $this->assertSame([], $r->json('pedidos'));
        $this->assertNull($r->json('conta_da_plataforma'));

        // Mas o perfil é dele e muda-o.
        $this->actingAs($caixa)->putJson(self::RAIZ.'/perfil', [
            'name' => 'Caixa Novo', 'email' => $caixa->email,
        ])->assertOk();

        $this->assertSame('Caixa Novo', $caixa->fresh()->name);
    }

    /** E não cria empresas. */
    public function test_quem_nao_gere_a_conta_nao_cria_empresas(): void
    {
        $caixa = User::create([
            'name' => 'Caixa', 'email' => 'caixa2-minha-conta@empresa.ao', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $caixa->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $this->actingAs($caixa)->postJson(self::RAIZ.'/empresas', [
            'name' => 'Empresa Do Caixa', 'nif' => '5000123456', 'regime' => 'regime_geral',
        ])->assertForbidden();

        $this->assertNull(Tenant::where('name', 'Empresa Do Caixa')->first());
    }
}
