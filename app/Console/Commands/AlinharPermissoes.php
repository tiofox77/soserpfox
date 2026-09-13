<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * AS PERMISSÕES QUE O CÓDIGO PEDE TÊM DE EXISTIR NA BASE.
 *
 * A migração para React pôs os ecrãs a pedir permissões finas — o painel de
 * cada módulo, os pedidos do RH, as guias, o apagar dos catálogos do hotel e da
 * oficina. Essas permissões nasciam de comandos `permissions:sync-*` que
 * ninguém correu em produção. Resultado (auditoria de 2026-09-13, contra a
 * cópia da base das 14:32): 58 permissões pedidas pelo código NÃO EXISTIAM, e
 * o RH inteiro, as guias e os painéis do hotel, da oficina e do salão davam
 * 403 a todos os papéis de todas as empresas, Super Admin incluído — e
 * desapareciam do menu.
 *
 *   permissoes:alinhar            → diz o que faria (não escreve)
 *   permissoes:alinhar --aplicar  → cria e reparte
 *
 * Reparte de duas formas, as duas idempotentes:
 *   1. os sincronizadores de cada área, que dão pelos papéis de sempre;
 *   2. por EQUIVALÊNCIA: quem já tinha a permissão antiga recebe a nova. É o
 *      que chega aos papéis que as empresas criaram à mão, que os
 *      sincronizadores (que vão pelo nome do papel) não conhecem.
 *
 * No fim confere: nenhuma permissão da lista pode continuar a faltar.
 */
class AlinharPermissoes extends Command
{
    protected $signature = 'permissoes:alinhar {--aplicar : escreve de facto}';

    protected $description = 'Cria as permissões que o código pede e reparte-as (a seco por omissão)';

    private const SINCRONIZADORES = [
        'permissions:sync-rh',
        'permissions:sync-guias',
        'permissions:sync-hotel',
        'permissions:sync-oficina',
    ];

    /** A nova e o nome por que se lê no ecrã de papéis. */
    private const NOVAS = [
        'treasury.transfers.delete' => 'Anular Transferências',
        'hotel.dashboard.view' => 'Ver o Painel do Hotel',
        'workshop.dashboard.view' => 'Ver o Painel da Oficina',
        'salon.dashboard.view' => 'Ver o Painel do Salão',
        'hr.dashboard.view' => 'Ver o Painel de RH',
    ];

    /** Quem tem a da esquerda passa a ter as da direita. */
    private const EQUIVALENTES = [
        'treasury.transfers.create' => ['treasury.transfers.delete'],
        'hotel.dashboard' => ['hotel.dashboard.view'],
        'workshop.dashboard' => ['workshop.dashboard.view'],
        'salon.dashboard' => ['salon.dashboard.view'],
        // O RH em produção era guardado por `employees.*`: quem via os
        // funcionários via o módulo. Consultar, só — aprovar e apagar ficam
        // com os papéis de gestão (sincronizador do RH).
        'employees.view' => [
            'hr.dashboard.view', 'hr.vacations.view', 'hr.leaves.view', 'hr.overtime.view', 'hr.advances.view',
            'hr.discounts.view', 'hr.contracts.view', 'hr.departments.view', 'hr.positions.view', 'hr.shifts.view',
            'hr.reports.view', 'hr.irt.view', 'hr.settings.view',
        ],
        // O menu já tratava as notas de débito como a porta das guias.
        'invoicing.debit-notes.view' => ['invoicing.transport-guides.view'],
        'invoicing.sales.invoices.create' => ['invoicing.transport-guides.view', 'invoicing.transport-guides.create'],
    ];

    /** Todas as que o código pede e que faltavam em produção. */
    private const OBRIGATORIAS = [
        'hotel.dashboard.view', 'hotel.maintenance.delete', 'hotel.packages.delete', 'hotel.rates.delete', 'hotel.staff.delete',
        'workshop.dashboard.view', 'workshop.mechanics.delete', 'workshop.parts.delete', 'workshop.services.delete', 'workshop.vehicles.delete',
        'salon.dashboard.view', 'treasury.transfers.delete',
        'invoicing.transport-guides.view', 'invoicing.transport-guides.create', 'invoicing.transport-guides.edit', 'invoicing.transport-guides.delete',
        'hr.dashboard.view', 'hr.reports.view', 'hr.irt.view', 'hr.settings.view', 'hr.settings.edit',
        'hr.vacations.view', 'hr.vacations.create', 'hr.vacations.approve', 'hr.vacations.delete',
        'hr.leaves.view', 'hr.leaves.create', 'hr.leaves.approve', 'hr.leaves.delete',
        'hr.overtime.view', 'hr.overtime.create', 'hr.overtime.approve', 'hr.overtime.delete',
        'hr.advances.view', 'hr.advances.create', 'hr.advances.approve', 'hr.advances.delete',
        'hr.discounts.view', 'hr.discounts.create', 'hr.discounts.approve', 'hr.discounts.delete',
        'hr.contracts.view', 'hr.contracts.create', 'hr.contracts.edit', 'hr.contracts.delete',
        'hr.departments.view', 'hr.departments.create', 'hr.departments.edit', 'hr.departments.delete',
        'hr.positions.view', 'hr.positions.create', 'hr.positions.edit', 'hr.positions.delete',
        'hr.shifts.view', 'hr.shifts.create', 'hr.shifts.edit', 'hr.shifts.delete',
    ];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');

        $this->info($aplicar ? 'A APLICAR' : 'A SECO — nada é escrito. Use --aplicar.');

        foreach (self::SINCRONIZADORES as $comando) {
            $this->line('');
            $this->line("<options=bold>{$comando}</>");
            $this->call($comando, $aplicar ? ['--aplicar' => true] : []);
        }

        $this->line('');
        $this->line('<options=bold>As que nenhum sincronizador criava</>');

        foreach (self::NOVAS as $nome => $descricao) {
            if ($aplicar) {
                Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web'], ['description' => $descricao]);
            }
            $this->line("  {$nome}: " . (Permission::where('name', $nome)->exists() ? 'existe' : 'seria criada'));
        }

        $this->line('');
        $this->line('<options=bold>Por equivalência (chega aos papéis feitos à mão)</>');

        $novasLigacoes = 0;

        foreach (self::EQUIVALENTES as $fonte => $destinos) {
            $papeis = DB::table('role_has_permissions as rp')
                ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                ->where('p.name', $fonte)
                ->pluck('rp.role_id');

            foreach ($destinos as $destino) {
                $permissao = Permission::where('name', $destino)->first();

                if (! $permissao) {
                    $this->line("  {$fonte} → {$destino}: {$papeis->count()} papel(éis) (a permissão ainda não existe)");

                    continue;
                }

                $jaTem = DB::table('role_has_permissions')->where('permission_id', $permissao->id)
                    ->whereIn('role_id', $papeis)->count();
                $faltam = $papeis->count() - $jaTem;

                if ($aplicar && $faltam > 0) {
                    $novasLigacoes += DB::table('role_has_permissions')->insertOrIgnore(
                        $papeis->map(fn ($id) => ['permission_id' => $permissao->id, 'role_id' => $id])->all()
                    );
                }

                $this->line("  {$fonte} → {$destino}: " . ($aplicar ? "{$faltam} atribuída(s)" : "{$faltam} por atribuir") . " (de {$papeis->count()} papéis)");
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $faltam = collect(self::OBRIGATORIAS)->reject(fn ($n) => Permission::where('name', $n)->exists())->values();

        $this->line('');

        if ($faltam->isEmpty()) {
            $this->info('Todas as ' . count(self::OBRIGATORIAS) . ' permissões que o código pede existem.' . ($aplicar ? " Ligações novas por equivalência: {$novasLigacoes}." : ''));

            return self::SUCCESS;
        }

        $this->warn('Continuam a faltar ' . $faltam->count() . ': ' . $faltam->implode(', '));

        return $aplicar ? self::FAILURE : self::SUCCESS;
    }
}
