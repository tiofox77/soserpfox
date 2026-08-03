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
        'total_invoices',
        'total_receipts',
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

        $byBucket = $transactions->groupBy(fn($t) => $bucket($t->payment_method));

        $this->cash_sales          = (float) ($byBucket->get('cash')?->sum('amount') ?? 0);
        $this->card_sales          = (float) ($byBucket->get('card')?->sum('amount') ?? 0);
        $this->bank_transfer_sales = (float) ($byBucket->get('bank_transfer')?->sum('amount') ?? 0);
        $this->other_sales         = (float) ($byBucket->get('other')?->sum('amount') ?? 0);
        $this->total_sales = $transactions->sum('amount');
        
        $this->total_invoices = $transactions->where('type', 'invoice')->count();
        $this->total_receipts = $transactions->where('type', 'receipt')->count();

        $this->save();
    }

    /**
     * Fechar turno
     */
    public function close(float $actualCash, ?string $notes = null, ?string $differenceReason = null): void
    {
        $this->expected_cash = $this->opening_balance + $this->cash_sales;
        $this->actual_cash = $actualCash;
        $this->cash_difference = $actualCash - $this->expected_cash;
        $this->closing_balance = $actualCash;
        $this->closing_notes = $notes;
        $this->difference_reason = $differenceReason;
        $this->closed_at = now();
        $this->closed_by = auth()->id();
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
            'open' => 'Aberto',
            'closed' => 'Fechado',
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
