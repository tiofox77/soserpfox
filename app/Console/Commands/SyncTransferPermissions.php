<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * A PERMISSÃO DE ANULAR UMA TRANSFERÊNCIA.
 *
 * Havia `treasury.transfers.view` e `.create` e mais nada — e o ecrã deixava
 * qualquer pessoa com o módulo activo anular uma transferência, devolvendo o
 * dinheiro à origem. Não é a mesma coisa que registar: anular desfaz dinheiro
 * já movido, e merece nome próprio.
 *
 * A QUEM SE DÁ: a quem já podia CRIAR transferências. Não aparece poder novo
 * a ninguém — o que aparece é o nome do poder que essas pessoas já exerciam
 * sem que nada o registasse. Quem quiser tirá-lo a alguém tira-o agora no
 * modal de papéis, que é onde isto se decide.
 *
 * Como o `permissions:sync-quotes`: inserção em massa no pivô
 * `role_has_permissions`, idempotente, e por isso repetível à vontade. Um
 * laço papel-a-papel com muitas empresas não acaba dentro do tempo da rota
 * de manutenção.
 */
class SyncTransferPermissions extends Command
{
    protected $signature = 'permissions:sync-transferencias';

    protected $description = 'Cria treasury.transfers.delete e dá-a a quem já pode criar transferências';

    public function handle(): int
    {
        $criar = Permission::firstOrCreate(
            ['name' => 'treasury.transfers.create', 'guard_name' => 'web'],
            ['description' => 'Criar Transferências']
        );

        $anular = Permission::firstOrCreate(
            ['name' => 'treasury.transfers.delete', 'guard_name' => 'web'],
            ['description' => 'Anular Transferências']
        );

        $this->line(" ✓ treasury.transfers.delete (#{$anular->id})");

        /*
         * OS PAPÉIS que já podem criar. É desta lista que sai a de anular —
         * lida do pivô e escrita no pivô, numa consulta cada.
         */
        $papeis = DB::table('role_has_permissions')
            ->where('permission_id', $criar->id)
            ->pluck('role_id');

        if ($papeis->isEmpty()) {
            $this->warn('Nenhum papel tem treasury.transfers.create — nada a atribuir.');

            return self::SUCCESS;
        }

        $linhas = $papeis->map(fn ($id) => ['permission_id' => $anular->id, 'role_id' => $id])->all();

        DB::table('role_has_permissions')->insertOrIgnore($linhas);

        $this->info("Atribuída a {$papeis->count()} papel(éis).");

        // O CACHE DO SPATIE guarda o mapa papel↔permissão. Sem o limpar, a
        // permissão existe na base e ninguém a tem até o processo reiniciar.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return self::SUCCESS;
    }
}
