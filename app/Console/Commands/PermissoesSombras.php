<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Permissões que engolem outras.
 *
 * Com `enable_wildcard_permission` ligado, o Spatie lê os nomes por partes:
 * quem tem `invoicing.pos.reports` tem, sem que ninguém lho tenha dado,
 * `invoicing.pos.reports.all` — e um caixa via as vendas de todos os
 * colegas. A distinção «ver» / «ver de todos» ficava sem efeito.
 *
 * Este comando SÓ LÊ. Lista os pares em que um nome é prefixo de outro e diz
 * quantos papéis têm o curto sem o longo — que é exactamente quem ganha
 * poder a mais hoje, e quem deixa de o ter quando o wildcard se desliga.
 */
class PermissoesSombras extends Command
{
    protected $signature = 'permissoes:sombras';

    protected $description = 'Lista permissões cujo nome é prefixo de outra (só lê)';

    public function handle(): int
    {
        $ligado = (bool) config('permission.enable_wildcard_permission');

        $this->line('  enable_wildcard_permission: '.($ligado ? 'LIGADO ⚠' : 'desligado'));
        $this->newLine();

        $nomes = DB::table('permissions')->orderBy('name')->pluck('name')->all();
        $this->line('  Permissões registadas: '.count($nomes));

        $pares = [];
        foreach ($nomes as $curta) {
            foreach ($nomes as $longa) {
                if ($longa !== $curta && str_starts_with($longa, $curta.'.')) {
                    $pares[] = [$curta, $longa];
                }
            }
        }

        if (! $pares) {
            $this->info('  Nenhum nome é prefixo de outro. Nada a esconder.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  Pares (ter a CURTA implica, com wildcard, ter a LONGA):');
        $this->newLine();

        foreach ($pares as [$curta, $longa]) {
            $papeis = DB::table('roles as r')
                ->whereExists(fn ($q) => $q->from('role_has_permissions as rp')
                    ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                    ->whereColumn('rp.role_id', 'r.id')->where('p.name', $curta))
                ->whereNotExists(fn ($q) => $q->from('role_has_permissions as rp')
                    ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                    ->whereColumn('rp.role_id', 'r.id')->where('p.name', $longa))
                ->count();

            $utilizadores = DB::table('model_has_permissions as mp')
                ->join('permissions as p', 'p.id', '=', 'mp.permission_id')
                ->where('p.name', $curta)
                ->count();

            // Com o wildcard desligado o mesmo número deixa de ser um alarme:
            // é só quanta gente tem a curta e NÃO a longa — que é o que se quer.
            if ($papeis === 0) {
                $estado = '<fg=green>nenhum papel tem a curta sem a longa</>';
            } elseif ($ligado) {
                $estado = "<fg=red>{$papeis} papel(éis) ganham a longa sem lha darem</>";
            } else {
                $estado = "<fg=green>{$papeis} papel(éis) têm a curta e não a longa — e assim fica</>";
            }

            $this->line(sprintf(
                '  %-38s → %-14s  %s%s',
                $curta,
                substr($longa, strlen($curta) + 1),
                $estado,
                $utilizadores ? "  (+{$utilizadores} atribuições directas da curta)" : ''
            ));
        }

        $this->newLine();
        $this->comment($ligado
            ? '  Com o wildcard LIGADO, as linhas a vermelho são poder a mais que ninguém concedeu.'
            : '  Wildcard desligado: cada permissão vale por si.');

        return self::SUCCESS;
    }
}
