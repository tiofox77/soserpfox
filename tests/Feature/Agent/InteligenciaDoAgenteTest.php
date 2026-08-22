<?php

namespace Tests\Feature\Agent;

use App\Models\AgentToken;
use App\Services\Agent\EmissaoDeTokens;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

class InteligenciaDoAgenteTest extends TenantTestCase
{
    private string $segredo;
    private AgentToken $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.token.exigir_ips' => false]);
        $r = app(EmissaoDeTokens::class)->emitir('openclaw-gerente', $this->user,
            ['tenants:read', 'analytics:read', 'logs:read', 'system:read', 'system:write'], [], 30);
        $this->segredo = $r['em_claro'];
        $this->token = $r['token'];
    }

    private function headers(bool $escrita = false): array
    {
        $h = ['Authorization' => 'Bearer ' . $this->segredo];
        if ($escrita) $h['Idempotency-Key'] = (string) Str::uuid();
        return $h;
    }

    public function test_ve_metricas_globais_e_utilizadores(): void
    {
        $this->getJson('/api/agent/v1/analytics/overview?dias=30', $this->headers())
            ->assertOk()->assertJsonStructure(['empresas' => ['total', 'activas'], 'utilizadores', 'erros', 'planos']);
        $this->getJson('/api/agent/v1/analytics/users?estado=activo', $this->headers())
            ->assertOk()->assertJsonPath('utilizadores.0.id', $this->user->id);
    }

    public function test_filtros_de_empresa_sao_aplicados_na_query(): void
    {
        $this->getJson('/api/agent/v1/tenants?pesquisa=' . urlencode($this->tenant->name) . '&activa=1', $this->headers())
            ->assertOk()->assertJsonPath('empresas.0.id', $this->tenant->id);
    }

    public function test_filtro_suspenso_filtra_antes_de_paginar_e_corrige_totais(): void
    {
        $this->tenant->update(['is_active' => false]);

        $r = $this->getJson('/api/agent/v1/tenants?estado=suspenso&por_pagina=5', $this->headers())
            ->assertOk()->assertJsonPath('empresas.0.id', $this->tenant->id);

        $this->assertSame(count($r->json('empresas')), $r->json('total'));
        $this->assertSame('Suspenso', $r->json('empresas.0.estado'));
    }

    public function test_sem_escopo_analitico_nao_ve_analitica(): void
    {
        $this->token->update(['scopes' => ['tenants:read']]);
        $this->getJson('/api/agent/v1/analytics/overview', $this->headers())->assertForbidden();
    }

    public function test_estado_tecnico_nao_expoe_segredos(): void
    {
        $r = $this->getJson('/api/agent/v1/system/status', $this->headers())->assertOk();
        $r->assertJsonStructure(['aplicacao', 'servicos', 'filas', 'erros_abertos', 'hora']);
        $this->assertStringNotContainsString('APP_KEY', $r->getContent());
    }

    public function test_accao_operacional_exige_confirmacao_literal(): void
    {
        $this->postJson('/api/agent/v1/system/actions', [
            'accao' => 'cache_limpar', 'confirmacao' => 'sim', 'motivo' => 'teste de confirmação obrigatória',
        ], $this->headers(true))->assertUnprocessable();
    }
}
