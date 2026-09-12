<?php

namespace Tests\Feature;

use App\Models\NotificationTemplate;
use App\Services\Notifications\ModelosPadrao;
use Tests\TenantTestCase;

/**
 * Os modelos de notificação que toda a empresa devia ter à partida.
 *
 * O ecrã /notifications/templates estava vazio em todas as empresas menos a
 * primeira: os 24 modelos existiam só lá, postos à mão, e não havia seeder
 * nenhum. Quem abrisse noutra empresa via uma lista em branco e tinha de
 * escrever tudo de raiz — assunto, corpo e variáveis para doze avisos.
 *
 * É o mesmo defeito das definições de RH, no ecrã do lado: o catálogo existia
 * agarrado a uma empresa.
 */
class ModelosPadraoNotificacaoTest extends TenantTestCase
{
    public function test_uma_empresa_sem_modelos_recebe_os_do_catalogo(): void
    {
        $this->assertSame(0, NotificationTemplate::where('tenant_id', $this->tenant->id)->count());

        $criados = ModelosPadrao::garantirPara($this->tenant->id);

        $this->assertSame(count(ModelosPadrao::catalogo()), $criados);
    }

    public function test_correr_duas_vezes_nao_duplica(): void
    {
        ModelosPadrao::garantirPara($this->tenant->id);
        $segunda = ModelosPadrao::garantirPara($this->tenant->id);

        $this->assertSame(0, $segunda);
        $this->assertSame(
            count(ModelosPadrao::catalogo()),
            NotificationTemplate::where('tenant_id', $this->tenant->id)->count()
        );
    }

    public function test_nao_reescreve_um_modelo_que_a_empresa_alterou(): void
    {
        // Isto corre no mount() do ecrã, portanto a cada visita. Se repusesse
        // os textos, uma empresa que reescreveu o corpo de um aviso via isso
        // desfeito por alguém abrir a página.
        ModelosPadrao::garantirPara($this->tenant->id);

        $modelo = NotificationTemplate::where('tenant_id', $this->tenant->id)->first();
        $modelo->update(['email_subject' => 'Assunto próprio da empresa']);

        ModelosPadrao::garantirPara($this->tenant->id);

        $this->assertSame('Assunto próprio da empresa', $modelo->fresh()->email_subject);
    }

    public function test_um_modelo_novo_do_catalogo_chega_a_uma_empresa_antiga(): void
    {
        ModelosPadrao::garantirPara($this->tenant->id);

        NotificationTemplate::where('tenant_id', $this->tenant->id)->first()->forceDelete();

        $this->assertSame(1, ModelosPadrao::garantirPara($this->tenant->id));
    }

    public function test_o_catalogo_nao_leva_credenciais_de_outra_empresa(): void
    {
        // Os identificadores de modelo na operadora — Twilio e email — são o
        // registo de CADA empresa. Copiá-los mandaria uma empresa usar a conta
        // de outra.
        foreach (ModelosPadrao::catalogo() as $m) {
            foreach (['whatsapp_template_sid', 'sms_template_sid', 'email_template_id', 'tenant_id', 'id'] as $proibido) {
                $this->assertArrayNotHasKey($proibido, $m, "{$proibido} não pode viajar no catálogo");
            }
        }
    }

    public function test_todo_o_modelo_tem_o_que_e_preciso_para_enviar(): void
    {
        foreach (ModelosPadrao::catalogo() as $m) {
            $this->assertNotEmpty($m['name'] ?? null);
            $this->assertNotEmpty($m['module'] ?? null);
            $this->assertNotEmpty($m['trigger_event'] ?? null);

            if (!empty($m['email_enabled'])) {
                $this->assertNotEmpty($m['email_subject'] ?? null, "assunto em falta: {$m['name']}");
                $this->assertNotEmpty($m['email_body'] ?? null, "corpo em falta: {$m['name']}");
            }

            if (!empty($m['sms_enabled'])) {
                $this->assertNotEmpty($m['sms_body'] ?? null, "corpo de SMS em falta: {$m['name']}");
            }
        }
    }

    public function test_os_nomes_do_catalogo_nao_se_repetem(): void
    {
        // A comparação de "já existe" é feita pelo NOME.
        $nomes = array_column(ModelosPadrao::catalogo(), 'name');

        $this->assertSame(count($nomes), count(array_unique($nomes)));
    }

    public function test_diz_se_um_modelo_dispara_e_porque_nao(): void
    {
        // Um modelo activo que nunca dispara é pior do que não existir: fica a
        // prometer um aviso que não vem.
        $this->assertTrue(ModelosPadrao::dispara('events', 'created'));
        $this->assertTrue(ModelosPadrao::dispara('events', 'date_approaching'));
        $this->assertTrue(ModelosPadrao::dispara('hr', 'created'));

        // `tasks` e `calendar` não têm tabela nesta base.
        $this->assertNotNull(ModelosPadrao::porQueNaoDispara('tasks', 'created'));
        $this->assertNotNull(ModelosPadrao::porQueNaoDispara('calendar', 'created'));

        // `status_changed` não tem regra de selecção: saber que um estado MUDOU
        // exige apanhar a mudança no momento, não voltar a olhar mais tarde.
        $razao = ModelosPadrao::porQueNaoDispara('hr', 'status_changed');
        $this->assertNotNull($razao);
        $this->assertStringContainsString('status_changed', $razao);
    }

    private const LISTA = '/api/v1/invoicing/react/notificacoes/modelos';

    public function test_o_ecra_cria_os_modelos_na_primeira_visita(): void
    {
        $this->comModulo('notifications');
        $this->comPermissoes('notifications.view');

        $resposta = $this->actingAs($this->user)->getJson(self::LISTA)->assertOk();

        $this->assertGreaterThan(0, $resposta->json('criados_agora'));
        $this->assertSame(
            count(ModelosPadrao::catalogo()),
            NotificationTemplate::where('tenant_id', $this->tenant->id)->count()
        );
    }

    public function test_a_segunda_visita_nao_anuncia_nada(): void
    {
        $this->comModulo('notifications');
        $this->comPermissoes('notifications.view');

        $this->actingAs($this->user)->getJson(self::LISTA)->assertOk();

        $this->actingAs($this->user)->getJson(self::LISTA)
            ->assertOk()->assertJsonPath('criados_agora', 0);
    }

    /**
     * O ECRÃ AVISA QUAIS É QUE AINDA NÃO DISPARAM.
     *
     * Um modelo activo que nunca vai disparar é pior do que um desligado:
     * parece que está a funcionar.
     */
    public function test_o_ecra_avisa_quais_ainda_nao_disparam(): void
    {
        $this->comModulo('notifications');
        $this->comPermissoes('notifications.view');

        $linhas = collect($this->actingAs($this->user)->getJson(self::LISTA)->assertOk()->json('data'));

        $mudos = $linhas->where('dispara', false);

        $this->assertNotEmpty($mudos, 'o catálogo tem modelos que ainda não disparam e o ecrã tem de o dizer');
        $this->assertNotNull($mudos->first()['porque_nao_dispara'], 'e tem de dizer PORQUÊ');
    }

    public function test_os_modelos_de_uma_empresa_nao_se_veem_de_outra(): void
    {
        ModelosPadrao::garantirPara($this->tenant->id);

        $outra = \App\Models\Tenant::create([
            'name'      => 'Outra Empresa',
            'slug'      => 'outra-' . uniqid(),
            'nif'       => (string) random_int(800000000, 899999999),
            'email'     => 'outra' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        ModelosPadrao::garantirPara($outra->id);

        $this->assertSame(
            count(ModelosPadrao::catalogo()),
            NotificationTemplate::withoutGlobalScopes()->where('tenant_id', $outra->id)->count()
        );

        // Os slugs têm de ser distintos: a coluna é única por empresa e um
        // slug repetido fazia a segunda empresa falhar a criação.
        $this->assertSame(
            0,
            NotificationTemplate::withoutGlobalScopes()
                ->whereIn('slug', NotificationTemplate::withoutGlobalScopes()
                    ->where('tenant_id', $this->tenant->id)->pluck('slug'))
                ->where('tenant_id', $outra->id)
                ->count()
        );
    }
}
