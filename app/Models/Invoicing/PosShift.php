<?php

namespace App\Models\Invoicing;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosShift extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'invoicing_pos_shifts';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'shift_number',
        'status',
        'opened_at',
        'closed_at',
        'opening_balance',
        'opening_notes',
        'cash_sales',
        'card_sales',
        'bank_transfer_sales',
        'other_sales',
        'total_sales',
        'credit_notes_amount',
        'total_invoices',
        'total_receipts',
        'total_credit_notes',
        'expected_cash',
        'actual_cash',
        'cash_difference',
        'closing_balance',
        'closing_notes',
        'difference_reason',
        'closed_by',
        'opened_ip',
        'closed_ip',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_balance' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'card_sales' => 'decimal:2',
        'bank_transfer_sales' => 'decimal:2',
        'other_sales' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'credit_notes_amount' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'closing_balance' => 'decimal:2',
    ];

    // Relacionamentos
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'closed_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PosShiftTransaction::class, 'shift_id');
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Métodos auxiliares
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Gerar número do turno
     */
    public static function generateShiftNumber(?int $tenantId = null): string
    {
        $year = now()->year;

        // Sequência POR TENANT. O índice único é (tenant_id, shift_number), por isso
        // cada tenant tem a sua própria contagem (POS-{ano}-NNN) sem colidir com outros.
        $query = self::query()->withTrashed(); // turnos soft-deleted ainda ocupam o número no índice
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        // Ordenação NUMÉRICA pelo sufixo (robusto acima de 999 e a sufixos de tamanho variável).
        $lastShift = $query->where('shift_number', 'like', "POS-{$year}-%")
            ->orderByRaw('CAST(SUBSTRING_INDEX(shift_number, "-", -1) AS UNSIGNED) DESC')
            ->first();

        $newNumber = $lastShift
            ? ((int) substr(strrchr($lastShift->shift_number, '-'), 1)) + 1
            : 1;

        return 'POS-' . $year . '-' . str_pad((string) $newNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Cria um turno de forma ATÓMICA e à prova de concorrência.
     *
     * Dois operadores do mesmo tenant a abrir turno no mesmo instante leriam o mesmo
     * "último número". O índice único (tenant_id, shift_number) rejeita o duplicado (1062);
     * aqui regeneramos o número e tentamos de novo, em vez de devolver erro ao utilizador.
     * Nunca duplica, nunca corrompe a sequência, nunca causa conflito visível.
     *
     * @param array    $attributes  atributos do turno SEM shift_number (é gerado aqui)
     * @param int|null $tenantId    tenant a usar para a sequência (default: o dos atributos)
     */
    public static function createSafely(array $attributes, ?int $tenantId = null): self
    {
        $tenantId = $tenantId ?? ($attributes['tenant_id'] ?? null);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $attributes['shift_number'] = self::generateShiftNumber($tenantId);
            try {
                return self::create($attributes);
            } catch (\Illuminate\Database\QueryException $e) {
                // 1062 = Duplicate entry. Só repetir se o conflito for no número do turno.
                $isDuplicateShiftNumber = ($e->errorInfo[1] ?? null) === 1062
                    && str_contains($e->getMessage(), 'shift_number');
                if ($isDuplicateShiftNumber && $attempt < 6) {
                    usleep(random_int(15000, 70000)); // 15-70ms de backoff antes de regerar
                    continue;
                }
                throw $e;
            }
        }

        throw new \RuntimeException('Não foi possível gerar um número de turno único após várias tentativas.');
    }

    /**
     * O TURNO ABERTO DE UM OPERADOR — ou nenhum.
     *
     * Quem tem turno aberto é o caixa: o dinheiro dos documentos que emite
     * passa pela gaveta dele. É a mesma pergunta que a venda já fazia (ver
     * `PosSaleService::linkToOpenShift`), agora num sítio só.
     */
    public static function abertoDe(int $tenantId, ?int $userId): ?self
    {
        if (! $userId) {
            return null;
        }

        return static::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /**
     * UMA DEVOLUÇÃO — dinheiro a SAIR da gaveta.
     *
     * O valor entra negativo de propósito: é o que faz o balde do meio de
     * pagamento descer e o «Dinheiro Esperado» bater certo com o que lá está.
     *
     * O MEIO DE PAGAMENTO É O DA VENDA e não uma escolha nova: o dinheiro
     * volta pelo caminho por onde veio. Vem, por esta ordem: do movimento
     * DESTE turno que registou a venda (o mais fiável — foi esta gaveta que o
     * recebeu), depois do que ficou gravado na factura, e, quando nenhum dos
     * dois se sabe, `other` — que é o balde que NÃO mexe no dinheiro esperado.
     * Não saber é razão para não tocar na contagem, não para adivinhar.
     */
    public function registarNotaDeCredito(\App\Models\Invoicing\CreditNote $nota): ?PosShiftTransaction
    {
        $valor = round((float) $nota->total, 2);

        if ($valor <= 0) {
            return null;
        }

        $factura = $nota->invoice;

        $daVenda = $factura
            ? $this->transactions()
                ->where('type', 'invoice')
                ->where('reference_id', $factura->id)
                ->value('payment_method')
            : null;

        return $this->addTransaction([
            'type' => 'credit_note',
            'reference_type' => \App\Models\Invoicing\CreditNote::class,
            'reference_id' => $nota->id,
            'reference_number' => $nota->credit_note_number,
            'payment_method' => $daVenda ?: ($factura?->payment_method ?: 'other'),
            'amount' => -$valor,
            'description' => __('Devolução :nota', ['nota' => $nota->credit_note_number]),
            'metadata' => [
                'invoice_id' => $factura?->id,
                'invoice_number' => $factura?->invoice_number,
                // Se a venda foi neste turno, a devolução é da gaveta com
                // certeza. Se não foi, o meio veio da factura — e fica dito.
                'venda_deste_turno' => $daVenda !== null,
            ],
        ]);
    }

    /**
     * Adicionar transação ao turno
     */
    public function addTransaction(array $data): ?PosShiftTransaction
    {
        // Verificar se o turno está aberto
        if ($this->status !== 'open') {
            \Log::warning('Tentativa de adicionar transação em turno fechado', [
                'shift_id' => $this->id,
                'shift_number' => $this->shift_number,
                'status' => $this->status,
            ]);
            throw new \Exception('Não é possível adicionar transação em turno fechado.');
        }

        try {
            $transaction = $this->transactions()->create([
                'tenant_id' => $this->tenant_id,
                'type' => $data['type'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            \Log::info('Transação adicionada ao turno', [
                'shift_id' => $this->id,
                'shift_number' => $this->shift_number,
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'payment_method' => $transaction->payment_method,
            ]);

            // Atualizar totais do turno
            $this->recalculateTotals();

            return $transaction;
        } catch (\Exception $e) {
            \Log::error('Erro ao adicionar transação ao turno', [
                'shift_id' => $this->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Recalcular totais do turno
     */
    public function recalculateTotals(): void
    {
        $transactions = $this->transactions()->get();

        // Classificação canónica e INSENSÍVEL a maiúsculas: as transações guardam
        // o código do método de tesouraria ('tpa', 'MCX', 'DEBIT', 'CHECK'…), pelo
        // que a comparação exata antiga mandava quase tudo para "outros" e o
        // "Dinheiro Esperado" saía muito abaixo do que estava na gaveta.
        $bucket = function ($method): string {
            $m = strtolower(trim((string) $method));
            if ($m === '') {
                return 'other';
            }
            $is = fn(array $needles) => (bool) array_filter($needles, fn($n) => str_contains($m, $n));

            if ($is(['cash', 'dinheiro', 'numerar', 'especie', 'espécie'])) {
                return 'cash';
            }
            if ($is(['transfer', 'transferen', 'wire', 'iban'])) {
                return 'bank_transfer';
            }
            if ($is(['card', 'cartao', 'cartão', 'tpa', 'mcx', 'multicaixa', 'debit', 'credit', 'visa', 'pos'])) {
                return 'card';
            }
            return 'other';
        };

        /*
         * OS BALDES CONTAM TUDO, DEVOLUÇÕES INCLUÍDAS.
         *
         * É deles que sai o «Dinheiro Esperado», e uma devolução paga da
         * gaveta tira dinheiro dela: o movimento da nota de crédito é
         * NEGATIVO, e por isso o balde certo desce sozinho. Antes não havia
         * movimento nenhum e o esperado ficava alto — o operador acabava o
         * turno com uma falta que não era dele.
         */
        /*
         * AS ENTRADAS E SAÍDAS DA GAVETA NÃO SÃO VENDAS (23/09/2026).
         *
         * Uma recolha do gerente ou uma despesa paga da gaveta (ver
         * App\Services\POS\GavetaDoTurno) mexem no dinheiro esperado, mas não
         * no que se vendeu: ficam fora dos baldes e do total vendido, e contam
         * à parte em `movimentosDaGaveta()`.
         */
        /*
         * E OS DOCUMENTOS A PRAZO TAMBÉM NÃO (26/09/2026): a factura por pagar,
         * a nota de débito e a nota de crédito sem devolução que o operador
         * emitiu pelos Documentos saem no fecho, mas nenhum passou pela gaveta
         * nem é dinheiro recebido. Contam à parte em `documentosAPrazo()`.
         */
        $transactions = $transactions->whereNotIn('type', [...self::TIPOS_DA_GAVETA, ...self::TIPOS_A_PRAZO, ...self::TIPOS_DE_COMPRA]);

        $byBucket = $transactions->groupBy(fn($t) => $bucket($t->payment_method));

        $this->cash_sales          = (float) ($byBucket->get('cash')?->sum('amount') ?? 0);
        $this->card_sales          = (float) ($byBucket->get('card')?->sum('amount') ?? 0);
        $this->bank_transfer_sales = (float) ($byBucket->get('bank_transfer')?->sum('amount') ?? 0);
        $this->other_sales         = (float) ($byBucket->get('other')?->sum('amount') ?? 0);

        /*
         * BRUTO E DEVOLVIDO SÃO DOIS NÚMEROS, e não um só.
         *
         * `total_sales` continua a querer dizer o que sempre quis — o que se
         * vendeu — e o devolvido fica à parte, em positivo, porque é assim que
         * se lê («devolveram-se 50.000», e não «vendeu-se menos 50.000»). O
         * líquido é a subtracção dos dois, e é o mesmo vocabulário que o
         * relatório de vendas do POS já usava.
         */
        $devolucoes = $transactions->where('type', 'credit_note');

        $this->total_sales = $transactions->where('type', '!=', 'credit_note')->sum('amount');
        $this->credit_notes_amount = abs((float) $devolucoes->sum('amount'));

        $this->total_invoices = $transactions->where('type', 'invoice')->count();
        $this->total_receipts = $transactions->where('type', 'receipt')->count();
        $this->total_credit_notes = $devolucoes->count();

        $this->save();
    }

    /** O que ficou de facto — o que se vendeu menos o que se devolveu. */
    public function getNetSalesAttribute(): float
    {
        return (float) $this->total_sales - (float) $this->credit_notes_amount;
    }

    /** Os tipos que só mexem na gaveta (ver App\Services\POS\GavetaDoTurno). */
    public const TIPOS_DA_GAVETA = ['withdrawal', 'deposit'];

    /** Os documentos que saem no fecho sem mexer na gaveta (ver App\Services\POS\DocumentosNoTurno). */
    public const TIPOS_A_PRAZO = ['a_prazo'];

    /**
     * As facturas de compra do turno: no fecho, fora das vendas. O pagamento
     * delas sai da gaveta como `withdrawal` (GavetaDoTurno), não por aqui.
     */
    public const TIPOS_DE_COMPRA = ['compra'];

    /**
     * As facturas de compra do turno: quantas e quanto somam.
     *
     * @return array{quantos: int, valor: float}
     */
    public function comprasDoTurno(): array
    {
        $linhas = $this->transactions()->withoutGlobalScopes()->whereIn('type', self::TIPOS_DE_COMPRA)->get(['amount']);

        return ['quantos' => $linhas->count(), 'valor' => round((float) $linhas->sum('amount'), 2)];
    }

    /**
     * Os documentos a prazo do turno: quantos e quanto somam (a NC desconta).
     *
     * @return array{quantos: int, valor: float}
     */
    public function documentosAPrazo(): array
    {
        $linhas = $this->transactions()->withoutGlobalScopes()->whereIn('type', self::TIPOS_A_PRAZO)->get(['amount']);

        return ['quantos' => $linhas->count(), 'valor' => round((float) $linhas->sum('amount'), 2)];
    }

    /**
     * O que saiu e entrou na gaveta fora das vendas, os dois em positivo.
     *
     * @return array{saidas: float, entradas: float}
     */
    public function movimentosDaGaveta(): array
    {
        $linhas = $this->transactions()->withoutGlobalScopes()->whereIn('type', self::TIPOS_DA_GAVETA)->get(['type', 'amount']);

        return [
            'saidas' => round(abs((float) $linhas->where('type', 'withdrawal')->sum('amount')), 2),
            'entradas' => round((float) $linhas->where('type', 'deposit')->sum('amount'), 2),
        ];
    }

    /**
     * O DINHEIRO QUE DEVIA ESTAR NA GAVETA: o fundo, mais o que entrou em
     * numerário pelas vendas (já sem as devoluções), mais as entradas e menos
     * as saídas que a tesouraria fez na gaveta durante o turno.
     */
    public function dinheiroEsperado(): float
    {
        $g = $this->movimentosDaGaveta();

        return round((float) $this->opening_balance + (float) $this->cash_sales + $g['entradas'] - $g['saidas'], 2);
    }

    /**
     * Fechar turno
     */
    public function close(
        float $actualCash,
        ?string $notes = null,
        ?string $differenceReason = null,
        ?int $closedBy = null
    ): void
    {
        /*
         * OS TOTAIS DE NOVO, a partir dos movimentos, antes de os comparar.
         *
         * Cada venda recalcula-os, mas dentro da sua transacção, com a
         * fotografia que tirou ao começar: duas vendas ao mesmo segundo e a
         * segunda gravava o total sem a primeira. A venda seguinte corrigia;
         * se o par era o último antes do fecho, a gaveta dava uma sobra que
         * não existia. O fecho corre depois de tudo gravado.
         */
        $this->recalculateTotals();

        $this->expected_cash = $this->dinheiroEsperado();
        $this->actual_cash = $actualCash;
        $this->cash_difference = $actualCash - $this->expected_cash;
        $this->closing_balance = $actualCash;
        $this->closing_notes = $notes;
        $this->difference_reason = $differenceReason;
        $this->closed_at = now();
        // No PWA offline, a sessao HTTP e a do aparelho, mas quem fechou foi
        // o operador validado pelo PIN e carimbado no trabalho da fila.
        $this->closed_by = $closedBy ?: auth()->id();
        $this->closed_ip = request()->ip();
        $this->status = 'closed';
        
        $this->save();
    }

    /**
     * Duração do turno em horas
     */
    public function getDurationAttribute(): ?float
    {
        if (!$this->opened_at) {
            return null;
        }

        $end = $this->closed_at ?? now();
        return round($this->opened_at->diffInMinutes($end) / 60, 2);
    }

    /**
     * Status formatado
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'open' => __('Aberto'),
            'closed' => __('Fechado'),
            default => $this->status,
        };
    }

    /**
     * Cor do status
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'open' => 'green',
            'closed' => 'gray',
            default => 'gray',
        };
    }
}
