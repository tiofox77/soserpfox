<?php

namespace Tests\Feature\Licensing;

use App\Services\Licensing\LicenseCheckin;
use App\Services\Licensing\LicenseIssuer;
use App\Services\Licensing\LicenseManager;
use App\Services\Licensing\LicenseStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O check-in do cliente. A propriedade que estes testes fixam: o contador
 * offline SÓ reinicia com uma renovação assinada válida. Sem isso — resposta
 * sem licença, ou assinada por outra chave — nada se reinicia.
 */
class CheckinTest extends TestCase
{
    private string $publica;
    private string $privada;
    private array $cfg;
    private LicenseManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $par = LicenseIssuer::gerarParDeChaves();
        $this->publica = $par['publica'];
        $this->privada = $par['privada'];

        $dir = sys_get_temp_dir() . '/soserp_lic_' . bin2hex(random_bytes(4));
        $this->cfg = [
            'public_key'   => $this->publica,
            'license_path' => $dir . '/license.key',
            'state_path'   => $dir . '/state.json',
            'bind_machine' => false,
            'offline_grace_days' => 15,
            'checkin_url'  => 'https://lic.exemplo/checkin',
        ];
        $this->manager = new LicenseManager(new LicenseStore($this->cfg), $this->cfg);

        // Licença inicial já instalada.
        $inicial = (new LicenseIssuer())->emitir(
            ['tenant_id' => 7, 'exp' => CarbonImmutable::now()->addDays(5)->getTimestamp()],
            $this->privada
        );
        $this->manager->store()->guardarToken($inicial);
    }

    private function checkin(): array
    {
        return (new LicenseCheckin($this->manager, $this->cfg))->executar();
    }

    public function test_renovacao_assinada_reinicia_contador(): void
    {
        $novo = (new LicenseIssuer())->emitir(
            ['tenant_id' => 7, 'exp' => CarbonImmutable::now()->addDays(30)->getTimestamp()],
            $this->privada
        );
        Http::fake(['lic.exemplo/*' => Http::response(['licenca' => $novo], 200)]);

        $r = $this->checkin();

        $this->assertTrue($r['ok']);
        $this->assertSame('renovada', $r['acao']);
        $this->assertSame($novo, $this->manager->store()->token());       // guardou a nova
        $this->assertNotEmpty($this->manager->store()->estado()['ultimo_checkin']); // reiniciou
    }

    public function test_renovacao_de_outra_chave_nao_reinicia(): void
    {
        // token assinado por um emissor DIFERENTE
        $intruso = LicenseIssuer::gerarParDeChaves();
        $falso = (new LicenseIssuer())->emitir(['tenant_id' => 7, 'exp' => CarbonImmutable::now()->addDays(30)->getTimestamp()], $intruso['privada']);
        Http::fake(['lic.exemplo/*' => Http::response(['licenca' => $falso], 200)]);

        $r = $this->checkin();

        $this->assertFalse($r['ok']);
        $this->assertSame('renovacao_invalida', $r['motivo']);
        $this->assertArrayNotHasKey('ultimo_checkin', $this->manager->store()->estado()); // NÃO reiniciou
    }

    public function test_resposta_sem_licenca_nao_reinicia(): void
    {
        Http::fake(['lic.exemplo/*' => Http::response(['acao' => 'continuar'], 200)]);

        $r = $this->checkin();

        $this->assertFalse($r['ok']);
        $this->assertArrayNotHasKey('ultimo_checkin', $this->manager->store()->estado());
    }

    public function test_bloquear_marca_bloqueio_remoto(): void
    {
        Http::fake(['lic.exemplo/*' => Http::response(['acao' => 'bloquear', 'motivo' => 'subscricao_suspensa'], 200)]);

        $r = $this->checkin();

        $this->assertTrue($r['ok']);
        $this->assertSame('bloquear', $r['acao']);
        $this->assertSame('subscricao_suspensa', $this->manager->store()->estado()['remote_bloqueio']);
    }

    public function test_sem_rede_nao_reinicia(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('offline'));

        $r = $this->checkin();

        $this->assertFalse($r['ok']);
        $this->assertSame('sem_rede', $r['motivo']);
    }
}
