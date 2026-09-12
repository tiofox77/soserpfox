<?php

namespace Tests\Feature;

use App\Models\TenantNotificationSetting;
use App\Services\TelcoSmsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

class TelcoSmsTest extends TenantTestCase
{
    public function test_gateway_telcosms_tambem_esta_acessivel_pelas_configuracoes_da_faturacao(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.settings.view');

        $this->get(route('invoicing.notification-gateways'))
            ->assertOk()
            ->assertSee('data-ecra="facturacao/gateways-de-notificacao"', false);

        $this->getJson('/api/v1/invoicing/react/notification-gateways')
            ->assertOk()
            ->assertJsonPath('permissoes.pode_editar', false)
            ->assertJsonCount(12, 'eventos')
            ->assertJsonMissingPath('definicoes.sms_api_token');
    }

    public function test_api_guarda_chave_sem_a_expor_e_preserva_a_chave_quando_o_campo_volta_vazio(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');
        $url = '/api/v1/invoicing/react/notification-gateways';
        $base = $this->getJson($url)->assertOk()->json('definicoes');

        $this->putJson($url, array_merge($base, [
            'email_enabled' => false,
            'sms_enabled' => true,
            'sms_provider' => 'telcosms',
            'sms_api_token' => 'prd-segredo-react',
            'whatsapp_enabled' => false,
        ]))->assertOk()->assertJsonPath('segredos_guardados.sms_api_token', true);

        $this->getJson($url)->assertOk()->assertJsonMissingPath('definicoes.sms_api_token');

        $this->putJson($url, array_merge($this->getJson($url)->json('definicoes'), [
            'sms_enabled' => true,
            'sms_provider' => 'telcosms',
            'sms_api_token' => '',
        ]))->assertOk();

        $setting = TenantNotificationSetting::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('prd-segredo-react', $setting->sms_api_token);
        $this->assertSame('SOSERP', $setting->sms_sender_id);
    }

    public function test_envia_json_oficial_e_aceita_resposta_vazia(): void
    {
        Http::fake(['www.telcosms.co.ao/api/v2/send_message' => Http::response('', 204)]);

        $result = (new TelcoSmsService('prd-chave'))->send('+244922123456', 'Mensagem de teste');

        $this->assertTrue($result['success']);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://www.telcosms.co.ao/api/v2/send_message'
                && $request['message']['api_key_app'] === 'prd-chave'
                && $request['message']['phone_number'] === '922123456'
                && $request['message']['message_body'] === 'Mensagem de teste';
        });
    }

    public function test_consulta_saldo_pela_chave_da_aplicacao(): void
    {
        Http::fake(['www.telcosms.co.ao/api/v2/check_balance*' => Http::response(['balance' => 350], 200)]);

        $result = (new TelcoSmsService('prd-chave'))->checkBalance();

        $this->assertTrue($result['success']);
        $this->assertSame(350, $result['balance']);
        Http::assertSent(fn (Request $request) => $request['api_key_app'] === 'prd-chave');
    }

    public function test_erro_500_do_saldo_e_classificado_como_indisponibilidade(): void
    {
        Http::fake([
            'www.telcosms.co.ao/api/v2/check_balance*' => Http::response([
                'error' => 'An error occurred while processing your request',
            ], 500),
        ]);

        $result = (new TelcoSmsService('prd-chave'))->checkBalance();

        $this->assertFalse($result['success']);
        $this->assertTrue($result['balance_unavailable']);
        $this->assertSame(500, $result['status']);
    }

    public function test_tenant_guarda_chave_telcosms_cifrada_sem_a_devolver_ao_browser(): void
    {
        $this->comModulo('notifications')->comPermissoes('notifications.view', 'notifications.manage');

        $this->actingAs($this->user)
            ->putJson('/api/v1/invoicing/react/notificacoes/definicoes', [
                'email' => ['enabled' => false],
                'sms' => ['enabled' => true, 'provider' => 'telcosms', 'api_token' => 'prd-segredo-telco'],
                'whatsapp' => ['enabled' => false],
            ])
            ->assertOk()
            // O ecrã volta a mostrar o campo vazio — a dizer que há um guardado.
            ->assertJsonPath('segredos.sms_api_token', true);

        $setting = TenantNotificationSetting::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('telcosms', $setting->sms_provider);
        $this->assertSame('prd-segredo-telco', $setting->sms_api_token);
        $this->assertStringNotContainsString('prd-segredo-telco', $setting->getRawOriginal('sms_api_token'));
        $this->assertArrayNotHasKey('sms_api_token', $setting->toArray());
    }
}
