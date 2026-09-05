<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\SoftwareSetting;
use App\Services\AGT\AGTClient;
use App\Services\AGT\AGTKeyStore;
use App\Services\AGT\AGTPayloadBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * O REGISTO DE SÉRIES assina o mesmo que a submissão de documentos.
 *
 * Havia DUAS implementações do mesmo bloco assinado do produtor:
 * `AGTPayloadBuilder::softwareInfo()` (documentos), que resolvia os três
 * campos por ambiente, e `AGTClient::buildSoftwareInfo()` (séries), que lia o
 * `productId` e o `productVersion` das definições GLOBAIS e só o número de
 * certificação por ambiente.
 *
 * Em produção saía o par trocado — versão `1.0` de homologação com o
 * certificado de produção, que é `1.0.0` — e a AGT respondia E39 ao registo de
 * séries de TODAS as empresas. Como a submissão de documentos passava pela
 * implementação certa, o sistema parecia funcionar: só o primeiro passo de
 * cada cliente novo é que falhava, sempre, e o defeito lia-se como problema
 * daquele cliente.
 *
 * O ensaio antigo (AgtProdutoVersaoAmbienteTest) cobria só a implementação
 * CERTA — foi por isso que o defeito sobreviveu. Este cobre a gémea, e prende
 * as duas uma à outra.
 */
class AgtSerieMesmaAssinaturaTest extends TenantTestCase
{
    private static array $par = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::flush();

        // Homologação e produção certificadas com valores DIFERENTES — é essa
        // diferença que expõe o defeito.
        SoftwareSetting::set('invoicing', 'saft_product_id_sandbox', 'SOS ERP - SOLUÇÕES EMPRESARIAIS', 'string');
        SoftwareSetting::set('invoicing', 'saft_version_sandbox', '1.0', 'string');
        SoftwareSetting::set('invoicing', 'saft_software_cert_sandbox', 'FE/351/AGT/2026', 'string');

        SoftwareSetting::set('invoicing', 'saft_product_id_production', 'SOS ERP — SOLUÇÕES EMPRESARIAIS', 'string');
        SoftwareSetting::set('invoicing', 'saft_version_production', '1.0.0', 'string');
        SoftwareSetting::set('invoicing', 'saft_software_cert_production', 'FE/324/AGT/2026', 'string');

        // O global fica com os valores de homologação — como está em produção
        // hoje, e é exactamente isso que o cliente lia por engano.
        SoftwareSetting::set('invoicing', 'saft_product_id', 'SOS ERP - SOLUÇÕES EMPRESARIAIS', 'string');
        SoftwareSetting::set('invoicing', 'saft_version', '1.0', 'string');

        Cache::flush();
    }

    private function par(): array
    {
        if (self::$par) {
            return self::$par;
        }

        $opcoes = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $cnf = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (is_file($cnf)) {
            $opcoes['config'] = $cnf;
        }

        $res = openssl_pkey_new($opcoes);
        openssl_pkey_export($res, $priv, null, $opcoes);

        return self::$par = [openssl_pkey_get_details($res)['key'], $priv];
    }

    /** Prepara chaves e ambiente, e devolve os dois blocos assinados. */
    private function blocos(string $ambiente): array
    {
        [$pub, $priv] = $this->par();
        Storage::disk('local')->put('saft/public_key.pem', $pub);
        Storage::disk('local')->put('saft/private_key.pem', $priv);

        [$pubE, $privE] = $this->par();
        AGTKeyStore::store($this->tenant->id, $pubE, $privE, $ambiente);

        $d = InvoicingSettings::forTenant($this->tenant->id);
        $d->update(['agt_environment' => $ambiente]);

        $documento = (new AGTPayloadBuilder($d->fresh()))->softwareInfo()['softwareInfoDetail'];

        // `buildSoftwareInfo` é privado — é o bloco que vai no registo de série.
        $cliente = new AGTClient($this->tenant->id, $ambiente);
        $metodo = (new \ReflectionClass($cliente))->getMethod('buildSoftwareInfo');
        $metodo->setAccessible(true);
        $serie = $metodo->invoke($cliente)['softwareInfoDetail'];

        return ['documento' => $documento, 'serie' => $serie];
    }

    /**
     * EM PRODUÇÃO, A SÉRIE ASSINA O QUE A PRODUÇÃO CERTIFICOU.
     *
     * Este é o ensaio que falhava antes: a série levava «1.0» (global,
     * de homologação) com o certificado FE/324, que é «1.0.0».
     *
     * @test
     */
    public function em_producao_a_serie_assina_os_valores_de_producao(): void
    {
        $b = $this->blocos('production');

        $this->assertSame('SOS ERP — SOLUÇÕES EMPRESARIAIS', $b['serie']['productId']);
        $this->assertSame('1.0.0', $b['serie']['productVersion']);
        $this->assertSame('FE/324/AGT/2026', $b['serie']['softwareValidationNumber']);
    }

    /** E em homologação assina os de homologação — sem contaminar. */
    public function test_em_homologacao_a_serie_assina_os_de_homologacao(): void
    {
        $b = $this->blocos('sandbox');

        $this->assertSame('SOS ERP - SOLUÇÕES EMPRESARIAIS', $b['serie']['productId']);
        $this->assertSame('1.0', $b['serie']['productVersion']);
        $this->assertSame('FE/351/AGT/2026', $b['serie']['softwareValidationNumber']);
    }

    /**
     * AS DUAS IMPLEMENTAÇÕES TÊM DE DIZER O MESMO.
     *
     * É esta a invariante que faltava. Enquanto houver dois caminhos a montar
     * o mesmo bloco assinado, é esta asserção que impede um deles de derivar
     * sem ninguém dar por isso.
     *
     * @test
     */
    public function a_serie_e_o_documento_assinam_exactamente_o_mesmo(): void
    {
        foreach (['sandbox', 'production'] as $ambiente) {
            $b = $this->blocos($ambiente);

            foreach (['productId', 'productVersion', 'softwareValidationNumber'] as $campo) {
                $this->assertSame(
                    $b['documento'][$campo],
                    $b['serie'][$campo],
                    "em {$ambiente}, o {$campo} do registo de série difere do da submissão de documentos"
                );
            }
        }
    }

    /**
     * O par versão↔certificado nunca pode sair trocado.
     *
     * É a assinatura exacta do E39: a AGT confronta os três campos com o
     * Processo de Certificação daquele ambiente, e basta um não bater.
     */
    public function test_a_versao_nunca_sai_com_o_certificado_do_outro_ambiente(): void
    {
        $prod = $this->blocos('production')['serie'];

        $this->assertNotSame('1.0', $prod['productVersion'],
            'a versão de homologação com o certificado de produção é exactamente o E39');

        $hml = $this->blocos('sandbox')['serie'];

        $this->assertNotSame('FE/324/AGT/2026', $hml['softwareValidationNumber']);
    }
}
