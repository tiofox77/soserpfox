<?php

namespace App\Models\Invoicing;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasAGTSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInvoice extends Model
{
    use SoftDeletes, BelongsToTenant, HasAGTSignature;

    protected $table = 'invoicing_sales_invoices';

    protected $fillable = [
        'tenant_id',
        'proforma_id',
        'invoice_number',
        'local_uuid',
        'payment_method',
        'atcud',
        'invoice_type',
        'invoice_status',
        'invoice_status_date',
        'source_id',
        'source_billing',
        // Origem de negócio (módulo + referência), p.ex. hotel/RES-000123.
        // Não confundir com source_id/source_billing, que são campos SAFT-AO.
        'source_module',
        'source_reference',
        'hash',
        'hash_control',
        'hash_previous',
        'system_entry_date',
        'client_id',
        'warehouse_id',
        'invoice_date',
        'due_date',
        'delivery_date',
        'delivery_location',
        'status',
        'is_service',
        'subtotal',
        'net_total',
        'tax_amount',
        'tax_payable',
        'irt_amount',
        'discount_amount',
        'discount_commercial',
        'discount_financial',
        'total',
        'gross_total',
        'paid_amount',
        'currency',
        'exchange_rate',
        'notes',
        'terms',
        'created_by',
        'saft_hash',
        'jws_signature',
        'jws_document_signature',
        'agt_status',
        'agt_reference',
        'agt_request_id',
        'agt_submission_uuid',
        'agt_submitted_at',
        'agt_validated_at',
        'eac_code',
        'document_status_code',
        'series_id',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'delivery_date' => 'date',
        'invoice_status_date' => 'datetime',
        'system_entry_date' => 'datetime',
        'is_service' => 'boolean',
        'subtotal' => 'decimal:2',
        'net_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_payable' => 'decimal:2',
        'irt_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_commercial' => 'decimal:2',
        'discount_financial' => 'decimal:2',
        'total' => 'decimal:2',
        'gross_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                // O tipo do documento escolhe a série: FR (Fatura-Recibo) usa a
                // MESMA sequência do POS; FT usa a série de faturas.
                $seriesType = static::seriesTypeFor($invoice->invoice_type ?? 'FT');
                $series = InvoicingSeries::getIssuanceSeries(
                    (int) $invoice->tenant_id,
                    $seriesType,
                    $invoice->series_id ? (int) $invoice->series_id : null
                );

                if ($series) {
                    $invoice->invoice_number = $series->getNextNumber();
                    // Ligar o documento à série que o numerou (antes ficava NULL:
                    // 99 faturas sem série, sem rastreio nem ATCUD).
                    if (empty($invoice->series_id)) {
                        $invoice->series_id = $series->id;
                    }
                    if ($series->isAGTRegistered() && empty($invoice->atcud)) {
                        $invoice->atcud = $series->generateATCUD(
                            $series->nextSequentialFromDocumentNumber($invoice->invoice_number)
                        );
                    }
                } else {
                    $invoice->invoice_number = static::generateInvoiceNumber(
                        $invoice->tenant_id,
                        $invoice->invoice_type ?? 'FT'
                    );
                }
            }
            if ($invoice->series_id) {
                $seriesType = static::seriesTypeFor($invoice->invoice_type ?? 'FT');
                $series = InvoicingSeries::getIssuanceSeries(
                    (int) $invoice->tenant_id,
                    $seriesType,
                    (int) $invoice->series_id
                );
                if ($series->isAGTRegistered() && empty($invoice->atcud)) {
                    $invoice->atcud = $series->generateATCUD(
                        $series->nextSequentialFromDocumentNumber($invoice->invoice_number)
                    );
                }
            }
            
            // Define armazém padrão se não especificado
            if (empty($invoice->warehouse_id)) {
                $defaultWarehouse = Warehouse::getDefault($invoice->tenant_id);
                if ($defaultWarehouse) {
                    $invoice->warehouse_id = $defaultWarehouse->id;
                }
            }
        });
    }

    /**
     * Série a usar por tipo de documento (AGT):
     *   FT (Fatura)        → série 'invoice'  (prefixo FT)
     *   FR (Fatura-Recibo) → série 'pos'      (prefixo FR) — MESMA sequência do POS
     */
    public static function seriesTypeFor(?string $invoiceType): string
    {
        return strtoupper((string) $invoiceType) === 'FR' ? 'pos' : 'invoice';
    }

    public static function generateInvoiceNumber($tenantId, ?string $invoiceType = 'FT')
    {
        $seriesType = static::seriesTypeFor($invoiceType);

        // Formato AGT Angola: FT A 2025/000001 | FR A 2025/000001
        $series = InvoicingSeries::getIssuanceSeries((int) $tenantId, $seriesType);

        if ($series) {
            return $series->getNextNumber();
        }

        // Fallback: gerar manualmente com formato AGT Angola
        $year = now()->year;
        $docPrefix = strtoupper((string) $invoiceType) === 'FR' ? 'FR' : 'FT';
        $prefix = $docPrefix . ' A ' . $year . '/';
        
        $maxAttempts = 5;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            // Lock para evitar duplicatas
            $lastInvoice = static::where('tenant_id', $tenantId)
                ->where('invoice_number', 'like', $prefix . '%')
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $lastNumber = $lastInvoice ? ((int) substr($lastInvoice->invoice_number, -6)) : 0;
            $newNumber = $lastNumber + 1;
            $invoiceNumber = $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
            
            \Log::info("Workshop: Gerando número de fatura. Última: " . ($lastInvoice ? $lastInvoice->invoice_number : 'nenhuma') . " → Nova: {$invoiceNumber}");
            
            // Verificar se já existe (segurança extra)
            $exists = static::where('tenant_id', $tenantId)
                ->where('invoice_number', $invoiceNumber)
                ->exists();
            
            if (!$exists) {
                \Log::info("Workshop: Número {$invoiceNumber} disponível!");
                return $invoiceNumber;
            }
            
            // Se existir, incrementar e tentar novamente
            $attempt++;
            \Log::warning("Workshop: Número {$invoiceNumber} JÁ EXISTE! Tentativa {$attempt}/{$maxAttempts}");
            
            // Pequeno delay antes de tentar novamente
            usleep(100000); // 100ms
        }
        
        throw new \Exception("Não foi possível gerar número único de fatura após {$maxAttempts} tentativas.");
    }

    // Relacionamentos
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function proforma()
    {
        return $this->belongsTo(SalesProforma::class, 'proforma_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(SalesInvoiceItem::class, 'sales_invoice_id')->orderBy('order');
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'invoice_id')->where('type', 'sale');
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class, 'invoice_id');
    }

    public function debitNotes()
    {
        return $this->hasMany(DebitNote::class, 'invoice_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function series()
    {
        return $this->belongsTo(InvoicingSeries::class, 'series_id');
    }

    // Métodos
    public function calculateTotals()
    {
        $this->subtotal = $this->items->sum('subtotal');
        $this->tax_amount = $this->items->sum('tax_amount');
        $this->total = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->save();
    }

    public function getBalanceAttribute()
    {
        return $this->total - ($this->paid_amount ?? 0);
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'draft' => __('Rascunho'),
            'pending' => __('Pendente'),
            // Faltavam os dois estados mais usados a seguir a "paga": `sent`
            // (111 facturas) e `credited` (14). Caíam no `default`, que devolve
            // o nome cru da coluna — o ecrã mostrava "Sent" e "Credited", em
            // inglês, com o ícone de estado desconhecido ao lado.
            'sent' => __('Emitida'),
            'partially_paid' => __('Parcialmente Pago'),
            'paid' => __('Pago'),
            'overdue' => __('Atrasado'),
            'credited' => __('Creditada'),
            'cancelled' => __('Cancelado'),
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'draft' => 'gray',
            'pending' => 'yellow',
            'sent' => 'indigo',
            'partially_paid' => 'blue',
            'paid' => 'green',
            'overdue' => 'red',
            // Creditada não é erro nem sucesso: foi anulada por nota de
            // crédito. Cor própria, para não se confundir com cancelada.
            'credited' => 'purple',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    // Métodos SAFT-AO
    public function generateHash()
    {
        $previousHash = self::where('tenant_id', $this->tenant_id)
            ->where('id', '<', $this->id)
            ->whereNotNull('saft_hash')
            ->orderBy('id', 'desc')
            ->value('saft_hash') ?? '';
        
        // Usar SAFTHelper com assinatura RSA-SHA256 (conforme SAFT-AO)
        $hash = \App\Helpers\SAFTHelper::generateHash(
            $this->invoice_date->format('Y-m-d'),
            ($this->system_entry_date ?? now())->format('Y-m-d H:i:s'),
            $this->invoice_number,
            $this->gross_total ?? $this->total,
            $previousHash ?: null
        );
        
        if ($hash) {
            $this->hash = $hash;
            $this->saft_hash = $hash;
            $this->hash_previous = $previousHash;
            $this->hash_control = '1';
            $this->save();
        }
    }

    public function finalizeInvoice()
    {
        $this->invoice_status = 'F';
        $this->invoice_status_date = now();
        $this->source_id = auth()->user()->id ?? 'SYSTEM';
        $this->system_entry_date = $this->system_entry_date ?? now();
        
        // Calcular totais SAFT-AO
        $this->net_total = $this->subtotal;
        $this->tax_payable = $this->tax_amount;
        $this->gross_total = $this->total;
        
        $this->save();
        $this->generateHash();
    }

    public function cancelInvoice($reason = null)
    {
        $this->invoice_status = 'A';
        $this->invoice_status_date = now();
        $this->notes = ($this->notes ? $this->notes . "\n\n" : '') . "ANULADO: " . ($reason ?? 'Sem motivo especificado');
        $this->save();
    }

    public static function validateNIF($nif)
    {
        // NIF em Angola tem 9 ou 14 dígitos
        return preg_match('/^\d{9}(\d{5})?$/', $nif);
    }
}
