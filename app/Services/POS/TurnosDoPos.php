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

        $caixaId = $this->caixaAtribuida()?->id;

        return DB::transaction(function () use ($saldoInicial, $notas, $ip, $caixaId) {
            $caixa = $caixaId ? CashRegister::where('tenant_id', $this->tenantId)->lockForUpdate()->find($caixaId) : null;

            if ($caixa && $caixa->status !== 'open') {
                $caixa->update([
                    'status' => 'open', 'opened_at' => now(), 'closed_at' => null,
                    'opening_balance' => $saldoInicial,
                    'current_balance' => $saldoInicial,
                    'expected_balance' => $saldoInicial,
                    'opening_notes' => $notas,
                ]);
            }

            return PosShift::createSafely([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'status' => 'open',
                'opened_at' => now(),
                'opening_balance' => $saldoInicial,
                'opening_notes' => $notas,
                'opened_ip' => $ip,
            ], $this->tenantId);
        });
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

        $caixaId = $this->caixaAtribuida()?->id;

        DB::transaction(function () use ($turno, $dinheiroContado, $notas, $motivoDaDiferenca, $caixaId) {
            $turno->close($dinheiroContado, $notas, $motivoDaDiferenca);

            if ($caixaId) {
                $caixa = CashRegister::where('tenant_id', $this->tenantId)->lockForUpdate()->find($caixaId);
                if ($caixa && $caixa->status === 'open') {
                    $caixa->update([
                        'status' => 'closed', 'closed_at' => now(),
                        'current_balance' => $dinheiroContado,
                        'expected_balance' => $turno->expected_cash,
                        'closing_notes' => $notas,
                    ]);
                }
            }
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
