<?php

namespace App\Models\Invoicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportItem extends Model
{
    protected $table = 'invoicing_import_items';
    
    protected $fillable = [
        'import_id',
        'product_id',
        'product_description',
        'hs_code',
        'quantity',
        'unit',
        'unit_price',
        'total_price',
        'weight_kg',
        'origin_country',
        'notes',
    ];
    
    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'weight_kg' => 'decimal:3',
    ];
    
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
    
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
