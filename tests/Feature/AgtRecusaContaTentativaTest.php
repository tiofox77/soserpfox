<?php

namespace Tests\Feature;

use App\Models\AGT\AGTSubmission;
use App\Services\AGT\AGTService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AgtDeEnsaio;
use Tests\TenantTestCase;

/**
 * UMA RECUSA DA AGT CONTA TENTATIVA — UMA VEZ — E GUARDA O CÓDIGO DELA.
 *
 * Dois defeitos juntos no mesmo caminho:
 *
 *  · o RegisterService não passava o estado HTTP e o AGTService lia sempre 0:
 *    TODAS as recusas eram tomadas por falha de rede. Um E43 ficava «pendente»
 *    com o código COMMS e voltava a ser enviado, igual, até esgotar;
 *  · a recusa gravava «AGT_REGISTER» / «RESULT_CODE_2», e o código da AGT
 *    (E43, E70…) — o que diz o que corrigir — só se via na resposta em bruto.
 *
 * E a contagem: um envio conta uma tentativa. A recusa que chega depois, pela
 * consulta do estado, é o desfecho de um envio já contado — não conta outra.
 */
class AgtRecusaContaTentativaTest extends TenantTestCase
{
    use AgtDeEnsaio;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('local');

        $this->emitirEm('sandbox');
        $this->instalarChavesDoContribuinte('sandbox');
        $this->instalarProdutor('sandbox');
    }

    private function submissaoDe($factura): AGTSubmission
    {
        return AGTSubmission::where('tenant_id', $this->tenant->id)
            ->where('document_id', $factura->id)
            ->latest('id')
            ->firstOrFail();
    }

    public function test_a_recusa_na_hora_fica_recusada_com_o_codigo_e_conta_uma_tentativa(): void
    {
        Http::fake(['*/registarFactura' => Http::response([
            'requestID' => null,
            'errorList' => [['idError' => 'E43', 'descriptionError' => 'A soma dos valores a anular excede o montante ainda não anulado (1.234,00).']],
        ], 200)]);

        $factura = $this->facturaDeEnsaio();
        $r = (new AGTService($this->tenant->id))->submitToAGT($factura);

        $this->assertFalse($r['success']);
        $s = $this->submissaoDe($factura);
        $this->assertSame(AGTSubmission::STATUS_REJECTED, $s->status, 'uma recusa da AGT não é falha de rede');
        $this->assertSame('E43', $s->error_code);
        $this->assertSame(1, (int) $s->retry_count);
        // A descrição DA AGT, com o valor em causa — e a explicação ao lado.
        $this->assertStringContainsString('1.234,00', $s->error_message);
        $this->assertStringContainsString('[E43]', $s->error_message);

        // Um segundo envio é outra tentativa, e só uma.
        (new AGTService($this->tenant->id))->submitToAGT($factura->fresh());
        $this->assertSame(2, (int) $s->fresh()->retry_count);
    }

    public function test_sem_resposta_do_documento_continua_por_repetir_e_conta(): void
    {
        Http::fake(['*/registarFactura' => Http::response('', 503)]);

        $factura = $this->facturaDeEnsaio();
        (new AGTService($this->tenant->id))->submitToAGT($factura);

        $s = $this->submissaoDe($factura);
        $this->assertSame(AGTSubmission::STATUS_PENDING, $s->status);
        $this->assertSame('COMMS', $s->error_code);
        $this->assertSame(1, (int) $s->retry_count);
    }

    public function test_a_recusa_pela_consulta_le_os_erros_do_documento_e_nao_conta_a_dobrar(): void
    {
        Http::fake([
            '*/registarFactura' => Http::response(['requestID' => 'REQ-77', 'errorList' => []], 200),
            '*/obterEstado' => Http::response([
                'requestID' => 'REQ-77',
                'resultCode' => '2',
                'requestErrorList' => [],
                'documentStatusList' => [[
                    'documentNo' => 'FT S/000001',
                    'documentStatus' => 'I',
                    'errorList' => [['idError' => 'E70', 'descriptionError' => 'Valor do imposto "taxContribution" da linha (1) não corresponde ao imposto apurado (140,01).']],
                ]],
            ], 200),
        ]);

        $factura = $this->facturaDeEnsaio();
        // Com a fila síncrona dos ensaios, a consulta do estado corre logo a seguir ao envio.
        (new AGTService($this->tenant->id))->submitToAGT($factura);

        $s = $this->submissaoDe($factura);
        $this->assertSame(AGTSubmission::STATUS_REJECTED, $s->status);
        $this->assertSame('E70', $s->error_code, 'o código vem de documentStatusList[].errorList');
        $this->assertStringContainsString('140,01', $s->error_message);
        $this->assertSame(1, (int) $s->retry_count, 'o envio contou; o veredicto que chega depois não conta outra vez');
    }
}
