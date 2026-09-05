<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Dá aos papéis por omissão as permissões que lhes faltam — e mais nada.
 *
 * SÓ ACRESCENTA. Nunca tira. É a diferença que importa: um `syncPermissions`
 * com o mapa por omissão apagaria tudo o que cada empresa afinou à mão nos
 * seus papéis, e isso é trabalho de outra pessoa.
 *
 * Existe porque o mapa do Gestor envelheceu: dizia «.view, .create ou .edit»
 * e os módulos que vieram depois nomeiam a gestão com outros verbos
 * (`.manage`, `.gerir`, `.facturar`, `.decidir`, `.receber`). O Gestor abria
 * o Projetos e não podia criar um projeto. Corrigido o mapa, isto leva a
 * correcção aos papéis que já existem.
 *
 * A seco por omissão. Escreve em bloco (uma consulta), não papel a papel:
 * mil chamadas ao Spatie estouram o tempo do pedido HTTP a meio da escrita.
 */
class CompletarPapeis extends Command
{
    protected $signature = 'papeis:completar
        {--papel=Gestor : nome do papel (ou "todos" para todos os do mapa)}
        {--tenant= : só esta empresa}
        {--aplicar : escreve de facto}';

    protected $description = 'Acrescenta aos papéis por omissão as permissões em falta (nunca tira; a seco por omissão)';

    public function handle(): int
    {
        $todas = Permission::all();
        $mapa = getDefaultRolePermissionMap($todas);
        $idPorNome = $todas->pluck('id', 'name');

        $pedido = (string) $this->option('papel');
        $papeis = $pedido === 'todos' ? array_keys($mapa) : [$pedido];

        foreach ($papeis as $nome) {
            if (! isset($mapa[$nome])) {
                $this->error("  Papel «{$nome}» não existe no mapa por omissão.");

                return self::FAILURE;
            }
        }

        $consulta = Role::whereIn('name', $papeis);

        if ($this->option('tenant')) {
            $consulta->where('tenant_id', (int) $this->option('tenant'));
        }

        $existentes = $consulta->get();

        $this->line('  Papéis encontrados: '.$existentes->count().' ('.implode(', ', $papeis).')');
        $this->newLine();

        $jaTem = DB::table('role_has_permissions')
            ->whereIn('role_id', $existentes->pluck('id'))
            ->get()
            ->groupBy('role_id')
            ->map(fn ($linhas) => $linhas->pluck('permission_id')->flip());

        $aInserir = [];
        $porPapel = [];

        foreach ($existentes as $papel) {
            $devidas = $mapa[$papel->name] ?? [];
            $tem = $jaTem[$papel->id] ?? collect();
            $faltam = 0;

            foreach ($devidas as $nomePermissao) {
                $id = $idPorNome[$nomePermissao] ?? null;

                if ($id === null || $tem->has($id)) {
                    continue;
                }

                $aInserir[] = ['role_id' => $papel->id, 'permission_id' => $id];
                $faltam++;
            }

            if ($faltam > 0) {
                $porPapel[$papel->name] = ($porPapel[$papel->name] ?? 0) + $faltam;
            }
        }

        if (! $aInserir) {
            $this->info('  Nada a fazer: todos já têm o que lhes é devido.');

            return self::SUCCESS;
        }

        foreach ($porPapel as $nome => $n) {
            $this->line(sprintf('    %-30s %5d permissões a acrescentar', $nome, $n));
        }

        $this->newLine();
        $this->line('  Total a acrescentar: '.count($aInserir).' ligações papel↔permissão');

        // Uma amostra do que muda, para não se gravar às cegas.
        $amostra = collect($aInserir)->take(10)->map(function ($l) use ($existentes, $idPorNome) {
            $papel = $existentes->firstWhere('id', $l['role_id']);
            $permissao = $idPorNome->search($l['permission_id']);

            return "      {$papel?->name} (empresa {$papel?->tenant_id}) → {$permissao}";
        });

        $this->newLine();
        $this->line('  Amostra:');
        $amostra->each(fn ($l) => $this->line($l));

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->comment('  A SECO. Corra com --aplicar para gravar. (Só acrescenta; nunca tira.)');

            return self::SUCCESS;
        }

        foreach (array_chunk($aInserir, 500) as $bloco) {
            DB::table('role_has_permissions')->insertOrIgnore($bloco);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info('  Gravado: '.count($aInserir).' permissões acrescentadas.');

        return self::SUCCESS;
    }
}
