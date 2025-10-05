<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FixUserTenantRelationships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:user-tenant-relationships';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige vinculação de usuários aos tenants na pivot table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Corrigindo vinculações de usuários aos tenants...');
        
        $users = User::whereNotNull('tenant_id')->get();
        $fixed = 0;
        $skipped = 0;
        
        foreach ($users as $user) {
            // Verificar se já tem vinculação ao tenant
            if (!$user->tenants()->where('tenants.id', $user->tenant_id)->exists()) {
                // Vincular ao tenant
                $user->tenants()->attach($user->tenant_id, [
                    'is_active' => true,
                    'joined_at' => $user->created_at ?? now(),
                ]);
                
                $this->info("✓ Usuário {$user->name} ({$user->email}) vinculado ao tenant ID {$user->tenant_id}");
                $fixed++;
            } else {
                $skipped++;
            }
        }
        
        $this->newLine();
        $this->info("Concluído!");
        $this->info("Usuários corrigidos: {$fixed}");
        $this->info("Usuários já vinculados: {$skipped}");
        
        return Command::SUCCESS;
    }
}
