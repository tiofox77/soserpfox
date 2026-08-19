<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Cria a administradora Claudina Caquienga no tenant 57.
 *
 * Idempotente pelo email: correr outra vez não duplica. Só grava se o papel
 * Admin do tenant existir — nunca deixa um utilizador sem papel.
 */
class CriarAdminClaudinaSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 57;
        $email    = 'claudina.caquienga@kienga.local';

        setPermissionsTeamId($tenantId);

        $papel = Role::where('name', 'Admin')->where('tenant_id', $tenantId)->first();
        if (!$papel) {
            $this->command?->error("Papel Admin do tenant {$tenantId} não existe. Nada criado.");
            return;
        }

        $novo = !User::where('email', $email)->exists();

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'      => 'Claudina Caquienga',
                'password'  => Hash::make('12345678'),
                'tenant_id' => $tenantId,
                'is_active' => true,
            ]
        );

        // Garantir estado correcto mesmo se o utilizador já existisse.
        $user->tenant_id = $user->tenant_id ?: $tenantId;
        $user->is_active = true;
        $user->save();

        $user->tenants()->syncWithoutDetaching([$tenantId => ['is_active' => true]]);

        if (!$user->hasRole($papel)) {
            $user->assignRole($papel);
        }

        $this->command?->info(
            ($novo ? 'Criada' : 'Actualizada') . " administradora {$user->name} ({$email}) "
            . "no tenant {$tenantId}, papel Admin. " . ($novo ? 'Palavra-passe: 12345678' : '')
        );
    }
}
