<?php

namespace App\Models\Invoicing;

use App\Models\Product;
use App\Models\Invoicing\Tax;
use Illuminate\Database\Eloquent\Model;

class SalesInvoiceItem extends Model
{
    protected $table = 'invoicing_sales_invoice_items';

    protected $fillable = [
        'sales_invoice_id',
        'product_id',
        'product_name',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'unit_price_base',
        'discount_percent',
        'discount_amount',
        'subtotal',
        'tax_rate_id',
        'tax_rate',
        'tax_amount',
        'total',
        'order',
        // AGT v1.2
        'debit_amount',
        'credit_amount',
        'settlement_amount',
        'eac_code',
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

    public function invoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
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

        // O IVA incide sobre o líquido ACRESCIDO do IEC (Imposto Especial de
        // Consumo), que vive em invoicing_line_taxes. Sem isto o hook 'saving'
        // recalculava o IVA só sobre o líquido e sobrepunha o valor correcto
        // que a emissão tinha apurado — a AGT recusava com "taxContribution
        // não corresponde ao imposto apurado". O Imposto de Selo não entra
        // nesta base.
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
