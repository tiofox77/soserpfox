<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * As permissões das GUIAS DE TRANSPORTE, que nunca existiram.
 *
 * O ecrã existe desde sempre, o menu tem a sua entrada, e a permissão não
 * estava na base. O menu contornava-o com um `canAny` que aceitava a das NOTAS
 * DE DÉBITO — quem podia ver notas de débito via as guias, o que não é a mesma
 * decisão nem de perto: uma guia de transporte leva a mercadoria, a matrícula
 * e a morada de descarga.
 *
 * Sem estas quatro na base, guardar a rota com `permission:` dava 403 a TODA a
 * gente: o middleware do Spatie recusa uma permissão que não conhece.
 *
 * Como os outros `permissions:sync-*`, o trabalho é uma inserção em massa no
 * pivot e não um `givePermissionTo` por papel — com muitas empresas, o laço
 * papel a papel não acaba dentro do tempo da rota de manutenção. É idempotente.
 */
class SyncGuiasPermissions extends Command
{
    protected $signature = 'permissions:sync-guias {--aplicar : escreve de facto}';

    protected $description = 'Cria as permissões das guias de transporte e reparte-as pelos papéis (a seco por omissão)';

    private const PERMISSOES = [
        'invoicing.transport-guides.view' => 'Ver Guias de Transporte',
        'invoicing.transport-guides.create' => 'Criar Guias de Transporte',
        'invoicing.transport-guides.edit' => 'Editar Guias de Transporte',
        'invoicing.transport-guides.delete' => 'Anular Guias de Transporte',
    ];

    /** Estes gerem: ficam com todas. */
    private const GESTAO = [
        'Super Admin', 'Admin', 'Administrador', 'Gestor', 'Gerente', 'Director', 'Diretor',
    ];

    /** Estes consultam: ficam só com a de VER. */
    private const CONSULTA = ['Utilizador', 'Contabilista', 'Vendedor'];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');

        if (! $aplicar) {
            $this->comment('A SECO — nada é escrito. Use --aplicar.');
        }

        $ids = [];

        foreach (self::PERMISSOES as $nome => $descricao) {
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
            $this->line('  Papéis que ficariam com tudo: ' . implode(', ', self::GESTAO));
            $this->line('  Papéis que ficariam só a ver: ' . implode(', ', self::CONSULTA));

            return self::SUCCESS;
        }

        $verApenas = [$ids['invoicing.transport-guides.view']];
        $linhas = [];

        foreach ([[self::GESTAO, array_values($ids)], [self::CONSULTA, $verApenas]] as [$nomes, $permissoes]) {
            foreach ($nomes as $nome) {
                foreach (Role::where('name', $nome)->pluck('id') as $papel) {
                    foreach ($permissoes as $permissao) {
                        $linhas[] = ['permission_id' => $permissao, 'role_id' => $papel];
                    }
                }
            }
        }

        $novas = 0;

        foreach (array_chunk($linhas, 1000) as $bloco) {
            $novas += DB::table('role_has_permissions')->insertOrIgnore($bloco);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info(count($linhas) . ' ligação(ões) processada(s), ' . $novas . ' nova(s).');

        return self::SUCCESS;
    }
}
