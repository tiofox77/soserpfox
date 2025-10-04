<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $table = 'invoicing_items';

    protected $fillable = [
        'invoice_id', 'product_id', 'order', 'code', 'name', 'description',
        'quantity', 'unit', 'unit_price', 'discount', 'discount_percentage',
        'subtotal', 'is_iva_subject', 'iva_rate', 'iva_amount', 'iva_reason', 'total'
    ];

    protected $casts = [
        'is_iva_subject' => 'boolean',
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'iva_rate' => 'decimal:2',
        'iva_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relacionamentos
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    // Calcular subtotal
    public function calculateSubtotal()
    {
        return ($this->quantity * $this->unit_price) - $this->discount;
    }
    
    // Calcular IVA
    public function calculateIva()
    {
        if ($this->is_iva_subject) {
            return $this->subtotal * ($this->iva_rate / 100);
        }
        return 0;
    }
    
    // Calcular total
    public function calculateTotal()
    {
        return $this->subtotal + $this->iva_amount;
    }
}
