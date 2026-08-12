<?php

namespace Tests\Feature;

use App\Livewire\Settings\NotificationSettings;
use App\Models\TenantNotificationSetting;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * As credenciais do ecrã de notificações.
 *
 * O ecrã guarda a senha do SMTP, o token do Twilio e o token da D7 Networks.
 * Este teste existe para provar duas coisas que eu suspeitava e não queria
 * afirmar sem prova:
 *
 *   1. os valores viajam para o browser em cada render, porque estão em
 *      propriedades públicas de um componente Livewire — o `type="password"`
 *      do campo esconde-os no ecrã e não no código-fonte da página;
 *   2. estão em texto simples na base de dados, sem `encrypted` no cast.
 */
class NotificacoesSegredosTest extends TenantTestCase
{
    private const SENHA = 'S3nh4-SMTP-do-cliente';
    private const TOKEN = 'tok3n-secret0-da-d7';

    private function comCredenciais(): TenantNotificationSetting
    {
        $s = TenantNotificationSetting::getForTenant($this->tenant->id);

        $s->update([
            'email_enabled'  => true,
            'smtp_host'      => 'smtp.exemplo.ao',
            'smtp_username'  => 'contas@exemplo.ao',
            'smtp_password'  => self::SENHA,
            'from_email'     => 'contas@exemplo.ao',
            'sms_provider'   => 'd7networks',
            'sms_api_token'  => self::TOKEN,
        ]);

        return $s->fresh();
    }

    public function test_a_senha_do_smtp_esta_em_texto_simples_na_base(): void
    {
        $this->comCredenciais();

        $bruto = DB::table('tenant_notification_settings')
            ->where('tenant_id', $this->tenant->id)
            ->value('smtp_password');

        $this->assertNotSame(
            self::SENHA,
            $bruto,
            'a senha do SMTP está gravada tal e qual — quem chegar à base lê-a'
        );
    }

    public function test_o_token_da_operadora_esta_em_texto_simples_na_base(): void
    {
        $this->comCredenciais();

        $bruto = DB::table('tenant_notification_settings')
            ->where('tenant_id', $this->tenant->id)
            ->value('sms_api_token');

        $this->assertNotSame(self::TOKEN, $bruto, 'o token da operadora está gravado tal e qual');
    }

    public function test_a_senha_nao_pode_viajar_para_o_browser(): void
    {
        // Em Livewire, as propriedades públicas são serializadas para dentro
        // da página. O campo é `type="password"`, o que esconde os caracteres
        // no ecrã — e não no código-fonte, onde a senha aparece por extenso a
        // quem abrir as ferramentas do browser.
        $this->comCredenciais()->refresh();

        $html = Livewire::test(NotificationSettings::class)->html();

        $this->assertStringNotContainsString(
            self::SENHA,
            $html,
            'a senha do SMTP está no HTML enviado ao browser'
        );
    }

    public function test_o_token_da_operadora_nao_pode_viajar_para_o_browser(): void
    {
        $this->comCredenciais()->refresh();

        $html = Livewire::test(NotificationSettings::class)->html();

        $this->assertStringNotContainsString(
            self::TOKEN,
            $html,
            'o token da operadora está no HTML enviado ao browser'
        );
    }

    public function test_gravar_sem_tocar_na_senha_nao_a_apaga(): void
    {
        // A consequência mais perigosa de esvaziar os campos: quem entra só
        // para mudar o nome do remetente carrega em Guardar, o campo da senha
        // está vazio, e sem este cuidado gravava-se o vazio por cima — o email
        // deixava de sair e ninguém ligava uma coisa à outra.
        $this->comCredenciais();

        Livewire::test(NotificationSettings::class)
            ->set('from_name', 'Nome Novo')
            ->call('save')
            ->assertHasNoErrors();

        $s = TenantNotificationSetting::getForTenant($this->tenant->id)->fresh();

        $this->assertSame('Nome Novo', $s->from_name);
        $this->assertSame(self::SENHA, $s->smtp_password, 'a senha guardada tem de ficar');
        $this->assertSame(self::TOKEN, $s->sms_api_token, 'o token guardado tem de ficar');
    }

    public function test_escrever_uma_senha_nova_substitui_a_anterior(): void
    {
        $this->comCredenciais();

        Livewire::test(NotificationSettings::class)
            ->set('smtp_password', 'Outra-S3nha')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            'Outra-S3nha',
            TenantNotificationSetting::getForTenant($this->tenant->id)->fresh()->smtp_password
        );
    }

    public function test_o_ecra_diz_que_ha_segredo_guardado_sem_o_mostrar(): void
    {
        // O campo abre vazio. Sem um sinal de que existe algo guardado, lê-se
        // como "a minha senha desapareceu".
        $this->comCredenciais();

        Livewire::test(NotificationSettings::class)
            ->assertSet('segredosGuardados.smtp_password', true)
            ->assertSet('smtp_password', '')
            // O separador de email não é o que abre por omissão, e o parcial só
            // é desenhado quando está activo.
            ->set('activeTab', 'email')
            ->assertSee('guardada');
    }

    public function test_um_email_remetente_invalido_e_recusado(): void
    {
        // Não havia validação nenhuma no gravar: um remetente inválido ficava
        // gravado e só dava erro na primeira tentativa de envio, sem ninguém
        // ligar uma coisa à outra.
        $this->comCredenciais();

        Livewire::test(NotificationSettings::class)
            ->set('from_email', 'isto-nao-e-um-email')
            ->call('save')
            ->assertHasErrors(['from_email' => 'email']);

        $this->assertSame(
            'contas@exemplo.ao',
            TenantNotificationSetting::getForTenant($this->tenant->id)->fresh()->from_email
        );
    }

    public function test_uma_porta_de_smtp_invalida_e_recusada(): void
    {
        $this->comCredenciais();

        Livewire::test(NotificationSettings::class)
            ->set('smtp_port', 'abc')
            ->call('save')
            ->assertHasErrors('smtp_port');
    }

    public function test_com_o_canal_desligado_nao_se_exige_a_configuracao_dele(): void
    {
        // Quem tem o SMS desligado não tem de preencher nada dele para poder
        // gravar o resto — senão o ecrã ficava impossível de guardar.
        TenantNotificationSetting::getForTenant($this->tenant->id)
            ->update(['email_enabled' => false, 'sms_enabled' => false, 'whatsapp_enabled' => false]);

        Livewire::test(NotificationSettings::class)
            ->set('from_name', 'Só o nome')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_o_modelo_nao_leva_credenciais_numa_serializacao(): void
    {
        // Um `toArray()` num log, num evento ou numa resposta de diagnóstico
        // levava a senha e os tokens consigo.
        $bruto = json_encode($this->comCredenciais()->toArray());

        $this->assertStringNotContainsString(self::SENHA, $bruto);
        $this->assertStringNotContainsString(self::TOKEN, $bruto);
    }
}
