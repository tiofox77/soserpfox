<?php

namespace Tests\Feature\Agent;

use App\Models\AgentToken;
use App\Models\Order;
use App\Models\Plan;
use App\Services\Agent\EmissaoDeTokens;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * A API do agente externo.
 *
 * O que se guarda aqui não é que "funciona" — é que NÃO funciona nas
 * circunstâncias em que não deve: sem credencial, sem escopo, fora do IP,
 * revogada, expirada, e sem poder repetir escritas nem aprovar às cegas.
 */
class ApiDoAgenteTest extends TenantTestCase
{
    private string $emClaro;
    private AgentToken $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['agent.token.exigir_ips' => false]);

        $r = app(EmissaoDeTokens::class)->emitir(
            'openclaw-teste',
            $this->user,
            ['tenants:read', 'orders:read', 'orders:approve', 'followup:read'],
            [],
            30
        );

        $this->token   = $r['token'];
        $this->emClaro = $r['em_claro'];
    }

    private function comToken(?string $token = null): array
    {
        return ['Authorization' => 'Bearer ' . ($token ?? $this->emClaro)];
    }

    private function uuid(): string
    {
        return (string) Str::uuid();
    }

    private function pedidoPendente(): Order
    {
        $plano = Plan::first();

        return Order::create([
            'tenant_id'     => $this->tenant->id,
            'user_id'       => $this->user->id,
            'plan_id'       => $plano->id,
            'amount'        => 1000,
            'billing_cycle' => 'monthly',
            'status'        => 'pending',
        ]);
    }

    public function test_dono_da_plataforma_e_resolvido_sem_tenant(): void
    {
        $this->user->update(['phone' => '+244939729902']);

        $this->getJson('/api/agent/v1/followup/platform-owner', $this->comToken())
            ->assertOk()
            ->assertJsonPath('destinatario.handle', 'dono_plataforma')
            ->assertJsonPath('destinatario.telefone', '+244939729902');
    }

    public function test_sms_ao_dono_nao_aceita_numero_no_corpo(): void
    {
        $this->token->update(['scopes' => array_merge($this->token->scopes, ['followup:sms'])]);
        $this->user->update(['phone' => '+244939729902']);
        $mock = \Mockery::mock(\App\Services\SmsService::class);
        $mock->shouldReceive('send')->once()->with(
            '+244939729902', \Mockery::type('string'), 'agent_platform', $this->user->id, null
        )->andReturn(['success' => true, 'log_id' => 99]);
        $this->app->instance(\App\Services\SmsService::class, $mock);

        $this->postJson('/api/agent/v1/followup/sms/platform-owner', [
            'template' => 'teste_integracao', 'motivo' => 'Validar a gateway administrativa.',
            'phone_number' => '+244900000000',
        ], $this->comToken() + ['Idempotency-Key' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('destinatario', '+244939729902');
    }

    public function test_texto_livre_exige_escopo_proprio(): void
    {
        $this->postJson('/api/agent/v1/followup/free/email', [
            'tenant_id' => $this->tenant->id, 'destinatario' => 'responsavel',
            'assunto' => 'Aviso', 'mensagem' => 'Mensagem livre.',
            'motivo' => 'Comunicação operacional solicitada.',
        ], $this->comToken() + ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_email_livre_so_vai_para_handle_autorizado(): void
    {
        $this->token->update(['scopes' => array_merge($this->token->scopes, ['followup:free'])]);
        \Illuminate\Support\Facades\Mail::fake();

        $this->postJson('/api/agent/v1/followup/free/email', [
            'tenant_id' => $this->tenant->id, 'destinatario' => 'responsavel',
            'assunto' => 'NIF por regularizar',
            'mensagem' => 'A sua empresa foi suspensa. Regularize o NIF para reactivar o acesso.',
            'motivo' => 'Informar o cliente sobre a suspensão por NIF.',
            'email' => 'atacante@fora.example',
        ], $this->comToken() + ['Idempotency-Key' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('destinatario', $this->user->email);

        // A garantia relevante fica também na resposta: o email livre do
        // corpo foi ignorado e o destino veio do handle.
    }

    // ══════════════ Autenticação ══════════════

    public function test_sem_credencial_nao_entra(): void
    {
        $this->getJson('/api/agent/v1/me')->assertStatus(401);
    }

    public function test_credencial_inventada_nao_entra(): void
    {
        $falsa = 'oclaw_aaaaaaaaaaaa_' . str_repeat('b', 64);

        $this->getJson('/api/agent/v1/me', $this->comToken($falsa))->assertStatus(401);
    }

    public function test_credencial_valida_entra(): void
    {
        $this->getJson('/api/agent/v1/me', $this->comToken())
            ->assertOk()
            ->assertJsonPath('agente', 'openclaw-teste');
    }

    public function test_credencial_revogada_deixa_de_entrar(): void
    {
        app(EmissaoDeTokens::class)->revogar($this->token, null, 'teste');

        $this->getJson('/api/agent/v1/me', $this->comToken())
            ->assertStatus(401)
            ->assertJsonPath('erro', 'credencial_revogada');
    }

    public function test_credencial_expirada_deixa_de_entrar(): void
    {
        $this->token->update(['expires_at' => now()->subDay()]);

        $this->getJson('/api/agent/v1/me', $this->comToken())
            ->assertStatus(401)
            ->assertJsonPath('erro', 'credencial_expirada');
    }

    public function test_ip_fora_da_lista_e_recusado(): void
    {
        $this->token->update(['allowed_ips' => ['203.0.113.7']]);

        $this->getJson('/api/agent/v1/me', $this->comToken())
            ->assertStatus(403)
            ->assertJsonPath('erro', 'ip_nao_autorizado');
    }

    public function test_a_api_pode_ser_desligada_toda(): void
    {
        config(['agent.activa' => false]);

        $this->getJson('/api/agent/v1/me', $this->comToken())->assertStatus(503);
    }

    // ══════════════ Escopos ══════════════

    public function test_escopo_em_falta_e_recusado_e_diz_qual(): void
    {
        // A credencial do setUp não leva health:read.
        $this->getJson('/api/agent/v1/health/checks', $this->comToken())
            ->assertStatus(403)
            ->assertJsonPath('erro', 'escopo_em_falta')
            ->assertJsonPath('escopo', 'health:read');
    }

    public function test_ler_pedidos_nao_da_direito_a_recusar(): void
    {
        $order = $this->pedidoPendente();

        $this->postJson(
            '/api/agent/v1/orders/' . $order->id . '/reject',
            ['expected_plan_id' => $order->plan_id, 'motivo' => 'comprovativo ilegivel'],
            array_merge($this->comToken(), ['Idempotency-Key' => $this->uuid()])
        )->assertStatus(403);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_le_empresas_com_o_escopo_certo(): void
    {
        $this->getJson('/api/agent/v1/tenants', $this->comToken())
            ->assertOk()
            ->assertJsonStructure(['empresas', 'total']);
    }

    // ══════════════ Escritas ══════════════

    public function test_escrita_sem_idempotency_key_e_recusada(): void
    {
        $order = $this->pedidoPendente();

        $this->postJson(
            '/api/agent/v1/orders/' . $order->id . '/approve',
            [
                'expected_plan_id' => $order->plan_id,
                'expected_amount'  => $order->amount,
                'motivo'           => 'o comprovativo confere com o valor',
            ],
            $this->comToken()
        )
            ->assertStatus(422)
            ->assertJsonPath('erro', 'falta_idempotency_key');
    }

    public function test_aprovar_exige_provar_que_leu_o_pedido(): void
    {
        $order = $this->pedidoPendente();

        $this->postJson(
            '/api/agent/v1/orders/' . $order->id . '/approve',
            [
                'expected_plan_id' => $order->plan_id,
                'expected_amount'  => (float) $order->amount + 5000,   // não bate
                'motivo'           => 'o comprovativo confere com o valor',
            ],
            array_merge($this->comToken(), ['Idempotency-Key' => $this->uuid()])
        )
            ->assertStatus(409)
            ->assertJsonPath('erro', 'pedido_mudou');

        $this->assertSame('pending', $order->fresh()->status, 'não podia ter escrito');
    }

    public function test_dry_run_nao_escreve(): void
    {
        $order = $this->pedidoPendente();

        $this->postJson(
            '/api/agent/v1/orders/' . $order->id . '/approve',
            [
                'expected_plan_id' => $order->plan_id,
                'expected_amount'  => $order->amount,
                'motivo'           => 'quero ver os efeitos antes de decidir',
                'dry_run'          => true,
            ],
            array_merge($this->comToken(), ['Idempotency-Key' => $this->uuid()])
        )
            ->assertOk()
            ->assertJsonPath('dry_run', true);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_mesma_chave_nao_aprova_duas_vezes(): void
    {
        $order = $this->pedidoPendente();
        $chave = $this->uuid();

        $corpo = [
            'expected_plan_id' => $order->plan_id,
            'expected_amount'  => $order->amount,
            'motivo'           => 'o comprovativo confere com o valor',
        ];

        $rota = '/api/agent/v1/orders/' . $order->id . '/approve';

        $primeira = $this->postJson($rota, $corpo,
            array_merge($this->comToken(), ['Idempotency-Key' => $chave]));

        $segunda = $this->postJson($rota, $corpo,
            array_merge($this->comToken(), ['Idempotency-Key' => $chave]));

        $primeira->assertOk();
        $segunda->assertHeader('Idempotent-Replay', 'true');

        $this->assertSame('approved', $order->fresh()->status);
    }

    public function test_acima_do_tecto_de_valor_nao_aprova(): void
    {
        config(['agent.aprovacao.valor_maximo' => 100]);

        $order = $this->pedidoPendente();

        $this->postJson(
            '/api/agent/v1/orders/' . $order->id . '/approve',
            [
                'expected_plan_id' => $order->plan_id,
                'expected_amount'  => $order->amount,
                'motivo'           => 'o comprovativo confere com o valor',
            ],
            array_merge($this->comToken(), ['Idempotency-Key' => $this->uuid()])
        )
            ->assertStatus(403)
            ->assertJsonPath('erro', 'nao_permitido');

        $this->assertSame('pending', $order->fresh()->status);
    }

    // ══════════════ Emissão de credenciais ══════════════

    public function test_uma_credencial_sem_escopos_nao_se_emite(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(EmissaoDeTokens::class)->emitir('vazia', $this->user, [], [], 30);
    }

    public function test_a_validade_tem_tecto(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(EmissaoDeTokens::class)->emitir('eterna', $this->user, ['tenants:read'], [], 9999);
    }

    public function test_o_segredo_nao_fica_guardado_em_claro(): void
    {
        $segredo = explode('_', $this->emClaro)[2];

        $this->assertNotSame($segredo, $this->token->token_hash);
        $this->assertSame(AgentToken::hashSegredo($segredo), $this->token->token_hash);
    }
}
