<?php

namespace Tests\Feature;

use App\Livewire\Settings\NotificationSettings;
use App\Models\TenantNotificationSetting;
use App\Services\TelcoSmsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TenantTestCase;

class TelcoSmsTest extends TenantTestCase
{
    public function test_gateway_telcosms_tambem_esta_acessivel_pelas_configuracoes_da_faturacao(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.settings.view');

        $this->get(route('invoicing.notification-gateways'))
            ->assertOk()
            ->assertSee('TelcoSMS Angola')
            ->assertSee('Configurações SMS');
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
        Livewire::actingAs($this->user)->test(NotificationSettings::class)
            ->set('email_enabled', false)
            ->set('sms_enabled', true)
            ->set('sms_provider', 'telcosms')
            ->set('sms_api_token', 'prd-segredo-telco')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('sms_api_token', '')
            ->assertSet('segredosGuardados.sms_api_token', true);

        $setting = TenantNotificationSetting::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('telcosms', $setting->sms_provider);
        $this->assertSame('prd-segredo-telco', $setting->sms_api_token);
        $this->assertStringNotContainsString('prd-segredo-telco', $setting->getRawOriginal('sms_api_token'));
        $this->assertArrayNotHasKey('sms_api_token', $setting->toArray());
    }
}
