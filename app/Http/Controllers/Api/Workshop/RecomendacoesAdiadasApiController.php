<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop\DeferredItem;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use App\Services\Workshop\OrdensDeServico;
use App\Services\Workshop\RecomendacoesAdiadas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * AS RECOMENDAÇÕES ADIADAS (15/09/2026, OF-12).
 *
 * Ver pede ver ordens; adiar, juntar, descartar e mudar a data pedem editar
 * ordens.
 */
class RecomendacoesAdiadasApiController extends Controller
{
    public function __construct(private readonly OrdensDeServico $ordens) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function ordem(int $id): WorkOrder
    {
        return WorkOrder::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    private function recomendacao(int $id): DeferredItem
    {
        return DeferredItem::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    /** A lista de todas, agrupada por viatura no ecrã. */
    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');
        $tenantId = activeTenantId();
        $dados = $request->validate([
            'estado' => ['nullable', Rule::in(array_merge(array_keys(DeferredItem::ESTADOS), ['todas']))],
            'origem' => ['nullable', Rule::in(array_keys(DeferredItem::ORIGENS))],
            'q' => ['nullable', 'string', 'max:100'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);
        $estado = $dados['estado'] ?? 'pendente';

        $consulta = DeferredItem::where('tenant_id', $tenantId)
            ->when($estado !== 'todas', fn ($q) => $q->where('status', $estado))
            ->when($dados['origem'] ?? null, fn ($q, $o) => $q->where('origin', $o))
            ->when(trim((string) ($dados['q'] ?? '')), function ($q, $termo) {
                $q->where(fn ($w) => $w->where('name', 'like', "%{$termo}%")
                    ->orWhereIn('vehicle_id', Vehicle::where('tenant_id', activeTenantId())
                        ->where(fn ($v) => $v->where('plate', 'like', "%{$termo}%")->orWhere('owner_name', 'like', "%{$termo}%")->orWhere('owner_phone', 'like', "%{$termo}%"))
                        ->select('id')));
            });

        // Paginam-se VIATURAS, não linhas: uma viatura não se parte em duas páginas.
        $viaturas = (clone $consulta)->selectRaw('vehicle_id, MIN(follow_up_on) AS primeira')
            ->groupBy('vehicle_id')->orderByRaw('primeira IS NULL, primeira')
            ->paginate(10, ['*'], 'pagina', $dados['pagina'] ?? 1);
        $ids = collect($viaturas->items())->pluck('vehicle_id')->all();

        $itens = (clone $consulta)->whereIn('vehicle_id', $ids)->with(['workOrder:id,order_number', 'resolvedWorkOrder:id,order_number'])
            ->orderByRaw("CASE severity WHEN 'urgente' THEN 0 WHEN 'atencao' THEN 1 ELSE 2 END")->orderBy('follow_up_on')->get()->groupBy('vehicle_id');
        $fichas = Vehicle::where('tenant_id', $tenantId)->whereIn('id', $ids)->get()->keyBy('id');

        $pendentes = DeferredItem::where('tenant_id', $tenantId)->where('status', 'pendente');

        return response()->json([
            'data' => collect($ids)->filter(fn ($id) => isset($fichas[$id]))->map(fn ($id) => [
                'viatura_id' => $id,
                'matricula' => $fichas[$id]->plate,
                'marca_modelo' => trim("{$fichas[$id]->brand} {$fichas[$id]->model}"),
                'dono' => $fichas[$id]->owner_name,
                'telefone' => $fichas[$id]->owner_phone,
                'itens' => $itens[$id]->map(fn (DeferredItem $r) => RecomendacoesAdiadas::paraEcra($r))->values(),
                'valor' => round($itens[$id]->where('status', 'pendente')->sum(fn (DeferredItem $r) => $r->valor()), 2),
            ])->values(),
            'paginacao' => ['pagina' => $viaturas->currentPage(), 'ultima' => $viaturas->lastPage(), 'total' => $viaturas->total(), 'de' => $viaturas->firstItem(), 'ate' => $viaturas->lastItem()],
            'contas' => [
                'pendentes' => (clone $pendentes)->count(),
                'valor_pendente' => round((float) (clone $pendentes)->selectRaw('SUM(quantity * unit_price) AS v')->value('v'), 2),
                'urgentes' => (clone $pendentes)->where('severity', 'urgente')->count(),
                'para_propor' => (clone $pendentes)->whereDate('follow_up_on', '<=', today())->count(),
                'aceites_90_dias' => DeferredItem::where('tenant_id', $tenantId)->where('status', 'aceite')->where('resolved_at', '>=', now()->subDays(90))->count(),
            ],
            'origens' => collect(DeferredItem::ORIGENS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'pode_gerir' => (bool) $request->user()?->can('workshop.work-orders.edit'),
        ]);
    }

    /** As que esperam na viatura desta ordem (menos as que nasceram nela). */
    public function daOrdem(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');
        $ordem = $this->ordem($id);

        return response()->json([
            'data' => $ordem->vehicle_id
                ? RecomendacoesAdiadas::pendentes($ordem->tenant_id, $ordem->vehicle_id)
                    ->reject(fn (DeferredItem $r) => $r->work_order_id === $ordem->id)
                    ->map(fn (DeferredItem $r) => RecomendacoesAdiadas::paraEcra($r))->values()
                : [],
        ]);
    }

    public function juntar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);
        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'precisa_aprovacao' => ['nullable', 'boolean'],
        ]);

        abort_if(in_array($ordem->status, ['delivered', 'cancelled'], true), 422, __('Esta ordem já está fechada.'));

        $n = RecomendacoesAdiadas::juntar($this->ordens, $ordem, $dados['ids'], (bool) ($dados['precisa_aprovacao'] ?? false), $request->user()?->id);
        abort_unless($n > 0, 422, __('Essas recomendações já não estão por propor.'));

        return response()->json(['message' => trans_choice(':n recomendação juntada à ordem.|:n recomendações juntadas à ordem.', $n, ['n' => $n])]);
    }

    /** Adiar uma linha: sai da ordem e fica para outra visita. */
    public function adiarLinha(Request $request, int $id, int $linha): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);
        $item = WorkOrderItem::where('work_order_id', $ordem->id)->findOrFail($linha);
        $dados = $request->validate([
            'voltar_em' => ['nullable', 'date', 'after_or_equal:today'],
            'nota' => ['nullable', 'string', 'max:500'],
        ]);

        abort_unless($ordem->vehicle_id, 422, __('Esta ordem não tem viatura.'));
        abort_if($item->invoice_id, 422, __('Esta linha já foi facturada.'));

        $r = RecomendacoesAdiadas::adiarLinha($this->ordens, $ordem, $item,
            ! empty($dados['voltar_em']) ? Carbon::parse($dados['voltar_em']) : null, $dados['nota'] ?? null, $request->user()?->id);

        return response()->json(['message' => __('«:nome» adiado: volta a ser proposto a :data.', ['nome' => $r->name, 'data' => $r->follow_up_on?->format('d/m/Y')])]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $r = $this->recomendacao($id);
        $dados = $request->validate([
            'voltar_em' => ['nullable', 'date'],
            'preco' => ['nullable', 'numeric', 'min:0'],
            'nota' => ['nullable', 'string', 'max:500'],
        ]);

        $r->update([
            'follow_up_on' => $dados['voltar_em'] ?? null,
            'unit_price' => $dados['preco'] ?? $r->unit_price,
            'note' => $dados['nota'] ?? null,
        ]);

        return response()->json(['message' => __('Recomendação «:nome» actualizada.', ['nome' => $r->name])]);
    }

    public function descartar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $r = $this->recomendacao($id);
        $nota = $request->validate(['nota' => ['nullable', 'string', 'max:500']])['nota'] ?? null;

        abort_unless($r->status === 'pendente', 422, __('Só se descarta uma recomendação por propor.'));
        $r->update(['status' => 'descartada', 'resolved_at' => now(), 'resolved_by' => $request->user()?->id, 'note' => $nota ?: $r->note]);

        return response()->json(['message' => __('Recomendação «:nome» descartada.', ['nome' => $r->name])]);
    }

    public function reabrir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $r = $this->recomendacao($id);

        abort_unless($r->status === 'descartada', 422, __('Só se reabre uma recomendação descartada.'));
        $r->update(['status' => 'pendente', 'resolved_at' => null, 'resolved_by' => null, 'follow_up_on' => $r->follow_up_on ?? today()]);

        return response()->json(['message' => __('Recomendação «:nome» voltou a estar por propor.', ['nome' => $r->name])]);
    }
}
