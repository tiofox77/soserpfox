<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop\InspectionTemplate;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use App\Models\Workshop\WorkOrderInspection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A INSPECÇÃO DIGITAL DE UMA ORDEM (15/09/2026, OF-02).
 *
 * Começa-se a partir de um modelo da empresa; cada ponto recebe o semáforo,
 * uma nota e (se for preciso) uma fotografia. Concluída, aparece ao cliente no
 * portal, e os pontos em Atenção ou Urgente podem passar às recomendações da
 * ordem com um botão.
 *
 * Lê-se com a permissão de ver ordens; faz-se com a de editar.
 */
class InspeccoesDaOrdemApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function ordem(int $id): WorkOrder
    {
        return WorkOrder::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    private function inspeccao(WorkOrder $ordem, int $inspeccao): WorkOrderInspection
    {
        return WorkOrderInspection::where('tenant_id', $ordem->tenant_id)->where('work_order_id', $ordem->id)->findOrFail($inspeccao);
    }

    public function index(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');
        $ordem = $this->ordem($id);

        return response()->json($this->resposta($request, $ordem));
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);

        $dados = $request->validate(['modelo_id' => ['required', 'integer']]);

        $modelo = InspectionTemplate::where('tenant_id', $ordem->tenant_id)->where('is_active', true)->find($dados['modelo_id']);

        if (! $modelo) {
            throw ValidationException::withMessages(['modelo_id' => [__('Esse modelo de inspecção não existe nesta empresa.')]]);
        }

        $pontos = InspectionTemplate::lerPontos($modelo->points);

        if (! $pontos) {
            throw ValidationException::withMessages(['modelo_id' => [__('Esse modelo não tem pontos.')]]);
        }

        $inspeccao = WorkOrderInspection::create([
            'tenant_id' => $ordem->tenant_id,
            'work_order_id' => $ordem->id,
            'template_id' => $modelo->id,
            'name' => $modelo->name,
            'kind' => $modelo->kind ?? 'inspecao',
            'results' => array_map(fn ($p) => $p + ['estado' => null, 'nota' => null, 'foto' => null], $pontos),
            'user_id' => auth()->id(),
        ]);

        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT, __('Inspecção «:nome» começada.', ['nome' => $modelo->name]));

        return response()->json($this->resposta($request, $ordem) + ['criada' => $inspeccao->id, 'message' => __('Inspecção «:nome» começada.', ['nome' => $modelo->name])], 201);
    }

    /** Gravar os semáforos e as notas; `concluir` fecha a inspecção. */
    public function update(Request $request, int $id, int $inspeccao): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);
        $i = $this->inspeccao($ordem, $inspeccao);

        $dados = $request->validate([
            'resultados' => ['required', 'array'],
            'resultados.*.estado' => ['nullable', Rule::in(array_keys(WorkOrderInspection::ESTADOS))],
            'resultados.*.nota' => ['nullable', 'string', 'max:500'],
            'concluir' => ['sometimes', 'boolean'],
            'reabrir' => ['sometimes', 'boolean'],
        ]);

        $resultados = $i->results ?? [];

        if (count($dados['resultados']) !== count($resultados)) {
            throw ValidationException::withMessages(['resultados' => [__('A lista de pontos mudou. Recarregue a inspecção.')]]);
        }

        foreach ($dados['resultados'] as $n => $r) {
            $resultados[$n]['estado'] = $r['estado'] ?? null;
            $resultados[$n]['nota'] = trim((string) ($r['nota'] ?? '')) ?: null;
        }

        $mensagem = __('Inspecção gravada.');
        $mudancas = ['results' => $resultados];

        if (! empty($dados['concluir'])) {
            $porVer = collect($resultados)->whereNull('estado')->count();

            if ($porVer > 0) {
                throw ValidationException::withMessages(['resultados' => [trans_choice('Falta ver :n ponto.|Faltam ver :n pontos.', $porVer, ['n' => $porVer])]]);
            }

            $mudancas['completed_at'] = now();
            $mensagem = __('Inspecção concluída.');
        }

        if (! empty($dados['reabrir'])) {
            $mudancas['completed_at'] = null;
            $mensagem = __('Inspecção reaberta.');
        }

        $i->update($mudancas);

        if (isset($mudancas['completed_at']) && $mudancas['completed_at']) {
            $c = $i->contas();
            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                __('Inspecção «:nome» concluída: :ok OK, :atencao atenção, :urgente urgentes.', ['nome' => $i->name, 'ok' => $c['ok'], 'atencao' => $c['atencao'], 'urgente' => $c['urgente']]));
        }

        return response()->json($this->resposta($request, $ordem) + ['message' => $mensagem]);
    }

    public function foto(Request $request, int $id, int $inspeccao, int $ponto): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);
        $i = $this->inspeccao($ordem, $inspeccao);

        $request->validate(['foto' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']], [
            'foto.image' => __('Só se aceitam imagens (JPG, PNG ou WebP).'),
            'foto.mimes' => __('Só se aceitam imagens (JPG, PNG ou WebP).'),
            'foto.max' => __('A fotografia pode ter no máximo 8 MB.'),
        ]);

        $resultados = $i->results ?? [];
        abort_unless(isset($resultados[$ponto]), 404);

        if (! empty($resultados[$ponto]['foto'])) {
            Storage::disk('public')->delete($resultados[$ponto]['foto']);
        }

        $ficheiro = $request->file('foto');
        $resultados[$ponto]['foto'] = $ficheiro->storeAs("workshop/inspections/{$i->id}", uniqid("ponto{$ponto}_") . '.' . $ficheiro->extension(), 'public');
        $i->update(['results' => $resultados]);

        return response()->json($this->resposta($request, $ordem) + ['message' => __('Fotografia do ponto juntada.')]);
    }

    public function tirarFoto(Request $request, int $id, int $inspeccao, int $ponto): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);
        $i = $this->inspeccao($ordem, $inspeccao);

        $resultados = $i->results ?? [];
        abort_unless(! empty($resultados[$ponto]['foto']), 404);

        Storage::disk('public')->delete($resultados[$ponto]['foto']);
        $resultados[$ponto]['foto'] = null;
        $i->update(['results' => $resultados]);

        return response()->json($this->resposta($request, $ordem) + ['message' => __('Fotografia removida.')]);
    }

    /**
     * OS PONTOS A VERMELHO E AMARELO PASSAM ÀS RECOMENDAÇÕES DA ORDEM.
     *
     * Acrescenta-se ao que já lá está, e um ponto que já foi passado não se
     * repete: carregar duas vezes não duplica a lista.
     */
    public function recomendar(Request $request, int $id, int $inspeccao): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);
        $i = $this->inspeccao($ordem, $inspeccao);

        $actuais = (string) $ordem->recommendations;
        $novas = collect($i->results ?? [])
            ->filter(fn ($r) => in_array($r['estado'] ?? null, ['urgente', 'atencao'], true))
            ->sortBy(fn ($r) => $r['estado'] === 'urgente' ? 0 : 1)
            ->map(fn ($r) => '• ' . ($r['estado'] === 'urgente' ? __('URGENTE') : __('Atenção')) . " — {$r['seccao']}: {$r['ponto']}" . (! empty($r['nota']) ? " ({$r['nota']})" : ''))
            ->reject(fn ($linha) => str_contains($actuais, $linha))
            ->values();

        if ($novas->isEmpty()) {
            return response()->json($this->resposta($request, $ordem) + ['message' => __('Não há pontos novos para recomendar.')]);
        }

        $ordem->update(['recommendations' => mb_substr(trim($actuais . "\n" . $novas->implode("\n")), 0, 5000)]);

        return response()->json($this->resposta($request, $ordem->fresh()) + [
            'message' => trans_choice(':n ponto passou às recomendações.|:n pontos passaram às recomendações.', $novas->count(), ['n' => $novas->count()]),
        ]);
    }

    public function destroy(Request $request, int $id, int $inspeccao): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);
        $i = $this->inspeccao($ordem, $inspeccao);

        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT, __('Inspecção «:nome» apagada.', ['nome' => $i->name]));
        $i->delete();

        return response()->json($this->resposta($request, $ordem) + ['message' => __('Inspecção apagada.')]);
    }

    /** Uma inspecção como o ecrã e o portal a lêem. */
    public static function paraEcra(WorkOrderInspection $i): array
    {
        return [
            'id' => $i->id,
            'nome' => $i->name,
            'tipo' => $i->kind ?? 'inspecao',
            'concluida_em' => $i->completed_at?->toIso8601String(),
            'por' => $i->user?->name,
            'em' => $i->created_at?->toIso8601String(),
            'contas' => $i->contas(),
            'pontos' => collect($i->results ?? [])->map(fn ($r) => [
                'seccao' => $r['seccao'],
                'ponto' => $r['ponto'],
                'estado' => $r['estado'] ?? null,
                'nota' => $r['nota'] ?? null,
                'foto' => ! empty($r['foto']) ? Storage::disk('public')->url($r['foto']) : null,
            ])->values()->all(),
        ];
    }

    private function resposta(Request $request, WorkOrder $ordem): array
    {
        InspectionTemplate::garantirCatalogo($ordem->tenant_id);

        return [
            'inspeccoes' => WorkOrderInspection::with('user:id,name')->where('tenant_id', $ordem->tenant_id)->where('work_order_id', $ordem->id)
                ->orderByDesc('id')->get()->map(fn ($i) => self::paraEcra($i))->values(),
            'modelos' => InspectionTemplate::where('tenant_id', $ordem->tenant_id)->where('is_active', true)
                ->orderByDesc('is_default')->orderBy('name')->get()
                ->map(fn ($m) => ['valor' => (string) $m->id, 'rotulo' => $m->name, 'pontos' => $m->points_count, 'padrao' => $m->is_default, 'tipo' => $m->kind, 'obrigatorio' => $m->is_required])->values(),
            // OF-09: se esta ordem só conclui com o controlo de qualidade feito.
            'qualidade' => (function () use ($ordem) {
                try {
                    app(\App\Services\Workshop\OrdensDeServico::class)->exigirControloDeQualidade($ordem);

                    return ['obrigatorio' => InspectionTemplate::where('tenant_id', $ordem->tenant_id)->where('kind', 'qualidade')->where('is_required', true)->where('is_active', true)->exists(), 'pendente' => null];
                } catch (\InvalidArgumentException $e) {
                    return ['obrigatorio' => true, 'pendente' => $e->getMessage()];
                }
            })(),
            'estados' => collect(WorkOrderInspection::ESTADOS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'recomendacoes' => $ordem->recommendations,
            'pode_editar' => (bool) $request->user()?->can('workshop.work-orders.edit'),
        ];
    }
}
