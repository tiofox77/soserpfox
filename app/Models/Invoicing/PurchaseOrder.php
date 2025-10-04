<?php

namespace App\Models\Invoicing;

use App\Models\Supplier;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'invoicing_purchase_orders';

    protected $fillable = [
        'tenant_id',
        'order_number',
        'supplier_id',
        'warehouse_id',
        'order_date',
        'expected_date',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'currency',
        'exchange_rate',
        'notes',
        'terms',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber($order->tenant_id);
            }
            
            // Define armazém padrão se não especificado
            if (empty($order->warehouse_id)) {
                $defaultWarehouse = Warehouse::getDefault($order->tenant_id);
                if ($defaultWarehouse) {
                    $order->warehouse_id = $defaultWarehouse->id;
                }
            }
        });
    }

    public static function generateOrderNumber($tenantId)
    {
        $year = now()->year;
        $prefix = 'PC ' . $year . '/';
        
        $last = static::where('tenant_id', $tenantId)
            ->where('order_number', 'like', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->first();

        $newNumber = $last ? ((int) substr($last->order_number, -6)) + 1 : 1;

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoices()
    {
        return $this->hasMany(PurchaseInvoice::class, 'purchase_order_id');
    }

    public function calculateTotals()
    {
        $this->subtotal = $this->items->sum('subtotal');
        $this->tax_amount = $this->items->sum('tax_amount');
        $this->total = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->save();
    }
}
