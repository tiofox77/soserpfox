<?php

namespace Tests\Feature\Licensing;

use App\Models\AppUpdate;
use App\Models\LicencaEmitida;
use App\Models\LicenseRequest;
use App\Models\User;
use App\Services\Licensing\LicenseIssuer;
use Tests\TenantTestCase;

/**
 * O painel do super admin (F5). Gated a super admin; publica versões, emite
 * licenças e faz o rollout por-tenant. O ecrã passou a React e fala com
 * `/api/v1/plataforma/react/licenciamento`.
 */
class LicenciamentoPanelTest extends TenantTestCase
{
    private const API = '/api/v1/plataforma/react/licenciamento';

    private User $super;

    private string $privada;

    protected function setUp(): void
    {
        parent::setUp();

        $par = LicenseIssuer::gerarParDeChaves();
        $this->privada = $par['privada'];
        config([
            'licensing.signing_key'         => $par['privada'],
            'licensing.public_key'          => $par['publica'],
            'licensing.update.signing_key'  => $par['privada'],
            'licensing.update.public_key'   => $par['publica'],
        ]);

        $this->super = User::create([
            'name' => 'Root', 'email' => 'root_' . uniqid() . '@x.com', 'password' => bcrypt('x'),
        ]);
        $this->super->forceFill(['is_super_admin' => true])->save();
    }

    public function test_nao_superadmin_recebe_403(): void
    {
        // TenantTestCase já faz actingAs($this->user), que é utilizador normal.
        $this->get('/superadmin/licenciamento')->assertForbidden();
        $this->getJson(self::API)->assertForbidden();
    }

    public function test_a_pagina_monta_o_ecra(): void
    {
        $this->actingAs($this->super)->get('/superadmin/licenciamento')
            ->assertOk()
            ->assertSee('data-ecra="plataforma/licenciamento"', false);
    }

    /** A chave privada nunca vai para o browser — nem a de licenças nem a de versões. */
    public function test_a_chave_privada_nao_sai_na_resposta(): void
    {
        $r = $this->actingAs($this->super)->getJson(self::API)->assertOk();

        $this->assertTrue($r->json('estado.chave_das_licencas'));
        $this->assertNull($r->json('estado.problema_da_chave'));
        $this->assertStringNotContainsString($this->privada, $r->getContent());
    }

    public function test_publica_versao_assinada(): void
    {
        $this->actingAs($this->super)->postJson(self::API . '/versoes', [
            'versao' => '1.1.0',
            'pacote_url' => 'https://cdn.exemplo/soserp-1.1.0.zip',
            'pacote_sha256' => str_repeat('a', 64),
            'rollout' => 'all',
        ])->assertOk();

        $this->assertDatabaseHas('app_updates', ['versao' => '1.1.0', 'rollout' => 'all']);
        $this->assertNotEmpty(AppUpdate::where('versao', '1.1.0')->value('manifesto'));
    }

    public function test_emite_licenca_para_tenant(): void
    {
        $this->actingAs($this->super)->postJson(self::API . '/licencas', [
            'tenant_id' => $this->tenant->id,
            'dias' => 30,
        ])->assertOk()->assertJsonPath('token', fn ($token) => str_starts_with($token, 'SOSERP-LIC'));
    }

    /** Com a chave PÚBLICA no lugar da privada, o painel explica — em vez de dar 500. */
    public function test_emitir_com_a_chave_errada_explica_o_que_esta_mal(): void
    {
        config(['licensing.signing_key' => LicenseIssuer::gerarParDeChaves()['publica']]);

        $this->actingAs($this->super)->postJson(self::API . '/licencas', ['tenant_id' => $this->tenant->id, 'dias' => 30])
            ->assertStatus(422)
            ->assertJsonPath('errors.tenant_id.0', fn ($m) => str_contains($m, 'PÚBLICA'));
    }

    public function test_rollout_por_tenant(): void
    {
        $this->actingAs($this->super)->postJson(self::API . '/versoes', [
            'versao' => '1.2.0', 'pacote_url' => 'https://cdn.exemplo/x.zip', 'pacote_sha256' => str_repeat('b', 64), 'rollout' => 'none',
        ])->assertOk();

        $this->actingAs($this->super)->postJson(self::API . '/alvos', ['versao' => '1.2.0', 'tenant_id' => $this->tenant->id])->assertOk();

        $this->assertDatabaseHas('app_update_targets', [
            'tenant_id' => $this->tenant->id, 'versao' => '1.2.0',
        ]);

        $id = AppUpdate::where('versao', '1.2.0')->value('id');
        $this->actingAs($this->super)->putJson(self::API . "/versoes/{$id}/rollout", ['rollout' => 'all'])->assertOk();
        $this->assertDatabaseHas('app_updates', ['versao' => '1.2.0', 'rollout' => 'all']);
    }

    /** Cria uma instalacao conhecida a expirar daqui a N dias. */
    private function instalacao(int $diasAteExpirar): LicencaEmitida
    {
        return LicencaEmitida::create([
            'tenant_id'  => $this->tenant->id,
            'plano'      => 'Enterprise',
            'modulos'    => ['*'],
            'token'      => 'SOSERP-LIC.v1.x.y',
            'emitida_em' => now()->subDay(),
            'expira_em'  => now()->addDays($diasAteExpirar),
        ]);
    }

    /**
     * O que o cliente reportou: a caixa abria sempre em 365, escondendo que a
     * licenca so tinha 1 dia. Tem de mostrar o que falta de verdade.
     */
    public function test_abrir_detalhes_puxa_os_dias_que_faltam(): void
    {
        $i = $this->instalacao(1);

        $this->actingAs($this->super)->getJson(self::API . "/instalacoes/{$i->id}")
            ->assertOk()
            ->assertJsonPath('instalacao.dias_sugeridos', 1)
            ->assertJsonPath('instalacao.empresa.nome', $this->tenant->name);
    }

    /** Licenca ja expirada: propoe o periodo que lhe foi vendido, nao 365. */
    public function test_licenca_expirada_propoe_o_periodo_anterior(): void
    {
        $i = LicencaEmitida::create([
            'tenant_id'  => $this->tenant->id,
            'modulos'    => ['*'],
            'token'      => 'SOSERP-LIC.v1.x.y',
            'emitida_em' => now()->subDays(40),
            'expira_em'  => now()->subDays(10),
        ]);

        $this->actingAs($this->super)->getJson(self::API . "/instalacoes/{$i->id}")
            ->assertJsonPath('instalacao.dias_sugeridos', 30);
    }

    /** Renovar usa o numero de dias escrito na caixa. */
    public function test_renovar_usa_os_dias_da_caixa(): void
    {
        $i = $this->instalacao(1);

        $this->actingAs($this->super)->postJson(self::API . "/instalacoes/{$i->id}/renovar", ['dias' => 90])->assertOk();

        $i->refresh();
        $this->assertEqualsWithDelta(90, now()->floatDiffInDays($i->expira_em), 0.05);
        $this->assertStringStartsWith('SOSERP-LIC.', $i->token);
    }

    public function test_renovar_recusa_dias_invalidos(): void
    {
        $i = $this->instalacao(5);
        $antes = $i->expira_em->toDateTimeString();

        $this->actingAs($this->super)->postJson(self::API . "/instalacoes/{$i->id}/renovar", ['dias' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('dias');

        $this->assertSame($antes, $i->refresh()->expira_em->toDateTimeString());
    }

    public function test_guardar_empresa_grava_a_ficha(): void
    {
        $i = $this->instalacao(30);

        $this->actingAs($this->super)->putJson(self::API . "/instalacoes/{$i->id}/empresa", [
            'nome' => 'Farmacia Nova Lda',
            'email' => 'geral@nova.ao',
        ])->assertOk();

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id, 'name' => 'Farmacia Nova Lda', 'email' => 'geral@nova.ao',
        ]);
    }

    public function test_suspender_e_reactivar(): void
    {
        $i = $this->instalacao(30);

        $this->actingAs($this->super)->postJson(self::API . "/instalacoes/{$i->id}/suspensao")->assertOk();
        $this->assertFalse((bool) $this->tenant->fresh()->is_active);

        $this->actingAs($this->super)->postJson(self::API . "/instalacoes/{$i->id}/suspensao")->assertOk();
        $this->assertTrue((bool) $this->tenant->fresh()->is_active);
    }

    /**
     * Um pedido só se decide uma vez. Aprovar um já recusado criava uma empresa
     * e uma licença para quem tinha sido recusado.
     */
    public function test_um_pedido_recusado_nao_se_aprova(): void
    {
        $pedido = LicenseRequest::create([
            'codigo' => LicenseRequest::gerarCodigo(), 'empresa' => 'Recusada', 'fingerprint' => 'fp-x',
            'estado' => LicenseRequest::RECUSADO, 'motivo_recusa' => 'Sem contrato',
        ]);
        $plano = \App\Models\Plan::query()->value('id') ?? \App\Models\Plan::create(['name' => 'P', 'slug' => 'p-' . uniqid(), 'price_monthly' => 0, 'is_active' => true])->id;

        $this->actingAs($this->super)->postJson(self::API . "/pedidos/{$pedido->id}/aprovar", ['plano_id' => $plano, 'dias' => 30])
            ->assertStatus(422)->assertJsonValidationErrors('pedido');

        $this->assertSame(LicenseRequest::RECUSADO, $pedido->fresh()->estado);
        $this->assertNull($pedido->fresh()->licenca);

        $this->actingAs($this->super)->postJson(self::API . "/pedidos/{$pedido->id}/recusar", ['motivo' => 'Outra vez recusado'])
            ->assertStatus(422)->assertJsonValidationErrors('pedido');
    }
}
