<?php

namespace App\Models\Invoicing;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    protected $table = 'invoicing_credit_note_items';

    protected $fillable = [
        'credit_note_id',
        'product_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'unit_price_base',
        'discount_percent',
        'discount_amount',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'order',
        // AGT v1.2
        'debit_amount',
        'credit_amount',
        'settlement_amount',
        'eac_code',
        'tax_country_region',
        'tax_code',
        'tax_exemption_code',
        'tax_exemption_reason',
        // referenceInfo (NC obrigatório) — origem
        'reference_invoice_no',
        'reference_item_line_no',
        'reference_reason',
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

    public $timestamps = true;

    // Relacionamentos
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
