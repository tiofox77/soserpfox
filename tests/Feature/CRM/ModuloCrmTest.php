<?php

namespace Tests\Feature\CRM;

use App\Livewire\CRM\Dashboard;
use App\Livewire\CRM\FunilDeVendas;
use App\Livewire\CRM\Leads;
use App\Livewire\CRM\Oportunidades;
use App\Models\Client;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\Stage;
use App\Services\CRM\ConversaoDeLead;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O módulo CRM — do placeholder ao módulo.
 *
 * As rotas /crm/* existiam desde o início a apontar para "em construção" com
 * o módulo ACTIVO e vendável: quem o comprasse recebia quatro páginas de
 * obras. Estes ensaios prendem o que agora lá está — e sobretudo a COSTURA:
 * o lead convertido vira cliente DA FACTURAÇÃO, não uma segunda lista.
 */
class ModuloCrmTest extends TenantTestCase
{
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
        Livewire::actingAs($this->user)->test(Leads::class)
            ->set('novoNome', 'Dona Ana')
            ->set('novoTelefone', '923000111')
            ->set('novaOrigem', 'indicacao')
            ->call('criar')
            ->assertHasNoErrors()
            ->assertSet('novoNome', '');

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

        Livewire::actingAs($this->user)->test(Leads::class)
            ->set('paraActividade', $lead->id)
            ->set('actTipo', 'chamada')
            ->set('actAssunto', 'Perguntou preços de cimento')
            ->call('registarActividade')
            ->assertHasNoErrors();

        $this->assertSame('contactado', $lead->fresh()->status);
        $this->assertDatabaseHas('crm_activities', ['lead_id' => $lead->id, 'type' => 'chamada']);
    }

    /** Perder exige motivo — «perdido sem razão» não ensina nada. */
    public function test_perder_um_lead_exige_motivo(): void
    {
        $lead = $this->lead();

        Livewire::actingAs($this->user)->test(Leads::class)
            ->set('paraPerder', $lead->id)
            ->set('motivoPerda', '')
            ->call('perder')
            ->assertHasErrors(['motivoPerda' => 'required']);

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
        $componente = Livewire::actingAs($this->user)->test(Oportunidades::class)
            ->call('criar')
            ->set('titulo', 'Fornecimento à obra')
            ->set('valor', '1.500.000,00')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $o = Opportunity::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('1500000.00', (string) $o->amount, 'o valor com máscara tem de ser lido certo');

        $componente->call('ganhar', $o->id)->assertHasNoErrors();

        $o->refresh();
        $this->assertSame('won', $o->status);
        $this->assertSame(100, (int) $o->probability);
        $this->assertNotNull($o->closed_at);
    }

    /** Perder uma oportunidade exige motivo. */
    public function test_perder_uma_oportunidade_exige_motivo(): void
    {
        $o = $this->oportunidade();

        Livewire::actingAs($this->user)->test(Oportunidades::class)
            ->set('paraPerder', $o->id)
            ->set('motivoPerda', 'preço do concorrente')
            ->call('perder')
            ->assertHasNoErrors();

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

        Livewire::actingAs($this->user)->test(FunilDeVendas::class)
            ->call('mover', $o->id, 'frente')
            ->assertHasNoErrors();

        $o->refresh();
        $this->assertSame($etapas[1]->id, $o->stage_id);
        $this->assertSame((int) $etapas[1]->probability, (int) $o->probability);

        // E da primeira etapa não se recua para lado nenhum.
        Livewire::actingAs($this->user)->test(FunilDeVendas::class)
            ->call('mover', $o->id, 'tras')
            ->call('mover', $o->id, 'tras')
            ->assertHasNoErrors();

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

        $resumo = Livewire::actingAs($this->user)->test(Dashboard::class)->viewData('resumo');

        $this->assertEqualsWithDelta(100000, $resumo['funil_valor'], 0.01, 'só as abertas contam para o funil');
        $this->assertEqualsWithDelta(50000, $resumo['ganho_mes'], 0.01);
        // 1 ganha ÷ 2 fechadas = 50%. As abertas NÃO entram no denominador —
        // senão registar um negócio novo fazia a taxa cair.
        $this->assertEquals(50, $resumo['taxa']);
    }

    /** Os quatro ecrãs abrem — o erro 500 é a avaria mais cara deste projecto. */
    public function test_os_quatro_ecras_abrem(): void
    {
        $this->lead();
        $this->oportunidade();

        foreach (['/crm/dashboard', '/crm/leads', '/crm/oportunidades', '/crm/funil-vendas'] as $rota) {
            $this->actingAs($this->user)->get($rota)->assertOk();
        }
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
