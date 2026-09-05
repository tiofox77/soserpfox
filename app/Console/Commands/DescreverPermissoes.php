<?php

namespace App\Console\Commands;

use App\Support\CatalogoDePermissoes;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

/**
 * Dá uma descrição em português às permissões que não têm nenhuma.
 *
 * 162 das 340 permissões nasceram sem descrição — hotel, restaurante, salão
 * e oficina inteiros — e o ecrã de papéis mostrava-lhes o nome técnico.
 * A descrição vem do catálogo (App\Support\CatalogoDePermissoes); só se
 * escreve onde está vazio, nunca por cima de uma escrita à mão.
 *
 * A seco por omissão; --aplicar grava.
 */
class DescreverPermissoes extends Command
{
    protected $signature = 'permissoes:descrever {--aplicar : escreve de facto}';

    protected $description = 'Preenche a descrição das permissões que não têm (a seco por omissão)';

    public function handle(): int
    {
        $semDescricao = Permission::query()
            ->where(fn ($q) => $q->whereNull('description')->orWhere('description', ''))
            ->orderBy('name')
            ->get();

        $this->line('  Sem descrição: '.$semDescricao->count().' de '.Permission::count());
        $this->newLine();

        foreach ($semDescricao as $p) {
            $rotulo = CatalogoDePermissoes::rotulo($p->name);
            $this->line(sprintf('  %-44s → %s', $p->name, $rotulo));

            if ($this->option('aplicar')) {
                $p->update(['description' => $rotulo]);
            }
        }

        $this->newLine();

        if (! $this->option('aplicar')) {
            $this->comment('  A SECO. Corra com --aplicar para gravar.');
        } else {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info('  Gravado: '.$semDescricao->count().' descrições.');
        }

        return self::SUCCESS;
    }
}
