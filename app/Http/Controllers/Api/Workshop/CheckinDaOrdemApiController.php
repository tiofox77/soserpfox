<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderCheckin;
use App\Models\Workshop\WorkOrderHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O CHECK-IN DA VIATURA — como o carro chegou à oficina (15/09/2026, OF-01).
 *
 * Lê-se com a permissão de ver ordens; grava-se e assina-se com a de editar.
 * Os km à entrada são os da própria ordem (`mileage_in`), e a viatura sobe de
 * km quando o check-in diz mais do que ela sabia (nunca desce).
 *
 * A ASSINATURA fica agarrada ao que se assinou: guarda-se a impressão do
 * conteúdo, e mudar os danos, os acessórios, o combustível ou os km depois
 * deixa-a sem valor — o ecrã diz que foi alterado e pede nova assinatura.
 */
class CheckinDaOrdemApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function ordem(int $id): WorkOrder
    {
        return WorkOrder::with('vehicle')->where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');
        $ordem = $this->ordem($id);

        return response()->json($this->resposta($request, $ordem));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);

        $dados = $request->validate([
            'km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'combustivel' => ['nullable', 'integer', 'min:0', 'max:8'],
            'danos' => ['nullable', 'array', 'max:60'],
            'danos.*.x' => ['required', 'numeric', 'min:0', 'max:100'],
            'danos.*.y' => ['required', 'numeric', 'min:0', 'max:100'],
            'danos.*.tipo' => ['required', Rule::in(array_keys(WorkOrderCheckin::TIPOS_DE_DANO))],
            'danos.*.nota' => ['nullable', 'string', 'max:200'],
            'acessorios' => ['nullable', 'array'],
            'acessorios.*' => [Rule::in(array_keys(WorkOrderCheckin::ACESSORIOS))],
            'luzes' => ['nullable', 'array'],
            'luzes.*' => [Rule::in(array_keys(WorkOrderCheckin::LUZES))],
            'chaves' => ['nullable', 'integer', 'min:0', 'max:20'],
            'objectos' => ['nullable', 'string', 'max:2000'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($ordem, $dados) {
            $km = (int) ($dados['km'] ?? $ordem->mileage_in);

            if ($km !== (int) $ordem->mileage_in) {
                $ordem->update(['mileage_in' => $km]);
            }
            // A viatura só sobe de km: um check-in escrito à pressa não lhe tira quilómetros.
            if ($ordem->vehicle && $km > (int) $ordem->vehicle->mileage) {
                $ordem->vehicle->update(['mileage' => $km]);
            }

            $checkin = WorkOrderCheckin::firstOrNew(['work_order_id' => $ordem->id], ['tenant_id' => $ordem->tenant_id]);
            $novo = ! $checkin->exists;

            $checkin->fill([
                'tenant_id' => $ordem->tenant_id,
                'fuel_level' => $dados['combustivel'] ?? null,
                'damages' => collect($dados['danos'] ?? [])->map(fn ($d) => [
                    'x' => round((float) $d['x'], 2),
                    'y' => round((float) $d['y'], 2),
                    'tipo' => $d['tipo'],
                    'nota' => trim((string) ($d['nota'] ?? '')) ?: null,
                ])->values()->all(),
                'accessories' => array_values(array_unique($dados['acessorios'] ?? [])),
                'warning_lights' => array_values(array_unique($dados['luzes'] ?? [])),
                'keys_count' => $dados['chaves'] ?? null,
                'belongings' => trim((string) ($dados['objectos'] ?? '')) ?: null,
                'notes' => trim((string) ($dados['notas'] ?? '')) ?: null,
                'user_id' => $checkin->user_id ?? auth()->id(),
            ])->save();

            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                $novo ? __('Check-in da viatura registado.') : __('Check-in da viatura actualizado.'),
                ['danos' => count($checkin->damages ?? []), 'combustivel' => $checkin->fuel_level]);
        });

        return response()->json($this->resposta($request, $ordem->fresh('vehicle')) + ['message' => __('Check-in gravado.')]);
    }

    public function assinar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);

        $dados = $request->validate([
            'assinatura' => ['required', 'string', 'max:400000', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/'],
            'nome' => ['required', 'string', 'max:150'],
        ], [
            'assinatura.regex' => __('A assinatura não chegou como imagem.'),
            'assinatura.required' => __('O cliente tem de assinar no quadro.'),
            'nome.required' => __('Escreva o nome de quem assina.'),
        ]);

        $checkin = WorkOrderCheckin::where('work_order_id', $ordem->id)->first();

        if (! $checkin) {
            throw ValidationException::withMessages(['assinatura' => [__('Grave o check-in antes de pedir a assinatura.')]]);
        }

        $checkin->update([
            'signature' => $dados['assinatura'],
            'signed_by' => trim($dados['nome']),
            'signed_at' => now(),
            'signed_hash' => $checkin->conteudoAssinado((int) $ordem->mileage_in),
        ]);

        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
            __(':nome assinou o check-in da viatura.', ['nome' => trim($dados['nome'])]));

        return response()->json($this->resposta($request, $ordem) + ['message' => __('Check-in assinado por :nome.', ['nome' => trim($dados['nome'])])]);
    }

    public function tirarAssinatura(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);

        $checkin = WorkOrderCheckin::where('work_order_id', $ordem->id)->firstOrFail();
        $checkin->update(['signature' => null, 'signed_by' => null, 'signed_at' => null, 'signed_hash' => null]);

        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT, __('Assinatura do check-in removida.'));

        return response()->json($this->resposta($request, $ordem) + ['message' => __('Assinatura removida.')]);
    }

    /** O check-in como o ecrã (e o papel) o lêem. */
    public static function paraEcra(WorkOrder $ordem, ?WorkOrderCheckin $c): array
    {
        $km = (int) $ordem->mileage_in;

        return [
            'existe' => (bool) $c,
            'km' => $km,
            'combustivel' => $c?->fuel_level,
            'danos' => array_values($c?->damages ?? []),
            'acessorios' => array_values($c?->accessories ?? []),
            'luzes' => array_values($c?->warning_lights ?? []),
            'chaves' => $c?->keys_count,
            'objectos' => $c?->belongings,
            'notas' => $c?->notes,
            'assinatura' => $c?->signature,
            'assinado_por' => $c?->signed_by,
            'assinado_em' => $c?->signed_at?->toIso8601String(),
            // A assinatura só vale para o que se assinou.
            'assinatura_valida' => $c && $c->signature && hash_equals((string) $c->signed_hash, $c->conteudoAssinado($km)),
            'registado_por' => $c?->user?->name,
            'actualizado_em' => $c?->updated_at?->toIso8601String(),
        ];
    }

    private function resposta(Request $request, WorkOrder $ordem): array
    {
        $checkin = WorkOrderCheckin::with('user:id,name')->where('work_order_id', $ordem->id)->first();

        return [
            'data' => self::paraEcra($ordem, $checkin),
            'listas' => WorkOrderCheckin::listas(),
            'pode_editar' => (bool) $request->user()?->can('workshop.work-orders.edit'),
        ];
    }
}
