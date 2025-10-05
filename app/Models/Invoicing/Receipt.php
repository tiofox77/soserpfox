<?php

namespace App\Models\Invoicing;

use App\Models\Client;
use App\Models\Supplier;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'invoicing_receipts';

    protected $fillable = [
        'tenant_id',
        'receipt_number',
        'type',
        'invoice_id',
        'client_id',
        'supplier_id',
        'payment_date',
        'payment_method',
        'amount_paid',
        'remaining_amount',
        'reference',
        'notes',
        'status',
        'saft_hash',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount_paid' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSales($query)
    {
        return $query->where('type', 'sale');
    }

    public function scopePurchases($query)
    {
        return $query->where('type', 'purchase');
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

        static::creating(function ($receipt) {
            if (empty($receipt->receipt_number)) {
                $receipt->receipt_number = self::generateReceiptNumber($receipt->type);
            }

            // Definir remaining_amount igual ao amount inicial
            if ($receipt->amount_paid && empty($receipt->remaining_amount)) {
                $receipt->remaining_amount = $receipt->amount_paid;
            }
        });

        static::created(function ($receipt) {
            // Atualizar status da fatura se houver
            if ($receipt->invoice_id && $receipt->type === 'sale') {
                $receipt->updateInvoiceStatus();
            }
        });
    }

    // Gerar número de recibo
    public static function generateReceiptNumber($type = 'sale')
    {
        $prefix = $type === 'sale' ? 'RV' : 'RC'; // RV = Recibo Venda, RC = Recibo Compra
        $year = date('Y');
        
        $lastReceipt = self::where('tenant_id', activeTenantId())
            ->where('type', $type)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastReceipt ? ((int) substr($lastReceipt->receipt_number, -4)) + 1 : 1;

        return sprintf('%s/%s/%04d', $prefix, $year, $nextNumber);
    }

    // Atualizar status da fatura baseado nos pagamentos
    public function updateInvoiceStatus()
    {
        if (!$this->invoice_id || $this->type !== 'sale') {
            return;
        }

        $invoice = SalesInvoice::find($this->invoice_id);
        if (!$invoice) {
            return;
        }

        // Somar todos os recibos dessa fatura
        $totalPaid = self::where('invoice_id', $this->invoice_id)
            ->where('status', 'issued')
            ->sum('amount_paid');

        // Atualizar status da fatura
        if ($totalPaid >= $invoice->total) {
            $invoice->status = 'paid';
        } elseif ($totalPaid > 0) {
            $invoice->status = 'partially_paid';
        } else {
            $invoice->status = 'pending';
        }

        $invoice->save();
    }

    // Accessors
    public function getAmountAttribute()
    {
        return $this->amount_paid;
    }

    public function getHashAttribute()
    {
        return $this->saft_hash;
    }

    public function getEntityNameAttribute()
    {
        if ($this->type === 'sale' && $this->client) {
            return $this->client->name;
        } elseif ($this->type === 'purchase' && $this->supplier) {
            return $this->supplier->name;
        }
        return 'N/A';
    }

    public function getPaymentMethodLabelAttribute()
    {
        $methods = [
            'cash' => 'Dinheiro',
            'transfer' => 'Transferência',
            'multicaixa' => 'Multicaixa',
            'tpa' => 'TPA',
            'check' => 'Cheque',
            'mbway' => 'MB Way',
            'other' => 'Outro',
        ];

        return $methods[$this->payment_method] ?? ucfirst($this->payment_method);
    }

    public function getStatusLabelAttribute()
    {
        $statuses = [
            'issued' => 'Emitido',
            'cancelled' => 'Cancelado',
        ];

        return $statuses[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'issued' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    // Cancelar recibo
    public function cancel()
    {
        $this->status = 'cancelled';
        $this->save();

        // Atualizar status da fatura
        if ($this->invoice_id && $this->type === 'sale') {
            $this->updateInvoiceStatus();
        }
    }
}
