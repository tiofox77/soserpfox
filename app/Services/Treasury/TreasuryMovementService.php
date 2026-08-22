<?php

namespace App\Services\Treasury;

use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Transaction;
use Illuminate\Support\Facades\DB;

/** Fonte única para gravar movimentos e actualizar o dinheiro físico/bancário. */
class TreasuryMovementService
{
    public function destination(PaymentMethod $method, int $tenantId, ?int $accountId = null, ?int $cashId = null, ?int $userId = null): array
    {
        if ($method->type === 'cash') {
            $cashId = $cashId ?: $method->default_cash_register_id;
            if (!$cashId && $userId) {
                $cashId = CashRegister::withoutGlobalScopes()->where('tenant_id', $tenantId)
                    ->where('user_id', $userId)->where('is_active', true)->where('status', 'open')
                    ->orderByDesc('is_default')->value('id');
            }
            $cash = CashRegister::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where('is_active', true)->where('status', 'open')
                ->when($cashId, fn ($q) => $q->whereKey($cashId))
                ->orderByDesc('is_default')->first();

            if (!$cash) {
                // Compatibilidade com empresas antigas ainda sem caixa. O
                // dashboard assinala o movimento como "por classificar"; não
                // se pode bloquear uma venda já emitida por falta de setup.
                return ['account_id' => null, 'cash_register_id' => null];
            }
            return ['account_id' => null, 'cash_register_id' => $cash->id];
        }

        $accountId = $accountId ?: $method->default_account_id;
        $account = Account::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($accountId, fn ($q) => $q->whereKey($accountId))
            ->orderByDesc('is_default')->first();

        if (!$account) {
            return ['account_id' => null, 'cash_register_id' => null];
        }
        return ['account_id' => $account->id, 'cash_register_id' => null];
    }

    public function post(array $data): Transaction
    {
        $tenantId = (int) ($data['tenant_id'] ?? activeTenantId());
        if (!isset($data['transaction_type_id']) && !empty($data['type'])) {
            $data['transaction_type_id'] = \App\Models\Treasury\TransactionType::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('nature', $data['type'])->where('is_active', true)->value('id');
        }
        if (!isset($data['transaction_category_id']) && !empty($data['category'])) {
            $data['transaction_category_id'] = \App\Models\Treasury\TransactionCategory::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('code', $data['category'])->where('is_active', true)->value('id');
        }
        return DB::transaction(function () use ($data) {
            $transaction = Transaction::withoutGlobalScopes()->create($data);
            if (($transaction->status ?? 'completed') === 'completed') {
                $this->apply($transaction, 1);
            }
            return $transaction;
        });
    }

    public function apply(Transaction $transaction, int $direction): void
    {
        $delta = (float) $transaction->amount * ($transaction->type === 'income' ? 1 : -1) * $direction;
        if ($transaction->account_id) {
            Account::withoutGlobalScopes()->where('tenant_id', $transaction->tenant_id)
                ->whereKey($transaction->account_id)->lockForUpdate()->increment('current_balance', $delta);
        } elseif ($transaction->cash_register_id) {
            CashRegister::withoutGlobalScopes()->where('tenant_id', $transaction->tenant_id)
                ->whereKey($transaction->cash_register_id)->lockForUpdate()->increment('current_balance', $delta);
        }
    }
}
