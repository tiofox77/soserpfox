<?php

namespace App\Models\Invoicing;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class BatchAllocation extends Model
{
    protected $table = 'invoicing_batch_allocations';

    protected $fillable = [
        'tenant_id',
        'document_type',
        'document_id',
        'document_item_id',
        'product_batch_id',
        'product_id',
        'quantity_allocated',
        'expiry_date_snapshot',
        'batch_number_snapshot',
        'status',
    ];

    protected $casts = [
        'quantity_allocated' => 'decimal:2',
        'expiry_date_snapshot' => 'date',
    ];

    // Relacionamentos
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Relacionamento polimórfico com documento
    public function document()
    {
        return $this->morphTo('document', 'document_type', 'document_id');
    }

    // Scopes
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeAllocated($query)
    {
        return $query->where('status', 'allocated');
    }

    public function scopeForDocument($query, $documentType, $documentId)
    {
        return $query->where('document_type', $documentType)
            ->where('document_id', $documentId);
    }
}
