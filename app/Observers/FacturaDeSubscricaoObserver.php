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

        // Guardado ANTES, porque é a única forma de saber se o pagamento fez
        // alguma coisa: o aplicarPagamento devolve a mesma subscrição quer
        // tenha estendido o período, quer tenha desistido por já estar
        // estendido. Avisar pelo valor de retorno era dizer ao cliente que
        // renovou quando não renovou.
        $antes = $factura->subscription?->current_period_end?->toDateTimeString();

        try {
            $sub = app(RenovacaoDeSubscricoes::class)->aplicarPagamento($factura);
        } catch (\Throwable $e) {
            // O pagamento fica registado de qualquer maneira; o que falha é a
            // extensão automática, e essa dá-se à mão no painel.
            Log::error('Renovação: falha ao estender subscrição após pagamento', [
                'invoice_id' => $factura->id,
                'erro'       => $e->getMessage(),
            ]);

            return;
        }

        if (!$sub || $sub->current_period_end?->toDateTimeString() === $antes) {
            return;   // não houve período novo: não há nada a anunciar
        }

        // O aviso corre DEPOIS da resposta seguir para o browser: quem carregou
        // em "marcar como paga" não espera pelo servidor de email nem pela
        // operadora de SMS.
        defer(function () use ($factura, $sub) {
            try {
                app(\App\Services\Billing\AvisosDeSubscricao::class)->renovada($factura, $sub);
            } catch (\Throwable $e) {
                Log::error('Renovação: aviso de renovação falhou', [
                    'invoice_id' => $factura->id,
                    'erro'       => $e->getMessage(),
                ]);
            }
        });
    }
}
