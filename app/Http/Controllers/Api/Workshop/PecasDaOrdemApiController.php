<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Compras\Requisicao;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Warehouse;
use App\Models\Tenant;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use App\Models\Workshop\WorkOrderItem;
use App\Services\Compras\FluxoDaRequisicao;
use App\Services\Workshop\OrdensDeServico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * AS PEÇAS DA ORDEM E O QUE FALTA (15/09/2026, OF-08).
 *
 * Cada peça do catálogo diz quanto há no armazém de onde vai sair e quanto
 * falta. As que faltam pedem-se de uma vez: nasce uma REQUISIÇÃO DE COMPRA
 * (módulo Compras) ligada à ordem, e a ordem pode passar a «À espera de peças».
 * Quando a encomenda que sair dessa requisição for recebida, a ordem volta a
 * «Em curso» sozinha (ver `aoReceberEncomenda`).
 */
class PecasDaOrdemApiController extends Controller
{
    public function __construct(
        private readonly FluxoDaRequisicao $requisicoes,
        private readonly OrdensDeServico $ordens,
    ) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function ordem(int $id): WorkOrder
    {
        return WorkOrder::with('vehicle')->where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function index(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');

        return response()->json($this->resposta($request, $this->ordem($id)));
    }

    public function requisitar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $this->exigir($request, 'compras.requisicoes.manage');
        $ordem = $this->ordem($id);

        abort_unless(Tenant::find($ordem->tenant_id)?->hasModule('compras'), 422, __('Pedir peças precisa do módulo Compras.'));

        $dados = $request->validate([
            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.linha_id' => ['required', 'integer'],
            'linhas.*.quantidade' => ['required', 'numeric', 'min:0.001'],
            'submeter' => ['nullable', 'boolean'],
            'esperar' => ['nullable', 'boolean'],
        ], ['linhas.required' => __('Escolha as peças a pedir.')]);

        $itens = WorkOrderItem::with('product:id,name,cost,unit')->where('work_order_id', $ordem->id)->where('type', 'part')
            ->whereIn('id', collect($dados['linhas'])->pluck('linha_id'))->get()->keyBy('id');

        $linhas = collect($dados['linhas'])->map(function ($l) use ($itens) {
            $item = $itens[$l['linha_id']] ?? null;
            if (! $item) {
                throw ValidationException::withMessages(['linhas' => [__('Essa peça não é desta ordem.')]]);
            }

            return [
                'product_id' => $item->product_id,
                'descricao' => $item->product?->name ?? $item->name,
                'quantidade' => (float) $l['quantidade'],
                'custo_estimado' => $item->product?->cost !== null ? (float) $item->product->cost : null,
                'unidade' => $item->product?->unit,
            ];
        })->values()->all();

        try {
            $requisicao = DB::transaction(function () use ($ordem, $dados, $linhas, $request) {
                $armazem = Warehouse::getDefault($ordem->tenant_id);
                $req = $this->requisicoes->criar($ordem->tenant_id, $request->user()->id, [
                    'warehouse_id' => $armazem?->id,
                    'necessaria_em' => ($ordem->scheduled_for && $ordem->scheduled_for->isFuture() ? $ordem->scheduled_for : now()->addDays(2))->toDateString(),
                    'justificacao' => __('Peças para a ordem :numero (:matricula).', ['numero' => $ordem->order_number, 'matricula' => $ordem->vehicle?->plate]),
                ], $linhas);

                $req->update(['work_order_id' => $ordem->id]);

                if (! empty($dados['submeter'])) {
                    $this->requisicoes->submeter($req, $ordem->tenant_id);
                }

                if (! empty($dados['esperar']) && ! in_array($ordem->status, ['waiting_parts', 'completed', 'delivered', 'cancelled'], true)) {
                    $this->ordens->aplicarEstado($ordem, 'waiting_parts');
                }

                WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                    trans_choice('Pedidas :n peça em falta na requisição :numero.|Pedidas :n peças em falta na requisição :numero.', count($linhas), ['n' => count($linhas), 'numero' => $req->numero]));

                return $req;
            });
        } catch (\InvalidArgumentException $e) {
            // As regras das Compras falam em português de gente: vão direitas ao ecrã.
            throw ValidationException::withMessages(['linhas' => [__($e->getMessage())]]);
        }

        return response()->json($this->resposta($request, $ordem->fresh('vehicle')) + [
            'message' => __('Requisição :numero criada.', ['numero' => $requisicao->numero]),
        ], 201);
    }

    /**
     * A ENCOMENDA CHEGOU — chamado pela recepção das Compras.
     *
     * Nunca rebenta a recepção: se algo correr mal aqui, a mercadoria entrou na
     * mesma e fica só por avisar a ordem.
     */
    public static function aoReceberEncomenda(\App\Models\Compras\Encomenda $enc): void
    {
        try {
            if (! $enc->requisicao_id) {
                return;
            }

            $req = Requisicao::withoutGlobalScopes()->find($enc->requisicao_id);
            $ordem = $req?->work_order_id ? WorkOrder::withoutGlobalScopes()->find($req->work_order_id) : null;

            if (! $ordem) {
                return;
            }

            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                $enc->estado === 'recebida'
                    ? __('Chegaram as peças da encomenda :numero.', ['numero' => $enc->numero])
                    : __('Chegou parte das peças da encomenda :numero.', ['numero' => $enc->numero]));

            if ($enc->estado === 'recebida' && $ordem->status === 'waiting_parts') {
                app(OrdensDeServico::class)->aplicarEstado($ordem, 'in_progress');
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function resposta(Request $request, WorkOrder $ordem): array
    {
        $armazem = Warehouse::getDefault($ordem->tenant_id);
        $itens = WorkOrderItem::with('product:id,name,code,unit,manage_stock')->where('work_order_id', $ordem->id)->where('type', 'part')
            ->where('approval', '!=', 'declined')->get();

        $stock = Stock::where('tenant_id', $ordem->tenant_id)
            ->when($armazem, fn ($q) => $q->where('warehouse_id', $armazem->id))
            ->whereIn('product_id', $itens->pluck('product_id')->filter())
            ->groupBy('product_id')->selectRaw('product_id, SUM(quantity) as quantidade')->pluck('quantidade', 'product_id');

        // A mesma peça em duas linhas conta o stock uma vez só.
        $precisa = $itens->whereNotNull('product_id')->groupBy('product_id')->map(fn ($g) => (float) $g->sum('quantity'));
        $jaSaiu = in_array($ordem->status, ['completed', 'delivered'], true);

        return [
            'armazem' => $armazem?->name,
            'pecas' => $itens->map(function (WorkOrderItem $i) use ($stock, $precisa, $jaSaiu) {
                $emStock = $i->product_id ? (float) ($stock[$i->product_id] ?? 0) : null;
                $falta = $i->product_id && ! $jaSaiu ? max(0, round((float) $precisa[$i->product_id] - $emStock, 3)) : 0;

                return [
                    'id' => $i->id,
                    'nome' => $i->name,
                    'codigo' => $i->product?->code ?? $i->code,
                    'quantidade' => (float) $i->quantity,
                    'do_catalogo' => (bool) $i->product_id,
                    'em_stock' => $emStock,
                    'falta' => min((float) $i->quantity, $falta),
                    'aprovacao' => $i->approval,
                ];
            })->values(),
            'requisicoes' => Requisicao::with('itens')->where('tenant_id', $ordem->tenant_id)->where('work_order_id', $ordem->id)->orderByDesc('id')->get()
                ->map(fn (Requisicao $r) => [
                    'id' => $r->id,
                    'numero' => $r->numero,
                    'estado' => $r->estado,
                    'estado_rotulo' => __(Requisicao::ESTADOS[$r->estado] ?? $r->estado),
                    'linhas' => $r->itens->count(),
                    'em' => $r->created_at?->toIso8601String(),
                ])->values(),
            'tem_compras' => (bool) Tenant::find($ordem->tenant_id)?->hasModule('compras'),
            'pode_pedir' => (bool) ($request->user()?->can('workshop.work-orders.edit') && $request->user()?->can('compras.requisicoes.manage')),
            'estado_da_ordem' => $ordem->status,
        ];
    }
}
