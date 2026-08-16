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

        // Um `since` que não se perceba trata-se como sincronização COMPLETA.
        //
        // O Carbon::parse rebenta com lixo e isso dava 500 — o dispositivo
        // ficava sem catálogo por causa de um parâmetro mal formado, que é o
        // que acontece quando o relógio local está errado ou um valor guardado
        // se corrompe. Mais vale mandar tudo do que deixar o balcão sem nada.
        $sinceDate = null;

        if ($since) {
            try {
                $sinceDate = \Carbon\Carbon::parse($since);
            } catch (\Throwable $e) {
                \Log::warning('Sync: since inválido, a tratar como sync completa', [
                    'since' => $since,
                    'error' => $e->getMessage(),
                ]);

                $since = null;
            }
        }

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
            // O stock conta como alteração do produto.
            //
            // Isto olhava só para invoicing_products.updated_at. Mas o stock
            // vive noutra tabela, e o StockObserver actualiza o agregado por
            // query builder — de propósito, para não disparar eventos do
            // Product — logo NÃO toca no updated_at do produto.
            //
            // Com o pos_hide_out_of_stock ligado (é o valor por omissão), o
            // efeito era este: o produto cai a zero e deixa de ser enviado;
            // quando é reposto, o updated_at continua velho e nunca mais volta.
            // E como o PWA junta com bulkPut e nunca apaga, o dispositivo fica
            // com o stock congelado na última vez que o produto passou por cá.
            //
            // No balcão: repõe-se o stock e o POS offline continua a dizer que
            // não há.
            $productsQuery->where(function ($q) use ($sinceDate) {
                $q->where('invoicing_products.updated_at', '>=', $sinceDate)
                  ->orWhere('invoicing_stocks.updated_at', '>=', $sinceDate);
            });
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

            // Farmácia, vestuário, cosmética e mercearia — enviados SEMPRE,
            // mesmo a null.
            //
            // O PWA grava com bulkPut, que junta campo a campo: uma chave
            // omitida deixa o valor antigo intacto no dispositivo. Se um artigo
            // deixasse de exigir receita e nós não mandássemos a chave, o
            // aparelho continuava a pedir receita para sempre — e o mesmo vale
            // para um alergénio corrigido, que é informação que se dá ao balcão.
            'requires_prescription' => (bool) $p->requires_prescription,
            'is_controlled' => (bool) $p->is_controlled,
            'active_ingredient' => $p->active_ingredient,
            'dosage' => $p->dosage,
            'pharmaceutical_form' => $p->pharmaceutical_form,
            'armed_registration' => $p->armed_registration,
            'size' => $p->size,
            'color' => $p->color,
            'gender' => $p->gender,
            'material' => $p->material,
            'net_content' => $p->net_content,
            // Inteiro ou null, nunca "": do outro lado é JavaScript, onde uma
            // string entra em comparações numéricas sem se queixar e depois dá
            // resultados errados em silêncio.
            'pao_months' => $p->pao_months !== null ? (int) $p->pao_months : null,
            'inci_ingredients' => $p->inci_ingredients,
            'storage_conditions' => $p->storage_conditions,
            'allergens' => $p->allergens,
            'origin_country' => $p->origin_country,

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

        // ---- O que SAIU do catálogo ----
        //
        // O PWA guarda tudo com bulkPut, que junta e actualiza mas nunca apaga.
        // Um produto desactivado ou eliminado simplesmente deixava de vir na
        // resposta — e o dispositivo continuava a mostrá-lo e a vendê-lo,
        // offline, indefinidamente. Não basta deixar de o enviar: é preciso
        // dizer que saiu.
        //
        // Só na sincronização incremental. Na completa o dispositivo limpa tudo
        // e recarrega, e mandar anos de produtos apagados seria peso morto.
        $removedProducts = [];
        $removedClients  = [];

        if ($sinceDate) {
            $removedProducts = Product::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) {
                    $q->whereNotNull('deleted_at')
                      ->orWhere('is_active', false);
                })
                ->where('updated_at', '>=', $sinceDate)
                ->pluck('id')
                ->all();

            $removedClients = Client::withTrashed()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('deleted_at')
                ->where('updated_at', '>=', $sinceDate)
                ->pluck('id')
                ->all();
        }

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
                // O email vai porque no aparelho ele é a única forma de
                // distinguir dois operadores com o mesmo nome próprio — e
                // porque é com ele que se entra quando não há internet.
                'email' => auth()->user()->email,
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
                // Ids que o dispositivo tem de APAGAR. Ver acima.
                'removed_products' => $removedProducts,
                'removed_clients'  => $removedClients,
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
