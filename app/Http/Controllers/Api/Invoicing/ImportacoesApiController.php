<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\Import;
use App\Models\Invoicing\Warehouse;
use App\Models\Supplier;
use App\Services\Invoicing\GestorDeImportacoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AS IMPORTAÇÕES, para o ecrã em React.
 *
 * O registo e o percurso vivem no `GestorDeImportacoes`, o mesmo que o
 * Livewire chama. Cada verbo tem a sua permissão.
 */
class ImportacoesApiController extends Controller
{
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.imports.view');

        $tenantId = activeTenantId();
        $escolhas = fn (array $lista) => collect($lista)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values();

        return response()->json([
            'fornecedores' => Supplier::where('tenant_id', $tenantId)->orderBy('name')->limit(500)->get(['id', 'name']),
            'armazens' => Warehouse::where('tenant_id', $tenantId)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']),
            'estados' => $escolhas(GestorDeImportacoes::ESTADOS),
            'transportes' => $escolhas(GestorDeImportacoes::TRANSPORTES),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('invoicing.imports.create'),
                'pode_editar' => (bool) $request->user()?->can('invoicing.imports.edit'),
                'pode_apagar' => (bool) $request->user()?->can('invoicing.imports.delete'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.imports.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'max:30'],
            'fornecedor' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantId = activeTenantId();

        $pagina = Import::with(['supplier', 'warehouse'])
            ->where('tenant_id', $tenantId)
            ->when($filtros['procura'] ?? null, fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('import_number', 'like', "%{$v}%")
                    ->orWhere('reference', 'like', "%{$v}%")
                    ->orWhere('container_number', 'like', "%{$v}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$v}%"));
            }))
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filtros['fornecedor'] ?? null, fn ($q, $v) => $q->where('supplier_id', $v))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn ($i) => $this->linha($i))->values(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
            'resumo' => [
                'total' => Import::where('tenant_id', $tenantId)->count(),
                'em_transito' => Import::where('tenant_id', $tenantId)->where('status', 'in_transit')->count(),
                'na_alfandega' => Import::where('tenant_id', $tenantId)->whereIn('status', ['customs_pending', 'customs_inspection'])->count(),
                'valor_em_curso' => round((float) Import::where('tenant_id', $tenantId)->whereNotIn('status', ['cancelled', 'completed'])->sum('cif_value'), 2),
            ],
        ]);
    }

    public function guardar(Request $request, GestorDeImportacoes $gestor): JsonResponse
    {
        $this->exigir($request, 'invoicing.imports.create');

        $dados = $request->validate($gestor->regras());
        $this->daEmpresa($dados);

        $i = $gestor->criar($dados, activeTenantId());

        return response()->json(['data' => $this->linha($i->load(['supplier', 'warehouse'])), 'message' => __('Importação :n criada.', ['n' => $i->import_number])], 201);
    }

    public function actualizar(Request $request, GestorDeImportacoes $gestor, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.imports.edit');

        $i = Import::where('tenant_id', activeTenantId())->findOrFail($id);
        $dados = $request->validate($gestor->regras());
        $this->daEmpresa($dados);

        $gestor->actualizar($i, $dados);

        return response()->json(['data' => $this->linha($i->fresh(['supplier', 'warehouse'])), 'message' => __('Importação :n actualizada.', ['n' => $i->import_number])]);
    }

    public function estado(Request $request, GestorDeImportacoes $gestor, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.imports.edit');

        $i = Import::where('tenant_id', activeTenantId())->findOrFail($id);
        $dados = $request->validate(['estado' => ['required', 'in:' . implode(',', array_keys(GestorDeImportacoes::ESTADOS))]]);

        $gestor->mudarEstado($i, $dados['estado']);

        return response()->json(['data' => $this->linha($i->fresh(['supplier', 'warehouse'])), 'message' => __('Estado: :e', ['e' => $i->status_label])]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.imports.delete');

        $i = Import::where('tenant_id', activeTenantId())->findOrFail($id);
        $i->delete();

        return response()->json(['message' => __('Importação :n eliminada.', ['n' => $i->import_number])]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** O fornecedor e o armazém têm de ser desta empresa: o `exists` sozinho não chega. */
    private function daEmpresa(array $d): void
    {
        $tenantId = activeTenantId();

        abort_unless(Supplier::where('tenant_id', $tenantId)->whereKey($d['supplier_id'])->exists(), 422, __('Fornecedor desconhecido nesta empresa.'));

        if (! empty($d['warehouse_id'])) {
            abort_unless(Warehouse::where('tenant_id', $tenantId)->whereKey($d['warehouse_id'])->exists(), 422, __('Armazém desconhecido nesta empresa.'));
        }
    }

    private function linha(Import $i): array
    {
        return [
            'id' => $i->id,
            'numero' => $i->import_number,
            'reference' => $i->reference,
            'supplier_id' => $i->supplier_id,
            'fornecedor' => $i->supplier?->name,
            'warehouse_id' => $i->warehouse_id,
            'armazem' => $i->warehouse?->name,
            'order_date' => optional($i->order_date)->toDateString(),
            'expected_arrival_date' => optional($i->expected_arrival_date)->toDateString(),
            'origin_country' => $i->origin_country,
            'origin_port' => $i->origin_port,
            'destination_port' => $i->destination_port,
            'shipping_company' => $i->shipping_company,
            'transport_type' => $i->transport_type,
            'fob_value' => round((float) $i->fob_value, 2),
            'freight_cost' => round((float) $i->freight_cost, 2),
            'insurance_cost' => round((float) $i->insurance_cost, 2),
            'cif_value' => round((float) $i->cif_value, 2),
            'notes' => $i->notes,
            'estado' => $i->status,
            'estado_rotulo' => $i->status_label,
            'estado_cor' => $i->status_color,
        ];
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
