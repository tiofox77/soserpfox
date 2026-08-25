<?php

namespace Tests\Feature\Licensing;

use App\Livewire\SuperAdmin\Licenciamento;
use App\Models\AppUpdate;
use App\Models\AppUpdateTarget;
use App\Models\LicencaEmitida;
use App\Models\User;
use App\Services\Licensing\LicenseIssuer;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O painel do super admin (F5). Gated a super admin; publica versões, emite
 * licenças e faz o rollout por-tenant.
 */
class LicenciamentoPanelTest extends TenantTestCase
{
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();

        $par = LicenseIssuer::gerarParDeChaves();
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
    }

    public function test_publica_versao_assinada(): void
    {
        Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->set('verVersao', '1.1.0')
            ->set('verUrl', 'https://cdn.exemplo/soserp-1.1.0.zip')
            ->set('verSha', str_repeat('a', 64))
            ->set('verRollout', 'all')
            ->call('publicarVersao')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('app_updates', ['versao' => '1.1.0', 'rollout' => 'all']);
        $this->assertNotEmpty(AppUpdate::where('versao', '1.1.0')->value('manifesto'));
    }

    public function test_emite_licenca_para_tenant(): void
    {
        Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->set('licTenantId', $this->tenant->id)
            ->set('licDias', 30)
            ->call('emitirLicenca')
            ->assertHasNoErrors()
            ->assertSee('SOSERP-LIC'); // o token gerado aparece no ecrã
    }

    public function test_rollout_por_tenant(): void
    {
        Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->set('verVersao', '1.2.0')->set('verUrl', 'https://cdn.exemplo/x.zip')
            ->set('verSha', str_repeat('b', 64))->call('publicarVersao')
            ->set('alvoVersao', '1.2.0')->set('alvoTenantId', $this->tenant->id)
            ->call('adicionarAlvo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('app_update_targets', [
            'tenant_id' => $this->tenant->id, 'versao' => '1.2.0',
        ]);
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

        Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->call('verInstalacao', $i->id)
            ->assertSet('edDias', 1)
            ->assertSet('edNome', $this->tenant->name);
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

        Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->call('verInstalacao', $i->id)
            ->assertSet('edDias', 30);
    }

    /**
     * O botao "Renovar" tem de usar o numero escrito na caixa. Antes passava
     * PHP cru para dentro do wire:click e nunca chegava ca.
     */
    public function test_renovar_usa_os_dias_da_caixa(): void
    {
        $i = $this->instalacao(1);

        Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->call('verInstalacao', $i->id)
            ->set('edDias', 90)
            ->call('renovarComDias', $i->id)
            ->assertHasNoErrors();

        $i->refresh();
        $this->assertEqualsWithDelta(90, now()->floatDiffInDays($i->expira_em), 0.05);
        $this->assertStringStartsWith('SOSERP-LIC.', $i->token);
    }

    public function test_renovar_recusa_dias_invalidos(): void
    {
        $i = $this->instalacao(5);
        $antes = $i->expira_em->toDateTimeString();

        Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->call('verInstalacao', $i->id)
            ->set('edDias', 0)
            ->call('renovarComDias', $i->id);

        $this->assertSame($antes, $i->refresh()->expira_em->toDateTimeString());
    }

    public function test_guardar_empresa_grava_a_ficha(): void
    {
        $i = $this->instalacao(30);

        Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->call('verInstalacao', $i->id)
            ->set('edNome', 'Farmacia Nova Lda')
            ->set('edEmail', 'geral@nova.ao')
            ->call('guardarEmpresa')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id, 'name' => 'Farmacia Nova Lda', 'email' => 'geral@nova.ao',
        ]);
    }

    public function test_suspender_e_reactivar(): void
    {
        $i = $this->instalacao(30);
        $comp = Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->call('verInstalacao', $i->id);

        $comp->call('alternarSuspensao');
        $this->assertFalse((bool) $this->tenant->fresh()->is_active);

        $comp->call('alternarSuspensao');
        $this->assertTrue((bool) $this->tenant->fresh()->is_active);
    }
}
