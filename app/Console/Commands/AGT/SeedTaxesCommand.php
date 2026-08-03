<?php

namespace App\Console\Commands\AGT;

use App\Models\Invoicing\Tax;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Garante que TODA a empresa tem o conjunto fiscal completo exigido pela AGT.
 *
 * Sem isto uma empresa não conseguia emitir certos documentos: faltando a taxa
 * reduzida de 5%, por exemplo, não havia como facturar bens desse regime, e a
 * emissão caía na taxa por omissão — declarando à AGT um imposto errado.
 *
 * O que assegura, por empresa:
 *   · IVA 14% (NOR), 7% e 5% (RED), 0% exportação (M04),
 *     isento art. 12.º (M01) e não sujeição (M99);
 *   · retenção IRT 6,5%.
 *
 * As tabelas de IEC (códigos pautais) e de Imposto de Selo (verbas) são globais
 * e comuns a todas as empresas — verifica-as e avisa se estiverem vazias.
 *
 * Idempotente: só acrescenta o que falta, nunca altera taxas já configuradas
 * (o cliente pode tê-las ajustado).
 *
 *   php artisan agt:seed-taxes --dry-run
 *   php artisan agt:seed-taxes --tenant=11
 */
class SeedTaxesCommand extends Command
{
    protected $signature = 'agt:seed-taxes
                            {--tenant= : Limitar a uma empresa}
                            {--dry-run : Apenas mostra o que seria criado}';

    protected $description = 'Garante o conjunto fiscal AGT completo (IVA, isenções, retenção) em todas as empresas';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->info('=== Conjunto fiscal AGT ' . ($dry ? '(dry-run)' : '(REAL)') . ' ===');
        $this->line('  Só acrescenta o que falta; nunca altera taxas já configuradas.');
        $this->newLine();

        $this->verificarTabelasGlobais();

        $query = Tenant::query()->orderBy('id');
        if ($id = $this->option('tenant')) {
            $query->where('id', $id);
        }

        // Fonte ÚNICA do conjunto de taxas: Tax::defaultSet(), a mesma que as
        // empresas novas usam. Duplicar a lista aqui seria a origem da próxima
        // divergência — foi assim que o saft_code passou anos sem ser lido.
        $canonicas = collect(Tax::defaultSet())->pluck('code')->all();
        $empresas = $query->get();
        $totalCriadas = 0;

        $this->line('  Empresas activas: ' . $empresas->count()
            . ' (empresas apagadas são ignoradas)');
        $this->newLine();

        foreach ($empresas as $tenant) {
            $antes = DB::table('invoicing_taxes')->where('tenant_id', $tenant->id)
                ->pluck('code')->all();
            $faltam = array_values(array_diff($canonicas, $antes));

            if (empty($faltam)) {
                continue;
            }

            $this->line("  #{$tenant->id} " . substr($tenant->name, 0, 40)
                . ': <fg=green>' . implode(', ', $faltam) . '</>');

            if (!$dry) {
                // seedDefaultsForTenant é idempotente: só cria o que falta
                $totalCriadas += Tax::seedDefaultsForTenant($tenant->id);
            } else {
                $totalCriadas += count($faltam);
            }
        }

        $this->newLine();
        if ($totalCriadas === 0) {
            $this->info('✓ Todas as empresas activas têm o conjunto fiscal completo.');
        } elseif ($dry) {
            $this->info("(dry-run) {$totalCriadas} taxa(s) seriam criadas.");
        } else {
            $this->info("   Taxas criadas: {$totalCriadas}");
        }

        return self::SUCCESS;
    }

    /** IEC e Imposto de Selo são tabelas globais, comuns a todas as empresas. */
    private function verificarTabelasGlobais(): void
    {
        foreach ([
            'agt_iec_pautal_codes'     => 'códigos pautais IEC',
            'agt_is_verbas'            => 'verbas de Imposto de Selo',
            'agt_tax_exemption_codes'  => 'códigos de isenção',
        ] as $tabela => $desc) {
            $n = DB::table($tabela)->count();
            if ($n === 0) {
                $this->warn("  ⚠ {$desc}: tabela VAZIA — corra os seeders AGT.");
            } else {
                $this->line("  {$desc}: {$n} registos (global)");
            }
        }

        $this->newLine();
    }
}
