<?php

namespace App\Console\Commands;

use App\Support\CatalogoDePermissoes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

/**
 * Apaga as permissões que nenhum portão do sistema verifica.
 *
 * São restos de um seeder antigo — `invoices.*`, `payments.*`, `repairs.*`,
 * `vehicles.*` — que ficaram quando os módulos passaram a usar nomes com
 * prefixo próprio. Marcá-las no ecrã dos papéis não faz absolutamente nada, e
 * isso é pior do que não as ter: quem gere uma empresa marca-as a pensar que
 * está a conceder alguma coisa.
 *
 * SÓ APAGA O QUE PROVA SER MORTO: tem de estar fora de todos os módulos do
 * catálogo E não ser verificada em lado nenhum do código (rotas, `can()`,
 * `@can`, `podeVer()`). A seco por omissão.
 */
class LimparPermissoesMortas extends Command
{
    protected $signature = 'permissoes:limpar-mortas {--aplicar : apaga de facto}';

    protected $description = 'Apaga permissões que nenhum portão verifica (a seco por omissão)';

    public function handle(): int
    {
        $noCodigo = $this->nomesVerificadosNoCodigo();
        $mortas = Permission::orderBy('name')->get()
            ->filter(fn ($p) => CatalogoDePermissoes::grupoDe($p->name) === null
                && ! in_array($p->name, $noCodigo, true));

        if ($mortas->isEmpty()) {
            $this->info('  Nenhuma permissão morta. Nada a fazer.');

            return self::SUCCESS;
        }

        $this->line('  Permissões que nenhum portão verifica: '.$mortas->count());
        $this->newLine();

        foreach ($mortas as $p) {
            $papeis = DB::table('role_has_permissions')->where('permission_id', $p->id)->count();
            $directas = DB::table('model_has_permissions')->where('permission_id', $p->id)->count();

            $this->line(sprintf('    %-28s em %d papel(éis), %d atribuição(ões) directa(s)', $p->name, $papeis, $directas));
        }

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->comment('  A SECO. Corra com --aplicar para apagar.');

            return self::SUCCESS;
        }

        $ids = $mortas->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        Permission::whereIn('id', $ids)->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info('  Apagadas: '.$mortas->count().' permissões e as suas atribuições.');

        return self::SUCCESS;
    }

    /** Os nomes que o código verifica, lidos dos ficheiros. */
    private function nomesVerificadosNoCodigo(): array
    {
        $padrao = "/(?:can\(|permission:|podeVer\(|hasPermissionTo\(|@can\(|valorProtegido\([^,]+,\s*)'([a-z][a-z0-9_.-]+\.[a-z0-9_.-]+)'/";
        $nomes = [];

        foreach ([app_path(), base_path('routes'), resource_path('views')] as $pasta) {
            foreach (File::allFiles($pasta) as $f) {
                if ($f->getExtension() !== 'php') {
                    continue;
                }

                if (preg_match_all($padrao, File::get($f->getPathname()), $m)) {
                    foreach ($m[1] as $n) {
                        $nomes[$n] = true;
                    }
                }
            }
        }

        foreach (File::allFiles(base_path('routes')) as $f) {
            if (preg_match_all('/permission:([a-z0-9_.|-]+)/', File::get($f->getPathname()), $m)) {
                foreach ($m[1] as $lista) {
                    foreach (explode('|', $lista) as $n) {
                        if (str_contains($n, '.')) {
                            $nomes[$n] = true;
                        }
                    }
                }
            }
        }

        return array_keys($nomes);
    }
}
