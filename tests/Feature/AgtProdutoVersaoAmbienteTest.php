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
 * productId e productVersion, por ambiente.
 *
 * Os três campos que a jwsSoftwareSignature assina — productId, productVersion
 * e o número de certificação — são confrontados pela AGT, letra a letra, com o
 * Processo de Certificação. E a AGT certifica cada ambiente em separado.
 *
 * O tenant Free Dation não conseguia submeter séries em produção:
 *
 *   E39 — «Os dados constantes na assinatura do produtor de software
 *   "jwsSoftwareSignature" não estão de acordo com a informação constante no
 *   Processo de Certificação do Software.»
 *
 * A chave estava certa (o mesmo sha256 que a AGT tinha registado). O que não
 * batia era o CONTEÚDO assinado: o sistema mandava «SOS ERP - …» / «1.0» nos
 * dois ambientes, mas produção fora certificada como «SOS ERP — …» / «1.0.0»
 * (hífen contra travessão, e a versão sem o terceiro número). Enquanto estes
 * dois campos foram um valor único partilhado, acertar produção estragava
 * homologação.
 */
class AgtProdutoVersaoAmbienteTest extends TenantTestCase
{
    private static array $par = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::flush();
    }

    private function par(): array
    {
        if (self::$par) {
            return self::$par;
        }

        $opcoes = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (is_file($cnf)) {
            $opcoes['config'] = $cnf;
        }

        $res = openssl_pkey_new($opcoes);
        openssl_pkey_export($res, $priv, null, $opcoes);

        return self::$par = [openssl_pkey_get_details($res)['key'], $priv];
    }

    private function detalheAssinado(string $ambiente): array
    {
        [$pub, $priv] = $this->par();
        Storage::disk('local')->put('saft/public_key.pem', $pub);
        Storage::disk('local')->put('saft/private_key.pem', $priv);

        [$pubE, $privE] = $this->par();
        AGTKeyStore::store($this->tenant->id, $pubE, $privE, $ambiente);

        $d = InvoicingSettings::forTenant($this->tenant->id);
        $d->update(['agt_environment' => $ambiente]);

        return (new AGTPayloadBuilder($d->fresh()))->softwareInfo()['softwareInfoDetail'];
    }

    // ── o caso do Free Dation ──────────────────────────────────────────────

    public function test_cada_ambiente_assina_o_seu_nome_e_versao(): void
    {
        SoftwareSetting::set('invoicing', 'saft_product_id_sandbox', 'SOS ERP - SOLUÇÕES EMPRESARIAIS', 'string');
        SoftwareSetting::set('invoicing', 'saft_version_sandbox', '1.0', 'string');
        SoftwareSetting::set('invoicing', 'saft_product_id_production', 'SOS ERP — SOLUÇÕES EMPRESARIAIS', 'string');
        SoftwareSetting::set('invoicing', 'saft_version_production', '1.0.0', 'string');
        Cache::flush();

        $hml = $this->detalheAssinado('sandbox');
        $this->assertSame('SOS ERP - SOLUÇÕES EMPRESARIAIS', $hml['productId']);
        $this->assertSame('1.0', $hml['productVersion']);

        $prod = $this->detalheAssinado('production');
        $this->assertSame('SOS ERP — SOLUÇÕES EMPRESARIAIS', $prod['productId']);
        $this->assertSame('1.0.0', $prod['productVersion']);
    }

    public function test_a_versao_de_producao_nao_contamina_homologacao(): void
    {
        SoftwareSetting::set('invoicing', 'saft_version_sandbox', '1.0', 'string');
        SoftwareSetting::set('invoicing', 'saft_version_production', '1.0.0', 'string');
        Cache::flush();

        // Homologação funcionava; corrigir produção não a pode partir.
        $this->assertSame('1.0', $this->detalheAssinado('sandbox')['productVersion']);
        $this->assertSame('1.0.0', $this->detalheAssinado('production')['productVersion']);
    }

    public function test_o_travessao_de_producao_nao_contamina_homologacao(): void
    {
        SoftwareSetting::set('invoicing', 'saft_product_id_sandbox', 'SOS ERP - SOLUÇÕES EMPRESARIAIS', 'string');
        SoftwareSetting::set('invoicing', 'saft_product_id_production', 'SOS ERP — SOLUÇÕES EMPRESARIAIS', 'string');
        Cache::flush();

        $this->assertStringContainsString(' - ', $this->detalheAssinado('sandbox')['productId']);
        $this->assertStringContainsString(' — ', $this->detalheAssinado('production')['productId']);
    }

    // ── a rede: sem valor de ambiente, usa o global ────────────────────────

    public function test_sem_valor_do_ambiente_usa_o_global(): void
    {
        SoftwareSetting::set('invoicing', 'saft_product_id', 'SOS ERP GLOBAL', 'string');
        SoftwareSetting::set('invoicing', 'saft_version', '9.9', 'string');
        Cache::flush();

        foreach (['sandbox', 'production'] as $amb) {
            $this->assertSame('SOS ERP GLOBAL', AGTProducerStore::productId($amb));
            $this->assertSame('9.9', AGTProducerStore::productVersion($amb));
        }
    }

    public function test_o_do_ambiente_ganha_ao_global(): void
    {
        SoftwareSetting::set('invoicing', 'saft_version', '1.0', 'string');
        SoftwareSetting::set('invoicing', 'saft_version_production', '1.0.0', 'string');
        Cache::flush();

        $this->assertSame('1.0', AGTProducerStore::productVersion('sandbox'));
        $this->assertSame('1.0.0', AGTProducerStore::productVersion('production'));
    }

    // ── o número e o conteúdo vão MESMO dentro da assinatura ───────────────

    public function test_o_nome_e_a_versao_vao_dentro_da_assinatura(): void
    {
        SoftwareSetting::set('invoicing', 'saft_product_id_production', 'SOS ERP — SOLUÇÕES EMPRESARIAIS', 'string');
        SoftwareSetting::set('invoicing', 'saft_version_production', '1.0.0', 'string');
        Cache::flush();

        [$pub, $priv] = $this->par();
        Storage::disk('local')->put('saft/public_key.pem', $pub);
        Storage::disk('local')->put('saft/private_key.pem', $priv);
        [$pubE, $privE] = $this->par();
        AGTKeyStore::store($this->tenant->id, $pubE, $privE, 'production');

        $d = InvoicingSettings::forTenant($this->tenant->id);
        $d->update(['agt_environment' => 'production']);

        $bloco = (new AGTPayloadBuilder($d->fresh()))->softwareInfo();
        $jws = $bloco['jwsSoftwareSignature'];

        // O payload da assinatura (parte do meio do JWS) tem de conter os
        // valores de produção — não basta irem no softwareInfoDetail em claro.
        $partes = explode('.', $jws);
        $this->assertCount(3, $partes, 'JWS bem formado');
        $payload = json_decode(base64_decode(strtr($partes[1], '-_', '+/')), true);

        $this->assertSame('SOS ERP — SOLUÇÕES EMPRESARIAIS', $payload['productId']);
        $this->assertSame('1.0.0', $payload['productVersion']);
    }
}
