<?php

namespace Tests\Feature;

use App\Models\AGT\AGTSubmission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AgtDeEnsaio;
use Tests\TenantTestCase;

/**
 * LIMITES NAS PORTAS QUE FALAM COM A AGT, E UM CALLBACK QUE NÃO DENUNCIA NADA.
 *
 *  · Cada botão que vai à AGT usa as credenciais do produtor — as de todas as
 *    empresas. Um script, ou o botão carregado em ciclo, punha a AGT a
 *    responder 429 a toda a gente. Limite por empresa e utilizador.
 *  · O callback é público e sem assinatura: responde o MESMO a um requestID
 *    que existe e a um inventado (senão servia para os descobrir), e tem
 *    limite por IP.
 */
class AgtLimitesECallbackTest extends TenantTestCase
{
    use AgtDeEnsaio;

    private const RAIZ = '/api/v1/invoicing/react/agt';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('local');
        Http::fake(['*' => Http::response(['requestID' => 'x', 'resultCode' => '0'], 200)]);
    }

    public function test_as_accoes_que_falam_com_a_agt_tem_limite_por_empresa_e_utilizador(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        config(['services.agt.limites.comunicacao_por_minuto' => 3]);

        // Sem produtor nem chaves o pedido pára antes da AGT (422): o que se
        // mede aqui é a porta, não a AGT.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(self::RAIZ . '/ligacao', ['ambiente' => 'sandbox'])->assertStatus(422);
        }

        $r = $this->postJson(self::RAIZ . '/ligacao', ['ambiente' => 'sandbox'])->assertStatus(429);
        $this->assertStringContainsString('Demasiados pedidos', $r->json('message'));

        // A quota é partilhada pelas acções de comunicação, não uma por botão.
        $this->postJson(self::RAIZ . '/consulta', ['ambiente' => 'sandbox', 'apiOperation' => 'obterEstado', 'apiRequestId' => '1'])->assertStatus(429);
        $this->postJson(self::RAIZ . '/submissoes/actualizar')->assertStatus(429);

        // Ler o estado não tem este limite.
        $this->getJson(self::RAIZ . '/estado')->assertOk();
    }

    public function test_o_callback_responde_o_mesmo_exista_ou_nao_o_request_id(): void
    {
        $this->submissaoAgt(['status' => AGTSubmission::STATUS_SUBMITTED, 'agt_reference' => 'REQ-VERDADEIRO']);

        $existe = $this->postJson('/api/facturacaoelectronica/callback', ['requestID' => 'REQ-VERDADEIRO', 'resultCode' => '0']);
        $naoExiste = $this->postJson('/api/facturacaoelectronica/callback', ['requestID' => 'REQ-INVENTADO', 'resultCode' => '0']);

        $this->assertSame($existe->status(), $naoExiste->status());
        $this->assertSame($existe->json(), $naoExiste->json());

        // E continua a não decidir nada.
        $this->assertSame(AGTSubmission::STATUS_SUBMITTED, AGTSubmission::where('agt_reference', 'REQ-VERDADEIRO')->value('status'));
    }

    public function test_o_callback_tem_limite_por_ip(): void
    {
        // A contagem começa limpa: o Cache::flush() do setUp leva os contadores.
        config(['services.agt.limites.callback_por_minuto' => 2]);

        $this->postJson('/api/facturacaoelectronica/callback', ['requestID' => 'A'])->assertStatus(202);
        $this->postJson('/api/facturacaoelectronica/callback', ['requestID' => 'B'])->assertStatus(202);
        $this->postJson('/api/facturacaoelectronica/callback', ['requestID' => 'C'])->assertStatus(429);
    }
}
