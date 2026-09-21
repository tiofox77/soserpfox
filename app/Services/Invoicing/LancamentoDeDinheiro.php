<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\PosShift;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Transaction;
use App\Services\Treasury\TreasuryMovementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * DINHEIRO QUE ENTRA OU SAI — a porta única de toda a facturação.
 *
 * Nasceu do `LancamentoDoRecibo`, quando se viu que o mesmo caminho faltava em
 * mais quatro sítios: o adiantamento, o sinal do hotel, a nota de crédito e o
 * estorno de um recibo apagado. Cada um deles mexia dinheiro de verdade e não
 * o escrevia em lado nenhum — e escrever o mesmo lançamento cinco vezes dava
 * cinco resultados diferentes, que é como se descobre, meses depois, que o
 * fecho de caixa nunca bateu.
 *
 * O QUE ESTA CLASSE GARANTE, e é tudo o que ela faz:
 *
 *  · UMA VEZ SÓ. A marca fica em `related_type`/`related_id` do movimento, e é
 *    por ela que se sabe que aquele documento já foi lançado. Dois caminhos a
 *    chamar isto não criam dinheiro do nada.
 *
 *  · A DATA É A DO DOCUMENTO, nunca a de agora: um recibo de ontem cai em
 *    ontem, senão os mapas não cruzam.
 *
 *  · O DESTINO sai da forma de pagamento — a gaveta de quem está ao balcão
 *    para o numerário, a conta bancária para o resto. Ver o
 *    `TreasuryMovementService::destination`.
 *
 *  · O TURNO leva o que passa pela gaveta, e só isso: é o que faz o valor
 *    contar no fecho de caixa.
 */
class LancamentoDeDinheiro
{
    /**
     * @param  Model  $origem  O documento que justifica o dinheiro (Receipt,
     *                         Advance, CreditNote…). É ele a marca de
     *                         idempotência.
     * @param  array{
     *     valor: float,
     *     forma: string,
     *     sentido?: string,
     *     categoria?: string,
     *     data?: mixed,
     *     referencia?: string|null,
     *     descricao?: string|null,
     *     notas?: string|null,
     *     invoice_id?: int|null,
     *     purchase_id?: int|null,
     *     destino?: array{account_id?: int|null, cash_register_id?: int|null},
     *     turno?: array{type: string, reference_number?: string|null, description?: string|null, metadata?: array}|null,
     * }  $d
     */
    public function lancar(Model $origem, array $d, ?int $userId = null): ?Transaction
    {
        $tenantId = (int) $origem->tenant_id;
        $valor = round((float) ($d['valor'] ?? 0), 2);

        if ($valor <= 0 || $this->jaLancado($origem, $tenantId)) {
            return null;
        }

        $metodo = $this->metodoDeTesouraria((string) ($d['forma'] ?? 'cash'), $tenantId);
        $tesouraria = app(TreasuryMovementService::class);
        $escolhido = $d['destino'] ?? [];

        $destino = $tesouraria->destination(
            $metodo,
            $tenantId,
            ! empty($escolhido['account_id']) ? (int) $escolhido['account_id'] : null,
            ! empty($escolhido['cash_register_id']) ? (int) $escolhido['cash_register_id'] : null,
            $userId,
        );

        $movimento = $tesouraria->post([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => $d['sentido'] ?? 'income',
            'category' => $d['categoria'] ?? 'customer_payment',
            'amount' => $valor,
            'currency' => 'AOA',
            // A data do DOCUMENTO. O `?? null` não é hábito: um chamador que se
            // esqueça dela tem de cair em `now()`, não em aviso de chave que
            // não existe no meio de um lançamento de dinheiro.
            'transaction_date' => ($d['data'] ?? null) ?: now(),
            'payment_method_id' => $metodo->id,
            'account_id' => $destino['account_id'],
            'cash_register_id' => $destino['cash_register_id'],
            'invoice_id' => $d['invoice_id'] ?? null,
            'purchase_id' => $d['purchase_id'] ?? null,
            'related_type' => $origem::class,
            'related_id' => $origem->getKey(),
            'reference' => $d['referencia'] ?? null,
            'description' => $d['descricao'] ?? null,
            'notes' => $d['notas'] ?? null,
            'status' => 'completed',
            'is_reconciled' => false,
        ]);

        if (! empty($d['turno'])) {
            $this->noTurno($origem, $tenantId, $userId, $valor, $d);
        }

        return $movimento;
    }

    /** Já há movimento deste documento? É o que impede lançar duas vezes. */
    public function jaLancado(Model $origem, ?int $tenantId = null): bool
    {
        return Transaction::withoutGlobalScopes()
            ->where('tenant_id', $tenantId ?: (int) $origem->tenant_id)
            ->where('related_type', $origem::class)
            ->where('related_id', $origem->getKey())
            ->exists();
    }

    /**
     * O ESTORNO de um documento que deixou de existir.
     *
     * Apagar um recibo desfazia o pagamento na factura e deixava o dinheiro na
     * tesouraria. Aqui desfaz-se o movimento pelo caminho certo — devolvendo o
     * valor ao saldo da caixa ou da conta — em vez de o apagar por baixo, que
     * deixaria os saldos por corrigir.
     */
    public function estornar(Model $origem, ?int $tenantId = null): int
    {
        $movimentos = Transaction::withoutGlobalScopes()
            ->where('tenant_id', $tenantId ?: (int) $origem->tenant_id)
            ->where('related_type', $origem::class)
            ->where('related_id', $origem->getKey())
            ->get();

        $tesouraria = app(TreasuryMovementService::class);

        foreach ($movimentos as $m) {
            if (($m->status ?? 'completed') === 'completed') {
                $tesouraria->apply($m, -1);
            }
            $m->delete();
        }

        return $movimentos->count();
    }

    /**
     * O movimento no turno aberto de quem recebeu. Falhar aqui nunca desfaz o
     * documento nem o movimento de tesouraria.
     */
    private function noTurno(Model $origem, int $tenantId, ?int $userId, float $valor, array $d): void
    {
        $turno = PosShift::abertoDe($tenantId, $userId);

        if (! $turno) {
            return;
        }

        try {
            $turno->addTransaction([
                'type' => $d['turno']['type'],
                'reference_type' => $origem::class,
                'reference_id' => $origem->getKey(),
                'reference_number' => $d['turno']['reference_number'] ?? null,
                'payment_method' => (string) ($d['forma'] ?? 'cash'),
                'amount' => ($d['sentido'] ?? 'income') === 'expense' ? -$valor : $valor,
                'description' => $d['turno']['description'] ?? $d['descricao'] ?? null,
                'metadata' => $d['turno']['metadata'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Lançamento: falha ao registar no turno', [
                'origem' => $origem::class . '#' . $origem->getKey(),
                'erro' => $e->getMessage(),
            ]);
        }
    }

    /**
     * O método de tesouraria da forma escolhida no ecrã.
     *
     * Procura pelo CÓDIGO (o índice único real): procurar pelo nome rebentava
     * com "Duplicate entry" assim que o cliente renomeasse o método.
     */
    public function metodoDeTesouraria(string $forma, int $tenantId): PaymentMethod
    {
        $mapa = RegistoDePagamento::MAPA_METODOS;
        [$codigo, $nome, $tipo] = $mapa[strtolower($forma)] ?? $mapa['cash'];

        return PaymentMethod::where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('code', $codigo)->orWhere('name', $nome))
            ->first()
            ?? PaymentMethod::create([
                'tenant_id' => $tenantId,
                'code' => $codigo,
                'name' => $nome,
                'type' => $tipo,
                'is_active' => true,
            ]);
    }
}
