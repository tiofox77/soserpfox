<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop\Mechanic;
use App\Models\Workshop\TimeEntry;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use App\Models\Workshop\WorkOrderItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * O REGISTO DE TEMPOS DE UMA ORDEM (15/09/2026, OF-06).
 *
 * Começar o relógio de um mecânico PÁRA o que ele tiver a correr noutro sítio —
 * ninguém trabalha em dois carros ao mesmo tempo, e esquecer-se de parar é o
 * engano mais comum. Cada linha de serviço mostra as horas vendidas contra as
 * trabalhadas. Ver pede a permissão de ver ordens; mexer, a de editar.
 */
class TemposDaOrdemApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function ordem(int $id): WorkOrder
    {
        return WorkOrder::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    private function registo(WorkOrder $ordem, int $registo): TimeEntry
    {
        return TimeEntry::where('tenant_id', $ordem->tenant_id)->where('work_order_id', $ordem->id)->findOrFail($registo);
    }

    public function index(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');

        return response()->json($this->resposta($request, $this->ordem($id)));
    }

    public function comecar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);

        abort_if(in_array($ordem->status, ['delivered', 'cancelled'], true), 422, __('A ordem está fechada: não se regista trabalho nela.'));

        $dados = $request->validate([
            'mecanico_id' => ['required', 'integer'],
            'linha_id' => ['nullable', 'integer'],
            'notas' => ['nullable', 'string', 'max:255'],
        ], ['mecanico_id.required' => __('Escolha o mecânico.')]);

        $mecanico = Mechanic::where('tenant_id', $ordem->tenant_id)->find($dados['mecanico_id']);
        if (! $mecanico) {
            throw ValidationException::withMessages(['mecanico_id' => [__('Esse mecânico não é desta empresa.')]]);
        }
        if (! empty($dados['linha_id']) && ! WorkOrderItem::where('work_order_id', $ordem->id)->where('type', 'service')->whereKey($dados['linha_id'])->exists()) {
            throw ValidationException::withMessages(['linha_id' => [__('Essa linha de serviço não é desta ordem.')]]);
        }

        $parados = DB::transaction(function () use ($ordem, $mecanico, $dados) {
            $abertos = TimeEntry::with('workOrder:id,order_number')->where('tenant_id', $ordem->tenant_id)->where('mechanic_id', $mecanico->id)->whereNull('ended_at')->lockForUpdate()->get();
            foreach ($abertos as $a) {
                $a->update(['ended_at' => now()]);
            }

            TimeEntry::create([
                'tenant_id' => $ordem->tenant_id,
                'work_order_id' => $ordem->id,
                'work_order_item_id' => ($dados['linha_id'] ?? null) ?: null,
                'mechanic_id' => $mecanico->id,
                'started_at' => now(),
                'notes' => trim((string) ($dados['notas'] ?? '')) ?: null,
                'user_id' => auth()->id(),
            ]);

            if ($ordem->status === 'pending' || $ordem->status === 'scheduled') {
                app(\App\Services\Workshop\OrdensDeServico::class)->aplicarEstado($ordem, 'in_progress');
            }

            return $abertos;
        });

        $mensagem = __(':mecanico começou a trabalhar em :ordem.', ['mecanico' => $mecanico->name, 'ordem' => $ordem->order_number]);
        if ($parados->where('work_order_id', '!=', $ordem->id)->isNotEmpty()) {
            $mensagem .= ' ' . __('Parou o relógio que tinha a correr em :outras.', ['outras' => $parados->where('work_order_id', '!=', $ordem->id)->pluck('workOrder.order_number')->unique()->implode(', ')]);
        }

        return response()->json($this->resposta($request, $ordem->fresh()) + ['message' => $mensagem], 201);
    }

    public function parar(Request $request, int $id, int $registo): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);
        $t = $this->registo($ordem, $registo);

        abort_if($t->ended_at, 422, __('Este relógio já estava parado.'));
        $t->update(['ended_at' => now()]);

        return response()->json($this->resposta($request, $ordem) + ['message' => __(':mecanico parou: :tempo.', ['mecanico' => $t->mechanic?->name, 'tempo' => self::tempo($t->minutes)])]);
    }

    /** Corrigir as horas de um período (o mecânico esqueceu-se de parar). */
    public function corrigir(Request $request, int $id, int $registo): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);
        $t = $this->registo($ordem, $registo);

        $dados = $request->validate([
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date', 'after:inicio'],
            'notas' => ['nullable', 'string', 'max:255'],
        ], ['fim.after' => __('O fim tem de ser depois do início.')]);

        $inicio = Carbon::parse($dados['inicio']);
        $fim = Carbon::parse($dados['fim']);
        abort_if($fim->isFuture(), 422, __('O fim não pode ser no futuro.'));
        abort_if($inicio->diffInHours($fim) > 24, 422, __('Um período não pode passar de 24 horas.'));

        $antes = self::tempo($t->minutosAteAgora());
        $t->update(['started_at' => $inicio, 'ended_at' => $fim, 'notes' => trim((string) ($dados['notas'] ?? $t->notes)) ?: null]);

        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_UPDATED,
            __('Tempo de :mecanico corrigido: :antes → :depois.', ['mecanico' => $t->mechanic?->name, 'antes' => $antes, 'depois' => self::tempo($t->minutes)]));

        return response()->json($this->resposta($request, $ordem) + ['message' => __('Tempo corrigido.')]);
    }

    public function destroy(Request $request, int $id, int $registo): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);
        $t = $this->registo($ordem, $registo);

        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_UPDATED,
            __('Tempo de :mecanico apagado (:tempo).', ['mecanico' => $t->mechanic?->name, 'tempo' => self::tempo($t->minutosAteAgora())]));
        $t->delete();

        return response()->json($this->resposta($request, $ordem) + ['message' => __('Registo de tempo apagado.')]);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    public static function tempo(int $minutos): string
    {
        return sprintf('%dh%02d', intdiv($minutos, 60), $minutos % 60);
    }

    private function resposta(Request $request, WorkOrder $ordem): array
    {
        $registos = TimeEntry::with(['mechanic:id,name', 'item:id,name'])->where('tenant_id', $ordem->tenant_id)->where('work_order_id', $ordem->id)
            ->orderByDesc('started_at')->get();

        $linhas = WorkOrderItem::where('work_order_id', $ordem->id)->where('type', 'service')->where('approval', 'approved')->get(['id', 'name', 'hours', 'quantity']);

        $porLinha = $linhas->map(fn (WorkOrderItem $l) => [
            'id' => $l->id,
            'nome' => $l->name,
            'vendidas' => round((float) $l->hours * max(1, (float) $l->quantity), 2),
            'trabalhadas' => round($registos->where('work_order_item_id', $l->id)->sum(fn ($r) => $r->minutosAteAgora()) / 60, 2),
        ])->values();

        $vendidas = round((float) $porLinha->sum('vendidas'), 2);
        $trabalhadas = round($registos->sum(fn ($r) => $r->minutosAteAgora()) / 60, 2);

        return [
            'registos' => $registos->map(fn (TimeEntry $r) => [
                'id' => $r->id,
                'mecanico_id' => $r->mechanic_id,
                'mecanico' => $r->mechanic?->name,
                'linha_id' => $r->work_order_item_id,
                'linha' => $r->item?->name,
                'inicio' => $r->started_at->format('Y-m-d\TH:i'),
                'fim' => $r->ended_at?->format('Y-m-d\TH:i'),
                'inicio_iso' => $r->started_at->toIso8601String(),
                'minutos' => $r->minutosAteAgora(),
                'a_correr' => $r->ended_at === null,
                'notas' => $r->notes,
            ])->values(),
            'linhas' => $porLinha,
            'contas' => ['vendidas' => $vendidas, 'trabalhadas' => $trabalhadas, 'eficiencia' => $trabalhadas > 0 ? (int) round($vendidas / $trabalhadas * 100) : null],
            'mecanicos' => Mechanic::where('tenant_id', $ordem->tenant_id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($m) => ['valor' => (string) $m->id, 'rotulo' => $m->name])->values(),
            'mecanico_da_ordem' => $ordem->mechanic_id ? (string) $ordem->mechanic_id : null,
            'aberta' => ! in_array($ordem->status, ['delivered', 'cancelled'], true),
            'pode_editar' => (bool) $request->user()?->can('workshop.work-orders.edit'),
        ];
    }
}
