<?php

namespace Tests\Feature;

use App\Models\AGT\AGTSubmission;
use App\Models\AuditTrail;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AgtDeEnsaio;
use Tests\TenantTestCase;

/**
 * «REPOR E REENVIAR» SÓ QUANDO O «REENVIAR» JÁ NÃO CHEGA.
 *
 * O botão zerava o contador de qualquer submissão que não estivesse validada.
 * Numa `submitted` isso era mandar outra vez um documento que a AGT ainda está
 * a decidir; numa cancelada, ressuscitá-la; e numa com tentativas por gastar,
 * apagar o rasto das que falharam. A regra é uma só, do modelo, e o `estado`
 * diz ao ecrã o que pode oferecer em cada linha.
 */
class AgtReporRegrasTest extends TenantTestCase
{
    use AgtDeEnsaio;

    private const RAIZ = '/api/v1/invoicing/react/agt';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::fake(['*' => Http::response(['requestID' => 'x', 'resultCode' => '0'], 200)]);

        $this->comModulo('invoicing')->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $this->emitirEm('sandbox');
    }

    private function repor(AGTSubmission $s)
    {
        return $this->postJson(self::RAIZ . '/submissoes/' . $s->id . '/reenviar', ['ambiente' => 'sandbox', 'repor' => true]);
    }

    public function test_uma_submitted_nao_se_repoe_nem_reenvia(): void
    {
        $s = $this->submissaoAgt(['status' => AGTSubmission::STATUS_SUBMITTED, 'retry_count' => 3, 'agt_reference' => 'R']);

        $this->assertStringContainsString('a processar — actualize o estado', $this->repor($s)->assertStatus(422)->json('message'));
        $this->postJson(self::RAIZ . '/submissoes/' . $s->id . '/reenviar', ['ambiente' => 'sandbox'])->assertStatus(422);

        $this->assertSame(3, (int) $s->fresh()->retry_count);
        $this->assertSame(AGTSubmission::STATUS_SUBMITTED, $s->fresh()->status);
    }

    public function test_uma_cancelada_nao_se_repoe(): void
    {
        $s = $this->submissaoAgt(['status' => AGTSubmission::STATUS_CANCELLED, 'retry_count' => 4]);

        $this->repor($s)->assertStatus(422);

        $this->assertSame(4, (int) $s->fresh()->retry_count);
        $this->assertSame(AGTSubmission::STATUS_CANCELLED, $s->fresh()->status);
    }

    public function test_com_tentativas_por_gastar_nao_se_repoe(): void
    {
        $s = $this->submissaoAgt(['status' => AGTSubmission::STATUS_REJECTED, 'retry_count' => AGTSubmission::MAX_TENTATIVAS_DO_BOTAO - 1, 'error_code' => 'E43']);

        $this->assertStringContainsString('Reenviar', $this->repor($s)->assertStatus(422)->json('message'));

        $this->assertSame(2, (int) $s->fresh()->retry_count);
        $this->assertSame('E43', $s->fresh()->error_code, 'o rasto da recusa fica');
    }

    public function test_esgotada_pendente_ou_recusada_repoe_e_fica_na_trilha(): void
    {
        foreach ([AGTSubmission::STATUS_PENDING, AGTSubmission::STATUS_REJECTED] as $estado) {
            $s = $this->submissaoAgt(['status' => $estado, 'retry_count' => AGTSubmission::MAX_TENTATIVAS_DO_BOTAO, 'error_code' => 'E70']);

            // O documento do ensaio não existe: o reenvio pára aí, mas a
            // reposição já está feita — é o que o botão promete.
            $this->repor($s)->assertStatus(422);

            $this->assertSame(0, (int) $s->fresh()->retry_count, "repõe uma {$estado} esgotada");
            $this->assertSame(AGTSubmission::STATUS_PENDING, $s->fresh()->status);
        }

        app(AuditRecorder::class)->despejar();
        $linha = AuditTrail::where('tenant_id', $this->tenant->id)->where('event', 'agt.submissao.reposta')->latest('id')->first();

        $this->assertNotNull($linha);
        $this->assertSame(AGTSubmission::MAX_TENTATIVAS_DO_BOTAO, $linha->metadata['antes']['retry_count']);
        $this->assertSame('E70', $linha->metadata['antes']['error_code']);
    }

    public function test_o_estado_diz_o_que_cada_linha_pode(): void
    {
        $max = AGTSubmission::MAX_TENTATIVAS_DO_BOTAO;

        $casos = [
            'por enviar' => [$this->submissaoAgt(['retry_count' => 0]), true, false],
            'recusada com tentativas' => [$this->submissaoAgt(['status' => AGTSubmission::STATUS_REJECTED, 'retry_count' => $max - 1]), true, false],
            'recusada esgotada' => [$this->submissaoAgt(['status' => AGTSubmission::STATUS_REJECTED, 'retry_count' => $max]), false, true],
            'enviada' => [$this->submissaoAgt(['status' => AGTSubmission::STATUS_SUBMITTED, 'retry_count' => $max]), false, false],
            'cancelada' => [$this->submissaoAgt(['status' => AGTSubmission::STATUS_CANCELLED, 'retry_count' => $max]), false, false],
            'validada' => [$this->submissaoAgt(['status' => AGTSubmission::STATUS_VALIDATED, 'retry_count' => 1]), false, false],
        ];

        $linhas = collect($this->getJson(self::RAIZ . '/estado?ambiente=sandbox')->assertOk()->json('submissoes'));

        foreach ($casos as $nome => [$s, $reenviar, $repor]) {
            $linha = $linhas->firstWhere('id', $s->id);
            $this->assertSame($reenviar, $linha['pode_reenviar'], "pode_reenviar: {$nome}");
            $this->assertSame($repor, $linha['pode_repor'], "pode_repor: {$nome}");
            $this->assertSame($max, $linha['tentativas_max']);
        }
    }
}
