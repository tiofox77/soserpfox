<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workshop\Service;
use App\Models\Workshop\ServicePackage;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use App\Models\Workshop\WorkOrderItem;
use App\Services\Workshop\OrdensDeServico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS PACOTES DE SERVIÇO (15/09/2026, OF-07).
 *
 * Montam-se no ecrã dos pacotes (ou guardam-se a partir de uma ordem já feita)
 * e juntam-se a uma ordem de uma vez: cada linha entra pela mesma porta das
 * linhas soltas (`OrdensDeServico::juntarLinha`), com o stock e os totais de
 * sempre — e, se se pedir, à espera da aprovação do cliente.
 *
 * Gerir pacotes pede a permissão de editar serviços; pô-los numa ordem, a de
 * editar ordens.
 */
class PacotesDeServicoApiController extends Controller
{
    public function __construct(private readonly OrdensDeServico $ordens) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function pacote(int $id): ServicePackage
    {
        return ServicePackage::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');
        $tenantId = activeTenantId();

        return response()->json([
            'data' => ServicePackage::where('tenant_id', $tenantId)->orderByDesc('is_active')->orderBy('name')->get()
                ->map(fn (ServicePackage $p) => self::paraEcra($p))->values(),
            'pode_gerir' => (bool) $request->user()?->can('workshop.services.edit'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.services.edit');
        $dados = $this->validar($request, null);

        $p = ServicePackage::create($dados + ['tenant_id' => activeTenantId()]);

        return response()->json(['data' => self::paraEcra($p), 'message' => __('Pacote «:nome» criado.', ['nome' => $p->name])], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.services.edit');
        $p = $this->pacote($id);
        $p->update($this->validar($request, $p));

        return response()->json(['data' => self::paraEcra($p->fresh()), 'message' => __('Pacote «:nome» actualizado.', ['nome' => $p->name])]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.services.edit');
        $p = $this->pacote($id);
        $nome = $p->name;
        $p->delete();

        return response()->json(['message' => __('Pacote «:nome» apagado.', ['nome' => $nome])]);
    }

    /** Juntar um pacote a uma ordem — linha a linha, pela porta de sempre. */
    public function juntar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = WorkOrder::where('tenant_id', activeTenantId())->findOrFail($id);

        abort_if($ordem->invoice_id, 422, __('A ordem já foi facturada: não se juntam linhas.'));
        abort_if(in_array($ordem->status, ['delivered', 'cancelled'], true), 422, __('A ordem está fechada.'));

        $dados = $request->validate(['pacote_id' => ['required', 'integer'], 'precisa_aprovacao' => ['nullable', 'boolean']]);
        $p = ServicePackage::where('tenant_id', $ordem->tenant_id)->where('is_active', true)->find($dados['pacote_id']);

        if (! $p) {
            throw ValidationException::withMessages(['pacote_id' => [__('Esse pacote não existe ou está desactivado.')]]);
        }

        DB::transaction(function () use ($ordem, $p, $dados) {
            foreach ($p->lines as $l) {
                // Um serviço ou artigo apagado do catálogo depois de o pacote nascer entra como linha escrita.
                $servico = ! empty($l['service_id']) && Service::withoutGlobalScopes()->where('tenant_id', $ordem->tenant_id)->whereKey($l['service_id'])->exists() ? $l['service_id'] : null;
                $artigo = ! empty($l['product_id']) && Product::withoutGlobalScopes()->where('tenant_id', $ordem->tenant_id)->whereKey($l['product_id'])->exists() ? $l['product_id'] : null;

                $this->ordens->juntarLinha($ordem, [
                    'type' => $l['tipo'],
                    'service_id' => $servico,
                    'product_id' => $artigo,
                    'code' => $l['codigo'] ?? null,
                    'name' => $l['nome'],
                    'description' => __('Pacote: :nome', ['nome' => $p->name]),
                    'quantity' => (float) $l['quantidade'],
                    'unit_price' => (float) $l['preco'],
                    'discount_percent' => (float) ($l['desconto'] ?? 0),
                    'hours' => (float) ($l['horas'] ?? 0),
                    'precisa_aprovacao' => ! empty($dados['precisa_aprovacao']),
                ]);
            }

            $p->increment('times_used');
            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_ITEM_ADDED,
                trans_choice('Pacote «:nome» junto (:n linha).|Pacote «:nome» junto (:n linhas).', count($p->lines), ['nome' => $p->name, 'n' => count($p->lines)]));
        });

        return response()->json(['message' => trans_choice('Pacote «:nome»: :n linha juntada.|Pacote «:nome»: :n linhas juntadas.', count($p->lines), ['nome' => $p->name, 'n' => count($p->lines)])]);
    }

    /** Guardar as linhas aprovadas de uma ordem como um pacote novo. */
    public function guardarDaOrdem(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.services.edit');
        $ordem = WorkOrder::where('tenant_id', activeTenantId())->findOrFail($id);

        $dados = $request->validate(['nome' => ['required', 'string', 'max:150']], ['nome.required' => __('Dê um nome ao pacote.')]);

        $linhas = WorkOrderItem::where('work_order_id', $ordem->id)->where('approval', 'approved')->get();
        if ($linhas->isEmpty()) {
            throw ValidationException::withMessages(['nome' => [__('A ordem não tem linhas aprovadas para guardar.')]]);
        }
        if (ServicePackage::where('tenant_id', $ordem->tenant_id)->where('name', trim($dados['nome']))->exists()) {
            throw ValidationException::withMessages(['nome' => [__('Já existe um pacote com esse nome.')]]);
        }

        $p = ServicePackage::create([
            'tenant_id' => $ordem->tenant_id,
            'name' => trim($dados['nome']),
            'description' => __('Guardado a partir da ordem :numero.', ['numero' => $ordem->order_number]),
            'lines' => $linhas->map(fn (WorkOrderItem $l) => [
                'tipo' => $l->type, 'service_id' => $l->service_id, 'product_id' => $l->product_id, 'codigo' => $l->code,
                'nome' => $l->name, 'quantidade' => (float) $l->quantity, 'preco' => (float) $l->unit_price,
                'desconto' => (float) $l->discount_percent, 'horas' => (float) $l->hours,
            ])->values()->all(),
            'is_active' => true,
        ]);

        return response()->json(['data' => self::paraEcra($p), 'message' => __('Pacote «:nome» criado.', ['nome' => $p->name])], 201);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function validar(Request $request, ?ServicePackage $actual): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'descricao' => ['nullable', 'string', 'max:500'],
            'activo' => ['nullable', 'boolean'],
            'linhas' => ['required', 'array', 'min:1', 'max:60'],
            'linhas.*.tipo' => ['required', Rule::in(['service', 'part'])],
            'linhas.*.service_id' => ['nullable', 'integer'],
            'linhas.*.product_id' => ['nullable', 'integer'],
            'linhas.*.codigo' => ['nullable', 'string', 'max:100'],
            'linhas.*.nome' => ['required', 'string', 'max:255'],
            'linhas.*.quantidade' => ['required', 'numeric', 'min:0.01'],
            'linhas.*.preco' => ['required', 'numeric', 'min:0'],
            'linhas.*.desconto' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'linhas.*.horas' => ['nullable', 'numeric', 'min:0'],
        ], [
            'linhas.required' => __('Junte pelo menos uma linha ao pacote.'),
            'linhas.min' => __('Junte pelo menos uma linha ao pacote.'),
        ]);

        $tenantId = activeTenantId();

        if (ServicePackage::where('tenant_id', $tenantId)->where('name', trim($dados['nome']))->when($actual, fn ($q) => $q->whereKeyNot($actual->id))->exists()) {
            throw ValidationException::withMessages(['nome' => [__('Já existe um pacote com esse nome.')]]);
        }

        // Os ids do catálogo vêm do browser: têm de ser desta empresa.
        foreach ($dados['linhas'] as $n => $l) {
            if (! empty($l['service_id']) && ! Service::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($l['service_id'])->exists()) {
                throw ValidationException::withMessages(["linhas.$n.service_id" => [__('Esse serviço não é desta empresa.')]]);
            }
            if (! empty($l['product_id']) && ! Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($l['product_id'])->exists()) {
                throw ValidationException::withMessages(["linhas.$n.product_id" => [__('Esse artigo não é desta empresa.')]]);
            }
        }

        return [
            'name' => trim($dados['nome']),
            'description' => trim((string) ($dados['descricao'] ?? '')) ?: null,
            'is_active' => (bool) ($dados['activo'] ?? true),
            'lines' => collect($dados['linhas'])->map(fn ($l) => [
                'tipo' => $l['tipo'],
                'service_id' => $l['tipo'] === 'service' ? (($l['service_id'] ?? null) ?: null) : null,
                'product_id' => $l['tipo'] === 'part' ? (($l['product_id'] ?? null) ?: null) : null,
                'codigo' => ($l['codigo'] ?? null) ?: null,
                'nome' => trim($l['nome']),
                'quantidade' => round((float) $l['quantidade'], 2),
                'preco' => round((float) $l['preco'], 2),
                'desconto' => round((float) ($l['desconto'] ?? 0), 2),
                'horas' => $l['tipo'] === 'service' ? round((float) ($l['horas'] ?? 0), 2) : 0,
            ])->values()->all(),
        ];
    }

    public static function paraEcra(ServicePackage $p): array
    {
        return [
            'id' => $p->id,
            'nome' => $p->name,
            'descricao' => $p->description,
            'linhas' => array_values($p->lines ?? []),
            'total' => (float) $p->total,
            'horas' => (float) $p->hours,
            'usado' => $p->times_used,
            'activo' => $p->is_active,
        ];
    }
}
