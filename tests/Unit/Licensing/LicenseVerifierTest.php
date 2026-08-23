<?php

namespace Tests\Unit\Licensing;

use App\Services\Licensing\LicenseIssuer;
use App\Services\Licensing\LicenseState;
use App\Services\Licensing\LicenseVerifier;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * A verificação offline é a fechadura do produto on-premise: se falhar aberta,
 * dá-se o sistema de graça; se falhar demasiado fechada, tranca quem pagou.
 * Cada regra é testada isolada, com a assinatura real (Ed25519).
 */
class LicenseVerifierTest extends TestCase
{
    private string $publica;
    private string $privada;
    private LicenseIssuer $emissor;

    private array $cfg = [
        'bind_machine'         => true,
        'offline_grace_days'   => 15,
        'clock_skew_tolerance' => 120,
        'degradacao'           => ['aviso' => 0.5, 'banner' => 0.8, 'so_leitura' => 0.95],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $par = LicenseIssuer::gerarParDeChaves();
        $this->publica = $par['publica'];
        $this->privada = $par['privada'];
        $this->emissor = new LicenseIssuer();
    }

    private function verifier(?string $publica = null): LicenseVerifier
    {
        return new LicenseVerifier($publica ?? $this->publica, $this->cfg);
    }

    private function token(array $overrides = []): string
    {
        $claims = array_merge([
            'tenant_id' => 7,
            'empresa'   => 'Empresa Teste',
            'plano'     => 'enterprise',
            'modulos'   => ['*'],
            'exp'       => CarbonImmutable::now()->addDays(365)->getTimestamp(),
        ], $overrides);

        return $this->emissor->emitir($claims, $this->privada);
    }

    public function test_licenca_valida_fica_ativa(): void
    {
        $estado = $this->verifier()->verificar($this->token(), [
            'agora'          => CarbonImmutable::now(),
            'fingerprint'    => 'qualquer',
            'ultimo_checkin' => CarbonImmutable::now(),
        ]);

        $this->assertTrue($estado->valida);
        $this->assertSame(LicenseState::ATIVA, $estado->estado);
        $this->assertFalse($estado->bloqueiaTudo());
    }

    public function test_payload_adulterado_cai_invalida(): void
    {
        $token = $this->token();
        // vira um caractere na secção do payload (3.ª parte)
        $partes = explode('.', $token);
        $partes[2][5] = $partes[2][5] === 'A' ? 'B' : 'A';
        $adulterado = implode('.', $partes);

        $estado = $this->verifier()->verificar($adulterado, ['agora' => CarbonImmutable::now()]);

        $this->assertFalse($estado->valida);
        $this->assertSame(LicenseState::INVALIDA, $estado->estado);
    }

    public function test_token_de_outro_emissor_cai_invalida(): void
    {
        $outra = LicenseIssuer::gerarParDeChaves()['publica'];

        $estado = $this->verifier($outra)->verificar($this->token(), ['agora' => CarbonImmutable::now()]);

        $this->assertFalse($estado->valida);
        $this->assertSame(LicenseState::INVALIDA, $estado->estado);
    }

    public function test_expirada_bloqueia(): void
    {
        $token = $this->token(['exp' => CarbonImmutable::now()->subDay()->getTimestamp()]);

        $estado = $this->verifier()->verificar($token, ['agora' => CarbonImmutable::now()]);

        $this->assertFalse($estado->valida);
        $this->assertSame(LicenseState::BLOQUEADA, $estado->estado);
        $this->assertStringContainsString('xpirada', $estado->motivo);
    }

    public function test_maquina_errada_cai_invalida(): void
    {
        $token = $this->token(['fp' => 'fingerprint-da-maquina-A']);

        $estado = $this->verifier()->verificar($token, [
            'agora'       => CarbonImmutable::now(),
            'fingerprint' => 'fingerprint-da-maquina-B',
        ]);

        $this->assertFalse($estado->valida);
        $this->assertSame(LicenseState::INVALIDA, $estado->estado);
    }

    public function test_maquina_certa_fica_ativa(): void
    {
        $token = $this->token(['fp' => 'fingerprint-X']);

        $estado = $this->verifier()->verificar($token, [
            'agora'          => CarbonImmutable::now(),
            'fingerprint'    => 'fingerprint-X',
            'ultimo_checkin' => CarbonImmutable::now(),
        ]);

        $this->assertTrue($estado->valida);
        $this->assertSame(LicenseState::ATIVA, $estado->estado);
    }

    public function test_escada_offline_progride(): void
    {
        $agora = CarbonImmutable::now();
        $graca = 20;

        $estadoAos = function (int $dias) use ($agora, $graca) {
            return $this->verifier()->verificar(
                $this->token(['graca' => $graca]),
                [
                    'agora'          => $agora,
                    'fingerprint'    => 'x',
                    'ultimo_checkin' => $agora->subDays($dias),
                ]
            )->estado;
        };

        $this->assertSame(LicenseState::ATIVA, $estadoAos(3));        // 15%
        $this->assertSame(LicenseState::AVISO, $estadoAos(10));       // 50%
        $this->assertSame(LicenseState::BANNER, $estadoAos(16));      // 80%
        $this->assertSame(LicenseState::SO_LEITURA, $estadoAos(19));  // 95%
        $this->assertSame(LicenseState::BLOQUEADA, $estadoAos(20));   // 100%
    }

    public function test_offline_alem_da_graca_bloqueia(): void
    {
        $agora = CarbonImmutable::now();

        $estado = $this->verifier()->verificar(
            $this->token(['graca' => 15]),
            [
                'agora'          => $agora,
                'fingerprint'    => 'x',
                'ultimo_checkin' => $agora->subDays(40),
            ]
        );

        $this->assertFalse($estado->valida);
        $this->assertSame(LicenseState::BLOQUEADA, $estado->estado);
        $this->assertSame(40, $estado->diasOffline);
    }

    public function test_relogio_recuado_bloqueia(): void
    {
        $agora = CarbonImmutable::now();

        $estado = $this->verifier()->verificar($this->token(), [
            'agora'          => $agora,
            'fingerprint'    => 'x',
            'ultimo_checkin' => $agora,
            // já vimos um relógio 30 dias à frente: agora está recuado
            'relogio_max'    => $agora->addDays(30),
        ]);

        $this->assertFalse($estado->valida);
        $this->assertSame(LicenseState::BLOQUEADA, $estado->estado);
        $this->assertStringContainsString('elógio', $estado->motivo);
    }

    public function test_formato_invalido_cai_invalida(): void
    {
        foreach (['', 'lixo', 'a.b.c', 'OUTRO-PREFIXO.v1.aaa.bbb'] as $mau) {
            $estado = $this->verifier()->verificar($mau, ['agora' => CarbonImmutable::now()]);
            $this->assertSame(LicenseState::INVALIDA, $estado->estado, "deveria recusar: [{$mau}]");
        }
    }
}
