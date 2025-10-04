<?php

namespace App\Models\Invoicing;

use App\Models\Tenant;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Invoicing\Tax;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoicingSettings extends Model
{
    use HasFactory;

    protected $table = 'invoicing_settings';

    protected $fillable = [
        'tenant_id',
        'default_warehouse_id',
        'default_client_id',
        'default_supplier_id',
        'default_tax_id',
        'default_currency',
        'default_exchange_rate',
        'default_payment_method',
        'proforma_series',
        'invoice_series',
        'receipt_series',
        'proforma_next_number',
        'invoice_next_number',
        'receipt_next_number',
        'default_tax_rate',
        'default_irt_rate',
        'apply_irt_services',
        'allow_line_discounts',
        'allow_commercial_discount',
        'allow_financial_discount',
        'max_discount_percent',
        'proforma_validity_days',
        'invoice_due_days',
        'auto_print_after_save',
        'show_company_logo',
        'invoice_footer_text',
        'saft_software_cert',
        'saft_product_id',
        'saft_version',
        'default_notes',
        'default_terms',
    ];

    protected $casts = [
        'default_exchange_rate' => 'decimal:4',
        'default_tax_rate' => 'decimal:2',
        'default_irt_rate' => 'decimal:2',
        'max_discount_percent' => 'decimal:2',
        'apply_irt_services' => 'boolean',
        'allow_line_discounts' => 'boolean',
        'allow_commercial_discount' => 'boolean',
        'allow_financial_discount' => 'boolean',
        'auto_print_after_save' => 'boolean',
        'show_company_logo' => 'boolean',
        'proforma_next_number' => 'integer',
        'invoice_next_number' => 'integer',
        'receipt_next_number' => 'integer',
        'proforma_validity_days' => 'integer',
        'invoice_due_days' => 'integer',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function defaultWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function defaultClient()
    {
        return $this->belongsTo(Client::class, 'default_client_id');
    }

    public function defaultSupplier()
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function defaultTax()
    {
        return $this->belongsTo(Tax::class, 'default_tax_id');
    }

    // Helper methods
    public static function forTenant($tenantId)
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'default_currency' => 'AOA',
                'default_tax_rate' => 14.00,
                'default_irt_rate' => 6.50,
                'proforma_series' => 'PRF',
                'invoice_series' => 'FT',
                'receipt_series' => 'RC',
            ]
        );
    }

    public function getNextProformaNumber()
    {
        $number = $this->proforma_next_number;
        $this->increment('proforma_next_number');
        return $this->proforma_series . ' ' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function getNextInvoiceNumber()
    {
        $number = $this->invoice_next_number;
        $this->increment('invoice_next_number');
        return $this->invoice_series . ' ' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function getNextReceiptNumber()
    {
        $number = $this->receipt_next_number;
        $this->increment('receipt_next_number');
        return $this->receipt_series . ' ' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
