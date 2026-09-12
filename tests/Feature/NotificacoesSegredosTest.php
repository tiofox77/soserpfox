<?php

namespace Tests\Feature;

use App\Models\TenantNotificationSetting;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * As credenciais do ecrã de notificações.
 *
 * O ecrã guarda a senha do SMTP, o token do Twilio e o token da D7 Networks.
 * Este teste existe para provar duas coisas:
 *
 *   1. os valores NÃO viajam para o browser — o `type="password"` do campo
 *      esconde-os no ecrã e não no código-fonte da página;
 *   2. não estão em texto simples na base de dados.
 *
 * O ecrã é hoje React, e o que antes viajava numa propriedade pública do
 * componente passa agora por uma resposta JSON: a regra é a mesma, e é a
 * resposta que se verifica.
 */
class NotificacoesSegredosTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/notificacoes/definicoes';

    private const SENHA = 'S3nh4-SMTP-do-cliente';

    private const TOKEN = 'tok3n-secret0-da-d7';

    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('notifications');
        $this->comPermissoes('notifications.view', 'notifications.manage');
    }

    private function comCredenciais(): TenantNotificationSetting
    {
        $s = TenantNotificationSetting::getForTenant($this->tenant->id);

        $s->update([
            'email_enabled' => true,
            'smtp_host' => 'smtp.exemplo.ao',
            'smtp_port' => 587,
            'smtp_username' => 'contas@exemplo.ao',
            'smtp_password' => self::SENHA,
            'from_email' => 'contas@exemplo.ao',
            'sms_provider' => 'd7networks',
            'sms_api_token' => self::TOKEN,
        ]);

        return $s->fresh();
    }

    /** O corpo que o ecrã manda ao gravar, já com o que lá estava. */
    private function corpo(array $mudancas = []): array
    {
        $s = TenantNotificationSetting::getForTenant($this->tenant->id)->fresh();

        return array_replace_recursive([
            'email' => [
                'enabled' => (bool) $s->email_enabled,
                'smtp_host' => $s->smtp_host,
                'smtp_port' => $s->smtp_port,
                'smtp_username' => $s->smtp_username,
                // Os campos de segredo abrem VAZIOS: é o estado normal.
                'smtp_password' => '',
                'smtp_encryption' => $s->smtp_encryption ?? 'tls',
                'from_email' => $s->from_email,
                'from_name' => $s->from_name,
            ],
            'sms' => [
                'enabled' => (bool) $s->sms_enabled,
                'provider' => $s->sms_provider ?? '',
                'api_token' => '',
                'auth_token' => '',
            ],
            'whatsapp' => [
                'enabled' => (bool) $s->whatsapp_enabled,
                'provider' => $s->whatsapp_provider ?? 'twilio',
                'auth_token' => '',
                'from_number' => $s->whatsapp_from_number,
            ],
        ], $mudancas);
    }

    public function test_a_senha_do_smtp_nao_esta_em_texto_simples_na_base(): void
    {
        $this->comCredenciais();

        $bruto = DB::table('tenant_notification_settings')
            ->where('tenant_id', $this->tenant->id)
            ->value('smtp_password');

        $this->assertNotSame(self::SENHA, $bruto,
            'a senha do SMTP está gravada tal e qual — quem chegar à base lê-a');
    }

    public function test_o_token_da_operadora_nao_esta_em_texto_simples_na_base(): void
    {
        $this->comCredenciais();

        $bruto = DB::table('tenant_notification_settings')
            ->where('tenant_id', $this->tenant->id)
            ->value('sms_api_token');

        $this->assertNotSame(self::TOKEN, $bruto, 'o token da operadora está gravado tal e qual');
    }

    /**
     * O SEGREDO NÃO SAI DO SERVIDOR.
     *
     * O que a resposta leva é a informação de que existe um guardado — nunca o
     * valor. Um `type="password"` esconde os caracteres no ecrã; no corpo da
     * resposta, quem abrir as ferramentas do browser lia-os por extenso.
     */
    public function test_os_segredos_nao_viajam_para_o_browser(): void
    {
        $this->comCredenciais();

        $resposta = $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk();
        $bruto = $resposta->getContent();

        $this->assertStringNotContainsString(self::SENHA, $bruto, 'a senha do SMTP está na resposta');
        $this->assertStringNotContainsString(self::TOKEN, $bruto, 'o token da operadora está na resposta');

        // Mas o ecrã sabe que há um guardado, senão lia-se como «a minha senha
        // desapareceu».
        $resposta->assertJsonPath('segredos.smtp_password', true)
            ->assertJsonPath('segredos.sms_api_token', true)
            ->assertJsonPath('segredos.whatsapp_auth_token', false);
    }

    /**
     * GRAVAR SEM TOCAR NA SENHA NÃO A APAGA.
     *
     * É a consequência mais perigosa de os campos abrirem vazios: quem entra só
     * para mudar o nome do remetente carrega em Guardar, o campo da senha está
     * em branco, e sem este cuidado gravava-se o vazio por cima — o e-mail
     * deixava de sair e ninguém ligava uma coisa à outra.
     */
    public function test_gravar_sem_tocar_na_senha_nao_a_apaga(): void
    {
        $this->comCredenciais();

        $this->actingAs($this->user)
            ->putJson(self::RAIZ, $this->corpo(['email' => ['from_name' => 'Nome Novo']]))
            ->assertOk();

        $s = TenantNotificationSetting::getForTenant($this->tenant->id)->fresh();

        $this->assertSame('Nome Novo', $s->from_name);
        $this->assertSame(self::SENHA, $s->smtp_password, 'a senha guardada tem de ficar');
        $this->assertSame(self::TOKEN, $s->sms_api_token, 'o token guardado tem de ficar');
    }

    public function test_escrever_uma_senha_nova_substitui_a_anterior(): void
    {
        $this->comCredenciais();

        $this->actingAs($this->user)
            ->putJson(self::RAIZ, $this->corpo(['email' => ['smtp_password' => 'Outra-S3nha']]))
            ->assertOk();

        $this->assertSame('Outra-S3nha',
            TenantNotificationSetting::getForTenant($this->tenant->id)->fresh()->smtp_password);
    }

    public function test_um_email_remetente_invalido_e_recusado(): void
    {
        $this->comCredenciais();

        $this->actingAs($this->user)
            ->putJson(self::RAIZ, $this->corpo(['email' => ['from_email' => 'isto-nao-e-um-email']]))
            ->assertStatus(422)->assertJsonValidationErrors('email.from_email');

        $this->assertSame('contas@exemplo.ao',
            TenantNotificationSetting::getForTenant($this->tenant->id)->fresh()->from_email);
    }

    public function test_uma_porta_de_smtp_invalida_e_recusada(): void
    {
        $this->comCredenciais();

        $this->actingAs($this->user)
            ->putJson(self::RAIZ, $this->corpo(['email' => ['smtp_port' => 'abc']]))
            ->assertStatus(422)->assertJsonValidationErrors('email.smtp_port');
    }

    /**
     * COM O CANAL DESLIGADO NÃO SE EXIGE A CONFIGURAÇÃO DELE.
     *
     * Quem tem o SMS desligado não tem de preencher nada dele para poder gravar
     * o resto — senão o ecrã ficava impossível de guardar.
     */
    public function test_com_o_canal_desligado_nao_se_exige_a_configuracao_dele(): void
    {
        TenantNotificationSetting::getForTenant($this->tenant->id)
            ->update(['email_enabled' => false, 'sms_enabled' => false, 'whatsapp_enabled' => false]);

        $this->actingAs($this->user)
            ->putJson(self::RAIZ, $this->corpo(['email' => ['from_name' => 'Só o nome']]))
            ->assertOk();
    }

    /**
     * UMA OPERADORA LIGADA SEM CHAVE NÃO MANDA NADA.
     *
     * Ligá-la assim era ficar com o canal «activo» e nenhum SMS a sair — e
     * ninguém a perceber porquê.
     */
    public function test_ligar_uma_operadora_sem_chave_e_recusado(): void
    {
        $this->actingAs($this->user)
            ->putJson(self::RAIZ, $this->corpo([
                'sms' => ['enabled' => true, 'provider' => 'telcosms', 'api_token' => ''],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('sms.api_token');

        $this->assertFalse(
            (bool) TenantNotificationSetting::getForTenant($this->tenant->id)->fresh()->sms_enabled,
        );
    }

    public function test_o_modelo_nao_leva_credenciais_numa_serializacao(): void
    {
        // Um `toArray()` num log, num evento ou numa resposta de diagnóstico
        // levava a senha e os tokens consigo.
        $bruto = json_encode($this->comCredenciais()->toArray());

        $this->assertStringNotContainsString(self::SENHA, $bruto);
        $this->assertStringNotContainsString(self::TOKEN, $bruto);
    }

    /**
     * VER NÃO É CONFIGURAR.
     *
     * A página estava atrás de `notifications.view` — a mais fraca das três
     * permissões do módulo — e quem a abrisse mudava o servidor de saída da
     * empresa. `notifications.manage` existe desde o princípio e nunca ninguém
     * a pediu.
     */
    public function test_quem_so_ve_nao_configura(): void
    {
        $this->comCredenciais();

        $so = \App\Models\User::create([
            'name' => 'Só vê', 'email' => 'sove@empresa.ao', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $so->tenants()->syncWithoutDetaching([$this->tenant->id]);

        setPermissionsTeamId($this->tenant->id);
        $so->givePermissionTo('notifications.view');

        $this->actingAs($so)->getJson(self::RAIZ)->assertOk()
            ->assertJsonPath('permissoes.configurar', false);

        $this->actingAs($so)
            ->putJson(self::RAIZ, $this->corpo(['email' => ['from_name' => 'Roubado']]))
            ->assertForbidden();

        $this->assertNotSame('Roubado',
            TenantNotificationSetting::getForTenant($this->tenant->id)->fresh()->from_name);
    }
}
