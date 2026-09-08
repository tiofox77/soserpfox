<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Cria as permissões do RH — os três catálogos e os cinco pedidos — e
 * reparte-as pelos papéis de TODAS as empresas.
 *
 * PORQUE É QUE ESTAS NASCEM AGORA. O módulo de RH tinha 26 rotas guardadas
 * apenas por `auth` e `tenant.module:rh` — nem uma permissão aplicada, nem nas
 * rotas, nem nos componentes, nem nos controladores que geram os PDF. Qualquer
 * utilizador de uma empresa com o RH activo abria a folha de pagamento e via o
 * salário de toda a gente. Cada ecrã que passa para React ganha a sua guarda.
 *
 * A REPARTIÇÃO POR OMISSÃO não tira nada a ninguém que já usasse o módulo: quem
 * gere a empresa fica com tudo, quem trabalha nele fica a ver. Um papel à
 * medida com outro nome fica sem elas — e quem gere a empresa dá-lhas no ecrã
 * de papéis, que é onde essa decisão pertence.
 *
 * Como no `permissions:sync-quotes`, o trabalho é uma inserção em massa no
 * pivot `role_has_permissions` e não um `givePermissionTo` por papel: com
 * muitas empresas, o laço papel a papel não acaba dentro do tempo da rota de
 * manutenção. É idempotente (`insertOrIgnore`), por isso pode correr as vezes
 * que forem precisas.
 */
class SyncHrCatalogPermissions extends Command
{
    protected $signature = 'permissions:sync-rh {--aplicar : escreve de facto}';

    protected $description = 'Cria as permissões do RH (catálogos e pedidos) e reparte-as pelos papéis (a seco por omissão)';

    /** As permissões, com o nome por que se lêem no ecrã de papéis. */
    private const PERMISSOES = [
        'hr.departments.view' => 'Ver Departamentos',
        'hr.departments.create' => 'Criar Departamentos',
        'hr.departments.edit' => 'Editar Departamentos',
        'hr.departments.delete' => 'Eliminar Departamentos',
        'hr.positions.view' => 'Ver Cargos',
        'hr.positions.create' => 'Criar Cargos',
        'hr.positions.edit' => 'Editar Cargos',
        'hr.positions.delete' => 'Eliminar Cargos',
        'hr.shifts.view' => 'Ver Turnos',
        'hr.shifts.create' => 'Criar Turnos',
        'hr.shifts.edit' => 'Editar Turnos',
        'hr.shifts.delete' => 'Eliminar Turnos',

        /*
         * OS PEDIDOS. APROVAR é um verbo próprio e não «editar»: quem pede as
         * suas férias não é quem as autoriza, e é esta permissão que separa as
         * duas pessoas. Por isso os papéis de consulta NÃO a recebem.
         */
        'hr.vacations.view' => 'Ver Férias',
        'hr.vacations.create' => 'Pedir Férias',
        'hr.vacations.approve' => 'Aprovar Férias',
        'hr.vacations.delete' => 'Eliminar Pedidos de Férias',
        'hr.leaves.view' => 'Ver Licenças',
        'hr.leaves.create' => 'Registar Licenças',
        'hr.leaves.approve' => 'Aprovar Licenças',
        'hr.leaves.delete' => 'Eliminar Licenças',
        'hr.overtime.view' => 'Ver Horas Extras',
        'hr.overtime.create' => 'Lançar Horas Extras',
        'hr.overtime.approve' => 'Aprovar Horas Extras',
        'hr.overtime.delete' => 'Eliminar Horas Extras',
        'hr.advances.view' => 'Ver Adiantamentos',
        'hr.advances.create' => 'Pedir Adiantamentos',
        'hr.advances.approve' => 'Aprovar Adiantamentos',
        'hr.advances.delete' => 'Eliminar Adiantamentos',
        'hr.discounts.view' => 'Ver Descontos Salariais',
        'hr.discounts.create' => 'Registar Descontos Salariais',
        'hr.discounts.approve' => 'Aprovar Descontos Salariais',
        'hr.discounts.delete' => 'Eliminar Descontos Salariais',
    ];

    /** Estes gerem: ficam com todas. */
    private const GESTAO = [
        'Super Admin', 'Admin', 'Administrador', 'Gestor', 'Gerente',
        'Director', 'Diretor', 'Recursos Humanos', 'RH',
    ];

    /** Estes consultam: ficam só com as de VER. */
    private const CONSULTA = ['Utilizador', 'Contabilista'];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');

        if (! $aplicar) {
            $this->comment('A SECO — nada é escrito. Use --aplicar.');
        }

        $ids = [];

        foreach (self::PERMISSOES as $nome => $descricao) {
            if ($aplicar) {
                $ids[$nome] = Permission::firstOrCreate(
                    ['name' => $nome, 'guard_name' => 'web'],
                    ['description' => $descricao]
                )->id;
                $this->line("  ✓ {$nome}");
            } else {
                $existe = Permission::where('name', $nome)->exists();
                $this->line("  {$nome}: " . ($existe ? 'já existe' : 'seria criada'));
            }
        }

        if (! $aplicar) {
            $this->line('');
            $this->line('  Papéis que ficariam com tudo: ' . implode(', ', self::GESTAO));
            $this->line('  Papéis que ficariam só a ver: ' . implode(', ', self::CONSULTA));

            return self::SUCCESS;
        }

        $todas = array_values($ids);

        /*
         * Quem consulta fica só com as de VER — nunca com as de aprovar.
         * Aprovar férias ou um adiantamento é uma decisão que custa dinheiro,
         * e não se dá a um papel por ele se chamar «Utilizador».
         */
        $verApenas = collect($ids)
            ->filter(fn ($id, $nome) => str_ends_with($nome, '.view'))
            ->values()->all();

        $linhas = [];

        foreach ([[self::GESTAO, $todas], [self::CONSULTA, $verApenas]] as [$nomes, $permissoes]) {
            foreach ($nomes as $nome) {
                foreach (Role::where('name', $nome)->pluck('id') as $papel) {
                    foreach ($permissoes as $permissao) {
                        $linhas[] = ['permission_id' => $permissao, 'role_id' => $papel];
                    }
                }
            }
        }

        $novas = 0;

        foreach (array_chunk($linhas, 1000) as $bloco) {
            $novas += DB::table('role_has_permissions')->insertOrIgnore($bloco);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info(count($linhas) . ' ligação(ões) processada(s), ' . $novas . ' nova(s).');

        return self::SUCCESS;
    }
}
