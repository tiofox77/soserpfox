<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;

/**
 * Afina os dias de período de teste de planos concretos.
 *
 * Separado do planos:trial-automatico de propósito: um decide SE o teste
 * arranca sozinho, este decide QUANTO dura. São decisões diferentes e
 * mudam-se em alturas diferentes.
 *
 * Só mexe nos planos indicados pelo slug — nunca em todos de uma vez, que
 * é como se estraga a tabela de preços sem dar por isso.
 */
class DiasDeTesteDosPlanos extends Command
{
    protected $signature = 'planos:dias-de-teste
        {--planos= : slugs separados por vírgula (ex.: business,enterprise)}
        {--dias= : novos dias de teste}
        {--so-ver : mostra o que mudaria, sem gravar}';

    protected $description = 'Muda os dias de período de teste de planos indicados';

    public function handle(): int
    {
        $slugs = array_filter(array_map('trim', explode(',', (string) $this->option('planos'))));
        $dias  = $this->option('dias');

        if (empty($slugs)) {
            $this->error('Indique --planos=slug1,slug2 (não mexo em todos por engano).');
            return self::FAILURE;
        }

        if ($dias === null || !is_numeric($dias) || (int) $dias < 0) {
            $this->error('Indique --dias= com um número (0 desliga o teste).');
            return self::FAILURE;
        }

        $dias = (int) $dias;
        $planos = Plan::whereIn('slug', $slugs)->get();

        $encontrados = $planos->pluck('slug')->all();
        $emFalta = array_diff($slugs, $encontrados);

        if (!empty($emFalta)) {
            $this->warn('Sem correspondência: ' . implode(', ', $emFalta));
        }

        if ($planos->isEmpty()) {
            $this->error('Nenhum plano encontrado.');
            return self::FAILURE;
        }

        $linhas = [];
        foreach ($planos as $p) {
            $antes = (int) $p->trial_days;

            if (!$this->option('so-ver') && $antes !== $dias) {
                $p->trial_days = $dias;
                $p->save();
            }

            $linhas[] = [
                $p->name,
                $p->slug,
                $antes . ' dias',
                $dias . ' dias' . ($antes === $dias ? ' (igual)' : ''),
                $p->auto_activate ? 'automático' : 'por aprovação',
            ];
        }

        $this->table(['plano', 'slug', 'antes', 'depois', 'arranque'], $linhas);

        $this->info($this->option('so-ver')
            ? '(só ver) nada foi gravado.'
            : $planos->count() . ' plano(s) actualizados.');

        return self::SUCCESS;
    }
}
