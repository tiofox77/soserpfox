<?php

namespace App\Services\Treasury;

use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\Transaction;
use App\Support\CategoriasDeTesouraria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ARRUMAR OS MOVIMENTOS SEM DESTINO — de uma vez (23/09/2026).
 *
 * Um movimento sem conta e sem caixa é dinheiro registado que não mexeu saldo
 * nenhum: nasceu quando a caixa estava fechada ou a forma de pagamento não
 * tinha destino. O painel dizia «38 movimentos por arrumar» e levava à lista
 * INTEIRA de movimentos, sem filtro — arrumá-los era abrir um a um.
 *
 * Aqui juntam-se por forma de pagamento e pelo destino SUGERIDO, e arruma-se
 * cada grupo com um clique. A sugestão:
 *
 *  · NUMERÁRIO → a caixa de quem fez o movimento; senão a caixa por omissão da
 *    forma; senão a caixa principal (ou a única) da empresa;
 *  · o resto (TPA, transferência, digital) → a conta por omissão da forma;
 *    senão a conta principal (ou a única) da empresa.
 *
 * É SÓ UMA SUGESTÃO: quem conhece a loja confirma ou troca antes de aplicar.
 * Aplicar põe o destino e mexe o saldo — pelo `apply()` da tesouraria, o
 * mesmo de sempre. Não vai a turno nenhum: é dinheiro de dias que já fecharam.
 */
class ArrumarMovimentos
{
    public function __construct(private readonly int $tenantId)
    {
    }

    /** Os movimentos que não caíram em lado nenhum (os que o painel conta). */
    public function consulta()
    {
        return Transaction::where('tenant_id', $this->tenantId)
            ->where('status', 'completed')
            ->whereNull('account_id')
            ->whereNull('cash_register_id');
    }

    /**
     * Os grupos para o ecrã: um por forma de pagamento e destino sugerido.
     *
     * @return array{grupos: list<array<string, mixed>>, destinos: list<array{valor: string, rotulo: string, tipo: string}>, total: array{movimentos: int, valor: float}}
     */
    public function grupos(): array
    {
        $caixas = $this->caixas();
        $contas = $this->contas();

        $movimentos = $this->consulta()->with('paymentMethod:id,name,type,default_account_id,default_cash_register_id')
            ->orderBy('transaction_date')->get();

        $grupos = $movimentos
            ->groupBy(fn (Transaction $t) => $this->forma($t) . '|' . ($this->sugestao($t, $caixas, $contas) ?? ''))
            ->map(function (Collection $doGrupo) use ($caixas, $contas) {
                $primeiro = $doGrupo->first();
                $sugestao = $this->sugestao($primeiro, $caixas, $contas);

                return [
                    'chave' => md5($this->forma($primeiro) . '|' . $sugestao),
                    'forma' => $this->nomeDaForma($primeiro),
                    'numerario' => $this->eNumerario($primeiro),
                    'movimentos' => $doGrupo->count(),
                    'entradas' => round((float) $doGrupo->where('type', 'income')->sum('amount'), 2),
                    'saidas' => round((float) $doGrupo->where('type', 'expense')->sum('amount'), 2),
                    'de' => ($datas = $doGrupo->map(fn (Transaction $t) => $t->transaction_date?->format('Y-m-d'))->filter())->min(),
                    'ate' => $datas->max(),
                    'sugestao' => $sugestao,
                    'ids' => $doGrupo->pluck('id')->values()->all(),
                ];
            })
            ->sortByDesc('movimentos')->values()->all();

        $destinos = collect()
            ->merge($caixas->map(fn (CashRegister $c) => ['valor' => 'cash:' . $c->id, 'rotulo' => $c->name, 'tipo' => 'caixa']))
            ->merge($contas->map(fn (Account $a) => ['valor' => 'account:' . $a->id, 'rotulo' => trim($a->account_name . ($a->bank?->name ? ' — ' . $a->bank->name : '')), 'tipo' => 'conta']))
            ->values()->all();

        return [
            'grupos' => $grupos,
            'destinos' => $destinos,
            'total' => ['movimentos' => $movimentos->count(), 'valor' => round((float) $movimentos->sum('amount'), 2)],
        ];
    }

    /**
     * Põe cada movimento no destino escolhido e mexe o saldo.
     *
     * Só os que AINDA não têm destino: arrumar duas vezes, ou arrumar um que
     * alguém corrigiu à mão entretanto, não mexe em nada.
     *
     * @param  list<array{ids: list<int>, destino: string}>  $atribuicoes
     * @return array{arrumados: int, valor: float}
     */
    public function arrumar(array $atribuicoes): array
    {
        $caixas = $this->caixas()->keyBy('id');
        $contas = $this->contas()->keyBy('id');
        $servico = app(TreasuryMovementService::class);

        return DB::transaction(function () use ($atribuicoes, $caixas, $contas, $servico) {
            $arrumados = 0;
            $valor = 0.0;

            foreach ($atribuicoes as $a) {
                [$tipo, $id] = array_pad(explode(':', (string) ($a['destino'] ?? '')), 2, null);
                $id = (int) $id;

                $destino = match ($tipo) {
                    'cash' => $caixas->has($id) ? ['account_id' => null, 'cash_register_id' => $id] : null,
                    'account' => $contas->has($id) ? ['account_id' => $id, 'cash_register_id' => null] : null,
                    default => null,
                };

                if (! $destino) {
                    throw new \DomainException(__('Escolha uma caixa ou conta activa desta empresa para cada grupo.'));
                }

                $movimentos = $this->consulta()->whereIn('id', array_map('intval', (array) ($a['ids'] ?? [])))->lockForUpdate()->get();

                foreach ($movimentos as $m) {
                    $m->forceFill($destino)->save();
                    $servico->apply($m, 1);
                    $arrumados++;
                    $valor += (float) $m->amount;
                }
            }

            return ['arrumados' => $arrumados, 'valor' => round($valor, 2)];
        });
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function caixas(): Collection
    {
        return CashRegister::where('tenant_id', $this->tenantId)->where('is_active', true)
            ->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'user_id', 'is_default']);
    }

    private function contas(): Collection
    {
        return Account::where('tenant_id', $this->tenantId)->where('is_active', true)->with('bank:id,name')
            ->orderByDesc('is_default')->orderBy('account_name')->get(['id', 'account_name', 'bank_id', 'is_default']);
    }

    /** Numerário: pela forma de pagamento, ou pela categoria que o POS grava. */
    private function eNumerario(Transaction $t): bool
    {
        return $t->paymentMethod ? $t->paymentMethod->type === 'cash' : in_array($t->category, ['cash'], true);
    }

    private function forma(Transaction $t): string
    {
        return $t->payment_method_id ? 'm' . $t->payment_method_id : 'c' . ($t->category ?? '');
    }

    private function nomeDaForma(Transaction $t): string
    {
        return $t->paymentMethod?->name ?? CategoriasDeTesouraria::nome($t->category);
    }

    /** O destino proposto — `cash:ID`, `account:ID` ou nulo quando não há onde. */
    private function sugestao(Transaction $t, Collection $caixas, Collection $contas): ?string
    {
        if ($this->eNumerario($t)) {
            $caixa = $caixas->firstWhere('user_id', $t->user_id)
                ?? $caixas->firstWhere('id', $t->paymentMethod?->default_cash_register_id)
                ?? $caixas->firstWhere('is_default', true)
                ?? ($caixas->count() === 1 ? $caixas->first() : null);

            return $caixa ? 'cash:' . $caixa->id : null;
        }

        $conta = $contas->firstWhere('id', $t->paymentMethod?->default_account_id)
            ?? $contas->firstWhere('is_default', true)
            ?? ($contas->count() === 1 ? $contas->first() : null);

        return $conta ? 'account:' . $conta->id : null;
    }
}
