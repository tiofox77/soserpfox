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
            /*
             * A ORDEM IMPORTA (19/09/2026). Primeiro a caixa ESCOLHIDA à mão
             * (o modal deixa escolher); depois a caixa DO OPERADOR — cada um
             * responde pela sua gaveta, que é o que o fecho por turno exige —;
             * e só então a caixa por omissão do método.
             *
             * Estava ao contrário: o `default_cash_register_id` do método vinha
             * primeiro e o numerário de TODOS os operadores caía na mesma
             * caixa, nunca na de quem vendeu.
             */
            $doOperador = $userId
                ? CashRegister::withoutGlobalScopes()->where('tenant_id', $tenantId)
                    ->where('user_id', $userId)->where('is_active', true)->where('status', 'open')
                    ->orderByDesc('is_default')->value('id')
                : null;

            foreach ([$cashId, $doOperador, $method->default_cash_register_id] as $candidata) {
                if (!$candidata) {
                    continue;
                }
                $cash = CashRegister::withoutGlobalScopes()->where('tenant_id', $tenantId)
                    ->where('is_active', true)->where('status', 'open')
                    ->whereKey($candidata)->first();

                if ($cash) {
                    return ['account_id' => null, 'cash_register_id' => $cash->id];
                }
            }

            /*
             * Nenhuma das indicadas está ABERTA. Antes devolvia-se null e o
             * movimento ficava sem caixa — dinheiro que entrava na tesouraria e
             * desaparecia do fecho de caixa. Agora cai na caixa aberta da casa.
             *
             * E se NENHUMA estiver aberta, vai para a caixa activa da casa
             * mesmo fechada: uma empresa que criou as caixas antes de o
             * formulário as saber abrir tem-nas todas fechadas, e todo o
             * numerário dela ficava sem gaveta — invisível no painel, no fecho
             * e nos relatórios. Numa caixa fechada o valor está mal arrumado;
             * em nenhuma, está perdido.
             */
            $cash = CashRegister::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
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
        // Número robusto e à prova de colisões: ignora o que o chamador mandou
        // (uniqid, sequência calculada à mão — que reiniciava e chocava) e gera
        // pelo MAX real da sequência do ano. Cada tentativa é uma transação
        // NOVA: sob concorrência, quem perde relê já com a linha vencedora
        // gravada e apanha o número seguinte, em vez de rebentar com 1062.
        for ($tentativa = 0; $tentativa < 6; $tentativa++) {
            try {
                return DB::transaction(function () use ($data, $tenantId, $tentativa) {
                    $data['transaction_number'] = $this->numeroDaTentativa($tenantId, $tentativa);
                    $transaction = Transaction::withoutGlobalScopes()->create($data);
                    if (($transaction->status ?? 'completed') === 'completed') {
                        $this->apply($transaction, 1);
                    }
                    return $transaction;
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // A última tentativa também continua: rebentar aqui deixava o
                // recurso de baixo por alcançar, e a venda inteira caía.
                if ($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                    usleep(random_int(1000, 6000));
                    continue;
                }
                throw $e;
            }
        }

        // Último recurso: sufixo aleatório único, para nunca prender a operação.
        return DB::transaction(function () use ($data, $tenantId) {
            $data['transaction_number'] = 'TRX-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $transaction = Transaction::withoutGlobalScopes()->create($data);
            if (($transaction->status ?? 'completed') === 'completed') {
                $this->apply($transaction, 1);
            }
            return $transaction;
        });
    }

    /**
     * O número da tentativa N: o maior visível + 1 + N.
     *
     * Dentro da transacção da venda a leitura é uma fotografia (REPEATABLE
     * READ): o número que outro posto acabou de gravar não se vê, e recalcular
     * dava sempre o mesmo. Ver Transaction::gerarNumero.
     */
    protected function numeroDaTentativa(int $tenantId, int $tentativa): string
    {
        return Transaction::gerarNumero($tenantId, 'TRX', $tentativa);
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
