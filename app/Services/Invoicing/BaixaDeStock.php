<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\Stock;
use App\Models\Product;

/**
 * Dar baixa de stock por uma venda. Uma regra, um sítio.
 *
 * Havia três caminhos a fazer isto — o observer das facturas, o POS e a
 * sincronização do PWA — cada um com a sua versão, e as três divergiam:
 *
 *   - o observer, sem linha de stock naquele armazém, fazia `continue`: não
 *     descontava E não escrevia movimento. A venda desaparecia do rastreio.
 *   - os outros dois travavam em zero com max(0, …). O documento saía com 12,
 *     o stock descia 10, e o movimento registava 12. Daí "vendidas 12, saíram
 *     10" sem nada que explicasse a diferença.
 *
 * Passa a valer o mesmo em todo o lado: desconta-se a quantidade TODA, mesmo
 * que fique negativo, e escreve-se sempre o movimento. Um armazém negativo é
 * uma informação — diz que se vendeu mais do que lá havia — e é reparável.
 * Um travão em zero apaga essa informação para sempre.
 */
class BaixaDeStock
{
    /**
     * Desconta $quantidade do produto no armazém indicado.
     *
     * Devolve o que ficou, ou null se não havia onde descontar (produto sem
     * gestão de stock, ou sem armazém).
     */
    public static function aplicar(int $tenantId, ?int $warehouseId, Product $produto, float $quantidade): ?float
    {
        if ($quantidade <= 0) {
            return null;
        }

        /*
         * LIDA SOB BLOQUEIO. Duas vendas do mesmo artigo ao mesmo segundo liam
         * as duas 11 e gravavam as duas 10: o stock descia uma vez por duas
         * vendas (Luk Simões, FR 003253/003254, 23/09/2026). Dentro de uma
         * transacção, um SELECT simples lê a fotografia tirada no início dela;
         * o FOR UPDATE lê o que está gravado e faz a segunda esperar.
         */
        $linha = $warehouseId
            ? Stock::where('tenant_id', $tenantId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $produto->id)
                ->lockForUpdate()
                ->first()
            : null;

        if ($linha) {
            // save() e não decrement(): é o StockObserver que ressincroniza o
            // agregado a partir das linhas.
            $linha->quantity = (float) $linha->quantity - $quantidade;
            $linha->save();

            return (float) $linha->quantity;
        }

        // Sem linha neste armazém. O que fazer depende do regime do produto, e
        // enganar-se aqui tem consequências visíveis na caixa.
        if ($warehouseId && self::temLinhasNoutroArmazem($tenantId, $produto)) {
            // Produto já em regime multi-armazém: o POS já lê 0 para armazéns
            // sem linha, por isso criar esta linha não muda nada do que se vê —
            // só passa a registar a falta em vez de a esconder.
            $linha = new Stock([
                'tenant_id'    => $tenantId,
                'warehouse_id' => $warehouseId,
                'product_id'   => $produto->id,
                'quantity'     => -$quantidade,
            ]);
            $linha->save();

            return (float) $linha->quantity;
        }

        // Produto sem linha nenhuma: vive do agregado, e é ele a própria
        // existência de stock. Criar-lhe aqui uma linha passava-o a
        // multi-armazém e os OUTROS armazéns passavam a ler 0 — o produto
        // aparecia esgotado na caixa por causa de uma venda.
        $produto->stock_quantity = (float) ($produto->stock_quantity ?? 0) - $quantidade;
        $produto->save();

        return (float) $produto->stock_quantity;
    }

    private static function temLinhasNoutroArmazem(int $tenantId, Product $produto): bool
    {
        return Stock::where('tenant_id', $tenantId)
            ->where('product_id', $produto->id)
            ->exists();
    }
}
