<?php

namespace App\Http\Resources\Invoicing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A forma de um artigo quando sai para o React.
 *
 * O STOCK QUE SAI DAQUI É A SOMA DAS LINHAS, não a coluna agregada. São a
 * mesma coisa enquanto o `StockObserver` estiver a fazer o seu trabalho — e
 * quando não estiver, é a soma que diz a verdade. É a mesma fonte que a lista
 * de sempre usa.
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $gereStock = (bool) $this->manage_stock;
        $stock = (float) ($this->stock_das_linhas ?? $this->stock_quantity ?? 0);
        $minimo = (int) ($this->stock_min ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'type' => $this->type,
            'tipo_rotulo' => $this->type === 'servico' ? __('Serviço') : __('Produto'),
            'description' => $this->description,
            'unit' => $this->unit,

            'category' => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null,
            'category_id' => $this->category_id,

            'price' => round((float) $this->price, 2),
            'cost' => $this->cost === null ? null : round((float) $this->cost, 2),

            'tax_type' => $this->tax_type,
            'tax_rate_id' => $this->tax_rate_id,
            // A percentagem vem do catálogo da empresa, nunca escrita à mão.
            'taxa' => $this->taxRate ? (float) $this->taxRate->rate : null,
            'exemption_reason' => $this->exemption_reason,

            'manage_stock' => $gereStock,
            'stock' => $gereStock ? $stock : null,
            'stock_min' => $this->stock_min,
            'stock_max' => $this->stock_max,
            // Em falta é uma DECISÃO, e sai decidida: só conta em quem gere
            // stock e tem mínimo definido.
            'em_falta' => $gereStock && $minimo > 0 && $stock <= $minimo,
            'esgotado' => $gereStock && $stock <= 0,

            'is_active' => (bool) $this->is_active,
        ];
    }
}
