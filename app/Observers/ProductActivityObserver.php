<?php

namespace App\Observers;

use App\Models\Invoicing\ProductActivityLog;
use App\Models\Product;

/**
 * Regista no histórico quem criou, alterou, eliminou ou restaurou cada produto.
 *
 * Fica no observer e não nos componentes porque há vários sítios a mexer em
 * produtos (Faturação, Salão, importações, API): assim nenhum caminho fica de
 * fora e não é preciso lembrar-se de registar em cada um.
 */
class ProductActivityObserver
{
    /**
     * Campos cujo histórico interessa. O stock_quantity fica de fora de
     * propósito: é um agregado mantido pelo StockObserver e mudaria a cada
     * venda, enchendo o histórico de ruído — os movimentos de stock já têm o
     * seu próprio registo em invoicing_stock_movements.
     */
    private const CAMPOS_RELEVANTES = [
        'name', 'code', 'barcode', 'sku', 'price', 'cost', 'category_id',
        'type', 'is_active', 'manage_stock', 'minimum_stock', 'tax_rate',
        'tax_id', 'unit', 'description', 'brand_id',
    ];

    public function created(Product $product): void
    {
        ProductActivityLog::registar(
            $product,
            ProductActivityLog::ACCAO_CRIADO,
            'Produto criado'
        );
    }

    public function updated(Product $product): void
    {
        // O restauro do soft delete também passa por updated(): distinguir para
        // não aparecer como uma alteração vulgar.
        if ($product->wasChanged('deleted_at') && $product->deleted_at === null) {
            return;   // tratado em restored()
        }

        $alteracoes = [];

        foreach (self::CAMPOS_RELEVANTES as $campo) {
            if (!$product->wasChanged($campo)) {
                continue;
            }

            $alteracoes[$campo] = [
                'antes'  => $product->getOriginal($campo),
                'depois' => $product->{$campo},
            ];
        }

        if (empty($alteracoes)) {
            return;   // só mudou o agregado de stock ou timestamps
        }

        ProductActivityLog::registar(
            $product,
            ProductActivityLog::ACCAO_ACTUALIZADO,
            count($alteracoes) . ' campo(s) alterado(s): ' . implode(', ', array_keys($alteracoes)),
            $alteracoes
        );
    }

    public function deleted(Product $product): void
    {
        // Só o soft delete: um forceDelete não deixa produto para referenciar.
        if ($product->isForceDeleting()) {
            return;
        }

        ProductActivityLog::registar(
            $product,
            ProductActivityLog::ACCAO_ELIMINADO,
            'Produto eliminado (recuperável)'
        );
    }

    public function restored(Product $product): void
    {
        ProductActivityLog::registar(
            $product,
            ProductActivityLog::ACCAO_RESTAURADO,
            'Produto restaurado'
        );
    }
}
