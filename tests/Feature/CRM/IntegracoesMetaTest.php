<?php

namespace Tests\Feature\CRM;

use App\Livewire\CRM\IntegracoesMeta;
use App\Models\CRM\MetaIntegration;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * As definições da integração Meta (Facebook/Instagram/WhatsApp) por empresa.
 *
 * O que estes ensaios prendem é o que torna isto seguro: os TOKENS ficam
 * CIFRADOS na base (nunca em claro), deixá-los em branco MANTÉM o que lá está
 * (não os apaga sem querer), e o webhook nasce com um endereço e um token de
 * verificação por empresa.
 */
class IntegracoesMetaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('crm');
        $this->comPermissoes('crm.integrations.manage');
    }

    /** @test */
    public function o_token_do_whatsapp_fica_cifrado_na_base(): void
    {
        Livewire::actingAs($this->user)->test(IntegracoesMeta::class)
            ->set('whatsapp_enabled', true)
            ->set('whatsapp_phone_number_id', '123456789')
            ->set('whatsapp_token', 'EAAG-token-secreto-do-whatsapp')
            ->call('save')
            ->assertHasNoErrors();

        // Pelo modelo, lê-se decifrado.
        $m = MetaIntegration::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('EAAG-token-secreto-do-whatsapp', $m->whatsapp_token);

        // Em cru na base, NÃO aparece o segredo.
        $cru = DB::table('meta_integrations')->where('tenant_id', $this->tenant->id)->value('whatsapp_token');
        $this->assertNotSame('EAAG-token-secreto-do-whatsapp', $cru);
        $this->assertStringNotContainsString('token-secreto', (string) $cru);
    }

    /**
     * DEIXAR EM BRANCO MANTÉM. Reabrir o ecrã e guardar sem reescrever o token
     * não pode apagá-lo — senão qualquer gravação de outra definição desligava
     * a integração.
     *
     * @test
     */
    public function guardar_sem_reescrever_o_token_nao_o_apaga(): void
    {
        $m = MetaIntegration::paraTenant($this->tenant->id);
        $m->whatsapp_token = 'token-original';
        $m->save();

        Livewire::actingAs($this->user)->test(IntegracoesMeta::class)
            ->assertSet('temWaToken', true)
            ->set('whatsapp_display_number', '+244912000000') // muda OUTRA coisa
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('token-original', $m->fresh()->whatsapp_token);
        $this->assertSame('+244912000000', $m->fresh()->whatsapp_display_number);
    }

    /** O webhook tem um URL por empresa e nasce com token de verificação. */
    public function test_webhook_url_e_token_por_empresa(): void
    {
        $comp = Livewire::actingAs($this->user)->test(IntegracoesMeta::class);

        $comp->assertSee('/webhooks/meta/'.$this->tenant->id);
        $this->assertNotEmpty($comp->get('webhook_verify_token'));
    }

    /** Sem a permissão, o ecrã não abre. */
    public function test_sem_permissao_nao_abre(): void
    {
        $outro = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id]);
        $outro->tenants()->attach($this->tenant->id);

        $this->actingAs($outro)->get('/crm/integracoes')->assertForbidden();
    }
}
