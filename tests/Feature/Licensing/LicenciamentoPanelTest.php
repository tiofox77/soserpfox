<?php

namespace Tests\Feature\Licensing;

use App\Livewire\SuperAdmin\Licenciamento;
use App\Models\AppUpdate;
use App\Models\AppUpdateTarget;
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
}
