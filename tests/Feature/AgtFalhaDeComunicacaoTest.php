<?php

namespace Tests\Feature;

use App\Models\AGT\AGTSubmission;
use Tests\TenantTestCase;

/**
 * Uma falha de rede não é uma recusa da AGT.
 *
 * Tudo o que não fosse sucesso virava `rejected`. Um
 * `cURL error 28: Resolving timed out` — o pedido nem chegou a sair — deixava
 * o documento marcado como recusado, fora da lista de pendentes, e nunca mais
 * era tentado. Ficava para sempre por comunicar, com o ecrã a dizer que a AGT
 * o tinha recusado (quando nem o viu) e a factura impressa a remeter para um
 * quiosque onde nunca ia aparecer.
 *
 * "Rejeitado" é um veredicto do fisco sobre o documento. Um DNS que não
 * resolve não diz nada sobre ele.
 */
class AgtFalhaDeComunicacaoTest extends TenantTestCase
{
    private function submissao(string $estado = 'pending'): AGTSubmission
    {
        return AGTSubmission::create([
            'tenant_id'          => $this->tenant->id,
            'document_type'      => \App\Models\Invoicing\SalesInvoice::class,
            'document_id'        => 1,
            'document_type_code' => 'FT',
            'document_number'    => 'SOS FT2026/000001',
            'status'             => $estado,
            'retry_count'        => 0,
        ]);
    }

    public function test_falha_de_rede_deixa_a_submissao_por_repetir(): void
    {
        $s = $this->submissao();

        $s->markAsCommunicationFailure('cURL error 28: Resolving timed out after 10002 milliseconds');

        $this->assertSame(AGTSubmission::STATUS_PENDING, $s->fresh()->status);
        $this->assertSame('COMMS', $s->fresh()->error_code);
        $this->assertStringContainsString('Resolving timed out', $s->fresh()->error_message);
    }

    public function test_continua_a_aparecer_como_por_enviar(): void
    {
        // O que interessa: continuar na lista de quem tem de ir. Marcada como
        // recusada, saía dela e o documento ficava fora da AGT para sempre.
        $s = $this->submissao();
        $s->markAsCommunicationFailure('ligação caiu');

        $this->assertTrue(
            AGTSubmission::pending()->where('id', $s->id)->exists(),
            'uma falha de comunicação tem de continuar pendente'
        );
    }

    public function test_conta_as_tentativas(): void
    {
        // Para se distinguir um soluço da rede de um problema persistente.
        $s = $this->submissao();

        $s->markAsCommunicationFailure('timeout');
        $s->markAsCommunicationFailure('timeout');

        $this->assertSame(2, (int) $s->fresh()->retry_count);
    }

    public function test_uma_recusa_da_agt_continua_a_ser_recusa(): void
    {
        // A distinção só serve se o outro lado se mantiver: quando a AGT
        // responde e recusa, isso é terminal e não se reenvia às cegas.
        $s = $this->submissao();

        $s->markAsRejected('E39', 'Dados do produtor fora do Processo de Certificação.', []);

        $this->assertSame(AGTSubmission::STATUS_REJECTED, $s->fresh()->status);
        $this->assertFalse(AGTSubmission::pending()->where('id', $s->id)->exists());
    }
}
