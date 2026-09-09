<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * As permissões de APAGAR que faltavam ao hotel.
 *
 * O pessoal, os pacotes, as tarifas e as ordens de manutenção tinham `view`,
 * `create` e `edit` — e nunca um `delete`. Os ecrãs em Livewire apagavam na
 * mesma, sem perguntar a ninguém; o ecrã genérico dos catálogos exige a
 * permissão por verbo, e uma que não existe na base é um 403 para toda a
 * gente: o middleware do Spatie recusa o que não conhece.
 *
 * Como os outros `permissions:sync-*`, o trabalho é uma inserção em massa no
 * pivot e não um `givePermissionTo` por papel — com muitas empresas, o laço
 * papel a papel não acaba dentro do tempo da rota de manutenção. É idempotente.
 */
class SyncHotelPermissions extends Command
{
    protected $signature = 'permissions:sync-hotel {--aplicar : escreve de facto}';

    protected $description = 'Cria as permissões de apagar do hotel e reparte-as pelos papéis (a seco por omissão)';

    private const NOVAS = [
        'hotel.staff.delete' => 'Apagar Pessoal do Hotel',
        'hotel.packages.delete' => 'Apagar Pacotes',
        'hotel.rates.delete' => 'Apagar Tarifas',
        'hotel.maintenance.delete' => 'Apagar Ordens de Manutenção',
    ];

    /** Estes gerem o hotel: ficam com as de apagar. */
    private const GESTAO = [
        'Super Admin', 'Admin', 'Administrador', 'Gestor', 'Gerente', 'Director', 'Diretor',
    ];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');

        if (! $aplicar) {
            $this->comment('A SECO — nada é escrito. Use --aplicar.');
        }

        $ids = [];

        foreach (self::NOVAS as $nome => $descricao) {
            if (! $aplicar) {
                $this->line("  {$nome}: " . (Permission::where('name', $nome)->exists() ? 'já existe' : 'seria criada'));

                continue;
            }

            $ids[$nome] = Permission::firstOrCreate(
                ['name' => $nome, 'guard_name' => 'web'],
                ['description' => $descricao]
            )->id;

            $this->line("  ✓ {$nome}");
        }

        if (! $aplicar) {
            $this->line('');
            $this->line('  Papéis que ficariam com elas: ' . implode(', ', self::GESTAO));

            return self::SUCCESS;
        }

        $linhas = [];

        foreach (self::GESTAO as $nome) {
            foreach (Role::where('name', $nome)->pluck('id') as $papel) {
                foreach ($ids as $permissao) {
                    $linhas[] = ['permission_id' => $permissao, 'role_id' => $papel];
                }
            }
        }

        $linhas = array_merge($linhas, $this->alinharOCheckOut());

        $novas = 0;

        foreach (array_chunk($linhas, 1000) as $bloco) {
            $novas += DB::table('role_has_permissions')->insertOrIgnore($bloco);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info(count($linhas) . ' ligação(ões) processada(s), ' . $novas . ' nova(s).');

        return self::SUCCESS;
    }

    /**
     * QUEM PODIA FECHAR UMA ESTADA CONTINUA A PODER.
     *
     * A morada do check-out era guardada por `hotel.reservations.edit` e o
     * ecrã não pedia mais nada: chegar lá era poder fechar. Mas a permissão que
     * existe para isto — e que o catálogo de papéis mostra — é
     * `hotel.checkout.manage`, e estava em MENOS papéis do que a outra: passar
     * a exigi-la tirava o check-out a quem sempre o fez.
     *
     * Esta passagem dá `hotel.checkout.manage` a todos os papéis que já tinham
     * `hotel.reservations.edit`. Não alarga nada: põe a permissão certa onde o
     * poder já estava.
     *
     * @return list<array{permission_id: int, role_id: int}>
     */
    private function alinharOCheckOut(): array
    {
        $fechar = Permission::firstOrCreate(
            ['name' => 'hotel.checkout.manage', 'guard_name' => 'web'],
            ['description' => 'Fechar estadas (check-out)']
        );

        $editar = Permission::where('name', 'hotel.reservations.edit')->first();

        if (! $editar) {
            return [];
        }

        return DB::table('role_has_permissions')
            ->where('permission_id', $editar->id)
            ->pluck('role_id')
            ->map(fn ($papel) => ['permission_id' => $fechar->id, 'role_id' => $papel])
            ->all();
    }
}
