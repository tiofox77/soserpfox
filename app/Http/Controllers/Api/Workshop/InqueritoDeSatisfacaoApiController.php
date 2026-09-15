<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use App\Models\Workshop\WorkOrderSurvey;
use App\Services\Workshop\InqueritosDaOficina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O INQUÉRITO DE SATISFAÇÃO (15/09/2026, OF-14).
 *
 * `ver` e `responder` são da página pública (o link, sem conta, com limite de
 * pedidos); `daOrdem` e `criar` são da oficina, dentro da ordem.
 */
class InqueritoDeSatisfacaoApiController extends Controller
{
    private function pelaChave(string $token): WorkOrderSurvey
    {
        abort_unless(strlen($token) === 48 && ctype_alnum($token), 404);

        $s = WorkOrderSurvey::withoutGlobalScopes()->where('token', $token)->first();
        abort_unless($s, 404);

        return $s;
    }

    public function ver(string $token): JsonResponse
    {
        $s = $this->pelaChave($token);
        $ordem = WorkOrder::withoutGlobalScopes()->with([
            'vehicle' => fn ($q) => $q->withoutGlobalScopes(),
            'mechanic' => fn ($q) => $q->withoutGlobalScopes(),
            'items',
        ])->findOrFail($s->work_order_id);
        $empresa = Tenant::find($s->tenant_id);

        return response()->json([
            'empresa' => ['nome' => $empresa?->name, 'telefone' => $empresa?->phone],
            'ordem' => [
                'numero' => $ordem->order_number,
                'matricula' => $ordem->vehicle?->plate,
                'viatura' => trim(($ordem->vehicle?->brand ?? '') . ' ' . ($ordem->vehicle?->model ?? '')),
                'dono' => $ordem->vehicle?->owner_name,
                'entregue_em' => $ordem->delivered_at?->toIso8601String(),
                // Só o primeiro nome: o cliente sabe quem o atendeu, e a página é pública.
                'mecanico' => $ordem->mechanic?->name ? strtok($ordem->mechanic->name, ' ') : null,
                'servicos' => $ordem->items->where('type', 'service')->where('approval', 'approved')->pluck('name')->values(),
            ],
            'resposta' => $s->answered_at ? [
                'nota' => $s->score,
                'recomenda' => $s->would_recommend,
                'comentario' => $s->comment,
                'em' => $s->answered_at->toIso8601String(),
            ] : null,
        ]);
    }

    public function responder(Request $request, string $token): JsonResponse
    {
        $s = $this->pelaChave($token);
        $dados = $request->validate([
            'nota' => ['required', 'integer', 'min:1', 'max:5'],
            'recomenda' => ['nullable', 'boolean'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ], ['nota.required' => __('Escolha de 1 a 5 estrelas.')]);

        abort_if($s->answered_at, 422, __('Esta avaliação já foi enviada. Obrigado!'));

        $s->update([
            'score' => $dados['nota'],
            'would_recommend' => $dados['recomenda'] ?? null,
            'comment' => trim((string) ($dados['comentario'] ?? '')) ?: null,
            'answered_at' => now(),
        ]);

        WorkOrderHistory::logAction($s->work_order_id, WorkOrderHistory::ACTION_COMMENT,
            trans_choice('O cliente avaliou o serviço com :n estrela.|O cliente avaliou o serviço com :n estrelas.', $s->score, ['n' => $s->score]),
            ['nota' => $s->score, 'recomenda' => $s->would_recommend]);

        return response()->json(['message' => __('Obrigado pela sua avaliação!')]);
    }

    public function daOrdem(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('workshop.work-orders.view'), 403, __('Sem permissão para esta operação.'));
        $ordem = WorkOrder::where('tenant_id', activeTenantId())->findOrFail($id);

        return response()->json([
            'data' => InqueritosDaOficina::paraEcra(WorkOrderSurvey::where('work_order_id', $ordem->id)->first()),
            'pode_criar' => (bool) $request->user()?->can('workshop.work-orders.edit') && in_array($ordem->status, ['completed', 'delivered'], true),
        ]);
    }

    /** O link à mão — para ordens entregues antes do inquérito existir, ou para mandar por WhatsApp já. */
    public function criar(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('workshop.work-orders.edit'), 403, __('Sem permissão para esta operação.'));
        $ordem = WorkOrder::where('tenant_id', activeTenantId())->findOrFail($id);
        abort_unless(in_array($ordem->status, ['completed', 'delivered'], true), 422, __('A avaliação pede-se depois de a ordem estar concluída.'));

        $s = InqueritosDaOficina::criar($ordem);

        return response()->json(['data' => InqueritosDaOficina::paraEcra($s), 'pode_criar' => true, 'message' => __('Link de avaliação pronto.')]);
    }
}
