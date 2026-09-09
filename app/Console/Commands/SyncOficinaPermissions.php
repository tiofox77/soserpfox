<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * As permissões que faltavam à OFICINA quando ela passou a React.
 *
 * São duas faltas, de naturezas diferentes:
 *
 *  1. APAGAR. As viaturas, os mecânicos, os serviços e as peças tinham `view`,
 *     `create` e `edit` — e nunca um `delete`. O ecrã em Livewire apagava na
 *     mesma, sem perguntar a ninguém; o ecrã genérico dos catálogos exige a
 *     permissão por verbo, e uma que não existe na base é um 403 para toda a
 *     gente: o middleware do Spatie recusa o que não conhece.
 *
 *  2. OS ARTIGOS. As peças da oficina SÃO os artigos da facturação — a mesma
 *     tabela, as mesmas regras fiscais. A API que o ecrã usa pede
 *     `invoicing.products.*`, que é a autoridade verdadeira sobre o catálogo.
 *     Quem tinha só `workshop.parts.*` abria a página e via um 403 em cada
 *     pedido; aqui recebe a permissão equivalente.
 *
 * Como os outros `permissions:sync-*`, o trabalho é uma inserção em massa no
 * pivot e não um `givePermissionTo` por papel — com muitas empresas, o laço
 * papel a papel não acaba dentro do tempo da rota de manutenção. É idempotente.
 */
class SyncOficinaPermissions extends Command
{
    protected $signature = 'permissions:sync-oficina {--aplicar : escreve de facto}';

    protected $description = 'Cria as permissões de apagar da oficina e dá os artigos a quem gere peças (a seco por omissão)';

    /** As que não existiam de todo. */
    private const NOVAS = [
        'workshop.vehicles.delete' => 'Apagar Viaturas',
        'workshop.mechanics.delete' => 'Apagar Mecânicos',
        'workshop.services.delete' => 'Apagar Serviços da Oficina',
        'workshop.parts.delete' => 'Apagar Peças',
    ];

    /**
     * QUEM TEM A DA ESQUERDA PASSA A TER A DA DIREITA.
     *
     * As peças são os artigos: sem isto, o ecrã abria e a API respondia 403.
     */
    private const EQUIVALENTES = [
        'workshop.parts.view' => 'invoicing.products.view',
        'workshop.parts.create' => 'invoicing.products.create',
        'workshop.parts.edit' => 'invoicing.products.edit',
        'workshop.parts.delete' => 'invoicing.products.delete',
    ];

    /** Estes gerem a oficina: ficam com as de apagar. */
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

        /*
         * AS EQUIVALÊNCIAS: papel a papel, quem tem a da oficina passa a ter a
         * da facturação. Uma consulta só ao pivot, e não uma por papel.
         */
        $porNome = Permission::whereIn('name', array_merge(
            array_keys(self::EQUIVALENTES), array_values(self::EQUIVALENTES),
        ))->pluck('id', 'name');

        $linhas = [];
        $directas = [];
        $emFalta = [];

        foreach (self::EQUIVALENTES as $daOficina => $daFacturacao) {
            $origem = $porNome[$daOficina] ?? ($ids[$daOficina] ?? null);
            $destino = $porNome[$daFacturacao] ?? null;

            if (! $origem || ! $destino) {
                $emFalta[] = $origem ? $daFacturacao : $daOficina;

                continue;
            }

            foreach (DB::table('role_has_permissions')->where('permission_id', $origem)->pluck('role_id') as $papel) {
                $linhas[] = ['permission_id' => $destino, 'role_id' => $papel];
            }

            /*
             * E AS PERMISSÕES DADAS DIRECTAMENTE A UMA PESSOA.
             *
             * Os outros `sync-*` só mexem em papéis, e chega-lhes: criam
             * permissões novas e repartem-nas. Aqui não — o que se está a
             * copiar é uma autoridade que alguém JÁ TEM, e há contas com a
             * permissão marcada na ficha e nenhum papel. Deixá-las de fora era
             * dar-lhes um ecrã que responde 403 a cada pedido.
             */
            foreach (DB::table('model_has_permissions')->where('permission_id', $origem)->get() as $l) {
                $directas[] = [
                    'permission_id' => $destino,
                    'model_type' => $l->model_type,
                    'model_id' => $l->model_id,
                    'tenant_id' => $l->tenant_id ?? null,
                ];
            }
        }

        // E as de apagar, para quem gere.
        foreach (self::GESTAO as $nome) {
            foreach (Role::where('name', $nome)->pluck('id') as $papel) {
                foreach ($ids as $permissao) {
                    $linhas[] = ['permission_id' => $permissao, 'role_id' => $papel];
                }
            }
        }

        if ($emFalta) {
            $this->warn('  Sem par na base (nada a copiar): ' . implode(', ', $emFalta));
        }

        if (! $aplicar) {
            $this->line('');
            $this->line('  Papéis que ficariam com as de apagar: ' . implode(', ', self::GESTAO));
            $this->line('  Equivalências a copiar: ' . count(self::EQUIVALENTES));
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
