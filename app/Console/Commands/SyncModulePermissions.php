<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenant\TenantModuleSyncService;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Corrige a lacuna: módulos ativos no tenant mas SEM permissões definidas
 * (Eventos, Notificações, CRM, Inventário, Compras, Projetos) — o que fazia o
 * canAccessModuleMenu() escondê-los do sidebar apesar de o plano os incluir.
 *
 * 1) Cria (firstOrCreate) as permissões em falta desses módulos.
 * 2) Para cada tenant, concede as permissões dos módulos ATIVOS aos seus roles,
 *    reutilizando TenantModuleSyncService::activateModule (mapa canónico de roles).
 *
 * Idempotente e não-destrutivo (nunca desativa módulos).
 */
class SyncModulePermissions extends Command
{
    protected $signature = 'modules:sync-permissions {--tenant= : Só este tenant ID}';
    protected $description = 'Cria permissões em falta dos módulos e concede-as aos roles dos tenants ativos';

    /**
     * Permissões por módulo que estavam em falta.
     * Eventos/Notificações: conjunto granular (módulos implementados).
     * CRM/Inventário/Compras/Projetos: acesso base (páginas em construção) — dá visibilidade no sidebar.
     */
    private array $modulePerms = [
        // Eventos (implementado)
        'events.dashboard.view'    => 'Ver Dashboard de Eventos',
        'events.calendar.view'     => 'Ver Calendário de Eventos',
        'events.calendar.manage'   => 'Gerir Eventos (criar/editar/eliminar)',
        'events.equipment.view'    => 'Ver Equipamentos de Eventos',
        'events.equipment.manage'  => 'Gerir Equipamentos de Eventos',
        'events.venues.view'       => 'Ver Locais',
        'events.venues.manage'     => 'Gerir Locais',
        'events.technicians.view'  => 'Ver Técnicos',
        'events.technicians.manage'=> 'Gerir Técnicos',
        'events.types.view'        => 'Ver Tipos de Eventos',
        'events.types.manage'      => 'Gerir Tipos de Eventos',
        'events.reports.view'      => 'Ver Relatórios de Eventos',
        // Notificações (implementado)
        'notifications.view'       => 'Ver Notificações',
        'notifications.manage'     => 'Gerir Configurações de Notificações',
        'notifications.send'       => 'Enviar Notificações',
        // Contabilidade — NENHUMA permissão 'accounting.*' existia no sistema, pelo
        // que canAccessModuleMenu() escondia o módulo a 100% dos tenants (mesmo com
        // o plano a incluí-lo). Espelha as rotas de routes/web.php (accounting.*).
        'accounting.dashboard.view'      => 'Ver Dashboard de Contabilidade',
        'accounting.accounts.view'       => 'Ver Plano de Contas',
        'accounting.accounts.manage'     => 'Gerir Plano de Contas',
        'accounting.journals.view'       => 'Ver Diários',
        'accounting.journals.manage'     => 'Gerir Diários',
        'accounting.document-types.view' => 'Ver Tipos de Documento',
        'accounting.document-types.manage' => 'Gerir Tipos de Documento',
        'accounting.moves.view'          => 'Ver Movimentos Contabilísticos',
        'accounting.moves.manage'        => 'Gerir Movimentos Contabilísticos',
        'accounting.periods.view'        => 'Ver Períodos Contabilísticos',
        'accounting.periods.manage'      => 'Gerir/Fechar Períodos',
        'accounting.reports.view'        => 'Ver Relatórios Contabilísticos',
        'accounting.reconciliation.view' => 'Ver Reconciliação Bancária',
        'accounting.reconciliation.manage' => 'Gerir Reconciliação Bancária',
        'accounting.fixed-assets.view'   => 'Ver Imobilizado',
        'accounting.fixed-assets.manage' => 'Gerir Imobilizado',
        'accounting.currencies.view'     => 'Ver Moedas e Câmbios',
        'accounting.currencies.manage'   => 'Gerir Moedas e Câmbios',
        'accounting.cost-centers.view'   => 'Ver Centros de Custo',
        'accounting.cost-centers.manage' => 'Gerir Centros de Custo',
        'accounting.analytics.view'      => 'Ver Contabilidade Analítica',
        'accounting.budgets.view'        => 'Ver Orçamentos',
        'accounting.budgets.manage'      => 'Gerir Orçamentos',
        'accounting.settings.view'       => 'Ver Configurações de Contabilidade',
        'accounting.settings.edit'       => 'Editar Configurações de Contabilidade',
        // Módulos em construção — acesso base para aparecerem no sidebar
        'crm.view'                 => 'Aceder ao CRM',
        'inventario.view'          => 'Aceder ao Inventário',
        'compras.view'             => 'Aceder às Compras',
        'projetos.view'            => 'Aceder aos Projetos',
    ];

    public function handle(TenantModuleSyncService $service): int
    {
        $this->info('1) A criar permissões em falta...');
        $created = 0;
        foreach ($this->modulePerms as $name => $desc) {
            $perm = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $desc]
            );
            if ($perm->wasRecentlyCreated) {
                $created++;
            }
        }
        $this->info("   {$created} permissões criadas (as restantes já existiam).");

        $tenants = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->get()
            : Tenant::all();

        $this->info("2) A conceder permissões dos módulos ativos aos roles de {$tenants->count()} tenant(s)...");
        foreach ($tenants as $tenant) {
            setPermissionsTeamId($tenant->id);
            $activeSlugs = $tenant->modules()
                ->wherePivot('is_active', true)
                ->pluck('modules.slug')
                ->toArray();

            foreach ($activeSlugs as $slug) {
                $service->activateModule($tenant, $slug);
            }
            $this->line("   • Tenant {$tenant->id} ({$tenant->name}): " . count($activeSlugs) . ' módulos ativos sincronizados');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->info('✓ Concluído. Recarregue o sistema para ver os módulos no menu.');

        return Command::SUCCESS;
    }
}
