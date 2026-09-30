<?php

namespace Tests\Feature\Agent;

use App\Models\AgentToken;
use App\Services\Agent\EmissaoDeTokens;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

class AdminDoAgenteTest extends TenantTestCase
{
    private array $headers;
    private AgentToken $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.token.exigir_ips' => false]);
        $this->user->forceFill(['is_super_admin' => true, 'is_active' => true])->save();
        $r = app(EmissaoDeTokens::class)->emitir('admin-teste', $this->user,
            array_keys(config('agent.escopos')), [], 365);
        $this->token = $r['token'];
        $this->headers = ['Authorization' => 'Bearer '.$r['em_claro']];
        auth()->forgetUser();
    }

    public function test_leitura_sem_sessao_e_restauro_do_contexto(): void
    {
        $team = getPermissionsTeamId();
        $this->getJson('/api/agent/v1/admin/empresas/'.$this->tenant->id.'/utilizadores', $this->headers)
            ->assertOk()->assertJsonPath('empresa.id', $this->tenant->id);
        $this->assertFalse(auth()->check());
        $this->assertEquals($team, getPermissionsTeamId());
    }

    public function test_escopo_em_falta_e_responsavel_desactivado_bloqueiam(): void
    {
        $this->token->update(['scopes' => ['tenants:read']]);
        $this->getJson('/api/agent/v1/admin/modulos', $this->headers)->assertForbidden();
        $this->token->update(['scopes' => ['modules:read']]);
        $this->user->update(['is_active' => false]);
        $this->getJson('/api/agent/v1/admin/modulos', $this->headers)->assertForbidden();
    }

    public function test_escrita_exige_confirmacao_e_motivo(): void
    {
        $this->postJson('/api/agent/v1/admin/modulos', [], $this->headers + ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors(['confirmar', 'motivo']);
    }

    public function test_mesma_chave_nao_pode_ser_usada_noutra_rota(): void
    {
        $headers = $this->headers + ['Idempotency-Key' => (string) Str::uuid()];
        $body = ['confirmar' => true, 'motivo' => 'Teste de isolamento das repeticoes.'];
        $this->postJson('/api/agent/v1/admin/modulos', $body, $headers)->assertUnprocessable();
        $this->postJson('/api/agent/v1/admin/pedidos-de-estabelecimentos/999999/recusar', $body, $headers)
            ->assertUnprocessable()->assertJsonPath('erro', 'idempotency_key_reutilizada');
    }

    public function test_prazo_anual_e_escopos_desconhecidos(): void
    {
        $this->assertEqualsWithDelta(365, now()->diffInDays($this->token->expires_at), 0.01);
        $this->expectException(\InvalidArgumentException::class);
        app(EmissaoDeTokens::class)->emitir('errado', $this->user, ['tenants:read', 'inventado:write'], [], 365);
    }

    public function test_replay_nao_contorna_escopo_revogado(): void
    {
        $headers = $this->headers + ['Idempotency-Key' => (string) Str::uuid()];
        $body = ['confirmar' => true, 'motivo' => 'Teste de revogacao de permissao.'];
        $this->postJson('/api/agent/v1/admin/modulos', $body, $headers)->assertUnprocessable();
        $this->token->update(['scopes' => ['modules:read']]);
        $this->postJson('/api/agent/v1/admin/modulos', $body, $headers)->assertForbidden();
    }

    public function test_licenca_do_agente_exige_maquina_e_escolha_explicita_de_modulos(): void
    {
        $this->postJson('/api/agent/v1/admin/licenciamento/licencas', [
            'confirmar' => true, 'motivo' => 'Validacao de licenciamento offline.',
        ], $this->headers + ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors(['fingerprint', 'todos_os_modulos']);
    }

    public function test_provisionamento_consume_apenas_hash_e_revoga_anterior(): void
    {
        $base = sys_get_temp_dir().'/agent-test-'.Str::uuid();
        mkdir($base.'/app/agente', 0700, true);
        $previousStorage = app()->storagePath();
        app()->useStoragePath($base);
        try {
            $prefix = bin2hex(random_bytes(6));
            $hash = hash('sha256', random_bytes(32));
            file_put_contents($base.'/app/agente/provisionar.json', json_encode([
                'prefix' => $prefix, 'token_hash' => $hash, 'owner_email' => $this->user->email,
                'replace_prefix' => $this->token->prefix, 'issued_at' => now()->toIso8601String(),
                'days' => 365, 'scopes' => ['tenants:read'],
            ]));
            $this->assertSame(0, Artisan::call('agente:provisionar'));
            $this->assertStringNotContainsString($hash, Artisan::output());
            $this->assertNotNull($this->token->fresh()->revoked_at);
            $this->assertFileDoesNotExist($base.'/app/agente/provisionar.json');
            $this->assertSame(1, Artisan::call('agente:provisionar'));
        } finally {
            app()->useStoragePath($previousStorage);
            if (is_file($base.'/app/agente/provisionar.json')) unlink($base.'/app/agente/provisionar.json');
            @rmdir($base.'/app/agente'); @rmdir($base.'/app'); @rmdir($base);
        }
    }
}
