<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderClaim;
use App\Models\Workshop\WorkOrderHistory;
use App\Services\Workshop\SinistrosDaOficina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O SINISTRO DA ORDEM (15/09/2026, OF-15).
 *
 * Ler pede ver ordens; gravar e tirar, editar ordens. Depois de facturada, a
 * seguradora e a franquia já não mudam (estão nas facturas); o processo, o
 * perito e o estado continuam a poder mudar.
 */
class SinistroDaOrdemApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function ordem(int $id): WorkOrder
    {
        return WorkOrder::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');

        return response()->json($this->resposta($request, $this->ordem($id)));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);
        $dados = $request->validate([
            'seguradora_id' => ['nullable', 'integer'],
            'processo' => ['nullable', 'string', 'max:60'],
            'apolice' => ['nullable', 'string', 'max:60'],
            'data_sinistro' => ['nullable', 'date', 'before_or_equal:today'],
            'perito' => ['nullable', 'string', 'max:150'],
            'perito_telefone' => ['nullable', 'string', 'max:30'],
            'perito_email' => ['nullable', 'email', 'max:150'],
            'data_peritagem' => ['nullable', 'date'],
            'valor_aprovado' => ['nullable', 'numeric', 'min:0'],
            'franquia' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', Rule::in(array_keys(WorkOrderClaim::ESTADOS))],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! empty($dados['seguradora_id']) && ! Client::where('tenant_id', $ordem->tenant_id)->whereKey($dados['seguradora_id'])->exists()) {
            throw ValidationException::withMessages(['seguradora_id' => [__('Essa seguradora não é cliente desta empresa.')]]);
        }

        $s = WorkOrderClaim::firstOrNew(['work_order_id' => $ordem->id], ['tenant_id' => $ordem->tenant_id, 'user_id' => auth()->id()]);
        $novo = ! $s->exists;
        $franquia = round((float) ($dados['franquia'] ?? 0), 2);

        // Facturada: a seguradora e a franquia estão nas facturas e não se mexem.
        if ($ordem->invoice_id && (($s->insurer_client_id ?? null) != ($dados['seguradora_id'] ?? null) || round((float) $s->excess_amount, 2) !== $franquia)) {
            throw ValidationException::withMessages(['franquia' => [__('A ordem já foi facturada: a seguradora e a franquia não podem mudar.')]]);
        }

        $s->fill([
            'tenant_id' => $ordem->tenant_id,
            'insurer_client_id' => $dados['seguradora_id'] ?? null,
            'claim_number' => trim((string) ($dados['processo'] ?? '')) ?: null,
            'policy_number' => trim((string) ($dados['apolice'] ?? '')) ?: null,
            'accident_date' => $dados['data_sinistro'] ?? null,
            'adjuster_name' => trim((string) ($dados['perito'] ?? '')) ?: null,
            'adjuster_phone' => trim((string) ($dados['perito_telefone'] ?? '')) ?: null,
            'adjuster_email' => trim((string) ($dados['perito_email'] ?? '')) ?: null,
            'inspection_date' => $dados['data_peritagem'] ?? null,
            'approved_amount' => $dados['valor_aprovado'] ?? null,
            'excess_amount' => $franquia,
            'status' => $dados['estado'],
            'notes' => trim((string) ($dados['notas'] ?? '')) ?: null,
        ]);
        $mudouEstado = $s->isDirty('status') && ! $novo;
        $s->save();

        if ($novo) {
            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT, __('Ordem marcada como sinistro (processo :processo).', ['processo' => $s->claim_number ?: '—']));
        } elseif ($mudouEstado) {
            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT, __('Sinistro: :estado.', ['estado' => __(WorkOrderClaim::ESTADOS[$s->status])]));
        }

        return response()->json($this->resposta($request, $ordem) + ['message' => __('Sinistro gravado.')]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);
        abort_if($ordem->invoice_id, 422, __('A ordem já foi facturada como sinistro: o sinistro não se tira.'));

        WorkOrderClaim::where('work_order_id', $ordem->id)->delete();
        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT, __('A ordem deixou de ser um sinistro.'));

        return response()->json($this->resposta($request, $ordem) + ['message' => __('Sinistro retirado da ordem.')]);
    }

    private function resposta(Request $request, WorkOrder $ordem): array
    {
        $s = WorkOrderClaim::with(['insurer', 'excessInvoice.client'])->where('work_order_id', $ordem->id)->first();
        $franquia = round((float) ($s?->excess_amount ?? 0), 2);
        $total = round((float) $ordem->total, 2);

        return [
            'data' => SinistrosDaOficina::paraEcra($s),
            // A conta à vista (o IVA e os descontos acertam-se ao facturar).
            'reparticao' => [
                'total' => $total,
                'seguradora' => round(max(0, $total - $franquia), 2),
                'cliente' => $franquia,
                'acima_do_aprovado' => $s && $s->approved_amount !== null && $total - $franquia > (float) $s->approved_amount + 0.005,
            ],
            // As seguradoras são clientes da casa — as pessoas colectivas primeiro.
            'seguradoras' => Client::where('tenant_id', $ordem->tenant_id)->orderByRaw("type = 'pessoa_juridica' DESC")->orderBy('name')->limit(500)
                ->get(['id', 'name', 'nif', 'type'])->map(fn (Client $c) => ['valor' => (string) $c->id, 'rotulo' => $c->name . ($c->nif ? " · {$c->nif}" : ''), 'empresa' => $c->type === 'pessoa_juridica'])->values(),
            'estados' => collect(WorkOrderClaim::ESTADOS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'facturada' => (bool) $ordem->invoice_id,
            'pode_editar' => (bool) $request->user()?->can('workshop.work-orders.edit'),
        ];
    }
}
