<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class FixSpecificUser extends Command
{
    protected $signature = 'user:fix {email}';
    
    protected $description = 'Corrige role de um usuário específico';

    public function handle()
    {
        $email = $this->argument('email');
        
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("❌ Usuário com email '{$email}' não encontrado!");
            return 1;
        }
        
        $this->info("👤 INFORMAÇÕES DO USUÁRIO:");
        $this->line("   ID: {$user->id}");
        $this->line("   Nome: {$user->name}");
        $this->line("   Email: {$user->email}");
        $this->line("   Tenant ID: {$user->tenant_id}");
        $this->line("   is_super_admin: " . ($user->is_super_admin ? 'TRUE ✅' : 'FALSE'));
        
        $this->newLine();
        
        // Mostrar roles atuais
        $currentRoles = $user->getRoleNames();
        $this->info("📋 ROLES ATUAIS:");
        if ($currentRoles->isEmpty()) {
            $this->warn("   Nenhuma role atribuída");
        } else {
            foreach ($currentRoles as $role) {
                $this->line("   - {$role}");
            }
        }
        
        $this->newLine();
        
        // Se não tem tenant, não pode ter roles
        if (!$user->tenant_id) {
            $this->error("❌ Usuário não tem tenant_id. Não é possível atribuir roles.");
            return 1;
        }
        
        setPermissionsTeamId($user->tenant_id);
        
        // Remover todas as roles antigas
        $this->info("🔄 Removendo roles antigas...");
        $user->roles()->detach();
        
        // Determinar a role correta
        $targetRole = $user->is_super_admin ? 'Super Admin' : 'Admin';
        
        // Buscar a role para este tenant
        $role = Role::where('name', $targetRole)
            ->where('tenant_id', $user->tenant_id)
            ->first();
        
        if (!$role) {
            $this->warn("⚠️  Role '{$targetRole}' não existe para o tenant {$user->tenant_id}. Tentando criar...");
            
            try {
                $role = Role::create([
                    'name' => $targetRole,
                    'guard_name' => 'web',
                    'tenant_id' => $user->tenant_id,
                ]);
                
                // Sincronizar permissões
                $allPermissions = \Spatie\Permission\Models\Permission::all();
                
                if ($targetRole === 'Super Admin') {
                    $role->syncPermissions($allPermissions);
                } else {
                    $permissions = $allPermissions->filter(fn($p) => !str_contains($p->name, 'system.'))->pluck('name');
                    $role->syncPermissions($permissions);
                }
                
                $this->info("   ✅ Role '{$targetRole}' criada com permissões");
                
            } catch (\Exception $e) {
                // Role pode existir mas sem tenant_id, buscar novamente
                $role = Role::where('name', $targetRole)->first();
                
                if ($role) {
                    $this->warn("   ⚠️  Role encontrada mas sem tenant_id correto. Usando role existente ID: {$role->id}");
                } else {
                    $this->error("   ❌ Erro ao criar role: " . $e->getMessage());
                    return 1;
                }
            }
        } else {
            $this->info("   ✓ Role '{$targetRole}' encontrada (ID: {$role->id})");
        }
        
        // Atribuir a role
        $user->assignRole($role);
        
        $this->newLine();
        $this->info("✅ ROLE CORRIGIDA COM SUCESSO!");
        $this->line("   Nova role: {$targetRole}");
        
        // Verificar
        $newRoles = $user->fresh()->getRoleNames();
        $this->newLine();
        $this->info("📋 ROLES FINAIS:");
        foreach ($newRoles as $role) {
            $this->line("   ✓ {$role}");
        }
        
        return 0;
    }
}
