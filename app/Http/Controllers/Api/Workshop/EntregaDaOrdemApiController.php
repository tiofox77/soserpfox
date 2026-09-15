<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderCheckin;
use App\Models\Workshop\WorkOrderHandover;
use App\Models\Workshop\WorkOrderHistory;
use App\Services\Workshop\OrdensDeServico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A ENTREGA DA VIATURA COM ASSINATURA (15/09/2026, OF-13).
 *
 * O termo de levantamento: km e combustível à saída (ao lado dos da entrada),
 * o que se conferiu com o cliente, quem levantou, e o dinheiro — a factura, o
 * pago e o que falta — à vista de quem entrega. Assinar pode também dar a ordem
 * por Entregue, pela porta única dos estados (stock e controlo de qualidade).
 *
 * Ler pede ver ordens; gravar e assinar, editar ordens.
 */
class EntregaDaOrdemApiController extends Controller
{
    public function __construct(private readonly OrdensDeServico $ordens) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function ordem(int $id): WorkOrder
    {
        return WorkOrder::with(['vehicle', 'invoice'])->where('tenant_id', activeTenantId())->findOrFail($id);
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
        $this->gravar($request, $ordem);

        return response()->json($this->resposta($request, $ordem->fresh(['vehicle', 'invoice'])) + ['message' => __('Entrega gravada.')]);
    }

    /** Assinar (e, se se pedir, dar a ordem por Entregue). */
    public function assinar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);

        $request->validate([
            'assinatura' => ['required', 'string', 'max:400000', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/'],
            'nome' => ['required', 'string', 'max:150'],
            'entregar' => ['nullable', 'boolean'],
        ], [
            'assinatura.regex' => __('A assinatura não chegou como imagem.'),
            'assinatura.required' => __('O cliente tem de assinar no quadro.'),
            'nome.required' => __('Escreva o nome de quem levanta a viatura.'),
        ]);

        if (in_array($ordem->status, ['cancelled'], true)) {
            throw ValidationException::withMessages(['assinatura' => [__('Uma ordem cancelada não se entrega.')]]);
        }

        $entregue = false;

        DB::transaction(function () use ($request, $ordem, &$entregue) {
            $e = $this->gravar($request, $ordem);

            if (! $e->mileage_out) {
                throw ValidationException::withMessages(['km_saida' => [__('Escreva os km à saída antes de assinar.')]]);
            }

            // Primeiro o estado: se o controlo de qualidade ou outra regra não deixa entregar, não fica assinado.
            if ($request->boolean('entregar') && $ordem->status !== 'delivered') {
                try {
                    $this->ordens->aplicarEstado($ordem, 'delivered');
                    $entregue = true;
                } catch (\InvalidArgumentException $ex) {
                    throw ValidationException::withMessages(['entregar' => [$ex->getMessage()]]);
                }
            }

            $contas = self::contas($ordem->fresh('invoice'));
            $e->update([
                'signature' => $request->input('assinatura'),
                'received_by' => trim((string) $request->input('nome')),
                'signed_at' => now(),
                'balance_due' => $contas['falta'],
            ]);
            $e->update(['signed_hash' => $e->conteudoAssinado((float) $ordem->fresh()->total)]);

            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                __(':nome levantou a viatura e assinou o termo de entrega.', ['nome' => $e->received_by]),
                ['km_saida' => $e->mileage_out, 'em_falta' => $contas['falta']]);
        });

        $nome = trim((string) $request->input('nome'));

        return response()->json($this->resposta($request, $ordem->fresh(['vehicle', 'invoice'])) + [
            'message' => $entregue
                ? __('Viatura entregue a :nome, termo assinado.', ['nome' => $nome])
                : __('Termo de entrega assinado por :nome.', ['nome' => $nome]),
        ]);
    }

    public function tirarAssinatura(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);

        $e = WorkOrderHandover::where('work_order_id', $ordem->id)->firstOrFail();
        $e->update(['signature' => null, 'signed_at' => null, 'signed_hash' => null, 'balance_due' => null]);

        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT, __('Assinatura do termo de entrega removida.'));

        return response()->json($this->resposta($request, $ordem) + ['message' => __('Assinatura removida.')]);
    }

    private function gravar(Request $request, WorkOrder $ordem): WorkOrderHandover
    {
        $dados = $request->validate([
            'km_saida' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'combustivel' => ['nullable', 'integer', 'min:0', 'max:8'],
            'conferido' => ['nullable', 'array'],
            'conferido.*' => [Rule::in(array_keys(WorkOrderHandover::CHECKLIST))],
            'nome' => ['nullable', 'string', 'max:150'],
            'documento' => ['nullable', 'string', 'max:40'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        $km = isset($dados['km_saida']) ? (int) $dados['km_saida'] : null;
        if ($km !== null && $km < (int) $ordem->mileage_in) {
            throw ValidationException::withMessages(['km_saida' => [__('Os km à saída não podem ser menos do que à entrada (:km).', ['km' => number_format((int) $ordem->mileage_in, 0, ',', '.')])]]);
        }

        return DB::transaction(function () use ($ordem, $dados, $km) {
            $e = WorkOrderHandover::firstOrNew(['work_order_id' => $ordem->id], ['tenant_id' => $ordem->tenant_id]);
            $novo = ! $e->exists;

            $e->fill([
                'tenant_id' => $ordem->tenant_id,
                'mileage_out' => $km,
                'fuel_level' => $dados['combustivel'] ?? null,
                'checklist' => array_values(array_unique($dados['conferido'] ?? [])),
                'received_by' => trim((string) ($dados['nome'] ?? '')) ?: null,
                'received_by_document' => trim((string) ($dados['documento'] ?? '')) ?: null,
                'notes' => trim((string) ($dados['notas'] ?? '')) ?: null,
                'user_id' => $e->user_id ?? auth()->id(),
            ])->save();

            // A viatura só sobe de km.
            if ($km && $ordem->vehicle && $km > (int) $ordem->vehicle->mileage) {
                $ordem->vehicle->update(['mileage' => $km]);
            }

            if ($novo) {
                WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT, __('Termo de entrega começado.'));
            }

            return $e;
        });
    }

    /** O dinheiro da ordem à hora de entregar. */
    public static function contas(WorkOrder $ordem): array
    {
        $f = $ordem->invoice;
        $anulada = $f && in_array($f->status, ['cancelled', 'canceled'], true);

        if (! $f || $anulada) {
            return [
                'total' => round((float) $ordem->total, 2),
                'pago' => 0.0,
                'falta' => round((float) $ordem->total, 2),
                'factura' => null,
            ];
        }

        $total = round((float) $f->total, 2);
        $pago = round((float) $f->paid_amount, 2);

        return [
            'total' => $total,
            'pago' => $pago,
            'falta' => max(0, round($total - $pago, 2)),
            'factura' => [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'estado_rotulo' => $f->status_label,
                'morada' => route('invoicing.sales.invoices.preview', $f->id),
            ],
        ];
    }

    public static function paraEcra(WorkOrder $ordem, ?WorkOrderHandover $e): array
    {
        return [
            'existe' => (bool) $e,
            'km_saida' => $e?->mileage_out,
            'combustivel' => $e?->fuel_level,
            'conferido' => array_values($e?->checklist ?? []),
            'nome' => $e?->received_by,
            'documento' => $e?->received_by_document,
            'notas' => $e?->notes,
            'assinatura' => $e?->signature,
            'assinado_em' => $e?->signed_at?->toIso8601String(),
            'falta_ao_assinar' => $e?->balance_due !== null ? (float) $e->balance_due : null,
            'assinatura_valida' => $e && $e->signature && hash_equals((string) $e->signed_hash, $e->conteudoAssinado((float) $ordem->total)),
            'registado_por' => $e?->user?->name,
        ];
    }

    private function resposta(Request $request, WorkOrder $ordem): array
    {
        $e = WorkOrderHandover::with('user:id,name')->where('work_order_id', $ordem->id)->first();
        $checkin = WorkOrderCheckin::where('work_order_id', $ordem->id)->first();

        return [
            'data' => self::paraEcra($ordem, $e),
            'entrada' => [
                'km' => (int) $ordem->mileage_in,
                'combustivel' => $checkin?->fuel_level,
                'chaves' => $checkin?->keys_count,
                'objectos' => $checkin?->belongings,
                'em' => $ordem->received_at?->toIso8601String(),
            ],
            'contas' => self::contas($ordem),
            'ordem' => [
                'estado' => $ordem->status,
                'estado_rotulo' => __(OrdensDeServico::ESTADOS[$ordem->status] ?? $ordem->status),
                'dono' => $ordem->vehicle?->owner_name,
                'entregue_em' => $ordem->delivered_at?->toIso8601String(),
            ],
            'listas' => WorkOrderHandover::listas(),
            'pode_editar' => (bool) $request->user()?->can('workshop.work-orders.edit'),
        ];
    }
}
