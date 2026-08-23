<?php

namespace Tests\Unit\Licensing;

use App\Services\Licensing\UpdateSigner;
use App\Services\Licensing\UpdateVerifier;
use App\Services\Licensing\LicenseIssuer;
use Tests\TestCase;

/**
 * O manifesto de atualização é o que autoriza mexer nos ficheiros de uma
 * instalação. Se a assinatura falhar aberta, empurra-se código arbitrário para
 * a máquina do cliente. Fecha sempre.
 */
class UpdateSignerVerifierTest extends TestCase
{
    private string $publica;
    private string $privada;

    protected function setUp(): void
    {
        parent::setUp();
        $par = LicenseIssuer::gerarParDeChaves(); // mesmo tipo de chave (Ed25519)
        $this->publica = $par['publica'];
        $this->privada = $par['privada'];
    }

    private function manifesto(array $extra = []): string
    {
        return (new UpdateSigner())->assinar(array_merge([
            'versao'        => '1.2.0',
            'pacote_url'    => 'https://cdn.exemplo/soserp-1.2.0.zip',
            'pacote_sha256' => str_repeat('a', 64),
        ], $extra), $this->privada);
    }

    public function test_manifesto_valido_le(): void
    {
        $claims = (new UpdateVerifier($this->publica))->ler($this->manifesto());
        $this->assertNotNull($claims);
        $this->assertSame('1.2.0', $claims['versao']);
    }

    public function test_manifesto_adulterado_nao_le(): void
    {
        $m = $this->manifesto();
        $p = explode('.', $m);
        $p[2][4] = $p[2][4] === 'A' ? 'B' : 'A';
        $this->assertNull((new UpdateVerifier($this->publica))->ler(implode('.', $p)));
    }

    public function test_outra_chave_nao_le(): void
    {
        $outra = LicenseIssuer::gerarParDeChaves()['publica'];
        $this->assertNull((new UpdateVerifier($outra))->ler($this->manifesto()));
    }

    public function test_formato_mau_nao_le(): void
    {
        foreach (['', 'lixo', 'a.b.c', 'SOSERP-LIC.v1.x.y'] as $mau) {
            $this->assertNull((new UpdateVerifier($this->publica))->ler($mau));
        }
    }

    public function test_ficheiro_confere_sha256(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upd');
        file_put_contents($tmp, 'conteudo do pacote');
        $sha = hash('sha256', 'conteudo do pacote');

        $this->assertTrue(UpdateVerifier::ficheiroConfere($tmp, $sha));
        $this->assertFalse(UpdateVerifier::ficheiroConfere($tmp, str_repeat('0', 64)));
        @unlink($tmp);
    }
}
