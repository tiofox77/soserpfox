<?php

namespace Tests\Feature\Licensing;

use App\Models\LicencaEmitida;
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

    /**
     * O DEFEITO QUE ISTO FECHA: o super admin punha 1 dia no painel e o
     * check-in seguinte devolvia os 30 de `renew_days`, apagando a decisão.
     * Quem manda no prazo é a linha em licencas_emitidas.
     */
    public function test_prazo_do_painel_manda_e_nao_e_reescrito(): void
    {
        $linha = LicencaEmitida::create([
            'tenant_id'  => $this->tenant->id,
            'modulos'    => ['*'],
            'token'      => 'SOSERP-LIC.v1.x.y',
            'emitida_em' => now(),
            'expira_em'  => now()->addDay(),   // o painel disse: 1 dia
        ]);

        $resp = $this->checkin($this->tokenDoTenant());
        $resp->assertOk();

        $payload = (new LicenseVerifier($this->publica, config('licensing')))
            ->payloadAssinado($resp->json('licenca'));

        // A licença que chega ao cliente vale 1 dia, não 30.
        $this->assertEqualsWithDelta(
            1,
            CarbonImmutable::now()->diffInDays($payload->expiraEm(), false),
            0.05
        );

        // E o check-in não pode empurrar a data do painel para a frente.
        $this->assertEqualsWithDelta(
            1,
            now()->floatDiffInDays($linha->refresh()->expira_em),
            0.05
        );
    }

    /** Licença curta → o servidor pede para voltar depressa. */
    public function test_licenca_curta_pede_checkin_frequente(): void
    {
        LicencaEmitida::create([
            'tenant_id' => $this->tenant->id,
            'modulos'   => ['*'],
            'token'     => 'SOSERP-LIC.v1.x.y',
            'expira_em' => now()->addDay(),
        ]);

        $this->checkin($this->tokenDoTenant())
            ->assertOk()
            ->assertJson(['proximo_checkin_minutos' => 10]);
    }

    /** Licença longa → não vale a pena incomodar o servidor. */
    public function test_licenca_longa_pede_checkin_espacado(): void
    {
        LicencaEmitida::create([
            'tenant_id' => $this->tenant->id,
            'modulos'   => ['*'],
            'token'     => 'SOSERP-LIC.v1.x.y',
            'expira_em' => now()->addYear(),
        ]);

        $this->checkin($this->tokenDoTenant())
            ->assertOk()
            ->assertJson(['proximo_checkin_minutos' => 720]);
    }

    /**
     * Sem linha nenhuma (licença emitida fora do painel, ou primeiro contacto
     * de sempre): a cloud adopta o prazo que o cliente traz. É uma licença
     * assinada pelo fornecedor — logo, é uma decisão do fornecedor.
     */
    public function test_primeiro_contacto_adopta_a_licenca_do_cliente(): void
    {
        $resp = $this->checkin($this->tokenDoTenant());   // token de 3 dias
        $resp->assertOk();

        $payload = (new LicenseVerifier($this->publica, config('licensing')))
            ->payloadAssinado($resp->json('licenca'));

        $this->assertEqualsWithDelta(
            3,
            CarbonImmutable::now()->diffInDays($payload->expiraEm(), false),
            0.05
        );

        // E a cloud passa a mostrar o MESMO número.
        $linha = LicencaEmitida::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertEqualsWithDelta(3, now()->floatDiffInDays($linha->expira_em), 0.05);
    }

    /**
     * OFFLINE → ONLINE. O cliente ficou sem rede, renovou-se por telefone com
     * um token que lhe demos, e só dias depois voltou a ter internet. A cloud
     * tem de ADOPTAR esse prazo — não empurrá-lo de volta para o antigo.
     */
    public function test_licenca_instalada_a_mao_actualiza_a_cloud(): void
    {
        $linha = LicencaEmitida::create([
            'tenant_id'  => $this->tenant->id,
            'modulos'    => ['*'],
            'token'      => 'SOSERP-LIC.v1.antigo',
            'emitida_em' => now()->subDays(10),
            'expira_em'  => now()->addDay(),        // a cloud ainda pensa: 1 dia
        ]);

        // O que lhe demos por telefone ONTEM: 90 dias, emitido depois do painel.
        $tokenNovo = (new LicenseIssuer())->emitir([
            'tenant_id' => $this->tenant->id,
            'iat'       => CarbonImmutable::now()->subDay()->getTimestamp(),
            'exp'       => CarbonImmutable::now()->addDays(90)->getTimestamp(),
        ], $this->privada);

        $resp = $this->checkin($tokenNovo);
        $resp->assertOk();

        $payload = (new LicenseVerifier($this->publica, config('licensing')))
            ->payloadAssinado($resp->json('licenca'));

        $this->assertEqualsWithDelta(90, CarbonImmutable::now()->diffInDays($payload->expiraEm(), false), 0.05);
        $this->assertEqualsWithDelta(90, now()->floatDiffInDays($linha->refresh()->expira_em), 0.05);
    }

    /**
     * ONLINE → OFFLINE. Token mais VELHO que a decisão do painel não ganha —
     * senão bastava guardar uma licença antiga e longa para ignorar cortes.
     */
    public function test_token_mais_velho_nao_ganha_ao_painel(): void
    {
        $linha = LicencaEmitida::create([
            'tenant_id'  => $this->tenant->id,
            'modulos'    => ['*'],
            'token'      => 'SOSERP-LIC.v1.x',
            'emitida_em' => now(),               // o painel mexeu agora
            'expira_em'  => now()->addDay(),     // e disse: 1 dia
        ]);

        // Uma licença de 2 anos que lhe demos o ano passado.
        $tokenVelho = (new LicenseIssuer())->emitir([
            'tenant_id' => $this->tenant->id,
            'iat'       => CarbonImmutable::now()->subYear()->getTimestamp(),
            'exp'       => CarbonImmutable::now()->addYears(2)->getTimestamp(),
        ], $this->privada);

        $resp = $this->checkin($tokenVelho);

        $payload = (new LicenseVerifier($this->publica, config('licensing')))
            ->payloadAssinado($resp->json('licenca'));

        $this->assertEqualsWithDelta(1, CarbonImmutable::now()->diffInDays($payload->expiraEm(), false), 0.05);
        $this->assertEqualsWithDelta(1, now()->floatDiffInDays($linha->refresh()->expira_em), 0.05);
    }

    /** Depois de sincronizar, os dois lados mostram o MESMO número. */
    public function test_os_dois_lados_ficam_com_o_mesmo_numero(): void
    {
        LicencaEmitida::create([
            'tenant_id'  => $this->tenant->id,
            'modulos'    => ['*'],
            'token'      => 'SOSERP-LIC.v1.x',
            'emitida_em' => now(),
            'expira_em'  => now()->addDays(45),
        ]);

        $resp = $this->checkin($this->tokenDoTenant());

        $payload = (new LicenseVerifier($this->publica, config('licensing')))
            ->payloadAssinado($resp->json('licenca'));

        $linha = LicencaEmitida::where('tenant_id', $this->tenant->id)->firstOrFail();

        // Ao segundo, o que o cliente conta e o que o painel mostra são o mesmo
        // INSTANTE. Compara-se o timestamp, não a hora de parede: o payload vem
        // em UTC e a coluna no fuso da app — a mesma data escrita de duas
        // maneiras. Comparar strings dava uma hora de diferença que não existe.
        $this->assertSame(
            $linha->expira_em->getTimestamp(),
            $payload->expiraEm()->getTimestamp()
        );
    }

    /** O painel também manda nos módulos e no tecto de utilizadores. */
    public function test_modulos_e_tecto_vem_do_painel(): void
    {
        LicencaEmitida::create([
            'tenant_id' => $this->tenant->id,
            'modulos'   => ['invoicing', 'stock'],
            'max_users' => 3,
            'token'     => 'SOSERP-LIC.v1.x.y',
            'expira_em' => now()->addDays(10),
        ]);

        $resp = $this->checkin($this->tokenDoTenant());

        $payload = (new LicenseVerifier($this->publica, config('licensing')))
            ->payloadAssinado($resp->json('licenca'));

        $this->assertSame(['invoicing', 'stock'], $payload->modulos());
        $this->assertSame(3, $payload->maxUtilizadores());
    }

    /** Cópia da licença noutra máquina não colhe licenças novas. */
    public function test_maquina_diferente_nao_renova(): void
    {
        config(['licensing.bind_machine' => true]);

        $token = (new LicenseIssuer())->emitir([
            'tenant_id' => $this->tenant->id,
            'fp'        => 'maquina-do-cliente',
            'exp'       => CarbonImmutable::now()->addDays(3)->getTimestamp(),
        ], $this->privada);

        $this->withoutMiddleware([
            \App\Http\Middleware\IdentifyTenant::class,
            \App\Http\Middleware\CheckTenantActive::class,
            \App\Http\Middleware\CheckSubscription::class,
        ])->postJson('/api/license/checkin', [
            'token'       => $token,
            'fingerprint' => 'outra-maquina',
        ])->assertOk()->assertJson(['acao' => 'bloquear', 'motivo' => 'maquina_diferente']);
    }

    /** A versão que a instalação diz correr fica registada para o painel. */
    public function test_regista_versao_instalada(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\IdentifyTenant::class,
            \App\Http\Middleware\CheckTenantActive::class,
            \App\Http\Middleware\CheckSubscription::class,
        ])->postJson('/api/license/checkin', [
            'token'  => $this->tokenDoTenant(),
            'versao' => '1.2.0',
        ])->assertOk();

        $this->assertDatabaseHas('licencas_emitidas', [
            'tenant_id' => $this->tenant->id, 'versao_instalada' => '1.2.0',
        ]);
    }
}
