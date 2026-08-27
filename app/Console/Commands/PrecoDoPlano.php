<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;

/**
 * Muda o preço de um plano.
 *
 * O `plans:update-pricing` tem a tabela dos quatro planos principais escrita
 * no código, revista de cada vez que se mexe no posicionamento. Os pacotes por
 * sector (RH, Hotel, Oficina…) ficam de fora dessa tabela de propósito — são
 * ajustes avulsos, não uma revisão de gama. Este comando serve-os.
 *
 * O QUE NÃO MEXE: subscrições em vigor. Cada uma guarda o valor com que foi
 * feita, em `subscriptions.amount`, e o preço novo só lhes aparece na
 * renovação. Subir um plano não pode reescrever o que já foi acordado com
 * quem está a pagar.
 */
class PrecoDoPlano extends Command
{
    protected $signature = 'plano:preco
                            {slug : O plano (ex.: pacote-rh)}
                            {mensal : Preço mensal, em Kz}
                            {--trimestral= : Preço trimestral (por omissão: mensal x3 -5%)}
                            {--semestral= : Preço semestral (por omissão: mensal x6 -10%)}
                            {--anual= : Preço anual (por omissão: mensal x10, os 2 meses grátis da casa)}
                            {--so-ver : Mostra o que faria, sem gravar}';

    protected $description = 'Muda o preço de um plano (não repreça subscrições em vigor)';

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');
        $mensal = $this->emKz($this->argument('mensal'));

        if ($mensal === null || $mensal < 0) {
            $this->error('Preço mensal inválido.');

            return self::FAILURE;
        }

        $plano = Plan::where('slug', $slug)->first();
        if (!$plano) {
            $this->error("Plano '{$slug}' não existe.");
            $this->line('Disponíveis: ' . Plan::orderBy('slug')->pluck('slug')->implode(', '));

            return self::FAILURE;
        }

        // Os escalões seguem a escada da casa quando não são indicados: ~5% no
        // trimestre, ~10% no semestre e dois meses grátis no ano. Deixá-los
        // como estavam dava um anual mais barato que doze meses do preço novo.
        $novos = [
            'price_monthly'    => $mensal,
            'price_quarterly'  => $this->emKz($this->option('trimestral')) ?? round($mensal * 3 * 0.95),
            'price_semiannual' => $this->emKz($this->option('semestral')) ?? round($mensal * 6 * 0.90),
            'price_yearly'     => $this->emKz($this->option('anual')) ?? round($mensal * 10),
        ];

        $this->line("Plano: {$plano->name} ({$plano->slug})");
        foreach ($novos as $campo => $valor) {
            $this->line(sprintf(
                '  %-18s %12s  ->  %12s Kz',
                str_replace('price_', '', $campo),
                number_format((float) $plano->{$campo}, 2, ',', '.'),
                number_format($valor, 2, ',', '.')
            ));
        }

        if ($this->option('so-ver')) {
            $this->warn('Só a ver — nada foi gravado.');

            return self::SUCCESS;
        }

        $plano->forceFill($novos)->save();

        $emVigor = $plano->subscriptions()->whereIn('status', ['active', 'trial'])->count();
        if ($emVigor > 0) {
            $this->warn(sprintf(
                '%d subscrição(ões) em vigor mantêm o valor com que foram feitas. '
                . 'O preço novo aparece-lhes na renovação.',
                $emVigor
            ));
        }

        \Log::info('Preço de plano alterado', [
            'plano' => $plano->slug,
            'para'  => $novos,
        ]);

        $this->info('Gravado.');

        return self::SUCCESS;
    }

    /** Aceita "30000", "30 000" e "30.000" — é como se escreve um preço. */
    private function emKz($valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $limpo = str_replace([' ', '.'], '', (string) $valor);
        $limpo = str_replace(',', '.', $limpo);

        return is_numeric($limpo) ? (float) $limpo : null;
    }
}
