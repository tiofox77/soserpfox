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
        'credit_note_number',
        'invoice_id',
        'client_id',
        'warehouse_id',
        'issue_date',
        'reason',
        'type',
        'notes',
        'subtotal',
        'tax_amount',
        'total',
        'status',
        'saft_hash',
        'jws_signature',
        'agt_status',
        'agt_reference',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
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
                $creditNote->credit_note_number = self::generateCreditNoteNumber();
            }
        });

        static::created(function ($creditNote) {
            // Atualizar saldo da fatura se houver
            if ($creditNote->invoice_id && $creditNote->status === 'issued') {
                $creditNote->updateInvoiceBalance();
            }
        });
    }

    // Gerar número de nota de crédito
    public static function generateCreditNoteNumber()
    {
        $year = date('Y');
        
        $lastCreditNote = self::where('tenant_id', activeTenantId())
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastCreditNote ? ((int) substr($lastCreditNote->credit_note_number, -4)) + 1 : 1;

        return sprintf('NC/%s/%04d', $year, $nextNumber);
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
            'return' => 'Devolução',
            'discount' => 'Desconto',
            'correction' => 'Correção',
            'other' => 'Outro',
        ];

        return $reasons[$this->reason] ?? ucfirst($this->reason);
    }

    public function getTypeLabelAttribute()
    {
        $types = [
            'total' => 'Total',
            'partial' => 'Parcial',
        ];

        return $types[$this->type] ?? ucfirst($this->type);
    }

    public function getStatusLabelAttribute()
    {
        $statuses = [
            'draft' => 'Rascunho',
            'issued' => 'Emitida',
            'cancelled' => 'Cancelada',
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
