<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;

class UpdatePlanPricing extends Command
{
    protected $signature = 'plans:update-pricing {--dry : Apenas mostra mudanças sem gravar}';
    protected $description = 'Atualiza preços dos planos para o mercado angolano (competitivo vs Vendus). NÃO afeta FOX Friendly nem subscrições ativas.';

    /**
     * Tabela de preços para Angola. Anual = -17% (≈ 2 meses grátis).
     *
     * REVISÃO DE AGOSTO DE 2026 — os planos multi-empresa estavam abaixo do
     * mercado, e quanto mais empresas o plano dava, mais barata ficava cada
     * uma. O oposto do que faz o mercado, onde uma empresa a mais é receita
     * a mais.
     *
     * O que se praticava em Angola quando isto foi revisto (€1 ≈ 1.076 Kz):
     *
     *   · Cegid Vendus (certificado AGT): Base €9 ≈ 9.700 Kz, Pro €22 ≈
     *     23.700 Kz — tudo por UMA unidade. "Cada POS ou unidade de negócio
     *     representa uma subscrição adicional", com 25% de desconto na 2.ª e
     *     30% da 3.ª em diante. Três empresas dão-lhe cerca de 58.000 Kz/mês.
     *   · Okulandisa (angolano): Pro a 15.000 Kz/mês para 3 empresas e 5
     *     utilizadores. Ilimitadas, só "sob consulta".
     *   · Kwanzar: 42.500 Kz/semestre (≈ 7.100/mês) para 10 utilizadores,
     *     uma empresa.
     *   · Kacennu: desde 4.500–5.500 Kz/mês, por módulo, uma empresa.
     *
     * Onde estávamos: o Professional dava 3 empresas por 11.900 (3.967 por
     * empresa, 21% abaixo do Okulandisa e cinco vezes abaixo do Vendus); o
     * Business dava 10 por 24.900 (2.490 cada); e o Enterprise dava empresas
     * ILIMITADAS por menos do dobro do Business — a 11.ª empresa e todas as
     * seguintes eram de graça, para sempre.
     *
     * Onde ficamos: cada escalão continua a dar desconto por volume, mas o
     * desconto deixa de tender para zero. Continuamos bem abaixo do Vendus e
     * um pouco acima do Okulandisa, o que se justifica pelo dobro dos
     * utilizadores e mais módulos.
     *
     * O Starter fica onde está de propósito: é a porta de entrada, tem uma
     * empresa só, e a 4.900 fica abaixo do Vendus Base (9.700) e à volta do
     * Kacennu (5.500). É aí que tem de estar.
     *
     * Subscrições em vigor não são repreçadas — cada uma guarda o valor com
     * que foi feita. O preço novo aparece-lhes na renovação.
     */
    protected array $pricing = [
        // 1 empresa, 3 utilizadores — porta de entrada, mantém-se.
        'starter' => [
            'price_monthly' => 4900,
            'price_quarterly' => 13900,   // ~5% off
            'price_semiannual' => 26400,  // ~10% off
            'price_yearly' => 49000,      // ~17% off
        ],
        // 3 empresas, 10 utilizadores. 5.967 Kz por empresa (era 3.967).
        // Okulandisa pede 15.000 por 3 empresas e metade dos utilizadores.
        'professional' => [
            'price_monthly' => 17900,
            'price_quarterly' => 50900,
            'price_semiannual' => 96600,
            'price_yearly' => 179000,
        ],
        // 10 empresas, 50 utilizadores. 4.490 Kz por empresa (era 2.490).
        // No Vendus, dez unidades passam dos 170.000 Kz/mês.
        'business' => [
            'price_monthly' => 44900,
            'price_quarterly' => 127900,
            'price_semiannual' => 242400,
            'price_yearly' => 449000,
        ],
        // Empresas ilimitadas. Estava a menos do dobro do Business, o que
        // fazia do Enterprise a maneira mais barata de ter a décima primeira
        // empresa — e a quinquagésima.
        'enterprise' => [
            'price_monthly' => 89900,
            'price_quarterly' => 256200,
            'price_semiannual' => 485400,
            'price_yearly' => 899000,
        ],
    ];

    public function handle()
    {
        $dry = $this->option('dry');
        $this->info($dry ? '🔍 Modo simulação (dry-run)' : '💾 Atualização real');

        // Garantir que FOX Friendly NUNCA é tocado
        $this->info('🦊 FOX Friendly: preservado (grátis durante 3 meses).');

        foreach ($this->pricing as $slug => $prices) {
            $plan = Plan::where('slug', $slug)->first();
            if (!$plan) {
                $this->warn("  ⚠️  Plano '{$slug}' não encontrado — saltando.");
                continue;
            }

            $this->line('');
            $this->info("📦 Plano: {$plan->name} ({$slug})");
            $this->table(
                ['Ciclo', 'Antes', 'Depois', 'Δ'],
                collect($prices)->map(function ($newVal, $key) use ($plan) {
                    $old = (float) $plan->{$key};
                    $diff = $newVal - $old;
                    $sign = $diff >= 0 ? '+' : '';
                    return [
                        $key,
                        number_format($old, 0, ',', '.') . ' Kz',
                        number_format($newVal, 0, ',', '.') . ' Kz',
                        $sign . number_format($diff, 0, ',', '.') . ' Kz',
                    ];
                })->values()->toArray()
            );

            // Quem está neste plano agora. Um aumento não lhes muda a
            // subscrição em curso — cada uma guarda o valor com que foi
            // feita — mas apanha-os na renovação, e é bom saber quantos são
            // antes de carregar no botão.
            $emVigor = $plan->subscriptions()
                ->whereIn('status', ['active', 'trial'])
                ->count();

            if ($emVigor > 0) {
                $this->line("  {$emVigor} subscrição(ões) em vigor — mantêm o valor actual até renovarem.");
            }

            if (!$dry) {
                $plan->update($prices);
                $this->info("  ✓ Atualizado.");
            }
        }

        $this->line('');
        if ($dry) {
            $this->warn('Nenhuma mudança gravada. Executa sem --dry para aplicar.');
        } else {
            $this->info('✅ Preços atualizados. Subscrições ativas NÃO foram afetadas (mantêm o preço que pagaram).');
        }

        return Command::SUCCESS;
    }
}
