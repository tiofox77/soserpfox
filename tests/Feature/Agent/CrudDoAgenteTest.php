<?php

namespace Tests\Feature\Agent;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Agent\EmissaoDeTokens;
use Tests\TenantTestCase;

/**
 * O CRUD da plataforma pela API do agente.
 *
 * O agente sabia ler quase tudo e escrever quase nada — era um observador com
 * opinião. Passa a poder criar, corrigir e apagar.
 *
 * O QUE ESTES TESTES PROTEGEM
 * ---------------------------
 * Não a decisão: se uma empresa deve ou não ser suspensa é assunto de quem
 * gere a plataforma, e o agente foi mandado decidi-lo. O que aqui se protege é
 * o acidente:
 *
 *   1. cada escopo abre só a sua porta — poder corrigir não é poder destruir;
 *   2. apagar exige escrever o nome por extenso, e a guarda fiscal manda
 *      sempre: uma empresa com documentos comunicados à AGT não se apaga;
 *   3. o que muda fica com motivo escrito e rasto.
 */
class CrudDoAgenteTest extends TenantTestCase
{
    private string $emClaro;

    protected function setUp(): void
    {
        parent::setUp();

        config(['agent.token.exigir_ips' => false]);

        $this->emClaro = $this->tokenCom([
            'tenants:read', 'tenants:write', 'tenants:delete',
            'plans:read', 'plans:write',
        ]);
    }

    private function tokenCom(array $escopos): string
    {
        return app(EmissaoDeTokens::class)->emitir(
            'openclaw-crud-' . uniqid(),
            $this->user,
            $escopos,
            [],
            30
        )['em_claro'];
    }

    /** Cabeçalhos. A escrita exige Idempotency-Key — sem ela não se escreve. */
    private function h(?string $token = null, bool $idempotente = true): array
    {
        $h = ['Authorization' => 'Bearer ' . ($token ?? $this->emClaro)];

        if ($idempotente) {
            $h['Idempotency-Key'] = (string) \Illuminate\Support\Str::uuid();
        }

        return $h;
    }

    private function empresaVazia(string $nome = 'Empresa Sem Nada'): Tenant
    {
        return Tenant::create([
            'name' => $nome, 'slug' => 'vazia-' . uniqid(), 'is_active' => true,
        ]);
    }

    // ══════════════ empresas: criar ══════════════

    public function test_cria_uma_empresa(): void
    {
        $r = $this->postJson('/api/agent/v1/tenants', [
            'nome'   => 'Padaria Sol Nascente',
            'nif'    => '5417123456',
            'email'  => 'geral@padariasol.ao',
            'motivo' => 'Cliente pediu por telefone e ficou de enviar os documentos.',
        ], $this->h());

        $r->assertCreated()
            ->assertJsonPath('empresa.nome', 'Padaria Sol Nascente')
            ->assertJsonPath('empresa.nif', '5417123456');

        $this->assertSame(1, Tenant::where('name', 'Padaria Sol Nascente')->count());
    }

    /**
     * Nasce INACTIVA.
     *
     * Uma empresa criada por um agente não deve poder ser usada antes de
     * alguém a olhar.
     */
    public function test_a_empresa_criada_nasce_inactiva(): void
    {
        $this->postJson('/api/agent/v1/tenants', [
            'nome' => 'Loja do Zé', 'motivo' => 'Registo feito ao balcão, a confirmar.',
        ], $this->h())->assertCreated()->assertJsonPath('empresa.activa', false);
    }

    public function test_criar_sem_motivo_e_recusado(): void
    {
        $this->postJson('/api/agent/v1/tenants', ['nome' => 'Sem Motivo Lda'], $this->h())
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }

    public function test_sem_idempotency_key_nao_escreve(): void
    {
        $this->postJson('/api/agent/v1/tenants', [
            'nome' => 'Sem Chave Lda', 'motivo' => 'A testar a idempotência.',
        ], $this->h(idempotente: false))->assertStatus(422);

        $this->assertSame(0, Tenant::where('name', 'Sem Chave Lda')->count());
    }

    // ══════════════ empresas: corrigir ══════════════

    /**
     * O caso que motivou isto: um NIF de pessoa singular num campo de empresa
     * CORRIGE-SE — não se suspende quem paga por causa dele.
     */
    public function test_corrige_o_nif_de_uma_empresa(): void
    {
        $empresa = $this->empresaVazia();
        $empresa->update(['nif' => '2417123456']);   // começa por 2: pessoa singular

        $this->patchJson('/api/agent/v1/tenants/' . $empresa->id, [
            'nif'    => '5417123456',
            'motivo' => 'NIF estava com o número do BI; confirmado com o cliente.',
        ], $this->h())
            ->assertOk()
            ->assertJsonPath('empresa.nif', '5417123456')
            ->assertJsonPath('empresa.nif_estado', 'valido');

        $this->assertSame('5417123456', $empresa->fresh()->nif);
    }

    public function test_diz_o_que_estava_la_antes(): void
    {
        $empresa = $this->empresaVazia('Nome Antigo');

        $this->patchJson('/api/agent/v1/tenants/' . $empresa->id, [
            'nome'   => 'Nome Novo',
            'motivo' => 'Empresa mudou de nome comercial.',
        ], $this->h())
            ->assertOk()
            ->assertJsonPath('antes.name', 'Nome Antigo')
            ->assertJsonPath('mudou.0', 'name');
    }

    public function test_enviar_o_que_ja_la_esta_nao_conta_como_mudanca(): void
    {
        $empresa = $this->empresaVazia('Igual');

        $this->patchJson('/api/agent/v1/tenants/' . $empresa->id, [
            'nome' => 'Igual', 'motivo' => 'A confirmar que nada muda.',
        ], $this->h())->assertOk()->assertJsonPath('mudou', []);
    }

    public function test_patch_vazio_e_noop_e_nao_erro_500(): void
    {
        $empresa = $this->empresaVazia('Sem Alteração');

        $this->patchJson('/api/agent/v1/tenants/' . $empresa->id, [], $this->h())
            ->assertOk()->assertJsonPath('mudou', []);
    }

    public function test_patch_pode_mudar_estado_com_motivo(): void
    {
        $empresa = $this->empresaVazia('Estado por Patch');

        $this->patchJson('/api/agent/v1/tenants/' . $empresa->id, [
            'estado' => 'suspenso', 'motivo' => 'Suspensão pedida para validar a API v2.',
        ], $this->h())->assertOk()->assertJsonPath('empresa.activa', false);
    }

    // ══════════════ empresas: apagar ══════════════

    public function test_apaga_uma_empresa_sem_actividade(): void
    {
        $empresa = $this->empresaVazia('Criada Por Engano');

        $this->deleteJson('/api/agent/v1/tenants/' . $empresa->id, [
            'confirmo_o_nome' => 'Criada Por Engano',
            'motivo'          => 'Registo duplicado, criado por engano no mesmo dia.',
        ], $this->h())->assertOk();

        $this->assertNull(Tenant::find($empresa->id));
    }

    /**
     * Escrever o nome por extenso é a diferença entre confirmar e carregar por
     * reflexo.
     */
    public function test_sem_o_nome_certo_nao_apaga(): void
    {
        $empresa = $this->empresaVazia('Nome Exacto Lda');

        $this->deleteJson('/api/agent/v1/tenants/' . $empresa->id, [
            'confirmo_o_nome' => 'nome errado',
            'motivo'          => 'A testar a confirmação por nome.',
        ], $this->h())->assertStatus(422);

        $this->assertNotNull(Tenant::find($empresa->id));
    }

    /**
     * A guarda fiscal manda, e não é negociável por confirmação nenhuma.
     */
    public function test_uma_empresa_com_facturas_nao_se_apaga(): void
    {
        SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'invoice_number' => 'FT-' . uniqid(),
            'invoice_date'   => now(), 'due_date' => now(),
            'subtotal' => 100, 'total' => 100, 'paid_amount' => 0,
            'status' => 'paid', 'created_by' => $this->user->id,
        ]);

        $this->deleteJson('/api/agent/v1/tenants/' . $this->tenant->id, [
            'confirmo_o_nome' => $this->tenant->name,
            'motivo'          => 'A testar a guarda fiscal.',
        ], $this->h())
            ->assertStatus(409)
            ->assertJsonStructure(['erro', 'impedimento', 'actividade', 'alternativa']);

        $this->assertNotNull(Tenant::find($this->tenant->id));
    }

    /** Ver o que se perde ANTES de decidir. */
    public function test_a_previsao_de_eliminacao_nao_apaga_nada(): void
    {
        $empresa = $this->empresaVazia();

        $this->getJson('/api/agent/v1/tenants/' . $empresa->id . '/eliminacao', $this->h(idempotente: false))
            ->assertOk()
            ->assertJsonPath('pode_apagar', true)
            ->assertJsonStructure(['o_que_se_perde', 'comunicou_agt', 'aviso']);

        $this->assertNotNull(Tenant::find($empresa->id));
    }

    // ══════════════ os escopos separam poderes ══════════════

    /**
     * Poder corrigir NÃO é poder destruir.
     *
     * `tenants:delete` é um escopo próprio de propósito: dar ao agente a
     * capacidade de arranjar uma empresa não lhe deve dar a de a apagar.
     */
    public function test_quem_so_tem_write_nao_apaga(): void
    {
        $empresa = $this->empresaVazia('Protegida Lda');
        $token = $this->tokenCom(['tenants:read', 'tenants:write']);

        $this->deleteJson('/api/agent/v1/tenants/' . $empresa->id, [
            'confirmo_o_nome' => 'Protegida Lda',
            'motivo'          => 'A testar a separação de escopos.',
        ], $this->h($token))->assertStatus(403);

        $this->assertNotNull(Tenant::find($empresa->id));
    }

    public function test_quem_so_le_nao_escreve(): void
    {
        $token = $this->tokenCom(['tenants:read']);

        $this->postJson('/api/agent/v1/tenants', [
            'nome' => 'Não Devia Existir', 'motivo' => 'A testar o escopo de leitura.',
        ], $this->h($token))->assertStatus(403);

        $this->assertSame(0, Tenant::where('name', 'Não Devia Existir')->count());
    }

    // ══════════════ planos ══════════════

    private function modulo(string $slug): Module
    {
        return Module::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'is_core' => false]);
    }

    public function test_cria_um_plano_fora_da_montra(): void
    {
        $this->modulo('invoicing');

        $this->postJson('/api/agent/v1/plans', [
            'nome'         => 'Plano Negociado Alfa',
            'preco_mensal' => 30000,
            'modulos'      => ['invoicing'],
            'motivo'       => 'Negociado com o cliente na reunião de ontem.',
        ], $this->h())
            ->assertCreated()
            ->assertJsonPath('plano.na_montra', false)
            ->assertJsonPath('plano.activo', true)
            ->assertJsonPath('plano.modulos.0', 'invoicing');
    }

    /**
     * Um plano a zero é tratado em todo o sistema como "o plano gratuito" e
     * queima a cortesia única do cliente.
     */
    public function test_um_plano_a_zero_e_recusado_com_a_razao(): void
    {
        $this->postJson('/api/agent/v1/plans', [
            'nome' => 'Plano Grátis', 'preco_mensal' => 0,
            'motivo' => 'A testar a armadilha do plano a zero.',
        ], $this->h())
            ->assertStatus(422)
            ->assertJsonFragment(['erro' => 'A mensalidade tem de ser maior que zero. Um plano a zero é tratado '
                . 'como o plano gratuito e gasta a cortesia única do cliente. Para oferecer, '
                . 'ponha um valor simbólico e faça o desconto na cobrança.']);
    }

    public function test_actualiza_o_preco_de_um_plano(): void
    {
        $plano = Plan::create([
            'name' => 'Plano X', 'slug' => 'plano-x-' . uniqid(),
            'price_monthly' => 10000, 'price_yearly' => 100000,
            'max_users' => 5, 'is_active' => true,
        ]);

        $this->patchJson('/api/agent/v1/plans/' . $plano->id, [
            'preco_mensal' => 12000,
            'motivo'       => 'Actualização de preços de Agosto.',
        ], $this->h())
            ->assertOk()
            ->assertJsonPath('plano.preco_mensal', 12000)
            ->assertJsonPath('antes.price_monthly', '10000.00');
    }

    /**
     * Desactivar substitui apagar: há subscrições a apontar-lhe, e apagá-lo
     * deixava clientes com uma subscrição órfã.
     */
    public function test_desactivar_um_plano_avisa_de_quem_ainda_la_esta(): void
    {
        $plano = Plan::create([
            'name' => 'Plano Y', 'slug' => 'plano-y-' . uniqid(),
            'price_monthly' => 5000, 'max_users' => 3, 'is_active' => true,
        ]);

        $this->tenant->subscriptions()->create([
            'plan_id' => $plano->id, 'status' => 'active', 'billing_cycle' => 'monthly',
            'amount' => 5000, 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        $this->postJson('/api/agent/v1/plans/' . $plano->id . '/estado', [
            'activo' => false,
            'motivo' => 'Plano descontinuado; substituído pelo Business.',
        ], $this->h())
            ->assertOk()
            ->assertJsonPath('plano.activo', false)
            ->assertJsonPath('subscricoes_vivas', 1);

        $this->assertFalse((bool) $plano->fresh()->is_active);
    }

    public function test_o_catalogo_de_planos_traz_os_modulos(): void
    {
        $this->modulo('treasury');
        $plano = Plan::create([
            'name' => 'Plano Z', 'slug' => 'plano-z-' . uniqid(),
            'price_monthly' => 7000, 'max_users' => 3, 'is_active' => true,
        ]);
        $plano->modules()->sync([$this->modulo('treasury')->id]);

        $this->getJson('/api/agent/v1/plans/catalogo', $this->h(idempotente: false))
            ->assertOk()
            ->assertJsonStructure(['planos' => [['id', 'slug', 'nome', 'preco_mensal', 'modulos']]]);
    }

    /** `plans/catalogo` não pode ser confundido com `plans/{plan}`. */
    public function test_o_catalogo_nao_colide_com_o_detalhe(): void
    {
        $this->getJson('/api/agent/v1/plans/catalogo', $this->h(idempotente: false))
            ->assertOk()
            ->assertJsonStructure(['planos']);
    }
}
