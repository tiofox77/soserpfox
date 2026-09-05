<?php

namespace Tests\Feature\CRM;

use App\Models\CRM\Activity;
use App\Models\CRM\Lead;
use App\Models\CRM\MetaContact;
use App\Models\CRM\MetaIntegration;
use Tests\TenantTestCase;

/**
 * O webhook do Meta: recebe mensagens e transforma-as em Leads + Actividades.
 *
 * O que estes ensaios prendem é o que torna isto de confiança: o aperto de mão
 * (verify_token), a ASSINATURA (um forjador sem o app_secret é recusado), a
 * criação do lead com a mensagem como actividade, e a DEDUPLICAÇÃO — quem
 * escreve duas vezes é um lead com duas actividades, não dois leads.
 */
class MetaWebhookTest extends TenantTestCase
{
    private function integracao(array $extra = []): MetaIntegration
    {
        $mi = MetaIntegration::paraTenant($this->tenant->id);
        $mi->fill(array_merge([
            'webhook_verify_token' => 'segredo-de-verificacao',
            'whatsapp_enabled' => true,
            'whatsapp_phone_number_id' => '111',
            'criar_leads' => true,
        ], $extra));
        $mi->save();

        return $mi;
    }

    private function payloadWhatsApp(string $from, string $texto, string $nome = 'Cliente Teste'): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'contacts' => [['wa_id' => $from, 'profile' => ['name' => $nome]]],
                        'messages' => [[
                            'from' => $from, 'id' => 'wamid.'.uniqid(), 'type' => 'text',
                            'text' => ['body' => $texto],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function postWebhook(array $payload, ?string $appSecret = null)
    {
        $raw = json_encode($payload);
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($appSecret !== null) {
            $server['HTTP_X_HUB_SIGNATURE_256'] = 'sha256='.hash_hmac('sha256', $raw, $appSecret);
        }

        return $this->call('POST', '/webhooks/meta/'.$this->tenant->id, [], [], [], $server, $raw);
    }

    /** @test */
    public function o_aperto_de_mao_devolve_o_challenge(): void
    {
        $this->integracao();

        $this->get('/webhooks/meta/'.$this->tenant->id.'?hub_mode=subscribe&hub_verify_token=segredo-de-verificacao&hub_challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    /** Token errado no aperto de mão → recusado. */
    public function test_aperto_de_mao_com_token_errado_e_recusado(): void
    {
        $this->integracao();

        $this->get('/webhooks/meta/'.$this->tenant->id.'?hub_mode=subscribe&hub_verify_token=ERRADO&hub_challenge=12345')
            ->assertForbidden();
    }

    /** @test */
    public function uma_mensagem_de_whatsapp_cria_lead_e_actividade(): void
    {
        $this->integracao();

        $this->postWebhook($this->payloadWhatsApp('244912345678', 'Olá, tenho interesse'))->assertOk();

        $lead = Lead::where('tenant_id', $this->tenant->id)->where('phone', '244912345678')->first();
        $this->assertNotNull($lead, 'devia ter criado o lead');
        $this->assertSame('whatsapp', $lead->source);
        $this->assertSame('Cliente Teste', $lead->name);

        $this->assertDatabaseHas('crm_activities', [
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'notes' => 'Olá, tenho interesse',
        ]);
    }

    /**
     * DEDUPLICAÇÃO. Duas mensagens do mesmo número → UM lead, DUAS actividades.
     *
     * @test
     */
    public function duas_mensagens_do_mesmo_numero_sao_um_lead(): void
    {
        $this->integracao();

        $this->postWebhook($this->payloadWhatsApp('244900000001', 'Primeira'))->assertOk();
        $this->postWebhook($this->payloadWhatsApp('244900000001', 'Segunda'))->assertOk();

        $this->assertSame(1, Lead::where('tenant_id', $this->tenant->id)->where('phone', '244900000001')->count());
        $this->assertSame(1, MetaContact::where('tenant_id', $this->tenant->id)->where('external_id', '244900000001')->count());

        $leadId = Lead::where('phone', '244900000001')->value('id');
        $this->assertSame(2, Activity::where('lead_id', $leadId)->count());
    }

    /**
     * A ASSINATURA protege. Com app_secret configurado, um POST sem a assinatura
     * certa é recusado — e NÃO cria nada.
     *
     * @test
     */
    public function assinatura_invalida_e_recusada(): void
    {
        $this->integracao(['app_secret' => 'o-segredo-da-app']);

        // Assinado com o segredo ERRADO.
        $this->postWebhook($this->payloadWhatsApp('244911111111', 'forjado'), 'segredo-errado')
            ->assertForbidden();

        $this->assertSame(0, Lead::where('tenant_id', $this->tenant->id)->count());
    }

    /** Com a assinatura certa, processa. */
    public function test_assinatura_certa_processa(): void
    {
        $this->integracao(['app_secret' => 'o-segredo-da-app']);

        $this->postWebhook($this->payloadWhatsApp('244922222222', 'verdadeiro'), 'o-segredo-da-app')
            ->assertOk();

        $this->assertSame(1, Lead::where('tenant_id', $this->tenant->id)->where('phone', '244922222222')->count());
    }

    /** Com criar_leads desligado, não cria lead. */
    public function test_criar_leads_desligado_nao_cria(): void
    {
        $this->integracao(['criar_leads' => false]);

        $this->postWebhook($this->payloadWhatsApp('244933333333', 'olá'))->assertOk();

        $this->assertSame(0, Lead::where('tenant_id', $this->tenant->id)->count());
    }
}
