<?php

namespace Tests\Feature\Licensing;

use App\Http\Middleware\VerificarLicenca;
use App\Services\Licensing\LicenseManager;
use App\Services\Licensing\LicenseState;
use Illuminate\Http\Request;
use Tests\TenantTestCase;

/**
 * O enforcement é a parte perigosa: se disparar na cloud, tranca a plataforma
 * inteira. Estes testes fixam o contrato — DESLIGADO por omissão, e só a agir
 * quando `licensing.enforce` está ligado.
 *
 * Estende TenantTestCase para EXISTIR uma empresa — senão o gate de setup
 * (licença válida + sem empresa → /setup) desviava o caso ATIVA.
 */
class EnforcementTest extends TenantTestCase
{
    private function correr(
        ?LicenseState $estado = null,
        array $headers = [],
        string $uri = '/faturas',
        bool $enforce = true,
    ) {
        config([
            'licensing.enforce'      => $enforce,
            'licensing.rotas_livres' => ['licenca', 'licenca/*', 'login', 'logout'],
        ]);

        $manager = \Mockery::mock(LicenseManager::class);
        if ($estado) {
            $manager->shouldReceive('estado')->andReturn($estado);
        }

        $mw = new VerificarLicenca($manager);
        $req = Request::create($uri, 'GET');
        foreach ($headers as $k => $v) {
            $req->headers->set($k, $v);
        }

        return $mw->handle($req, fn ($r) => response('OK'));
    }

    public function test_enforce_desligado_e_no_op_total(): void
    {
        // Nem sequer precisa de licença: passa sempre. (o contrato da cloud)
        $resp = $this->correr(enforce: false);
        $this->assertSame('OK', $resp->getContent());
    }

    public function test_ativa_deixa_passar(): void
    {
        $resp = $this->correr(new LicenseState(LicenseState::ATIVA, true, 'ok'));
        $this->assertSame('OK', $resp->getContent());
    }

    public function test_bloqueada_redireciona_para_activacao(): void
    {
        $resp = $this->correr(new LicenseState(LicenseState::BLOQUEADA, false, 'expirada'));
        $this->assertSame(302, $resp->getStatusCode());
        $this->assertStringContainsString('/licenca', $resp->headers->get('Location'));
    }

    public function test_bloqueada_num_pedido_json_da_423(): void
    {
        $resp = $this->correr(
            new LicenseState(LicenseState::BLOQUEADA, false, 'expirada'),
            ['Accept' => 'application/json']
        );
        $this->assertSame(423, $resp->getStatusCode());
    }

    public function test_rota_livre_passa_mesmo_bloqueada(): void
    {
        // /licenca tem de responder para o cliente poder reactivar.
        $resp = $this->correr(
            new LicenseState(LicenseState::BLOQUEADA, false, 'expirada'),
            [],
            '/licenca'
        );
        $this->assertSame('OK', $resp->getContent());
    }
}
