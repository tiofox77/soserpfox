<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * O ESTADO DAS CAIXAS DE CADA EMPRESA.
 *
 * O ecrã das Caixas criava-as FECHADAS e não tinha onde as abrir (corrigido
 * em 19/09/2026). Quem criou caixas antes disso tem-nas todas fechadas, e o
 * numerário dessas empresas ficou sem gaveta: entrava na tesouraria com
 * `cash_register_id` nulo e não aparecia no painel, no fecho, nem em mapa
 * nenhum de caixa.
 *
 * Este comando diz, empresa a empresa:
 *
 *  1. QUANTAS CAIXAS há, e quantas estão abertas. Nenhuma aberta é o sinal
 *     do defeito acima.
 *  2. QUANTO DINHEIRO ficou sem gaveta — movimentos de numerário sem caixa.
 *  3. SE O SALDO BATE com os movimentos lançados em cada caixa.
 *
 * SÓ LÊ. Com `--abrir` passa a escrever, e só isso: abre as caixas activas
 * que estejam fechadas. Não mexe em saldos nem em movimentos — reatribuir
 * dinheiro já lançado é uma decisão de quem conhece a loja, não do comando.
 */
class VerificarCaixas extends Command
{
    protected $signature = 'caixas:verificar
                            {--tenant= : Só esta empresa (id)}
                            {--abrir : Abre as caixas activas que estão fechadas (escreve)}';

    protected $description = 'Estado das caixas por empresa: abertas, dinheiro sem gaveta e saldos que não batem (só lê)';

    public function handle(): int
    {
        $empresas = Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->whereKey((int) $this->option('tenant')))
            ->orderBy('id')
            ->get();

        $comProblema = 0;

        foreach ($empresas as $empresa) {
            $comProblema += $this->conferirEmpresa($empresa) ? 1 : 0;
        }

        $this->newLine();

        if ($comProblema === 0) {
            $this->info('Todas as empresas conferidas têm as caixas em ordem.');

            return self::SUCCESS;
        }

        $this->warn("{$comProblema} empresa(s) com caixas a precisar de atenção.");

        if (! $this->option('abrir')) {
            $this->line('Para abrir as caixas fechadas: php artisan caixas:verificar --abrir');
        }

        return self::SUCCESS;
    }

    /** @return bool true se esta empresa tem alguma coisa a assinalar. */
    private function conferirEmpresa(Tenant $empresa): bool
    {
        $caixas = CashRegister::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)->orderBy('name')->get();

        if ($caixas->isEmpty()) {
            return false;
        }

        $activas = $caixas->where('is_active', true);
        $abertas = $activas->where('status', 'open');

        // O numerário que ficou sem gaveta. `category` é o que o lançamento
        // escreve para o dinheiro físico; sem caixa, não entra em mapa nenhum.
        $semGaveta = Transaction::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->whereNull('cash_register_id')->whereNull('account_id')
            ->selectRaw('COUNT(*) AS quantos, COALESCE(SUM(amount), 0) AS quanto')
            ->first();

        $desencontros = $this->saldosQueNaoBatem($empresa->id, $activas);

        $problema = $activas->isNotEmpty() && $abertas->isEmpty();
        $problema = $problema || (int) $semGaveta->quantos > 0 || $desencontros->isNotEmpty();

        if (! $problema) {
            return false;
        }

        $this->newLine();
        $this->line("<options=bold>#{$empresa->id} — {$empresa->name}</>");
        $this->line("  caixas: {$caixas->count()} ({$activas->count()} activas, {$abertas->count()} abertas)");

        if ($activas->isNotEmpty() && $abertas->isEmpty()) {
            $this->warn('  · nenhuma caixa aberta: o numerário desta empresa não tem gaveta.');
        }

        if ((int) $semGaveta->quantos > 0) {
            $this->warn(sprintf(
                '  · %d movimento(s) sem caixa nem conta, num total de %s Kz.',
                $semGaveta->quantos,
                number_format((float) $semGaveta->quanto, 2, ',', '.'),
            ));
        }

        foreach ($desencontros as $d) {
            $this->warn(sprintf(
                '  · «%s»: saldo %s Kz, movimentos + fundo dão %s Kz (diferença %s Kz).',
                $d['nome'],
                number_format($d['saldo'], 2, ',', '.'),
                number_format($d['calculado'], 2, ',', '.'),
                number_format($d['saldo'] - $d['calculado'], 2, ',', '.'),
            ));
        }

        if ($this->option('abrir')) {
            $this->abrirAsFechadas($activas);
        }

        return true;
    }

    /**
     * As caixas cujo `current_balance` não bate com o fundo de maneio mais os
     * movimentos lançados nelas.
     *
     * @param  \Illuminate\Support\Collection<int, CashRegister>  $activas
     * @return \Illuminate\Support\Collection<int, array{nome: string, saldo: float, calculado: float}>
     */
    private function saldosQueNaoBatem(int $tenantId, $activas)
    {
        return $activas->map(function (CashRegister $c) use ($tenantId) {
            $movimentos = (float) Transaction::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('cash_register_id', $c->id)
                ->where('status', 'completed')
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) AS saldo")
                ->value('saldo');

            $calculado = round((float) $c->opening_balance + $movimentos, 2);
            $saldo = round((float) $c->current_balance, 2);

            return ['nome' => $c->name, 'saldo' => $saldo, 'calculado' => $calculado];
        })->filter(fn ($d) => abs($d['saldo'] - $d['calculado']) > 0.01)->values();
    }

    /** @param  \Illuminate\Support\Collection<int, CashRegister>  $activas */
    private function abrirAsFechadas($activas): void
    {
        $fechadas = $activas->where('status', 'closed');

        if ($fechadas->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($fechadas) {
            foreach ($fechadas as $c) {
                // SÓ O ESTADO E A HORA. O saldo não se toca: o dinheiro que lá
                // está, está, e não é um comando que o conta.
                CashRegister::withoutGlobalScopes()->whereKey($c->id)
                    ->update(['status' => 'open', 'opened_at' => now(), 'closed_at' => null]);
            }
        });

        $this->info("  → {$fechadas->count()} caixa(s) aberta(s).");
    }
}
