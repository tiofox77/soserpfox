<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\CatalogoDePermissoes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Falta alguma permissão? Sobra alguma? Falta algum papel a uma empresa?
 *
 * SÓ LÊ. Cruza três coisas: as permissões que o CÓDIGO verifica (rotas,
 * `can()`, `@can`, `podeVer()`), as que estão REGISTADAS na base, e os
 * papéis por omissão que cada empresa devia ter.
 *
 *   permissoes:catalogo              — o sistema inteiro
 *   permissoes:catalogo --tenant=80  — mais os papéis dessa empresa
 */
class CatalogoDePermissoesCommand extends Command
{
    protected $signature = 'permissoes:catalogo {--tenant= : id da empresa para conferir os papéis}';

    protected $description = 'Confere permissões em falta, mortas e sem descrição, e os papéis de uma empresa (só lê)';

    public function handle(): int
    {
        $registadas = Permission::orderBy('name')->get();
        $nomes = $registadas->pluck('name')->all();
        $noCodigo = $this->nomesVerificadosNoCodigo();

        // 1. O código verifica e a base não tem: ninguém passa, nem o admin.
        $emFalta = array_values(array_diff($noCodigo, $nomes));
        $this->titulo('Verificadas no código mas NÃO registadas (ninguém as tem)', count($emFalta));
        foreach ($emFalta as $n) {
            $this->line("    <fg=red>{$n}</>");
        }

        // 2. Registadas mas nenhum portão verifica: marcar não faz nada.
        $mortas = array_values(array_filter($nomes, fn ($n) => ! in_array($n, $noCodigo, true) && CatalogoDePermissoes::grupoDe($n) === null));
        $this->titulo('Registadas fora de qualquer módulo e sem portão no código (lixo antigo)', count($mortas));
        foreach ($mortas as $n) {
            $this->line("    <fg=yellow>{$n}</>");
        }

        // 3. Sem descrição.
        $semDescricao = $registadas->filter(fn ($p) => trim((string) $p->description) === '')->count();
        $this->titulo('Sem descrição (permissoes:descrever --aplicar preenche)', $semDescricao);

        // 4. Por módulo: quantas registadas.
        $this->newLine();
        $this->line('  Por grupo:');
        foreach (CatalogoDePermissoes::NUCLEO + CatalogoDePermissoes::MODULOS as $slug => $g) {
            $n = CatalogoDePermissoes::doGrupo($registadas, $g)->count();
            $this->line(sprintf('    %-24s %3d', $g['nome'], $n));
        }

        // 5. Os papéis de uma empresa.
        if ($this->option('tenant')) {
            $this->papeisDaEmpresa((int) $this->option('tenant'));
        }

        return self::SUCCESS;
    }

    private function papeisDaEmpresa(int $tenantId): void
    {
        $empresa = Tenant::find($tenantId);

        if (! $empresa) {
            $this->error('  Empresa não encontrada.');

            return;
        }

        $this->newLine();
        $this->line("  Empresa #{$empresa->id} {$empresa->name}");

        $activos = $empresa->modules()->wherePivot('is_active', true)->pluck('slug')->all();
        $this->line('    Módulos activos: '.(implode(', ', $activos) ?: 'nenhum'));

        $existentes = Role::where('tenant_id', $tenantId)->withCount('permissions', 'users')->get();
        $esperados = array_keys(getDefaultRolePermissionMap(collect()));

        $faltam = array_values(array_diff($esperados, $existentes->pluck('name')->all()));
        $this->titulo('Papéis por omissão em falta nesta empresa', count($faltam));
        foreach ($faltam as $n) {
            $this->line("    <fg=yellow>{$n}</>");
        }

        $this->newLine();
        $this->line('    Papéis existentes:');
        foreach ($existentes as $r) {
            $this->line(sprintf('      %-30s %3d permissões  %2d utilizador(es)', $r->name, $r->permissions_count, $r->users_count));
        }

        // Um módulo activo que nenhum papel vê é um módulo pago que ninguém usa.
        $registadas = Permission::all();
        $idsDosPapeis = DB::table('role_has_permissions')
            ->whereIn('role_id', $existentes->pluck('id'))
            ->pluck('permission_id')->unique();
        $comAlgumaPermissao = $registadas->whereIn('id', $idsDosPapeis);

        $cegos = [];
        foreach (CatalogoDePermissoes::gruposPara($activos) as $slug => $g) {
            if (isset(CatalogoDePermissoes::NUCLEO[$slug])) {
                continue;
            }
            if (CatalogoDePermissoes::doGrupo($comAlgumaPermissao, $g)->isEmpty()) {
                $cegos[] = $g['nome'];
            }
        }
        $this->titulo('Módulos activos que nenhum papel consegue ver', count($cegos));
        foreach ($cegos as $n) {
            $this->line("    <fg=red>{$n}</>");
        }
    }

    /** Os nomes que o código verifica, lidos dos ficheiros. */
    private function nomesVerificadosNoCodigo(): array
    {
        $padrao = "/(?:can\(|permission:|podeVer\(|hasPermissionTo\(|@can\(|valorProtegido\([^,]+,\s*)'([a-z][a-z0-9_.-]+\.[a-z0-9_.-]+)'/";
        $nomes = [];

        foreach ([app_path(), base_path('routes'), resource_path('views')] as $pasta) {
            foreach (File::allFiles($pasta) as $f) {
                if (! in_array($f->getExtension(), ['php'], true)) {
                    continue;
                }
                if (preg_match_all($padrao, File::get($f->getPathname()), $m)) {
                    foreach ($m[1] as $n) {
                        $nomes[$n] = true;
                    }
                }
            }
        }

        // Os middlewares com várias: permission:a|b
        foreach (File::allFiles(base_path('routes')) as $f) {
            if (preg_match_all("/permission:([a-z0-9_.|-]+)/", File::get($f->getPathname()), $m)) {
                foreach ($m[1] as $lista) {
                    foreach (explode('|', $lista) as $n) {
                        if (str_contains($n, '.')) {
                            $nomes[$n] = true;
                        }
                    }
                }
            }
        }

        ksort($nomes);

        return array_keys($nomes);
    }

    private function titulo(string $texto, int $n): void
    {
        $this->newLine();
        $this->line("  {$texto}: <options=bold>{$n}</>");
    }
}
