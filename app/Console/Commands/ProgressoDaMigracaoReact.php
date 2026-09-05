<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Onde vamos na migração para React.
 *
 * Lê o mapa (`config/migracao-react.php`) e vai ao DISCO confirmar. Não há um
 * campo «estado» para alguém marcar à mão: o estado de cada ecrã é deduzido do
 * que existe, e por isso a percentagem não pode ficar optimista sozinha.
 *
 *   por fazer   — só existe o Livewire
 *   a conviver  — o React já existe, o Livewire também
 *   feito       — o Livewire foi apagado
 *
 * «A conviver» NÃO conta como feito. Enquanto os dois existem há duas
 * implementações da mesma coisa, que é o problema que a migração vem resolver.
 * A percentagem grande é a do trabalho feito; a pequena, a do trabalho fechado.
 */
class ProgressoDaMigracaoReact extends Command
{
    protected $signature = 'react:progresso
                            {--frente= : só uma frente (ex.: Facturação)}
                            {--detalhe : lista ecrã a ecrã}';

    protected $description = 'Quanto já migrámos para React e quanto falta';

    private const POR_FAZER  = 'por fazer';
    private const A_CONVIVER = 'a conviver';
    private const FEITO      = 'feito';

    public function handle(): int
    {
        $mapa = config('migracao-react');

        if (! $mapa) {
            $this->error('Falta o config/migracao-react.php — é ele que diz o que há para fazer.');

            return self::FAILURE;
        }

        $raiz = base_path($mapa['raiz'] ?? 'resources/js');

        $total = ['ecras' => 0, self::FEITO => 0, self::A_CONVIVER => 0, self::POR_FAZER => 0];
        $linhas = ['todas' => 0, 'tratadas' => 0];

        $filtro = $this->option('frente');

        foreach ($mapa['frentes'] as $nomeDaFrente => $frente) {
            if ($filtro && mb_stripos($nomeDaFrente, $filtro) === false) {
                continue;
            }

            $contas = [self::FEITO => 0, self::A_CONVIVER => 0, self::POR_FAZER => 0];
            $daFrente = ['todas' => 0, 'tratadas' => 0];
            $detalhes = [];

            foreach ($frente['ecras'] as $ecra) {
                $estado = $this->estadoDo($ecra, $raiz);
                $peso   = $this->linhasDo($ecra);

                $contas[$estado]++;
                $daFrente['todas'] += $peso;

                if ($estado !== self::POR_FAZER) {
                    $daFrente['tratadas'] += $peso;
                }

                $detalhes[] = [$estado, $ecra['nome'], $peso, $ecra['react'] ?? '—'];
            }

            $nEcras = count($frente['ecras']);
            $feitos = $contas[self::FEITO] + $contas[self::A_CONVIVER];

            $this->newLine();
            $this->line("  <options=bold>{$nomeDaFrente}</>");

            if (! empty($frente['nota'])) {
                $this->line("  <fg=gray>{$frente['nota']}</>");
            }

            $this->line('  ' . $this->barra($feitos, $nEcras) . '  '
                . $this->pct($feitos, $nEcras) . "   {$feitos} de {$nEcras} ecrãs");

            $this->line(sprintf(
                '    <fg=green>feito %d</>   <fg=yellow>a conviver %d</>   <fg=gray>por fazer %d</>   ·   %s de %s linhas tratadas (%s)',
                $contas[self::FEITO],
                $contas[self::A_CONVIVER],
                $contas[self::POR_FAZER],
                number_format($daFrente['tratadas'], 0, ',', '.'),
                number_format($daFrente['todas'], 0, ',', '.'),
                $this->pct($daFrente['tratadas'], $daFrente['todas'])
            ));

            if ($this->option('detalhe')) {
                $this->newLine();

                foreach ($detalhes as [$estado, $nome, $peso, $react]) {
                    $this->line(sprintf(
                        '      %s %s %7s linhas   <fg=gray>%s</>',
                        $this->marca($estado),
                        // À MÃO e não com %-32s: o sprintf conta BYTES, e um
                        // «ç» ou um «ã» ocupa dois — as colunas fugiam
                        // exactamente nos nomes portugueses.
                        $this->encostar($nome, 34),
                        number_format($peso, 0, ',', '.'),
                        $react
                    ));
                }
            }

            $total['ecras'] += $nEcras;
            foreach ($contas as $k => $v) {
                $total[$k] += $v;
            }
            $linhas['todas'] += $daFrente['todas'];
            $linhas['tratadas'] += $daFrente['tratadas'];
        }

        if ($total['ecras'] === 0) {
            $this->warn('Nenhuma frente corresponde a esse filtro.');

            return self::SUCCESS;
        }

        $tratados = $total[self::FEITO] + $total[self::A_CONVIVER];

        $this->newLine();
        $this->line('  <options=bold>NO TOTAL</>');
        $this->line('  ' . $this->barra($tratados, $total['ecras']) . '  '
            . $this->pct($tratados, $total['ecras']) . "   {$tratados} de {$total['ecras']} ecrãs");
        $this->line('  ' . $this->barra($total[self::FEITO], $total['ecras']) . '  '
            . $this->pct($total[self::FEITO], $total['ecras'])
            . "   fechados de vez (Livewire apagado)");

        $this->newLine();
        $this->line(sprintf(
            '  Falta escrever React para <options=bold>%s</> ecrãs, que hoje são <options=bold>%s</> linhas de Livewire e Blade.',
            $total[self::POR_FAZER],
            number_format($linhas['todas'] - $linhas['tratadas'], 0, ',', '.')
        ));

        if ($total[self::A_CONVIVER] > 0) {
            $this->newLine();
            $this->line(sprintf(
                '  <fg=yellow>Atenção:</> %d ecrã(s) têm as DUAS versões de pé. Enquanto assim for, uma'
                . ' correcção tem de ser feita duas vezes — é dívida, não é progresso.',
                $total[self::A_CONVIVER]
            ));
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * O estado sai do disco, nunca de um campo escrito à mão.
     *
     * A casca (menu, barra do topo) não tem componente Livewire próprio — vive
     * dentro do layout — por isso aí «feito» é o React existir.
     */
    private function estadoDo(array $ecra, string $raiz): string
    {
        $temReact = ! empty($ecra['react']) && is_file($raiz . '/' . $ecra['react']);

        if (! $temReact) {
            return self::POR_FAZER;
        }

        $velho = $ecra['livewire'] ?? null;

        if ($velho === null) {
            return self::FEITO;
        }

        return is_file(base_path($velho)) ? self::A_CONVIVER : self::FEITO;
    }

    /** O tamanho do que há para substituir, em linhas de Livewire mais Blade. */
    private function linhasDo(array $ecra): int
    {
        $linhas = 0;

        foreach (['livewire', 'blade'] as $chave) {
            $caminho = $ecra[$chave] ?? null;

            if ($caminho && is_file(base_path($caminho))) {
                $linhas += count(file(base_path($caminho)));
            }
        }

        return $linhas;
    }

    private function barra(int $feito, int $total): string
    {
        $largura = 24;
        $cheio = $total > 0 ? (int) round($feito / $total * $largura) : 0;

        return '<fg=green>' . str_repeat('█', $cheio) . '</>'
             . '<fg=gray>' . str_repeat('░', $largura - $cheio) . '</>';
    }

    /** Encosta à esquerda contando LETRAS e não bytes. */
    private function encostar(string $texto, int $largura): string
    {
        $falta = max(0, $largura - mb_strlen($texto));

        return $texto . str_repeat(' ', $falta);
    }

    private function pct(float $parte, float $total): string
    {
        return str_pad($total > 0 ? round($parte / $total * 100) . '%' : '—', 4, ' ', STR_PAD_LEFT);
    }

    private function marca(string $estado): string
    {
        return match ($estado) {
            self::FEITO      => '<fg=green>✓</>',
            self::A_CONVIVER => '<fg=yellow>◐</>',
            default          => '<fg=gray>○</>',
        };
    }
}
