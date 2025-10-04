<?php

namespace App\Models\Invoicing;

use App\Models\Product;
use App\Models\Invoicing\Tax;
use Illuminate\Database\Eloquent\Model;

class SalesInvoiceItem extends Model
{
    protected $table = 'invoicing_sales_invoice_items';

    protected $fillable = [
        'sales_invoice_id',
        'product_id',
        'product_name',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'subtotal',
        'tax_rate_id',
        'tax_rate',
        'tax_amount',
        'total',
        'order',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
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
            $item->calculateTotals();
        });
    }

    public function invoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function taxRate()
    {
        return $this->belongsTo(Tax::class, 'tax_rate_id');
    }

    public function calculateTotals()
    {
        $this->subtotal = $this->quantity * $this->unit_price;
        
        if ($this->discount_percent > 0) {
            $this->discount_amount = ($this->subtotal * $this->discount_percent) / 100;
        }
        
        $subtotalAfterDiscount = $this->subtotal - $this->discount_amount;
        $this->tax_amount = ($subtotalAfterDiscount * $this->tax_rate) / 100;
        $this->total = $subtotalAfterDiscount + $this->tax_amount;
    }
}
