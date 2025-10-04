<?php

namespace App\Models\Invoicing;

use App\Models\Supplier;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseProforma extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'invoicing_purchase_proformas';

    protected $fillable = [
        'tenant_id',
        'proforma_number',
        'supplier_id',
        'warehouse_id',
        'proforma_date',
        'valid_until',
        'status',
        'is_service',
        'subtotal',
        'tax_amount',
        'irt_amount',
        'discount_amount',
        'discount_commercial',
        'discount_financial',
        'total',
        'currency',
        'exchange_rate',
        'notes',
        'terms',
        'created_by',
    ];

    protected $casts = [
        'proforma_date' => 'date',
        'valid_until' => 'date',
        'is_service' => 'boolean',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'irt_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_commercial' => 'decimal:2',
        'discount_financial' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($proforma) {
            if (empty($proforma->proforma_number)) {
                $proforma->proforma_number = static::generateProformaNumber($proforma->tenant_id);
            }
            
            // Define armazém padrão se não especificado
            if (empty($proforma->warehouse_id)) {
                $defaultWarehouse = Warehouse::getDefault($proforma->tenant_id);
                if ($defaultWarehouse) {
                    $proforma->warehouse_id = $defaultWarehouse->id;
                }
            }

            // Define validade padrão (30 dias)
            if (empty($proforma->valid_until)) {
                $proforma->valid_until = now()->addDays(30);
            }
        });
    }

    public static function generateProformaNumber($tenantId)
    {
        $year = now()->year;
        $prefix = 'PP-C ' . $year . '/';
        
        $lastProforma = static::where('tenant_id', $tenantId)
            ->where('proforma_number', 'like', $prefix . '%')
            ->orderBy('proforma_number', 'desc')
            ->first();

        $newNumber = $lastProforma ? ((int) substr($lastProforma->proforma_number, -6)) + 1 : 1;

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    // Relacionamentos
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
        return $this->hasMany(PurchaseProformaItem::class, 'purchase_proforma_id')->orderBy('order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseInvoice()
    {
        return $this->hasOne(PurchaseInvoice::class, 'proforma_id');
    }

    // Métodos
    public function calculateTotals()
    {
        $this->subtotal = $this->items->sum('subtotal');
        $this->tax_amount = $this->items->sum('tax_amount');
        $this->total = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->save();
    }

    public function convertToInvoice()
    {
        if ($this->status === 'converted') {
            throw new \Exception('Proforma já convertida em fatura.');
        }

        $invoice = PurchaseInvoice::create([
            'tenant_id' => $this->tenant_id,
            'proforma_id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'warehouse_id' => $this->warehouse_id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'draft',
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'created_by' => auth()->id() ?? $this->created_by,
        ]);

        // Copiar itens
        foreach ($this->items as $item) {
            $invoice->items()->create([
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'discount_amount' => $item->discount_amount,
                'subtotal' => $item->subtotal,
                'tax_rate' => $item->tax_rate,
                'tax_amount' => $item->tax_amount,
                'total' => $item->total,
                'order' => $item->order,
            ]);
        }

        $this->status = 'converted';
        $this->save();

        return $invoice;
    }

    public function checkExpiration()
    {
        if ($this->status === 'draft' || $this->status === 'sent') {
            if ($this->valid_until && $this->valid_until->isPast()) {
                $this->status = 'expired';
                $this->save();
            }
        }
    }
}
