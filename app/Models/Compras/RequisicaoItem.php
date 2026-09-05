<?php

namespace App\Models\Compras;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Linha de uma requisição de compra.
 *
 * `product_id` pode ser nulo de propósito: pede-se muitas vezes uma coisa que
 * ainda não existe no catálogo. A `descricao` é que manda, e é ela que
 * sobrevive se o artigo for apagado mais tarde.
 */
class RequisicaoItem extends Model
{
    protected $table = 'compras_requisicao_itens';

    protected $fillable = [
        'requisicao_id',
        'product_id',
        'descricao',
        'quantidade',
        'quantidade_encomendada',
        'custo_estimado',
        'unidade',
        'notas',
        'ordem',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'quantidade_encomendada' => 'decimal:3',
        'custo_estimado' => 'decimal:2',
    ];

    public function requisicao()
    {
        return $this->belongsTo(Requisicao::class, 'requisicao_id');
    }

    public function produto()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** O que ainda falta encomendar desta linha (nunca negativo). */
    public function porEncomendar(): float
    {
        return max(0, (float) $this->quantidade - (float) $this->quantidade_encomendada);
    }
}
