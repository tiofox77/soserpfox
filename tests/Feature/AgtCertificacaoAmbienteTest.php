<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\SoftwareSetting;
use App\Services\AGT\AGTKeyStore;
use App\Services\AGT\AGTPayloadBuilder;
use App\Services\AGT\AGTProducerStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * Número do Processo de Certificação, por ambiente.
 *
 * A AGT certifica o software em separado em homologação e em produção, e emite
 * uma resolução para cada. Havia um só valor guardado — o de produção — e ia
 * também nas chamadas a homologação, que respondia:
 *
 *   E39 — «Os dados constantes na assinatura do produtor de software
 *   "jwsSoftwareSignature" não estão de acordo com a informação constante no
 *   Processo de Certificação do Software.»
 *
 * A assinatura estava boa (isso seria E08); o número é que era do outro
 * ambiente.
 */
class AgtCertificacaoAmbienteTest extends TenantTestCase
{
    private static array $pares = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::flush();   // softwareSetting() guarda em cache por uma hora
    }

    private function parRsa(int $n = 0): array
    {
        if (isset(self::$pares[$n])) {
            return self::$pares[$n];
        }

        $opcoes = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $cnf = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras'
            . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
        if (is_file($cnf)) {
            $opcoes['config'] = $cnf;
        }

        $res = openssl_pkey_new($opcoes);
        $this->assertNotFalse($res);
        openssl_pkey_export($res, $privada, null, $opcoes);

        return self::$pares[$n] = [openssl_pkey_get_details($res)['key'], $privada];
    }

    private function empresaPronta(string $ambiente): InvoicingSettings
    {
        [$pub, $priv] = $this->parRsa(0);
        Storage::disk('local')->put('saft/public_key.pem', $pub);
        Storage::disk('local')->put('saft/private_key.pem', $priv);
        Storage::disk('local')->put("saft/{$ambiente}/public_key.pem", $pub);
        Storage::disk('local')->put("saft/{$ambiente}/private_key.pem", $priv);

        [$pubE, $privE] = $this->parRsa(1);
        AGTKeyStore::store($this->tenant->id, $pubE, $privE, $ambiente);

        $d = InvoicingSettings::forTenant($this->tenant->id);
        $d->update(['agt_environment' => $ambiente]);

        return $d->fresh();
    }

    private function numeroEnviado(string $ambiente): string
    {
        return (new AGTPayloadBuilder($this->empresaPronta($ambiente)))
            ->softwareInfo()['softwareInfoDetail']['softwareValidationNumber'];
    }

    public function test_cada_ambiente_envia_o_seu_numero(): void
    {
        SoftwareSetting::set('invoicing', 'saft_software_cert_sandbox', 'FE/351/AGT/2026', 'string');
        SoftwareSetting::set('invoicing', 'saft_software_cert_production', 'FE/324/AGT/2026', 'string');
        Cache::flush();

        $this->assertSame('FE/351/AGT/2026', $this->numeroEnviado('sandbox'));
        $this->assertSame('FE/324/AGT/2026', $this->numeroEnviado('production'));
    }

    public function test_o_de_producao_nao_vai_para_homologacao(): void
    {
        // O caso que deu E39.
        SoftwareSetting::set('invoicing', 'saft_software_cert_sandbox', 'FE/351/AGT/2026', 'string');
        SoftwareSetting::set('invoicing', 'saft_software_cert_production', 'FE/324/AGT/2026', 'string');
        Cache::flush();

        $this->assertNotSame(
            'FE/324/AGT/2026',
            $this->numeroEnviado('sandbox'),
            'homologação não pode levar o número de produção'
        );
    }

    public function test_sem_numero_do_ambiente_usa_o_antigo(): void
    {
        // Para não parar quem ainda não os separou.
        SoftwareSetting::set('invoicing', 'saft_software_cert', 'FE/324/AGT/2026', 'string');
        Cache::flush();

        $this->assertSame('FE/324/AGT/2026', AGTProducerStore::numeroCertificacao('sandbox'));
        $this->assertSame('FE/324/AGT/2026', AGTProducerStore::numeroCertificacao('production'));

        $this->assertFalse(
            AGTProducerStore::temCertificacaoPropria('sandbox'),
            'tem de ficar claro que ainda é o partilhado'
        );
    }

    public function test_o_do_ambiente_ganha_ao_antigo(): void
    {
        SoftwareSetting::set('invoicing', 'saft_software_cert', 'FE/324/AGT/2026', 'string');
        SoftwareSetting::set('invoicing', 'saft_software_cert_sandbox', 'FE/351/AGT/2026', 'string');
        Cache::flush();

        $this->assertSame('FE/351/AGT/2026', AGTProducerStore::numeroCertificacao('sandbox'));
        $this->assertSame('FE/324/AGT/2026', AGTProducerStore::numeroCertificacao('production'));
    }

    public function test_o_numero_vai_dentro_da_assinatura(): void
    {
        // Não basta ir no payload: é assinado, e é isso que a AGT confronta
        // com o Processo de Certificação.
        SoftwareSetting::set('invoicing', 'saft_software_cert_sandbox', 'FE/351/AGT/2026', 'string');
        Cache::flush();

        $bloco = (new AGTPayloadBuilder($this->empresaPronta('sandbox')))->softwareInfo();

        [$pubProdutor] = $this->parRsa(0);
        $assinado = (new \App\Services\AGT\JwsSigner())
            ->verify($bloco['jwsSoftwareSignature'], $pubProdutor)['payload'] ?? [];

        $this->assertSame('FE/351/AGT/2026', $assinado['softwareValidationNumber'] ?? null);
    }
}
