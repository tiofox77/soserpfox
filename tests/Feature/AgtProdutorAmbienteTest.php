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

        // Disco falso, OBRIGATÓRIO aqui.
        //
        // A versão anterior apagava saft/*.pem no disco a sério para partir de
        // um estado limpo — e o disco a sério é o mesmo da instalação de
        // desenvolvimento. Correr a suite apagava as chaves do produtor de quem
        // estava a trabalhar, e o ecrã passava a dizer "Em falta" sem que nada
        // no código tivesse mudado. Nenhum teste tem negócio nenhum a escrever
        // em storage/app/private.
        Storage::fake('local');
    }

    private function escreverChaves(string $pasta): void
    {
        Storage::disk('local')->put($pasta . '/public_key.pem', "-- publica {$pasta} --");
        Storage::disk('local')->put($pasta . '/private_key.pem', "-- privada {$pasta} --");
    }

    /**
     * O ecrã passou a React: o que se prova é o que a API lhe entrega, que é o
     * que o ecrã desenha — o utilizador PRÓPRIO no campo, o herdado à parte.
     */
    private function produtor(string $ambiente = 'sandbox'): array
    {
        $this->user->forceFill(['is_super_admin' => true])->save();

        return $this->actingAs($this->user->fresh())
            ->getJson('/api/v1/plataforma/react/software?ambiente=' . $ambiente)
            ->assertOk()->json('produtor');
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

        $this->assertSame('ws.hml.Empresa', $this->produtor('sandbox')['username']);
        $this->assertSame('ws.prd.Empresa', $this->produtor('production')['username']);
    }

    public function test_a_resposta_diz_de_que_ambiente_e(): void
    {
        // O <select> não trazia `selected` e o browser caía na primeira opção:
        // o seletor dizia "Homologação" com as credenciais de produção. Em React
        // o estado é do ecrã, e a resposta diz sempre a que ambiente pertence.
        $this->assertSame('production', $this->produtor('production')['ambiente']);
        $this->assertSame('sandbox', $this->produtor('sandbox')['ambiente']);
        $this->assertSame('sandbox', $this->produtor('inventado')['ambiente'], 'um ambiente desconhecido cai em homologação');
    }

    public function test_o_badge_distingue_proprias_de_partilhadas(): void
    {
        // Dizia "Configuradas" por causa do recurso às partilhadas, mesmo ao
        // lado do aviso de que este ambiente não tinha credenciais próprias.
        config([
            'services.agt.username'            => 'legado',
            'services.agt.password'            => 'legado',
            'services.agt.production.username' => null,
            'services.agt.production.password' => null,
            'services.agt.sandbox.username'    => 'ws.hml.Empresa',
            'services.agt.sandbox.password'    => 'p-hml',
        ]);

        $this->assertTrue($this->produtor('sandbox')['credenciais_proprias']);
        $this->assertFalse($this->produtor('production')['credenciais_proprias']);
        $this->assertTrue($this->produtor('production')['tem_credenciais'], 'as partilhadas continuam utilizáveis');
    }

    public function test_sem_credenciais_nenhumas_diz_nao_configuradas(): void
    {
        config([
            'services.agt.username'            => null,
            'services.agt.password'            => null,
            'services.agt.sandbox.username'    => null,
            'services.agt.sandbox.password'    => null,
            'services.agt.production.username' => null,
            'services.agt.production.password' => null,
        ]);

        $this->assertFalse($this->produtor()['tem_credenciais']);
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

        $p = $this->produtor('production');

        $this->assertSame('', $p['username']);
        $this->assertFalse($p['credenciais_proprias']);
        // Continua a haver credenciais utilizáveis — as partilhadas.
        $this->assertTrue($p['tem_credenciais']);
        // E tem de continuar a ver-se qual é: campo vazio sem mais nada fazia
        // parecer que a credencial se tinha perdido.
        $this->assertSame('ws.hml.Empresa', $p['username_herdado']);
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

        $this->produtor();

        $this->putJson('/api/v1/plataforma/react/software/produtor', [
            'ambiente' => 'production', 'username' => 'ws.prd.Empresa', 'password' => '',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    /**
     * GRAVAR ESCREVE NO `.env` — e um ensaio nunca pode escrever no verdadeiro.
     *
     * O ficheiro vem de `environmentFilePath()`: aponta-se para um temporário.
     */
    public function test_gravar_as_credenciais_escreve_no_ficheiro_de_ambiente(): void
    {
        $pasta = sys_get_temp_dir() . '/soserp-env-' . uniqid();
        mkdir($pasta);
        file_put_contents($pasta . '/.env', "APP_NAME=Ensaio\nAGT_PRODUCTION_API_USERNAME=antigo\n");

        $original = app()->environmentPath();
        app()->useEnvironmentPath($pasta);

        try {
            $this->produtor();

            $this->putJson('/api/v1/plataforma/react/software/produtor', [
                'ambiente' => 'production', 'username' => 'ws.prd.Nova', 'password' => 'segredo "com" aspas',
            ])->assertOk();

            $env = file_get_contents($pasta . '/.env');

            $this->assertStringContainsString('AGT_PRODUCTION_API_USERNAME="ws.prd.Nova"', $env);
            $this->assertStringContainsString('AGT_PRODUCTION_API_PASSWORD="segredo \"com\" aspas"', $env);
            $this->assertStringContainsString('APP_NAME=Ensaio', $env, 'o resto do ficheiro fica');
            $this->assertSame(1, substr_count($env, 'AGT_PRODUCTION_API_USERNAME='), 'substitui, não duplica');
        } finally {
            app()->useEnvironmentPath($original);
            @unlink($pasta . '/.env');
            @rmdir($pasta);
        }
    }

    /** Uma quebra de linha no utilizador escrevia uma variável nova no `.env`. */
    public function test_uma_quebra_de_linha_e_recusada(): void
    {
        $this->produtor();

        $this->putJson('/api/v1/plataforma/react/software/produtor', [
            'ambiente' => 'sandbox', 'username' => "ws.hml\nAPP_DEBUG=true", 'password' => 'x',
        ])->assertStatus(422)->assertJsonValidationErrors('username');
    }

    public function test_o_ecra_nao_recebe_a_chave(): void
    {
        // A chave do produtor já existe e é gerada fora daqui. O ecrã só diz
        // qual está instalada — nunca a recebe, nem a pública inteira.
        $this->escreverChaves('saft/sandbox');

        $p = $this->produtor();

        $this->assertNotNull($p['chave']);
        $this->assertStringNotContainsString('privada', json_encode($p));
        $this->assertStringNotContainsString('-- publica', json_encode($p));
    }

    public function test_diz_quando_nao_encontra_a_chave(): void
    {
        // "Instalada" com a chave ausente mandava procurar no sítio errado.
        $this->assertNull($this->produtor()['chave']);
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

    public function test_os_dois_ambientes_podem_usar_a_chave_certificada_do_produtor(): void
    {
        Storage::disk('local')->put('saft/public_key.pem', '-- legada --');
        Storage::disk('local')->put('saft/private_key.pem', '-- legada --');

        foreach (['sandbox', 'production'] as $ambiente) {
            $this->assertSame('saft/public_key.pem', AGTProducerStore::publicKeyPath($ambiente));
            $this->assertTrue(AGTProducerStore::temChaves($ambiente));
            $this->assertFalse(AGTProducerStore::temChavesProprias($ambiente));
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
