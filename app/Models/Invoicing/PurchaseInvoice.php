<?php

namespace App\Models\Invoicing;

use App\Models\Supplier;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'invoicing_purchase_invoices';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'invoice_number',
        'supplier_id',
        'warehouse_id',
        'invoice_date',
        'due_date',
        'status',
        'is_service',
        'subtotal',
        'tax_amount',
        'irt_amount',
        'discount_amount',
        'discount_commercial',
        'discount_financial',
        'total',
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
        'is_service' => 'boolean',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'irt_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_commercial' => 'decimal:2',
        'discount_financial' => 'decimal:2',
        'total' => 'decimal:2',
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
        $prefix = 'FC ' . $year . '/';
        
        $last = static::where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        $newNumber = $last ? ((int) substr($last->invoice_number, -6)) + 1 : 1;

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class)->orderBy('order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

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
}
