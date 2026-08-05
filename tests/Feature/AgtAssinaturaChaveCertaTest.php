<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\AGTKeyStore;
use App\Services\AGT\AGTPayloadBuilder;
use App\Services\AGT\JwsSigner;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * Cada assinatura tem de sair da chave certa — e faltar uma tem de doer.
 *
 * O disco `local` está com throw=false: um get() a um ficheiro inexistente
 * devolve null em vez de rebentar. O JwsSigner, ao receber null, ia buscar
 * saft/private_key.pem — a chave do PRODUTOR. Resultado: uma empresa sem
 * chave instalada via os seus documentos assinados pela chave do produtor,
 * sem erro nem registo, e a AGT recusava-os com uma mensagem que não apontava
 * para nada. Basta passar uma empresa a produção sem lá instalar as chaves.
 */
class AgtAssinaturaChaveCertaTest extends TenantTestCase
{
    private static array $pares = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function parRsa(int $n = 0): array
    {
        if (isset(self::$pares[$n])) {
            return self::$pares[$n];
        }

        $opcoes = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

        // No Windows o PHP não encontra o openssl.cnf sozinho.
        $cnf = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras'
            . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
        if (is_file($cnf)) {
            $opcoes['config'] = $cnf;
        }

        $res = openssl_pkey_new($opcoes);
        $this->assertNotFalse($res, 'nao foi possivel gerar o par RSA de teste');
        openssl_pkey_export($res, $privada, null, $opcoes);

        return self::$pares[$n] = [openssl_pkey_get_details($res)['key'], $privada];
    }

    /** Instala o par do produtor (partilhado por todas as empresas). */
    private function chaveDoProdutor(): array
    {
        [$publica, $privada] = $this->parRsa(0);
        Storage::disk('local')->put('saft/public_key.pem', $publica);
        Storage::disk('local')->put('saft/private_key.pem', $privada);

        return [$publica, $privada];
    }

    private function definicoes(string $ambiente = 'sandbox'): InvoicingSettings
    {
        $d = InvoicingSettings::forTenant($this->tenant->id);
        $d->update(['agt_environment' => $ambiente]);

        return $d->fresh();
    }

    public function test_o_documento_e_assinado_pela_chave_da_empresa(): void
    {
        $this->chaveDoProdutor();
        [$publicaEmpresa, $privadaEmpresa] = $this->parRsa(1);
        AGTKeyStore::store($this->tenant->id, $publicaEmpresa, $privadaEmpresa, 'sandbox');

        $construtor = new AGTPayloadBuilder($this->definicoes());
        $serie = $construtor->buildSolicitarSerie('5001363476', 2026, 'FT', 'SEDE', 'N');

        $verificador = new JwsSigner();

        $this->assertTrue(
            $verificador->verify($serie['jwsSignature'], $publicaEmpresa)['valid'] ?? false,
            'a assinatura do pedido tem de sair da chave da empresa'
        );
    }

    public function test_a_do_software_e_assinada_pela_chave_do_produtor(): void
    {
        [$publicaProdutor] = $this->chaveDoProdutor();
        [$publicaEmpresa, $privadaEmpresa] = $this->parRsa(1);
        AGTKeyStore::store($this->tenant->id, $publicaEmpresa, $privadaEmpresa, 'sandbox');

        $bloco = (new AGTPayloadBuilder($this->definicoes()))->softwareInfo();
        $verificador = new JwsSigner();

        $this->assertTrue(
            $verificador->verify($bloco['jwsSoftwareSignature'], $publicaProdutor)['valid'] ?? false
        );

        // E a chave da empresa tem de a recusar — é o que prova que não foi ela.
        $this->assertFalse(
            $verificador->verify($bloco['jwsSoftwareSignature'], $publicaEmpresa)['valid'] ?? false
        );
    }

    public function test_sem_chave_da_empresa_recusa_em_vez_de_usar_a_do_produtor(): void
    {
        // O caso de origem. Antes assinava na mesma, com a chave errada.
        $this->chaveDoProdutor();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('não tem chave privada do contribuinte');

        new AGTPayloadBuilder($this->definicoes());
    }

    public function test_passar_a_producao_sem_chaves_de_producao_recusa(): void
    {
        // Chaves instaladas só em homologação; a empresa passa a produção.
        // O par de homologação não serve — e o do produtor muito menos.
        $this->chaveDoProdutor();
        [$publica, $privada] = $this->parRsa(1);
        AGTKeyStore::store($this->tenant->id, $publica, $privada, 'sandbox');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Produção');

        new AGTPayloadBuilder($this->definicoes('production'));
    }

    public function test_o_ambiente_das_definicoes_manda_no_par_usado(): void
    {
        // O AGTKeyStore relia o ambiente à base de dados quando não lho davam.
        // Duas empresas, dois ambientes, dois pares: tem de usar o que lhe é
        // passado, não o que a base diz.
        $this->chaveDoProdutor();
        [$pubHml, $privHml] = $this->parRsa(1);
        [$pubPrd, $privPrd] = $this->parRsa(2);
        AGTKeyStore::store($this->tenant->id, $pubHml, $privHml, 'sandbox');
        AGTKeyStore::store($this->tenant->id, $pubPrd, $privPrd, 'production');

        $verificador = new JwsSigner();

        $hml = (new AGTPayloadBuilder($this->definicoes('sandbox')))
            ->buildSolicitarSerie('5001363476', 2026, 'FT', 'SEDE', 'N');
        $prd = (new AGTPayloadBuilder($this->definicoes('production')))
            ->buildSolicitarSerie('5001363476', 2026, 'FT', 'SEDE', 'N');

        $this->assertTrue($verificador->verify($hml['jwsSignature'], $pubHml)['valid'] ?? false);
        $this->assertFalse($verificador->verify($hml['jwsSignature'], $pubPrd)['valid'] ?? false);

        $this->assertTrue($verificador->verify($prd['jwsSignature'], $pubPrd)['valid'] ?? false);
        $this->assertFalse($verificador->verify($prd['jwsSignature'], $pubHml)['valid'] ?? false);
    }

    public function test_o_cabecalho_jws_e_o_canonico_da_agt(): void
    {
        $this->chaveDoProdutor();
        [$publica, $privada] = $this->parRsa(1);
        AGTKeyStore::store($this->tenant->id, $publica, $privada, 'sandbox');

        $serie = (new AGTPayloadBuilder($this->definicoes()))
            ->buildSolicitarSerie('5001363476', 2026, 'FT', 'SEDE', 'N');

        $this->assertSame(
            ['typ' => 'JOSE', 'alg' => 'RS256'],
            (new JwsSigner())->verify($serie['jwsSignature'], $publica)['header'] ?? null
        );
    }
}
