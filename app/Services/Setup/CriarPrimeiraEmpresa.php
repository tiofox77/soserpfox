<?php

namespace App\Services\Setup;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\TenantModuleSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * A PRIMEIRA EMPRESA DE UMA INSTALAÇÃO ON-PREMISE.
 *
 * Numa instalação fresca a base de dados só tem o super admin do sistema e
 * nenhuma empresa. Depois de a licença estar activa, o assistente cria a
 * empresa e o administrador — provisionando-a como o registo normal faz
 * (papéis, contabilidade PGC-AO, módulos do plano, subscrição).
 *
 * Tudo numa transacção: um erro no último passo não deixa uma empresa a meio,
 * sem administrador, que depois fecharia a porta do assistente (ele só abre
 * enquanto não houver empresa nenhuma).
 */
class CriarPrimeiraEmpresa
{
    /** O assistente só serve uma instalação sem empresa nenhuma. */
    public function jaHaEmpresa(): bool
    {
        return Tenant::query()->exists();
    }

    /**
     * @param  array{empresa:string,nif?:?string,regime:string,endereco?:?string,telefone?:?string,email_empresa?:?string,admin_nome:string,admin_email:string,admin_password:string}  $d
     */
    public function criar(array $d, ?string $planoDaLicenca): Tenant
    {
        $plano = $planoDaLicenca
            ? (Plan::where('slug', $planoDaLicenca)->orWhere('name', $planoDaLicenca)->first() ?? Plan::query()->first())
            : Plan::query()->first();

        return DB::transaction(function () use ($d, $plano) {
            $tenant = Tenant::create([
                'name' => $d['empresa'],
                'company_name' => $d['empresa'],
                'nif' => ($d['nif'] ?? null) ?: null,
                'regime' => Tenant::canonicalRegime($d['regime']),
                'address' => ($d['endereco'] ?? null) ?: null,
                'phone' => ($d['telefone'] ?? null) ?: null,
                'email' => ($d['email_empresa'] ?? null) ?: $d['admin_email'],
                'is_active' => true,
            ]);

            $user = User::create([
                'name' => $d['admin_nome'],
                'email' => $d['admin_email'],
                'password' => Hash::make($d['admin_password']),
                'is_active' => true,
                'is_super_admin' => false,
            ]);
            $user->tenants()->attach($tenant->id, ['is_active' => true, 'joined_at' => now()]);
            $user->tenant_id = $tenant->id;
            $user->save();

            setPermissionsTeamId($tenant->id);
            createDefaultRolesForTenant($tenant->id);
            $papel = Role::where('name', 'Super Admin')->where('tenant_id', $tenant->id)->first();
            if ($papel) {
                $user->assignRole($papel);
            }

            initializeAccountingDataForTenant($tenant->id);

            if ($plano) {
                $tenant->subscriptions()->create([
                    'plan_id' => $plano->id,
                    'status' => 'active',
                    // O prazo real é imposto pela LICENÇA (renova por check-in);
                    // a subscrição local só satisfaz o CheckSubscription.
                    'current_period_start' => now(),
                    'current_period_end' => now()->addYears(10),
                    'ends_at' => now()->addYears(10),
                    // NOT NULL sem default — sem isto a criação rebentava no fim.
                    'amount' => $plano->price_monthly ?? 0,
                    'billing_cycle' => 'monthly',
                ]);

                $sync = new TenantModuleSyncService();
                foreach ($plano->modules()->pluck('modules.slug')->toArray() as $slug) {
                    $sync->activateModule($tenant, $slug);
                }
            }

            return $tenant;
        });
    }
}
