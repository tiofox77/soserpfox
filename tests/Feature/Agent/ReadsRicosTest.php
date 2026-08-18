<?php

namespace Tests\Feature\Agent;

use App\Services\Agent\EmissaoDeTokens;
use App\Services\Agent\SinaisDoTenant;
use App\Services\Plataforma\Inconsistencias;
use Tests\TenantTestCase;

/**
 * Os reads enriquecidos do agente.
 *
 * O que se guarda: os agregados aparecem, e nenhum dado em bruto (preço,
 * NIF completo, endereço) escapa por eles.
 */
class ReadsRicosTest extends TenantTestCase
{
    private string $emClaro;

    protected function setUp(): void
    {
        parent::setUp();

        config(['agent.token.exigir_ips' => false]);
        $this->comPermissoes('invoicing.sales.invoices.create')->comModulo('invoicing');

        $r = app(EmissaoDeTokens::class)->emitir(
            'openclaw-reads',
            $this->user,
            ['tenants:read', 'health:read', 'orders:read'],
            [],
            30
        );
        $this->emClaro = $r['em_claro'];
    }

    private function token(): array
    {
        return ['Authorization' => 'Bearer ' . $this->emClaro];
    }

    public function test_o_catalogo_de_planos_responde(): void
    {
        $this->getJson('/api/agent/v1/plans', $this->token())
            ->assertOk()
            ->assertJsonStructure(['planos', 'nota']);
    }

    public function test_o_detalhe_do_tenant_traz_os_agregados(): void
    {
        $this->getJson('/api/agent/v1/tenants/' . $this->tenant->id, $this->token())
            ->assertOk()
            ->assertJsonStructure([
                'empresa',
                'nif'      => ['estado', 'mascarado'],
                'produtos' => ['total', 'sem_preco', 'conta_vazia'],
                'envios'   => ['email', 'sms'],
            ]);
    }

    public function test_o_detalhe_do_tenant_nao_expoe_o_nif_completo(): void
    {
        $this->tenant->update(['nif' => '5417123456']);

        $r = $this->getJson('/api/agent/v1/tenants/' . $this->tenant->id, $this->token());

        $r->assertOk();
        $this->assertStringNotContainsString('5417123456', $r->getContent(),
            'o NIF completo escapou no detalhe do tenant');
    }

    public function test_os_agregados_de_produtos_sao_so_contagens(): void
    {
        $sinais = app(SinaisDoTenant::class);
        $p = $sinais->produtos($this->tenant->id);

        foreach ($p as $chave => $valor) {
            $this->assertTrue(
                is_int($valor) || is_bool($valor),
                "o agregado '{$chave}' devia ser contagem ou booleano, veio " . gettype($valor)
            );
        }
    }

    public function test_o_catalogo_de_saude_inclui_os_checks_operacionais(): void
    {
        $catalogo = app(Inconsistencias::class)->catalogo();

        $this->assertArrayHasKey('agt_fila_parada', $catalogo);
        $this->assertArrayHasKey('documentos_por_comunicar', $catalogo);
        $this->assertArrayHasKey('stock_negativo', $catalogo);
        $this->assertArrayHasKey('nif_invalido', $catalogo);
    }

    public function test_os_checks_operacionais_correm_sem_erro(): void
    {
        $inc = app(Inconsistencias::class);

        foreach (['agt_fila_parada', 'documentos_por_comunicar', 'stock_negativo'] as $check) {
            $r = $inc->correr([$check], $this->tenant->id);
            $this->assertIsArray($r, "o check {$check} rebentou");
        }
    }

    public function test_um_check_fora_da_allowlist_e_ignorado(): void
    {
        // Nunca executar um método arbitrário a partir do nome recebido.
        $r = app(Inconsistencias::class)->correr(['apagar_tudo; drop table'], $this->tenant->id);

        $this->assertSame([], $r);
    }
}
