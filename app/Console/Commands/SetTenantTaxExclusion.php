<?php

namespace App\Console\Commands;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Tenant\TaxRegimeSyncer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Coloca um tenant no REGIME DE NÃO SUJEIÇÃO / EXCLUSÃO (Artigo 53.º — isento
 * de IVA), delegando em TaxRegimeSyncer para haver um único caminho de código:
 *
 *   1. tenants.regime            → regime_nao_sujeicao (canónico)
 *   2. invoicing_taxes           → 'ISENTO-EXCL' 0% (SAFT ISE, código M04) como default
 *   3. invoicing_settings        → default_tax_rate = 0 e default_tax_id
 *   4. invoicing_products        → tax_type=isento, tax_rate_id=NULL, exemption_reason=M04
 *
 * Idempotente. NÃO altera documentos já emitidos.
 *
 * ATENÇÃO: há tenants que partilham o mesmo NIF (ex.: as duas farmácias com o
 * NIF 5417289442). Por isso o alvo preferencial é o ID; se for passado um NIF
 * ambíguo, o comando recusa em vez de adivinhar.
 *
 * Uso:
 *   php artisan tenant:set-tax-exclusion --tenant=19
 *   php artisan tenant:set-tax-exclusion --tenant=19 --dry
 *   php artisan tenant:set-tax-exclusion 5417289442        (só se o NIF for único)
 */
class SetTenantTaxExclusion extends Command
{
    protected $signature = 'tenant:set-tax-exclusion
                            {nif? : NIF do tenant (só se for único; preferir --tenant)}
                            {--tenant= : ID do tenant (forma segura e inequívoca)}
                            {--regime= : Regime destino (regime_geral|regime_simplificado|regime_nao_sujeicao). Omitido = não sujeição}
                            {--code=M04 : Código de isenção AGT}
                            {--dry : Apenas simula, não persiste}';

    protected $description = 'Define o REGIME fiscal AGT de um tenant e sincroniza taxes, settings e produtos';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $tenant = $this->resolveTenant();
        if (!$tenant) {
            return self::FAILURE;
        }

        // Regime destino: por omissão, não sujeição (comportamento histórico).
        $target = Tenant::canonicalRegime($this->option('regime') ?: Tenant::REGIME_NAO_SUJEICAO);
        if ($this->option('regime') && !isset(Tenant::REGIMES[$target])) {
            $this->error('Regime inválido. Use: ' . implode(', ', array_keys(Tenant::REGIMES)));
            return self::FAILURE;
        }

        $meta = Tenant::REGIMES[$target];

        $this->info("Tenant: {$tenant->name} (ID {$tenant->id}, NIF {$tenant->nif})");
        $this->line("Regime actual : " . $tenant->regimeLabel() . " ({$tenant->regime})");
        $this->line("Regime destino: {$meta['label']} — "
            . ($meta['exempt']
                ? "0% IVA, código {$meta['exemption_code']}"
                : 'IVA ' . rtrim(rtrim(number_format($meta['default_rate'], 2, ',', ''), '0'), ',') . '%'));
        if ($dry) {
            $this->warn('** MODO DRY-RUN — nada será persistido **');
        }
        $this->newLine();

        // Fotografia ANTES
        $before = $this->snapshot($tenant->id);
        $this->line('  ANTES  → ' . $this->fmt($before));

        DB::beginTransaction();
        try {
            $previousRegime = $tenant->regime;
            $tenant->update(['regime' => $target]);

            // force=true: re-sincroniza mesmo que o regime já estivesse marcado,
            // reparando estados incoerentes.
            $result = (new TaxRegimeSyncer())->sync($tenant->fresh(), $previousRegime, true);

            $after = $this->snapshot($tenant->id);
            $this->line('  DEPOIS → ' . $this->fmt($after));
            $this->newLine();
            $this->info("  Produtos actualizados: " . ($result['products_count'] ?? 0));
            $this->info("  Imposto por omissão  : tax #" . ($result['tax_id'] ?? '-') . " a " . ($result['default_rate'] ?? '-') . '%');

            // Segurança: documentos já emitidos NÃO podem ser tocados
            $docs = DB::table('invoicing_sales_invoices')->where('tenant_id', $tenant->id)->count();
            $this->line("  Documentos emitidos (intocados): {$docs}");

            if ($dry) {
                DB::rollBack();
                $this->newLine();
                $this->warn('(dry-run) Alterações revertidas. Remova --dry para aplicar.');
                return self::SUCCESS;
            }

            DB::commit();
            $this->newLine();
            $this->info('✓ Regime aplicado com sucesso.');
            $this->line('  Nota: os dispositivos POS offline precisam de re-sincronizar o catálogo.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Falhou: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /** Resolve o tenant por ID (preferencial) ou por NIF (recusa se ambíguo). */
    protected function resolveTenant(): ?Tenant
    {
        if ($id = $this->option('tenant')) {
            $tenant = Tenant::find($id);
            if (!$tenant) {
                $this->error("Tenant #{$id} não encontrado.");
            }
            return $tenant;
        }

        $nif = $this->argument('nif');
        if (!$nif) {
            $this->error('Indique --tenant=<id> (recomendado) ou o NIF.');
            return null;
        }

        $matches = Tenant::where('nif', $nif)->get();
        if ($matches->isEmpty()) {
            $this->error("Nenhum tenant com NIF {$nif}.");
            return null;
        }
        if ($matches->count() > 1) {
            $this->error("NIF {$nif} pertence a {$matches->count()} empresas — use --tenant=<id>:");
            foreach ($matches as $m) {
                $this->line("   #{$m->id} {$m->name} (regime actual: {$m->regime})");
            }
            return null;
        }

        return $matches->first();
    }

    protected function snapshot(int $tenantId): array
    {
        $tax = Tax::where('tenant_id', $tenantId)->where('is_default', true)->first();
        $settings = InvoicingSettings::where('tenant_id', $tenantId)->first();

        return [
            'tax'      => $tax?->code ?? '—',
            'rate'     => $tax ? (float) $tax->rate : null,
            'saft'     => $tax?->saft_type ?? '—',
            'code'     => $tax?->exemption_code ?? '—',
            'setting'  => $settings ? (float) $settings->default_tax_rate : null,
            'prod_iva' => Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('tax_type', '<>', 'isento')->count(),
            'prod_ise' => Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('tax_type', 'isento')->count(),
        ];
    }

    protected function fmt(array $s): string
    {
        return "imposto {$s['tax']} {$s['rate']}% ({$s['saft']}, cod {$s['code']}) | settings {$s['setting']}% | produtos: IVA={$s['prod_iva']} isentos={$s['prod_ise']}";
    }
}
