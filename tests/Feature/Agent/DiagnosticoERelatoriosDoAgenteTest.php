<?php

namespace Tests\Feature\Agent;

use App\Models\AgentToken;
use App\Services\Agent\EmissaoDeTokens;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * O DIAGNÓSTICO, OS ACESSOS, OS RELATÓRIOS E O ANALYTICS DO AGENTE.
 *
 * Tudo só de leitura, com os escopos que já existiam. O que se prova aqui é que
 * cada pergunta tem resposta com os números certos — rascunhos fora das vendas,
 * o acesso contado na empresa certa, falhas agrupadas por email — e que sem o
 * escopo a porta fica fechada.
 */
class DiagnosticoERelatoriosDoAgenteTest extends TenantTestCase
{
    private string $segredo;

    private AgentToken $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.token.exigir_ips' => false]);
        $r = app(EmissaoDeTokens::class)->emitir('openclaw-diagnostico', $this->user,
            ['tenants:read', 'health:read', 'logs:read', 'analytics:read', 'system:read'], [], 30);
        $this->segredo = $r['em_claro'];
        $this->token = $r['token'];
    }

    private function h(): array
    {
        return ['Authorization' => 'Bearer ' . $this->segredo];
    }

    public function test_o_catalogo_lista_as_rotas_com_o_escopo_e_se_esta_credencial_pode(): void
    {
        $r = $this->getJson('/api/agent/v1/catalogo', $this->h())->assertOk();

        $rotas = collect($r->json('rotas'))->keyBy(fn ($x) => $x['metodo'] . ' ' . $x['caminho']);

        $this->assertSame('health:read', $rotas['GET tenants/{tenant}/diagnostico']['escopo']);
        $this->assertTrue($rotas['GET tenants/{tenant}/diagnostico']['pode']);
        $this->assertNotNull($rotas['GET reports/plataforma']['descricao']);
        $this->assertFalse($rotas['DELETE tenants/{tenant}']['pode'], 'esta credencial não apaga empresas');
        $this->assertTrue($rotas['DELETE tenants/{tenant}']['escrita']);
    }

    public function test_diagnostico_da_empresa_diz_o_que_falta(): void
    {
        $r = $this->getJson("/api/agent/v1/tenants/{$this->tenant->id}/diagnostico", $this->h())->assertOk();

        $r->assertJsonStructure(['empresa', 'utilizadores', 'papeis', 'subscricao', 'modulos', 'facturacao' => ['impostos', 'series'], 'erros', 'faltas', 'completa']);
        $this->assertSame($r->json('faltas') === [], $r->json('completa'));
    }

    public function test_sem_o_escopo_de_saude_nao_ha_diagnostico(): void
    {
        $this->token->update(['scopes' => ['tenants:read']]);

        $this->getJson("/api/agent/v1/tenants/{$this->tenant->id}/diagnostico", $this->h())->assertForbidden();
        $this->getJson('/api/agent/v1/reports/plataforma', $this->h())->assertForbidden();
        $this->getJson('/api/agent/v1/logs/acessos/online', $this->h())->assertForbidden();
    }

    public function test_a_agt_por_ambiente_responde(): void
    {
        $this->getJson('/api/agent/v1/health/agt?tenant_id=' . $this->tenant->id, $this->h())
            ->assertOk()->assertJsonStructure(['resumo' => ['paradas_de_outro_ambiente', 'por_confirmar_ha_mais_de_24h', 'recusadas_7d'], 'empresas']);
    }

    public function test_o_diagnostico_do_sistema_tem_relogios_e_migracoes_e_nenhum_segredo(): void
    {
        $r = $this->getJson('/api/agent/v1/system/diagnostico', $this->h())->assertOk();

        $r->assertJsonStructure(['aplicacao' => ['php', 'laravel'], 'deploy', 'migracoes_por_correr' => ['total'], 'relogios' => ['php', 'base_de_dados', 'diferenca_minutos'], 'disco', 'log', 'filas']);
        $corpo = $r->getContent();
        $this->assertStringNotContainsString((string) config('app.key'), $corpo);
        $this->assertStringNotContainsString('APP_KEY', $corpo);
        if (config('database.connections.mysql.password')) {
            $this->assertStringNotContainsString((string) config('database.connections.mysql.password'), $corpo);
        }
    }

    /** O acesso conta na empresa em que a pessoa esteve, não em todas as dela. */
    public function test_acessos_da_empresa_usam_o_acesso_nesta_empresa(): void
    {
        DB::table('tenant_user')->where('tenant_id', $this->tenant->id)->where('user_id', $this->user->id)
            ->update(['ultimo_acesso_em' => now()->subHour()]);

        $r = $this->getJson("/api/agent/v1/tenants/{$this->tenant->id}/acessos", $this->h())->assertOk();

        $eu = collect($r->json('pessoas'))->firstWhere('user_id', $this->user->id);
        $this->assertNotNull($eu);
        $this->assertNotNull($eu['ultimo_acesso_nesta_empresa']);
        $this->assertArrayHasKey('falhas_de_entrada_30d', $eu);
        $this->assertGreaterThanOrEqual(1, $r->json('resumo.entraram_30d'));
    }

    /** Cinco falhas do mesmo email é alguém a tentar, não alguém a errar. */
    public function test_o_resumo_de_acessos_aponta_os_suspeitos_de_forca_bruta(): void
    {
        $email = 'alvo' . uniqid() . '@exemplo.ao';
        for ($i = 0; $i < 6; $i++) {
            app(AuditRecorder::class)->acto('login_falhado', $this->tenant->id, ['guarda' => 'web', 'email' => $email]);
        }
        app(AuditRecorder::class)->despejar();

        $r = $this->getJson('/api/agent/v1/logs/acessos/resumo?horas=1&tenant_id=' . $this->tenant->id, $this->h())->assertOk();

        $suspeito = collect($r->json('suspeitos.emails'))->firstWhere('email', $email);
        $this->assertNotNull($suspeito, 'seis falhas do mesmo email não apareceram como suspeitas');
        $this->assertSame(6, $suspeito['falhas']);
    }

    public function test_quem_esta_online_e_as_personificacoes_respondem(): void
    {
        $this->getJson('/api/agent/v1/logs/acessos/online?minutos=30', $this->h())
            ->assertOk()->assertJsonStructure(['minutos', 'total', 'pessoas']);

        app(AuditRecorder::class)->acto('personificacao.entrou', $this->tenant->id, ['utilizador' => $this->user->id]);
        app(AuditRecorder::class)->despejar();

        $r = $this->getJson('/api/agent/v1/logs/personificacoes?tenant_id=' . $this->tenant->id, $this->h())->assertOk();
        $this->assertSame('personificacao.entrou', $r->json('personificacoes.0.evento'));
    }

    public function test_os_erros_filtram_por_empresa(): void
    {
        $this->getJson('/api/agent/v1/logs/errors?estado=todos&tenant_id=' . $this->tenant->id, $this->h())
            ->assertOk()->assertJsonStructure(['erros', 'resumo']);
    }

    /** Um rascunho não foi emitido: não entra nas vendas. */
    public function test_as_vendas_da_empresa_contam_so_documentos_emitidos(): void
    {
        foreach (['sent', 'paid', 'draft'] as $estado) {
            DB::table('invoicing_sales_invoices')->insert([
                'tenant_id' => $this->tenant->id, 'invoice_number' => 'FT-' . uniqid(), 'invoice_type' => 'FT',
                'client_id' => $this->cliente->id, 'invoice_date' => now(), 'status' => $estado, 'total' => 1000,
                'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $r = $this->getJson("/api/agent/v1/reports/tenants/{$this->tenant->id}/vendas?meses=3", $this->h())->assertOk();

        $this->assertCount(3, $r->json('por_mes'));
        $this->assertSame(2, $r->json('totais.documentos'));
        $this->assertEquals(2000, $r->json('totais.valor'));
        $this->assertSame(2, $r->json('por_mes.2.por_tipo.FT.documentos'));
    }

    public function test_o_relatorio_da_plataforma_e_o_dos_documentos(): void
    {
        $this->getJson('/api/agent/v1/reports/plataforma?meses=6', $this->h())
            ->assertOk()->assertJsonCount(6, 'por_mes')
            ->assertJsonStructure(['subscricoes' => ['activas', 'pagantes', 'receita_mensal_recorrente', 'por_plano'], 'testes_a_acabar_7d', 'empresas_por_estado']);

        $this->getJson('/api/agent/v1/reports/documentos?dias=7', $this->h())
            ->assertOk()->assertJsonStructure(['totais' => ['documentos', 'valor'], 'por_tipo', 'por_dia', 'por_empresa']);
    }

    /** O analytics do site é o mesmo do painel do dono. */
    public function test_o_analytics_do_site_e_o_percurso_de_um_visitante(): void
    {
        $visitante = (string) Str::uuid();
        DB::table('analytics_events')->insert([
            'visitor_id' => $visitante, 'session_id' => (string) Str::uuid(), 'type' => 'pageview',
            'path' => '/precos', 'created_at' => now(),
        ]);

        $this->getJson('/api/agent/v1/analytics/site?periodo=today', $this->h())
            ->assertOk()->assertJsonStructure(['periodo', 'agora', 'numeros', 'paginas', 'origens', 'aparelhos', 'dias']);

        $this->getJson("/api/agent/v1/analytics/site/visitantes/{$visitante}", $this->h())
            ->assertOk()->assertJsonPath('passos.0.pagina', '/precos');

        $this->getJson('/api/agent/v1/analytics/site/visitantes/nao-e-um-uuid', $this->h())->assertNotFound();
    }

    /** O mesmo ecrã com ids diferentes conta como um ecrã só. */
    public function test_o_uso_do_erp_junta_os_ecras_com_ids(): void
    {
        foreach ([12, 13] as $id) {
            DB::table('analytics_events')->insert([
                'visitor_id' => (string) Str::uuid(), 'session_id' => (string) Str::uuid(), 'type' => 'pageview',
                'path' => "/invoicing/sales/invoices/{$id}", 'user_id' => $this->user->id, 'tenant_id' => $this->tenant->id,
                'created_at' => now(),
            ]);
        }

        $r = $this->getJson('/api/agent/v1/analytics/uso?dias=1&tenant_id=' . $this->tenant->id, $this->h())->assertOk();

        $ecra = collect($r->json('ecras_mais_usados'))->firstWhere('ecra', '/invoicing/sales/invoices/{id}');
        $this->assertNotNull($ecra);
        $this->assertSame(2, $ecra['vistas']);
        $this->assertSame($this->tenant->id, $r->json('por_empresa.0.tenant_id'));
    }

    /** O detalhe da empresa traz o estado de vida e o porquê. */
    public function test_o_detalhe_da_empresa_traz_a_vida(): void
    {
        $this->getJson("/api/agent/v1/tenants/{$this->tenant->id}", $this->h())
            ->assertOk()->assertJsonStructure(['vida' => ['estado', 'motivo', 'ultimo_acesso', 'ultima_actividade']]);
    }

    public function test_a_visao_geral_conta_por_estado(): void
    {
        $this->getJson('/api/agent/v1/analytics/overview', $this->h())
            ->assertOk()->assertJsonStructure(['empresas' => ['sem_actividade', 'por_estado']]);
    }
}
