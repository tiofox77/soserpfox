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
            'saldo' => $this->porReceber(),

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
            'pode_receber' => $this->porReceber() > 0.01,
            'e_rascunho' => $this->status === 'draft',

            /*
             * EDITAR E ELIMINAR SÓ ANTES DE SER DOCUMENTO FISCAL.
             *
             * Não basta estar em rascunho: `invoice_status === 'F'` quer dizer
             * FINALIZADA — assinada e com número de série. Um documento fiscal
             * emitido não se corrige nem se apaga, rectifica-se por Nota de
             * Crédito (Decreto 71/25). O ecrã de sempre exigia as duas
             * condições e é isso que aqui se repete, decidido de uma vez em
             * vez de o browser as voltar a juntar.
             */
            'pode_editar' => $this->status === 'draft' && $this->invoice_status !== 'F',

            /*
             * E NÃO SE APAGA O QUE JÁ RECEBEU DINHEIRO. Um pagamento aponta
             * para a factura; apagá-la deixava o movimento de tesouraria a
             * apontar para o nada.
             *
             * AQUI SÓ O QUE SE SABE SEM PERGUNTAR À BASE. Procurar movimentos
             * de tesouraria por linha seriam cem consultas numa página de cem
             * facturas. Essa verificação vive no endpoint, que é onde tem de
             * viver: um botão apagado nunca foi segurança.
             */
            'pode_apagar' => $this->status === 'draft'
                && $this->invoice_status !== 'F'
                && (float) ($this->paid_amount ?? 0) <= 0,
        ];
    }

    /**
     * Quanto falta receber MESMO.
     *
     * `total - paid_amount` não chega, e o ecrã em React mostrou porquê ao
     * primeiro olhar: as facturas-recibo do balcão apareciam «Pago» e a dizer
     * «falta 570,00» ao lado.
     *
     * UMA FR É PAGA NO ACTO DA VENDA, POR DEFINIÇÃO. Não tem recibo nenhum
     * porque não precisa de um, e por isso o `paid_amount` fica em zero para
     * sempre — na bancada são 124 documentos assim. O mesmo vale para o que
     * está `draft`, `paid`, `cancelled` ou `credited`: não há nada a
     * receber, seja o que for que a coluna diga.
     *
     * É a mesma regra que a lista Livewire já aplica ao botão de pagamento
     * (`$__linhaFR`), trazida para o único sítio onde agora se decide.
     */
    private function porReceber(): float
    {
        if (($this->invoice_type ?? 'FT') === 'FR') {
            return 0.0;
        }

        /*
         * O RASCUNHO ENTROU NESTA LISTA, e não é engano: uma factura em
         * rascunho ainda não foi emitida a ninguém. Não deve nada — e a
         * API dos recibos recusa-a, por isso oferecer «Receber» num
         * rascunho levava a um ecrã que não a conseguia sequer escolher.
         */
        if (in_array($this->status, ['draft', 'paid', 'cancelled', 'credited'], true)) {
            return 0.0;
        }

        return max(0.0, round((float) $this->total - (float) $this->paid_amount, 2));
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
