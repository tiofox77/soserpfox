<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * As permissões que faltavam ao RESTAURANTE quando ele passou a React.
 *
 * O QUE MUDOU. Os ecrãs em Livewire não verificavam permissão nenhuma por
 * dentro: a rota deixava entrar com `restaurant.orders.view` e, lá dentro,
 * tudo se podia fazer — montar a carta, apagar categorias, mexer nas mesas.
 * Os ecrãs em React perguntam à API por cada operação, e a API exige a
 * permissão que corresponde ao que se está a fazer:
 *
 *   · a CARTA (pratos e categorias) é `restaurant.menu.*`, e não `orders.*`;
 *   · as MESAS são `restaurant.floor.manage`.
 *
 * Estas permissões SEMPRE EXISTIRAM na base — o `PermissionSeeder` cria-as
 * desde o princípio. O que não existia era o hábito de as dar: o
 * `RoleHelper` só entrega `restaurant.menu.view` ao «Gestor Stock
 * Restaurante», e nenhum papel recebe `restaurant.menu.manage`. Sem isto,
 * quem geria a carta ontem abria o ecrã hoje e via um 403 em cada gravação.
 *
 * QUEM TINHA A DA ESQUERDA PASSA A TER A DA DIREITA. Não se inventa autoridade
 * nova: copia-se a que já estava atribuída para a porta por onde ela passa
 * agora.
 *
 * Como os outros `permissions:sync-*`, o trabalho é uma inserção em massa no
 * pivot e não um `givePermissionTo` por papel — com muitas empresas, o laço
 * papel a papel não acaba dentro do tempo da rota de manutenção. É idempotente.
 */
class SyncRestaurantePermissions extends Command
{
    protected $signature = 'permissions:sync-restaurante {--aplicar : escreve de facto}';

    protected $description = 'Dá as permissões da carta e da sala a quem já geria comandas no restaurante (a seco por omissão)';

    /**
     * QUEM TEM A DA ESQUERDA PASSA A TER A DA DIREITA.
     *
     * `orders.view` → ver a carta: quem atende ao balcão precisa de a ler.
     * `orders.edit` → mexer na carta e nas mesas: era o que o ecrã em Livewire
     * deixava fazer a quem lá entrava, e tirá-lo agora seria uma regressão
     * disfarçada de segurança.
     */
    private const EQUIVALENTES = [
        'restaurant.orders.view' => ['restaurant.menu.view'],
        'restaurant.orders.edit' => ['restaurant.menu.manage', 'restaurant.floor.manage'],
    ];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');

        if (! $aplicar) {
            $this->comment('A SECO — nada é escrito. Use --aplicar.');
        }

        $nomes = array_merge(
            array_keys(self::EQUIVALENTES),
            array_merge(...array_values(self::EQUIVALENTES)),
        );

        $porNome = Permission::whereIn('name', $nomes)->pluck('id', 'name');

        $linhas = [];
        $directas = [];
        $emFalta = [];

        foreach (self::EQUIVALENTES as $origem => $destinos) {
            $idOrigem = $porNome[$origem] ?? null;

            if (! $idOrigem) {
                $emFalta[] = $origem;

                continue;
            }

            $papeis = DB::table('role_has_permissions')->where('permission_id', $idOrigem)->pluck('role_id');
            $pessoas = DB::table('model_has_permissions')->where('permission_id', $idOrigem)->get();

            foreach ($destinos as $destino) {
                $idDestino = $porNome[$destino] ?? null;

                if (! $idDestino) {
                    $emFalta[] = $destino;

                    continue;
                }

                foreach ($papeis as $papel) {
                    $linhas[] = ['permission_id' => $idDestino, 'role_id' => $papel];
                }

                /*
                 * E AS PERMISSÕES DADAS DIRECTAMENTE A UMA PESSOA: há contas
                 * com a permissão marcada na ficha e nenhum papel. Deixá-las
                 * de fora era dar-lhes um ecrã que responde 403 a cada pedido.
                 */
                foreach ($pessoas as $l) {
                    $directas[] = [
                        'permission_id' => $idDestino,
                        'model_type' => $l->model_type,
                        'model_id' => $l->model_id,
                        'tenant_id' => $l->tenant_id ?? null,
                    ];
                }
            }
        }

        if ($emFalta) {
            $this->warn('  Sem par na base (nada a copiar): ' . implode(', ', array_unique($emFalta)));
        }

        if (! $aplicar) {
            $this->line('  Ligações a papéis: ' . count($linhas));
            $this->line('  Ligações a pessoas (permissão directa): ' . count($directas));

            return self::SUCCESS;
        }

        $novas = 0;

        foreach (array_chunk($linhas, 1000) as $bloco) {
            $novas += DB::table('role_has_permissions')->insertOrIgnore($bloco);
        }

        foreach (array_chunk($directas, 1000) as $bloco) {
            $novas += DB::table('model_has_permissions')->insertOrIgnore($bloco);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info((count($linhas) + count($directas)) . ' ligação(ões) processada(s), ' . $novas . ' nova(s).');

        return self::SUCCESS;
    }
}
