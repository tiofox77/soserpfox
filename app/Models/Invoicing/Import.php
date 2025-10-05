<?php

namespace App\Models\Invoicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenant;
use App\Models\Supplier;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Warehouse;
use App\Models\Invoicing\ImportItem;

class Import extends Model
{
    use SoftDeletes, BelongsToTenant;
    
    protected $table = 'invoicing_imports';
    
    protected $fillable = [
        'tenant_id', 'purchase_invoice_id', 'supplier_id', 'warehouse_id',
        'import_number', 'reference', 'order_date', 'expected_arrival_date', 'actual_arrival_date',
        'origin_country', 'origin_port', 'destination_port', 'shipping_company',
        'container_number', 'bill_of_lading', 'transport_type',
        'du_number', 'du_date', 'du_reference', 'du_declared_value', 'du_currency',
        'fob_value', 'freight_cost', 'insurance_cost', 'cif_value',
        'customs_duty', 'consumption_tax', 'stamp_duty', 'other_charges', 'total_import_cost',
        'customs_agent', 'customs_agent_contact', 'agent_fee',
        'status', 'notes', 'customs_notes', 'documents', 'checklist',
        'created_by', 'approved_by', 'approved_at',
    ];
    
    protected $casts = [
        'order_date' => 'date',
        'expected_arrival_date' => 'date',
        'actual_arrival_date' => 'date',
        'du_date' => 'date',
        'approved_at' => 'datetime',
        'documents' => 'array',
        'checklist' => 'array',
    ];
    
    // Relacionamentos
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
    
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }
    
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
    
    public function items(): HasMany
    {
        return $this->hasMany(ImportItem::class);
    }
    
    public function history(): HasMany
    {
        return $this->hasMany(ImportHistory::class)->orderBy('created_at', 'desc');
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
    
    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }
    
    // Accessors
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'quotation' => 'Cotação',
            'order_placed' => 'Pedido Realizado',
            'payment_pending' => 'Pagamento Pendente',
            'payment_confirmed' => 'Pagamento Confirmado',
            'in_transit' => 'Em Trânsito',
            'customs_pending' => 'Desembaraço Pendente',
            'customs_inspection' => 'Inspeção Alfandegária',
            'customs_cleared' => 'Desembaraçado',
            'in_warehouse' => 'No Armazém',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            default => $this->status
        };
    }
    
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'quotation' => 'gray',
            'order_placed' => 'blue',
            'payment_pending' => 'yellow',
            'payment_confirmed' => 'green',
            'in_transit' => 'cyan',
            'customs_pending' => 'orange',
            'customs_inspection' => 'orange',
            'customs_cleared' => 'green',
            'in_warehouse' => 'emerald',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray'
        };
    }
    
    // Métodos úteis
    public function calculateCIF()
    {
        $this->cif_value = ($this->fob_value ?? 0) + ($this->freight_cost ?? 0) + ($this->insurance_cost ?? 0);
        return $this->cif_value;
    }
    
    public function calculateStampDuty()
    {
        $this->stamp_duty = ($this->cif_value ?? 0) * 0.003; // 0.3%
        return $this->stamp_duty;
    }
    
    public function calculateTotalCost()
    {
        $this->total_import_cost = ($this->cif_value ?? 0)
            + ($this->customs_duty ?? 0)
            + ($this->consumption_tax ?? 0)
            + ($this->stamp_duty ?? 0)
            + ($this->agent_fee ?? 0)
            + ($this->other_charges ?? 0);
        
        return $this->total_import_cost;
    }
    
    public static function generateNumber($tenantId = null)
    {
        $tenantId = $tenantId ?? activeTenantId();
        $year = date('Y');
        
        $lastImport = self::where('tenant_id', $tenantId)
            ->where('import_number', 'like', "IMP/{$year}/%")
            ->latest('id')
            ->first();
        
        $newNumber = $lastImport 
            ? str_pad(intval(substr($lastImport->import_number, -4)) + 1, 4, '0', STR_PAD_LEFT) 
            : '0001';
        
        return "IMP/{$year}/{$newNumber}";
    }
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($import) {
            if (empty($import->import_number)) {
                $import->import_number = self::generateNumber($import->tenant_id);
            }
            
            if (empty($import->created_by)) {
                $import->created_by = auth()->id();
            }
        });
    }
}
