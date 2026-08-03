<?php

namespace App\Models\Workshop;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\HR\Employee;

class WorkOrderItem extends Model
{
    use HasFactory;

    protected $table = 'workshop_work_order_items';

    protected $fillable = [
        'work_order_id',
        'service_id',
        'product_id',
        'invoice_id',
        'type',
        'code',
        'name',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'subtotal',
        'hours',
        'mechanic_id',
        'part_number',
        'brand',
        'is_original',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'hours' => 'decimal:2',
        'is_original' => 'boolean',
    ];

    protected $appends = ['formatted_subtotal'];

    // Relationships
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function mechanic()
    {
        return $this->belongsTo(Employee::class, 'mechanic_id');
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    public function invoice()
    {
        return $this->belongsTo(\App\Models\Invoicing\SalesInvoice::class, 'invoice_id');
    }

    // Accessors
    public function getFormattedSubtotalAttribute()
    {
        return number_format($this->subtotal, 2, ',', '.') . ' Kz';
    }

    // Methods
    public function isService()
    {
        return $this->type === 'service';
    }

    public function isPart()
    {
        return $this->type === 'part';
    }

    /** Calcula desconto e subtotal em memória (não grava). */
    public function aplicarSubtotal()
    {
        $baseAmount = (float) $this->quantity * (float) $this->unit_price;

        if ($this->discount_percent > 0) {
            $this->discount_amount = $baseAmount * ((float) $this->discount_percent / 100);
        }

        $this->subtotal = $baseAmount - (float) $this->discount_amount;
    }

    protected static function booted()
    {
        // O subtotal é calculado ANTES de gravar.
        //
        // Antes era calculado DEPOIS, nos eventos `created`/`updated`, e cada um
        // chamava $this->save() outra vez. Como o Eloquent só sincroniza o
        // `original` DEPOIS de disparar `updated`, o item continuava a parecer
        // "sujo" dentro do próprio evento e voltava a gravar — recursão
        // infinita. Na prática, adicionar uma peça ou um serviço a uma Ordem de
        // Serviço ficava pendurado até o PHP esgotar tempo/memória.
        static::saving(function ($item) {
            $item->aplicarSubtotal();
        });

        // Totais da OS: é outro modelo, não reentra.
        static::saved(function ($item) {
            $item->workOrder?->calculateTotals();
        });

        static::deleted(function ($item) {
            $item->workOrder?->calculateTotals();
        });
    }
}
