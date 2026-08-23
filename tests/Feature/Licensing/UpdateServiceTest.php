<?php

namespace Tests\Feature\Licensing;

use App\Services\Licensing\LicenseIssuer;
use App\Services\Licensing\UpdateService;
use App\Services\Licensing\UpdateSigner;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O cliente de atualização. Fixa as duas trancas: o manifesto reverifica-se
 * sempre localmente (servidor pode mentir, assinatura não), e o pacote só passa
 * se o SHA-256 bater.
 */
class UpdateServiceTest extends TestCase
{
    private string $publica;
    private string $privada;
    private UpdateService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $par = LicenseIssuer::gerarParDeChaves();
        $this->publica = $par['publica'];
        $this->privada = $par['privada'];

        config([
            'licensing.update.public_key' => $this->publica,
            'licensing.update.check_url'  => 'https://up.exemplo/check',
            'licensing.update.current'    => '1.0.0',
            'licensing.update.work_dir'   => sys_get_temp_dir() . '/soserp_upd_' . bin2hex(random_bytes(3)),
        ]);
        $this->servico = UpdateService::apartirDaConfig();
    }

    private function manifesto(array $extra = []): string
    {
        return (new UpdateSigner())->assinar(array_merge([
            'versao'        => '1.1.0',
            'pacote_url'    => 'https://cdn.exemplo/soserp-1.1.0.zip',
            'pacote_sha256' => str_repeat('a', 64),
        ], $extra), $this->privada);
    }

    public function test_verificar_devolve_claims_de_manifesto_valido(): void
    {
        Http::fake(['up.exemplo/*' => Http::response(['atualizado' => false, 'manifesto' => $this->manifesto()], 200)]);

        $claims = $this->servico->verificar('token-qualquer');

        $this->assertNotNull($claims);
        $this->assertSame('1.1.0', $claims['versao']);
    }

    public function test_verificar_rejeita_manifesto_adulterado(): void
    {
        $m = $this->manifesto();
        $p = explode('.', $m);
        $p[2][4] = $p[2][4] === 'A' ? 'B' : 'A';
        Http::fake(['up.exemplo/*' => Http::response(['atualizado' => false, 'manifesto' => implode('.', $p)], 200)]);

        $this->assertNull($this->servico->verificar('token-qualquer'));
    }

    public function test_verificar_null_quando_atualizado(): void
    {
        Http::fake(['up.exemplo/*' => Http::response(['atualizado' => true], 200)]);
        $this->assertNull($this->servico->verificar('token-qualquer'));
    }

    public function test_descarregar_recusa_hash_errado(): void
    {
        $corpo = 'pacote-falso';
        Http::fake(['cdn.exemplo/*' => Http::response($corpo, 200)]);

        $this->expectException(\RuntimeException::class);
        $this->servico->descarregar([
            'versao'        => '1.1.0',
            'pacote_url'    => 'https://cdn.exemplo/soserp-1.1.0.zip',
            'pacote_sha256' => str_repeat('f', 64), // não bate
        ]);
    }

    public function test_descarregar_aceita_hash_certo(): void
    {
        $corpo = 'pacote-verdadeiro-123';
        Http::fake(['cdn.exemplo/*' => Http::response($corpo, 200)]);

        $caminho = $this->servico->descarregar([
            'versao'        => '1.1.0',
            'pacote_url'    => 'https://cdn.exemplo/soserp-1.1.0.zip',
            'pacote_sha256' => hash('sha256', $corpo),
        ]);

        $this->assertFileExists($caminho);
        @unlink($caminho);
    }
}
