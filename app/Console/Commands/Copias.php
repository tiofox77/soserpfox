<?php

namespace App\Console\Commands;

use App\Models\Copias\AgendaDeCopia;
use App\Models\Copias\CopiaDeSeguranca;
use App\Services\Copias\Agendador;
use App\Services\Copias\Cifra;
use App\Services\Copias\FazerCopia;
use App\Services\Copias\Pasta;
use Illuminate\Console\Command;

/**
 * AS CÓPIAS PELA CONSOLA:
 *
 *   copias fazer [--empresa=17]         uma cópia já (da plataforma, ou de uma empresa)
 *   copias devidas                     a que estiver na hora (para o cron, onde houver)
 *   copias estado                      as últimas e a próxima
 *   copias reindexar                   devolve ao índice as cópias que estão na pasta
 *   copias decifrar <cifrada> <saída> --frase=…   noutro servidor, sem a aplicação a correr
 */
class Copias extends Command
{
    protected $signature = 'copias {accao : fazer | devidas | estado | reindexar | decifrar}
        {origem? : (decifrar) o ficheiro .soscopia}
        {saida? : (decifrar) onde escrever o ficheiro em claro}
        {--empresa= : (fazer) id da empresa; sem ele, a base inteira}
        {--frase= : (decifrar) a frase-passe}';

    protected $description = 'Cópias de segurança: fazer, correr as devidas, ver o estado, reindexar e decifrar';

    public function handle(FazerCopia $fazer, Agendador $agendador): int
    {
        return match ($this->argument('accao')) {
            'fazer' => $this->fazer($fazer),
            'devidas' => $this->devidas($agendador),
            'estado' => $this->estado(),
            'reindexar' => $this->reindexar(),
            'decifrar' => $this->decifrar(),
            default => $this->erro('Acção desconhecida.'),
        };
    }

    private function fazer(FazerCopia $fazer): int
    {
        $empresa = $this->option('empresa') ? (int) $this->option('empresa') : null;

        try {
            $c = $fazer->fazer($empresa, 'manual');
        } catch (\Throwable $e) {
            return $this->erro('A cópia falhou: ' . $e->getMessage());
        }

        $this->info(sprintf('Cópia %s (%s MB)%s', $c->ficheiro, round($c->tamanho / 1048576, 2), $c->cifrada ? ' — cifrada' : ''));
        foreach ($c->envios as $e) {
            $this->line(sprintf('  → %s: %s%s', $e->destino?->nome ?? '?', $e->estado, $e->erro ? ' — ' . $e->erro : ''));
        }

        return self::SUCCESS;
    }

    private function devidas(Agendador $agendador): int
    {
        $r = $agendador->correrUma();
        $this->line($r ? "{$r['ambito']}: " . ($r['erro'] ?? 'cópia #' . $r['copia_id']) : 'Nenhuma cópia está na hora.');

        return self::SUCCESS;
    }

    private function estado(): int
    {
        $p = AgendaDeCopia::para(null);
        $this->info(sprintf('Plataforma: %s, de %dh em %dh · última %s · próxima %s · %s',
            $p->activa ? 'ligada' : 'DESLIGADA', $p->intervalo_horas, $p->intervalo_horas,
            $p->ultima_em ?? '—', $p->proxima_em ?? '—', $p->cifrar ? 'cifradas' : 'sem cifra'));

        $this->table(['#', 'Âmbito', 'Ficheiro', 'MB', 'Estado', 'Origem', 'Concluída', 'Envios'],
            CopiaDeSeguranca::with('envios.destino')->orderByDesc('id')->limit(15)->get()->map(fn ($c) => [
                $c->id, $c->tenant_id ? "empresa #{$c->tenant_id}" : 'plataforma', $c->ficheiro, round($c->tamanho / 1048576, 2),
                $c->estado . ($c->erro ? ' — ' . mb_strimwidth($c->erro, 0, 60, '…') : ''), $c->origem, $c->concluida_em,
                $c->envios->map(fn ($e) => ($e->destino?->nome ?? '?') . ':' . $e->estado)->implode(', '),
            ])->all());

        return self::SUCCESS;
    }

    private function reindexar(): int
    {
        $this->info('Cópias devolvidas ao índice: ' . Pasta::reindexar());

        return self::SUCCESS;
    }

    private function decifrar(): int
    {
        [$origem, $saida, $frase] = [$this->argument('origem'), $this->argument('saida'), (string) $this->option('frase')];
        if (! $origem || ! $saida || $frase === '') {
            return $this->erro('Uso: copias decifrar <ficheiro.soscopia> <saida> --frase="…"');
        }

        try {
            Cifra::decifrar($origem, $saida, $frase);
        } catch (\Throwable $e) {
            return $this->erro($e->getMessage());
        }

        $this->info("Decifrado para {$saida}.");

        return self::SUCCESS;
    }

    private function erro(string $m): int
    {
        $this->error($m);

        return self::FAILURE;
    }
}
