<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\SmsSettings;
use App\Models\SmsSetting;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\SmsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TenantTestCase;

class SuperAdminTelcoSmsTest extends TenantTestCase
{
    private function configureTelco(): SmsSetting
    {
        SmsSetting::where('tenant_id', $this->tenant->id)->delete();
        return SmsSetting::updateOrCreate(['tenant_id' => null], [
            'provider' => 'telcosms',
            'api_url' => 'https://www.telcosms.co.ao/api/v2/send_message',
            'api_token' => 'prd-global-telco',
            'sender_id' => 'SOSERP',
            'config' => ['telco_application' => 'soserp_prd', 'sender' => 'SOSERP'],
            'report_url' => null,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_guarda_chave_global_cifrada_e_nao_a_expoe(): void
    {
        $this->configureTelco();

        Livewire::actingAs($this->user)->test(SmsSettings::class)
            ->assertSet('provider', 'telcosms')
            ->assertSet('telco_application', 'soserp_prd')
            ->assertSet('api_token', '')
            ->assertSet('apiTokenGuardado', true)
            ->set('is_active', false)
            ->call('save')
            ->assertHasNoErrors();

        $setting = SmsSetting::whereNull('tenant_id')->firstOrFail();
        $this->assertSame('prd-global-telco', $setting->api_token);
        $this->assertSame('SOSERP', $setting->sender_id);
        $this->assertStringNotContainsString('prd-global-telco', $setting->getRawOriginal('api_token'));
        $this->assertArrayNotHasKey('api_token', $setting->toArray());
    }

    public function test_botao_de_teste_torna_o_gateway_selecionado_no_padrao(): void
    {
        SmsSetting::updateOrCreate(['tenant_id' => null], [
            'provider' => 'd7networks', 'api_url' => 'https://api.d7networks.com/messages/v1/send',
            'api_token' => 'token-d7-antigo', 'sender_id' => 'SOS ERP', 'is_active' => true,
        ]);
        Http::fake(['www.telcosms.co.ao/api/v2/send_message' => Http::response('', 200)]);

        Livewire::actingAs($this->user)->test(SmsSettings::class)
            ->set('provider', 'telcosms')
            ->set('api_token', 'prd-telco-nova')
            ->set('test_phone', '939729902')
            ->set('test_message', 'Teste de seleção')
            ->call('sendTestSms')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sms_settings', ['tenant_id' => null, 'provider' => 'telcosms', 'is_active' => 1]);
        $this->assertDatabaseHas('sms_logs', ['recipient' => '+244939729902', 'type' => 'test', 'status' => 'sent']);
    }

    public function test_aviso_de_plano_a_expirar_sai_pela_telcosms_global(): void
    {
        $this->configureTelco();
        $this->user->update(['phone' => '922123456']);
        SmsTemplate::updateOrCreate(['slug' => 'plan_expiring'], [
            'name' => 'Plano a expirar',
            'content' => 'Plano da {{tenant_name}} expira em {{days_remaining}} dias.',
            'is_active' => true,
            'tenant_id' => null,
        ]);
        Http::fake(['www.telcosms.co.ao/api/v2/send_message' => Http::response('', 204)]);

        $result = (new SmsService())->sendPlanExpiringSms($this->tenant, 5);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('sms_logs', ['tenant_id' => $this->tenant->id, 'type' => 'plan_expiring', 'status' => 'sent']);
        Http::assertSent(fn (Request $request) =>
            $request['message']['api_key_app'] === 'prd-global-telco'
            && $request['message']['phone_number'] === '922123456'
            && str_contains($request['message']['message_body'], 'expira em 5 dias')
        );
    }

    public function test_consulta_de_saldo_500_mostra_aviso_sem_declarar_falha_do_gateway(): void
    {
        $this->configureTelco();
        SmsLog::create([
            'tenant_id' => $this->tenant->id,
            'recipient' => '+244939729902',
            'message' => 'Teste aceite',
            'type' => 'test',
            'status' => 'sent',
            'sender_id' => 'SOSERP',
            'sent_at' => now(),
        ]);
        Http::fake([
            'www.telcosms.co.ao/api/v2/check_balance*' => Http::response([
                'error' => 'An error occurred while processing your request',
            ], 500),
        ]);

        Livewire::actingAs($this->user)->test(SmsSettings::class)
            ->call('checkBalance')
            ->assertDispatched('warning');
    }

    public function test_ambiente_qas_usa_a_chave_qas_cifrada(): void
    {
        $this->configureTelco();
        Http::fake(['www.telcosms.co.ao/api/v2/send_message' => Http::response('', 200)]);

        Livewire::actingAs($this->user)->test(SmsSettings::class)
            ->set('telco_application', 'soserp_qas')
            ->set('telco_api_key_qas', 'qas-chave-soserp')
            ->set('test_phone', '939729902')
            ->set('test_message', 'Teste QAS')
            ->call('sendTestSms')
            ->assertHasNoErrors();

        $setting = SmsSetting::whereNull('tenant_id')->firstOrFail();
        $this->assertSame('qas-chave-soserp', $setting->telco_api_key_qas);
        $this->assertStringNotContainsString('qas-chave-soserp', $setting->getRawOriginal('telco_api_key_qas'));
        Http::assertSent(fn (Request $request) =>
            $request['message']['api_key_app'] === 'qas-chave-soserp'
            && $request['message']['phone_number'] === '939729902'
        );
    }

    public function test_historico_filtra_por_gateway(): void
    {
        SmsLog::create([
            'recipient' => '+244939729902', 'message' => 'Mensagem Telco',
            'sender_id' => 'SOSERP', 'gateway' => 'telcosms', 'type' => 'test',
            'status' => 'sent', 'sent_at' => now(),
        ]);
        SmsLog::create([
            'recipient' => '+244922000000', 'message' => 'Mensagem D7',
            'sender_id' => 'SOS ERP', 'gateway' => 'd7networks', 'type' => 'test',
            'status' => 'sent', 'sent_at' => now(),
        ]);

        Livewire::actingAs($this->user)->test(SmsSettings::class)
            ->set('activeTab', 'logs')
            ->set('logGateway', 'telcosms')
            ->assertSee('Mensagem Telco')
            ->assertDontSee('Mensagem D7');
    }
}
