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
    public static function generateShiftNumber(): string
    {
        $year = now()->year;
        $lastShift = self::where('shift_number', 'like', "POS-{$year}-%")
            ->orderBy('shift_number', 'desc')
            ->first();

        if ($lastShift) {
            $lastNumber = (int) substr($lastShift->shift_number, -3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return 'POS-' . $year . '-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
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

        $this->cash_sales = $transactions->where('payment_method', 'cash')->sum('amount');
        $this->card_sales = $transactions->where('payment_method', 'card')->sum('amount');
        $this->bank_transfer_sales = $transactions->where('payment_method', 'bank_transfer')->sum('amount');
        $this->other_sales = $transactions->whereNotIn('payment_method', ['cash', 'card', 'bank_transfer'])->sum('amount');
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
