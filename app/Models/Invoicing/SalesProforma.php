<?php

namespace App\Models\Invoicing;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\NumeracaoInternaEAgt;

class SalesProforma extends Model
{
    // O número nas duas séries: a interna e a da AGT.
    use NumeracaoInternaEAgt;

    use SoftDeletes, BelongsToTenant;

    protected $table = 'invoicing_sales_proformas';

    protected $fillable = [
        'tenant_id',
        'series_id',
        'proforma_number',
        'client_id',
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
        'saft_hash',
        'created_by',
    ];

    protected $casts = [
        'proforma_date' => 'date',
        'valid_until' => 'date',
        'system_entry_date' => 'datetime',   // sem cast vinha string da BD ao editar
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
            // Gerar número da proforma usando série
            if (empty($proforma->proforma_number)) {
                // Se série foi especificada, usar essa série
                if ($proforma->series_id) {
                    $series = InvoicingSeries::find($proforma->series_id);
                } else {
                    // Buscar série padrão para proformas
                    $series = InvoicingSeries::getDefaultSeries($proforma->tenant_id, 'proforma');
                    
                    // Se encontrou série padrão, associar
                    if ($series) {
                        $proforma->series_id = $series->id;
                    }
                }
                
                // Se encontrou série, usar para gerar número
                if ($series) {
                    $proforma->proforma_number = $series->getNextNumber();
                } else {
                    // Fallback para método antigo se não houver série configurada
                    $proforma->proforma_number = static::generateProformaNumber($proforma->tenant_id);
                }
            }
            
            // Define armazém padrão se não especificado
            if (empty($proforma->warehouse_id)) {
                $defaultWarehouse = Warehouse::getDefault($proforma->tenant_id);
                if ($defaultWarehouse) {
                    $proforma->warehouse_id = $defaultWarehouse->id;
                }
            }
        });
    }

    public static function generateProformaNumber($tenantId)
    {
        $year = now()->year;
        $prefix = 'PF ' . $year . '/';
        
        $lastProforma = static::where('tenant_id', $tenantId)
            ->where('proforma_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastProforma) {
            $lastNumber = (int)str_replace($prefix, '', $lastProforma->proforma_number);
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
        return $this->hasMany(SalesProformaItem::class, 'sales_proforma_id')->orderBy('order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function series()
    {
        return $this->belongsTo(InvoicingSeries::class, 'series_id');
    }

    public function invoices()
    {
        return $this->hasMany(SalesInvoice::class, 'proforma_id');
    }

    public function convertToInvoice()
    {
        $invoice = SalesInvoice::create([
            'tenant_id' => $this->tenant_id,
            'proforma_id' => $this->id,
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
            // Campos fiscais AGT: a proforma pode não os ter (colunas recentes) e
            // uma linha a 0% sem motivo de isenção é rejeitada pela AGT.
            $tx = \App\Services\Invoicing\TaxResolver::forProductId($item->product_id, $this->tenant_id);

            // A TAXA É A DE HOJE, não a que ficou gravada na proforma.
            //
            // A factura é um documento fiscal NOVO, com data de hoje. Uma
            // proforma feita antes de a empresa mudar de regime tem a taxa
            // antiga na linha, e copiá-la fazia nascer hoje uma factura a
            // liquidar IVA que a empresa já não pode cobrar — só corrigível
            // por nota de crédito. Todos os outros caminhos de emissão
            // resolvem a taxa no momento; esta conversão era a excepção.
            //
            // O resolvedor devolve sempre taxa: do artigo, ou a do regime da
            // empresa quando a linha não tem artigo. Não há caso em que a
            // proforma saiba melhor.
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
                // tax_amount e total não se copiam: o hook `saving` da linha
                // recalcula-os a partir da taxa, e copiar os da proforma
                // deixava o imposto antigo ao lado da taxa nova.
                'order' => $item->order,
                // AGT DS.120 — tudo do resolvedor, para a linha não ficar com
                // metade dos campos de um regime e metade do outro.
                'tax_country_region'   => $item->tax_country_region ?? 'AO',
                'tax_code'             => $tx['tax_code'] ?: ($rate > 0 ? 'NOR' : 'ISE'),
                'tax_exemption_code'   => $rate > 0 ? null : $tx['exemption_code'],
                'tax_exemption_reason' => $rate > 0 ? null : $tx['exemption_reason'],
            ]);
        }

        // Os totais do documento saem das linhas já gravadas. O cabeçalho
        // nasceu com os valores da proforma, que podem ter outra taxa.
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

        // Permitir múltiplas conversões - não mudar status
        // $this->update(['status' => 'converted']);

        return $invoice;
    }
}
