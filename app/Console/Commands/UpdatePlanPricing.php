<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;

class UpdatePlanPricing extends Command
{
    protected $signature = 'plans:update-pricing {--dry : Apenas mostra mudanças sem gravar}';
    protected $description = 'Atualiza preços dos planos para o mercado angolano (competitivo vs Vendus). NÃO afeta FOX Friendly nem subscrições ativas.';

    /**
     * Tabela de preços competitiva para Angola.
     * Anual = -17% (≈ 2 meses grátis)
     */
    protected array $pricing = [
        'starter' => [
            'price_monthly' => 4900,
            'price_quarterly' => 13900,   // ~5% off
            'price_semiannual' => 26400,  // ~10% off
            'price_yearly' => 49000,      // ~17% off
        ],
        'professional' => [
            'price_monthly' => 11900,
            'price_quarterly' => 33900,
            'price_semiannual' => 64200,
            'price_yearly' => 119000,
        ],
        'business' => [
            'price_monthly' => 24900,
            'price_quarterly' => 70900,
            'price_semiannual' => 134400,
            'price_yearly' => 249000,
        ],
        'enterprise' => [
            'price_monthly' => 49900,
            'price_quarterly' => 142200,
            'price_semiannual' => 269400,
            'price_yearly' => 499000,
        ],
    ];

    public function handle()
    {
        $dry = $this->option('dry');
        $this->info($dry ? '🔍 Modo simulação (dry-run)' : '💾 Atualização real');

        // Garantir que FOX Friendly NUNCA é tocado
        $this->info('🦊 FOX Friendly: preservado (grátis durante 6 meses).');

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
