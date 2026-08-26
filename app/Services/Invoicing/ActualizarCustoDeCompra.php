<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\PurchaseInvoiceItem;
use App\Models\Product;

/**
 * O preço a que se comprou passa a ser o custo do artigo.
 *
 * Quando se lança uma factura de compra com um preço novo, o artigo tinha de
 * ser corrigido à mão no catálogo — e ninguém se lembrava. O resultado era
 * margens calculadas sobre um custo de há meses.
 *
 * QUE VALOR: o preço da linha **depois do desconto da linha** e **sem
 * imposto**. Isto é, o que a mercadoria custou de facto. O IVA fica de fora
 * porque é dedutível para quem está no regime geral — somá-lo ao custo
 * inflaccionaria a margem de todos os artigos.
 *
 * QUANDO: no momento em que a mercadoria entra em stock, não quando o
 * rascunho é escrito. Um rascunho ainda pode ser corrigido ou deitado fora.
 */
class ActualizarCustoDeCompra
{
    /** @return int quantos artigos mudaram de custo */
    public function aplicar(PurchaseInvoice $factura): int
    {
        $factura->loadMissing('items.product');
        $mudados = 0;

        foreach ($factura->items as $linha) {
            if ($this->actualizarLinha($factura, $linha)) {
                $mudados++;
            }
        }

        return $mudados;
    }

    private function actualizarLinha(PurchaseInvoice $factura, PurchaseInvoiceItem $linha): bool
    {
        // Serviços não têm custo de armazém. Linhas livres (sem artigo do
        // catálogo) não têm onde escrever.
        if (!$linha->product_id || $linha->is_service) {
            return false;
        }

        $custo = $this->custoUnitario($linha);

        // Custo zero ou negativo não é um preço de compra — é um engano, uma
        // oferta ou uma linha por preencher. Apagar o custo bom por causa
        // disso seria pior do que não fazer nada.
        if ($custo === null || $custo <= 0) {
            return false;
        }

        /** @var Product|null $artigo */
        $artigo = $linha->product;
        if (!$artigo || (int) $artigo->tenant_id !== (int) $factura->tenant_id) {
            return false;
        }

        // Comparar ao cêntimo: a coluna é decimal(15,2) e sem isto uma
        // diferença invisível marcava o artigo como alterado a cada compra,
        // enchendo o histórico de ruído.
        if (round((float) $artigo->cost, 2) === round($custo, 2)) {
            return false;
        }

        $anterior = (float) $artigo->cost;
        $artigo->cost = round($custo, 2);
        $artigo->save();   // o ProductActivityObserver regista a alteração

        \Log::info('Custo do artigo actualizado pela compra', [
            'produto'   => $artigo->id,
            'de'        => $anterior,
            'para'      => $artigo->cost,
            'factura'   => $factura->invoice_number,
            'tenant_id' => $factura->tenant_id,
        ]);

        return true;
    }

    /**
     * Preço unitário líquido da linha: tira o desconto, ignora o imposto.
     *
     * Não se usa a coluna `subtotal` porque nas facturas de compra ela guarda
     * o valor BRUTO (antes do desconto) — usá-la faria o custo ignorar
     * descontos de fornecedor.
     */
    private function custoUnitario(PurchaseInvoiceItem $linha): ?float
    {
        $quantidade = (float) $linha->quantity;
        if ($quantidade <= 0) {
            return null;
        }

        $bruto    = (float) $linha->unit_price * $quantidade;
        $desconto = (float) ($linha->discount_amount ?? 0)
                  + (float) ($linha->discount_commercial_amount ?? 0);

        $liquido = $bruto - $desconto;

        return $liquido > 0 ? $liquido / $quantidade : null;
    }
}
