<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Restaurant\VenueLimitRequest;
use App\Services\Restaurant\RestaurantVenueLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OS PEDIDOS DE MAIS ESTABELECIMENTOS DO RESTAURANTE.
 *
 * Quem decide é o RestaurantVenueLimitService, o mesmo de sempre: tranca o
 * pedido, recusa um que já foi analisado e recusa aprovar um limite que não
 * aumenta a quota. O ecrã só mostra e pergunta.
 *
 * O pedido é de uma empresa e o modelo tem escopo de empresa: sem o
 * `withoutGlobalScopes` o dono da plataforma via só os pedidos da empresa onde
 * por acaso estivesse.
 */
class PedidosDeEstabelecimentosApiController extends Controller
{
    private const ESTADOS = ['pending', 'approved', 'rejected'];

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'estado' => ['nullable', 'in:' . implode(',', self::ESTADOS)],
            'procura' => ['nullable', 'string', 'max:120'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $termo = trim((string) ($f['procura'] ?? ''));
        $estado = $f['estado'] ?? 'pending';

        $pagina = VenueLimitRequest::withoutGlobalScopes()
            ->with(['tenant', 'requester:id,name', 'reviewer:id,name'])
            ->where('status', $estado)
            ->when($termo !== '', fn ($q) => $q->whereHas('tenant', fn ($t) => $t
                ->where(fn ($w) => $w->where('name', 'like', "%{$termo}%")->orWhere('company_name', 'like', "%{$termo}%"))))
            ->latest()
            ->paginate(15, ['*'], 'pagina', $f['pagina'] ?? 1);

        $contagens = VenueLimitRequest::withoutGlobalScopes()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'pedidos' => collect($pagina->items())->map(fn (VenueLimitRequest $p) => [
                'id' => $p->id,
                'empresa' => $p->tenant?->company_name ?: $p->tenant?->name,
                'quota_actual_da_empresa' => $p->tenant ? max(1, (int) $p->tenant->restaurant_venue_limit) : null,
                'pedido_por' => $p->requester?->name,
                'pedido_em' => $p->created_at?->toIso8601String(),
                'quota' => (int) $p->current_limit,
                'pedido' => (int) $p->requested_limit,
                'motivo' => $p->reason,
                'estado' => $p->status,
                'nota' => $p->admin_notes,
                'analisado_por' => $p->reviewer?->name,
                'analisado_em' => $p->reviewed_at?->toIso8601String(),
            ]),
            'contagens' => collect(self::ESTADOS)->mapWithKeys(fn ($e) => [$e => (int) ($contagens[$e] ?? 0)]),
            'paginacao' => ['pagina' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function aprovar(Request $request, int $id, RestaurantVenueLimitService $servico): JsonResponse
    {
        $dados = $request->validate([
            'limite' => ['required', 'integer', 'min:2', 'max:100'],
            'nota' => ['nullable', 'string', 'max:1000'],
        ]);

        $servico->review($id, (int) auth()->id(), true, (int) $dados['limite'], $dados['nota'] ?? null);

        return response()->json(['message' => __('Quota aprovada e atualizada para a empresa.')]);
    }

    public function recusar(Request $request, int $id, RestaurantVenueLimitService $servico): JsonResponse
    {
        $dados = $request->validate([
            'nota' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $servico->review($id, (int) auth()->id(), false, null, $dados['nota']);

        return response()->json(['message' => __('Pedido recusado.')]);
    }
}
