<?php

namespace App\Http\Resources\Invoicing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A forma de uma factura de venda quando sai daqui para o React.
 *
 * DUAS REGRAS QUE NÃO SE QUEBRAM:
 *
 * 1. NUNCA se devolve o modelo cru. Um `SalesInvoice::all()` numa resposta
 *    entrega colunas internas — o `jws_signature`, o `hash`, o `saft_hash` —
 *    e a partir daí qualquer coluna nova passa a estar publicada sem ninguém
 *    ter decidido isso. Aqui o que sai está escrito, uma linha de cada vez.
 *
 * 2. AS DECISÕES VÊM DECIDIDAS. O ecrã não recebe o estado para depois
 *    concluir se pode creditar: recebe `pode_creditar`, já resolvido pelo
 *    servidor, que é quem sabe. Quem manda continua deste lado — o React
 *    apenas mostra ou apaga um botão, e um botão apagado nunca foi segurança.
 */
class SalesInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Os dois números que a factura tem: o interno, que a empresa
            // reconhece, e o da AGT. Ver o NumeracaoInternaEAgt.
            'numero' => $this->numeroInterno(),
            'numero_agt' => $this->numeroAgt(),
            'tipo' => $this->invoice_type ?? 'FT',

            'cliente' => [
                'id' => $this->client?->id,
                'nome' => $this->client?->name ?? __('Consumidor Final'),
                'nif' => $this->client?->nif,
            ],

            'data' => optional($this->invoice_date)->toDateString(),
            'vencimento' => optional($this->due_date)->toDateString(),

            'estado' => $this->status,
            'estado_rotulo' => $this->status_label,
            'estado_cor' => $this->corDaEtiqueta(),

            'total' => round((float) $this->total, 2),
            'pago' => round((float) $this->paid_amount, 2),
            'saldo' => round((float) $this->total - (float) $this->paid_amount, 2),

            'agt' => [
                'comunicada' => (bool) $this->jws_signature,
                'rotulo' => $this->jws_signature
                    ? __('Emitida no Portal AGT')
                    : __('Por comunicar'),
            ],

            'armazem' => $this->warehouse?->name,
            'autor' => $this->creator?->name,

            // As decisões, já tomadas do lado de cá.
            'pode_creditar' => ! $this->jaTotalmenteCreditada(),
            'pode_receber' => round((float) $this->total - (float) $this->paid_amount, 2) > 0.01,
            'e_rascunho' => $this->status === 'draft',
        ];
    }

    /**
     * A cor da etiqueta, na linguagem do `ui/tokens.ts`.
     *
     * Sai daqui e não do React para que a lista, o PDF e um relatório futuro
     * digam a mesma coisa da mesma cor.
     */
    private function corDaEtiqueta(): string
    {
        return match ($this->status) {
            'paid' => 'bom',
            'draft' => 'neutra',
            'cancelled', 'credited' => 'perigo',
            'overdue' => 'aviso',
            default => 'primaria',
        };
    }
}
