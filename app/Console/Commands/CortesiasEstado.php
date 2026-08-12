<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Subscriptions\DireitoACortesia;
use Illuminate\Console\Command;

/**
 * Quem já gastou a cortesia — e, por isso, deixa de poder escolher o plano
 * gratuito ou começar outro em período de teste.
 *
 * Serve para duas coisas: ver o efeito da regra antes de a pôr a mexer, e
 * responder depois a "porque é que este cliente não consegue escolher o FOX
 * Friendly?" sem ter de abrir a base de dados.
 *
 * Só contas e identificadores — nada de nomes nem contactos.
 */
class CortesiasEstado extends Command
{
    protected $signature = 'cortesias:estado {--empresa= : Detalhe de uma empresa pelo id}';

    protected $description = 'Mostra que empresas já gastaram o plano gratuito ou o período de teste';

    public function handle(): int
    {
        if ($id = $this->option('empresa')) {
            return $this->detalhe((int) $id);
        }

        $gratuito = 0;
        $teste    = 0;
        $clientes = 0;
        $livres   = 0;
        $total    = 0;

        Tenant::withTrashed()->select('id', 'nif')->chunkById(200, function ($empresas) use (
            &$gratuito, &$teste, &$clientes, &$livres, &$total
        ) {
            foreach ($empresas as $empresa) {
                $total++;
                $direito = DireitoACortesia::daEmpresa($empresa);

                if ($direito->jaTeveGratuito()) {
                    $gratuito++;
                }
                if ($direito->jaTeveTeste()) {
                    $teste++;
                }
                if ($direito->jaFoiCliente()) {
                    $clientes++;
                } else {
                    $livres++;
                }
            }
        });

        $this->line('');
        $this->line('  Empresas .................. ' . $total);
        $this->line('  Já foram clientes ......... ' . $clientes . '  (o plano gratuito fecha-se-lhes)');
        $this->line('  Já usaram o gratuito ...... ' . $gratuito);
        $this->line('  Já usaram um teste ........ ' . $teste);
        $this->line('  Ainda com a cortesia ...... ' . $livres);
        $this->line('');

        return self::SUCCESS;
    }

    private function detalhe(int $id): int
    {
        $empresa = Tenant::withTrashed()->find($id);

        if (!$empresa) {
            $this->error("Empresa {$id} não encontrada.");

            return self::FAILURE;
        }

        $direito = DireitoACortesia::daEmpresa($empresa);

        $this->line('');
        $this->line('  Empresa ................... ' . $empresa->id);
        $this->line('  Empresas consideradas ..... ' . implode(', ', $direito->empresasConsideradas()));
        $this->line('  Já foi cliente ............ ' . ($direito->jaFoiCliente() ? 'sim' : 'não'));
        $this->line('  Já teve o gratuito ........ ' . ($direito->jaTeveGratuito() ? 'sim' : 'não'));
        $this->line('  Já teve um teste .......... ' . ($direito->jaTeveTeste() ? 'sim' : 'não'));
        $this->line('');

        foreach (Plan::where('is_active', true)->orderBy('order')->get() as $plano) {
            $recusa = $direito->motivoParaRecusar($plano);
            $this->line(sprintf(
                '  %-22s %-12s teste: %s',
                $plano->slug,
                $recusa ? 'FECHADO' : 'disponível',
                $direito->temDireitoATeste($plano) ? 'sim' : 'não'
            ));
        }

        $this->line('');

        return self::SUCCESS;
    }
}
