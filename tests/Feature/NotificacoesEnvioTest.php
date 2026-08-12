<?php

namespace Tests\Feature;

use App\Models\NotificationTemplate;
use App\Models\TenantNotificationSetting;
use App\Services\Notifications\EnvioDeNotificacoes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TenantTestCase;

/**
 * O envio das notificações de modelo.
 *
 * Três defeitos viviam aqui e, juntos, explicam porque o módulo nunca
 * funcionou:
 *
 *   · `sendEmail` era um TODO — escrevia no log e não enviava nada;
 *   · `sendSMS` chamava `sendWhatsApp` — ligar o SMS mandava um WhatsApp;
 *   · não havia memória do que já tinha saído — cada passagem reenviava tudo.
 *
 * O terceiro é o que decide se o disparo por tráfego é possível: sem memória,
 * correr mais vezes não é uma melhoria, é mandar o mesmo aviso a cada janela
 * do dia — e em SMS e WhatsApp isso é dinheiro da empresa a sair.
 */
class NotificacoesEnvioTest extends TenantTestCase
{
    private function comEmailConfigurado(): void
    {
        TenantNotificationSetting::getForTenant($this->tenant->id)->update([
            'email_enabled' => true,
            'smtp_host'     => 'smtp.exemplo.ao',
            'smtp_port'     => 587,
            'smtp_username' => 'avisos@exemplo.ao',
            'smtp_password' => 'senha',
            'from_email'    => 'avisos@exemplo.ao',
            'from_name'     => 'Exemplo',
        ]);
    }

    private function comSmsConfigurado(): void
    {
        TenantNotificationSetting::getForTenant($this->tenant->id)->update([
            'sms_enabled'   => true,
            'sms_provider'  => 'd7networks',
            'sms_api_token' => 'token-de-teste',
            'sms_sender_id' => 'EXEMPLO',
        ]);
    }

    private function modelo(array $dados = []): NotificationTemplate
    {
        return NotificationTemplate::create(array_merge([
            'tenant_id'     => $this->tenant->id,
            'name'          => 'Factura a vencer',
            'slug'          => 'factura-vencer-' . uniqid(),
            'module'        => 'invoicing',
            'email_enabled' => true,
            'email_subject' => 'A sua factura {{numero}} vence em breve',
            'email_body'    => 'Caro cliente, a factura {{numero}} vence a {{data}}.',
            'sms_enabled'   => true,
            'sms_body'      => 'Factura {{numero}} vence a {{data}}.',
            'trigger_event' => 'date_approaching',
            'is_active'     => true,
        ], $dados));
    }

    public function test_o_email_e_mesmo_enviado(): void
    {
        // Era um TODO: escrevia no log e devolvia. O canal mais usado dos três
        // nunca mandou um único email de modelo.
        Mail::fake();
        $this->comEmailConfigurado();

        $enviou = (new EnvioDeNotificacoes)->enviar(
            $this->modelo(),
            'email',
            'cliente@exemplo.ao',
            ['numero' => 'FT 2026/1', 'data' => '15/08/2026'],
            101
        );

        $this->assertTrue($enviou);
        Mail::assertSentCount(1);
    }

    public function test_as_variaveis_sao_substituidas_no_email(): void
    {
        Mail::fake();
        $this->comEmailConfigurado();

        (new EnvioDeNotificacoes)->enviar(
            $this->modelo(),
            'email',
            'cliente@exemplo.ao',
            ['numero' => 'FT 2026/1', 'data' => '15/08/2026'],
            101
        );

        Mail::assertSent(
            \App\Mail\NotificacaoDeModelo::class,
            fn ($mail) => str_contains($mail->assuntoDaNotificacao, 'FT 2026/1')
                && str_contains($mail->corpoHtml, '15/08/2026')
        );
    }

    public function test_o_sms_vai_pela_operadora_e_nao_pelo_whatsapp(): void
    {
        // `sendSMS` chamava `sendWhatsApp`: quem ligasse o SMS num modelo
        // recebia um WhatsApp, e o resumo contava-o como SMS.
        Http::fake(['*d7networks*' => Http::response(['data' => ['request_id' => 'x']], 200)]);
        $this->comSmsConfigurado();

        (new EnvioDeNotificacoes)->enviar(
            $this->modelo(),
            'sms',
            '+244923000000',
            ['numero' => 'FT 2026/1', 'data' => '15/08/2026'],
            101
        );

        Http::assertSent(fn ($r) => str_contains($r->url(), 'd7networks'));
    }

    public function test_o_mesmo_aviso_nao_sai_duas_vezes_no_mesmo_dia(): void
    {
        // A razão de existir da tabela `notification_sends`, e a condição para
        // o disparo por tráfego ser seguro.
        Mail::fake();
        $this->comEmailConfigurado();

        $modelo  = $this->modelo();
        $servico = new EnvioDeNotificacoes;

        $primeiro = $servico->enviar($modelo, 'email', 'cliente@exemplo.ao', ['numero' => 'FT 1'], 101);
        $segundo  = $servico->enviar($modelo, 'email', 'cliente@exemplo.ao', ['numero' => 'FT 1'], 101);
        $terceiro = $servico->enviar($modelo, 'email', 'cliente@exemplo.ao', ['numero' => 'FT 1'], 101);

        $this->assertTrue($primeiro);
        $this->assertFalse($segundo);
        $this->assertFalse($terceiro);

        Mail::assertSentCount(1);
        $this->assertSame(2, $servico->contagem()['repetidos']);
    }

    public function test_registos_diferentes_recebem_avisos_diferentes(): void
    {
        // Sem o id do registo na chave, avisar sobre a factura A impedia o
        // aviso sobre a factura B no mesmo dia.
        Mail::fake();
        $this->comEmailConfigurado();

        $modelo  = $this->modelo();
        $servico = new EnvioDeNotificacoes;

        $servico->enviar($modelo, 'email', 'cliente@exemplo.ao', ['numero' => 'FT 1'], 101);
        $servico->enviar($modelo, 'email', 'cliente@exemplo.ao', ['numero' => 'FT 2'], 102);

        Mail::assertSentCount(2);
    }

    public function test_destinatarios_diferentes_recebem_cada_um_o_seu(): void
    {
        Mail::fake();
        $this->comEmailConfigurado();

        $modelo  = $this->modelo();
        $servico = new EnvioDeNotificacoes;

        $servico->enviar($modelo, 'email', 'ana@exemplo.ao', ['numero' => 'FT 1'], 101);
        $servico->enviar($modelo, 'email', 'joao@exemplo.ao', ['numero' => 'FT 1'], 101);

        Mail::assertSentCount(2);
    }

    public function test_canais_diferentes_do_mesmo_aviso_nao_se_bloqueiam(): void
    {
        Mail::fake();
        Http::fake(['*d7networks*' => Http::response(['data' => ['request_id' => 'x']], 200)]);
        $this->comEmailConfigurado();
        $this->comSmsConfigurado();

        $modelo  = $this->modelo();
        $servico = new EnvioDeNotificacoes;

        $email = $servico->enviar($modelo, 'email', 'cliente@exemplo.ao', ['numero' => 'FT 1'], 101);
        $sms   = $servico->enviar($modelo, 'sms', '+244923000000', ['numero' => 'FT 1'], 101);

        $this->assertTrue($email);
        $this->assertTrue($sms);
    }

    public function test_amanha_o_aviso_pode_repetir_se(): void
    {
        // Um aviso de "factura a vencer" deve poder repetir-se no dia seguinte
        // se continuar por pagar. O que não pode é repetir-se daqui a cinco
        // minutos.
        Mail::fake();
        $this->comEmailConfigurado();

        $modelo = $this->modelo();

        (new EnvioDeNotificacoes)->enviar($modelo, 'email', 'cliente@exemplo.ao', ['numero' => 'FT 1'], 101);

        // Envelhecer o registo de ontem.
        DB::table('notification_sends')->update(['window_date' => now()->subDay()->toDateString()]);

        $hoje = (new EnvioDeNotificacoes)->enviar($modelo, 'email', 'cliente@exemplo.ao', ['numero' => 'FT 1'], 101);

        $this->assertTrue($hoje);
        Mail::assertSentCount(2);
    }

    public function test_um_canal_por_configurar_fica_registado_como_falha(): void
    {
        // Devolver falso é honesto: fica visível que não saiu, em vez de sair
        // um WhatsApp a fingir de SMS.
        $modelo = $this->modelo();   // sem SMS configurado na empresa

        $enviou = (new EnvioDeNotificacoes)->enviar($modelo, 'sms', '+244923000000', [], 101);

        $this->assertFalse($enviou);

        $this->assertSame(
            'failed',
            DB::table('notification_sends')->where('channel', 'sms')->value('status')
        );
    }

    public function test_uma_falha_de_envio_nao_lanca_para_fora(): void
    {
        // Isto corre à boleia do pedido de quem está a trabalhar. Um SMTP em
        // baixo não pode derrubar a página dessa pessoa.
        $this->comEmailConfigurado();

        Mail::shouldReceive('send')->andThrow(new \RuntimeException('SMTP em baixo'));

        $enviou = (new EnvioDeNotificacoes)->enviar(
            $this->modelo(),
            'email',
            'cliente@exemplo.ao',
            ['numero' => 'FT 1'],
            101
        );

        $this->assertFalse($enviou, 'devolve falso em vez de lançar');

        $this->assertSame(
            'failed',
            DB::table('notification_sends')->where('channel', 'email')->value('status')
        );
    }
}
