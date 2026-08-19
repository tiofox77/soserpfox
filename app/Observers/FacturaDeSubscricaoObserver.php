<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\Plataforma\RenovacaoDeSubscricoes;
use Illuminate\Support\Facades\Log;

/**
 * Uma factura de subscrição que passa a PAGA estende a subscrição.
 *
 * É esta a peça que fecha o ciclo de facturação da plataforma: a renovação
 * emite a conta do período seguinte, e é o pagamento — não a emissão — que dá
 * os dias novos.
 *
 * Aqui e não no ecrã do Billing de propósito: uma factura pode ser dada como
 * paga por vários caminhos (o botão do painel, a edição da factura, um
 * comando, a API), e todos têm de levar ao mesmo sítio. O serviço é
 * idempotente, portanto passar por aqui duas vezes não soma dois períodos.
 *
 * NÃO é o InvoiceObserver, que existe ao lado e está deliberadamente por
 * registar: esse cria lançamentos contabilísticos nos livros da EMPRESA, e uma
 * factura de subscrição é dinheiro que a empresa paga à plataforma — não tem
 * nada que fazer na contabilidade dela. Este observer só olha para facturas
 * com subscrição associada e não toca em contabilidade nenhuma.
 */
class FacturaDeSubscricaoObserver
{
    public function updated(Invoice $factura): void
    {
        if (!$factura->subscription_id) {
            return;
        }

        // Só a MUDANÇA para paga interessa. Sem isto, qualquer gravação de uma
        // factura já paga voltava a chamar o serviço.
        if (!$factura->wasChanged('status') || $factura->status !== 'paid') {
            return;
        }

        try {
            app(RenovacaoDeSubscricoes::class)->aplicarPagamento($factura);
        } catch (\Throwable $e) {
            // O pagamento fica registado de qualquer maneira; o que falha é a
            // extensão automática, e essa dá-se à mão no painel.
            Log::error('Renovação: falha ao estender subscrição após pagamento', [
                'invoice_id' => $factura->id,
                'erro'       => $e->getMessage(),
            ]);
        }
    }
}
