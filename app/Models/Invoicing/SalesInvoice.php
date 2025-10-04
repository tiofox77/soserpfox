<?php

namespace App\Models\Invoicing;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInvoice extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'invoicing_sales_invoices';

    protected $fillable = [
        'tenant_id',
        'proforma_id',
        'invoice_number',
        'atcud',
        'invoice_type',
        'invoice_status',
        'invoice_status_date',
        'source_id',
        'source_billing',
        'hash',
        'hash_control',
        'hash_previous',
        'system_entry_date',
        'client_id',
        'warehouse_id',
        'invoice_date',
        'due_date',
        'status',
        'is_service',
        'subtotal',
        'net_total',
        'tax_amount',
        'tax_payable',
        'irt_amount',
        'discount_amount',
        'discount_commercial',
        'discount_financial',
        'total',
        'gross_total',
        'paid_amount',
        'currency',
        'exchange_rate',
        'notes',
        'terms',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'invoice_status_date' => 'datetime',
        'system_entry_date' => 'datetime',
        'is_service' => 'boolean',
        'subtotal' => 'decimal:2',
        'net_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_payable' => 'decimal:2',
        'irt_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_commercial' => 'decimal:2',
        'discount_financial' => 'decimal:2',
        'total' => 'decimal:2',
        'gross_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = static::generateInvoiceNumber($invoice->tenant_id);
            }
            
            // Define armazém padrão se não especificado
            if (empty($invoice->warehouse_id)) {
                $defaultWarehouse = Warehouse::getDefault($invoice->tenant_id);
                if ($defaultWarehouse) {
                    $invoice->warehouse_id = $defaultWarehouse->id;
                }
            }
        });
    }

    public static function generateInvoiceNumber($tenantId)
    {
        $year = now()->year;
        $prefix = 'FT ' . $year . '/';
        
        $lastInvoice = static::where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        $newNumber = $lastInvoice ? ((int) substr($lastInvoice->invoice_number, -6)) + 1 : 1;

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    // Relacionamentos
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function proforma()
    {
        return $this->belongsTo(SalesProforma::class, 'proforma_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(SalesInvoiceItem::class, 'sales_invoice_id')->orderBy('order');
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'invoice_id')->where('type', 'sale');
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class, 'invoice_id');
    }

    public function debitNotes()
    {
        return $this->hasMany(DebitNote::class, 'invoice_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Métodos
    public function calculateTotals()
    {
        $this->subtotal = $this->items->sum('subtotal');
        $this->tax_amount = $this->items->sum('tax_amount');
        $this->total = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->save();
    }

    public function getBalanceAttribute()
    {
        return $this->total - ($this->paid_amount ?? 0);
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'draft' => 'Rascunho',
            'pending' => 'Pendente',
            'partially_paid' => 'Parcialmente Pago',
            'paid' => 'Pago',
            'overdue' => 'Atrasado',
            'cancelled' => 'Cancelado',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'draft' => 'gray',
            'pending' => 'yellow',
            'partially_paid' => 'blue',
            'paid' => 'green',
            'overdue' => 'red',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    // Métodos SAFT-AO
    public function generateHash()
    {
        $previousHash = self::where('tenant_id', $this->tenant_id)
            ->where('id', '<', $this->id)
            ->orderBy('id', 'desc')
            ->value('hash') ?? '';
        
        $dataToHash = sprintf(
            "%s;%s;%s;%.2f;%s",
            $this->invoice_date->format('Y-m-d'),
            ($this->system_entry_date ?? now())->format('Y-m-dTH:i:s'),
            $this->invoice_number,
            $this->gross_total ?? $this->total,
            $previousHash
        );
        
        $this->hash = hash('sha256', $dataToHash);
        $this->hash_previous = $previousHash;
        $this->hash_control = '1';
        $this->save();
    }

    public function finalizeInvoice()
    {
        $this->invoice_status = 'F';
        $this->invoice_status_date = now();
        $this->source_id = auth()->user()->id ?? 'SYSTEM';
        $this->system_entry_date = $this->system_entry_date ?? now();
        
        // Calcular totais SAFT-AO
        $this->net_total = $this->subtotal;
        $this->tax_payable = $this->tax_amount;
        $this->gross_total = $this->total;
        
        $this->save();
        $this->generateHash();
    }

    public function cancelInvoice($reason = null)
    {
        $this->invoice_status = 'A';
        $this->invoice_status_date = now();
        $this->notes = ($this->notes ? $this->notes . "\n\n" : '') . "ANULADO: " . ($reason ?? 'Sem motivo especificado');
        $this->save();
    }

    public static function validateNIF($nif)
    {
        // NIF em Angola tem 9 ou 14 dígitos
        return preg_match('/^\d{9}(\d{5})?$/', $nif);
    }
}
