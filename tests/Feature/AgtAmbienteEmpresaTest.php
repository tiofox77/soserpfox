<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\AGTKeyStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * Configuração AGT: os dois ambientes configuram-se em separado.
 *
 * Havia um seletor só, que era ao mesmo tempo "o ambiente que estou a ver" e
 * "o ambiente que emite". Instalar as chaves de produção obrigava a pôr já a
 * empresa em produção; e espreitar a homologação seguido de um Guardar por
 * outro motivo qualquer deitava produção abaixo sem aviso nenhum.
 *
 * O ecrã é hoje React e fala com a API (`/agt/chaves`, `/agt/ambiente`). O que
 * sobra aqui é o que mais nenhum ensaio faz: instalar PARES RSA A SÉRIO,
 * gerados na hora, um por ambiente — a validação das chaves só se prova com
 * chaves verdadeiras, e é com elas que se vê que instalar as de produção não
 * escreve por cima das de homologação nem tira a empresa de onde está. As
 * regras genéricas (ver ≠ editar, guardar não muda o ambiente, uma chave que
 * não é PEM, remover só um ambiente) estão no `ApiDaAgtParaReactTest`.
 */
class AgtAmbienteEmpresaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/agt';

    /** Pares gerados uma vez por execução: RSA de 2048 bits não é barato. */
    private static array $pares = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Disco falso: estes testes instalam e apagam chaves. No disco a sério
        // — o mesmo da instalação de desenvolvimento — deixavam lixo em
        // storage/app/private/agt/tenants/ e podiam apagar chaves reais.
        Storage::fake('local');

        // Nada sai para a AGT a partir de um ensaio.
        Http::fake(['*' => Http::response(['requestID' => 'x', 'resultCode' => '0'], 200)]);

        // Sem estas permissões as acções vêm 403 e os testes passariam por não
        // ter acontecido nada — que é o mesmo resultado de "não mudou o
        // ambiente". Ficariam a verificar o 403, não a regra.
        $this->comModulo('invoicing')->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
    }

    /** Par RSA válido — as chaves são validadas antes de gravadas. */
    private function parRsa(int $n = 0): array
    {
        if (isset(self::$pares[$n])) {
            return self::$pares[$n];
        }

        $opcoes = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // No Windows o PHP não encontra o openssl.cnf sozinho e a geração
        // falha. Em Linux o ficheiro não está aqui e o omisso funciona.
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

    private function definicoes(): InvoicingSettings
    {
        return InvoicingSettings::forTenant($this->tenant->id);
    }

    /**
     * O resto do que produção exige além do par do contribuinte: credenciais
     * e número de certificação PRÓPRIOS de produção e a chave do produtor.
     * Sem isto o activarAmbiente() recusa com a lista da prontidão — é outro
     * ensaio (AgtProntidaoEConfirmacaoTest).
     */
    private function produtorProntoParaProducao(): void
    {
        config([
            'services.agt.production.username' => 'produtor-producao',
            'services.agt.production.password' => 'segredo-de-ensaio',
        ]);
        \App\Models\SoftwareSetting::set('invoicing', 'saft_software_cert_production', 'FE/324/AGT/2026', 'string');
        Storage::disk('local')->put('saft/production/private_key.pem', 'chave de ensaio');
        Storage::disk('local')->put('saft/production/public_key.pem', 'chave de ensaio');
        \Illuminate\Support\Facades\Cache::flush();
    }

    /** Instalar as chaves de um ambiente é sempre pelo ambiente pedido. */
    private function instalar(string $ambiente, array $par): void
    {
        $this->postJson(self::RAIZ . '/chaves', [
            'ambiente' => $ambiente,
            'contributorPublicKey' => $par[0],
            'contributorPrivateKey' => $par[1],
        ])->assertOk();
    }

    public function test_instalar_chaves_de_producao_sem_sair_de_homologacao(): void
    {
        $this->definicoes()->update(['agt_environment' => 'sandbox']);

        // O pedido de origem: configurar os dois ambientes. Instalar as chaves
        // de produção não pode obrigar a empresa a começar a emitir por lá.
        $this->instalar('production', $this->parRsa());

        $this->assertTrue(AGTKeyStore::hasKeyPair($this->tenant->id, 'production'));
        $this->assertSame('sandbox', $this->definicoes()->fresh()->agt_environment);
    }

    public function test_as_chaves_de_um_ambiente_nao_sobrepoem_as_do_outro(): void
    {
        [$pubA, $privA] = $this->parRsa(0);
        [$pubB, $privB] = $this->parRsa(1);

        $this->instalar('sandbox', [$pubA, $privA]);
        $this->instalar('production', [$pubB, $privB]);

        $this->assertTrue(AGTKeyStore::hasKeyPair($this->tenant->id, 'sandbox'));
        $this->assertTrue(AGTKeyStore::hasKeyPair($this->tenant->id, 'production'));

        $lida = Storage::disk('local')->get(
            AGTKeyStore::publicKeyPath($this->tenant->id, 'sandbox')
        );

        $this->assertSame(trim($pubA), trim($lida), 'a chave de homologação tem de continuar a ser a que lá foi posta');
    }

    /**
     * ACTIVAR PRODUÇÃO EXIGE O PAR DE PRODUÇÃO — com chaves a sério.
     *
     * Sem ele a empresa ficava a falhar toda a facturação: nenhum documento
     * chega a ser assinado. E ter o par de homologação instalado não vale: são
     * universos separados.
     */
    public function test_activar_producao_exige_as_chaves_de_producao(): void
    {
        $this->definicoes()->update(['agt_environment' => 'sandbox']);
        $this->instalar('sandbox', $this->parRsa(0));

        $r = $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'production'])->assertStatus(422);

        // A recusa tem de ser pela falta de chaves. Sem isto, o ensaio passava
        // na mesma se a acção tivesse rebentado por outro motivo: "não mudou o
        // ambiente" também é o que acontece num 403.
        $this->assertStringContainsString('par RSA de Produção', $r->json('message'));
        $this->assertSame('sandbox', $this->definicoes()->fresh()->agt_environment);

        $this->instalar('production', $this->parRsa(1));
        $this->produtorProntoParaProducao();

        // A troca pede hoje um `confirmar` explícito (ver o teste seguinte).
        $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'production', 'confirmar' => true])
            ->assertOk()
            ->assertJsonPath('ambiente_activo', 'production');

        $this->assertSame('production', $this->definicoes()->fresh()->agt_environment);
    }

    /**
     * VOLTAR A HOMOLOGAÇÃO CONTINUA SEMPRE POSSÍVEL — mas já não às cegas.
     *
     * Era um POST sem mais nada. Com documentos de produção por enviar, essa
     * troca mandava-os para a AGT de testes, que os «validava». O defeito
     * fechou-se na raiz (o despacho, a consulta e o reenvio só tocam nas
     * submissões do ambiente activo); a troca não se bloqueia, mas passa a
     * exigir `confirmar: true`, para quem carrega ver antes quantas ficam à
     * espera. Sem chaves de homologação instaladas, na mesma: é o recuo.
     */
    public function test_voltar_a_homologacao_e_sempre_possivel(): void
    {
        $this->definicoes()->update(['agt_environment' => 'production']);

        $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'sandbox'])
            ->assertStatus(422)
            ->assertJsonPath('confirmar_necessario', true);
        $this->assertSame('production', $this->definicoes()->fresh()->agt_environment, 'sem confirmar não muda');

        $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'sandbox', 'confirmar' => true])
            ->assertOk()
            ->assertJsonPath('ambiente_activo', 'sandbox');

        $this->assertSame('sandbox', $this->definicoes()->fresh()->agt_environment);
    }

    /** O ecrã mostra os DOIS ambientes, e diz qual deles é que emite. */
    public function test_o_estado_mostra_os_dois_ambientes(): void
    {
        $this->definicoes()->update(['agt_environment' => 'sandbox']);
        $this->instalar('sandbox', $this->parRsa());

        $estado = $this->getJson(self::RAIZ . '/estado')->assertOk();

        $this->assertTrue($estado->json('ambientes.sandbox.chaves'));
        $this->assertFalse($estado->json('ambientes.production.chaves'), 'produção sem chaves tem de aparecer como tal');
        $this->assertTrue($estado->json('ambientes.sandbox.activo'));
        $this->assertFalse($estado->json('ambientes.production.activo'));

        // E ver o outro continua a dizer a verdade sobre o que emite.
        $aVer = $this->getJson(self::RAIZ . '/estado?ambiente=production')->assertOk();

        $this->assertTrue($aVer->json('ambientes.production.a_ver'));
        $this->assertTrue($aVer->json('ambientes.sandbox.activo'));
        $this->assertSame('sandbox', $this->definicoes()->fresh()->agt_environment);
    }
}
