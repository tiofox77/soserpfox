<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * O assistente de registo não pode pedir o que já sabe nem cobrar o que não custa.
 *
 * Duas coisas davam mau aspecto logo à porta:
 *
 *   · quem escolhia o FOX Friendly — 0 Kz — chegava ao fim e via o IBAN da
 *     SOSERP, o campo "Referência da Transferência", "Valor: 0.00 Kz" e o
 *     aviso de que tinha de anexar o comprovativo. Comprovativo de quê?
 *
 *   · quem escolhia o plano na página inicial e carregava em "Começar Agora"
 *     era recebido com a pergunta "selecione o plano ideal para o seu
 *     negócio" — a escolha que tinha acabado de fazer.
 *
 * E ainda uma terceira, mais cara: a palavra-passe não é guardada na sessão
 * (e não deve ser), mas o assistente lia essa ausência como "os dados
 * perderam-se" e apagava tudo. Bastava um F5.
 *
 * O assistente passou de Livewire a React: as mesmas regras, agora pelas
 * acções do `RegistoController`, que devolvem o estado inteiro.
 */
class RegistoAssistenteTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function planoGratuito(): Plan
    {
        return Plan::create([
            'name' => 'Amigo', 'slug' => 'amigo-teste', 'description' => 'Grátis',
            'price_monthly' => 0, 'price_yearly' => 0, 'trial_days' => 180,
            'max_users' => 3, 'max_companies' => 1, 'is_active' => true,
            'auto_activate' => true, 'order' => 1,
        ]);
    }

    private function planoPago(): Plan
    {
        return Plan::create([
            'name' => 'Empresarial', 'slug' => 'empresarial-teste', 'description' => 'Pago',
            'price_monthly' => 24900, 'price_yearly' => 249000, 'trial_days' => 0,
            'max_users' => 20, 'max_companies' => 3, 'is_active' => true,
            'auto_activate' => false, 'order' => 2,
        ]);
    }

    /** As props com que a página monta o ecrã. */
    private function props(TestResponse $r): array
    {
        $r->assertOk()->assertSee('data-ecra="registo/assistente"', false);
        preg_match('/data-props="([^"]*)"/', $r->getContent(), $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
    }

    private function planoNoEstado(array $estado, int $id): array
    {
        return collect($estado['planos'])->firstWhere('id', $id);
    }

    private function nif(): string
    {
        return (string) random_int(500000000, 599999999);
    }

    public function test_a_pagina_monta_o_ecra_react(): void
    {
        $this->planoGratuito();

        $props = $this->props($this->get('/register'));

        $this->assertSame(1, $props['estado']['passo']);
        $this->assertArrayHasKey('iban', $props['conta']);
    }

    // ==================== nada a pagar ====================

    public function test_o_plano_gratuito_e_marcado_gratuito_e_o_pago_nao(): void
    {
        $gratis = $this->planoGratuito();
        $pago = $this->planoPago();

        $estado = $this->props($this->get('/register'))['estado'];

        $this->assertTrue($this->planoNoEstado($estado, $gratis->id)['gratuito']);
        $this->assertFalse($this->planoNoEstado($estado, $pago->id)['gratuito']);
    }

    /**
     * Todos os planos têm dias de teste. Se o teste contasse como "nada a
     * pagar", o passo do pagamento desaparecia para toda a gente.
     */
    public function test_ter_dias_de_teste_nao_torna_um_plano_pago_em_gratuito(): void
    {
        $pago = $this->planoPago();
        $pago->update(['trial_days' => 30]);

        $estado = $this->props($this->get('/register'))['estado'];

        $this->assertFalse($this->planoNoEstado($estado, $pago->id)['gratuito']);
    }

    /**
     * O GUARDAR AUTOMÁTICO NÃO GASTA O TRAVÃO DO REGISTO (16/09/2026).
     *
     * O `throttle:N,M` do Laravel partilhava um contador por IP entre TODAS as
     * rotas: vinte gravações do progresso enchiam-no, e o «Criar conta» (tecto
     * 10) respondia «Too Many Attempts.» sem a pessoa ter tentado nada.
     */
    public function test_o_progresso_guardado_nao_trava_o_botao_final(): void
    {
        $gratis = $this->planoGratuito();

        for ($i = 0; $i < 25; $i++) {
            $this->putJson('/register/progresso', ['passo' => 2, 'company_name' => 'Padaria ' . $i])->assertOk();
            $this->postJson('/api/analytics/track', ['event' => 'page_view', 'url' => '/register']);
        }

        $this->postJson('/register', $this->registoCompleto($gratis))->assertOk();
    }

    /** Cada travão conta só os seus pedidos — e o aviso chega em português, com a espera. */
    public function test_o_travao_e_por_rota_e_o_aviso_diz_quanto_esperar(): void
    {
        // Os guardados de outra rota não contam para o «Recomeçar» (tecto 20).
        for ($i = 0; $i < 15; $i++) {
            $this->putJson('/register/progresso', ['passo' => 2, 'company_name' => 'Padaria ' . $i])->assertOk();
        }
        for ($i = 0; $i < 20; $i++) {
            $this->deleteJson('/register/progresso')->assertOk();
        }

        $r = $this->deleteJson('/register/progresso')->assertStatus(429);
        $this->assertStringStartsWith('Demasiadas tentativas seguidas. Tente de novo dentro de', $r->json('message'));
        $this->assertNotNull($r->headers->get('Retry-After'));

        // E o recomeçar esgotado não toca nos outros.
        $this->putJson('/register/progresso', ['passo' => 2, 'company_name' => 'Padaria'])->assertOk();
    }

    /** O plano gratuito não pede referência nem comprovativo — nem à entrada do servidor. */
    public function test_o_plano_gratuito_regista_sem_dados_de_pagamento(): void
    {
        $gratis = $this->planoGratuito();

        $this->postJson('/register', $this->registoCompleto($gratis))->assertOk();
    }

    public function test_o_plano_pago_sem_teste_pede_a_referencia_e_o_comprovativo(): void
    {
        $pago = $this->planoPago();

        $this->postJson('/register', $this->registoCompleto($pago))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_reference', 'payment_proof']);
    }

    // ==================== plano do link ====================

    public function test_plano_vindo_do_link_nao_e_perguntado_outra_vez(): void
    {
        $gratis = $this->planoGratuito();
        $this->planoPago();

        $estado = $this->props($this->get('/register?plan=amigo-teste'))['estado'];

        $this->assertSame($gratis->id, $estado['campos']['selected_plan_id']);
        $this->assertTrue($estado['plano_veio_do_link']);
    }

    public function test_sem_plano_no_link_o_passo_da_escolha_mantem_se(): void
    {
        $this->planoGratuito();

        $this->assertFalse($this->props($this->get('/register'))['estado']['plano_veio_do_link']);
    }

    /** Não perguntar duas vezes não pode virar não deixar mudar de ideias. */
    public function test_pode_sempre_trocar_de_plano_mesmo_vindo_do_link(): void
    {
        $this->planoGratuito();
        $this->get('/register?plan=amigo-teste');
        $this->postJson('/register/seguinte', $this->passo1())->assertOk();

        $this->postJson('/register/outro-plano', ['passo' => 4])
            ->assertOk()
            ->assertJsonPath('plano_veio_do_link', false)
            ->assertJsonPath('passo', 3);
    }

    public function test_com_plano_no_link_a_empresa_leva_direto_ao_ultimo_passo(): void
    {
        $this->planoPago();

        $this->get('/register?plan=empresarial-teste');

        $this->postJson('/register/seguinte', $this->passo1())->assertOk()->assertJsonPath('passo', 2);
        $this->postJson('/register/seguinte', ['passo' => 2, 'company_name' => 'Padaria Central', 'company_nif' => $this->nif()])
            ->assertOk()
            ->assertJsonPath('passo', 4);
    }

    /** O botão "Voltar" não pode cair no passo que o "Próximo" salta. */
    public function test_voltar_atras_salta_o_passo_do_plano_que_veio_do_link(): void
    {
        $pago = $this->planoPago();
        session(['wizard_progress' => ['currentStep' => 4, 'name' => 'Ana', 'email' => 'ana@exemplo.ao', 'selected_plan_id' => $pago->id, 'planoVeioDoLink' => true]]);

        $this->postJson('/register/anterior', ['passo' => 4])->assertOk()->assertJsonPath('passo', 2);
    }

    // ==================== refresh ====================

    /** O caso que apagava tudo: recarregar a página a meio do registo. */
    public function test_um_refresh_nao_deita_fora_os_dados_ja_preenchidos(): void
    {
        $pago = $this->planoPago();

        session(['wizard_progress' => [
            'currentStep' => 4,
            'name' => 'Ana',
            'email' => 'ana@exemplo.ao',
            'company_name' => 'Padaria Central',
            'company_nif' => '5417123456',
            'company_address' => 'Rua 1, Luanda',
            'selected_plan_id' => $pago->id,
            'payment_method' => 'transfer',
            'payment_reference' => 'TRF900',
        ]]);

        // Sem a palavra-passe — é o que acontece a seguir a um F5.
        $estado = $this->props($this->get('/register'))['estado'];

        $this->assertSame('Padaria Central', $estado['campos']['company_name']);
        $this->assertSame('5417123456', $estado['campos']['company_nif']);
        $this->assertSame('Rua 1, Luanda', $estado['campos']['company_address']);
        $this->assertSame($pago->id, $estado['campos']['selected_plan_id']);
        // Volta ao passo 1 só para reescrever a palavra-passe...
        $this->assertSame(1, $estado['passo']);
        $this->assertSame(4, $estado['passo_antes_da_senha']);
        $this->assertSame('info', $estado['aviso']['tipo']);

        // ...e daí segue direto para onde estava.
        $this->postJson('/register/seguinte', ['passo' => 1, 'name' => 'Ana', 'email' => 'ana'.uniqid().'@exemplo.ao', 'password' => 'segredo-forte-123', 'password_confirmation' => 'segredo-forte-123'])
            ->assertOk()
            ->assertJsonPath('passo', 4)
            ->assertJsonPath('campos.company_name', 'Padaria Central');
    }

    public function test_o_plano_do_link_sobrevive_ao_refresh(): void
    {
        $gratis = $this->planoGratuito();

        session(['wizard_progress' => [
            'currentStep' => 2,
            'name' => 'Ana',
            'email' => 'ana@exemplo.ao',
            'selected_plan_id' => $gratis->id,
            'planoVeioDoLink' => true,
        ]]);

        $this->assertTrue($this->props($this->get('/register'))['estado']['plano_veio_do_link']);
    }

    /** Erros de validação não são motivo para apagar o que já foi escrito. */
    public function test_erros_de_validacao_nao_apagam_a_empresa_ja_preenchida(): void
    {
        $this->planoPago();

        $this->postJson('/register/seguinte', ['passo' => 1, 'company_name' => 'Padaria Central', 'company_nif' => '5417123456'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertSame('Padaria Central', session('wizard_progress.company_name'));
        $this->assertSame('5417123456', session('wizard_progress.company_nif'));
        $this->assertSame(1, session('wizard_progress.currentStep'));
    }

    /** O que se escreve guarda-se sozinho — menos a palavra-passe. */
    public function test_guardar_o_progresso_nunca_leva_a_palavra_passe(): void
    {
        $this->putJson('/register/progresso', ['passo' => 2, 'company_name' => 'Padaria Central', 'password' => 'segredo-forte-123'])
            ->assertOk()
            ->assertJsonStructure(['guardado_em']);

        $this->assertSame('Padaria Central', session('wizard_progress.company_name'));
        $this->assertStringNotContainsString('segredo-forte-123', json_encode(session('wizard_progress')));
    }

    public function test_recomecar_apaga_o_progresso(): void
    {
        session(['wizard_progress' => ['currentStep' => 3, 'name' => 'Ana', 'email' => 'ana@exemplo.ao', 'company_name' => 'Padaria']]);

        $this->deleteJson('/register/progresso')
            ->assertOk()
            ->assertJsonPath('passo', 1)
            ->assertJsonPath('campos.company_name', '');

        $this->assertNull(session('wizard_progress'));
    }

    public function test_a_pagina_inicial_leva_o_plano_escolhido_para_o_registo(): void
    {
        $pago = $this->planoPago();

        $this->get('/')
            ->assertOk()
            ->assertSee(route('register', ['plan' => $pago->slug]), false);
    }

    // ==================== cortesia: uma só, para sempre ====================

    /**
     * Um utilizador autenticado que já gastou o gratuito na primeira empresa
     * e volta ao assistente para abrir a segunda.
     */
    private function donoQueJaGastouOGratuito(): User
    {
        $gratis = Plan::create([
            'name' => 'Grátis Antigo', 'slug' => 'gratis-antigo-'.uniqid(),
            'description' => 'x', 'price_monthly' => 0, 'price_yearly' => 0,
            'trial_days' => 180, 'max_users' => 3, 'max_companies' => 3,
            'is_active' => false, 'order' => 99,
        ]);

        $primeira = Tenant::create([
            'name' => 'Primeira', 'slug' => 'primeira-'.uniqid(),
            'nif' => $this->nif(),
            'email' => 'a'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $primeira->subscriptions()->create([
            'plan_id' => $gratis->id, 'status' => 'expired',
            'billing_cycle' => 'monthly', 'amount' => 0,
            'trial_ends_at' => now()->subDay(),
        ]);

        $dono = User::create([
            'name' => 'Dono', 'email' => 'dono'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $primeira->id,
        ]);
        $dono->tenants()->syncWithoutDetaching([$primeira->id]);

        return $dono;
    }

    public function test_o_registo_recusa_o_gratuito_a_quem_ja_o_usou(): void
    {
        $gratis = $this->planoGratuito();
        $this->actingAs($this->donoQueJaGastouOGratuito());

        $estado = $this->props($this->get('/register'))['estado'];
        $this->assertNotNull($this->planoNoEstado($estado, $gratis->id)['recusa']);

        $this->postJson('/register/seguinte', ['passo' => 3, 'selected_plan_id' => $gratis->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('selected_plan_id');
    }

    public function test_um_plano_pago_passa_a_quem_ja_gastou_a_cortesia(): void
    {
        $pago = $this->planoPago();
        $pago->update(['trial_days' => 30]);
        $this->actingAs($this->donoQueJaGastouOGratuito());

        $estado = $this->props($this->get('/register'))['estado'];
        $this->assertFalse($this->planoNoEstado($estado, $pago->id)['com_teste']);
        $this->assertNotNull($estado['sem_teste']);

        $this->postJson('/register/seguinte', ['passo' => 3, 'selected_plan_id' => $pago->id])
            ->assertOk()
            ->assertJsonPath('passo', 4);
    }

    /** O plano do link deixa de valer se a conta já o gastou. */
    public function test_o_gratuito_vindo_do_link_devolve_a_escolha_quando_ja_foi_usado(): void
    {
        $gratis = $this->planoGratuito();
        $this->planoPago();
        $this->actingAs($this->donoQueJaGastouOGratuito());

        // Já autenticado, o historial é conhecido logo no arranque: o plano do
        // link não chega sequer a fixar-se.
        $this->assertFalse($this->props($this->get('/register?plan=amigo-teste'))['estado']['plano_veio_do_link']);

        $r = $this->postJson('/register/seguinte', ['passo' => 2, 'company_name' => 'Padaria Central', 'company_nif' => $this->nif(), 'selected_plan_id' => $gratis->id])
            ->assertOk()
            ->assertJsonPath('passo', 3);

        $this->assertNotNull($this->planoNoEstado($r->json(), $gratis->id)['recusa']);
    }

    /** Sem teste, o comprovativo volta a ser obrigatório. */
    public function test_sem_direito_a_teste_o_comprovativo_passa_a_ser_exigido(): void
    {
        $pago = $this->planoPago();
        $pago->update(['trial_days' => 30]);
        $this->actingAs($this->donoQueJaGastouOGratuito());

        $this->postJson('/register', [
            'passo' => 4, 'company_name' => 'Padaria Central', 'company_nif' => $this->nif(),
            'company_regime' => Tenant::REGIME_GERAL, 'selected_plan_id' => $pago->id, 'aceito_termos' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_reference', 'payment_proof']);
    }

    // ==================== regime fiscal ====================

    /** O passo da empresa pergunta o regime, com o Geral pré-escolhido. */
    public function test_o_passo_da_empresa_pergunta_o_regime(): void
    {
        $this->planoGratuito();

        $props = $this->props($this->get('/register'));

        $this->assertSame(Tenant::REGIME_GERAL, $props['estado']['campos']['company_regime']);
        $rotulos = array_column($props['regimes'], 'rotulo');
        $this->assertContains('Regime Geral', $rotulos);
        $this->assertContains('Regime Simplificado', $rotulos);
    }

    public function test_um_regime_inventado_nao_passa(): void
    {
        $this->planoGratuito();

        $this->postJson('/register/seguinte', ['passo' => 2, 'company_name' => 'Padaria Central', 'company_nif' => $this->nif(), 'company_regime' => 'regime_inventado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('company_regime');
    }

    /**
     * O NIF DO ALVARÁ COM ZEROS À ESQUERDA (22/09/2026): um empresário em nome
     * individual com NIF 0000083092 não passava do passo da empresa.
     */
    public function test_o_nif_de_dez_digitos_comecado_por_zero_passa_no_passo_da_empresa(): void
    {
        $this->planoGratuito();

        $this->postJson('/register/seguinte', ['passo' => 2, 'company_name' => 'Mamadou Comércio', 'company_nif' => '0000083092'])
            ->assertOk()
            ->assertJsonMissingValidationErrors('company_nif');

        $this->postJson('/register/seguinte', ['passo' => 2, 'company_name' => 'Mamadou Comércio', 'company_nif' => '004512345'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('company_nif');
    }

    /**
     * O CÓDIGO QUE O COOKIE LÁ PÔS SOZINHO NÃO VOLTA (22/09/2026). Um progresso
     * guardado antes da mudança (sem a marca `reseller_code_escrito`) trazia o
     * código do link; o escrito pela pessoa depois da mudança volta.
     */
    public function test_so_o_codigo_de_revendedor_escrito_volta_do_progresso(): void
    {
        $this->planoGratuito();

        $pagina = fn (array $progresso) => html_entity_decode(
            $this->withSession(['wizard_progress' => ['currentStep' => 2, 'name' => 'Ana', 'email' => 'ana@exemplo.ao'] + $progresso])
                ->get('/register')->assertOk()->getContent()
        );

        $this->assertStringNotContainsString('CELES1219', $pagina(['reseller_code' => 'CELES1219']));
        $this->assertStringContainsString('CELES1219', $pagina(['reseller_code' => 'CELES1219', 'reseller_code_escrito' => true]));
    }

    private function passo1(): array
    {
        return ['passo' => 1, 'name' => 'Ana Silva', 'email' => 'ana'.uniqid().'@exemplo.ao', 'password' => 'segredo-forte-123', 'password_confirmation' => 'segredo-forte-123'];
    }

    private function registoCompleto(Plan $plano, array $troca = []): array
    {
        return array_merge($this->passo1(), [
            'passo' => 4,
            'company_name' => 'Padaria Simplificada',
            'company_nif' => $this->nif(),
            'company_regime' => Tenant::REGIME_SIMPLIFICADO,
            'selected_plan_id' => $plano->id,
            'payment_method' => 'transfer',
            'aceito_termos' => 1,
        ], $troca);
    }

    /**
     * O regime escolhido fica gravado na empresa. É ele que decide os impostos
     * com que a empresa é provisionada.
     */
    public function test_o_regime_escolhido_fica_gravado_na_empresa_e_a_pessoa_entra(): void
    {
        $gratis = $this->planoGratuito();
        $dados = $this->registoCompleto($gratis);

        $this->postJson('/register', $dados)->assertOk()->assertJsonPath('ir_para', route('home'));

        $empresa = Tenant::where('nif', $dados['company_nif'])->first();
        $this->assertNotNull($empresa, 'O registo tinha de criar a empresa.');
        $this->assertSame(Tenant::REGIME_SIMPLIFICADO, $empresa->regime);

        $this->assertAuthenticated();
        $this->assertSame($dados['email'], auth()->user()->email);
        $this->assertNull(session('wizard_progress'));
        // O plano gratuito auto-activável arranca em teste, com os módulos.
        $this->assertSame('trial', $empresa->subscriptions()->first()->status);
    }

    /** O regime sobrevive ao refresh, como o resto da empresa. */
    public function test_o_regime_sobrevive_ao_refresh(): void
    {
        $this->planoGratuito();

        session(['wizard_progress' => [
            'currentStep' => 2,
            'name' => 'Ana',
            'email' => 'ana@exemplo.ao',
            'company_name' => 'Padaria Central',
            'company_nif' => '5417123456',
            'company_regime' => Tenant::REGIME_NAO_SUJEICAO,
        ]]);

        $this->assertSame(Tenant::REGIME_NAO_SUJEICAO, $this->props($this->get('/register'))['estado']['campos']['company_regime']);
    }

    // ==================== o fim ====================

    public function test_sem_aceitar_os_termos_nao_se_regista(): void
    {
        $gratis = $this->planoGratuito();

        $this->postJson('/register', $this->registoCompleto($gratis, ['aceito_termos' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('aceito_termos');
    }

    /** Quem pagou fica à espera da aprovação, com o comprovativo guardado no pedido. */
    public function test_o_plano_pago_com_comprovativo_fica_pendente_sem_modulos(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $pago = $this->planoPago();
        $dados = $this->registoCompleto($pago, [
            'payment_reference' => 'TRF123',
            'payment_proof' => UploadedFile::fake()->create('comprovativo.pdf', 100, 'application/pdf'),
        ]);

        $this->post('/register', $dados, ['Accept' => 'application/json'])->assertOk();

        $empresa = Tenant::where('nif', $dados['company_nif'])->firstOrFail();
        $this->assertSame('pending', $empresa->subscriptions()->first()->status);
        $pedido = \App\Models\Order::where('tenant_id', $empresa->id)->firstOrFail();
        $this->assertSame('TRF123', $pedido->payment_reference);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($pedido->payment_proof);
        $this->assertSame(0, $empresa->modules()->wherePivot('is_active', true)->count());
    }

    /** Autenticado, o nome e o email são os da conta — o pedido não os troca. */
    public function test_autenticado_comeca_na_empresa_e_nao_troca_de_nome(): void
    {
        $dono = $this->donoQueJaGastouOGratuito();
        $this->actingAs($dono);

        $estado = $this->props($this->get('/register'))['estado'];
        $this->assertSame(2, $estado['passo']);
        $this->assertTrue($estado['autenticado']);

        $this->putJson('/register/progresso', ['passo' => 2, 'name' => 'Outro Nome', 'email' => 'outro@exemplo.ao'])->assertOk();
        $this->assertSame($dono->email, session('wizard_progress.email'));
    }
}
