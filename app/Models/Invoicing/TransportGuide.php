<?php

namespace App\Models\Invoicing;

use App\Models\Client;
use App\Traits\BelongsToTenant;
use App\Traits\HasAGTSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportGuide extends Model
{
    use BelongsToTenant, SoftDeletes, HasAGTSignature;

    protected $table = 'invoicing_transport_guides';

    protected $fillable = [
        'tenant_id', 'guide_number', 'type', 'series_id', 'invoice_id', 'client_id', 'warehouse_id',
        'issue_date', 'system_entry_date', 'loading_datetime', 'vehicle_plate', 'driver_name', 'driver_document',
        'load_address', 'unload_address', 'notes', 'status', 'created_by',
        'gross_total', 'net_total', 'tax_amount',
        'atcud', 'saft_hash', 'hash', 'hash_previous', 'hash_control', 'jws_signature',
        'agt_status', 'agt_reference', 'document_status_code',
        'jws_document_signature', 'agt_submission_uuid', 'agt_request_id', 'agt_submitted_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'system_entry_date' => 'datetime',
        'loading_datetime' => 'datetime',
        'gross_total' => 'decimal:2',
        'net_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    /**
     * Assina a guia (hash SAFT-AO + JWS) ANTES do insert, para o hash anterior
     * ser corretamente encadeado. Gracioso: se o tenant não tiver chaves AGT, salta.
     */
    protected static function booted(): void
    {
        static::creating(function (self $guide) {
            if (config('app.agt_auto_sign', true) && !empty($guide->guide_number) && empty($guide->hash)) {
                $guide->system_entry_date = $guide->system_entry_date ?? now();
                $guide->generateHashAndSign();
            }
        });

        // ATCUD só após insert (usa o id) e apenas se houver série (código de validação AGT)
        static::created(function (self $guide) {
            if ($guide->series_id && empty($guide->atcud)) {
                $guide->ensureATCUD();
            }
        });
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(InvoicingSeries::class, 'series_id');
    }

    public const TYPES = [
        'GT' => 'Guia de Transporte',
        'GR' => 'Guia de Remessa',
    ];

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransportGuideItem::class, 'transport_guide_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
