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
        'number_format',
        'decimal_places',
        'rounding_mode',
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
        'pos_auto_print',
        'pos_play_sounds',
        'pos_validate_stock',
        'pos_allow_negative_stock',
        'pos_show_product_images',
        'pos_products_per_page',
        'pos_auto_complete_sale',
        'pos_require_customer',
        'pos_default_payment_method_id',
        'agt_environment',
        'agt_api_base_url',
        'agt_client_id',
        'agt_client_secret',
        'agt_access_token',
        'agt_token_expires_at',
        'agt_auto_submit',
        'agt_require_validation',
        'agt_software_certificate',
    ];

    protected $casts = [
        'default_exchange_rate' => 'decimal:4',
        'default_tax_rate' => 'decimal:2',
        'default_irt_rate' => 'decimal:2',
        'max_discount_percent' => 'decimal:2',
        'decimal_places' => 'integer',
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
        'pos_auto_print' => 'boolean',
        'pos_play_sounds' => 'boolean',
        'pos_validate_stock' => 'boolean',
        'pos_allow_negative_stock' => 'boolean',
        'pos_show_product_images' => 'boolean',
        'pos_products_per_page' => 'integer',
        'pos_auto_complete_sale' => 'boolean',
        'pos_require_customer' => 'boolean',
        'agt_auto_submit' => 'boolean',
        'agt_require_validation' => 'boolean',
        'agt_token_expires_at' => 'datetime',
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
                // Moeda e Câmbio
                'default_currency' => 'AOA',
                'default_exchange_rate' => 1.0000,
                'default_payment_method' => 'dinheiro',
                
                // Formato de Números
                'number_format' => 'angola',
                'decimal_places' => 2,
                'rounding_mode' => 'normal',
                
                // Séries
                'proforma_series' => 'PRF',
                'invoice_series' => 'FT',
                'receipt_series' => 'RC',
                'proforma_next_number' => 1,
                'invoice_next_number' => 1,
                'receipt_next_number' => 1,
                
                // Impostos
                'default_tax_rate' => 14.00,
                'default_irt_rate' => 6.50,
                'apply_irt_services' => true,
                
                // Descontos
                'allow_line_discounts' => true,
                'allow_commercial_discount' => true,
                'allow_financial_discount' => true,
                'max_discount_percent' => 100.00,
                
                // Validade
                'proforma_validity_days' => 30,
                'invoice_due_days' => 30,
                
                // Impressão
                'auto_print_after_save' => false,
                'show_company_logo' => true,
                
                // SAFT
                'saft_version' => '1.0.0',
                
                // POS - Ponto de Venda
                'pos_auto_print' => true,
                'pos_play_sounds' => true,
                'pos_validate_stock' => true,
                'pos_allow_negative_stock' => false,
                'pos_show_product_images' => true,
                'pos_products_per_page' => 12,
                'pos_auto_complete_sale' => false,
                'pos_require_customer' => false,
                'pos_default_payment_method_id' => null, // Será configurado pelo usuário
            ]
        );
    }
    
    /**
     * Relacionamento com método de pagamento padrão do POS
     */
    public function posDefaultPaymentMethod()
    {
        return $this->belongsTo(\App\Models\Treasury\PaymentMethod::class, 'pos_default_payment_method_id');
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
