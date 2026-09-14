<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Services\AGT\AGTKeyStore;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AgtDeEnsaio;
use Tests\TenantTestCase;

/**
 * AS CHAVES QUE ESTÃO A ASSINAR NÃO SE TROCAM NEM APAGAM COM UM CLIQUE.
 *
 * Remover o par do ambiente ACTIVO pára a facturação no instante seguinte, e
 * substituí-lo por um errado põe cada factura a sair com uma assinatura que a
 * AGT não conhece. Pede-se `confirmar: true`; no outro ambiente não. E fica na
 * trilha com a impressão digital da chave PÚBLICA — a chave, nunca.
 */
class AgtChavesConfirmacaoTest extends TenantTestCase
{
    use AgtDeEnsaio;

    private const RAIZ = '/api/v1/invoicing/react/agt';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::fake(['*' => Http::response(['requestID' => 'x', 'resultCode' => '0'], 200)]);

        $this->comModulo('invoicing')->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $this->emitirEm('production');
    }

    private function trilha(string $evento)
    {
        app(AuditRecorder::class)->despejar();

        return AuditTrail::where('tenant_id', $this->tenant->id)->where('event', $evento)->latest('id')->first();
    }

    public function test_remover_as_chaves_do_activo_pede_confirmar_e_fica_na_trilha(): void
    {
        $this->instalarChavesDoContribuinte('production');
        $impressao = hash('sha256', Storage::disk('local')->get(AGTKeyStore::publicKeyPath($this->tenant->id, 'production')));

        $this->postJson(self::RAIZ . '/chaves/remover', ['ambiente' => 'production'])
            ->assertStatus(422)
            ->assertJsonPath('confirmar_necessario', true);
        $this->assertTrue(AGTKeyStore::hasKeyPair($this->tenant->id, 'production'), 'sem confirmar ficam');

        $this->postJson(self::RAIZ . '/chaves/remover', ['ambiente' => 'production', 'confirmar' => true])->assertOk();
        $this->assertFalse(AGTKeyStore::hasKeyPair($this->tenant->id, 'production'));

        $linha = $this->trilha('agt.chaves.removidas');
        $this->assertNotNull($linha);
        $this->assertSame($impressao, $linha->metadata['publica_sha256']);
        $this->assertTrue($linha->metadata['era_o_activo']);

        $gravado = json_encode($linha->metadata);
        $this->assertStringNotContainsString('PRIVATE KEY', $gravado);
        $this->assertStringNotContainsString('PUBLIC KEY', $gravado);
    }

    public function test_no_outro_ambiente_nao_se_pede_confirmacao(): void
    {
        $this->instalarChavesDoContribuinte('sandbox');

        $this->postJson(self::RAIZ . '/chaves/remover', ['ambiente' => 'sandbox'])->assertOk();

        $this->assertFalse(AGTKeyStore::hasKeyPair($this->tenant->id, 'sandbox'));
    }

    public function test_substituir_as_chaves_do_activo_pede_confirmar(): void
    {
        $pedido = [
            'ambiente' => 'production',
            'contributorPublicKey' => $this->publicaDeEnsaio(),
            'contributorPrivateKey' => self::$pemDeEnsaio,
        ];

        // A primeira instalação não substitui nada: não pede.
        $this->postJson(self::RAIZ . '/chaves', $pedido)->assertOk();

        $this->postJson(self::RAIZ . '/chaves', $pedido)->assertStatus(422)->assertJsonPath('confirmar_necessario', true);
        $this->postJson(self::RAIZ . '/chaves', $pedido + ['confirmar' => true])->assertOk();

        $linha = $this->trilha('agt.chaves.guardadas');
        $this->assertNotNull($linha);
        $this->assertTrue($linha->metadata['substituiu']);
        $this->assertSame(64, strlen($linha->metadata['publica_sha256']));
        $this->assertStringNotContainsString('PRIVATE KEY', json_encode($linha->metadata));
    }

    public function test_a_chave_do_modo_antigo_fica_na_trilha_sem_a_chave(): void
    {
        $this->postJson(self::RAIZ . '/contribuinte/chave', ['contributor_private_key' => self::$pemDeEnsaio])->assertOk();
        $this->postJson(self::RAIZ . '/contribuinte/chave/remover', ['confirmar' => true])->assertOk();

        $esperada = hash('sha256', $this->publicaDeEnsaio());

        foreach (['agt.chave_legado.guardada', 'agt.chave_legado.removida'] as $evento) {
            $linha = $this->trilha($evento);
            $this->assertNotNull($linha, $evento);
            $this->assertSame($esperada, $linha->metadata['publica_sha256']);
            $this->assertStringNotContainsString('PRIVATE KEY', json_encode($linha->metadata));
        }
    }
}
