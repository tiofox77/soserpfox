<?php

namespace Tests\Feature\Agent;

use App\Models\AgentToken;
use App\Models\ErroDoSistema;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Support\Ticket;
use App\Services\Agent\EmissaoDeTokens;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * O que o agente externo pode fazer para GERIR a plataforma.
 *
 * Erros, ciclo de facturação, suporte, contactos reais e suspensão de
 * empresas. O que estes testes guardam não é que funciona — é que NÃO
 * funciona sem o escopo certo, e que as acções que mexem em dinheiro ou em
 * mensagens a pessoas reais nascem em modo de leitura.
 */
class OperacoesDoAgenteTest extends TenantTestCase
{
    private string $emClaro;
    private AgentToken $token;

    /** Todos os escopos novos: é assim que o token do openclaw fica. */
    private const ESCOPOS = [
        'contacts:read', 'logs:read', 'logs:write',
        'billing:read', 'billing:write',
        'support:read', 'support:write', 'tenants:write',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['agent.token.exigir_ips' => false]);

        $r = app(EmissaoDeTokens::class)->emitir(
            'openclaw-operacoes', $this->user, self::ESCOPOS, [], 30
        );

        $this->token   = $r['token'];
        $this->emClaro = $r['em_claro'];
    }

    private function comToken(): array
    {
        return ['Authorization' => 'Bearer ' . $this->emClaro];
    }

    /** As escritas exigem chave de idempotência. */
    private function paraEscrever(): array
    {
        return $this->comToken() + ['Idempotency-Key' => (string) Str::uuid()];
    }

    private function semEscopos(array $escopos): void
    {
        $this->token->forceFill(['scopes' => $escopos])->save();
    }

    private function erro(array $extra = []): ErroDoSistema
    {
        return ErroDoSistema::create(array_merge([
            'fingerprint' => hash('sha256', (string) Str::uuid()),
            'nivel'       => 'error',
            'mensagem'    => 'A base de dados não respondeu',
            'ficheiro'    => 'app/X.php',
            'linha'       => 42,
            'ocorrencias' => 5,
            'primeira_vez' => now()->subHour(),
            'ultima_vez'  => now(),
        ], $extra));
    }

    // ══════════════ Erros ══════════════

    public function test_ve_os_erros_agrupados(): void
    {
        $this->erro();

        $r = $this->getJson('/api/agent/v1/logs/errors', $this->comToken())->assertOk();

        $r->assertJsonPath('erros.0.ocorrencias', 5)
            ->assertJsonPath('resumo.abertos', 1);
    }

    public function test_sem_escopo_de_logs_nao_ve_erros(): void
    {
        $this->semEscopos(['tenants:read']);

        $this->getJson('/api/agent/v1/logs/errors', $this->comToken())->assertStatus(403);
    }

    public function test_pode_marcar_um_erro_como_resolvido(): void
    {
        $erro = $this->erro();

        $this->postJson("/api/agent/v1/logs/errors/{$erro->id}/estado",
            ['accao' => 'resolvido', 'nota' => 'corrigido no deploy de hoje'],
            $this->paraEscrever()
        )->assertOk()->assertJsonPath('erro.resolvido', true);

        $this->assertSame('agente:openclaw-operacoes', $erro->refresh()->resolvido_por);
    }

    public function test_sem_escopo_de_escrita_nao_fecha_erros(): void
    {
        $erro = $this->erro();
        $this->semEscopos(['logs:read']);

        $this->postJson("/api/agent/v1/logs/errors/{$erro->id}/estado",
            ['accao' => 'resolvido'], $this->paraEscrever()
        )->assertStatus(403);
    }

    // ══════════════ Contactos reais ══════════════

    public function test_com_o_escopo_certo_ve_o_contacto_real(): void
    {
        $this->user->forceFill(['email' => 'dono@cliente.ao', 'phone' => '923456789'])->save();

        $this->getJson("/api/agent/v1/tenants/{$this->tenant->id}/contacts", $this->comToken())
            ->assertOk()
            ->assertJsonPath('responsavel.email', 'dono@cliente.ao')
            // Já normalizado: é o formato que o WhatsApp e as operadoras aceitam.
            ->assertJsonPath('responsavel.telefone', '+244923456789');
    }

    public function test_sem_o_escopo_de_contactos_a_porta_esta_fechada(): void
    {
        $this->semEscopos(['tenants:read', 'logs:read']);

        $this->getJson("/api/agent/v1/tenants/{$this->tenant->id}/contacts", $this->comToken())
            ->assertStatus(403);
    }

    public function test_um_numero_impossivel_de_marcar_vem_a_nulo_e_nao_em_bruto(): void
    {
        // Oito dígitos: não é angolano. Devolver o valor cru levaria o agente
        // a tentar mandar WhatsApp para um número que não existe.
        $this->user->forceFill(['phone' => '92345678'])->save();
        $this->tenant->forceFill(['phone' => null])->save();

        $this->getJson("/api/agent/v1/tenants/{$this->tenant->id}/contacts", $this->comToken())
            ->assertOk()
            ->assertJsonPath('responsavel.telefone', null);
    }

    // ══════════════ Ciclo de facturação ══════════════

    public function test_ve_o_estado_do_ciclo(): void
    {
        $this->getJson('/api/agent/v1/billing/ciclo', $this->comToken())
            ->assertOk()
            ->assertJsonStructure([
                'periodos_a_acabar', 'facturas_por_pagar',
                'totais' => ['por_receber', 'vencido', 'facturas_vencidas'],
                'automatismos',
            ]);
    }

    public function test_as_facturas_por_pagar_aparecem_com_o_que_ha_a_receber(): void
    {
        $sub = $this->tenant->subscriptions()->first();

        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $sub->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'invoice_date' => now()->subDays(10),
            'due_date' => now()->subDays(3),
            'subtotal' => 25000, 'tax' => 0, 'total' => 25000,
            'status' => 'pending',
        ]);

        $this->getJson('/api/agent/v1/billing/ciclo', $this->comToken())
            ->assertOk()
            ->assertJsonPath('totais.por_receber', 25000)
            ->assertJsonPath('totais.facturas_vencidas', 1);
    }

    /**
     * A rota de emitir facturas nasce em modo de leitura.
     *
     * Uma factura é um documento que o cliente vê e sobre o qual lhe é pedido
     * dinheiro. Emitir por omissão punha o agente a facturar clientes por
     * chamar uma rota sem argumentos.
     */
    public function test_renovar_sem_dizer_nada_nao_emite_factura_nenhuma(): void
    {
        $antes = Invoice::count();

        $this->postJson('/api/agent/v1/billing/renovar', [], $this->paraEscrever())
            ->assertOk()
            ->assertJsonPath('so_ver', true);

        $this->assertSame($antes, Invoice::count());
    }

    public function test_avisar_sem_dizer_nada_nao_envia_a_ninguem(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $this->postJson('/api/agent/v1/billing/avisar', [], $this->paraEscrever())
            ->assertOk()
            ->assertJsonPath('so_ver', true);

        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    // ══════════════ Suporte ══════════════

    public function test_ve_os_pedidos_de_suporte_abertos(): void
    {
        Ticket::create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->user->id,
            'ticket_number' => 'TK-1',
            'subject'   => 'Não consigo facturar',
            'description' => 'dá erro ao gravar',
            'priority'  => 'high',
            'status'    => 'open',
        ]);

        $this->getJson('/api/agent/v1/support/tickets', $this->comToken())
            ->assertOk()
            ->assertJsonPath('tickets.0.assunto', 'Não consigo facturar')
            ->assertJsonPath('resumo.abertos', 1)
            ->assertJsonPath('resumo.urgentes', 1);
    }

    public function test_pode_deixar_nota_interna_e_mudar_o_estado(): void
    {
        $t = Ticket::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'ticket_number' => 'TK-2', 'subject' => 'x', 'description' => 'y',
            'priority' => 'low', 'status' => 'open',
        ]);

        $this->postJson("/api/agent/v1/support/tickets/{$t->id}/nota",
            ['nota' => 'reproduzi o problema; é o módulo de stock', 'estado' => 'in_progress'],
            $this->paraEscrever()
        )->assertOk()->assertJsonPath('ticket.estado', 'in_progress');

        // A nota tem de existir MESMO. O TicketMessage era um esqueleto vazio
        // a apontar para a tabela errada (`ticket_messages`, que não existe):
        // o fio de conversa dos tickets nunca tinha funcionado.
        $this->assertSame(1, $t->messages()->count());
        $this->assertStringContainsString('módulo de stock', $t->messages()->first()->message);
    }

    // ══════════════ Suspender uma empresa ══════════════

    public function test_pode_suspender_e_reactivar_com_motivo(): void
    {
        $this->postJson("/api/agent/v1/tenants/{$this->tenant->id}/estado",
            ['accao' => 'suspender', 'motivo' => 'factura vencida há 60 dias'],
            $this->paraEscrever()
        )->assertOk()->assertJsonPath('empresa.activa', false);

        $this->postJson("/api/agent/v1/tenants/{$this->tenant->id}/estado",
            ['accao' => 'reactivar', 'motivo' => 'o cliente pagou hoje'],
            $this->paraEscrever()
        )->assertOk()->assertJsonPath('empresa.activa', true);
    }

    public function test_suspender_sem_motivo_e_recusado(): void
    {
        // É a acção mais pesada que o agente pode fazer: corta o acesso a toda
        // a gente da empresa. Tem de ficar escrito porquê.
        $this->postJson("/api/agent/v1/tenants/{$this->tenant->id}/estado",
            ['accao' => 'suspender'], $this->paraEscrever()
        )->assertStatus(422);

        $this->assertTrue((bool) $this->tenant->refresh()->is_active);
    }

    public function test_sem_escopo_nao_suspende_ninguem(): void
    {
        $this->semEscopos(['tenants:read']);

        $this->postJson("/api/agent/v1/tenants/{$this->tenant->id}/estado",
            ['accao' => 'suspender', 'motivo' => 'porque sim'], $this->paraEscrever()
        )->assertStatus(403);

        $this->assertTrue((bool) $this->tenant->refresh()->is_active);
    }

    // ══════════════ O resumo de hora a hora ══════════════

    public function test_o_resumo_diz_quando_nao_ha_nada_a_dizer(): void
    {
        $r = $this->getJson('/api/agent/v1/status/resumo', $this->comToken())->assertOk();

        // Metade do trabalho de quem vigia é ficar calado.
        $r->assertJsonPath('precisa_atencao', false)
            ->assertJsonPath('porque', []);
    }

    public function test_o_resumo_explica_porque_e_que_precisa_de_atencao(): void
    {
        $this->erro(['nivel' => 'critical']);

        $this->getJson('/api/agent/v1/status/resumo', $this->comToken())
            ->assertOk()
            ->assertJsonPath('precisa_atencao', true)
            ->assertJsonPath('erros.criticos', 1)
            ->assertJsonFragment(['1 erro(s) crítico(s) por resolver']);
    }

    public function test_um_erro_ja_resolvido_nao_pede_atencao(): void
    {
        $this->erro(['nivel' => 'critical', 'resolvido_em' => now(), 'ultima_vez' => now()->subDays(3)]);

        $this->getJson('/api/agent/v1/status/resumo', $this->comToken())
            ->assertOk()
            ->assertJsonPath('erros.criticos', 0)
            ->assertJsonPath('precisa_atencao', false);
    }
}
