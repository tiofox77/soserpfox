<?php

namespace App\Services\Tenant;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza o estado fiscal do tenant com o REGIME escolhido (AGT).
 *
 * A AGT tem TRÊS regimes (ver Tenant::REGIMES, a fonte única de verdade):
 *
 *  • REGIME GERAL          → imposto por omissão IVA 14% (SAFT NOR)
 *  • REGIME SIMPLIFICADO   → imposto por omissão IVA 7%  (SAFT RED)
 *  • REGIME NÃO SUJEIÇÃO   → imposto por omissão Isento 0% (SAFT ISE) + motivo M04
 *
 * Para cada regime garante coerência em três níveis:
 *   1) invoicing_taxes      — a taxa do regime existe, está activa e é a default
 *   2) invoicing_settings   — default_tax_rate igual à taxa do regime
 *   3) invoicing_products   — tax_type/tax_rate_id/exemption_reason coerentes
 *
 * Regra de segurança nos produtos: ao SAIR de um regime isento só são
 * reconvertidos os produtos que o próprio sistema tinha marcado (isento com o
 * código do regime). Produtos que o utilizador marcou isentos por outro motivo
 * (ex.: M11 produtos farmacêuticos) mantêm-se isentos.
 */
class TaxRegimeSyncer
{
    /** @deprecated usar Tenant::REGIMES[...]['exempt'] — mantido por retrocompatibilidade. */
    public const EXEMPT_REGIMES = ['regime_isencao', 'regime_nao_sujeicao'];

    public const DEFAULT_EXEMPTION_CODE   = 'M04';
    public const DEFAULT_EXEMPTION_REASON = 'Regime Especial de Isenção (Artigo 53.º)';

    /**
     * @param  bool  $force  Re-sincroniza mesmo que o regime não tenha mudado
     *                       (útil para reparar tenants com estado incoerente).
     */
    public function sync(Tenant $tenant, ?string $previousRegime = null, bool $force = false): array
    {
        $newRegime = Tenant::canonicalRegime($tenant->regime);
        $oldRegime = Tenant::canonicalRegime($previousRegime);

        if (!$force && $previousRegime !== null && $newRegime === $oldRegime) {
            return ['changed' => false, 'reason' => 'no-change'];
        }

        return DB::transaction(function () use ($tenant, $newRegime, $oldRegime, $previousRegime) {
            $meta = Tenant::REGIMES[$newRegime];

            // 1) Garantir/marcar a taxa do regime como default
            $tax = $meta['exempt']
                ? $this->ensureExemptTax($tenant, $meta)
                : $this->ensureVatTax($tenant, $meta);

            if ($tax) {
                Tax::where('tenant_id', $tenant->id)
                    ->where('id', '!=', $tax->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            // 2) invoicing_settings.default_tax_rate = taxa do regime
            $settings = InvoicingSettings::firstOrNew(['tenant_id' => $tenant->id]);
            $settings->default_tax_rate = $meta['default_rate'];
            if ($tax) {
                $settings->default_tax_id = $tax->id;
            }
            $settings->save();

            // 3) Produtos
            $productsCount = $meta['exempt']
                ? $this->applyExemptToProducts($tenant, $meta)
                : $this->applyVatToProducts($tenant, $meta, $tax, $oldRegime);

            Log::info('TaxRegimeSyncer: regime sincronizado', [
                'tenant_id'      => $tenant->id,
                'from'           => $previousRegime,
                'to'             => $newRegime,
                'tax_id'         => $tax?->id,
                'default_rate'   => $meta['default_rate'],
                'products_count' => $productsCount,
            ]);

            return [
                'changed'        => true,
                'mode'           => $meta['exempt'] ? 'apply-exempt' : 'apply-vat',
                'regime'         => $newRegime,
                'regime_label'   => $meta['label'],
                'tax_id'         => $tax?->id,
                'default_rate'   => $meta['default_rate'],
                'products_count' => $productsCount,
            ];
        });
    }

    /** Garante a taxa 0% isenta (ISE) do regime de não sujeição/exclusão. */
    protected function ensureExemptTax(Tenant $tenant, array $meta): Tax
    {
        $attrs = [
            'name'             => 'Isento - Regime de Exclusão',
            'description'      => self::DEFAULT_EXEMPTION_REASON,
            'rate'             => 0,
            'type'             => 'iva',
            'saft_code'        => 'ISE',
            'saft_type'        => 'ISE',
            'exemption_code'   => $meta['exemption_code'] ?? self::DEFAULT_EXEMPTION_CODE,
            'exemption_reason' => self::DEFAULT_EXEMPTION_REASON,
            'is_default'       => true,
            'is_active'        => true,
        ];

        $tax = Tax::where('tenant_id', $tenant->id)->where('code', 'ISENTO-EXCL')->first();

        if ($tax) {
            $tax->update($attrs);
            return $tax;
        }

        return Tax::create(array_merge($attrs, [
            'tenant_id'        => $tenant->id,
            'code'             => 'ISENTO-EXCL',
            'include_in_price' => false,
            'compound_tax'     => false,
        ]));
    }

    /**
     * Garante a taxa de IVA do regime (14% geral, 7% simplificado) activa e default.
     * Se não existir no tenant, semeia o conjunto padrão e volta a procurar.
     */
    protected function ensureVatTax(Tenant $tenant, array $meta): ?Tax
    {
        $find = fn() => Tax::where('tenant_id', $tenant->id)
            ->where('code', $meta['tax_code'])
            ->first();

        $tax = $find();

        if (!$tax) {
            // Tenant sem os impostos padrão — semear e tentar de novo.
            Tax::seedDefaultsForTenant($tenant->id);
            $tax = $find();
        }

        if (!$tax) {
            // Último recurso: qualquer taxa activa com a rate do regime.
            $tax = Tax::where('tenant_id', $tenant->id)
                ->where('rate', $meta['default_rate'])
                ->where('is_active', true)
                ->first();
        }

        $tax?->update(['is_default' => true, 'is_active' => true]);

        return $tax;
    }

    /** Regime isento: todos os produtos passam a isento com o código do regime. */
    protected function applyExemptToProducts(Tenant $tenant, array $meta): int
    {
        $code = $meta['exemption_code'] ?? self::DEFAULT_EXEMPTION_CODE;

        return Product::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->update([
                'tax_type'         => 'isento',
                'tax_rate_id'      => null,
                'exemption_reason' => $code,
            ]);
    }

    /**
     * Regime com IVA: reconverter os produtos que o sistema tinha marcado como
     * isentos por causa do regime anterior. Produtos isentos por outro motivo
     * (código diferente) NÃO são tocados — a isenção é uma decisão do utilizador.
     */
    protected function applyVatToProducts(Tenant $tenant, array $meta, ?Tax $tax, string $oldRegime): int
    {
        $oldMeta = Tenant::REGIMES[$oldRegime] ?? null;

        // Só faz sentido reconverter quando vínhamos de um regime isento.
        if (!$oldMeta || !$oldMeta['exempt']) {
            return 0;
        }

        $oldCode = $oldMeta['exemption_code'] ?? self::DEFAULT_EXEMPTION_CODE;

        return Product::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('tax_type', 'isento')
            ->where(function ($q) use ($oldCode) {
                // marcados pelo regime (código do regime) ou sem motivo nenhum
                $q->where('exemption_reason', $oldCode)
                  ->orWhereNull('exemption_reason')
                  ->orWhere('exemption_reason', '');
            })
            ->update([
                'tax_type'         => 'iva',
                'tax_rate_id'      => $tax?->id,
                'exemption_reason' => null,
            ]);
    }
}
