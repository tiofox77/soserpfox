<?php

namespace App\Models\Invoicing;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasAGTSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\NumeracaoInternaEAgt;

class SalesInvoice extends Model
{
    // O número nas duas séries: a interna e a da AGT.
    use NumeracaoInternaEAgt;

    use SoftDeletes, BelongsToTenant, HasAGTSignature;

    protected $table = 'invoicing_sales_invoices';

    protected $fillable = [
        'tenant_id',
        'proforma_id',
        'quote_id',
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
        // O que o cliente entregou ao balcão, troco incluído (só no POS).
        'amount_received',
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
        // Sem isto vinha texto cru e o ->format() do ecrã rebentava.
        'agt_submitted_at' => 'datetime',
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
        'amount_received' => 'decimal:2',
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

    public function quote()
    {
        return $this->belongsTo(SalesQuote::class, 'quote_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Lança um pagamento (ou a sua anulação) nesta factura.
     *
     * POR DIFERENÇA, como num livro de contas — e não recalculando o total a
     * partir dos recibos. A diferença importa muito: há facturas antigas com o
     * pagamento registado por outro caminho (o balcão, o modal de antes de
     * haver recibos, uma importação) e um recibo pequeno por cima. Recalcular
     * do zero apagava-lhes o pagamento verdadeiro — em produção seriam 6,8
     * milhões de Kz numa só empresa.
     *
     * @param  float  $diferenca  positiva ao receber, negativa ao anular
     */
    public function aplicarPagamento(float $diferenca): void
    {
        if (abs($diferenca) < 0.005) {
            return;
        }

        $this->paid_amount = max(0, round((float) $this->paid_amount + $diferenca, 2));

        $this->acertarEstadoPeloPago();
    }

    /**
     * Põe o estado de acordo com o que já está pago.
     *
     * Não inventa estados: se não há nada pago, uma factura `sent` continua
     * `sent`, e só se desfaz o `paid`/`partially_paid` que isto próprio pôs.
     */
    public function acertarEstadoPeloPago(): void
    {
        $pago  = round((float) $this->paid_amount, 2);
        $total = round((float) $this->total, 2);

        if ($pago > 0 && $pago >= $total - 0.01) {
            $this->status = 'paid';
        } elseif ($pago > 0.01) {
            $this->status = 'partially_paid';
        } elseif (in_array($this->status, ['paid', 'partially_paid'], true)) {
            $this->status = 'sent';
        }

        $this->save();
    }

    /**
     * O que os recibos e adiantamentos desta factura explicam.
     *
     * Serve para diagnóstico e para o acerto do passado, que só ACRESCENTA o
     * que ficou por registar — nunca tira.
     */
    public function pagamentoExplicadoPorDocumentos(): float
    {
        $recibos = \App\Models\Invoicing\Receipt::where('invoice_id', $this->id)
            ->where('status', 'issued')
            ->sum('amount_paid');

        $adiantado = \App\Models\Invoicing\AdvanceUsage::where('invoice_id', $this->id)
            ->where('invoice_type', 'SalesInvoice')
            ->sum('amount_used');

        return round((float) $recibos + (float) $adiantado, 2);
    }
    public function items()
    {
        return $this->hasMany(SalesInvoiceItem::class, 'sales_invoice_id')->orderBy('order');
    }

    /** Formas de pagamento (multi-tender no POS). */
    public function payments()
    {
        return $this->hasMany(SalePayment::class, 'sales_invoice_id');
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'invoice_id')->where('type', 'sale');
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class, 'invoice_id');
    }

    /**
     * Traz o já-creditado na mesma consulta, para as listas.
     *
     * Sem isto, perguntar `porCreditar()` linha a linha numa página de 15
     * facturas são 15 consultas. Com isto é uma.
     */
    public function scopeComCreditado($query)
    {
        return $query->withSum([
            'creditNotes as creditado_total' => fn ($q) => $q->whereNotIn('status', ['draft', 'cancelled']),
        ], 'total');
    }

    /**
     * As duas notas na mesma consulta, para quem lê o SALDO de várias
     * facturas seguidas. Sem isto, cada linha de um mapa de dívida faz duas
     * consultas suas — ver a nota do `porCreditar`.
     */
    public function scopeComNotas($query)
    {
        return $query->withSum([
            'creditNotes as creditado_total' => fn ($q) => $q->whereNotIn('status', ['draft', 'cancelled']),
        ], 'total')->withSum([
            'debitNotes as debitado_total' => fn ($q) => $q->whereNotIn('status', ['draft', 'cancelled']),
        ], 'total');
    }

    /**
     * O que as notas já mudaram nesta factura: o que se anulou e o que se
     * acrescentou. Usa o que veio na consulta quando veio.
     *
     * @return array{0: float, 1: float}
     */
    public function efeitoDasNotas(): array
    {
        $creditado = array_key_exists('creditado_total', $this->getAttributes())
            ? (float) $this->creditado_total
            : (float) CreditNote::where('invoice_id', $this->id)
                ->whereNotIn('status', ['draft', 'cancelled'])->sum('total');

        $debitado = array_key_exists('debitado_total', $this->getAttributes())
            ? (float) $this->debitado_total
            : (float) DebitNote::where('invoice_id', $this->id)
                ->whereNotIn('status', ['draft', 'cancelled'])->sum('total');

        return [round($creditado, 2), round($debitado, 2)];
    }

    /**
     * Quanto desta factura ainda se pode anular por nota de crédito.
     *
     * FONTE ÚNICA. A pergunta «esta factura ainda dá para creditar?» aparece em
     * quatro sítios — o botão da lista, o arranque do ecrã da nota, o selector
     * de facturas e o travão de gravação — e tem de ter sempre a mesma
     * resposta. Enquanto a soma vivia dentro do ecrã da nota, a lista continuou
     * a convidar a creditar uma factura já inteiramente creditada e só se
     * descobria no fim, depois de a nota estar toda preenchida.
     *
     * Conta o que TODAS as notas já tiraram, não só a que se está a fazer:
     * duas notas parciais passam o total sem que nenhuma delas, sozinha, o
     * pareça. E conta pelo avesso — fica de fora o que ainda não existe
     * (rascunho) e o que já não existe (cancelada) — para que um estado novo
     * não passe a contar sem ninguém dar por isso.
     */
    public function porCreditar(?int $exceptoNota = null): float
    {
        /*
         * A nota que se está a editar não se conta a si própria — senão a
         * factura que ela anulou por inteiro parece sem saldo para a corrigir.
         *
         * E a pergunta é se a soma VEIO na consulta, não se ela é diferente de
         * zero: o `withSum` devolve NULL quando a factura não tem nota nenhuma,
         * que é o caso da esmagadora maioria. Perguntar `!== null` mandava
         * justamente essas de volta à base, uma a uma — o N+1 que o `scope`
         * existe para evitar.
         */
        $jaAnulado = ($exceptoNota === null && array_key_exists('creditado_total', $this->getAttributes()))
            ? (float) $this->creditado_total
            : CreditNote::where('invoice_id', $this->id)
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->when($exceptoNota, fn ($q) => $q->where('id', '!=', $exceptoNota))
                ->sum('total');

        return round((float) $this->total - (float) $jaAnulado, 2);
    }

    /**
     * Já não há nada para anular.
     *
     * O cêntimo de tolerância é o mesmo que o travão da gravação usa: uma
     * factura de 2.550.698,08 anulada por 2.550.698,08 não pode ficar com
     * «0,004 por creditar» por causa de um arredondamento.
     */
    public function jaTotalmenteCreditada(?int $exceptoNota = null): bool
    {
        return $this->porCreditar($exceptoNota) <= 0.01;
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

    // ── Numeração: interna (série gravada) vs AGT ────────────────────────────
    //
    // O `invoice_number` guarda UM número — o que foi emitido/assinado, que
    // depois do registo da série passa a usar o código da AGT (ex.: FT
    // FT0426S72207N/000001). A série interna (series_code, ex.: SOSFT) fica na
    // série ligada. Para as listagens e o preview mostramos as DUAS: a interna
    // primeiro, por ser a que a empresa reconhece e procura, e a da AGT a
    // seguir. O número fiscal a valer continua a ser o `invoice_number`.

    // Métodos
    public function calculateTotals()
    {
        $this->subtotal = $this->items->sum('subtotal');
        $this->tax_amount = $this->items->sum('tax_amount');
        $this->total = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->save();
    }

    /**
     * O QUE ESTA FACTURA AINDA DEVE — notas incluídas.
     *
     * Era só `total - paid_amount`, e as notas ficavam de fora das duas
     * pontas: creditar metade de uma factura não lhe tirava um cêntimo à
     * dívida, e uma nota de DÉBITO não lhe acrescentava nada — o
     * `DebitNote::updateInvoiceBalance()` somava as notas e deitava fora o
     * resultado.
     *
     * O extracto de conta corrente sempre contou as duas (ver a
     * `ContaCorrenteQuery`): a dívida do mesmo cliente dava dois números
     * conforme o ecrã que se abrisse. Agora dá um.
     */
    public function getBalanceAttribute()
    {
        [$creditado, $debitado] = $this->efeitoDasNotas();

        return max(0, round(
            (float) $this->total - (float) ($this->paid_amount ?? 0) - $creditado + $debitado,
            2,
        ));
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
