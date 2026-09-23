<?php

namespace App\Console\Commands;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\PosShift;
use Illuminate\Console\Command;

/**
 * AS DEVOLUÇÕES QUE FICARAM DE FORA DO TURNO.
 *
 * O turno do POS nunca soube o que era uma nota de crédito: a tabela dos
 * movimentos tinha um ENUM de cinco tipos e a devolução não era nenhum deles.
 * Corrigido — mas só para a frente: as notas já emitidas continuam sem
 * movimento, e o turno onde foram feitas continua a dizer que só houve vendas.
 *
 * Isto lança-as. A SECO por omissão, como todos os comandos desta casa que
 * escrevem: primeiro vê-se o que ia acontecer, depois decide-se.
 *
 * UM TURNO FECHADO NÃO SE MEXE sem se pedir. Fechar um turno é contar a gaveta
 * e assinar por baixo; acrescentar-lhe movimentos depois muda um número que
 * alguém já conferiu, e a diferença que ficou registada deixa de bater com a
 * conta. Quem quiser fazê-lo à mesma tem de o dizer — e fica dito no ecrã o que
 * isso significa.
 */
class DevolucoesNosTurnos extends Command
{
    protected $signature = 'pos:devolucoes-nos-turnos
                            {--tenant= : só esta empresa}
                            {--incluir-fechados : mexe também nos turnos já conferidos}
                            {--aplicar : escreve de facto}';

    protected $description = 'Lança nos turnos do POS as notas de crédito que ficaram de fora (a seco por omissão)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $fechados = (bool) $this->option('incluir-fechados');

        if (! $aplicar) {
            $this->comment('A SECO — nada é escrito. Use --aplicar.');
        }

        if ($fechados) {
            $this->warn('Inclui turnos FECHADOS: são contagens já conferidas e assinadas.');
        }

        /*
         * A NOTA É DO TURNO EM QUE FOI FEITA — o que estava aberto, do mesmo
         * operador, quando ela nasceu. É a mesma pergunta que o emissor passou
         * a fazer, só que para trás.
         */
        $notas = CreditNote::query()
            ->when($this->option('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNotNull('created_by')
            ->with('invoice')
            ->orderBy('id')
            ->get();

        $linhas = [];
        $lancadas = 0;
        $saltadas = 0;

        foreach ($notas as $nota) {
            $turno = PosShift::withoutGlobalScopes()
                ->where('tenant_id', $nota->tenant_id)
                ->where('user_id', $nota->created_by)
                ->where('opened_at', '<=', $nota->created_at)
                ->where(fn ($q) => $q->where('closed_at', '>=', $nota->created_at)->orWhereNull('closed_at'))
                ->orderByDesc('opened_at')
                ->first();

            if (! $turno) {
                continue;
            }

            // Já lá está: correr isto duas vezes não pode devolver a dobrar.
            $jaLa = $turno->transactions()
                ->where('type', 'credit_note')
                ->where('reference_id', $nota->id)
                ->exists();

            if ($jaLa) {
                continue;
            }

            $porque = $turno->status !== 'open' && ! $fechados ? 'turno fechado' : null;

            $linhas[] = [
                $nota->credit_note_number,
                $turno->shift_number,
                $turno->status === 'open' ? 'aberto' : 'FECHADO',
                number_format((float) $nota->total, 2, ',', '.'),
                $porque ?? ($aplicar ? 'lançada' : 'seria lançada'),
            ];

            if ($porque) {
                $saltadas++;

                continue;
            }

            if ($aplicar) {
                /*
                 * O TURNO FECHADO TEM DE REABRIR PARA ACEITAR O MOVIMENTO —
                 * `addTransaction` recusa-o, e bem. Reabre-se, escreve-se, e
                 * volta a fechar-se com a MESMA contagem: o que muda é o
                 * esperado, e portanto a diferença, que passa a ser a
                 * verdadeira.
                 */
                $estado = $turno->status;

                if ($estado !== 'open') {
                    $turno->forceFill(['status' => 'open'])->save();
                }

                $turno->registarNotaDeCredito($nota);

                if ($estado !== 'open') {
                    $turno->refresh();
                    // O esperado pela conta única do turno — que conta também as
                    // saídas e entradas da gaveta (PosShift::dinheiroEsperado).
                    $esperado = $turno->dinheiroEsperado();
                    $turno->forceFill([
                        'status' => $estado,
                        'expected_cash' => $esperado,
                        'cash_difference' => (float) $turno->actual_cash - $esperado,
                    ])->save();
                }
            }

            $lancadas++;
        }

        if ($linhas === []) {
            $this->info('Nada a lançar: todas as notas de crédito já estão nos seus turnos.');

            return self::SUCCESS;
        }

        $this->table(['Nota', 'Turno', 'Estado', 'Valor', 'O quê'], $linhas);

        $this->info($lancadas . ' devolução(ões) ' . ($aplicar ? 'lançada(s)' : 'por lançar') . '.');

        if ($saltadas > 0) {
            $this->comment($saltadas . ' em turnos fechados — use --incluir-fechados para as lançar também.');
        }

        return self::SUCCESS;
    }
}
