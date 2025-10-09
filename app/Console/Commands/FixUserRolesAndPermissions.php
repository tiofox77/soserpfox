<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class FixUserRolesAndPermissions extends Command
{
    protected $signature = 'users:fix-roles-permissions {--dry-run : Executar sem fazer alterações}';
    
    protected $description = 'Corrige roles e permissões de usuários que estão incorretas';
    
    protected $stats = [
        'users_checked' => 0,
        'users_fixed' => 0,
        'roles_created' => 0,
        'permissions_synced' => 0,
        'errors' => 0,
    ];

    public function handle()
    {
        $this->info('🔧 CORREÇÃO DE ROLES E PERMISSÕES DE USUÁRIOS');
        $this->info('============================================');
        $this->newLine();
        
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('⚠️  MODO DRY-RUN: Nenhuma alteração será feita!');
            $this->newLine();
        }
        
        // 1. Garantir que roles padrão existem para cada tenant
        $this->info('📋 Passo 1: Verificando roles padrão dos tenants...');
        $this->ensureDefaultRolesForAllTenants($isDryRun);
        $this->newLine();
        
        // 2. Corrigir usuários sem roles
        $this->info('👥 Passo 2: Corrigindo usuários sem roles...');
        $this->fixUsersWithoutRoles($isDryRun);
        $this->newLine();
        
        // 3. Corrigir roles sem permissões
        $this->info('🔐 Passo 3: Corrigindo roles sem permissões...');
        $this->fixRolesWithoutPermissions($isDryRun);
        $this->newLine();
        
        // 4. Limpar roles antigas/duplicadas
        $this->info('🗑️  Passo 4: Limpando roles antigas...');
        $this->cleanupOldRoles($isDryRun);
        $this->newLine();
        
        // 5. Mostrar estatísticas
        $this->showStatistics();
        
        if ($isDryRun) {
            $this->newLine();
            $this->info('💡 Para aplicar as correções, execute sem --dry-run:');
            $this->line('   php artisan users:fix-roles-permissions');
        } else {
            $this->newLine();
            $this->info('✅ Correções aplicadas com sucesso!');
        }
        
        return 0;
    }
    
    protected function ensureDefaultRolesForAllTenants($isDryRun)
    {
        $tenants = Tenant::all();
        $allPermissions = Permission::all();
        
        $this->line("   Tenants encontrados: {$tenants->count()}");
        
        $roleStructure = [
            'Super Admin' => $allPermissions->pluck('name')->toArray(),
            'Admin' => $allPermissions->filter(fn($p) => !str_contains($p->name, 'system.'))->pluck('name')->toArray(),
            'Gestor' => $allPermissions->filter(fn($p) => str_contains($p->name, '.view') || str_contains($p->name, '.create') || str_contains($p->name, '.edit'))->pluck('name')->toArray(),
            'Utilizador' => $allPermissions->filter(fn($p) => str_contains($p->name, '.view'))->pluck('name')->toArray(),
        ];
        
        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();
        
        foreach ($tenants as $tenant) {
            setPermissionsTeamId($tenant->id);
            
            foreach ($roleStructure as $roleName => $permissionNames) {
                $role = Role::where('name', $roleName)
                    ->where('tenant_id', $tenant->id)
                    ->first();
                
                if (!$role) {
                    if (!$isDryRun) {
                        // Verificar se já existe uma role com o mesmo nome (sem tenant_id ou outro tenant)
                        $existingRole = Role::where('name', $roleName)
                            ->where('guard_name', 'web')
                            ->first();
                        
                        if ($existingRole && $existingRole->tenant_id != $tenant->id) {
                            // Role existe mas para outro tenant, criar nova
                            try {
                                $role = Role::create([
                                    'name' => $roleName,
                                    'guard_name' => 'web',
                                    'tenant_id' => $tenant->id,
                                ]);
                                $this->stats['roles_created']++;
                            } catch (\Exception $e) {
                                // Se falhar, pular este tenant
                                continue;
                            }
                        } elseif (!$existingRole) {
                            // Não existe, criar
                            $role = Role::create([
                                'name' => $roleName,
                                'guard_name' => 'web',
                                'tenant_id' => $tenant->id,
                            ]);
                            $this->stats['roles_created']++;
                        } else {
                            // Já existe para este tenant, usar ela
                            $role = $existingRole;
                        }
                    }
                }
                
                // Sincronizar permissões se a role existe
                if ($role && (!$role->permissions()->count() || $isDryRun)) {
                    if (!$isDryRun) {
                        $permissions = Permission::whereIn('name', $permissionNames)->get();
                        $role->syncPermissions($permissions);
                        $this->stats['permissions_synced']++;
                    }
                }
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->line("   ✅ Roles padrão verificadas para todos os tenants");
    }
    
    protected function fixUsersWithoutRoles($isDryRun)
    {
        $users = User::whereNotNull('tenant_id')->get();
        
        $this->line("   Usuários encontrados: {$users->count()}");
        
        // Proteger usuário ID 1
        $protectedUser = User::find(1);
        if ($protectedUser) {
            $this->warn("   🛡️  Usuário ID 1 ({$protectedUser->email}) está PROTEGIDO");
        }
        
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();
        
        foreach ($users as $user) {
            $this->stats['users_checked']++;
            
            // PROTEÇÃO: Pular usuário ID 1
            if ($user->id == 1) {
                $bar->advance();
                continue;
            }
            
            // Pular super admin do sistema
            if ($user->is_super_admin) {
                $bar->advance();
                continue;
            }
            
            setPermissionsTeamId($user->tenant_id);
            
            $roles = $user->getRoleNames();
            
            if ($roles->isEmpty()) {
                // Usuário sem role - verificar se é dono do tenant
                $isOwner = DB::table('tenant_user')
                    ->where('tenant_id', $user->tenant_id)
                    ->where('user_id', $user->id)
                    ->whereNotNull('joined_at')
                    ->orderBy('joined_at')
                    ->limit(1)
                    ->exists();
                
                // Verificar se é o primeiro usuário do tenant
                $firstUser = DB::table('tenant_user')
                    ->where('tenant_id', $user->tenant_id)
                    ->orderBy('joined_at')
                    ->first();
                
                $isFirstUser = $firstUser && $firstUser->user_id == $user->id;
                
                $targetRole = ($isOwner || $isFirstUser) ? 'Super Admin' : 'Utilizador';
                
                if (!$isDryRun) {
                    $role = Role::where('name', $targetRole)
                        ->where('tenant_id', $user->tenant_id)
                        ->first();
                    
                    if ($role) {
                        $user->assignRole($role);
                        $this->stats['users_fixed']++;
                    }
                }
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->line("   ✅ Usuários sem roles corrigidos");
        
        if ($protectedUser) {
            $this->newLine();
            $this->info("   🛡️  USUÁRIO PROTEGIDO:");
            $this->line("      ID: {$protectedUser->id}");
            $this->line("      Email: {$protectedUser->email}");
            $this->line("      is_super_admin: " . ($protectedUser->is_super_admin ? 'TRUE ✅' : 'FALSE'));
        }
    }
    
    protected function fixRolesWithoutPermissions($isDryRun)
    {
        $allPermissions = Permission::all();
        $roleStructure = [
            'Super Admin' => $allPermissions->pluck('name')->toArray(),
            'Admin' => $allPermissions->filter(fn($p) => !str_contains($p->name, 'system.'))->pluck('name')->toArray(),
            'Gestor' => $allPermissions->filter(fn($p) => str_contains($p->name, '.view') || str_contains($p->name, '.create') || str_contains($p->name, '.edit'))->pluck('name')->toArray(),
            'Utilizador' => $allPermissions->filter(fn($p) => str_contains($p->name, '.view'))->pluck('name')->toArray(),
        ];
        
        foreach ($roleStructure as $roleName => $permissionNames) {
            $roles = Role::where('name', $roleName)->get();
            
            foreach ($roles as $role) {
                if ($role->permissions()->count() == 0) {
                    if (!$isDryRun) {
                        $permissions = Permission::whereIn('name', $permissionNames)->get();
                        $role->syncPermissions($permissions);
                        $this->stats['permissions_synced']++;
                    }
                }
            }
        }
        
        $this->line("   ✅ Permissões sincronizadas");
    }
    
    protected function cleanupOldRoles($isDryRun)
    {
        $standardRoles = ['Super Admin', 'Admin', 'Gestor', 'Utilizador'];
        
        // Roles antigas com nomes diferentes
        $oldRoles = Role::whereNotIn('name', $standardRoles)->get();
        
        $this->line("   Roles antigas encontradas: {$oldRoles->count()}");
        
        foreach ($oldRoles as $oldRole) {
            $userCount = $oldRole->users()->count();
            
            if ($userCount == 0) {
                if (!$isDryRun) {
                    $oldRole->delete();
                }
                $this->line("   🗑️  Removida role '{$oldRole->name}' (sem usuários)");
            } else {
                $this->warn("   ⚠️  Role '{$oldRole->name}' tem {$userCount} usuário(s) - não removida");
            }
        }
    }
    
    protected function showStatistics()
    {
        $this->info('═══════════════════════════════════════════════');
        $this->info('📊 ESTATÍSTICAS');
        $this->info('═══════════════════════════════════════════════');
        $this->newLine();
        
        $this->table(
            ['Métrica', 'Quantidade'],
            [
                ['Usuários verificados', $this->stats['users_checked']],
                ['Usuários corrigidos', $this->stats['users_fixed']],
                ['Roles criadas', $this->stats['roles_created']],
                ['Permissões sincronizadas', $this->stats['permissions_synced']],
                ['Erros', $this->stats['errors']],
            ]
        );
    }
}
