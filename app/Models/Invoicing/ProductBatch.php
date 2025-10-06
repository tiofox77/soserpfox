<?php

namespace App\Models\Invoicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class ProductBatch extends Model
{
    use SoftDeletes;

    protected $table = 'invoicing_product_batches';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'quantity',
        'quantity_available',
        'purchase_invoice_id',
        'supplier_name',
        'cost_price',
        'status',
        'alert_days',
        'notes',
    ];

    protected $casts = [
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'quantity' => 'decimal:2',
        'quantity_available' => 'decimal:2',
        'cost_price' => 'decimal:2',
    ];

    // Relacionamentos
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('quantity_available', '>', 0);
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('status', 'active')
            ->whereDate('expiry_date', '<=', Carbon::now()->addDays($days))
            ->whereDate('expiry_date', '>=', Carbon::now());
    }

    public function scopeExpired($query)
    {
        return $query->whereDate('expiry_date', '<', Carbon::now());
    }

    // Accessors
    public function getDaysUntilExpiryAttribute()
    {
        if (!$this->expiry_date) {
            return null;
        }
        
        return Carbon::now()->diffInDays($this->expiry_date, false);
    }

    public function getIsExpiredAttribute()
    {
        if (!$this->expiry_date) {
            return false;
        }
        
        return Carbon::now()->isAfter($this->expiry_date);
    }

    public function getIsExpiringSoonAttribute()
    {
        if (!$this->expiry_date) {
            return false;
        }
        
        $daysUntilExpiry = $this->days_until_expiry;
        return $daysUntilExpiry !== null && $daysUntilExpiry <= $this->alert_days && $daysUntilExpiry >= 0;
    }

    public function getStatusColorAttribute()
    {
        if ($this->is_expired) {
            return 'red';
        }
        
        if ($this->is_expiring_soon) {
            return 'orange';
        }
        
        if ($this->quantity_available <= 0) {
            return 'gray';
        }
        
        return 'green';
    }

    public function getStatusLabelAttribute()
    {
        if ($this->is_expired) {
            return 'Expirado';
        }
        
        if ($this->is_expiring_soon) {
            return 'Expira em breve';
        }
        
        if ($this->quantity_available <= 0) {
            return 'Esgotado';
        }
        
        return 'Ativo';
    }

    // Métodos
    public function updateStatus()
    {
        if ($this->is_expired) {
            $this->status = 'expired';
        } elseif ($this->quantity_available <= 0) {
            $this->status = 'sold_out';
        } else {
            $this->status = 'active';
        }
        
        $this->save();
    }

    public function decreaseQuantity($amount)
    {
        if ($amount > $this->quantity_available) {
            throw new \Exception('Quantidade insuficiente no lote');
        }
        
        $this->quantity_available -= $amount;
        $this->updateStatus();
        
        return $this;
    }

    public function increaseQuantity($amount)
    {
        $this->quantity_available += $amount;
        $this->updateStatus();
        
        return $this;
    }
}
