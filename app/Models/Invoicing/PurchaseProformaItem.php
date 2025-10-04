<?php

namespace App\Models\Invoicing;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class PurchaseProformaItem extends Model
{
    protected $table = 'invoicing_purchase_proforma_items';

    protected $fillable = [
        'purchase_proforma_id',
        'product_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            // Calcular subtotal
            $item->subtotal = $item->quantity * $item->unit_price;
            
            // Calcular desconto
            if ($item->discount_percent > 0) {
                $item->discount_amount = $item->subtotal * ($item->discount_percent / 100);
            }
            $item->subtotal -= $item->discount_amount;
            
            // Calcular imposto
            $item->tax_amount = $item->subtotal * ($item->tax_rate / 100);
            
            // Calcular total
            $item->total = $item->subtotal + $item->tax_amount;
        });

        static::saved(function ($item) {
            // Recalcular totais da proforma
            $item->proforma->calculateTotals();
        });

        static::deleted(function ($item) {
            // Recalcular totais da proforma
            $item->proforma->calculateTotals();
        });
    }

    // Relacionamentos
    public function proforma()
    {
        return $this->belongsTo(PurchaseProforma::class, 'purchase_proforma_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
