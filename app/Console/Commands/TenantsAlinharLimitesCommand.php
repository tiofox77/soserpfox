<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Põe a ficha de cada empresa a dizer, no mínimo, o que o plano dá.
 *
 * O campo "Máx. Utilizadores" da ficha nunca era verificado, portanto ninguém
 * reparou que se ia afastando do plano. Medido antes desta correcção, três das
 * seis empresas tinham a ficha ABAIXO do que pagam — uma dizia 3, com plano
 * Business de 50, e já lá trabalhavam 5 pessoas.
 *
 * O limite passou a ser imposto e é o maior dos dois que vale, portanto
 * ninguém ficou bloqueado. Mas a ficha continua a mostrar um número que não é
 * o que vale, e isso lê-se mal. Isto alinha-a.
 *
 * SÓ SOBE, NUNCA DESCE. A ficha serve para conceder mais do que o plano dá; um
 * limite maior concedido à parte não pode ser cortado por uma sincronização.
 */
class TenantsAlinharLimitesCommand extends Command
{
    protected $signature = 'tenants:alinhar-limites {--dry-run : Mostra o que faria, sem escrever}';

    protected $description = 'Alinha o limite de utilizadores e de armazenamento da ficha com o plano de cada empresa';

    public function handle(): int
    {
        $seco  = (bool) $this->option('dry-run');
        $todas = Tenant::with('activeSubscription.plan')->get();

        $this->line('Empresas: ' . $todas->count() . ($seco ? '   (simulação)' : ''));
        $this->newLine();

        $alteradas = 0;

        foreach ($todas as $empresa) {
            $plano = $empresa->activeSubscription?->plan;

            if (!$plano) {
                $this->line(sprintf('  %-28s sem plano activo — ficha fica como está', mb_substr($empresa->name, 0, 27)));

                continue;
            }

            $novoUsers   = max((int) $empresa->max_users, (int) $plano->max_users);
            $novoStorage = max((int) $empresa->max_storage_mb, (int) $plano->max_storage_mb);

            if ($novoUsers === (int) $empresa->max_users && $novoStorage === (int) $empresa->max_storage_mb) {
                $this->line(sprintf('  %-28s já alinhada', mb_substr($empresa->name, 0, 27)));

                continue;
            }

            $this->line(sprintf(
                '  %-28s utilizadores %d → %d   armazenamento %d → %d MB   (plano %s)',
                mb_substr($empresa->name, 0, 27),
                $empresa->max_users,
                $novoUsers,
                $empresa->max_storage_mb,
                $novoStorage,
                $plano->name
            ));

            if (!$seco) {
                $empresa->update([
                    'max_users'      => $novoUsers,
                    'max_storage_mb' => $novoStorage,
                ]);
            }

            $alteradas++;
        }

        $this->newLine();
        $this->info($seco
            ? "Seriam alinhadas {$alteradas} empresa(s)."
            : "✓ {$alteradas} empresa(s) alinhada(s).");

        return self::SUCCESS;
    }
}
