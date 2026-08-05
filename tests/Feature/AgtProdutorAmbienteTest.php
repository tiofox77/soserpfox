<?php

namespace Tests\Feature;

use App\Services\AGT\AGTProducerStore;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * Credenciais e chaves do PRODUTOR, por ambiente.
 *
 * A AGT entrega conjuntos diferentes para homologação e produção. Havia um só:
 * uma empresa em produção assinava com a chave de produtor de testes e
 * autenticava-se com as credenciais de testes, e a AGT de produção recusa
 * ambas. Instalar as de produção obrigava a apagar as de homologação.
 */
class AgtProdutorAmbienteTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sem ficheiros herdados de outros testes.
        foreach (['saft/public_key.pem', 'saft/private_key.pem'] as $f) {
            Storage::disk('local')->delete($f);
        }

        Storage::disk('local')->deleteDirectory('saft/sandbox');
        Storage::disk('local')->deleteDirectory('saft/production');
    }

    private function escreverChaves(string $pasta): void
    {
        Storage::disk('local')->put($pasta . '/public_key.pem', "-- publica {$pasta} --");
        Storage::disk('local')->put($pasta . '/private_key.pem', "-- privada {$pasta} --");
    }

    /** Só o super admin da plataforma abre este ecrã. */
    private function comoSuperAdmin(): \Livewire\Features\SupportTesting\Testable
    {
        $this->user->forceFill(['is_super_admin' => true])->save();

        // O ecrã abre no separador de facturação; a secção AGT está no seu.
        return \Livewire\Livewire::actingAs($this->user)
            ->test(\App\Livewire\SuperAdmin\SoftwareSettings::class)
            ->set('activeModule', 'agt');
    }

    public function test_trocar_de_ambiente_troca_o_username_no_ecra(): void
    {
        // O campo não mudava ao trocar o seletor: mostrava o username do
        // recurso partilhado nos dois lados. Ficava um username de homologação
        // debaixo do rótulo "Produção", com ar de configurado.
        config([
            'services.agt.username'            => 'legado',
            'services.agt.password'            => 'legado',
            'services.agt.sandbox.username'    => 'ws.hml.Empresa',
            'services.agt.sandbox.password'    => 'p-hml',
            'services.agt.production.username' => 'ws.prd.Empresa',
            'services.agt.production.password' => 'p-prd',
        ]);

        $this->comoSuperAdmin()
            ->assertSet('agt_basic_username', 'ws.hml.Empresa')
            ->set('produtorAmbiente', 'production')
            ->assertSet('agt_basic_username', 'ws.prd.Empresa');
    }

    public function test_ambiente_sem_credenciais_proprias_mostra_campo_vazio(): void
    {
        // Vazio, não o username partilhado: pré-preencher com o do outro
        // ambiente é que fazia parecer que já estava configurado.
        config([
            'services.agt.username'            => 'ws.hml.Empresa',
            'services.agt.password'            => 'p-hml',
            'services.agt.sandbox.username'    => null,
            'services.agt.sandbox.password'    => null,
            'services.agt.production.username' => null,
            'services.agt.production.password' => null,
        ]);

        $this->comoSuperAdmin()
            ->set('produtorAmbiente', 'production')
            ->assertSet('agt_basic_username', '')
            ->assertSet('credenciaisProprias', false)
            // Continua a haver credenciais utilizáveis — as partilhadas.
            ->assertSet('hasGlobalCredentials', true);
    }

    public function test_nao_se_grava_username_sem_password_no_primeiro_ambiente(): void
    {
        // Sem password própria, gravar só o username deixava o ambiente a
        // autenticar-se com o username de um e a password do outro.
        config([
            'services.agt.username'            => 'legado',
            'services.agt.password'            => 'legado',
            'services.agt.production.username' => null,
            'services.agt.production.password' => null,
        ]);

        $this->comoSuperAdmin()
            ->set('produtorAmbiente', 'production')
            ->set('agt_basic_username', 'ws.prd.Empresa')
            ->set('agt_basic_password', '')
            ->call('saveAgtProducerCredentials')
            ->assertHasErrors('agt_basic_password');
    }

    public function test_o_ecra_nao_tem_campos_para_colar_a_chave(): void
    {
        // A chave do produtor já existe e é gerada fora daqui. Os campos só
        // convidavam a substituir, por engano, a chave que assina os documentos
        // de todas as empresas ao mesmo tempo.
        $this->escreverChaves('saft/sandbox');

        $this->comoSuperAdmin()
            ->assertDontSee('BEGIN PRIVATE KEY')
            ->assertDontSee('Instalar chave')
            ->assertDontSee('Substituir chave')
            // Mas continua a dizer qual está instalada.
            ->assertSee('Chave RSA do Produtor de Software')
            ->assertSet('hasProducerKeys', true);
    }

    public function test_diz_quando_nao_encontra_a_chave(): void
    {
        // "Instalada" com a chave ausente mandava procurar no sítio errado.
        $this->comoSuperAdmin()
            ->assertSet('hasProducerKeys', false)
            ->assertSee('Nenhuma chave encontrada');
    }

    public function test_cada_ambiente_usa_a_sua_chave(): void
    {
        $this->escreverChaves('saft/sandbox');
        $this->escreverChaves('saft/production');

        $this->assertSame('saft/sandbox/public_key.pem', AGTProducerStore::publicKeyPath('sandbox'));
        $this->assertSame('saft/production/public_key.pem', AGTProducerStore::publicKeyPath('production'));

        $this->assertTrue(AGTProducerStore::temChavesProprias('sandbox'));
        $this->assertTrue(AGTProducerStore::temChavesProprias('production'));
    }

    public function test_instalar_um_ambiente_nao_apaga_o_outro(): void
    {
        // O problema de origem: pôr as de produção obrigava a apagar as de
        // homologação, e quem ainda testava ficava sem forma de o fazer.
        $this->escreverChaves('saft/sandbox');
        $this->escreverChaves('saft/production');

        Storage::disk('local')->put('saft/production/public_key.pem', '-- nova de producao --');

        $this->assertSame(
            '-- publica saft/sandbox --',
            Storage::disk('local')->get(AGTProducerStore::publicKeyPath('sandbox')),
            'a chave de homologação tem de ficar intacta'
        );
    }

    public function test_sem_chave_do_ambiente_recorre_a_legada(): void
    {
        // Deliberado: o par legado pode ser o de produção — é o que hoje assina
        // os documentos de todas as empresas. Assumir que é de testes e cortá-lo
        // partia a facturação de quem já está a funcionar.
        Storage::disk('local')->put('saft/public_key.pem', '-- legada --');
        Storage::disk('local')->put('saft/private_key.pem', '-- legada --');

        foreach (['sandbox', 'production'] as $ambiente) {
            $this->assertSame('saft/public_key.pem', AGTProducerStore::publicKeyPath($ambiente));
            $this->assertTrue(AGTProducerStore::temChaves($ambiente));
            $this->assertFalse(
                AGTProducerStore::temChavesProprias($ambiente),
                'tem de ficar claro que ainda não é a chave própria do ambiente'
            );
        }
    }

    public function test_a_chave_propria_ganha_a_legada(): void
    {
        Storage::disk('local')->put('saft/public_key.pem', '-- legada --');
        Storage::disk('local')->put('saft/private_key.pem', '-- legada --');
        $this->escreverChaves('saft/production');

        $this->assertSame('saft/production/public_key.pem', AGTProducerStore::publicKeyPath('production'));
        $this->assertSame('saft/public_key.pem', AGTProducerStore::publicKeyPath('sandbox'));
    }

    public function test_cada_ambiente_usa_as_suas_credenciais(): void
    {
        config([
            'services.agt.sandbox.username'    => 'u-sandbox',
            'services.agt.sandbox.password'    => 'p-sandbox',
            'services.agt.production.username' => 'u-producao',
            'services.agt.production.password' => 'p-producao',
        ]);

        $this->assertSame('u-sandbox', AGTProducerStore::credenciais('sandbox')['username']);
        $this->assertSame('u-producao', AGTProducerStore::credenciais('production')['username']);
        $this->assertTrue(AGTProducerStore::credenciais('production')['proprias']);
    }

    public function test_sem_credenciais_do_ambiente_recorre_as_legadas(): void
    {
        config([
            'services.agt.username' => 'u-legado',
            'services.agt.password' => 'p-legado',
            'services.agt.sandbox.username'    => null,
            'services.agt.sandbox.password'    => null,
            'services.agt.production.username' => null,
            'services.agt.production.password' => null,
        ]);

        foreach (['sandbox', 'production'] as $ambiente) {
            $c = AGTProducerStore::credenciais($ambiente);

            $this->assertSame('u-legado', $c['username']);
            $this->assertFalse($c['proprias'], 'tem de ficar claro que ainda são as partilhadas');
        }
    }

    public function test_metade_das_credenciais_nao_conta_como_configurado(): void
    {
        // Um username sem palavra-passe autentica-se com uma password vazia e
        // leva 401 — pior do que dizer que não está configurado.
        config([
            'services.agt.username' => '',
            'services.agt.password' => '',
            'services.agt.production.username' => 'u-producao',
            'services.agt.production.password' => null,
        ]);

        $this->assertFalse(AGTProducerStore::temCredenciais('production'));
    }

    public function test_o_ambiente_da_empresa_decide_o_conjunto(): void
    {
        $definicoes = \App\Models\Invoicing\InvoicingSettings::forTenant($this->tenant->id);
        $definicoes->update(['agt_environment' => 'production']);

        $this->assertSame('production', AGTProducerStore::ambienteDaEmpresa($this->tenant->id));

        $definicoes->update(['agt_environment' => 'sandbox']);

        $this->assertSame('sandbox', AGTProducerStore::ambienteDaEmpresa($this->tenant->id));
    }

    public function test_um_ambiente_desconhecido_cai_em_homologacao(): void
    {
        // Nunca em produção: um valor inesperado não pode fazer o sistema
        // começar a falar com a AGT real.
        $this->assertSame('sandbox', AGTProducerStore::normalizar('qualquer-coisa'));
        $this->assertSame('sandbox', AGTProducerStore::normalizar(null));
        $this->assertSame('sandbox', AGTProducerStore::ambienteDaEmpresa(null));
    }
}
