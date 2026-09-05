<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\AGTSettings;
use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\AGTKeyStore;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Ecrã de configurações AGT: os dois ambientes configuram-se em separado.
 *
 * Havia um seletor só, que era ao mesmo tempo "o ambiente que estou a ver" e
 * "o ambiente que emite". Instalar as chaves de produção obrigava a pôr já a
 * empresa em produção; e espreitar a homologação seguido de um Guardar por
 * outro motivo qualquer deitava produção abaixo sem aviso nenhum.
 */
class AgtAmbienteEmpresaTest extends TenantTestCase
{
    /** Pares gerados uma vez por execução: RSA de 2048 bits não é barato. */
    private static array $pares = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Disco falso: estes testes instalam e apagam chaves. No disco a sério
        // — o mesmo da instalação de desenvolvimento — deixavam lixo em
        // storage/app/private/agt/tenants/ e podiam apagar chaves reais.
        \Illuminate\Support\Facades\Storage::fake('local');

        // Sem esta permissão as acções abortam com 403 e os testes passariam
        // por não ter acontecido nada — que é o mesmo resultado de "não mudou
        // o ambiente". Ficariam a verificar o 403, não a regra.
        $this->comPermissoes('invoicing.agt.edit');
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

    public function test_abre_no_ambiente_activo(): void
    {
        $this->definicoes()->update(['agt_environment' => 'production']);

        Livewire::actingAs($this->user)
            ->test(AGTSettings::class)
            ->assertSet('agt_environment', 'production')
            ->assertSet('ambienteVista', 'production');
    }

    public function test_ver_o_outro_ambiente_nao_muda_o_que_emite(): void
    {
        $this->definicoes()->update(['agt_environment' => 'production']);

        Livewire::actingAs($this->user)
            ->test(AGTSettings::class)
            ->set('ambienteVista', 'sandbox')
            ->assertSet('agt_environment', 'production');

        $this->assertSame(
            'production',
            $this->definicoes()->fresh()->agt_environment,
            'olhar para homologação não pode tirar a empresa de produção'
        );
    }

    public function test_guardar_nao_mexe_no_ambiente(): void
    {
        // O caso perigoso: empresa em produção, alguém está a ver a
        // homologação e carrega em Guardar para mudar o CAE.
        $this->definicoes()->update(['agt_environment' => 'production']);

        Livewire::actingAs($this->user)
            ->test(AGTSettings::class)
            ->set('ambienteVista', 'sandbox')
            ->set('agt_eac_code', '47730')
            ->call('save')
            ->assertHasNoErrors();

        $definicoes = $this->definicoes()->fresh();

        $this->assertSame('production', $definicoes->agt_environment, 'guardar o CAE não pode mudar o ambiente');
        $this->assertSame('47730', $definicoes->agt_eac_code, 'e o CAE tinha mesmo de ser guardado');
    }

    public function test_activar_producao_exige_as_chaves_de_producao(): void
    {
        $this->definicoes()->update(['agt_environment' => 'sandbox']);

        // Activar produção sem par RSA de produção deixava a empresa a falhar
        // toda a facturação: nenhum documento chega a ser assinado.
        [$publica, $privada] = $this->parRsa();
        AGTKeyStore::store($this->tenant->id, $publica, $privada, 'sandbox');

        Livewire::actingAs($this->user)
            ->test(AGTSettings::class)
            ->set('ambienteVista', 'production')
            ->call('activarAmbiente')
            // A recusa tem de ser pela falta de chaves. Sem isto, o teste
            // passava na mesma se a acção tivesse rebentado por outro motivo:
            // "não mudou o ambiente" também é o que acontece num 403.
            ->assertDispatched('notify', fn ($evento, $params) =>
                ($params['type'] ?? null) === 'error'
                && str_contains($params['message'] ?? '', 'par RSA de Produção'))
            ->assertSet('agt_environment', 'sandbox');

        $this->assertSame('sandbox', $this->definicoes()->fresh()->agt_environment);
    }

    public function test_activar_producao_com_chaves(): void
    {
        [$publica, $privada] = $this->parRsa();
        AGTKeyStore::store($this->tenant->id, $publica, $privada, 'production');

        Livewire::actingAs($this->user)
            ->test(AGTSettings::class)
            ->set('ambienteVista', 'production')
            ->call('activarAmbiente')
            ->assertSet('agt_environment', 'production');

        $this->assertSame('production', $this->definicoes()->fresh()->agt_environment);
    }

    public function test_voltar_a_homologacao_e_sempre_possivel(): void
    {
        // Sem chaves de homologação instaladas: a saída de produção não pode
        // ficar bloqueada, é o caminho de recuo.
        $this->definicoes()->update(['agt_environment' => 'production']);

        Livewire::actingAs($this->user)
            ->test(AGTSettings::class)
            ->set('ambienteVista', 'sandbox')
            ->call('activarAmbiente')
            ->assertSet('agt_environment', 'sandbox');
    }

    public function test_instalar_chaves_de_producao_sem_sair_de_homologacao(): void
    {
        $this->definicoes()->update(['agt_environment' => 'sandbox']);

        // O pedido de origem: configurar os dois ambientes. Instalar as chaves
        // de produção não pode obrigar a empresa a começar a emitir por lá.
        [$publica, $privada] = $this->parRsa();

        Livewire::actingAs($this->user)
            ->test(AGTSettings::class)
            ->set('ambienteVista', 'production')
            ->set('contributorPublicKey', $publica)
            ->set('contributorPrivateKey', $privada)
            ->call('saveContributorKeys')
            ->assertHasNoErrors()
            ->assertSet('agt_environment', 'sandbox');

        $this->assertTrue(AGTKeyStore::hasKeyPair($this->tenant->id, 'production'));
        $this->assertSame('sandbox', $this->definicoes()->fresh()->agt_environment);
    }

    public function test_as_chaves_de_um_ambiente_nao_sobrepoem_as_do_outro(): void
    {
        [$pubA, $privA] = $this->parRsa(0);
        [$pubB, $privB] = $this->parRsa(1);

        $componente = Livewire::actingAs($this->user)->test(AGTSettings::class);

        $componente->set('ambienteVista', 'sandbox')
            ->set('contributorPublicKey', $pubA)
            ->set('contributorPrivateKey', $privA)
            ->call('saveContributorKeys');

        $componente->set('ambienteVista', 'production')
            ->set('contributorPublicKey', $pubB)
            ->set('contributorPrivateKey', $privB)
            ->call('saveContributorKeys');

        $this->assertTrue(AGTKeyStore::hasKeyPair($this->tenant->id, 'sandbox'));
        $this->assertTrue(AGTKeyStore::hasKeyPair($this->tenant->id, 'production'));

        $lida = \Illuminate\Support\Facades\Storage::disk('local')->get(
            AGTKeyStore::publicKeyPath($this->tenant->id, 'sandbox')
        );

        $this->assertSame(trim($pubA), trim($lida), 'a chave de homologação tem de continuar a ser a que lá foi posta');
    }

    public function test_remover_chaves_so_afecta_o_ambiente_visto(): void
    {
        [$pubA, $privA] = $this->parRsa(0);
        [$pubB, $privB] = $this->parRsa(1);
        AGTKeyStore::store($this->tenant->id, $pubA, $privA, 'sandbox');
        AGTKeyStore::store($this->tenant->id, $pubB, $privB, 'production');

        Livewire::actingAs($this->user)
            ->test(AGTSettings::class)
            ->set('ambienteVista', 'production')
            ->call('removeContributorKeys');

        $this->assertFalse(AGTKeyStore::hasKeyPair($this->tenant->id, 'production'));
        $this->assertTrue(
            AGTKeyStore::hasKeyPair($this->tenant->id, 'sandbox'),
            'apagar as de produção não pode levar as de homologação atrás'
        );
    }

    public function test_o_estado_mostra_os_dois_ambientes(): void
    {
        $this->definicoes()->update(['agt_environment' => 'sandbox']);

        [$publica, $privada] = $this->parRsa();
        AGTKeyStore::store($this->tenant->id, $publica, $privada, 'sandbox');

        $estado = Livewire::actingAs($this->user)
            ->test(AGTSettings::class)
            ->instance()
            ->estadoAmbientes();

        $this->assertTrue($estado['sandbox']['chaves']);
        $this->assertFalse($estado['production']['chaves'], 'produção sem chaves tem de aparecer como tal');
        $this->assertTrue($estado['sandbox']['activo']);
        $this->assertFalse($estado['production']['activo']);
    }

    public function test_o_ecra_mostra_os_dois_e_so_propoe_activar_o_outro(): void
    {
        $this->definicoes()->update(['agt_environment' => 'sandbox']);

        $componente = Livewire::actingAs($this->user)->test(AGTSettings::class);

        $componente->assertSee('Homologação')
            ->assertSee('Produção')
            ->assertSee('A emitir em Homologação')
            // No ambiente activo não há nada a activar.
            ->assertDontSee('Passar a emitir aqui');

        $componente->set('ambienteVista', 'production')
            ->assertSee('Passar a emitir aqui')
            // O cabeçalho continua a dizer a verdade sobre o que emite.
            ->assertSee('A emitir em Homologação');
    }

    public function test_sincronizar_series_recusa_se_o_ecra_mostra_outro_ambiente(): void
    {
        // Sincronizar séries escreve na AGT e corre sempre no ambiente activo.
        // A ver homologação e a registar séries em produção era um engano à
        // espera de acontecer.
        [$publica, $privada] = $this->parRsa();
        AGTKeyStore::store($this->tenant->id, $publica, $privada, 'production');
        $this->definicoes()->update(['agt_environment' => 'production']);

        Livewire::actingAs($this->user)
            ->test(AGTSettings::class)
            ->set('ambienteVista', 'sandbox')
            ->call('syncSeries')
            ->assertSet('syncResult', []);
    }
}
