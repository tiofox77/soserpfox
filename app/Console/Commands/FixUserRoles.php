<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Tenant;

class FixUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:fix-roles {email? : Email do usuário}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar e corrigir roles de usuários';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        if ($email) {
            $this->fixUserRole($email);
        } else {
            $this->info('Listando todos os usuários com problemas de role...');
            $users = User::all();
            
            foreach ($users as $user) {
                $this->checkUser($user);
            }
        }
        
        return 0;
    }
    
    protected function fixUserRole($email)
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("❌ Usuário não encontrado: {$email}");
            return;
        }
        
        $this->info("🔍 Verificando usuário: {$user->name} ({$user->email})");
        $this->line("   ID: {$user->id}");
        $this->line("   Tenant ID: {$user->tenant_id}");
        $this->line("   Is Super Admin (Sistema): " . ($user->is_super_admin ? 'SIM' : 'NÃO'));
        
        if (!$user->tenant_id) {
            $this->warn("   ⚠️ Usuário sem tenant_id definido!");
            
            // Buscar tenant do usuário
            $tenantRelation = $user->tenants()->first();
            if ($tenantRelation) {
                $user->tenant_id = $tenantRelation->id;
                $user->save();
                $this->info("   ✅ Tenant ID definido: {$tenantRelation->id}");
            } else {
                $this->error("   ❌ Usuário não está vinculado a nenhum tenant!");
                return;
            }
        }
        
        $tenant = Tenant::find($user->tenant_id);
        if (!$tenant) {
            $this->error("   ❌ Tenant não encontrado!");
            return;
        }
        
        $this->line("   Empresa: {$tenant->name}");
        
        // Verificar roles atuais
        setPermissionsTeamId($tenant->id);
        $roles = $user->getRoleNames();
        
        $this->newLine();
        $this->info("   📋 Roles atuais:");
        if ($roles->isEmpty()) {
            $this->warn("      ⚠️ SEM ROLES!");
        } else {
            foreach ($roles as $role) {
                $this->line("      - {$role}");
            }
        }
        
        // Verificar se deve ter role super-admin
        $isOwner = $user->tenants()->wherePivot('joined_at', '!=', null)->first();
        
        if ($isOwner && $roles->isEmpty()) {
            $this->warn("   ⚠️ Usuário é dono do tenant mas não tem role!");
            
            if ($this->confirm('Atribuir role "Super Admin" do tenant?', true)) {
                $superAdminRole = \Spatie\Permission\Models\Role::firstOrCreate(
                    ['name' => 'Super Admin', 'guard_name' => 'web'],
                    ['tenant_id' => $tenant->id]
                );
                
                $user->assignRole($superAdminRole);
                $this->info("   ✅ Role 'Super Admin' atribuída!");
                
                // Verificar novamente
                $roles = $user->getRoleNames();
                $this->info("   📋 Roles após correção:");
                foreach ($roles as $role) {
                    $this->line("      - {$role}");
                }
            }
        } elseif (!$roles->isEmpty()) {
            $this->info("   ✅ Usuário já tem roles configuradas!");
        }
        
        // Mostrar permissões
        $permissions = $user->getAllPermissions();
        $this->newLine();
        $this->info("   🔐 Permissões totais: " . $permissions->count());
        
        if ($this->option('verbose')) {
            foreach ($permissions->take(10) as $permission) {
                $this->line("      - {$permission->name}");
            }
            if ($permissions->count() > 10) {
                $this->line("      ... e mais " . ($permissions->count() - 10) . " permissões");
            }
        }
    }
    
    protected function checkUser($user)
    {
        if (!$user->tenant_id) {
            $this->warn("⚠️ {$user->email} - Sem tenant_id");
            return;
        }
        
        setPermissionsTeamId($user->tenant_id);
        $roles = $user->getRoleNames();
        
        if ($roles->isEmpty()) {
            $this->error("❌ {$user->email} - Sem roles");
        } else {
            $this->info("✅ {$user->email} - Roles: " . $roles->implode(', '));
        }
    }
}
