<?php

namespace App\Services\POS;

use App\Models\Invoicing\PosShift;
use App\Models\Treasury\CashRegister;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * OS TURNOS DO POS — a única implementação.
 *
 * Abrir e fechar o turno de quem está ao balcão, com a caixa atribuída a
 * acompanhar; e o histórico, em que quem não pode ver todos fica preso aos
 * seus. O ecrã de sempre (Livewire) e o ecrã em React chamam aqui.
 */
class TurnosDoPos
{
    public function __construct(private readonly int $tenantId, private readonly int $userId)
    {
    }

    public static function regrasDeAbertura(): array
    {
        return ['opening_balance' => 'required|numeric|min:0', 'opening_notes' => 'nullable|string|max:1000'];
    }

    public static function mensagensDeAbertura(): array
    {
        return [
            'opening_balance.required' => __('Informe o saldo inicial'),
            'opening_balance.numeric' => __('O saldo deve ser um número'),
            'opening_balance.min' => __('O saldo não pode ser negativo'),
        ];
    }

    public static function regrasDeFecho(): array
    {
        return ['actual_cash' => 'required|numeric|min:0', 'closing_notes' => 'nullable|string|max:1000', 'difference_reason' => 'nullable|string|max:1000'];
    }

    public static function mensagensDeFecho(): array
    {
        return [
            'actual_cash.required' => __('Informe o valor em dinheiro contado'),
            'actual_cash.numeric' => __('O valor deve ser um número'),
        ];
    }

    /** O turno aberto deste operador, se houver. */
    public function actual(): ?PosShift
    {
        return PosShift::where('tenant_id', $this->tenantId)
            ->where('user_id', $this->userId)
            ->where('status', 'open')
            ->with(['transactions'])
            ->first();
    }

    /** O último turno que este operador fechou — para reimprimir o fecho sem ir ao histórico. */
    public function ultimoFechado(): ?PosShift
    {
        return PosShift::where('tenant_id', $this->tenantId)
            ->where('user_id', $this->userId)
            ->where('status', 'closed')
            ->with(['user', 'closedBy', 'transactions'])
            ->latest('closed_at')
            ->first();
    }

    /** A caixa de tesouraria atribuída ao operador: abre e fecha com o turno. */
    public function caixaAtribuida(): ?CashRegister
    {
        return CashRegister::where('tenant_id', $this->tenantId)
            ->where('user_id', $this->userId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function abrir(float $saldoInicial, ?string $notas, ?string $ip): PosShift
    {
        if ($this->actual()) {
            throw new DomainException(__('Você já tem um turno aberto!'));
        }

        return DB::transaction(function () use ($saldoInicial, $notas, $ip) {
            $turno = PosShift::createSafely([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'status' => 'open',
                'opened_at' => now(),
                'opening_balance' => $saldoInicial,
                'opening_notes' => $notas,
                'opened_ip' => $ip,
            ], $this->tenantId);

            $this->abrirACaixa($turno, $notas);

            return $turno;
        });
    }

    /**
     * A CAIXA DO OPERADOR ABRE COM O TURNO — e o saldo fica o que foi contado.
     *
     * Antes o saldo era ESCRITO por cima com o fundo declarado, sem movimento
     * nenhum: o dinheiro que tinha ficado na caixa sem ser transferido
     * desaparecia da tesouraria sem rasto. Agora a diferença fica lançada
     * (ver `acertar`) e aparece nos movimentos e no fluxo de caixa.
     *
     * O `opening_balance` da CAIXA não se toca: é o fundo de maneio que se
     * configurou, e é a base com que o `caixas:verificar` confere o saldo.
     * Serve o balcão web e o PWA (PosShiftController) — uma porta só.
     */
    public function abrirACaixa(PosShift $turno, ?string $notas = null): void
    {
        $caixaId = $this->caixaAtribuida()?->id;
        if (! $caixaId) {
            return;
        }

        $caixa = CashRegister::where('tenant_id', $this->tenantId)->lockForUpdate()->find($caixaId);
        if (! $caixa) {
            return;
        }

        $this->acertar($caixa, (float) $turno->opening_balance, $turno, 'abertura');

        if ($caixa->status !== 'open') {
            $caixa->update([
                'status' => 'open', 'opened_at' => now(), 'closed_at' => null,
                'expected_balance' => $turno->opening_balance,
                'opening_notes' => $notas,
            ]);
        }
    }

    /**
     * A CAIXA FECHA COM O TURNO, e a falta ou a sobra fica lançada.
     *
     * Antes o saldo passava a ser o dinheiro contado, escrito por cima: a
     * quebra de caixa nunca aparecia como movimento, e o fluxo de caixa
     * deixava de bater com o saldo das caixas.
     */
    public function fecharACaixa(PosShift $turno): void
    {
        $caixaId = $this->caixaAtribuida()?->id;
        if (! $caixaId) {
            return;
        }

        $caixa = CashRegister::where('tenant_id', $this->tenantId)->lockForUpdate()->find($caixaId);
        if (! $caixa) {
            return;
        }

        $this->acertar($caixa, (float) $turno->actual_cash, $turno, 'fecho');

        if ($caixa->status === 'open') {
            $caixa->update([
                'status' => 'closed', 'closed_at' => now(),
                'expected_balance' => $turno->expected_cash,
                'closing_notes' => $turno->closing_notes,
            ]);
        }
    }

    /**
     * O SALDO DA CAIXA PASSA A SER O QUE FOI CONTADO — por movimento.
     *
     * A diferença entre o que está lançado na caixa e o que o operador contou
     * vai para a tesouraria como «acerto de caixa»: saída quando falta
     * dinheiro, entrada quando sobra. Ligado ao turno (`related_type`), uma vez
     * por turno e por momento. Nunca rebenta: um acerto que falha não impede
     * ninguém de abrir ou fechar o turno, e fica no registo para se ver.
     */
    private function acertar(CashRegister $caixa, float $contado, PosShift $turno, string $momento): void
    {
        try {
            $referencia = 'TURNO-' . strtoupper($momento) . '-' . $turno->shift_number;

            $jaLancado = \App\Models\Treasury\Transaction::withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->where('related_type', PosShift::class)
                ->where('related_id', $turno->id)
                ->where('reference', $referencia)
                ->exists();

            $saldo = round((float) CashRegister::where('tenant_id', $this->tenantId)->whereKey($caixa->id)->value('current_balance'), 2);
            $diferenca = round($contado - $saldo, 2);

            if ($jaLancado || abs($diferenca) < 0.01) {
                return;
            }

            $valores = ['turno' => $turno->shift_number, 'caixa' => $caixa->name, 'lancado' => number_format($saldo, 2, ',', '.'), 'contado' => number_format($contado, 2, ',', '.')];

            $descricao = match (true) {
                $momento === 'abertura' && $diferenca < 0 => __('Falta na abertura do turno :turno (:caixa): lançado :lancado, contado :contado', $valores),
                $momento === 'abertura' => __('Sobra na abertura do turno :turno (:caixa): lançado :lancado, contado :contado', $valores),
                $diferenca < 0 => __('Quebra de caixa no fecho do turno :turno (:caixa): esperado :lancado, contado :contado', $valores),
                default => __('Sobra de caixa no fecho do turno :turno (:caixa): esperado :lancado, contado :contado', $valores),
            };

            app(\App\Services\Treasury\TreasuryMovementService::class)->post([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'type' => $diferenca > 0 ? 'income' : 'expense',
                'category' => 'cash_adjustment',
                'amount' => abs($diferenca),
                'currency' => 'AOA',
                'transaction_date' => now(),
                'payment_method_id' => app(\App\Services\Invoicing\LancamentoDeDinheiro::class)->metodoDeTesouraria('cash', $this->tenantId)->id,
                'account_id' => null,
                'cash_register_id' => $caixa->id,
                'related_type' => PosShift::class,
                'related_id' => $turno->id,
                'reference' => $referencia,
                'description' => $descricao,
                'notes' => $momento === 'fecho' ? $turno->difference_reason : null,
                'status' => 'completed',
                'is_reconciled' => false,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Turno: acerto da caixa falhou', [
                'turno' => $turno->shift_number, 'caixa_id' => $caixa->id, 'momento' => $momento, 'erro' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fecha com o dinheiro CONTADO pelo operador. Nunca se pré-preenche com o
     * esperado: senão a diferença de caixa dá sempre 0,00 e quebras ou
     * excessos nunca são detectados.
     */
    public function fechar(float $dinheiroContado, ?string $notas, ?string $motivoDaDiferenca): PosShift
    {
        $turno = $this->actual();
        if (!$turno) {
            throw new DomainException(__('Não há turno aberto!'));
        }

        DB::transaction(function () use ($turno, $dinheiroContado, $notas, $motivoDaDiferenca) {
            $turno->close($dinheiroContado, $notas, $motivoDaDiferenca);

            $this->fecharACaixa($turno);
        });

        return $turno->fresh(['transactions']);
    }

    /* ─── Histórico ───────────────────────────────────────────────────── */

    /**
     * Os turnos da empresa no período. Quem não tem `invoicing.pos.reports.all`
     * fica preso aos seus — e não escapa pelo filtro do operador.
     */
    public function historico(array $f, bool $veTodos)
    {
        $q = PosShift::with(['user', 'closedBy'])
            ->where('tenant_id', $this->tenantId)
            ->when($f['dateFrom'] ?? null, fn ($q, $v) => $q->whereDate('opened_at', '>=', $v))
            ->when($f['dateTo'] ?? null, fn ($q, $v) => $q->whereDate('opened_at', '<=', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v));

        return $this->restringir($q, $f['userId'] ?? null, $veTodos)->orderBy('opened_at', 'desc');
    }

    /** Um turno pelo id — com a mesma regra da lista, senão abria-se o de um colega. */
    public function turno(int $id, bool $veTodos): ?PosShift
    {
        $q = PosShift::with(['user', 'closedBy', 'transactions'])->where('tenant_id', $this->tenantId);

        return $this->restringir($q, null, $veTodos)->find($id);
    }

    private function restringir($q, $userId, bool $veTodos)
    {
        if (!$veTodos) {
            return $q->where('user_id', $this->userId);
        }
        if ($userId) {
            $q->where('user_id', $userId);
        }

        return $q;
    }
}
