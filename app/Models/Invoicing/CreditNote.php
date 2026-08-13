<?php

namespace App\Models\Invoicing;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasAGTSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    use BelongsToTenant, SoftDeletes, HasAGTSignature;

    protected $table = 'invoicing_credit_notes';

    protected $fillable = [
        'tenant_id',
        'series_id',
        'credit_note_number',
        'invoice_id',
        'client_id',
        'warehouse_id',
        'issue_date',
        'system_entry_date',
        'reason',
        'reason_text',
        'type',
        'notes',
        'subtotal',
        'net_total',
        'tax_amount',
        'tax_payable',
        'total',
        'gross_total',
        'status',
        'invoice_status',
        'source_billing',
        'atcud',
        'saft_hash',
        'hash',
        'hash_previous',
        'hash_control',
        'jws_signature',
        'jws_document_signature',
        'agt_status',
        'agt_reference',
        'agt_request_id',
        'agt_submission_uuid',
        'agt_submitted_at',
        'agt_validated_at',
        'eac_code',
        'document_status_code',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'system_entry_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'net_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_payable' => 'decimal:2',
        'total' => 'decimal:2',
        'gross_total' => 'decimal:2',
    ];

    // Relacionamentos
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(InvoicingSeries::class, 'series_id');
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($creditNote) {
            if (empty($creditNote->credit_note_number)) {
                $creditNote->credit_note_number = self::generateCreditNoteNumber(
                    (int) $creditNote->tenant_id,
                    $creditNote
                );
            }
            if ($creditNote->series_id) {
                $series = InvoicingSeries::getIssuanceSeries(
                    (int) $creditNote->tenant_id,
                    'credit_note',
                    (int) $creditNote->series_id
                );
                if ($series->isAGTRegistered() && empty($creditNote->atcud)) {
                    $creditNote->atcud = $series->generateATCUD(
                        $series->nextSequentialFromDocumentNumber($creditNote->credit_note_number)
                    );
                }
            }
        });

        static::created(function ($creditNote) {
            // Atualizar saldo da fatura se houver
            if ($creditNote->invoice_id && $creditNote->status === 'issued') {
                $creditNote->updateInvoiceBalance();
            }
        });
    }

    // Gerar número de nota de crédito (formato AGT: NC A 2025/000001)
    public static function generateCreditNoteNumber(?int $tenantId = null, ?self $creditNote = null)
    {
        $tenantId = $tenantId ?: (int) activeTenantId();
        
        // Usar sistema de séries AGT (Decreto Presidencial 71/25)
        $series = InvoicingSeries::getIssuanceSeries($tenantId, 'credit_note');
        
        if ($series) {
            if ($creditNote && empty($creditNote->series_id)) {
                $creditNote->series_id = $series->id;
            }
            $number = $series->getNextNumber();
            if ($creditNote && $series->isAGTRegistered() && empty($creditNote->atcud)) {
                $creditNote->atcud = $series->generateATCUD(
                    $series->nextSequentialFromDocumentNumber($number)
                );
            }
            return $number;
        }
        
        // Fallback: formato AGT manual
        $year = date('Y');
        $prefix = 'NC A ' . $year . '/';
        
        $lastCreditNote = self::where('tenant_id', $tenantId)
            ->where('credit_note_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastCreditNote ? ((int) substr($lastCreditNote->credit_note_number, -6)) + 1 : 1;

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    // Atualizar saldo da fatura
    public function updateInvoiceBalance()
    {
        if (!$this->invoice_id) {
            return;
        }

        $invoice = SalesInvoice::find($this->invoice_id);
        if (!$invoice) {
            return;
        }

        // Somar todas as notas de crédito emitidas para esta fatura
        $totalCreditNotes = self::where('invoice_id', $this->invoice_id)
            ->where('status', 'issued')
            ->sum('total');

        // Calcular novo total da fatura
        // O saldo é o total original menos as notas de crédito
        // Nota: Você pode querer adicionar um campo balance na tabela de faturas
        // Por enquanto, apenas registramos a nota de crédito

        // Se o total das notas de crédito >= total da fatura, marcar como creditada
        if ($totalCreditNotes >= $invoice->total) {
            $invoice->status = 'credited';
            $invoice->save();
        }
    }

    // Accessors
    public function getReasonLabelAttribute()
    {
        $reasons = [
            'return' => __('Devolução'),
            'discount' => __('Desconto'),
            'correction' => __('Correção'),
            'other' => __('Outro'),
        ];

        return $reasons[$this->reason] ?? ucfirst($this->reason);
    }

    // Expressão obrigatória conforme Art. 12º RJF (Decreto 71/25)
    public function getReasonExpressionAttribute(): string
    {
        if ($this->type === 'total') {
            return 'Anulação';
        }
        return 'Rectificação';
    }

    public function getTypeLabelAttribute()
    {
        $types = [
            'total' => __('Total'),
            'partial' => __('Parcial'),
        ];

        return $types[$this->type] ?? ucfirst($this->type);
    }

    public function getStatusLabelAttribute()
    {
        $statuses = [
            'draft' => __('Rascunho'),
            'issued' => __('Emitida'),
            'cancelled' => __('Cancelada'),
        ];

        return $statuses[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'draft' => 'gray',
            'issued' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    // Cancel credit note
    public function cancel()
    {
        $this->status = 'cancelled';
        $this->save();

        // Recalcular saldo da fatura
        if ($this->invoice_id) {
            $this->updateInvoiceBalance();
        }
    }
}
