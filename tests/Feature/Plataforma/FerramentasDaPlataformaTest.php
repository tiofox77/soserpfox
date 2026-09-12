<?php

namespace Tests\Feature\Plataforma;

use App\Models\ContactMessage;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\PlatformMessage;
use App\Models\Restaurant\VenueLimitRequest;
use App\Models\User;
use App\Support\PartesDeSms;
use Tests\TenantTestCase;

/**
 * AS FERRAMENTAS E OS REGISTOS DA PLATAFORMA — contactos, pedidos de
 * estabelecimentos, registo de emails, aparelhos PWA, modelos de email,
 * mensagens às empresas e SMS às empresas. Os ecrãs passaram a React; o que se
 * prova aqui é a API.
 */
class FerramentasDaPlataformaTest extends TenantTestCase
{
    private const API = '/api/v1/plataforma/react';

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dono = User::create(['name' => 'Dono', 'email' => 'dono_'.uniqid().'@exemplo.ao', 'password' => bcrypt('x')]);
        $this->dono->forceFill(['is_super_admin' => true])->save();
    }

    public static function moradas(): array
    {
        return [
            'contactos' => ['/contactos', '/superadmin/contact-messages'],
            'estabelecimentos' => ['/pedidos-de-estabelecimentos', '/superadmin/restaurant-venue-requests'],
            'registo de emails' => ['/registo-de-emails', '/superadmin/email-logs'],
            'aparelhos PWA' => ['/aparelhos-pwa', '/superadmin/aparelhos-pwa'],
            'modelos de email' => ['/modelos-de-email', '/superadmin/email-templates'],
            'mensagens às empresas' => ['/avisos', '/superadmin/mensagens'],
            'SMS às empresas' => ['/sms-empresas', '/superadmin/sms-empresas'],
        ];
    }

    /** @dataProvider moradas */
    public function test_um_utilizador_de_empresa_nao_entra(string $api, string $pagina): void
    {
        $this->getJson(self::API.$api)->assertForbidden();
        $this->assertNotEquals(200, $this->get($pagina)->getStatusCode());
    }

    /** @dataProvider moradas */
    public function test_o_dono_entra_na_api_e_na_pagina(string $api, string $pagina): void
    {
        $this->actingAs($this->dono)->getJson(self::API.$api)->assertOk();
        $this->actingAs($this->dono)->get($pagina)->assertOk()->assertSee('data-ecra="plataforma/', false);
    }

    /* ─── Contactos ───────────────────────────────────────────────────── */

    public function test_os_contactos_contam_tudo_mesmo_filtrados(): void
    {
        ContactMessage::query()->delete();
        ContactMessage::create(['name' => 'Ana Nova', 'email' => 'ana@x.ao', 'message' => 'Olá', 'status' => 'new']);
        ContactMessage::create(['name' => 'Rui Lido', 'email' => 'rui@x.ao', 'message' => 'Olá', 'status' => 'read']);

        $r = $this->actingAs($this->dono)->getJson(self::API.'/contactos?estado=read')->assertOk();

        $this->assertSame(['Rui Lido'], array_column($r->json('mensagens'), 'nome'));
        $this->assertSame(1, $r->json('numeros.novas'), 'o contador não encolhe com o filtro');
        $this->assertSame(2, $r->json('numeros.total'));
    }

    public function test_marcar_e_apagar_um_contacto(): void
    {
        $m = ContactMessage::create(['name' => 'Ana', 'email' => 'ana@x.ao', 'message' => 'Olá', 'status' => 'new']);

        $this->actingAs($this->dono)->postJson(self::API."/contactos/{$m->id}/marcar", ['estado' => 'replied'])->assertOk();
        $this->assertSame('replied', $m->fresh()->status);

        // Uma respondida não volta a lida.
        $this->actingAs($this->dono)->postJson(self::API."/contactos/{$m->id}/marcar", ['estado' => 'read'])->assertOk();
        $this->assertSame('replied', $m->fresh()->status);

        $this->actingAs($this->dono)->deleteJson(self::API."/contactos/{$m->id}")->assertOk();
        $this->assertNull(ContactMessage::find($m->id));
    }

    /* ─── Pedidos de estabelecimentos ─────────────────────────────────── */

    private function pedido(): VenueLimitRequest
    {
        return VenueLimitRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'requested_by' => $this->user->id,
            'current_limit' => 1, 'requested_limit' => 3, 'status' => 'pending', 'reason' => 'Segunda loja',
        ]);
    }

    public function test_o_dono_ve_os_pedidos_de_qualquer_empresa(): void
    {
        $p = $this->pedido();

        $linha = collect($this->actingAs($this->dono)->getJson(self::API.'/pedidos-de-estabelecimentos')->assertOk()->json('pedidos'))->firstWhere('id', $p->id);

        $this->assertNotNull($linha, 'o escopo de empresa não pode esconder o pedido ao dono');
        $this->assertSame(3, $linha['pedido']);
        $this->assertSame('Segunda loja', $linha['motivo']);
    }

    public function test_aprovar_sobe_a_quota_e_nao_se_aprova_duas_vezes(): void
    {
        $this->tenant->forceFill(['restaurant_venue_limit' => 1])->save();
        $p = $this->pedido();

        $this->actingAs($this->dono)->postJson(self::API."/pedidos-de-estabelecimentos/{$p->id}/aprovar", ['limite' => 4, 'nota' => 'Contrato novo'])->assertOk();

        $this->assertSame(4, (int) $this->tenant->fresh()->restaurant_venue_limit);
        $this->assertSame('approved', $p->fresh()->status);

        $this->actingAs($this->dono)->postJson(self::API."/pedidos-de-estabelecimentos/{$p->id}/recusar", ['nota' => 'Afinal não'])
            ->assertStatus(422)->assertJsonValidationErrors('review');
    }

    public function test_recusar_pede_uma_nota(): void
    {
        $p = $this->pedido();

        $this->actingAs($this->dono)->postJson(self::API."/pedidos-de-estabelecimentos/{$p->id}/recusar", ['nota' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('nota');

        $this->assertSame('pending', $p->fresh()->status);
    }

    /* ─── Registo de emails ───────────────────────────────────────────── */

    public function test_o_detalhe_tapa_as_senhas_dos_dados_do_modelo(): void
    {
        $l = EmailLog::create([
            'to_email' => 'novo@x.ao', 'from_email' => 'sistema@x.ao', 'subject' => 'As suas credenciais',
            'template_slug' => 'new-user', 'status' => 'sent',
            'template_data' => ['user_name' => 'Novo', 'password' => 'Segredo-123', 'login_url' => 'https://x'],
        ]);

        $r = $this->actingAs($this->dono)->getJson(self::API."/registo-de-emails/{$l->id}")->assertOk();

        $this->assertSame('Novo', $r->json('registo.dados.user_name'));
        $this->assertStringNotContainsString('Segredo-123', $r->getContent());
    }

    public function test_limpar_apaga_so_os_de_mais_de_90_dias(): void
    {
        $velho = EmailLog::create(['to_email' => 'a@x.ao', 'from_email' => 's@x.ao', 'subject' => 'Velho', 'status' => 'sent']);
        $velho->forceFill(['created_at' => now()->subDays(120)])->save();
        $novo = EmailLog::create(['to_email' => 'b@x.ao', 'from_email' => 's@x.ao', 'subject' => 'Novo', 'status' => 'failed']);

        $this->actingAs($this->dono)->postJson(self::API.'/registo-de-emails/limpar-antigos')->assertOk();

        $this->assertNull(EmailLog::find($velho->id));
        $this->assertNotNull(EmailLog::find($novo->id));
    }

    /* ─── Modelos de email ────────────────────────────────────────────── */

    private function modelo(): array
    {
        return ['slug' => 'aviso-teste-'.uniqid(), 'name' => 'Aviso', 'subject' => 'Olá {user_name}', 'body_html' => '<p>Olá {user_name}</p>', 'is_active' => true];
    }

    public function test_o_identificador_do_modelo_e_unico_e_nao_muda(): void
    {
        $dados = $this->modelo();

        $this->actingAs($this->dono)->postJson(self::API.'/modelos-de-email', $dados)->assertOk();
        $this->actingAs($this->dono)->postJson(self::API.'/modelos-de-email', $dados)
            ->assertStatus(422)->assertJsonValidationErrors('slug');

        $m = EmailTemplate::where('slug', $dados['slug'])->firstOrFail();

        $this->actingAs($this->dono)->putJson(self::API."/modelos-de-email/{$m->id}", ['slug' => 'outro'] + $dados + ['name' => 'Renomeado'])->assertOk();

        $this->assertSame($dados['slug'], $m->fresh()->slug);
    }

    public function test_um_assunto_maior_que_a_coluna_e_recusado_e_nao_rebenta(): void
    {
        $this->actingAs($this->dono)->postJson(self::API.'/modelos-de-email', ['subject' => str_repeat('a', 300)] + $this->modelo())
            ->assertStatus(422)->assertJsonValidationErrors('subject');
    }

    public function test_previsualizar_troca_as_variaveis(): void
    {
        $m = EmailTemplate::create($this->modelo());

        $r = $this->actingAs($this->dono)->getJson(self::API."/modelos-de-email/{$m->id}/previsualizar")->assertOk();

        $this->assertSame('Olá João Silva', $r->json('assunto'));
        $this->assertStringContainsString('Olá João Silva', $r->json('html'));
    }

    /* ─── Mensagens às empresas ───────────────────────────────────────── */

    public function test_o_alcance_conta_antes_de_publicar(): void
    {
        $r = $this->actingAs($this->dono)->postJson(self::API.'/avisos/alcance', ['audience' => 'empresas', 'tenant_ids' => [$this->tenant->id], 'plan_ids' => []]);

        $r->assertOk()->assertJson(['empresas' => 1]);
    }

    public function test_as_leituras_de_uma_mensagem(): void
    {
        $m = PlatformMessage::create(['title' => 'Aviso', 'body' => 'Um aviso qualquer.', 'level' => 'info', 'display' => 'barra', 'audience' => 'todas']);
        \App\Models\PlatformMessageRead::create(['platform_message_id' => $m->id, 'user_id' => $this->user->id, 'tenant_id' => $this->tenant->id, 'seen_at' => now()]);

        $r = $this->actingAs($this->dono)->getJson(self::API."/avisos/{$m->id}/leituras")->assertOk();

        $this->assertSame($this->user->name, $r->json('leituras.0.nome'));
    }

    /* ─── SMS: as partes ──────────────────────────────────────────────── */

    public function test_um_acento_encolhe_a_parte_e_multiplica_o_custo(): void
    {
        $this->assertSame(0, PartesDeSms::contar('   '));
        $this->assertSame(1, PartesDeSms::contar(str_repeat('a', 160)));
        $this->assertSame(2, PartesDeSms::contar(str_repeat('a', 161)));
        $this->assertSame(3, PartesDeSms::contar(str_repeat('a', 159).'ã'));
    }
}
