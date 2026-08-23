<?php

namespace Tests\Feature\Licensing;

use App\Services\Licensing\LicenseIssuer;
use App\Services\Licensing\LicenseState;
use App\Services\Licensing\LicenseVerifier;
use Carbon\CarbonImmutable;
use Tests\TenantTestCase;

/**
 * O servidor de licenças (cloud): responde ao check-in de uma instalação.
 * Renova quando o tenant está activo; manda bloquear quando está suspenso.
 */
class LicenseServerTest extends TenantTestCase
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
            'licensing.public_key'   => $this->publica,
            'licensing.signing_key'  => $this->privada,
            'licensing.bind_machine' => false,
            'licensing.renew_days'   => 30,
        ]);
    }

    private function tokenDoTenant(): string
    {
        return (new LicenseIssuer())->emitir(
            ['tenant_id' => $this->tenant->id, 'exp' => CarbonImmutable::now()->addDays(3)->getTimestamp()],
            $this->privada
        );
    }

    /**
     * Modela o pedido REAL: a instalação offline liga máquina-a-máquina, SEM
     * sessão nem autenticação. O TenantTestCase faz actingAs($user), o que faz
     * o IdentifyTenant resolver o tenant pelo utilizador e o CheckTenantActive
     * redirigir quando ele está suspenso — coisa que num check-in de guest
     * (produção) não acontece. Excluem-se aqui para modelar esse cenário: o
     * endpoint identifica o tenant pelo TOKEN, não por auth/sessão.
     */
    private function checkin(string $token)
    {
        return $this->withoutMiddleware([
            \App\Http\Middleware\IdentifyTenant::class,
            \App\Http\Middleware\CheckTenantActive::class,
            \App\Http\Middleware\CheckSubscription::class,
        ])->postJson('/api/license/checkin', ['token' => $token]);
    }

    public function test_tenant_activo_recebe_licenca_renovada(): void
    {
        $resp = $this->checkin($this->tokenDoTenant());

        $resp->assertOk();
        $novo = $resp->json('licenca');
        $this->assertNotEmpty($novo);

        // A licença renovada é válida e é do mesmo tenant.
        $estado = (new LicenseVerifier($this->publica, config('licensing')))
            ->verificar($novo, ['fingerprint' => 'x', 'ultimo_checkin' => CarbonImmutable::now()]);
        $this->assertTrue($estado->valida);
        $this->assertSame($this->tenant->id, $estado->payload->tenantId());
    }

    public function test_tenant_suspenso_manda_bloquear(): void
    {
        $this->tenant->update(['is_active' => false]);

        $resp = $this->checkin($this->tokenDoTenant());

        $resp->assertOk()->assertJson(['acao' => 'bloquear']);
        $this->assertNull($resp->json('licenca'));
    }

    public function test_assinatura_invalida_nao_renova(): void
    {
        $intruso = LicenseIssuer::gerarParDeChaves();
        $falso = (new LicenseIssuer())->emitir(
            ['tenant_id' => $this->tenant->id, 'exp' => CarbonImmutable::now()->addDays(3)->getTimestamp()],
            $intruso['privada']
        );

        $resp = $this->checkin($falso);

        $resp->assertOk()->assertJson(['acao' => 'bloquear', 'motivo' => 'assinatura_invalida']);
    }
}
