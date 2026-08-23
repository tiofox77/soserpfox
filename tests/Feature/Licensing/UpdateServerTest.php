<?php

namespace Tests\Feature\Licensing;

use App\Models\AppUpdate;
use App\Models\AppUpdateTarget;
use App\Services\Licensing\LicenseIssuer;
use App\Services\Licensing\UpdateSigner;
use Carbon\CarbonImmutable;
use Tests\TenantTestCase;

/**
 * O servidor de atualizações: decide a versão-alvo pelo rollout POR-TENANT.
 */
class UpdateServerTest extends TenantTestCase
{
    private string $publica;
    private string $privada;

    protected function setUp(): void
    {
        parent::setUp();
        $par = LicenseIssuer::gerarParDeChaves();
        $this->publica = $par['publica'];
        $this->privada = $par['privada'];
        config([
            'licensing.public_key'  => $this->publica,   // verifica o TOKEN
            'licensing.bind_machine' => false,
        ]);
    }

    private function publicar(string $versao, string $rollout = 'none'): AppUpdate
    {
        $manifesto = (new UpdateSigner())->assinar([
            'versao'        => $versao,
            'pacote_url'    => "https://cdn.exemplo/soserp-{$versao}.zip",
            'pacote_sha256' => str_repeat('a', 64),
        ], $this->privada);

        return AppUpdate::create([
            'versao'        => $versao,
            'pacote_url'    => "https://cdn.exemplo/soserp-{$versao}.zip",
            'pacote_sha256' => str_repeat('a', 64),
            'rollout'       => $rollout,
            'manifesto'     => $manifesto,
        ]);
    }

    private function token(): string
    {
        return (new LicenseIssuer())->emitir(
            ['tenant_id' => $this->tenant->id, 'exp' => CarbonImmutable::now()->addDays(30)->getTimestamp()],
            $this->privada
        );
    }

    private function check(string $versaoAtual = '1.0.0')
    {
        return $this->withoutMiddleware([
            \App\Http\Middleware\IdentifyTenant::class,
            \App\Http\Middleware\CheckTenantActive::class,
            \App\Http\Middleware\CheckSubscription::class,
        ])->postJson('/api/license/update', ['token' => $this->token(), 'versao_atual' => $versaoAtual]);
    }

    public function test_rollout_all_oferece_a_versao(): void
    {
        $this->publicar('1.1.0', 'all');

        $this->check('1.0.0')
            ->assertOk()
            ->assertJson(['atualizado' => false, 'versao' => '1.1.0']);
    }

    public function test_sem_alvo_e_rollout_none_nao_oferece(): void
    {
        $this->publicar('1.1.0', 'none');

        $this->check('1.0.0')->assertOk()->assertJson(['atualizado' => true]);
    }

    public function test_alvo_por_tenant_oferece_so_a_esse(): void
    {
        $this->publicar('1.1.0', 'none');
        AppUpdateTarget::create(['tenant_id' => $this->tenant->id, 'versao' => '1.1.0']);

        $this->check('1.0.0')->assertOk()->assertJson(['atualizado' => false, 'versao' => '1.1.0']);
    }

    public function test_ja_na_versao_nao_oferece(): void
    {
        $this->publicar('1.1.0', 'all');

        $this->check('1.1.0')->assertOk()->assertJson(['atualizado' => true]);
    }

    public function test_tenant_suspenso_nao_recebe_updates(): void
    {
        $this->publicar('1.1.0', 'all');
        $this->tenant->update(['is_active' => false]);

        $this->check('1.0.0')->assertOk()->assertJson(['atualizado' => true]);
    }
}
