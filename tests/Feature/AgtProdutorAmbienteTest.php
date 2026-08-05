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
