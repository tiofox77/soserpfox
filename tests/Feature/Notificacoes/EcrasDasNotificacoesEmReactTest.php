<?php

namespace Tests\Feature\Notificacoes;

use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\TenantNotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TenantTestCase;

/**
 * OS DOIS ECRÃS DAS NOTIFICAÇÕES EM REACT.
 *
 * O QUE A MIGRAÇÃO DESTAPOU, e é grave: o ecrã dos modelos lia e escrevia por
 * `NotificationTemplate::findOrFail($id)` — sem UMA verificação de empresa. Um
 * número escrito à mão abria, alterava e APAGAVA o modelo de outra casa.
 *
 * E o «enviar um teste» ia buscar as definições pelo `tenant_id` do modelo
 * encontrado: mandava um e-mail pelo servidor SMTP DE OUTRA EMPRESA, com o
 * endereço dela, a pedido de quem escrevesse o número.
 *
 * O TESTE TAMBÉM MENTIA. O canal de SMS era um `TODO` que escrevia no log e
 * somava «SMS» aos canais enviados: o ecrã dizia «Teste enviado com sucesso via
 * SMS» sem nada ter saído. Agora passa pelo mesmo caminho do envio a sério.
 */
class EcrasDasNotificacoesEmReactTest extends TenantTestCase
{
    private const DEFINICOES = '/api/v1/invoicing/react/notificacoes/definicoes';

    private const MODELOS = '/api/v1/invoicing/react/notificacoes/modelos';

    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('notifications');
    }

    private function modelo(array $extra = [], ?int $empresa = null): NotificationTemplate
    {
        return NotificationTemplate::create(array_merge([
            'tenant_id' => $empresa ?? $this->tenant->id,
            'name' => 'Aviso '.uniqid(),
            'slug' => 'aviso-'.uniqid(),
            'module' => 'events',
            'trigger_event' => 'created',
            'email_enabled' => true,
            'email_subject' => 'Olá {{ cliente }}',
            'email_body' => 'O evento {{event}} é a {{ date }}.',
            'is_active' => true,
        ], $extra));
    }

    private function outraEmpresa(): Tenant
    {
        return Tenant::create([
            'name' => 'Outra Empresa', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(800000000, 899999999),
            'email' => 'outra'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);
    }

    /* ─── As portas ───────────────────────────────────────────────────── */

    /** @test */
    public function os_dois_ecras_abrem_e_montam_o_react(): void
    {
        $this->comPermissoes('notifications.view');

        foreach ([
            'notifications.settings' => 'notificacoes/definicoes',
            'notifications.templates' => 'notificacoes/modelos',
        ] as $rota => $ecra) {
            $this->get(route($rota))->assertOk()->assertSee($ecra, false);
        }
    }

    public function test_sem_permissao_nenhum_dos_dois_abre(): void
    {
        foreach (['notifications.settings', 'notifications.templates'] as $rota) {
            $this->get(route($rota))->assertForbidden();
        }
    }

    /* ─── O escopo dos modelos ────────────────────────────────────────── */

    /**
     * O MODELO DE OUTRA EMPRESA NÃO SE LÊ, NÃO SE MEXE E NÃO SE APAGA.
     *
     * Era esta a linha que faltava: `findOrFail($id)` sem `where('tenant_id')`.
     */
    public function test_o_modelo_de_outra_empresa_esta_fechado(): void
    {
        $this->comPermissoes('notifications.view', 'notifications.manage');

        $alheio = $this->modelo(['name' => 'De outra casa'], $this->outraEmpresa()->id);

        $this->actingAs($this->user)->getJson(self::MODELOS.'/'.$alheio->id)->assertNotFound();

        $this->actingAs($this->user)->putJson(self::MODELOS.'/'.$alheio->id, [
            'name' => 'Roubado', 'module' => 'events', 'trigger_event' => 'created',
        ])->assertNotFound();

        $this->actingAs($this->user)->postJson(self::MODELOS.'/'.$alheio->id.'/estado', [])->assertNotFound();
        $this->actingAs($this->user)->deleteJson(self::MODELOS.'/'.$alheio->id)->assertNotFound();

        $this->assertSame('De outra casa', $alheio->fresh()->name);
        $this->assertNotNull(NotificationTemplate::find($alheio->id));
    }

    /**
     * E NÃO SE TESTA.
     *
     * Era o pior dos furos: o teste ia buscar as definições pelo `tenant_id` do
     * modelo, e mandava um e-mail pelo SMTP de outra empresa, com o endereço
     * dela, a pedido de quem escrevesse o número.
     */
    public function test_nao_se_testa_o_modelo_de_outra_empresa(): void
    {
        $this->comPermissoes('notifications.view', 'notifications.send');
        Mail::fake();

        $alheio = $this->modelo([], $this->outraEmpresa()->id);

        $this->actingAs($this->user)->getJson(self::MODELOS.'/'.$alheio->id.'/teste')->assertNotFound();

        $this->actingAs($this->user)->postJson(self::MODELOS.'/'.$alheio->id.'/testar', [
            'canais' => ['email'], 'email' => 'intruso@exemplo.ao', 'variaveis' => [],
        ])->assertNotFound();

        Mail::assertNothingSent();
    }

    /** A lista também não atravessa empresas. */
    public function test_a_lista_e_so_desta_empresa(): void
    {
        $this->comPermissoes('notifications.view');

        $this->modelo(['name' => 'Desta casa']);
        $this->modelo(['name' => 'Da outra casa'], $this->outraEmpresa()->id);

        $nomes = collect($this->actingAs($this->user)->getJson(self::MODELOS)->assertOk()->json('data'))
            ->pluck('nome')->all();

        $this->assertContains('Desta casa', $nomes);
        $this->assertNotContains('Da outra casa', $nomes);
    }

    /* ─── Ver não é gerir ─────────────────────────────────────────────── */

    /**
     * VER NÃO É GERIR.
     *
     * As duas páginas estavam atrás de `notifications.view` — a mais fraca das
     * três permissões do módulo — e quem as abrisse apagava modelos.
     */
    public function test_quem_so_ve_nao_apaga_nem_edita(): void
    {
        $this->comPermissoes('notifications.view');

        $m = $this->modelo();

        $this->actingAs($this->user)->getJson(self::MODELOS)->assertOk();
        $this->actingAs($this->user)->getJson(self::MODELOS.'/opcoes')
            ->assertOk()->assertJsonPath('permissoes.gerir', false);

        $this->actingAs($this->user)->putJson(self::MODELOS.'/'.$m->id, [
            'name' => 'Roubado', 'module' => 'events', 'trigger_event' => 'created',
        ])->assertForbidden();

        $this->actingAs($this->user)->deleteJson(self::MODELOS.'/'.$m->id)->assertForbidden();
        $this->actingAs($this->user)->postJson(self::MODELOS.'/'.$m->id.'/estado', [])->assertForbidden();

        $this->assertNotNull(NotificationTemplate::find($m->id));
    }

    /* ─── O modelo ────────────────────────────────────────────────────── */

    /**
     * UM CANAL LIGADO SEM TEXTO manda uma mensagem em branco.
     *
     * Não havia validação nenhuma: gravava-se um modelo de e-mail com o canal
     * ligado e o assunto vazio.
     */
    public function test_um_canal_ligado_sem_texto_e_recusado(): void
    {
        $this->comPermissoes('notifications.view', 'notifications.manage');

        $this->actingAs($this->user)->postJson(self::MODELOS, [
            'name' => 'Mudo', 'module' => 'events', 'trigger_event' => 'created',
            'email_enabled' => true, 'email_subject' => '', 'email_body' => '',
        ])->assertStatus(422)->assertJsonValidationErrors(['email_subject', 'email_body']);

        $this->actingAs($this->user)->postJson(self::MODELOS, [
            'name' => 'Mudo SMS', 'module' => 'events', 'trigger_event' => 'created',
            'sms_enabled' => true, 'sms_body' => '',
        ])->assertStatus(422)->assertJsonValidationErrors('sms_body');

        // O WhatsApp só manda modelos aprovados — sem o SID não manda nada.
        $this->actingAs($this->user)->postJson(self::MODELOS, [
            'name' => 'Mudo WA', 'module' => 'events', 'trigger_event' => 'created',
            'whatsapp_enabled' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('whatsapp_template_sid');
    }

    /**
     * AS VARIÁVEIS ESCRITAS NO TEXTO LIGAM-SE SOZINHAS AOS CAMPOS.
     *
     * Quem escreve `{{ cliente }}` no corpo não tem de ir depois a uma segunda
     * lista dizer que `cliente` é `client.name`.
     */
    public function test_as_variaveis_do_texto_mapeiam_se_sozinhas(): void
    {
        $this->comPermissoes('notifications.view', 'notifications.manage');

        $this->actingAs($this->user)->postJson(self::MODELOS, [
            'name' => 'Com variáveis', 'module' => 'events', 'trigger_event' => 'created',
            'email_enabled' => true,
            'email_subject' => 'Olá {{ cliente }}',
            'email_body' => 'O evento {{event}} é a {{  date  }}.',
        ])->assertCreated();

        $m = NotificationTemplate::where('tenant_id', $this->tenant->id)
            ->where('name', 'Com variáveis')->firstOrFail();

        $this->assertArrayHasKey('cliente', $m->variable_mappings);
        $this->assertArrayHasKey('event', $m->variable_mappings);
        $this->assertArrayHasKey('date', $m->variable_mappings,
            'o espaço a mais dentro das chavetas não pode esconder a variável');
    }

    /** A lista marca um modelo sem canal nenhum — nunca manda nada. */
    public function test_a_lista_conta_os_modelos_sem_canal(): void
    {
        $this->comPermissoes('notifications.view');

        $mudo = $this->modelo(['name' => 'Sem canal nenhum', 'email_enabled' => false]);

        $resposta = $this->actingAs($this->user)->getJson(self::MODELOS)->assertOk();

        $this->assertGreaterThanOrEqual(1, $resposta->json('resumo.sem_canal'));

        $linha = collect($resposta->json('data'))->firstWhere('id', $mudo->id);

        $this->assertSame([], $linha['canais'], 'um modelo sem canal nunca manda nada — e a lista tem de o dizer');
    }

    /* ─── A pré-visualização e o teste ────────────────────────────────── */

    /**
     * O ESPAÇO A MAIS DENTRO DAS CHAVETAS CONTAVA.
     *
     * A substituição era literal: `{{var}}` e `{{ var }}` trocavam-se, mas um
     * `{{  var  }}` — que é o que sai de colar de um documento — chegava com a
     * chaveta à vista ao e-mail do cliente.
     */
    public function test_a_previsao_apanha_as_chavetas_com_espacos(): void
    {
        $this->comPermissoes('notifications.view');

        $m = $this->modelo([
            'email_subject' => 'Olá {{  cliente  }}',
            'email_body' => 'O {{evento}} é a {{ data }}.',
        ]);

        $r = $this->actingAs($this->user)->postJson(self::MODELOS.'/'.$m->id.'/previsao', [
            'variaveis' => ['cliente' => 'João', 'evento' => 'Festa', 'data' => 'segunda'],
        ])->assertOk();

        $this->assertSame('Olá João', $r->json('previsao.assunto'));
        $this->assertSame('O Festa é a segunda.', $r->json('previsao.corpo'));
    }

    /** Uma variável por preencher vê-se — um buraco no meio de uma frase não. */
    public function test_a_variavel_vazia_mostra_se_entre_parenteses(): void
    {
        $this->comPermissoes('notifications.view');

        $m = $this->modelo(['email_subject' => 'Olá {{ cliente }}']);

        $this->actingAs($this->user)->postJson(self::MODELOS.'/'.$m->id.'/previsao', [
            'variaveis' => ['cliente' => ''],
        ])->assertOk()->assertJsonPath('previsao.assunto', 'Olá [cliente]');
    }

    /** A preparação traz as variáveis já com dados de exemplo. */
    public function test_a_preparacao_traz_dados_de_exemplo(): void
    {
        $this->comPermissoes('notifications.view');

        TenantNotificationSetting::getForTenant($this->tenant->id)
            ->update(['from_email' => 'geral@empresa.ao']);

        $m = $this->modelo(['email_subject' => 'Olá {{ cliente }}', 'email_body' => 'O {{event}}.']);

        $r = $this->actingAs($this->user)->getJson(self::MODELOS.'/'.$m->id.'/teste')->assertOk();

        $this->assertNotEmpty($r->json('variaveis.cliente'));
        $this->assertNotEmpty($r->json('variaveis.event'));
        $this->assertSame('geral@empresa.ao', $r->json('email_sugerido'));
        $this->assertStringNotContainsString('{{', (string) $r->json('previsao.assunto'));
    }

    /**
     * O TESTE MANDA MESMO — e usa o SMTP DESTA empresa.
     */
    public function test_o_teste_de_email_sai_pelo_smtp_da_propria_empresa(): void
    {
        $this->comPermissoes('notifications.view', 'notifications.send');
        Mail::fake();

        TenantNotificationSetting::getForTenant($this->tenant->id)->update([
            'email_enabled' => true,
            'smtp_host' => 'smtp.empresa.ao',
            'smtp_port' => 587,
            'from_email' => 'geral@empresa.ao',
            'from_name' => 'A Empresa',
        ]);

        $m = $this->modelo();

        $this->actingAs($this->user)->postJson(self::MODELOS.'/'.$m->id.'/testar', [
            'canais' => ['email'],
            'email' => 'destino@exemplo.ao',
            'variaveis' => ['cliente' => 'João', 'event' => 'Festa', 'date' => 'segunda'],
        ])->assertOk()->assertJsonPath('enviados', ['email']);

        Mail::assertSent(\App\Mail\NotificacaoDeModelo::class);
    }

    /**
     * O TESTE JÁ NÃO MENTE.
     *
     * Com o canal de SMS desligado nas definições, não sai nada — e o servidor
     * diz que não saiu, em vez de somar «SMS» aos canais enviados.
     */
    public function test_um_canal_por_configurar_nao_conta_como_enviado(): void
    {
        $this->comPermissoes('notifications.view', 'notifications.send');

        TenantNotificationSetting::getForTenant($this->tenant->id)
            ->update(['sms_enabled' => false]);

        $m = $this->modelo([
            'email_enabled' => false, 'sms_enabled' => true, 'sms_body' => 'Olá {{ cliente }}',
        ]);

        $this->actingAs($this->user)->postJson(self::MODELOS.'/'.$m->id.'/testar', [
            'canais' => ['sms'], 'telefone' => '923000000', 'variaveis' => ['cliente' => 'João'],
        ])->assertStatus(422);
    }

    /** Um número que não é angolano é recusado antes de gastar uma mensagem. */
    public function test_um_numero_invalido_e_recusado(): void
    {
        $this->comPermissoes('notifications.view', 'notifications.send');

        $m = $this->modelo(['email_enabled' => false, 'sms_enabled' => true, 'sms_body' => 'Olá']);

        $this->actingAs($this->user)->postJson(self::MODELOS.'/'.$m->id.'/testar', [
            'canais' => ['sms'], 'telefone' => '12', 'variaveis' => [],
        ])->assertStatus(422);
    }

    /** E quem não pode enviar não envia. */
    public function test_quem_nao_pode_enviar_nao_testa(): void
    {
        $this->comPermissoes('notifications.view');
        Mail::fake();

        $m = $this->modelo();

        $this->actingAs($this->user)->postJson(self::MODELOS.'/'.$m->id.'/testar', [
            'canais' => ['email'], 'email' => 'destino@exemplo.ao', 'variaveis' => [],
        ])->assertForbidden();

        Mail::assertNothingSent();
    }

    /* ─── As definições ───────────────────────────────────────────────── */

    /** As definições de uma empresa não se lêem de outra. */
    public function test_as_definicoes_sao_da_empresa_activa(): void
    {
        $this->comPermissoes('notifications.view', 'notifications.manage');

        $outra = $this->outraEmpresa();

        TenantNotificationSetting::getForTenant($outra->id)
            ->update(['smtp_host' => 'smtp.da-outra-casa.ao']);

        $this->actingAs($this->user)->getJson(self::DEFINICOES)->assertOk()
            ->assertJsonMissing(['smtp_host' => 'smtp.da-outra-casa.ao']);
    }

    /** O utilizador sem permissão de gerir vê, mas não testa a ligação. */
    public function test_o_teste_de_ligacao_pede_permissao(): void
    {
        $so = User::create([
            'name' => 'Só vê', 'email' => 'sove2@empresa.ao', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $so->tenants()->syncWithoutDetaching([$this->tenant->id]);

        setPermissionsTeamId($this->tenant->id);
        \Spatie\Permission\Models\Permission::findOrCreate('notifications.view', 'web');
        $so->givePermissionTo('notifications.view');

        $this->actingAs($so)->postJson(self::DEFINICOES.'/testar-email', [
            'smtp_host' => 'smtp.exemplo.ao', 'smtp_port' => 587, 'from_email' => 'a@b.ao',
        ])->assertForbidden();
    }
}
