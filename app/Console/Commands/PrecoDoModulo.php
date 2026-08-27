<?php

namespace App\Console\Commands;

use App\Models\Module;
use Illuminate\Console\Command;

/**
 * Muda o preço de catálogo de um módulo.
 *
 * O ecrã /superadmin/modules faz o mesmo em dois cliques — isto existe para
 * quando é preciso fazê-lo sem sessão aberta (deploy, manutenção) e para
 * deixar rasto do que mudou e quando.
 *
 * O QUE ISTO MEXE — e o que NÃO mexe: `modules.default_price` é o preço do
 * CATÁLOGO, o que se propõe quando o módulo é activado para uma empresa nova.
 * Cada empresa que já tem o módulo guarda o seu preço em `tenant_module.price`
 * e NÃO é tocada aqui. É de propósito: subir o catálogo não pode reescrever
 * contratos já acordados sem alguém decidir isso.
 */
class PrecoDoModulo extends Command
{
    protected $signature = 'modulos:preco
                            {slug : O módulo (ex.: rh)}
                            {preco : O preço mensal, em Kz}';

    protected $description = 'Muda o preço de catálogo de um módulo (não toca nas empresas que já o têm)';

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');
        $preco = (float) str_replace([' ', ','], ['', '.'], (string) $this->argument('preco'));

        if ($preco < 0) {
            $this->error('O preço não pode ser negativo.');

            return self::FAILURE;
        }

        $modulo = Module::where('slug', $slug)->first();
        if (!$modulo) {
            $this->error("Módulo '{$slug}' não existe.");
            $this->line('Disponíveis: ' . Module::orderBy('slug')->pluck('slug')->implode(', '));

            return self::FAILURE;
        }

        $antes = (float) $modulo->default_price;
        $modulo->forceFill(['default_price' => $preco])->save();

        $this->info(sprintf(
            '%s (%s): %s -> %s Kz/mês',
            $modulo->name,
            $modulo->slug,
            number_format($antes, 2, ',', '.'),
            number_format($preco, 2, ',', '.')
        ));

        // Quantas empresas ficam com o preço antigo: é a pergunta que vem a
        // seguir, e sem isto ninguém se lembra de a fazer.
        $comOModulo = $modulo->tenants()->count();
        if ($comOModulo > 0) {
            $this->warn(sprintf(
                '%d empresa(s) já tem este módulo e mantém o preço acordado. '
                . 'Este preço novo vale para activações futuras.',
                $comOModulo
            ));
        }

        \Log::info('Preço de catálogo alterado', [
            'modulo' => $modulo->slug,
            'de'     => $antes,
            'para'   => $preco,
        ]);

        return self::SUCCESS;
    }
}
