<?php

namespace Tests\Feature\Revenda;

use App\Mail\Revenda\AvisoDaRevenda;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Revenda\LigacaoAoRevendedor;
use App\Services\Revenda\RegraDeComissao;
use Illuminate\Support\Facades\Mail;
use Tests\TenantTestCase;

/**
 * O PROGRAMA DE REVENDEDORES DE PONTA A PONTA (16/09/2026): o pedido, a
 * aprovação, o link e o código no registo, a entrada, o portal e o painel do
 * super admin.
 */
class PortalDoRevendedorTest extends TenantTestCase
{
    private Plan $pago;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->pago = Plan::create([
            'name' => 'Revenda Pago ' . uniqid(), 'slug' => 'rv-pago-' . uniqid(), 'description' => 'x',
            'price_monthly' => 15000, 'price_yearly' => 150000, 'trial_days' => 0,
            'max_users' => 5, 'max_companies' => 1, 'is_active' => true, 'is_public' => true, 'order' => 50,
        ]);
    }

    private function revendedor(string $estado = 'aprovado', array $mais = []): Reseller
    {
        $r = Reseller::create($mais + ['name' => 'Maria Revende', 'email' => 'maria' . uniqid() . '@exemplo.ao', 'phone' => '923000111', 'password' => 'Senha-forte-9']);
        $r->forceFill(['status' => $estado, 'code' => $estado === 'aprovado' ? Reseller::novoCodigo('Maria') : null, 'commission' => RegraDeComissao::de(['valor' => 10])->paraGuardar()])->save();

        return $r;
    }

    private function dono(): User
    {
        return User::create(['name' => 'Dono da plataforma', 'email' => 'dono' . uniqid() . '@exemplo.ao', 'password' => 'x', 'is_super_admin' => true, 'is_active' => true]);
    }

    private function nif(): string
    {
        return '5' . random_int(100000000, 999999999);
    }

    public function test_o_pedido_publico_fica_por_aprovar_e_avisa_os_dois_lados(): void
    {
        $admin = $this->dono();
        auth()->logout();

        $corpo = ['name' => 'Carlos Vendas', 'company_name' => 'CV Soluções', 'email' => 'Carlos.' . uniqid() . '@Exemplo.ao', 'phone' => '923456789',
            'province' => 'Luanda', 'motivation' => 'Tenho 40 clientes no comércio de Viana.', 'password' => 'Senha-forte-1', 'password_confirmation' => 'Senha-forte-1', 'aceito_termos' => true];

        $this->postJson('/revendedores/pedido', array_merge($corpo, ['aceito_termos' => false]))->assertStatus(422)->assertJsonValidationErrors('aceito_termos');
        $this->postJson('/revendedores/pedido', $corpo)->assertCreated();

        $r = Reseller::where('email', mb_strtolower($corpo['email']))->firstOrFail();
        $this->assertSame(['pendente', null], [$r->status, $r->code]);
        Mail::assertSent(AvisoDaRevenda::class, fn ($m) => $m->hasTo($r->email));
        Mail::assertSent(AvisoDaRevenda::class, fn ($m) => $m->hasTo($admin->email));

        // O mesmo email não pede duas vezes; o robot do campo escondido não cria nada.
        $this->postJson('/revendedores/pedido', $corpo)->assertStatus(422)->assertJsonValidationErrors('email');
        $antes = Reseller::count();
        $this->postJson('/revendedores/pedido', array_merge($corpo, ['email' => 'robot@exemplo.ao', 'site_da_empresa' => 'http://spam']))->assertCreated();
        $this->assertSame($antes, Reseller::count());

        // Por aprovar não entra, mesmo com a senha certa.
        $this->postJson('/revendedor/entrar', ['email' => $r->email, 'password' => 'Senha-forte-1'])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'O seu pedido ainda está a ser analisado. Avisamos por email quando for aprovado.');
        $this->assertFalse(auth('revendedor')->check());
    }

    public function test_o_super_admin_aprova_com_codigo_e_comissao_e_o_revendedor_entra(): void
    {
        $r = $this->revendedor('pendente');
        $this->actingAs($this->dono());

        $this->postJson("/api/v1/plataforma/react/revendedores/{$r->id}/aprovar", ['codigo' => 'mar ia1', 'comissao' => ['tipo' => 'percentagem', 'valor' => 150, 'aplica' => 'sempre', 'base' => 'sem_iva']])
            ->assertStatus(422)->assertJsonValidationErrors(['comissao.valor']);
        $this->postJson("/api/v1/plataforma/react/revendedores/{$r->id}/aprovar", ['codigo' => 'MARIA1', 'comissao' => ['tipo' => 'percentagem', 'valor' => 25, 'aplica' => 'meses', 'meses' => 12, 'base' => 'com_iva', 'planos' => [['plan_id' => $this->pago->id, 'tipo' => 'fixo', 'valor' => 3000]]]])
            ->assertOk();

        $r->refresh();
        $this->assertSame(['aprovado', 'MARIA1', 25.0, 'meses', 12, 3000.0], [$r->status, $r->code, $r->regra()->valor, $r->regra()->aplica, $r->regra()->meses, $r->regra()->planos[0]['valor']]);
        Mail::assertSent(AvisoDaRevenda::class, fn ($m) => $m->hasTo($r->email) && str_contains($m->render(), '/r/MARIA1'));

        // A lista do super admin traz os números.
        $this->getJson('/api/v1/plataforma/react/revendedores?estado=aprovado')->assertOk()->assertJsonPath('revendedores.0.codigo', 'MARIA1');

        auth()->logout();
        $this->postJson('/revendedor/entrar', ['email' => $r->email, 'password' => 'Senha-forte-9'])->assertOk()->assertJsonStructure(['ir_para']);
        $this->getJson('/revendedor/api/painel')->assertOk()->assertJsonPath('revendedor.codigo', 'MARIA1');

        // Suspenso a meio da sessão: sai no pedido seguinte (um pedido novo lê a conta de novo).
        $r->forceFill(['status' => 'suspenso'])->save();
        $this->app['auth']->forgetGuards();
        $this->getJson('/revendedor/api/painel')->assertForbidden();
    }

    public function test_um_utilizador_das_empresas_nao_mexe_nos_revendedores_e_o_convidado_vai_a_entrada(): void
    {
        $r = $this->revendedor('pendente');

        $this->postJson("/api/v1/plataforma/react/revendedores/{$r->id}/aprovar", ['comissao' => RegraDeComissao::PADRAO])->assertForbidden();
        // Sem sessão de revendedor, a API responde como uma sessão que acabou.
        $this->getJson('/revendedor/api/painel')->assertStatus(419);

        auth()->logout();
        $this->get('/revendedor')->assertRedirect(route('revendedor.login'));
        $this->get('/revendedor/entrar')->assertOk()->assertSee('data-ecra="revenda/entrada"', false);
        $this->get('/revendedores')->assertOk()->assertSee('data-ecra="revenda/seja-revendedor"', false);
    }

    public function test_o_link_e_o_codigo_ligam_a_empresa_no_registo(): void
    {
        $r = $this->revendedor();
        auth()->logout();

        // O link de um revendedor aprovado deixa o cookie; um código qualquer não.
        $this->get('/r/NAOEXISTE1')->assertRedirect(url('/'))->assertCookieMissing(LigacaoAoRevendedor::COOKIE);
        $this->get('/r/' . strtolower($r->code))->assertRedirect(url('/'))->assertCookie(LigacaoAoRevendedor::COOKIE, $r->code);

        // O registo confirma o código e recusa um inventado.
        $this->postJson('/register/revendedor', ['codigo' => $r->code])->assertOk()->assertJsonPath('nome', 'Maria Revende');
        $this->postJson('/register/revendedor', ['codigo' => 'XPTO99'])->assertNotFound();

        // O registo completo com o cookie do link: a página abre com o código, e a empresa fica ligada «por link».
        $props = $this->withCookie(LigacaoAoRevendedor::COOKIE, $r->code)->get('/register')->assertOk()->getContent();
        $this->assertStringContainsString($r->code, html_entity_decode($props));

        $gratis = Plan::create(['name' => 'Amigo ' . uniqid(), 'slug' => 'rv-gratis-' . uniqid(), 'description' => 'x', 'price_monthly' => 0, 'price_yearly' => 0, 'trial_days' => 30, 'max_users' => 2, 'max_companies' => 1, 'is_active' => true, 'auto_activate' => true, 'order' => 51]);
        $email = 'dona' . uniqid() . '@exemplo.ao';
        // Uma pessoa leva mais do que uns segundos a preencher (o travão dos robots do registo).
        $this->withSession(['registo_aberto_em' => time() - 60])->withCredentials()->withCookie(LigacaoAoRevendedor::COOKIE, $r->code)->postJson('/register', [
            'name' => 'Dona Rosa Silva', 'email' => $email, 'password' => 'Senha-forte-7', 'password_confirmation' => 'Senha-forte-7',
            'company_name' => 'Padaria Doce Lar', 'company_nif' => $this->nif(), 'company_regime' => Tenant::REGIME_GERAL,
            'selected_plan_id' => $gratis->id, 'payment_method' => 'transfer', 'aceito_termos' => true,
            'reseller_code' => $r->code, 'passo' => 4,
        ])->assertOk();

        $empresa = User::where('email', $email)->firstOrFail()->tenants()->first();
        $this->assertSame([$r->id, 'link'], [$empresa->reseller_id, $empresa->reseller_via]);

        // Um código inventado não passa do passo da empresa.
        $this->postJson('/register/seguinte', ['passo' => 2, 'company_name' => 'Talho Bom Corte', 'company_nif' => $this->nif(), 'company_regime' => Tenant::REGIME_GERAL, 'reseller_code' => 'XPTO99'])
            ->assertStatus(422)->assertJsonValidationErrors('reseller_code');
    }

    public function test_o_portal_so_mostra_as_empresas_dele_e_cria_empresas_pelo_cliente(): void
    {
        $r = $this->revendedor();
        $outro = $this->revendedor();
        LigacaoAoRevendedor::ligar($this->tenant, $outro, 'codigo');

        $this->actingAs($r, 'revendedor');
        $this->getJson('/revendedor/api/empresas')->assertOk()->assertJsonPath('contagens.todas', 0);
        $this->getJson("/revendedor/api/empresas/{$this->tenant->id}")->assertNotFound();

        $email = 'cliente' . uniqid() . '@exemplo.ao';
        $resposta = $this->postJson('/revendedor/api/empresas', [
            'company_name' => 'Mercearia Boa Esperança', 'company_nif' => $this->nif(), 'company_regime' => Tenant::REGIME_GERAL,
            'name' => 'Paulo Mendes Costa', 'email' => $email, 'selected_plan_id' => $this->pago->id,
        ])->assertCreated();

        $empresa = Tenant::findOrFail($resposta->json('id'));
        $this->assertSame([$r->id, 'revendedor'], [$empresa->reseller_id, $empresa->reseller_via]);
        $this->assertNotEmpty($resposta->json('senha'));
        Mail::assertSent(AvisoDaRevenda::class, fn ($m) => $m->hasTo($email) && str_contains($m->render(), $resposta->json('senha')));
        $this->assertSame('pending', Order::where('tenant_id', $empresa->id)->value('status'));

        $lista = $this->getJson('/revendedor/api/empresas')->assertOk();
        $this->assertSame([1, 1], [$lista->json('contagens.todas'), $lista->json('contagens.por_pagar')]);
        $this->getJson("/revendedor/api/empresas/{$empresa->id}")->assertOk()->assertJsonPath('empresa.dono.email', $email);

        // O comprovativo do pedido que ficou por pagar.
        $pedido = Order::where('tenant_id', $empresa->id)->firstOrFail();
        $this->post("/revendedor/api/empresas/{$empresa->id}/pedidos/{$pedido->id}/comprovativo", [
            'comprovativo' => \Illuminate\Http\UploadedFile::fake()->create('transferencia.pdf', 50, 'application/pdf'), 'referencia' => 'TRF-2026',
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('TRF-2026', $pedido->fresh()->payment_reference);
        $this->assertNotNull($pedido->fresh()->payment_proof);

        // Com um pedido à espera não se faz outro.
        $this->postJson("/revendedor/api/empresas/{$empresa->id}/pedidos", ['plan_id' => $this->pago->id, 'ciclo' => 'yearly'])->assertStatus(422);
    }

    /**
     * O REVENDEDOR PEDE O PLANO E PAGA O PREÇO DE REVENDEDOR (21/09/2026).
     *
     * Era «a aprovação dá a comissão que o super admin paga». Mudou por decisão
     * do utilizador: quando é o revendedor a pagar, paga já com o desconto, e a
     * comissão fica COMPENSADA — não se paga segunda vez.
     */
    public function test_o_pedido_do_revendedor_sai_com_desconto_e_a_comissao_fica_compensada(): void
    {
        $r = $this->revendedor();
        LigacaoAoRevendedor::ligar($this->tenant, $r, "codigo");

        $this->actingAs($r, "revendedor");
        $this->postJson("/revendedor/api/empresas/{$this->tenant->id}/pedidos", ["plan_id" => $this->pago->id, "ciclo" => "yearly", "referencia" => "TRF-ANO"])->assertCreated();

        $pedido = Order::where("tenant_id", $this->tenant->id)->where("status", "pending")->latest("id")->firstOrFail();
        // 150.000 de tabela, menos os 10% da regra: transfere 135.000.
        $this->assertSame([135000.0, "yearly"], [(float) $pedido->amount, $pedido->billing_cycle]);
        $this->assertStringContainsString($r->code, (string) $pedido->notes);

        $admin = $this->dono();
        $this->actingAs($admin);
        $this->postJson("/api/v1/plataforma/react/facturacao/pedidos/{$pedido->id}/aprovar")->assertOk();
        $c = ResellerCommission::where("origin_type", "order")->where("origin_id", $pedido->id)->firstOrFail();
        $this->assertEquals(15000, $c->amount, "o desconto que teve fica registado");
        $this->assertSame("compensada", $c->status);

        // Uma comissão compensada NÃO se paga: já a recebeu à cabeça.
        $this->postJson("/api/v1/plataforma/react/revendedores/{$r->id}/pagamentos", [
            "comissoes" => [$c->id], "method" => "transferencia", "reference" => "PAG-X", "paid_at" => now()->toDateString(),
        ])->assertStatus(422);
        $this->assertSame("compensada", $c->fresh()->status);
    }

    /** O CLIENTE PAGA: comissão por pagar, anulada só com motivo, paga e avisada. */
    public function test_o_cliente_a_pagar_da_a_comissao_que_o_super_admin_paga(): void
    {
        $r = $this->revendedor();
        LigacaoAoRevendedor::ligar($this->tenant, $r, "codigo");

        // O cliente pediu ele próprio, ao preço de tabela.
        $pedido = Order::create([
            "tenant_id" => $this->tenant->id, "user_id" => $this->user->id, "plan_id" => $this->pago->id,
            "amount" => 150000, "billing_cycle" => "yearly", "status" => "pending", "payment_method" => "bank_transfer",
        ]);

        $admin = $this->dono();
        $this->actingAs($admin);
        $this->postJson("/api/v1/plataforma/react/facturacao/pedidos/{$pedido->id}/aprovar")->assertOk();
        $c = ResellerCommission::where("origin_type", "order")->where("origin_id", $pedido->id)->firstOrFail();
        $this->assertEquals(15000, $c->amount);
        $this->assertSame("por_pagar", $c->status);

        // Anular pede motivo; pagar soma as escolhidas e avisa.
        $this->postJson("/api/v1/plataforma/react/revendedores/{$r->id}/comissoes/{$c->id}/anular", [])->assertStatus(422);
        $this->postJson("/api/v1/plataforma/react/revendedores/{$r->id}/pagamentos", [
            "comissoes" => [$c->id], "method" => "transferencia", "reference" => "PAG-1", "paid_at" => now()->toDateString(),
        ])->assertCreated();
        $this->assertSame("paga", $c->fresh()->status);
        Mail::assertSent(AvisoDaRevenda::class, fn ($m) => $m->hasTo($r->email) && $m->assunto === "Pagamento de comissões");

        // O revendedor vê a comissão paga e o pagamento.
        $this->actingAs($r, "revendedor");
        $comissoes = $this->getJson("/revendedor/api/comissoes")->assertOk();
        $this->assertSame(["paga", 15000.0], [$comissoes->json("comissoes.0.estado"), (float) $comissoes->json("pagamentos.0.valor")]);
    }
}
