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
                'nif'      => ['estado', 'nif'],
                'produtos' => ['total', 'sem_preco', 'conta_vazia'],
                'envios'   => ['email', 'sms'],
            ]);
    }

    /**
     * O NIF vai por inteiro.
     *
     * Ia mascarado ('54******23') e este teste guardava isso. A máscara foi
     * retirada de toda a API do agente a pedido de quem gere a plataforma:
     * um NIF cortado ao meio não se verifica contra a AGT nem se compara com
     * um documento, que é para o que o agente precisa dele.
     */
    public function test_o_detalhe_do_tenant_traz_o_nif_por_inteiro(): void
    {
        $this->tenant->update(['nif' => '5417123456']);

        $this->getJson('/api/agent/v1/tenants/' . $this->tenant->id, $this->token())
            ->assertOk()
            ->assertJsonPath('nif.nif', '5417123456');
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
    // ══════════════ nada sai mascarado ══════════════

    /**
     * A API do agente não mascara NADA.
     *
     * Foi decisão de quem gere a plataforma: o agente contacta os clientes por
     * canais próprios (WhatsApp, chamada) e verifica NIFs contra a AGT — um
     * valor cortado ao meio não serve para nenhuma dessas coisas, e o agente
     * ficava a ver dados que não conseguia usar.
     *
     * O que protege isto continua a ser o token, a lista de IPs, os escopos e
     * o registo de cada pedido. Estes testes existem para que ninguém volte a
     * pôr máscaras aqui a pensar que está a melhorar a segurança.
     */
    public function test_os_destinatarios_vem_com_email_e_telefone_inteiros(): void
    {
        $this->user->forceFill(['email' => 'ana@cliente.ao', 'phone' => '923456789'])->save();
        $this->tenant->forceFill(['email' => 'geral@cliente.ao', 'phone' => '222330011'])->save();

        $r = $this->getJson('/api/agent/v1/tenants/' . $this->tenant->id, $this->token())->assertOk();

        $corpo = $r->getContent();

        $this->assertStringContainsString('ana@cliente.ao', $corpo);
        $this->assertStringContainsString('923456789', $corpo);
        $this->assertStringContainsString('geral@cliente.ao', $corpo);
    }

    public function test_nao_sobra_nenhum_asterisco_de_mascara(): void
    {
        $this->tenant->update(['nif' => '5417123456']);
        $this->user->forceFill(['email' => 'ana@cliente.ao', 'phone' => '923456789'])->save();
        $this->tenant->forceFill(['email' => 'geral@cliente.ao', 'phone' => '222330011'])->save();

        $corpo = $this->getJson('/api/agent/v1/tenants/' . $this->tenant->id, $this->token())
            ->assertOk()->getContent();

        // Três asteriscos seguidos é a assinatura de qualquer das máscaras
        // que aqui existiram ('ca***@…', '9****9902', '54******56').
        $this->assertStringNotContainsString('***', $corpo,
            'voltou a haver mascaramento na API do agente');
    }

    public function test_a_inconsistencia_do_nif_traz_o_numero_para_se_poder_corrigir(): void
    {
        // NIF de pessoa singular num campo de empresa: é o caso que o agente
        // detecta. Sem o número, não há como dizer a ninguém qual corrigir.
        $this->tenant->update(['nif' => '2417123456']);

        $r = $this->getJson('/api/agent/v1/health/inconsistencias?checks[]=nif_invalido', $this->token())
            ->assertOk();

        $this->assertStringContainsString('2417123456', $r->getContent());
    }

}
