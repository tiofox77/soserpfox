<?php

namespace App\Models\Invoicing;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Orçamento de venda (ORC).
 *
 * Documento comercial, NÃO fiscal: sem série, sem hash SAFT-AO, sem AGT. O
 * número é gerado por empresa no formato ORC-AAAA-NNNNNN. Quando o cliente
 * aceita, convertToInvoice() dá origem a uma factura de venda — essa sim,
 * fiscal, com a taxa resolvida no momento da emissão.
 */
class SalesQuote extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'invoicing_sales_quotes';

    protected $fillable = [
        'tenant_id',
        'quote_number',
        'client_id',
        'warehouse_id',
        'quote_date',
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
        'quote_template_id',
        'campos_proposta',
    ];

    protected $casts = [
        'quote_date' => 'date',
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
        // O que quem fez o orçamento escreveu nos campos livres do modelo.
        'campos_proposta' => 'array',
    ];

    public function modelo()
    {
        return $this->belongsTo(QuoteTemplate::class, 'quote_template_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quote) {
            if (empty($quote->quote_number)) {
                $quote->quote_number = static::generateQuoteNumber($quote->tenant_id);
            }

            // Armazém padrão se não especificado (só relevante quando há artigos
            // físicos; um orçamento de serviços dispensa-o).
            if (empty($quote->warehouse_id)) {
                $defaultWarehouse = Warehouse::getDefault($quote->tenant_id);
                if ($defaultWarehouse) {
                    $quote->warehouse_id = $defaultWarehouse->id;
                }
            }
        });
    }

    /**
     * Número sequencial por empresa: ORC-AAAA-NNNNNN.
     *
     * Sem série fiscal de propósito — o orçamento não é documento da AGT. A
     * sequência é por tenant e por ano; a unicidade fica garantida pelo índice
     * composto (tenant_id, quote_number).
     */
    public static function generateQuoteNumber($tenantId)
    {
        $year = now()->year;
        $prefix = 'ORC-' . $year . '-';

        $last = static::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('quote_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($last) {
            $lastNumber = (int) str_replace($prefix, '', $last->quote_number);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(SalesQuoteItem::class, 'sales_quote_id')->orderBy('order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoices()
    {
        return $this->hasMany(SalesInvoice::class, 'quote_id');
    }

    /**
     * Converter em factura de venda (documento fiscal).
     *
     * A TAXA é a de HOJE, resolvida pelo TaxResolver — nunca a que ficou
     * gravada no orçamento. Um orçamento feito antes de a empresa mudar de
     * regime tem a taxa antiga na linha; copiá-la faria nascer hoje uma
     * factura a liquidar IVA que já não se pode cobrar. Mesma regra da
     * conversão da proforma.
     */
    public function convertToInvoice()
    {
        $invoice = SalesInvoice::create([
            'tenant_id' => $this->tenant_id,
            'quote_id' => $this->id,
            'client_id' => $this->client_id,
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
            'created_by' => auth()->id(),
        ]);

        foreach ($this->items as $item) {
            $tx = \App\Services\Invoicing\TaxResolver::forProductId($item->product_id, $this->tenant_id);
            $rate = (float) $tx['rate'];

            SalesInvoiceItem::create([
                'sales_invoice_id' => $invoice->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'discount_amount' => $item->discount_amount,
                'subtotal' => $item->subtotal,
                'tax_rate_id' => $item->tax_rate_id,
                'tax_rate' => $rate,
                // tax_amount e total recalculam-se no hook saving da linha a
                // partir da taxa nova; copiar os do orçamento deixava o imposto
                // antigo ao lado da taxa nova.
                'order' => $item->order,
                'tax_country_region'   => $item->tax_country_region ?? 'AO',
                'tax_code'             => $tx['tax_code'] ?: ($rate > 0 ? 'NOR' : 'ISE'),
                'tax_exemption_code'   => $rate > 0 ? null : $tx['exemption_code'],
                'tax_exemption_reason' => $rate > 0 ? null : $tx['exemption_reason'],
            ]);
        }

        // Totais do cabeçalho saem das linhas já gravadas (taxa de hoje).
        $invoice->load('items');
        $subtotal = (float) $invoice->items->sum('subtotal');
        $desconto = (float) $invoice->items->sum('discount_amount');
        $imposto = (float) $invoice->items->sum('tax_amount');

        $invoice->update([
            'subtotal'        => $subtotal,
            'discount_amount' => $desconto,
            'tax_amount'      => $imposto,
            'total'           => $subtotal - $desconto + $imposto,
        ]);

        return $invoice;
    }
}
