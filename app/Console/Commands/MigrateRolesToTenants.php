<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class MigrateRolesToTenants extends Command
{
    protected $signature = 'roles:migrate-to-tenants {--force : Forçar migração}';
    
    protected $description = 'Migra roles globais (tenant_id NULL) para roles específicas por tenant';

    public function handle()
    {
        $this->info('🔄 MIGRAÇÃO DE ROLES PARA TENANT-SPECIFIC');
        $this->info('═══════════════════════════════════════════════');
        $this->newLine();
        
        $force = $this->option('force');
        
        // Buscar roles globais (tenant_id NULL)
        $globalRoles = Role::whereNull('tenant_id')
            ->whereIn('name', ['Super Admin', 'Admin', 'Gestor', 'Utilizador'])
            ->get();
        
        if ($globalRoles->isEmpty()) {
            $this->info('✅ Não há roles globais para migrar. Tudo OK!');
            return 0;
        }
        
        $this->warn("📋 Encontradas {$globalRoles->count()} roles globais:");
        foreach ($globalRoles as $role) {
            $userCount = $role->users()->count();
            $this->line("   - {$role->name} (ID: {$role->id}) com {$userCount} usuário(s)");
        }
        
        $this->newLine();
        
        if (!$force) {
            if (!$this->confirm('Deseja continuar com a migração?')) {
                $this->info('Operação cancelada.');
                return 0;
            }
        }
        
        $this->newLine();
        $this->info('🚀 Iniciando migração...');
        $this->newLine();
        
        $tenants = Tenant::all();
        $this->line("Tenants encontrados: {$tenants->count()}");
        
        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();
        
        $stats = [
            'roles_created' => 0,
            'users_migrated' => 0,
        ];
        
        foreach ($tenants as $tenant) {
            setPermissionsTeamId($tenant->id);
            
            foreach ($globalRoles as $globalRole) {
                // Verificar se já existe role para este tenant
                $tenantRole = Role::where('name', $globalRole->name)
                    ->where('tenant_id', $tenant->id)
                    ->first();
                
                if (!$tenantRole) {
                    // Criar role para o tenant
                    $tenantRole = Role::create([
                        'name' => $globalRole->name,
                        'guard_name' => 'web',
                        'tenant_id' => $tenant->id,
                    ]);
                    
                    // Copiar permissões
                    $permissions = $globalRole->permissions;
                    $tenantRole->syncPermissions($permissions);
                    
                    $stats['roles_created']++;
                }
                
                // Migrar usuários deste tenant que têm a role global
                $usersWithGlobalRole = $globalRole->users()
                    ->where('tenant_id', $tenant->id)
                    ->get();
                
                foreach ($usersWithGlobalRole as $user) {
                    setPermissionsTeamId($tenant->id);
                    
                    // Remover role global
                    $user->removeRole($globalRole);
                    
                    // Adicionar role do tenant
                    if (!$user->hasRole($tenantRole)) {
                        $user->assignRole($tenantRole);
                        $stats['users_migrated']++;
                    }
                }
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Verificar se ainda há usuários com roles globais
        $remainingUsers = 0;
        foreach ($globalRoles as $globalRole) {
            $remaining = $globalRole->users()->count();
            if ($remaining > 0) {
                $this->warn("⚠️  Role global '{$globalRole->name}' ainda tem {$remaining} usuário(s)");
                $remainingUsers += $remaining;
            }
        }
        
        $this->newLine();
        $this->info('═══════════════════════════════════════════════');
        $this->info('📊 ESTATÍSTICAS');
        $this->info('═══════════════════════════════════════════════');
        $this->table(
            ['Métrica', 'Quantidade'],
            [
                ['Roles criadas (por tenant)', $stats['roles_created']],
                ['Usuários migrados', $stats['users_migrated']],
                ['Usuários restantes em roles globais', $remainingUsers],
            ]
        );
        
        if ($remainingUsers == 0) {
            $this->newLine();
            $this->info('✅ Migração concluída com sucesso!');
            $this->newLine();
            $this->warn('📝 PRÓXIMO PASSO: Remover roles globais antigas:');
            $this->line('   php artisan roles:cleanup-global');
        } else {
            $this->newLine();
            $this->error('⚠️  Ainda há usuários em roles globais. Verifique manualmente.');
        }
        
        return 0;
    }
}
