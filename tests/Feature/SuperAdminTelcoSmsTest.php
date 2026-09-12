<?php

namespace Tests\Feature;

use App\Models\SmsLog;
use App\Models\SmsSetting;
use App\Models\SmsTemplate;
use App\Services\SmsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

/**
 * O SMS da plataforma pela TelcoSMS.
 *
 * O ecrã passou a React e fala com `/api/v1/plataforma/react/sms`, atrás da
 * guarda do dono da plataforma.
 */
class SuperAdminTelcoSmsTest extends TenantTestCase
{
    private const API = '/api/v1/plataforma/react/sms';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($this->user->fresh());
    }

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

    private function formulario(array $troca = []): array
    {
        return array_merge([
            'provider' => 'telcosms',
            'api_url' => 'https://www.telcosms.co.ao/api/v2/send_message',
            'api_token' => '',
            'telco_api_key_qas' => '',
            'sender_id' => 'SOSERP',
            'telco_application' => 'soserp_prd',
            'report_url' => '',
            'is_active' => true,
        ], $troca);
    }

    public function test_super_admin_guarda_chave_global_cifrada_e_nao_a_expoe(): void
    {
        $this->configureTelco();

        $conf = $this->getJson(self::API)->assertOk()->json('configuracao');

        $this->assertSame('telcosms', $conf['provider']);
        $this->assertSame('soserp_prd', $conf['telco_application']);
        $this->assertTrue($conf['token_guardado']);
        $this->assertStringNotContainsString('prd-global-telco', $this->getJson(self::API)->getContent(), 'a chave não volta ao browser');

        // Gravar com o campo vazio mantém a chave.
        $this->putJson(self::API, $this->formulario(['is_active' => false]))->assertOk();

        $setting = SmsSetting::whereNull('tenant_id')->firstOrFail();
        $this->assertSame('prd-global-telco', $setting->api_token);
        $this->assertSame('SOSERP', $setting->sender_id);
        $this->assertFalse((bool) $setting->is_active);
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

        $this->postJson(self::API . '/testar', $this->formulario([
            'api_token' => 'prd-telco-nova',
            'test_phone' => '939729902',
            'test_message' => 'Teste de seleção',
        ]))->assertOk();

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

        // Um AVISO (200 com aviso), e não um erro: a chave pode estar boa.
        $this->postJson(self::API . '/saldo')->assertOk()->assertJson(['aviso' => true]);
    }

    public function test_ambiente_qas_usa_a_chave_qas_cifrada(): void
    {
        $this->configureTelco();
        Http::fake(['www.telcosms.co.ao/api/v2/send_message' => Http::response('', 200)]);

        $this->postJson(self::API . '/testar', $this->formulario([
            'telco_application' => 'soserp_qas',
            'telco_api_key_qas' => 'qas-chave-soserp',
            'test_phone' => '939729902',
            'test_message' => 'Teste QAS',
        ]))->assertOk();

        $setting = SmsSetting::whereNull('tenant_id')->firstOrFail();
        $this->assertSame('qas-chave-soserp', $setting->telco_api_key_qas);
        $this->assertStringNotContainsString('qas-chave-soserp', $setting->getRawOriginal('telco_api_key_qas'));
        Http::assertSent(fn (Request $request) =>
            $request['message']['api_key_app'] === 'qas-chave-soserp'
            && $request['message']['phone_number'] === '939729902'
        );
    }

    public function test_a_aplicacao_qas_sem_chave_e_recusada(): void
    {
        $this->configureTelco();

        $this->putJson(self::API, $this->formulario(['telco_application' => 'soserp_qas']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('telco_api_key_qas');
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

        $mensagens = array_column($this->getJson(self::API . '/historico?gateway=telcosms')->assertOk()->json('registos'), 'mensagem');

        $this->assertContains('Mensagem Telco', $mensagens);
        $this->assertNotContains('Mensagem D7', $mensagens);
    }

    /** O identificador de um modelo não muda: é por ele que o sistema o pede. */
    public function test_editar_um_modelo_nao_muda_o_identificador(): void
    {
        $m = SmsTemplate::updateOrCreate(['slug' => 'plan_expiring'], [
            'name' => 'Plano a expirar', 'content' => 'x', 'is_active' => true, 'tenant_id' => null,
        ]);

        $this->putJson(self::API . "/modelos/{$m->id}", [
            'name' => 'Novo nome', 'content' => 'Outro texto', 'slug' => 'outro-slug', 'is_active' => true,
        ])->assertOk();

        $m->refresh();
        $this->assertSame('plan_expiring', $m->slug);
        $this->assertSame('Outro texto', $m->content);
    }
}
