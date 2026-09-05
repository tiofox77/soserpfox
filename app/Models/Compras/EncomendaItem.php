<?php

namespace App\Models\Compras;

use App\Models\Invoicing\Tax;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Linha de uma encomenda ao fornecedor.
 *
 * Os totais nunca se escrevem à mão: recalculam-se no `saving` a partir de
 * quantidade, preço, desconto e taxa. Assim uma linha gravada é sempre
 * coerente consigo mesma, venha de onde vier (ecrã, conversão, ensaio).
 */
class EncomendaItem extends Model
{
    protected $table = 'compras_encomenda_itens';

    protected $fillable = [
        'encomenda_id',
        'product_id',
        'descricao',
        'quantidade',
        'quantidade_recebida',
        'preco_unitario',
        'desconto_percent',
        'tax_rate_id',
        'tax_rate',
        'subtotal',
        'imposto',
        'total',
        'unidade',
        'ordem',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'quantidade_recebida' => 'decimal:3',
        'preco_unitario' => 'decimal:2',
        'desconto_percent' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'imposto' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $item) {
            $item->calcular();
        });
    }

    public function encomenda()
    {
        return $this->belongsTo(Encomenda::class, 'encomenda_id');
    }

    public function produto()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function taxa()
    {
        return $this->belongsTo(Tax::class, 'tax_rate_id');
    }

    /** O que ainda falta chegar desta linha (nunca negativo). */
    public function porReceber(): float
    {
        return max(0, (float) $this->quantidade - (float) $this->quantidade_recebida);
    }

    /** Valor do desconto desta linha, em kwanzas. */
    public function descontoValor(): float
    {
        $bruto = (float) $this->quantidade * (float) $this->preco_unitario;

        return round($bruto * (float) $this->desconto_percent / 100, 2);
    }

    /**
     * Subtotal já LÍQUIDO de desconto, mais o imposto por cima.
     *
     * Guarda-se o líquido em `subtotal` (e não o bruto) porque é o líquido que
     * soma para o total da encomenda — o desconto vive na percentagem e
     * recalcula-se de lá sempre que é preciso mostrá-lo.
     */
    public function calcular(): void
    {
        $bruto = (float) $this->quantidade * (float) $this->preco_unitario;
        $liquido = $bruto - round($bruto * (float) $this->desconto_percent / 100, 2);

        $this->subtotal = round($liquido, 2);
        $this->imposto = round($liquido * (float) $this->tax_rate / 100, 2);
        $this->total = round($this->subtotal + $this->imposto, 2);
    }
}
