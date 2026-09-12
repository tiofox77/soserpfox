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
    private const RAIZ = '/api/v1/invoicing/react/crm/meta';

    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('crm');
        $this->comPermissoes('crm.integrations.manage');
    }

    /** @test */
    public function o_token_do_whatsapp_fica_cifrado_na_base(): void
    {
        $this->actingAs($this->user)->putJson(self::RAIZ, [
            'webhook_verify_token' => 'sos-ensaio-1234',
            'whatsapp_enabled' => true,
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_token' => 'EAAG-token-secreto-do-whatsapp',
        ])->assertOk();

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

        // O ecrã diz que o segredo está lá — sem nunca o devolver.
        $this->actingAs($this->user)->getJson(self::RAIZ)
            ->assertOk()
            ->assertJsonPath('segredos.whatsapp_token', true)
            ->assertDontSee('token-original');

        $this->actingAs($this->user)->putJson(self::RAIZ, [
            'webhook_verify_token' => $m->fresh()->webhook_verify_token,
            'whatsapp_display_number' => '+244912000000', // muda OUTRA coisa
        ])->assertOk();

        $this->assertSame('token-original', $m->fresh()->whatsapp_token);
        $this->assertSame('+244912000000', $m->fresh()->whatsapp_display_number);
    }

    /** O webhook tem um URL por empresa e nasce com token de verificação. */
    public function test_webhook_url_e_token_por_empresa(): void
    {
        $r = $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk();

        $this->assertStringContainsString('/webhooks/meta/'.$this->tenant->id, $r->json('url_do_webhook'));
        $this->assertNotEmpty($r->json('data.webhook_verify_token'));
    }

    /**
     * O SEGREDO NÃO VOLTA AO ECRÃ — nem ao de quem o escreveu.
     *
     * O ecrã em Livewire tinha o cuidado de não o mandar de volta; esta é a
     * mesma promessa, agora medida à porta.
     */
    public function test_os_segredos_nunca_saem_pela_porta(): void
    {
        $m = MetaIntegration::paraTenant($this->tenant->id);
        $m->whatsapp_token = 'token-que-nao-pode-sair';
        $m->app_secret = 'segredo-que-nao-pode-sair';
        $m->save();

        $this->actingAs($this->user)->getJson(self::RAIZ)
            ->assertOk()
            ->assertDontSee('token-que-nao-pode-sair')
            ->assertDontSee('segredo-que-nao-pode-sair')
            ->assertJsonPath('segredos.whatsapp_token', true)
            ->assertJsonPath('segredos.app_secret', true);
    }

    /** E sem a permissão, a porta fecha-se — não só o ecrã. */
    public function test_sem_permissao_a_porta_fecha_se(): void
    {
        $outro = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id]);
        $outro->tenants()->attach($this->tenant->id);

        $this->actingAs($outro)->getJson(self::RAIZ)->assertForbidden();
        $this->actingAs($outro)->putJson(self::RAIZ, [
            'webhook_verify_token' => 'sos-tentativa-1234',
        ])->assertForbidden();
        $this->actingAs($outro)->postJson(self::RAIZ.'/testar-whatsapp')->assertForbidden();
    }

    /** Sem a permissão, o ecrã não abre. */
    public function test_sem_permissao_nao_abre(): void
    {
        $outro = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id]);
        $outro->tenants()->attach($this->tenant->id);

        $this->actingAs($outro)->get('/crm/integracoes')->assertForbidden();
    }
}
