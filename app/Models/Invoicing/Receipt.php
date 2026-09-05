<?php

namespace App\Models\Invoicing;

use App\Models\Client;
use App\Models\Supplier;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\NumeracaoInternaEAgt;

class Receipt extends Model
{
    // O número nas duas séries: a interna e a da AGT.
    use NumeracaoInternaEAgt;

    use BelongsToTenant, SoftDeletes;

    protected $table = 'invoicing_receipts';

    protected $fillable = [
        'tenant_id',
        'series_id',
        'atcud',
        'receipt_number',
        'type',
        'invoice_id',
        'purchase_invoice_id',
        'client_id',
        'supplier_id',
        'payment_date',
        'payment_method',
        'amount_paid',
        'remaining_amount',
        'reference',
        'notes',
        'status',
        'saft_hash',
        'hash',
        'hash_previous',
        'hash_control',
        'created_by',
        'jws_document_signature',
        'agt_status',
        'agt_reference',
        'agt_request_id',
        'agt_submission_uuid',
        'agt_submitted_at',
        'agt_validated_at',
        'eac_code',
        'document_status_code',
    ];

    protected $casts = [
        // Sem isto vinha texto cru e o ->format() do ecrã rebentava.
        'agt_submitted_at' => 'datetime',
        'payment_date' => 'date',
        'amount_paid' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    // Relacionamentos
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /** A factura de VENDA que este recibo paga. Null nos recibos de compra. */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    /**
     * A factura de COMPRA que este recibo paga. Null nos recibos de venda.
     *
     * Existe porque `invoice_id` tem chave estrangeira para as facturas de
     * VENDA: escrever lá o id de uma compra fazia a base recusar a linha, e
     * pagar uma factura de compra nunca funcionou.
     */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /** A factura que este recibo paga, seja de que tipo for. */
    public function documento()
    {
        return $this->type === 'purchase' ? $this->purchaseInvoice : $this->invoice;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(InvoicingSeries::class, 'series_id');
    }

    // Scopes
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSales($query)
    {
        return $query->where('type', 'sale');
    }

    public function scopePurchases($query)
    {
        return $query->where('type', 'purchase');
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($receipt) {
            if (empty($receipt->receipt_number)) {
                $receipt->receipt_number = self::generateReceiptNumber(
                    $receipt->type,
                    (int) $receipt->tenant_id,
                    $receipt
                );
            }
            if ($receipt->series_id) {
                $series = InvoicingSeries::getIssuanceSeries(
                    (int) $receipt->tenant_id,
                    'receipt',
                    (int) $receipt->series_id
                );
                if ($series->isAGTRegistered() && empty($receipt->atcud)) {
                    $receipt->atcud = $series->generateATCUD(
                        $series->nextSequentialFromDocumentNumber($receipt->receipt_number)
                    );
                }
            }

            // Definir remaining_amount igual ao amount inicial
            if ($receipt->amount_paid && empty($receipt->remaining_amount)) {
                $receipt->remaining_amount = $receipt->amount_paid;
            }
        });

        // NASCER, MUDAR E MORRER. Só havia o `created`, e punha apenas o
        // ESTADO: um recibo feito pelo ecrã dos recibos não mexia no valor da
        // factura, e um recibo corrigido ou anulado deixava lá dinheiro que já
        // não existe.
        static::created(function ($receipt) {
            $receipt->lancarNaFactura($receipt->documentoPago(), $receipt->valorQueConta());
        });

        static::deleted(function ($receipt) {
            $receipt->lancarNaFactura($receipt->documentoPago(), -$receipt->valorQueConta());
        });

        static::updated(function ($receipt) {
            $facturaAntes = $receipt->type === 'purchase'
                ? $receipt->getOriginal('purchase_invoice_id')
                : $receipt->getOriginal('invoice_id');

            $antes = $receipt->valorQueConta(
                $receipt->getOriginal('status'),
                $receipt->getOriginal('amount_paid')
            );

            $agora = $receipt->valorQueConta();

            // A factura mudou: tira-se tudo da antiga e põe-se na nova.
            if ($facturaAntes != $receipt->documentoPago()) {
                $receipt->lancarNaFactura($facturaAntes, -$antes);
                $receipt->lancarNaFactura($receipt->documentoPago(), $agora);

                return;
            }

            $receipt->lancarNaFactura($receipt->documentoPago(), $agora - $antes);
        });
    }

    // Gerar número de recibo
    public static function generateReceiptNumber($type = 'sale', ?int $tenantId = null, ?self $receipt = null)
    {
        $tenantId = $tenantId ?: (int) activeTenantId();
        if ($type === 'sale') {
            $series = InvoicingSeries::getIssuanceSeries($tenantId, 'receipt');
            if ($series) {
                if ($receipt && empty($receipt->series_id)) {
                    $receipt->series_id = $series->id;
                }
                $number = $series->getNextNumber();
                if ($receipt && $series->isAGTRegistered() && empty($receipt->atcud)) {
                    $receipt->atcud = $series->generateATCUD(
                        $series->nextSequentialFromDocumentNumber($number)
                    );
                }
                return $number;
            }
        }

        $prefix = $type === 'sale' ? 'RV' : 'RC';
        $year = date('Y');
        
        $lastReceipt = self::where('tenant_id', $tenantId)
            ->where('type', $type)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastReceipt ? ((int) substr($lastReceipt->receipt_number, -4)) + 1 : 1;

        return sprintf('%s/%s/%04d', $prefix, $year, $nextNumber);
    }

    /**
     * Quanto é que este recibo conta como dinheiro recebido.
     *
     * Um recibo anulado conta zero. Os argumentos servem para comparar com o
     * que ele valia ANTES de ser alterado.
     */
    public function valorQueConta(?string $estado = null, $valor = null): float
    {
        $estado = $estado ?? $this->status;
        $valor  = $valor ?? $this->amount_paid;

        return $estado === 'issued' ? round((float) $valor, 2) : 0.0;
    }

    /** A factura que este recibo paga — de venda ou de compra. */
    public function documentoPago()
    {
        return $this->type === 'purchase' ? $this->purchase_invoice_id : $this->invoice_id;
    }

    /**
     * Lança uma diferença na factura a que este recibo pertence.
     *
     * Cada tipo na SUA coluna: `invoice_id` é a factura de venda e
     * `purchase_invoice_id` a de compra. Tratar só as vendas deixava o
     * pagamento a fornecedores sem ninguém a marcá-lo.
     */
    public function lancarNaFactura($documentoId, float $diferenca): void
    {
        if (! $documentoId || abs($diferenca) < 0.005) {
            return;
        }

        if ($this->type === 'purchase') {
            \App\Models\Invoicing\PurchaseInvoice::withoutGlobalScopes()
                ->find($documentoId)?->aplicarPagamento($diferenca);

            return;
        }

        SalesInvoice::withoutGlobalScopes()->find($documentoId)?->aplicarPagamento($diferenca);
    }

    /** Compatibilidade: quem chamava isto queria pôr a factura a par. */
    public function updateInvoiceStatus()
    {
        if (!$this->invoice_id || $this->type !== 'sale') {
            return;
        }

        SalesInvoice::withoutGlobalScopes()->find($this->invoice_id)?->acertarEstadoPeloPago();
    }

    // Accessors
    public function getAmountAttribute()
    {
        return $this->amount_paid;
    }

    public function getHashAttribute()
    {
        return $this->saft_hash;
    }

    public function getEntityNameAttribute()
    {
        if ($this->type === 'sale' && $this->client) {
            return $this->client->name;
        } elseif ($this->type === 'purchase' && $this->supplier) {
            return $this->supplier->name;
        }
        return 'N/A';
    }

    public function getPaymentMethodLabelAttribute()
    {
        $methods = [
            'cash' => __('Dinheiro'),
            'transfer' => __('Transferência'),
            'multicaixa' => __('Multicaixa'),
            'tpa' => __('TPA'),
            'check' => __('Cheque'),
            'mbway' => __('MB Way'),
            'other' => __('Outro'),
        ];

        return $methods[$this->payment_method] ?? ucfirst($this->payment_method);
    }

    public function getStatusLabelAttribute()
    {
        $statuses = [
            'issued' => __('Emitido'),
            'cancelled' => __('Cancelado'),
        ];

        return $statuses[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'issued' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    // Cancelar recibo
    public function cancel()
    {
        $this->status = 'cancelled';
        $this->save();

        // Atualizar status da fatura
        if ($this->invoice_id && $this->type === 'sale') {
            $this->updateInvoiceStatus();
        }
    }
}
