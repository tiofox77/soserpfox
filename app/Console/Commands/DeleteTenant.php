<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;

class DeleteTenant extends Command
{
    protected $signature = 'tenant:delete {id} {--force : Força a exclusão permanente}';
    
    protected $description = 'Deleta um tenant e todos os dados associados';

    public function handle()
    {
        $tenantId = $this->argument('id');
        $force = $this->option('force');
        
        $tenant = Tenant::withTrashed()->find($tenantId);
        
        if (!$tenant) {
            $this->error("❌ Tenant #{$tenantId} não encontrado!");
            return 1;
        }
        
        $this->newLine();
        $this->info("🏢 INFORMAÇÕES DO TENANT:");
        $this->line("   ID: {$tenant->id}");
        $this->line("   Nome: {$tenant->name}");
        $this->line("   Slug: {$tenant->slug}");
        $this->line("   Email: {$tenant->email}");
        $this->line("   Status: " . ($tenant->is_active ? 'Ativo ✅' : 'Inativo ❌'));
        
        if ($tenant->trashed()) {
            $this->warn("   ⚠️  Tenant já foi deletado (soft delete)");
            $this->line("   Deletado em: {$tenant->deleted_at}");
        }
        
        $this->newLine();
        
        // Mostrar estatísticas
        $usersCount = $tenant->users()->count();
        $rolesCount = $tenant->roles()->count();
        $subscriptionsCount = $tenant->subscriptions()->count();
        $invoicesCount = $tenant->invoices()->count();
        
        $this->warn("📊 DADOS QUE SERÃO DELETADOS:");
        $this->table(
            ['Tipo', 'Quantidade'],
            [
                ['Usuários', $usersCount],
                ['Roles', $rolesCount],
                ['Subscriptions', $subscriptionsCount],
                ['Invoices', $invoicesCount],
            ]
        );
        
        $this->newLine();
        $this->error("⚠️  ATENÇÃO: Esta ação é IRREVERSÍVEL!");
        $this->error("⚠️  Todos os dados associados serão PERMANENTEMENTE deletados!");
        
        $this->newLine();
        
        if (!$this->confirm("Tem certeza que deseja deletar o tenant '{$tenant->name}'?", false)) {
            $this->info("Operação cancelada.");
            return 0;
        }
        
        $this->newLine();
        
        if ($force) {
            $this->warn("🗑️  Executando exclusão PERMANENTE (force delete)...");
        } else {
            $this->info("🗑️  Executando exclusão (soft delete)...");
        }
        
        $this->newLine();
        
        try {
            if ($force) {
                // Force delete (permanente)
                $tenant->forceDelete();
            } else {
                // Soft delete
                $tenant->delete();
            }
            
            $this->newLine();
            $this->info("✅ TENANT DELETADO COM SUCESSO!");
            $this->newLine();
            $this->line("📋 Verifique os logs para detalhes da exclusão em cascata:");
            $this->line("   storage/logs/laravel.log");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->newLine();
            $this->error("❌ ERRO AO DELETAR TENANT:");
            $this->error("   {$e->getMessage()}");
            $this->newLine();
            $this->line("Verifique os logs para mais detalhes:");
            $this->line("   storage/logs/laravel.log");
            
            return 1;
        }
    }
}
