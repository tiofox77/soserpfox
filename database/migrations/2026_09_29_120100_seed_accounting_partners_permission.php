<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * A permissão do ecrã novo da CONTA-CORRENTE DE TERCEIROS.
 *
 * Quem já via os relatórios da contabilidade passa a ver também a
 * conta-corrente — é a mesma natureza de informação (só leitura, dados da
 * empresa). Espelha-se `accounting.reports.view` papel a papel e utilizador a
 * utilizador, em todas as empresas, sem adivinhar nomes de papéis.
 *
 * O `modules:sync-permissions` também a criaria (está na lista dele), mas só a
 * concederia ao próximo `sync`; assim o ecrã aparece logo a seguir ao deploy.
 */
return new class extends Migration
{
    private const NOVA = 'accounting.partners.view';
    private const ORIGEM = 'accounting.reports.view';

    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $nova = Permission::firstOrCreate(
            ['name' => self::NOVA, 'guard_name' => 'web'],
            ['description' => 'Ver Conta-Corrente de Terceiros']
        );

        $origem = DB::table('permissions')
            ->where('name', self::ORIGEM)
            ->where('guard_name', 'web')
            ->value('id');

        if ($origem) {
            DB::table('role_has_permissions')
                ->where('permission_id', $origem)
                ->orderBy('role_id')
                ->pluck('role_id')
                ->chunk(500)
                ->each(fn ($ids) => DB::table('role_has_permissions')->insertOrIgnore(
                    $ids->map(fn ($roleId) => ['permission_id' => $nova->id, 'role_id' => $roleId])->all()
                ));

            DB::table('model_has_permissions')
                ->where('permission_id', $origem)
                ->get(['model_type', 'model_id', 'tenant_id'])
                ->chunk(500)
                ->each(fn ($linhas) => DB::table('model_has_permissions')->insertOrIgnore(
                    $linhas->map(fn ($l) => [
                        'permission_id' => $nova->id,
                        'model_type'    => $l->model_type,
                        'model_id'      => $l->model_id,
                        'tenant_id'     => $l->tenant_id,
                    ])->all()
                ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        // As ligações aos papéis e utilizadores caem em cascata com a permissão.
        Permission::where('name', self::NOVA)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
