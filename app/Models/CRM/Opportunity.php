<?php

namespace App\Models\CRM;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma oportunidade: um negócio com nome, valor e etapa no funil.
 */
class Opportunity extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_opportunities';

    protected $fillable = [
        'tenant_id', 'title', 'stage_id', 'client_id', 'lead_id', 'amount',
        'probability', 'expected_close_date', 'status', 'lost_reason',
        'closed_at', 'assigned_to', 'created_by', 'notes',
        // O documento que nasceu deste negócio. É a marca que liga o funil à
        // facturação e a que impede facturar duas vezes o mesmo.
        'sales_invoice_id', 'facturada_em',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expected_close_date' => 'date',
        'facturada_em' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'stage_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** A factura que nasceu deste negócio, se já houver. */
    public function factura(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Invoicing\SalesInvoice::class, 'sales_invoice_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'opportunity_id');
    }

    /** O valor ponderado: o que este negócio vale, descontada a dúvida. */
    public function getWeightedAmountAttribute(): float
    {
        return round((float) $this->amount * (int) $this->probability / 100, 2);
    }
}
