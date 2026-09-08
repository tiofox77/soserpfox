<?php

namespace App\Console\Commands;

use App\Models\HR\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ALINHA OS QUATRO PARES DE COLUNAS QUE DIZEM A MESMA COISA.
 *
 * A `hr_employees` tem duas colunas para cada uma de quatro coisas:
 *
 *   salary              ↔ base_salary
 *   status              ↔ employment_status
 *   transport_allowance ↔ transport_benefit
 *   meal_allowance      ↔ food_benefit
 *
 * O formulário em Blade gravava sempre nas da esquerda; partes do cálculo da
 * folha lêem as da direita. Um funcionário a quem se aumentou o salário
 * ficava com o novo em `salary` e o antigo em `base_salary` — e a folha do
 * mês saía pelo antigo, sem um aviso.
 *
 * A partir da ficha em React as duas escrevem-se sempre juntas
 * (`FuncionariosApiController`). Este comando trata do PASSADO.
 *
 * QUAL É QUE MANDA: a da ESQUERDA, que é onde o formulário sempre escreveu —
 * é a que tem o valor mais recente. A da direita só ganha quando a esquerda
 * está vazia e ela não: aí o valor é dela, e perdê-lo seria apagar o que
 * alguém escreveu por outra via.
 *
 * A seco por omissão. Só escreve com --aplicar.
 */
class AlinharColunasDoFuncionario extends Command
{
    protected $signature = 'rh:alinhar-colunas-do-funcionario {--aplicar : escreve de facto} {--empresa= : só esta empresa}';

    protected $description = 'Põe de acordo os pares de colunas duplicadas da ficha do funcionário (a seco por omissão)';

    /** esquerda (manda) => direita (segue) */
    private const PARES = [
        'salary' => 'base_salary',
        'status' => 'employment_status',
        'transport_allowance' => 'transport_benefit',
        'meal_allowance' => 'food_benefit',
    ];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $empresa = $this->option('empresa');

        if (! $aplicar) {
            $this->comment('A SECO — nada é escrito. Use --aplicar.');
        }

        $divergentes = 0;
        $corrigidos = 0;

        Employee::withoutGlobalScopes()
            ->when($empresa, fn ($q) => $q->where('tenant_id', $empresa))
            ->orderBy('id')
            ->chunkById(500, function ($lote) use ($aplicar, &$divergentes, &$corrigidos) {
                foreach ($lote as $e) {
                    $mudar = [];

                    foreach (self::PARES as $manda => $segue) {
                        $a = $e->{$manda};
                        $b = $e->{$segue};

                        // Numérico compara-se por valor: '1000.00' e 1000 são
                        // o mesmo número escrito de duas maneiras.
                        $iguais = is_numeric($a) && is_numeric($b)
                            ? abs((float) $a - (float) $b) < 0.005
                            : (string) $a === (string) $b;

                        if ($iguais) {
                            continue;
                        }

                        // A esquerda manda; a direita só ganha se a esquerda
                        // estiver vazia.
                        $valor = ($a === null || $a === '') ? $b : $a;

                        $mudar[$manda] = $valor;
                        $mudar[$segue] = $valor;
                    }

                    if (! $mudar) {
                        continue;
                    }

                    $divergentes++;

                    $this->line(sprintf(
                        '  #%d %s — %s',
                        $e->id,
                        $e->employee_number ?? '?',
                        collect($mudar)->map(fn ($v, $k) => "{$k}=" . ($v ?? 'null'))->implode(', '),
                    ));

                    if ($aplicar) {
                        DB::table('hr_employees')->where('id', $e->id)->update($mudar);
                        $corrigidos++;
                    }
                }
            });

        $this->line('');

        if ($divergentes === 0) {
            $this->info('Nenhuma ficha com colunas divergentes.');

            return self::SUCCESS;
        }

        $aplicar
            ? $this->info("{$corrigidos} ficha(s) alinhada(s).")
            : $this->warn("{$divergentes} ficha(s) divergem. Corra com --aplicar para as alinhar.");

        return self::SUCCESS;
    }
}
