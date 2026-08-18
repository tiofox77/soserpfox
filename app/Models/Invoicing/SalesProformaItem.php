<?php

namespace App\Models\Invoicing;

use App\Models\Product;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\LineTax;
use Illuminate\Database\Eloquent\Model;

class SalesProformaItem extends Model
{
    protected $table = 'invoicing_sales_proforma_items';

    protected $fillable = [
        'sales_proforma_id',
        'product_id',
        'product_name',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'subtotal',
        'tax_rate_id',
        'tax_rate',
        'tax_amount',
        'total',
        'order',
        // AGT DS.120 — sem estes campos a conversão em fatura perdia o motivo
        // de isenção e a linha saía a 0% sem código (rejeição da AGT).
        'tax_country_region',
        'tax_code',
        'tax_exemption_code',
        'tax_exemption_reason',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateTotals();
        });
    }

    public function proforma()
    {
        return $this->belongsTo(SalesProforma::class, 'sales_proforma_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function taxRate()
    {
        return $this->belongsTo(Tax::class, 'tax_rate_id');
    }

    public function calculateTotals()
    {
        $this->subtotal = $this->quantity * $this->unit_price;
        
        if ($this->discount_percent > 0) {
            $this->discount_amount = ($this->subtotal * $this->discount_percent) / 100;
        }
        
        $subtotalAfterDiscount = $this->subtotal - $this->discount_amount;

        // O IVA incide sobre o liquido ACRESCIDO do IEC, tal como na factura
        // (ver SalesInvoiceItem::calculateTotals). Sem isto a linha da
        // proforma apurava o IVA so sobre o liquido e ficava a divergir do
        // cabecalho, que ja soma o IEC a base. O Imposto de Selo nao entra.
        $iec = 0.0;
        if ($this->exists) {
            $iec = (float) LineTax::where('line_type', static::class)
                ->where('line_id', $this->id)
                ->where('tax_type', LineTax::TIPO_IEC)
                ->sum('tax_amount');
        }

        $this->tax_amount = (($subtotalAfterDiscount + $iec) * $this->tax_rate) / 100;
        $this->total = $subtotalAfterDiscount + $this->tax_amount + $iec;
    }
}
