<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Cria `invoicing.documents.all` e decide quem fica com ela.
 *
 * A REGRA: sem esta permissão, cada utilizador vê nas listas de documentos
 * (facturas, proformas, orçamentos, notas de crédito e débito, recibos,
 * adiantamentos e compras) apenas os que EMITIU.
 *
 * A REPARTIÇÃO por omissão foi escolhida para não tirar nada a ninguém sem
 * necessidade: quem hoje já vê os documentos continua a vê-los, EXCEPTO os
 * papéis de venda e caixa — que são precisamente aqueles em que o dono não
 * quer que um colega veja as vendas do outro.
 *
 * Papéis de venda reconhecem-se pelo nome (Vendedor, Caixa, Balcão, Loja…).
 * Um papel à medida com outro nome fica com o direito de ver todos — e
 * tira-se-lho no ecrã de papéis, que é uma decisão de quem gere a empresa e
 * não deste comando.
 *
 * A seco por omissão. Só escreve com --aplicar.
 */
class SyncDocumentPermissions extends Command
{
    protected $signature = 'permissions:sync-documentos {--aplicar : escreve de facto}';

    protected $description = 'Cria invoicing.documents.all e reparte-a pelos papéis (a seco por omissão)';

    /** Um papel cujo nome contenha isto é de venda: vê só os seus documentos. */
    private const NOMES_DE_VENDA = ['vendedor', 'vendedora', 'caixa', 'balcão', 'balcao', 'loja', 'seller', 'cashier'];

    /** Estes vêem sempre tudo, chamem-se como se chamarem. */
    private const NOMES_DE_GESTAO = ['super admin', 'admin', 'administrador', 'gestor', 'gerente', 'contabilista', 'director', 'diretor'];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');

        if ($aplicar) {
            Permission::findOrCreate('invoicing.documents.all', 'web');
            $this->info('  Permissão invoicing.documents.all pronta.');
        } else {
            $existe = Permission::where('name', 'invoicing.documents.all')->exists();
            $this->line('  Permissão invoicing.documents.all: '.($existe ? 'já existe' : 'seria criada'));
        }

        $this->newLine();

        // Quem já a tem, para o relatório fazer sentido numa segunda corrida.
        $idDaPermissao = (int) (\Illuminate\Support\Facades\DB::table('permissions')
            ->where('name', 'invoicing.documents.all')->value('id') ?? 0);

        $jaTem = $idDaPermissao
            ? \Illuminate\Support\Facades\DB::table('role_has_permissions')
                ->where('permission_id', $idDaPermissao)->pluck('role_id')->all()
            : [];
        $jaTem = array_flip($jaTem);

        $comTodos = 0;
        $soOsSeus = 0;
        $linhas = [];
        $aDar = [];
        $aTirar = [];

        foreach (Role::all() as $papel) {
            $nome = mb_strtolower($papel->name);

            $deVenda = false;
            foreach (self::NOMES_DE_VENDA as $marca) {
                if (str_contains($nome, $marca)) {
                    $deVenda = true;
                    break;
                }
            }

            $deGestao = false;
            foreach (self::NOMES_DE_GESTAO as $marca) {
                if (str_contains($nome, $marca)) {
                    $deGestao = true;
                    break;
                }
            }

            // Gestão ganha à venda: um «Gestor de Loja» gere, não vende.
            $veTodos = $deGestao || ! $deVenda;

            if ($veTodos) {
                $comTodos++;
                if (! isset($jaTem[$papel->id])) {
                    $aDar[] = ['permission_id' => $idDaPermissao, 'role_id' => $papel->id];
                }
            } else {
                $soOsSeus++;
                $linhas[] = $papel->name;
                if (isset($jaTem[$papel->id])) {
                    $aTirar[] = $papel->id;
                }
            }
        }

        $this->line("  Papéis que vêem os documentos de todos ....... {$comTodos}");
        $this->line("  Papéis que vêem só os seus ................... {$soOsSeus}");
        $this->line('  Já certos ................................... '.($comTodos + $soOsSeus - count($aDar) - count($aTirar)));
        $this->line('  A dar ....................................... '.count($aDar));
        $this->line('  A tirar ..................................... '.count($aTirar));

        // EM BLOCO, não papel a papel: mil chamadas ao Spatie estouravam o
        // tempo do pedido HTTP a meio da escrita. Assim são duas consultas, e
        // uma segunda corrida não tem nada para fazer.
        if ($aplicar && $idDaPermissao) {
            foreach (array_chunk($aDar, 500) as $bloco) {
                \Illuminate\Support\Facades\DB::table('role_has_permissions')->insertOrIgnore($bloco);
            }

            if ($aTirar) {
                \Illuminate\Support\Facades\DB::table('role_has_permissions')
                    ->where('permission_id', $idDaPermissao)
                    ->whereIn('role_id', $aTirar)
                    ->delete();
            }
        }

        if ($linhas) {
            $this->newLine();
            $this->line('  Só os seus: '.implode(', ', array_slice(array_unique($linhas), 0, 20)));
        }

        $this->newLine();

        if (! $aplicar) {
            $this->comment('  A SECO. Corra com --aplicar para gravar.');
        } else {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info('  Gravado.');
        }

        return self::SUCCESS;
    }
}
