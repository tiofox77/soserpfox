<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Invoicing\Tax;
use Illuminate\Console\Command;

/**
 * Garante que cada tenant tem o conjunto padrão de impostos de faturação
 * (IVA Angola + SAFT-AO + IRT). Idempotente e ADITIVO (por código), por isso
 * tenants que só tinham 1 imposto recebem os restantes em falta.
 *
 *   php artisan taxes:backfill              → todos os tenants
 *   php artisan taxes:backfill --tenant=17  → apenas o tenant 17
 */
class BackfillTenantTaxes extends Command
{
    protected $signature = 'taxes:backfill
                            {--tenant= : ID de um tenant específico}
                            {--align-exemptions : Alinha isenções: primeiro os REGIMES (invoicing_taxes.exemption_code), depois os PRODUTOS (products.exemption_reason passa a guardar o código AGT)}
                            {--dry-run : Só mostra o que seria alterado}';

    protected $description = 'Cria/garante os impostos padrão de faturação (IVA/SAFT) em falta nos tenants; --align-exemptions alinha códigos de isenção regime→produtos';

    public function handle(): int
    {
        $query = Tenant::query()->orderBy('id');
        if ($id = $this->option('tenant')) {
            $query->where('id', $id);
        }
        $tenants = $query->get(['id', 'name']);

        $expected = count(Tax::defaultSet());

        foreach ($tenants as $tenant) {
            $before = Tax::where('tenant_id', $tenant->id)->count();
            $created = Tax::seedDefaultsForTenant($tenant->id);
            $after = Tax::where('tenant_id', $tenant->id)->count();

            $this->info("✓ #{$tenant->id} {$tenant->name} — antes: {$before}, criados: {$created}, agora: {$after} (mínimo esperado: {$expected})");
        }

        $this->newLine();
        $this->info('Backfill de impostos concluído.');

        if ($this->option('align-exemptions')) {
            $this->newLine();
            $this->alignExemptions($tenants);
        }

        return self::SUCCESS;
    }

    /**
     * Alinha os códigos de isenção AGT: 1) regimes (invoicing_taxes.exemption_code),
     * 2) produtos (invoicing_products.exemption_reason tem de conter o CÓDIGO).
     *
     * Motivo: items.tax_exemption_code é varchar(10). Produtos com a DESCRIÇÃO
     * gravada faziam a venda falhar ("Data too long for column
     * 'tax_exemption_code'"). Idempotente.
     */
    protected function alignExemptions($tenants): void
    {
        $dry = (bool) $this->option('dry-run');
        $this->info('=== Alinhamento de isenções AGT ' . ($dry ? '(dry-run)' : '(REAL)') . ' ===');

        // Lista de tenants a tratar vinda dos DADOS (não da tabela tenants): há
        // tenants soft-deleted cujos impostos/produtos continuam na BD e ficavam
        // de fora do alinhamento.
        $tenantIds = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : \Illuminate\Support\Facades\DB::table('invoicing_taxes')->distinct()->pluck('tenant_id')
                ->merge(\Illuminate\Support\Facades\DB::table('invoicing_products')->distinct()->pluck('tenant_id'))
                ->unique()->sort()->values()->all();

        // ── PASSO 1: REGIMES ──
        $this->line("\n<fg=cyan>1) Regimes (invoicing_taxes)</>");
        $taxesFixed = 0;
        foreach ($tenantIds as $tid) {
            // Precisam de código: os que têm descrição E TAMBÉM todos os ISE/NS
            // (isentos/não sujeitos sem descrição também exigem código AGT).
            $taxes = Tax::where('tenant_id', $tid)
                ->where(function ($q) {
                    $q->whereNull('exemption_code')->orWhere('exemption_code', '');
                })
                ->where(function ($q) {
                    $q->whereIn('saft_type', ['ISE', 'NS'])
                      ->orWhere(function ($q2) {
                          $q2->whereNotNull('exemption_reason')->where('exemption_reason', '<>', '');
                      });
                })
                ->get(['id', 'code', 'name', 'saft_type', 'exemption_reason']);

            foreach ($taxes as $tax) {
                // 1º o código esperado para este imposto padrão (IVAISEN→M01,
                // IVANS→M99, IVA0→M04, ISENTO-EXCL→M04); 2º resolver pela descrição;
                // 3º fallback por saft_type (ISE→M01, NS→M99).
                $code = Tax::defaultExemptionCodeFor($tax->code)
                    ?: \App\Models\Product::normalizeExemptionCode($tax->exemption_reason)
                    ?: ($tax->saft_type === 'NS' ? 'M99' : ($tax->saft_type === 'ISE' ? 'M01' : null));
                if (!$code) {
                    $this->warn("   ? #{$tid} tax {$tax->code}: sem código para \"{$tax->exemption_reason}\"");
                    continue;
                }
                $this->line("   • #{$tid} {$tax->code} → exemption_code={$code}");
                if (!$dry) {
                    $tax->update(['exemption_code' => $code]);
                }
                $taxesFixed++;
            }
        }
        $this->info("   Regimes alinhados: {$taxesFixed}");

        // ── PASSO 2: PRODUTOS (a partir dos regimes já corretos) ──
        $this->line("\n<fg=cyan>2) Produtos (invoicing_products.exemption_reason → código AGT)</>");
        $prodFixed = 0;
        $prodFilled = 0;
        foreach ($tenantIds as $tid) {
            $tenant = (object) ['id' => $tid];
            // Código de isenção canónico do tenant (regime default ISE, senão M04)
            $defaultCode = Tax::where('tenant_id', $tenant->id)
                ->where('saft_type', 'ISE')
                ->orderByDesc('is_default')
                ->value('exemption_code')
                ?: \App\Services\Tenant\TaxRegimeSyncer::DEFAULT_EXEMPTION_CODE;

            // 2a) Valores que NÃO são código (descrição gravada no lugar errado)
            $bad = \App\Models\Product::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('exemption_reason')
                ->whereRaw('CHAR_LENGTH(exemption_reason) > 10')
                ->get(['id', 'name', 'exemption_reason']);

            foreach ($bad as $p) {
                $code = \App\Models\Product::normalizeExemptionCode($p->exemption_reason) ?: $defaultCode;
                $this->line("   • #{$tenant->id} produto {$p->id} \"" . mb_substr($p->name, 0, 30) . "\": texto → {$code}");
                if (!$dry) {
                    \App\Models\Product::withoutGlobalScopes()->where('id', $p->id)->update(['exemption_reason' => $code]);
                }
                $prodFixed++;
            }

            // 2b) Isentos SEM código nenhum → herdar o código do regime
            $missing = \App\Models\Product::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('tax_type', 'isento')
                ->where(function ($q) {
                    $q->whereNull('exemption_reason')->orWhere('exemption_reason', '');
                })
                ->count();

            if ($missing > 0) {
                $this->line("   • #{$tenant->id}: {$missing} produto(s) isento(s) sem código → {$defaultCode}");
                if (!$dry) {
                    \App\Models\Product::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('tax_type', 'isento')
                        ->where(function ($q) {
                            $q->whereNull('exemption_reason')->orWhere('exemption_reason', '');
                        })
                        ->update(['exemption_reason' => $defaultCode]);
                }
                $prodFilled += $missing;
            }
        }

        // ── PASSO 3: coerência tax_type ↔ REGIME ──
        // Um tenant em regime de isenção não pode ter produtos 'iva' (o importador
        // criou 939 assim e o POS chegou a emitir faturas com 14% numa farmácia
        // que não pode liquidar IVA).
        $this->line("\n<fg=cyan>3) Coerência tax_type ↔ regime</>");
        $prodRegime = 0;
        foreach ($tenantIds as $tid) {
            $tenant = \App\Models\Tenant::withTrashed()->find($tid);
            if (!$tenant) {
                continue;
            }
            $meta = $tenant->regimeMeta();
            if (!$meta['exempt']) {
                continue;   // regimes com IVA: não forçar nada (isenções são decisão do utilizador)
            }

            // GUARDA: só alinhar quando o regime E a configuração fiscal concordam.
            // Se o tenant diz "isento" mas o imposto por omissão é IVA (e já emitiu
            // documentos com IVA), o campo do regime é que está errado — nunca
            // reescrever produtos às cegas; avisar e deixar a decisão ao utilizador.
            $defaultTax = Tax::where('tenant_id', $tid)->where('is_default', true)->first();
            $configIsExempt = $defaultTax
                && (in_array($defaultTax->saft_type, ['ISE', 'NS'], true) || (float) $defaultTax->rate == 0.0);

            if (!$configIsExempt) {
                $comIva = \App\Models\Product::withoutGlobalScopes()
                    ->where('tenant_id', $tid)->where('tax_type', '<>', 'isento')->count();
                $this->warn("   ! #{$tid}: regime diz «{$meta['short']}» mas o imposto por omissão é "
                    . ($defaultTax->code ?? 'nenhum') . ' (' . ($defaultTax->rate ?? '?') . '%). '
                    . "{$comIva} produto(s) com IVA NÃO foram tocados — confirme o regime em Dados da Empresa.");
                continue;
            }

            $code = Tax::where('tenant_id', $tid)->where('saft_type', 'ISE')
                ->orderByDesc('is_default')->value('exemption_code')
                ?: ($meta['exemption_code'] ?? \App\Services\Tenant\TaxRegimeSyncer::DEFAULT_EXEMPTION_CODE);

            $q = \App\Models\Product::withoutGlobalScopes()
                ->where('tenant_id', $tid)
                ->where('tax_type', '<>', 'isento');
            $n = (clone $q)->count();

            if ($n > 0) {
                $this->line("   • #{$tid} ({$meta['short']}): {$n} produto(s) com IVA → isento + {$code}");
                if (!$dry) {
                    $q->update(['tax_type' => 'isento', 'tax_rate_id' => null, 'exemption_reason' => $code]);
                }
                $prodRegime += $n;
            }
        }
        $this->info("   Produtos alinhados ao regime: {$prodRegime}");

        $this->newLine();
        $this->info("   Produtos corrigidos (texto→código): {$prodFixed}");
        $this->info("   Produtos isentos preenchidos: {$prodFilled}");
        $this->newLine();
        $this->info($dry ? '(dry-run) Nada foi alterado.' : '✓ Isenções alinhadas — regimes e produtos coerentes.');
    }
}
