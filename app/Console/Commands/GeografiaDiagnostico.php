<?php

namespace App\Console\Commands;

use App\Support\Geografia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * O que está gravado nas moradas, e o que a AGT recusaria.
 *
 * Só lê. Serve para ver, antes e depois da migração, se ainda há países
 * escritos à mão — porque é esse valor que viaja para a AGT como
 * `customerCountry`, onde só cabem duas letras.
 */
class GeografiaDiagnostico extends Command
{
    protected $signature = 'geografia:diagnostico {--tabela= : só esta tabela}';

    protected $description = 'Mostra os países e províncias gravados nas moradas e o que a AGT nao aceita';

    private const TABELAS = ['tenants', 'invoicing_clients', 'invoicing_suppliers'];

    public function handle(): int
    {
        $this->info('DADOS DA APLICACAO');
        $this->line(sprintf('  %d paises ISO · %d provincias · %d municipios',
            count(Geografia::paises()), count(Geografia::provincias()), count(Geografia::todosOsMunicipios())));
        $this->line('  provincias novas em 2024: ' . implode(', ', Geografia::provinciasNovas()));
        $this->newLine();

        $porArranjar = 0;

        foreach (self::TABELAS as $tabela) {
            if ($this->option('tabela') && $this->option('tabela') !== $tabela) {
                continue;
            }

            $porArranjar += $this->tabela($tabela);
        }

        $this->newLine();

        if ($porArranjar === 0) {
            $this->info('Todas as moradas tem um pais que a AGT aceita.');

            return self::SUCCESS;
        }

        $this->warn("{$porArranjar} registo(s) com um pais que a AGT NAO aceita.");
        $this->line('  Corrigem-se no ecra da morada, escolhendo o pais da lista.');

        return self::FAILURE;
    }

    private function tabela(string $tabela): int
    {
        $this->line("<options=bold>{$tabela}</>");

        $linhas = DB::table($tabela)
            ->selectRaw('country, COUNT(*) as total')
            ->groupBy('country')
            ->orderByDesc('total')
            ->get();

        $mau = 0;
        $dados = [];

        foreach ($linhas as $l) {
            $valor = $l->country;
            $ok    = Geografia::ehPaisValido($valor);
            $sug   = $ok ? null : Geografia::normalizarPais($valor);

            if (!$ok) {
                $mau += (int) $l->total;
            }

            $dados[] = [
                $valor === null ? '(vazio)' : $valor,
                $l->total,
                $ok ? 'ok' : 'NAO ACEITE',
                $ok ? Geografia::nomeDoPais($valor) : ($sug ? "convertivel em {$sug}" : 'sem correspondencia'),
            ];
        }

        $this->table(['pais gravado', 'registos', 'AGT', 'nota'], $dados);

        // A coluna pode ainda não existir: este comando serve para se correr
        // ANTES da migração, e rebentar aí seria perder o retrato do antes.
        if (!\Illuminate\Support\Facades\Schema::hasColumn($tabela, 'province')) {
            $this->line('  (ainda sem coluna de provincia — migracao por correr)');

            return $mau;
        }

        // As províncias que não são nenhuma das 21 nem uma equivalência
        // conhecida: escritas à mão, ou de outro país.
        $estranhas = DB::table($tabela)
            ->selectRaw('province, COUNT(*) as total')
            ->whereNotNull('province')
            ->where('province', '!=', '')
            ->groupBy('province')
            ->pluck('total', 'province')
            ->reject(fn ($t, $p) => in_array(Geografia::normalizarProvincia($p), Geografia::provincias(), true));

        if ($estranhas->isNotEmpty()) {
            $this->line('  provincias fora da lista: ' . $estranhas->keys()->implode(', '));
        }

        return $mau;
    }
}
