<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Listagem genérica (read-only) das áreas do módulo de Faturação para a app móvel.
 * Tenant-scoped. Normaliza qualquer tabela para { id, title, subtitle, amount, status, date }.
 */
class InvoicingListController extends Controller
{
    private array $tables = [
        'sales-invoices'     => 'invoicing_sales_invoices',
        'purchase-invoices'  => 'invoicing_purchase_invoices',
        'sales-proformas'    => 'invoicing_sales_proformas',
        'purchase-proformas' => 'invoicing_purchase_proformas',
        'receipts'           => 'invoicing_receipts',
        'credit-notes'       => 'invoicing_credit_notes',
        'debit-notes'        => 'invoicing_debit_notes',
        'advances'           => 'invoicing_advances',
        'suppliers'          => 'invoicing_suppliers',
        'brands'             => 'invoicing_brands',
        'categories'         => 'invoicing_categories',
        'warehouses'         => 'invoicing_warehouses',
        'series'             => 'invoicing_series',
        'taxes'              => 'invoicing_taxes',
        'product-batches'    => 'invoicing_product_batches',
        'clients'            => 'invoicing_clients',
        'products'           => 'invoicing_products',
        'shifts'             => 'pos_shifts',
    ];

    // Áreas editáveis (CRUD) => modelo Eloquent
    private array $models = [
        'suppliers'  => \App\Models\Supplier::class,
        'categories' => \App\Models\Category::class,
        'brands'     => \App\Models\Brand::class,
        'warehouses' => \App\Models\Invoicing\Warehouse::class,
        'taxes'      => \App\Models\Invoicing\Tax::class,
        'clients'    => \App\Models\Client::class,
    ];

    // Campos editáveis por área
    private array $editFields = [
        'suppliers'  => ['name', 'nif', 'email', 'phone', 'address'],
        'categories' => ['name', 'description'],
        'brands'     => ['name', 'description'],
        'warehouses' => ['name', 'code', 'address'],
        'taxes'      => ['name', 'code', 'rate', 'type'],
        'clients'    => ['name', 'nif', 'email', 'phone', 'address', 'type', 'tax_regime'],
    ];

    // area => [tabela de itens, chave estrangeira]
    private array $itemTables = [
        'sales-invoices'     => ['invoicing_sales_invoice_items', 'sales_invoice_id'],
        'purchase-invoices'  => ['invoicing_purchase_invoice_items', 'purchase_invoice_id'],
        'sales-proformas'    => ['invoicing_sales_proforma_items', 'sales_proforma_id'],
        'purchase-proformas' => ['invoicing_purchase_proforma_items', 'purchase_proforma_id'],
        'credit-notes'       => ['invoicing_credit_note_items', 'credit_note_id'],
        'debit-notes'        => ['invoicing_debit_note_items', 'debit_note_id'],
    ];

    public function index(string $area): JsonResponse
    {
        $tenantId = activeTenantId();
        if (!$tenantId) {
            return response()->json(['error' => 'No active tenant'], 403);
        }

        $table = $this->tables[$area] ?? null;
        if (!$table || !Schema::hasTable($table)) {
            return response()->json(['data' => [], 'total' => 0, 'area' => $area]);
        }

        $rows = DB::table($table)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($r) => (array) $r);

        // Nomes de clientes/fornecedores (para subtítulo)
        $clientIds = collect($rows)->pluck('client_id')->filter()->unique()->values()->all();
        $supplierIds = collect($rows)->pluck('supplier_id')->filter()->unique()->values()->all();
        $clients = !empty($clientIds)
            ? DB::table('invoicing_clients')->whereIn('id', $clientIds)->pluck('name', 'id')
            : collect();
        $suppliers = !empty($supplierIds)
            ? DB::table('invoicing_suppliers')->whereIn('id', $supplierIds)->pluck('name', 'id')
            : collect();

        $data = collect($rows)->map(function ($row) use ($clients, $suppliers, $area) {
            $title = $row['invoice_number'] ?? $row['proforma_number'] ?? $row['receipt_number']
                ?? $row['credit_note_number'] ?? $row['debit_note_number'] ?? $row['advance_number']
                ?? $row['shift_number'] ?? $row['batch_number'] ?? $row['name'] ?? ('#' . ($row['id'] ?? ''));

            $amount = $row['total'] ?? $row['amount'] ?? $row['total_sales'] ?? $row['cash_sales'] ?? ($row['rate'] ?? null);

            $subtitle = null;
            if (!empty($row['client_id'])) {
                $subtitle = $clients[$row['client_id']] ?? null;
            } elseif (!empty($row['supplier_id'])) {
                $subtitle = $suppliers[$row['supplier_id']] ?? null;
            } elseif (!empty($row['nif'])) {
                $subtitle = 'NIF: ' . $row['nif'];
            } elseif (!empty($row['code'])) {
                $subtitle = $row['code'];
            } elseif (!empty($row['expiry_date'])) {
                $subtitle = 'Validade: ' . $row['expiry_date'];
            }

            $date = $row['invoice_date'] ?? $row['proforma_date'] ?? $row['receipt_date']
                ?? $row['date'] ?? $row['created_at'] ?? null;

            return [
                'id' => $row['id'] ?? null,
                'title' => (string) $title,
                'subtitle' => $subtitle,
                'amount' => $amount !== null ? (float) $amount : null,
                'status' => $row['status'] ?? null,
                'date' => $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : null,
                'is_rate' => $area === 'taxes',
            ];
        })->values();

        return response()->json(['data' => $data, 'total' => $data->count(), 'area' => $area]);
    }

    /**
     * Estatísticas do dashboard de faturação (defensivo).
     */
    public function dashboard(): JsonResponse
    {
        $t = activeTenantId();
        if (!$t) {
            return response()->json(['error' => 'No active tenant'], 403);
        }

        $count = function (string $table) use ($t): int {
            return Schema::hasTable($table) ? (int) DB::table($table)->where('tenant_id', $t)->count() : 0;
        };

        $si = Schema::hasTable('invoicing_sales_invoices')
            ? DB::table('invoicing_sales_invoices')->where('tenant_id', $t) : null;

        $salesTotal = $si ? (float) (clone $si)->sum('total') : 0;
        $salesPaid = $si ? (float) (clone $si)->where('status', 'paid')->sum('total') : 0;
        $salesPending = $si ? (float) (clone $si)->whereIn('status', ['pending', 'sent', 'partial'])->sum('total') : 0;

        $recent = [];
        if ($si) {
            $clients = DB::table('invoicing_clients')->where('tenant_id', $t)->pluck('name', 'id');
            $recent = (clone $si)->orderByDesc('id')->limit(6)->get()->map(fn ($r) => [
                'id' => $r->id,
                'title' => $r->invoice_number ?? ('#' . $r->id),
                'subtitle' => $clients[$r->client_id] ?? 'Consumidor Final',
                'amount' => (float) ($r->total ?? 0),
                'status' => $r->status ?? null,
                'date' => isset($r->invoice_date) ? \Carbon\Carbon::parse($r->invoice_date)->format('d/m/Y') : null,
            ])->values();
        }

        return response()->json([
            'sales_total' => $salesTotal,
            'sales_paid' => $salesPaid,
            'sales_pending' => $salesPending,
            'counts' => [
                'sales_invoices' => $count('invoicing_sales_invoices'),
                'proformas' => $count('invoicing_sales_proformas'),
                'receipts' => $count('invoicing_receipts'),
                'clients' => $count('invoicing_clients'),
                'products' => $count('invoicing_products'),
                'suppliers' => $count('invoicing_suppliers'),
            ],
            'recent_invoices' => $recent,
        ]);
    }

    /**
     * Detalhe de um documento (registo + itens, quando aplicável).
     */
    public function detail(string $area, int $id): JsonResponse
    {
        $t = activeTenantId();
        if (!$t) {
            return response()->json(['error' => 'No active tenant'], 403);
        }
        $table = $this->tables[$area] ?? null;
        if (!$table || !Schema::hasTable($table)) {
            return response()->json(['error' => 'Área inválida'], 404);
        }

        $row = DB::table($table)->where('tenant_id', $t)->where('id', $id)->first();
        if (!$row) {
            return response()->json(['error' => 'Não encontrado'], 404);
        }
        $record = (array) $row;

        if (!empty($record['client_id'])) {
            $record['_client_name'] = DB::table('invoicing_clients')->where('id', $record['client_id'])->value('name');
        }
        if (!empty($record['supplier_id'])) {
            $record['_supplier_name'] = DB::table('invoicing_suppliers')->where('id', $record['supplier_id'])->value('name');
        }

        $items = [];
        if (isset($this->itemTables[$area])) {
            [$itemsTable, $fk] = $this->itemTables[$area];
            if (Schema::hasTable($itemsTable)) {
                $items = DB::table($itemsTable)->where($fk, $id)->get()->map(function ($i) {
                    $i = (array) $i;
                    return [
                        'product_name' => $i['product_name'] ?? $i['description'] ?? 'Item',
                        'quantity' => (float) ($i['quantity'] ?? 0),
                        'unit_price' => (float) ($i['unit_price'] ?? 0),
                        'tax_rate' => (float) ($i['tax_rate'] ?? 0),
                        'total' => (float) ($i['total'] ?? 0),
                    ];
                })->values();
            }
        }

        return response()->json(['record' => $record, 'items' => $items]);
    }

    // ==================== CRUD (áreas de dados-mestre) ====================

    public function store(\Illuminate\Http\Request $request, string $area): JsonResponse
    {
        $tenantId = activeTenantId();
        if (!$tenantId) return response()->json(['error' => 'No active tenant'], 403);
        $model = $this->models[$area] ?? null;
        if (!$model) return response()->json(['error' => 'Área não editável'], 422);

        $request->validate(['name' => 'required|string|max:255']);
        $data = $this->payload($request, $area, $tenantId);

        try {
            $obj = new $model();
            $obj->forceFill($data);
            $obj->save();
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        return response()->json(['id' => $obj->id, 'ok' => true], 201);
    }

    public function update(\Illuminate\Http\Request $request, string $area, int $id): JsonResponse
    {
        $tenantId = activeTenantId();
        if (!$tenantId) return response()->json(['error' => 'No active tenant'], 403);
        $model = $this->models[$area] ?? null;
        if (!$model) return response()->json(['error' => 'Área não editável'], 422);

        $obj = $model::where('tenant_id', $tenantId)->find($id);
        if (!$obj) return response()->json(['error' => 'Não encontrado'], 404);

        $request->validate(['name' => 'required|string|max:255']);
        $fields = $this->editFields[$area];
        try {
            $obj->forceFill(collect($request->only($fields))->all());
            $obj->save();
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        return response()->json(['ok' => true]);
    }

    public function destroy(string $area, int $id): JsonResponse
    {
        $tenantId = activeTenantId();
        if (!$tenantId) return response()->json(['error' => 'No active tenant'], 403);
        $model = $this->models[$area] ?? null;
        if (!$model) return response()->json(['error' => 'Área não editável'], 422);

        $obj = $model::where('tenant_id', $tenantId)->find($id);
        if (!$obj) return response()->json(['error' => 'Não encontrado'], 404);
        try {
            $obj->delete();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Não foi possível eliminar (em uso?).'], 422);
        }
        return response()->json(['ok' => true]);
    }

    private function payload(\Illuminate\Http\Request $request, string $area, int $tenantId): array
    {
        $data = collect($request->only($this->editFields[$area]))
            ->filter(fn ($v) => $v !== null)->all();
        $data['tenant_id'] = $tenantId;
        $data['is_active'] = $data['is_active'] ?? true;

        if ($area === 'taxes') {
            $data['type'] = $data['type'] ?? 'iva';
            $data['rate'] = (float) ($data['rate'] ?? 0);
            $data['saft_type'] = $data['saft_type'] ?? 'NOR';
            $data['saft_code'] = $data['saft_code'] ?? 'NOR';
        } elseif ($area === 'clients') {
            $data['type'] = $data['type'] ?? 'pessoa_fisica';
            $data['country'] = $data['country'] ?? 'Angola';
            $data['tax_regime'] = $data['tax_regime'] ?? 'geral';
        } elseif ($area === 'suppliers') {
            $data['country'] = $data['country'] ?? 'Angola';
        }
        return $data;
    }
}
