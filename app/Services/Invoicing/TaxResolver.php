<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Tenant;

/**
 * Fonte ÚNICA de resolução de imposto por linha de documento.
 *
 * Antes cada ecrã decidia à sua maneira (POS assumia 14% hardcoded, faturação
 * assumia 0%, notas de crédito ignoravam tax_type) — o mesmo produto saía com
 * impostos diferentes conforme o ecrã, e empresas em regime de isenção
 * chegaram a emitir faturas com IVA 14%.
 *
 * Regras (AGT):
 *  1. Produto isento          → 0%, tax_code ISE, código de isenção obrigatório
 *  2. Produto com taxa ligada → essa taxa
 *  3. Sem taxa ligada         → imposto por omissão do tenant (regime)
 *  4. Se a taxa resolvida for 0 (ou o default for ISE), a linha é ISENTA e
 *     LEVA SEMPRE código de isenção — a AGT rejeita linhas sem imposto e sem motivo.
 */
class TaxResolver
{
    /** Cache do imposto default por tenant (evita query por linha). */
    protected static array $defaultTaxCache = [];

    public static function defaultTax(?int $tenantId): ?Tax
    {
        $tenantId = $tenantId ?: activeTenantId();
        if (!$tenantId) {
            return null;
        }
        if (!array_key_exists($tenantId, static::$defaultTaxCache)) {
            static::$defaultTaxCache[$tenantId] = Tax::where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->first();
        }
        return static::$defaultTaxCache[$tenantId];
    }

    /**
     * Região fiscal AGT do adquirente.
     *
     * Cabinda tem regime próprio (IVA reduzido) e a AGT identifica-o por
     * `AO-CAB` no campo taxCountryRegion. Antes gravava-se sempre 'AO', pelo
     * que uma venda em Cabinda saía com a região do continente.
     */
    public static function regionForClient($client): string
    {
        $provincia = mb_strtolower(trim((string) ($client->province ?? '')));

        // Sem acento e sem variações de escrita
        $normalizada = str_replace(
            ['á', 'â', 'ã', 'à', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ç'],
            ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c'],
            $provincia
        );

        return str_contains($normalizada, 'cabinda') ? 'AO-CAB' : 'AO';
    }

    public static function clearCache(): void
    {
        static::$defaultTaxCache = [];
    }

    /**
     * @return array{rate: float, type: string, exemption_code: ?string, exemption_reason: ?string, tax_code: string}
     */
    public static function forProduct(?Product $product, ?int $tenantId = null): array
    {
        $tenantId   = $tenantId ?: activeTenantId();
        $defaultTax = static::defaultTax($tenantId);

        // 1) Produto explicitamente isento
        if ($product && ($product->tax_type ?? 'iva') === 'isento') {
            return static::exemptResult(
                $product->exemption_reason ?: ($defaultTax->exemption_code ?? null),
                $defaultTax
            );
        }

        // 2/3) Taxa do produto, senão a do regime (imposto por omissão)
        $rate = $product?->taxRate?->rate;
        if ($rate === null) {
            $rate = $defaultTax?->rate;
        }
        $rate = (float) ($rate ?? 0);

        // 4) Taxa 0 (ou default isento) → linha isenta COM motivo obrigatório
        $defaultIsExempt = $defaultTax && in_array($defaultTax->saft_type, ['ISE', 'NS'], true);
        if ($rate <= 0 || $defaultIsExempt) {
            return static::exemptResult(
                $product?->exemption_reason ?: ($defaultTax->exemption_code ?? null),
                $defaultTax
            );
        }

        // O código SAFT vem do imposto configurado (NOR na taxa normal, RED nas
        // reduzidas). Antes devolvia-se sempre 'NOR', pelo que uma venda a 7% ou
        // 5% ia para a AGT declarada como taxa normal — a tabela invoicing_taxes
        // já tinha o saft_code certo e não era lido.
        $saft = $product?->taxRate?->saft_code ?: ($defaultTax->saft_code ?? null);

        return [
            'rate'             => $rate,
            'type'             => 'iva',
            'exemption_code'   => null,
            'exemption_reason' => null,
            'tax_code'         => in_array($saft, ['NOR', 'RED', 'INT'], true) ? $saft : 'NOR',
        ];
    }

    /** Resolve pelo id do produto (conveniência). */
    public static function forProductId($productId, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?: activeTenantId();

        // withTrashed: o regime fiscal do artigo tem de continuar a ser
        // reconhecido depois de ele ser eliminado.
        //
        // Product usa SoftDeletes, e sem isto o find() devolvia null para um
        // artigo eliminado — a linha caía no imposto POR OMISSÃO da empresa. Uma
        // peça isenta apagada enquanto ainda estava numa OS por facturar saía
        // depois a 14% em vez de 0% com código de isenção, num documento já
        // assinado, hasheado e comunicado à AGT. Só corrigível por nota de
        // crédito. O mesmo acontecia às taxas reduzidas (7%, 5%).
        $product  = ($productId && is_numeric($productId))
            ? Product::withTrashed()->where('tenant_id', $tenantId)->find($productId)
            : null;

        return static::forProduct($product, $tenantId);
    }

    /** Linha isenta com código AGT garantido (nunca devolve isento sem motivo). */
    protected static function exemptResult(?string $code, ?Tax $defaultTax): array
    {
        $code = Product::normalizeExemptionCode($code)
            ?: Product::normalizeExemptionCode($defaultTax->exemption_code ?? null)
            ?: static::regimeExemptionCode();

        return [
            'rate'             => 0.0,
            'type'             => 'isento',
            'exemption_code'   => $code,
            'exemption_reason' => Product::exemptionReasonText($code),
            'tax_code'         => 'ISE',
        ];
    }

    /** Código de isenção do regime do tenant activo (último recurso). */
    protected static function regimeExemptionCode(): string
    {
        $tenant = Tenant::find(activeTenantId());
        return ($tenant?->regimeMeta()['exemption_code'])
            ?: \App\Services\Tenant\TaxRegimeSyncer::DEFAULT_EXEMPTION_CODE;
    }
}
