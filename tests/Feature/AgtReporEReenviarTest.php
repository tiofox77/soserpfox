<?php

namespace Tests\Feature;

use App\Models\AGT\AGTSubmission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * Submissão com as tentativas gastas: o ecrã dá o botão «Repor e reenviar».
 *
 * 2026-09-02: a Free Dation ficou com a FT …/000002 pendente, 5 tentativas
 * gastas pela recusa do schema 1.2, e o ecrã mostrava «—» em vez de um
 * botão — não havia maneira de reenviar sem ir à base de dados.
 *
 * O botão é hoje um `POST /agt/submissoes/{id}/reenviar` com `repor`. O que
 * este ficheiro guarda, e mais nenhum, é o LIMITE: uma submissão JÁ VALIDADA
 * pela AGT não se repõe. Reenviar um documento que a AGT aceitou é pedir-lhe
 * que o registe duas vezes. O resto — repor zera o contador, e não se toca na
 * submissão de outra empresa — está no `ApiDaAgtParaReactTest`.
 */
class AgtReporEReenviarTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/agt';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');

        // Nada sai para a AGT a partir de um ensaio, e nada se escreve no
        // disco a sério.
        Storage::fake('local');
        Http::fake(['*' => Http::response(['requestID' => 'x', 'resultCode' => '0'], 200)]);
    }

    private function ambiente(): string
    {
        return \App\Models\Invoicing\InvoicingSettings::forTenant($this->tenant->id)->agt_environment ?: 'sandbox';
    }

    private function submissao(string $estado, int $tentativas): AGTSubmission
    {
        return AGTSubmission::create([
            'tenant_id' => $this->tenant->id,
            'agt_environment' => $this->ambiente(),
            'document_type' => 'App\\Models\\Invoicing\\SalesInvoice',
            'document_id' => 999999999,
            'document_number' => 'FT A/000002',
            'document_type_code' => 'FT',
            'status' => $estado,
            'retry_count' => $tentativas,
            'error_code' => 'COMMS',
            'error_message' => 'A versão 1.2 do schema já não é suportada.',
        ]);
    }

    /**
     * A LINHA DIZ AO ECRÃ O QUE PODE FAZER.
     *
     * Era o ecrã a decidir a partir do estado, e uma esgotada ficava com «—».
     * Hoje é o servidor que marca cada linha: quem pode reenviar, e quem está
     * esgotada e só sai daí com uma reposição.
     *
     * @test
     */
    public function uma_esgotada_pede_reposicao_e_uma_validada_nao_se_toca(): void
    {
        $esgotada = $this->submissao(AGTSubmission::STATUS_PENDING, 5);
        $validada = $this->submissao(AGTSubmission::STATUS_VALIDATED, 3);

        $linhas = collect($this->getJson(self::RAIZ . '/estado?ambiente=' . $this->ambiente())->assertOk()->json('submissoes'));

        $a = $linhas->firstWhere('id', $esgotada->id);
        $this->assertTrue($a['esgotada']);
        $this->assertFalse($a['pode_reenviar'], 'esgotada não se reenvia: primeiro repõe-se');

        $b = $linhas->firstWhere('id', $validada->id);
        $this->assertFalse($b['esgotada']);
        $this->assertFalse($b['pode_reenviar'], 'o que a AGT já validou não se reenvia de todo');
    }

    /**
     * REPOR NÃO REPÕE UMA VALIDADA.
     *
     * O botão zera o contador e volta a enviar. Numa submissão que a AGT já
     * aceitou, isso seria pedir-lhe que registasse o mesmo documento outra
     * vez — e o contador reposto apagaria a prova de que já lá estava.
     *
     * @test
     */
    public function nao_repoe_uma_validada(): void
    {
        $s = $this->submissao(AGTSubmission::STATUS_VALIDATED, 3);

        $r = $this->postJson(self::RAIZ . '/submissoes/' . $s->id . '/reenviar', [
            'ambiente' => $this->ambiente(), 'repor' => true,
        ])->assertStatus(422);

        $this->assertStringContainsString('já foi validado', $r->json('message'));

        $s->refresh();
        $this->assertSame(3, (int) $s->retry_count, 'o contador não se mexe');
        $this->assertSame(AGTSubmission::STATUS_VALIDATED, $s->status);
    }
}
