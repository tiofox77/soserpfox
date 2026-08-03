<?php

namespace App\Console\Commands\AGT;

use App\Models\Tenant;
use App\Services\AGT\AGTKeyStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Move as chaves RSA do caminho legado (sem ambiente) para a pasta do ambiente
 * a que pertencem.
 *
 *   agt/tenants/{id}/{public,private}_key.pem
 *        ↓
 *   agt/tenants/{id}/sandbox/{public,private}_key.pem
 *
 * Assume-se homologação: até hoje só existiam séries registadas em sandbox, e
 * é o par de homologação que o Portal do Contribuinte entrega primeiro. Quem
 * já tiver o par de produção deve colá-lo no ecrã com o ambiente em Produção.
 *
 * Copia (não move) por omissão, para o caminho legado continuar a funcionar
 * como recurso até se confirmar que está tudo bem. Use --remover-legado depois.
 *
 *   php artisan agt:migrate-keys --dry-run
 *   php artisan agt:migrate-keys --ambiente=sandbox
 */
class MigrateKeysToEnvironment extends Command
{
    protected $signature = 'agt:migrate-keys
                            {--tenant= : Limitar a uma empresa}
                            {--ambiente=sandbox : Ambiente a que as chaves legadas pertencem}
                            {--remover-legado : Apagar o par legado depois de copiar}
                            {--dry-run : Apenas mostra o que seria feito}';

    protected $description = 'Move as chaves RSA legadas para a pasta do ambiente (sandbox/production)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $ambiente = $this->option('ambiente');

        if (!in_array($ambiente, AGTKeyStore::AMBIENTES, true)) {
            $this->error("Ambiente inválido: {$ambiente}. Use sandbox ou production.");
            return self::FAILURE;
        }

        $this->info('=== Migração de chaves AGT ' . ($dry ? '(dry-run)' : '(REAL)') . ' ===');
        $this->line("  Destino: <fg=cyan>{$ambiente}</>");
        $this->newLine();

        $query = Tenant::query()->orderBy('id');
        if ($id = $this->option('tenant')) {
            $query->where('id', $id);
        }

        $disk = Storage::disk('local');
        $movidas = 0;

        foreach ($query->get() as $tenant) {
            $legado  = AGTKeyStore::legacyDirectory($tenant->id);
            $destino = AGTKeyStore::directory($tenant->id, $ambiente);

            $temLegado = $disk->exists("{$legado}/private_key.pem")
                || $disk->exists("{$legado}/public_key.pem");

            if (!$temLegado) {
                continue;
            }

            if ($disk->exists("{$destino}/private_key.pem")) {
                $this->line("  <fg=yellow>ignorado</> #{$tenant->id} {$tenant->name}: "
                    . "já existe par em {$ambiente}");
                continue;
            }

            $ficheiros = [];
            foreach (['public_key.pem', 'private_key.pem'] as $f) {
                if ($disk->exists("{$legado}/{$f}")) {
                    $ficheiros[] = $f;
                }
            }

            $this->line("  #{$tenant->id} {$tenant->name}: "
                . implode(' + ', $ficheiros) . " → <fg=green>{$ambiente}/</>"
                . (count($ficheiros) < 2 ? ' <fg=yellow>(par incompleto)</>' : ''));

            if (!$dry) {
                foreach ($ficheiros as $f) {
                    $disk->put("{$destino}/{$f}", $disk->get("{$legado}/{$f}"));
                }

                if ($this->option('remover-legado')) {
                    foreach ($ficheiros as $f) {
                        $disk->delete("{$legado}/{$f}");
                    }
                }
            }

            $movidas++;
        }

        $this->newLine();
        if ($movidas === 0) {
            $this->info('✓ Nenhuma chave legada por migrar.');
        } elseif ($dry) {
            $this->info("(dry-run) {$movidas} empresa(s) seriam migradas.");
        } else {
            $this->info("   Empresas migradas: {$movidas}");
            if (!$this->option('remover-legado')) {
                $this->warn('   O par legado foi mantido como recurso. '
                    . 'Confirme o funcionamento e volte a correr com --remover-legado.');
            }
        }

        return self::SUCCESS;
    }
}
