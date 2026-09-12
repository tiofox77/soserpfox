<?php

namespace Tests\Feature\CRM;

use App\Models\Client;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\Stage;
use App\Services\CRM\ConversaoDeLead;
use Tests\TenantTestCase;

/**
 * O módulo CRM — do placeholder ao módulo.
 *
 * As rotas /crm/* existiam desde o início a apontar para "em construção" com
 * o módulo ACTIVO e vendável: quem o comprasse recebia quatro páginas de
 * obras. Estes ensaios prendem o que agora lá está — e sobretudo a COSTURA:
 * o lead convertido vira cliente DA FACTURAÇÃO, não uma segunda lista.
 *
 * OS ECRÃS PASSARAM A REACT e estes ensaios foram com eles: em vez de mexer em
 * propriedades de um componente, batem à porta que o ecrã chama. O que medem é
 * o mesmo — e agora medem também as PERMISSÕES DE ESCRITA, que o Livewire não
 * perguntava a ninguém.
 */
class ModuloCrmTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/crm';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('crm');
        $this->comPermissoes('crm.view', 'crm.leads.view', 'crm.leads.manage',
            'crm.opportunities.view', 'crm.opportunities.manage');
    }

    /* ── As etapas ─────────────────────────────────────────────────── */

    /** @test */
    public function as_etapas_nascem_sozinhas_a_primeira_pergunta(): void
    {
        $this->assertSame(0, Stage::where('tenant_id', $this->tenant->id)->count());

        $etapas = Stage::doTenant($this->tenant->id);

        $this->assertCount(5, $etapas);
        $this->assertSame('Novo contacto', $etapas->first()->name);

        // E não se duplicam à segunda pergunta.
        Stage::doTenant($this->tenant->id);
        $this->assertSame(5, Stage::where('tenant_id', $this->tenant->id)->count());
    }

    /* ── Os leads ──────────────────────────────────────────────────── */

    /** @test */
    public function um_lead_cria_se_com_nome_e_telefone(): void
    {
        $this->actingAs($this->user)->postJson(self::RAIZ.'/leads', [
            'name' => 'Dona Ana',
            'phone' => '923000111',
            'source' => 'indicacao',
        ])->assertCreated();

        $this->assertDatabaseHas('crm_leads', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Dona Ana',
            'status' => 'novo',
            'source' => 'indicacao',
        ]);
    }

    /** Registar uma chamada num lead novo passa-o a contactado sozinho. */
    public function test_falar_com_um_lead_novo_conta_lo_como_contactado(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->user)->postJson(self::RAIZ."/leads/{$lead->id}/actividades", [
            'type' => 'chamada',
            'subject' => 'Perguntou preços de cimento',
        ])->assertCreated();

        $this->assertSame('contactado', $lead->fresh()->status);
        $this->assertDatabaseHas('crm_activities', ['lead_id' => $lead->id, 'type' => 'chamada']);
    }

    /** Perder exige motivo — «perdido sem razão» não ensina nada. */
    public function test_perder_um_lead_exige_motivo(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->user)->postJson(self::RAIZ."/leads/{$lead->id}/perder", ['motivo' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');

        $this->assertSame('novo', $lead->fresh()->status);
    }

    /* ── A conversão: a costura com a Facturação ───────────────────── */

    /**
     * A PROVA CENTRAL: converter cria um cliente DA FACTURAÇÃO e abre a
     * oportunidade já ligada a ele.
     *
     * @test
     */
    public function converter_cria_o_cliente_na_facturacao_e_abre_a_oportunidade(): void
    {
        $lead = $this->lead(['company' => 'Obras do Camama, Lda.', 'email' => 'obras@camama.ao']);

        $cliente = app(ConversaoDeLead::class)->converter($lead, $this->tenant->id, $this->user->id, [
            'title' => 'Fornecimento anual',
            'amount' => 250000,
        ]);

        // O cliente é o da facturação — a tabela invoicing_clients.
        $this->assertDatabaseHas('invoicing_clients', [
            'id' => $cliente->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Obras do Camama, Lda.',
            'type' => 'pessoa_juridica',
        ]);

        $lead->refresh();
        $this->assertSame('convertido', $lead->status);
        $this->assertSame($cliente->id, $lead->converted_client_id);

        $oportunidade = Opportunity::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame($cliente->id, $oportunidade->client_id);
        $this->assertSame($lead->id, $oportunidade->lead_id);
        $this->assertSame('250000.00', (string) $oportunidade->amount);
        $this->assertSame('open', $oportunidade->status);
    }

    /** O botão carregado duas vezes não cria dois clientes. */
    public function test_converter_duas_vezes_devolve_o_mesmo_cliente(): void
    {
        $lead = $this->lead(['email' => 'ana@exemplo.ao']);
        $servico = app(ConversaoDeLead::class);

        $primeiro = $servico->converter($lead, $this->tenant->id, $this->user->id);
        $segundo = $servico->converter($lead->fresh(), $this->tenant->id, $this->user->id);

        $this->assertSame($primeiro->id, $segundo->id);
        $this->assertSame(1, Client::where('tenant_id', $this->tenant->id)
            ->where('email', 'ana@exemplo.ao')->count());
    }

    /**
     * Um lead de quem JÁ É cliente reaproveita o cadastro que existe.
     *
     * Acontece a toda a hora — o cliente ligou outra vez, veio à feira — e
     * criar um segundo cadastro partia a história de facturação ao meio.
     *
     * @test
     */
    public function um_lead_de_quem_ja_e_cliente_reaproveita_o_cadastro(): void
    {
        $existente = Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dona Ana',
            'type' => 'pessoa_fisica',
            'phone' => '923000111',
            'country' => 'Angola',
            'tax_regime' => 'geral',
            'is_active' => true,
        ]);

        $lead = $this->lead(['phone' => '923000111']);

        $cliente = app(ConversaoDeLead::class)->converter($lead, $this->tenant->id, $this->user->id);

        $this->assertSame($existente->id, $cliente->id, 'um cadastro só, nunca dois a divergir');
    }

    /** Um lead perdido não se converte sem ser reaberto. */
    public function test_um_lead_perdido_nao_se_converte(): void
    {
        $lead = $this->lead(['status' => 'perdido']);

        $this->expectException(\InvalidArgumentException::class);

        app(ConversaoDeLead::class)->converter($lead, $this->tenant->id, $this->user->id);
    }

    /* ── As oportunidades e o funil ────────────────────────────────── */

    /** @test */
    public function uma_oportunidade_cria_se_e_ganha_se(): void
    {
        $this->actingAs($this->user)->postJson(self::RAIZ.'/oportunidades', [
            'title' => 'Fornecimento à obra',
            'stage_id' => Stage::doTenant($this->tenant->id)->first()->id,
            'amount' => 1500000,
        ])->assertCreated();

        $o = Opportunity::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('1500000.00', (string) $o->amount);

        $this->actingAs($this->user)->postJson(self::RAIZ."/oportunidades/{$o->id}/ganhar")->assertOk();

        $o->refresh();
        $this->assertSame('won', $o->status);
        $this->assertSame(100, (int) $o->probability);
        $this->assertNotNull($o->closed_at);
    }

    /** Perder uma oportunidade exige motivo. */
    public function test_perder_uma_oportunidade_exige_motivo(): void
    {
        $o = $this->oportunidade();

        $this->actingAs($this->user)->postJson(self::RAIZ."/oportunidades/{$o->id}/perder", [
            'motivo' => 'preço do concorrente',
        ])->assertOk();

        $o->refresh();
        $this->assertSame('lost', $o->status);
        $this->assertSame('preço do concorrente', $o->lost_reason);
        $this->assertSame(0, (int) $o->probability);
    }

    /**
     * Mover no funil acompanha a probabilidade da etapa — é isso que faz o
     * valor ponderado dizer a verdade sem ninguém estimar à mão.
     *
     * @test
     */
    public function mover_no_funil_acompanha_a_probabilidade_da_etapa(): void
    {
        $o = $this->oportunidade();

        $etapas = Stage::doTenant($this->tenant->id);
        $this->assertSame((int) $etapas[0]->probability, (int) $o->probability);

        $this->actingAs($this->user)->postJson(self::RAIZ."/oportunidades/{$o->id}/mover", [
            'direccao' => 'frente',
        ])->assertOk();

        $o->refresh();
        $this->assertSame($etapas[1]->id, $o->stage_id);
        $this->assertSame((int) $etapas[1]->probability, (int) $o->probability);

        // E da primeira etapa não se recua para lado nenhum: a porta diz que
        // não, em vez de não fazer nada em silêncio.
        $this->actingAs($this->user)->postJson(self::RAIZ."/oportunidades/{$o->id}/mover", ['direccao' => 'tras'])
            ->assertOk();
        $this->actingAs($this->user)->postJson(self::RAIZ."/oportunidades/{$o->id}/mover", ['direccao' => 'tras'])
            ->assertStatus(422);

        $this->assertSame($etapas[0]->id, $o->fresh()->stage_id);
    }

    /* ── O painel e as portas ──────────────────────────────────────── */

    /** @test */
    public function o_painel_conta_o_funil_e_a_taxa_sobre_o_que_fechou(): void
    {
        $this->oportunidade(['amount' => 100000]);

        $ganha = $this->oportunidade(['amount' => 50000]);
        $ganha->update(['status' => 'won', 'closed_at' => now()]);

        $perdida = $this->oportunidade(['amount' => 30000]);
        $perdida->update(['status' => 'lost', 'closed_at' => now()]);

        $resumo = $this->actingAs($this->user)->getJson(self::RAIZ.'/painel')->assertOk()->json('resumo');

        $this->assertEqualsWithDelta(100000, $resumo['funil_valor'], 0.01, 'só as abertas contam para o funil');
        $this->assertEqualsWithDelta(50000, $resumo['ganho_mes'], 0.01);
        // 1 ganha ÷ 2 fechadas = 50%. As abertas NÃO entram no denominador —
        // senão registar um negócio novo fazia a taxa cair.
        $this->assertEquals(50, $resumo['taxa']);
    }

    /**
     * OS ECRÃS ABREM — o erro 500 é a avaria mais cara deste projecto.
     *
     * São QUATRO MORADAS PARA TRÊS ECRÃS: a lista e o funil são o mesmo, visto
     * de duas maneiras, e cada morada abre no seu separador.
     */
    public function test_os_ecras_abrem_e_montam_o_react(): void
    {
        $this->lead();
        $this->oportunidade();

        foreach ([
            '/crm/dashboard' => 'crm/painel',
            '/crm/leads' => 'crm/leads',
            '/crm/oportunidades' => 'crm/oportunidades',
            '/crm/funil-vendas' => 'crm/oportunidades',
        ] as $rota => $ecra) {
            $this->actingAs($this->user)->get($rota)->assertOk()->assertSee($ecra, false);
        }
    }

    /**
     * VER NÃO É MEXER — e não era.
     *
     * O componente em Livewire nunca perguntou por "crm.leads.manage" nem por
     * "crm.opportunities.manage": quem abrisse a página convertia leads em
     * clientes, dava negócios por perdidos e respondia por WhatsApp em nome da
     * empresa. As permissões existiam na base de dados e não serviam para nada.
     */
    public function test_ver_nao_e_mexer(): void
    {
        $outro = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id]);
        $outro->tenants()->attach($this->tenant->id);
        $outro->givePermissionTo(['crm.view', 'crm.leads.view', 'crm.opportunities.view']);

        $lead = $this->lead();
        $o = $this->oportunidade();

        $this->actingAs($outro)->getJson(self::RAIZ.'/leads')->assertOk();
        $this->actingAs($outro)->getJson(self::RAIZ.'/oportunidades')->assertOk();

        $this->actingAs($outro)->postJson(self::RAIZ.'/leads', [
            'name' => 'Tentativa', 'source' => 'telefone',
        ])->assertForbidden();
        $this->actingAs($outro)->postJson(self::RAIZ."/leads/{$lead->id}/converter", [
            'title' => 'Tentativa',
        ])->assertForbidden();
        $this->actingAs($outro)->postJson(self::RAIZ."/leads/{$lead->id}/responder", [
            'texto' => 'olá',
        ])->assertForbidden();
        $this->actingAs($outro)->postJson(self::RAIZ."/oportunidades/{$o->id}/ganhar")->assertForbidden();
        $this->actingAs($outro)->postJson(self::RAIZ."/oportunidades/{$o->id}/mover", [
            'direccao' => 'frente',
        ])->assertForbidden();
    }

    /**
     * UM NEGÓCIO JÁ FACTURADO NÃO SE REABRE.
     *
     * Havia uma factura emitida a apontar para ele: reabri-lo punha-o de volta
     * no funil a contar como dinheiro por fechar, ao mesmo tempo que o
     * documento já estava na rua. O mesmo negócio contado duas vezes.
     */
    public function test_uma_oportunidade_ja_facturada_nao_se_reabre(): void
    {
        $o = $this->oportunidade();
        $o->update(['status' => 'won', 'closed_at' => now(), 'sales_invoice_id' => 999999]);

        $this->actingAs($this->user)->postJson(self::RAIZ."/oportunidades/{$o->id}/reabrir")
            ->assertStatus(422);

        $this->assertSame('won', $o->fresh()->status);
    }

    /** E um lead já convertido não se apaga: é o princípio do historial. */
    public function test_um_lead_convertido_nao_se_apaga(): void
    {
        $lead = $this->lead(['status' => 'convertido']);

        $this->actingAs($this->user)->deleteJson(self::RAIZ."/leads/{$lead->id}")->assertStatus(422);

        $this->assertNotNull(Lead::withoutGlobalScopes()->find($lead->id));
    }

    /** Sem permissão, nenhum ecrã abre. */
    public function test_sem_permissao_nao_se_entra(): void
    {
        $outro = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id]);
        $outro->tenants()->attach($this->tenant->id);

        $this->actingAs($outro)->get('/crm/leads')->assertForbidden();
    }

    /** Empresas não se vêem umas às outras. */
    public function test_uma_empresa_nao_ve_os_leads_da_outra(): void
    {
        $lead = $this->lead();

        $outroTenant = \App\Models\Tenant::create([
            'name' => 'Outra Empresa',
            'slug' => 'outra-'.uniqid(),
            'email' => 'outra-'.uniqid().'@exemplo.ao',
            'is_active' => true,
        ]);

        $this->assertSame(0, Lead::withoutGlobalScopes()
            ->where('tenant_id', $outroTenant->id)->count());

        // E a conversão recusa um lead de outra empresa.
        $this->expectException(\InvalidArgumentException::class);
        app(ConversaoDeLead::class)->converter($lead, $outroTenant->id, null);
    }

    /* ── Apoios ────────────────────────────────────────────────────── */

    private function lead(array $extra = []): Lead
    {
        return Lead::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dona Ana',
            'phone' => '923000111',
            'source' => 'telefone',
            'status' => 'novo',
            'created_by' => $this->user->id,
        ], $extra));
    }

    private function oportunidade(array $extra = []): Opportunity
    {
        $etapa = Stage::doTenant($this->tenant->id)->first();

        return Opportunity::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'title' => 'Negócio de ensaio',
            'stage_id' => $etapa->id,
            'amount' => 100000,
            'probability' => (int) $etapa->probability,
            'status' => 'open',
            'created_by' => $this->user->id,
        ], $extra));
    }
}
