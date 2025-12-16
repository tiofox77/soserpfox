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

class DebitNote extends Model
{
    use BelongsToTenant, SoftDeletes, HasAGTSignature;

    protected $table = 'invoicing_debit_notes';

    protected $fillable = [
        'tenant_id',
        'debit_note_number',
        'invoice_id',
        'client_id',
        'warehouse_id',
        'issue_date',
        'due_date',
        'reason',
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
        'due_date' => 'date',
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
        return $this->hasMany(DebitNoteItem::class);
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

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($debitNote) {
            if (empty($debitNote->debit_note_number)) {
                $debitNote->debit_note_number = self::generateDebitNoteNumber();
            }
        });

        static::created(function ($debitNote) {
            // Atualizar total da fatura se houver
            if ($debitNote->invoice_id && $debitNote->status === 'issued') {
                $debitNote->updateInvoiceBalance();
            }
        });
    }

    // Gerar número de nota de débito
    public static function generateDebitNoteNumber()
    {
        $year = date('Y');
        
        $lastDebitNote = self::where('tenant_id', activeTenantId())
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastDebitNote ? ((int) substr($lastDebitNote->debit_note_number, -4)) + 1 : 1;

        return sprintf('ND/%s/%04d', $year, $nextNumber);
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

        // Somar todas as notas de débito emitidas para esta fatura
        $totalDebitNotes = self::where('invoice_id', $this->invoice_id)
            ->where('status', 'issued')
            ->sum('total');

        // Aumentar o total da fatura (débito adiciona valor)
        // Nota: Você pode querer criar um campo adicional para rastrear isto
    }

    // Accessors
    public function getHashAttribute()
    {
        return $this->saft_hash;
    }

    public function getReasonLabelAttribute()
    {
        $reasons = [
            'interest' => 'Juros',
            'penalty' => 'Multa',
            'additional_charge' => 'Cobrança Adicional',
            'correction' => 'Correção',
            'other' => 'Outro',
        ];

        return $reasons[$this->reason] ?? ucfirst($this->reason);
    }

    public function getStatusLabelAttribute()
    {
        $statuses = [
            'draft' => 'Rascunho',
            'issued' => 'Emitida',
            'paid' => 'Paga',
            'cancelled' => 'Cancelada',
        ];

        return $statuses[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'draft' => 'gray',
            'issued' => 'yellow',
            'paid' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    // Cancel debit note
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
