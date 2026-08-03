<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API de sincronização para PWA offline do módulo de Faturação.
 * Autenticação: sessão web existente (mesmo domínio).
 */
class SyncController extends Controller
{
    /**
     * Endpoint principal — devolve catálogo + clientes + séries + impostos.
     * Suporta sync incremental via parâmetro ?since=<timestamp>
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = activeTenantId();
        if (!$tenantId) {
            return response()->json(['error' => 'No active tenant'], 403);
        }

        $since = $request->query('since'); // ISO timestamp para sync incremental
        $sinceDate = $since ? \Carbon\Carbon::parse($since) : null;

        // ---- Armazém DEFAULT do tenant (fonte única de stock no POS) ----
        $defaultWh = \App\Models\Invoicing\Warehouse::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first()
            ?: \App\Models\Invoicing\Warehouse::where('tenant_id', $tenantId)
                ->where('is_active', true)->first();
        $whId = $defaultWh?->id;

        // ---- Produtos ----
        // is_active pode estar como NULL em produtos antigos — só excluir os explicitamente desativados
        // Stock devolvido = stock no armazém default (invoicing_stocks) com fallback para
        // products.stock_quantity quando não há linha (tenants sem multi-armazém).
        // Sub-select: produto tem QUALQUER linha em invoicing_stocks (qualquer armazém)?
        // Se sim, esse produto já está no regime multi-armazém e o agregado legado
        // (invoicing_products.stock_quantity) deixa de ser de confiança — devolver 0
        // quando não há linha para o armazém ativo.
        $hasStockRowSql = '(SELECT 1 FROM invoicing_stocks ss WHERE ss.tenant_id = ? AND ss.product_id = invoicing_products.id LIMIT 1)';
        $stockExpr = "COALESCE(invoicing_stocks.quantity, CASE WHEN EXISTS{$hasStockRowSql} THEN 0 ELSE COALESCE(invoicing_products.stock_quantity, 0) END)";

        $productsQuery = Product::where('invoicing_products.tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('invoicing_products.is_active', true)
                  ->orWhereNull('invoicing_products.is_active');
            })
            ->leftJoin('invoicing_stocks', function ($join) use ($whId, $tenantId) {
                $join->on('invoicing_stocks.product_id', '=', 'invoicing_products.id')
                     ->where('invoicing_stocks.tenant_id', $tenantId)
                     ->where('invoicing_stocks.warehouse_id', $whId);
            })
            ->selectRaw("invoicing_products.*, {$stockExpr} AS stock_in_warehouse", [$tenantId]);

        // Ocultar produtos sem stock (excepto serviços) se configurado em Settings → Faturação
        $hideOutOfStock = \App\Models\Invoicing\InvoicingSettings::forTenant($tenantId)->pos_hide_out_of_stock ?? true;
        if ($hideOutOfStock) {
            $productsQuery->whereRaw("(invoicing_products.type = 'servico' OR {$stockExpr} > 0)", [$tenantId]);
        }

        if ($sinceDate) {
            $productsQuery->where('invoicing_products.updated_at', '>=', $sinceDate);
        }
        // Pre-carregar taxas de IVA para mapeamento tax_rate_id → rate
        $taxMap = DB::table('invoicing_taxes')
            ->where('tenant_id', $tenantId)
            ->pluck('rate', 'id');

        // Tax DEFAULT do tenant — fallback quando o produto não tem tax vinculada.
        // Espelha a lógica do POS online (PosSaleService): sem hardcode de 14%.
        $defaultTax = DB::table('invoicing_taxes')
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
        $defaultTaxRate = (float) ($defaultTax->rate ?? 0);
        $tenantIsExempt = ($defaultTax->saft_type ?? '') === 'ISE';

        // Pre-carregar categorias para mapeamento category_id → nome.
        // (NOTA: $p->category resolve para a RELAÇÃO, não para um nome — por isso
        //  as categorias não chegavam ao PWA. Usamos um mapa id→nome.)
        $categoryMap = DB::table('invoicing_categories')->pluck('name', 'id');

        $products = $productsQuery->get()->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'barcode' => $p->barcode,
            'type' => $p->type,
            'price' => (float) $p->price,
            'cost' => (float) ($p->cost ?? 0),
            // Mesma lógica do POS online: produto isento → 0; senão taxa vinculada
            // ou a default do tenant (que é 0 em regimes de isenção).
            'tax_rate' => ($p->tax_type === 'isento' || $tenantIsExempt)
                ? 0.0
                : (float) ($taxMap[$p->tax_rate_id] ?? $defaultTaxRate),
            'tax_type' => ($p->tax_type === 'isento' || $tenantIsExempt) ? 'isento' : ($p->tax_type ?? 'iva'),
            'exemption_reason' => $p->exemption_reason,
            // Stock no armazém ATIVO (fonte única no POS Offline)
            'stock_quantity' => (float) ($p->stock_in_warehouse ?? 0),
            'warehouse_id' => $whId,
            'category' => $p->category_id ? ($categoryMap[$p->category_id] ?? null) : null,
            'updated_at' => optional($p->updated_at)->toIso8601String(),
        ]);

        // ---- Clientes ----
        $clientsQuery = Client::where('tenant_id', $tenantId);
        if ($sinceDate) {
            $clientsQuery->where('updated_at', '>=', $sinceDate);
        }
        $clients = $clientsQuery->get()->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'nif' => $c->nif,
            'email' => $c->email,
            'phone' => $c->phone,
            'mobile' => $c->mobile,
            'address' => $c->address,
            'city' => $c->city,
            'province' => $c->province,
            'country' => $c->country,
            'type' => $c->type,
            'tax_regime' => $c->tax_regime,
            'is_iva_subject' => (bool) $c->is_iva_subject,
            'updated_at' => optional($c->updated_at)->toIso8601String(),
        ]);

        // ---- Séries documentais ----
        $series = collect();
        try {
            $series = DB::table('invoicing_series')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->select('id', 'document_type', 'series_code', 'name', 'prefix', 'next_number')
                ->get();
        } catch (\Throwable $e) {
            \Log::warning('Sync series query failed: ' . $e->getMessage());
        }

        // ---- Taxas de IVA aplicáveis ----
        $taxRates = [
            ['rate' => 0, 'label' => 'Isento (0%)'],
            ['rate' => 5, 'label' => 'IVA Reduzido (5%)'],
            ['rate' => 7, 'label' => 'IVA Cabinda (7%)'],
            ['rate' => 14, 'label' => 'IVA Normal (14%)'],
        ];

        // ---- Métodos de pagamento (Tesouraria) ----
        $paymentMethods = collect();
        try {
            $paymentMethods = DB::table('treasury_payment_methods')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'code', 'type', 'icon']);
        } catch (\Throwable $e) {
            \Log::warning('Sync payment_methods query failed: ' . $e->getMessage());
        }

        // ---- Módulos ativos do tenant (para a app dar acesso a todos) ----
        $modules = [];
        try {
            $tenantModel = \App\Models\Tenant::find($tenantId);
            if ($tenantModel) {
                $modules = $tenantModel->modules()
                    ->wherePivot('is_active', true)
                    ->orderBy('order')
                    ->get(['modules.slug', 'modules.name', 'modules.icon'])
                    ->map(fn ($m) => ['slug' => $m->slug, 'name' => $m->name, 'icon' => $m->icon])
                    ->values()
                    ->toArray();
            }
        } catch (\Throwable $e) {
            \Log::warning('Sync modules query failed: ' . $e->getMessage());
        }

        // ---- Turno POS aberto do operador (para verificação no POS offline) ----
        $shiftInfo = ['open' => false, 'number' => null, 'opened_at' => null];
        try {
            $openShift = \App\Models\Invoicing\PosShift::where('tenant_id', $tenantId)
                ->where('user_id', auth()->id())
                ->where('status', 'open')
                ->latest('opened_at')
                ->first();
            if ($openShift) {
                $shiftInfo = [
                    'open' => true,
                    'number' => $openShift->shift_number,
                    'opened_at' => optional($openShift->opened_at)->toIso8601String(),
                    // Para o PWA calcular o valor esperado em caixa no fecho offline
                    'opening_balance' => (float) $openShift->opening_balance,
                    'cash_sales' => (float) $openShift->cash_sales,
                    'total_sales' => (float) $openShift->total_sales,
                ];
            }
        } catch (\Throwable $e) {
            \Log::warning('Sync shift query failed: ' . $e->getMessage());
        }

        // ---- Dados da empresa (para ticket offline) ----
        $tenant = \App\Models\Tenant::find($tenantId);
        $settings = null;
        try {
            $settings = \App\Models\Invoicing\InvoicingSettings::forTenant($tenantId);
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'tenant_id' => $tenantId,
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
            ],
            'company' => [
                'name' => $tenant->company_name ?? $tenant->name ?? 'Empresa',
                'nif' => $tenant->nif ?? '',
                'address' => $tenant->address ?? '',
                'phone' => $tenant->phone ?? '',
                'logo' => app_logo(),
                // Fonte única (ver AGTHelper): a coluna do tenant fica muitas
                // vezes em C_PENDING e o número real vive na definição global.
                'agt_cert' => \App\Helpers\AGTHelper::softwareValidationNumber(),
            ],
            'warehouse' => $defaultWh ? [
                'id'   => $defaultWh->id,
                'name' => $defaultWh->name,
                'code' => $defaultWh->code ?? null,
            ] : null,
            'shift' => $shiftInfo,
            'modules' => $modules,
            'data' => [
                'products' => $products,
                'clients' => $clients,
                'series' => $series,
                'tax_rates' => $taxRates,
                'payment_methods' => $paymentMethods,
            ],
            'meta' => [
                'incremental' => (bool) $sinceDate,
                'since' => $since,
                'counts' => [
                    'products' => $products->count(),
                    'clients' => $clients->count(),
                ],
            ],
        ]);
    }

    /**
     * Endpoint leve — só verifica autenticação e devolve servidor estado.
     * Usado pelo PWA para detectar se está realmente online (vs WiFi sem internet).
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'tenant_id' => activeTenantId(),
        ]);
    }

    /**
     * Endpoint de diagnóstico — devolve contagens crus por tabela
     */
    public function diagnose(): JsonResponse
    {
        $tenantId = activeTenantId();
        return response()->json([
            'tenant_id' => $tenantId,
            'user' => auth()->user()?->email,
            'counts' => [
                'products_all' => Product::where('tenant_id', $tenantId)->count(),
                'products_active' => Product::where('tenant_id', $tenantId)->where('is_active', true)->count(),
                'products_null_active' => Product::where('tenant_id', $tenantId)->whereNull('is_active')->count(),
                'products_inactive' => Product::where('tenant_id', $tenantId)->where('is_active', false)->count(),
                'clients' => Client::where('tenant_id', $tenantId)->count(),
                'taxes' => DB::table('invoicing_taxes')->where('tenant_id', $tenantId)->count(),
                'series' => DB::table('invoicing_series')->where('tenant_id', $tenantId)->count(),
            ],
            'sample_product' => Product::where('tenant_id', $tenantId)->first()?->only(['id', 'name', 'is_active', 'price', 'tax_rate_id']),
        ]);
    }
}
