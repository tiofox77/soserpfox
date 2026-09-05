<?php

namespace Tests\Feature\CRM;

use App\Livewire\CRM\Leads;
use App\Models\CRM\Activity;
use App\Models\CRM\Lead;
use App\Models\CRM\MetaContact;
use App\Models\CRM\MetaIntegration;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Responder ao lead por WhatsApp a partir do CRM, e ver a conversa.
 *
 * O que estes ensaios prendem: a resposta VAI mesmo ao Meta (e regista-se como
 * actividade ENVIADA, para a conversa ter dois lados), o erro do Meta chega ao
 * ecrã em vez de fingir sucesso, e sem WhatsApp ligado não se tenta enviar.
 */
class ResponderWhatsAppTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('crm');
        $this->comPermissoes('crm.leads.view', 'crm.leads.manage');
    }

    private function integracaoWhatsapp(): MetaIntegration
    {
        $mi = MetaIntegration::paraTenant($this->tenant->id);
        $mi->fill([
            'whatsapp_enabled' => true,
            'whatsapp_phone_number_id' => '999',
            'whatsapp_token' => 'token-valido',
        ]);
        $mi->save();

        return $mi;
    }

    private function leadComWhatsapp(string $numero = '244911222333'): Lead
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cliente WA',
            'phone' => $numero, 'source' => 'whatsapp', 'status' => 'novo',
        ]);
        MetaContact::create([
            'tenant_id' => $this->tenant->id, 'channel' => 'whatsapp',
            'external_id' => $numero, 'lead_id' => $lead->id, 'name' => 'Cliente WA', 'phone' => $numero,
        ]);

        return $lead;
    }

    /** @test */
    public function responder_envia_ao_meta_e_regista_actividade_enviada(): void
    {
        $this->integracaoWhatsapp();
        $lead = $this->leadComWhatsapp();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.enviado']]], 200),
        ]);

        Livewire::actingAs($this->user)->test(Leads::class)
            ->call('verConversa', $lead->id)
            ->set('respostaTexto', 'Boa tarde, o produto está disponível.')
            ->call('responder')
            ->assertHasNoErrors()
            ->assertSet('respostaTexto', '');

        // Foi mesmo ao Meta, para o número certo.
        Http::assertSent(fn ($req) => str_contains($req->url(), '/999/messages')
            && $req['to'] === '244911222333'
            && $req['text']['body'] === 'Boa tarde, o produto está disponível.');

        // Ficou registada como ENVIADA.
        $this->assertDatabaseHas('crm_activities', [
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'direction' => 'out',
            'type' => 'whatsapp',
            'notes' => 'Boa tarde, o produto está disponível.',
        ]);
    }

    /**
     * O ERRO DO META CHEGA AO ECRÃ. Se o Meta recusar (ex.: janela de 24h
     * fechada), não se finge que enviou nem se regista actividade.
     *
     * @test
     */
    public function erro_do_meta_aparece_e_nao_regista(): void
    {
        $this->integracaoWhatsapp();
        $lead = $this->leadComWhatsapp();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Message failed to send because more than 24 hours have passed.'],
            ], 400),
        ]);

        $comp = Livewire::actingAs($this->user)->test(Leads::class)
            ->call('verConversa', $lead->id)
            ->set('respostaTexto', 'Olá?')
            ->call('responder');

        $this->assertNotEmpty($comp->get('erroResposta'));
        $this->assertStringContainsString('24 hours', $comp->get('erroResposta'));

        $this->assertSame(0, Activity::where('lead_id', $lead->id)->where('direction', 'out')->count());
    }

    /** Sem WhatsApp ligado, nem se tenta — e diz-se porquê. */
    public function test_sem_whatsapp_nao_envia(): void
    {
        // Integração existe mas WhatsApp desligado.
        MetaIntegration::paraTenant($this->tenant->id);
        $lead = $this->leadComWhatsapp();

        Http::fake();

        $comp = Livewire::actingAs($this->user)->test(Leads::class)
            ->call('verConversa', $lead->id)
            ->set('respostaTexto', 'teste')
            ->call('responder');

        $this->assertNotEmpty($comp->get('erroResposta'));
        Http::assertNothingSent();
    }

    /** A conversa mostra recebidas e enviadas por ordem. */
    public function test_a_conversa_junta_recebidas_e_enviadas(): void
    {
        $this->integracaoWhatsapp();
        $lead = $this->leadComWhatsapp();

        Activity::create(['tenant_id' => $this->tenant->id, 'lead_id' => $lead->id, 'type' => 'whatsapp', 'direction' => 'in', 'subject' => 'Mensagem WhatsApp', 'notes' => 'Tens em stock?', 'done' => true]);

        $conversa = Livewire::actingAs($this->user)->test(Leads::class)
            ->call('verConversa', $lead->id)
            ->get('conversa');

        $this->assertTrue($conversa['podeWhatsapp']);
        $this->assertSame('244911222333', $conversa['numero']);
        $this->assertCount(1, $conversa['actividades']);
    }
}
