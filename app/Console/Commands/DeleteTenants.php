<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class DeleteTenants extends Command
{
    protected $signature = 'tenants:delete {--slugs=} {--keep=} {--force}';
    protected $description = 'Apaga tenants. Use --slugs=a,b,c para apagar específicos OU --keep=x,y,z para apagar todos exceto esses. --force aplica delete (default: dry-run).';

    public function handle()
    {
        $slugs = $this->option('slugs');
        $keep = $this->option('keep');
        $force = $this->option('force');

        if (!$slugs && !$keep) {
            $this->error('Forneça --slugs=a,b,c OU --keep=x,y,z');
            return Command::FAILURE;
        }

        if ($slugs) {
            $list = array_map('trim', explode(',', $slugs));
            $tenants = Tenant::whereIn('slug', $list)->get();
        } else {
            $keepList = array_map('trim', explode(',', $keep));
            $tenants = Tenant::whereNotIn('slug', $keepList)->get();
            $this->info("Mantendo: " . implode(', ', $keepList));
        }

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado para apagar.');
            return Command::SUCCESS;
        }

        $this->info("Tenants a apagar ({$tenants->count()}):");
        foreach ($tenants as $t) {
            $this->line("  - #{$t->id} {$t->name} <{$t->slug}>");
        }

        if (!$force) {
            $this->warn("\n[DRY-RUN] Adicione --force para executar a remoção.");
            return Command::SUCCESS;
        }

        $deleted = 0;
        foreach ($tenants as $t) {
            try {
                $t->delete();
                $this->info("  ✓ Apagado #{$t->id} {$t->slug}");
                $deleted++;
            } catch (\Exception $e) {
                $this->error("  ✗ #{$t->id} {$t->slug} — {$e->getMessage()}");
            }
        }

        $this->info("\n✅ {$deleted}/{$tenants->count()} tenants apagados.");
        return Command::SUCCESS;
    }
}
