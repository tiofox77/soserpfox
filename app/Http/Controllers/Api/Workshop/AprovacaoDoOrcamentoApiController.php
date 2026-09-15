<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use App\Models\Workshop\WorkOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O ORÇAMENTO APROVADO PELO CLIENTE (15/09/2026, OF-03).
 *
 * DO LADO DA OFICINA (com sessão): pedir a aprovação cria um link que vale 14
 * dias e que se envia ao cliente (copiar, WhatsApp); cada linha pode também ser
 * decidida à mão («aprovou por telefone»), e fica escrito quem a registou.
 *
 * DO LADO DO CLIENTE (sem sessão, só com o link): vê as linhas à espera e as já
 * decididas, aprova ou recusa cada uma, escreve o nome e assina. O link só mexe
 * nas linhas À ESPERA desta ordem — nunca muda uma linha já aprovada, nunca
 * serve depois de expirar, de a ordem ser facturada, entregue ou cancelada.
 */
class AprovacaoDoOrcamentoApiController extends Controller
{
    public const DIAS_DO_LINK = 14;

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function ordem(int $id): WorkOrder
    {
        return WorkOrder::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    /* ─── A oficina ─────────────────────────────────────────────────────── */

    public function pedir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);

        if (! $ordem->pendingItems()->exists()) {
            throw ValidationException::withMessages(['aprovacao' => [__('Não há linhas à espera da aprovação do cliente.')]]);
        }

        // Um link ainda válido serve outra vez: reenviar não invalida o que o cliente já recebeu.
        if (! self::linkActivo($ordem)) {
            $ordem->forceFill(['approval_token' => Str::random(48)]);
        }

        $ordem->forceFill([
            'approval_requested_at' => now(),
            'approval_expires_at' => now()->addDays(self::DIAS_DO_LINK),
        ])->save();

        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT, __('Aprovação do orçamento pedida ao cliente.'));

        return response()->json(['data' => self::resumo($ordem->fresh()), 'message' => __('Link de aprovação pronto a enviar.')]);
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);

        $ordem->forceFill(['approval_token' => null, 'approval_expires_at' => null])->save();
        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT, __('Link de aprovação do orçamento anulado.'));

        return response()->json(['data' => self::resumo($ordem->fresh()), 'message' => __('Link de aprovação anulado.')]);
    }

    /** A oficina regista a decisão de uma linha (ou devolve-a a «à espera»). */
    public function decidirLinha(Request $request, int $id, int $linha): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $ordem = $this->ordem($id);

        abort_if($ordem->invoice_id, 422, __('A ordem já foi facturada: as linhas não mudam.'));

        $dados = $request->validate(['decisao' => ['required', Rule::in(array_keys(WorkOrderItem::APROVACOES))]]);
        $item = WorkOrderItem::where('work_order_id', $ordem->id)->findOrFail($linha);

        DB::transaction(function () use ($ordem, $item, $dados, $request) {
            $item->update([
                'approval' => $dados['decisao'],
                'approval_at' => $dados['decisao'] === 'pending' ? null : now(),
                'approval_by' => $dados['decisao'] === 'pending' ? null : __('Oficina: :nome', ['nome' => $request->user()->name]),
            ]);
            $ordem->calculateTotals();

            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_ITEM_UPDATED,
                __(':linha — :decisao (registado pela oficina).', ['linha' => $item->name, 'decisao' => __(WorkOrderItem::APROVACOES[$dados['decisao']])]));
        });

        return response()->json(['data' => self::resumo($ordem->fresh()), 'message' => __(':linha: :decisao.', ['linha' => $item->name, 'decisao' => __(WorkOrderItem::APROVACOES[$dados['decisao']])])]);
    }

    /* ─── O cliente, pelo link ─────────────────────────────────────────── */

    private function pelaChave(string $token): WorkOrder
    {
        abort_unless(strlen($token) === 48 && ctype_alnum($token), 404);

        $ordem = WorkOrder::withoutGlobalScopes()->with(['vehicle' => fn ($q) => $q->withoutGlobalScopes(), 'items'])
            ->where('approval_token', $token)->first();

        abort_unless($ordem, 404);

        return $ordem;
    }

    public function ver(string $token): JsonResponse
    {
        $ordem = $this->pelaChave($token);
        $empresa = Tenant::find($ordem->tenant_id);

        $valor = fn ($linhas) => round((float) $linhas->sum('subtotal'), 2);
        $aprovadas = $ordem->items->where('approval', 'approved');
        $aEspera = $ordem->items->where('approval', 'pending');

        return response()->json([
            'empresa' => ['nome' => $empresa?->name, 'telefone' => $empresa?->phone, 'email' => $empresa?->email],
            'ordem' => [
                'numero' => $ordem->order_number,
                'matricula' => $ordem->vehicle?->plate,
                'viatura' => trim(($ordem->vehicle?->brand ?? '') . ' ' . ($ordem->vehicle?->model ?? '')),
                'dono' => $ordem->vehicle?->owner_name,
                'problema' => $ordem->problem_description,
                'diagnostico' => $ordem->diagnosis,
            ],
            'linhas' => $ordem->items->map(fn (WorkOrderItem $l) => [
                'id' => $l->id,
                'tipo' => $l->type,
                'nome' => $l->name,
                'descricao' => $l->description,
                'quantidade' => (float) $l->quantity,
                'preco' => round((float) $l->unit_price, 2),
                'desconto' => (float) $l->discount_percent,
                'subtotal' => round((float) $l->subtotal, 2),
                'aprovacao' => $l->approval,
                'decidida_em' => $l->approval_at?->toIso8601String(),
            ])->values(),
            'contas' => ['aprovado' => $valor($aprovadas), 'a_espera' => $valor($aEspera), 'desconto' => round((float) $ordem->discount, 2)],
            'aberto' => self::linkActivo($ordem) && $aEspera->isNotEmpty(),
            'motivo' => self::porQueFechado($ordem, $aEspera->isNotEmpty()),
            'expira_em' => $ordem->approval_expires_at?->toIso8601String(),
            'assinado_por' => $ordem->approval_signed_by,
            'assinado_em' => $ordem->approval_signed_at?->toIso8601String(),
        ]);
    }

    public function responder(Request $request, string $token): JsonResponse
    {
        $ordem = $this->pelaChave($token);

        $aEspera = $ordem->items->where('approval', 'pending');

        if (! self::linkActivo($ordem) || $aEspera->isEmpty()) {
            throw ValidationException::withMessages(['decisoes' => [self::porQueFechado($ordem, $aEspera->isNotEmpty()) ?? __('Este link já não está aberto.')]]);
        }

        $dados = $request->validate([
            'decisoes' => ['required', 'array'],
            'decisoes.*' => ['required', Rule::in(['approved', 'declined'])],
            'nome' => ['required', 'string', 'max:150'],
            'assinatura' => ['required', 'string', 'max:400000', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/'],
        ], [
            'nome.required' => __('Escreva o seu nome.'),
            'assinatura.required' => __('Assine no quadro para confirmar.'),
            'assinatura.regex' => __('A assinatura não chegou como imagem.'),
        ]);

        // Todas as linhas à espera têm de ter resposta — e só essas.
        $ids = $aEspera->pluck('id')->map(fn ($i) => (string) $i)->sort()->values()->all();
        $respondidas = collect(array_keys($dados['decisoes']))->map(fn ($i) => (string) $i)->sort()->values()->all();

        if ($ids !== $respondidas) {
            throw ValidationException::withMessages(['decisoes' => [__('Decida todas as linhas à espera: aprovar ou recusar.')]]);
        }

        $nome = trim($dados['nome']);

        DB::transaction(function () use ($ordem, $aEspera, $dados, $nome) {
            foreach ($aEspera as $item) {
                $item->update([
                    'approval' => $dados['decisoes'][(string) $item->id] ?? $dados['decisoes'][$item->id],
                    'approval_at' => now(),
                    'approval_by' => __(':nome (pelo link)', ['nome' => $nome]),
                ]);
            }

            $ordem->forceFill([
                'approval_signature' => $dados['assinatura'],
                'approval_signed_by' => $nome,
                'approval_signed_at' => now(),
            ])->save();
            $ordem->calculateTotals();

            $aprovadas = collect($dados['decisoes'])->filter(fn ($d) => $d === 'approved')->count();
            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                __(':nome respondeu ao orçamento: :aprovadas aprovada(s), :recusadas recusada(s).', ['nome' => $nome, 'aprovadas' => $aprovadas, 'recusadas' => count($dados['decisoes']) - $aprovadas]));
        });

        return response()->json(['message' => __('Obrigado, :nome. A oficina já recebeu a sua decisão.', ['nome' => $nome])]);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    public static function linkActivo(WorkOrder $ordem): bool
    {
        return $ordem->approval_token
            && $ordem->approval_expires_at && $ordem->approval_expires_at->isFuture()
            && ! $ordem->invoice_id
            && ! in_array($ordem->status, ['delivered', 'cancelled'], true);
    }

    private static function porQueFechado(WorkOrder $ordem, bool $haAEspera): ?string
    {
        return match (true) {
            (bool) $ordem->invoice_id => __('Esta ordem já foi facturada.'),
            in_array($ordem->status, ['delivered', 'cancelled'], true) => __('Esta ordem já está fechada.'),
            ! $ordem->approval_expires_at || $ordem->approval_expires_at->isPast() => __('Este link expirou. Peça um novo à oficina.'),
            ! $haAEspera => __('Não há nada à espera da sua decisão.'),
            default => null,
        };
    }

    /** O estado da aprovação, como a ficha da ordem o mostra. */
    public static function resumo(WorkOrder $ordem): array
    {
        $activo = self::linkActivo($ordem);
        $aEspera = $ordem->items()->where('approval', 'pending')->get(['subtotal']);

        return [
            'a_espera' => $aEspera->count(),
            'valor_a_espera' => round((float) $aEspera->sum('subtotal'), 2),
            'recusadas' => $ordem->items()->where('approval', 'declined')->count(),
            'link' => $activo ? route('oficina.aprovar-orcamento', $ordem->approval_token) : null,
            'pedido_em' => $ordem->approval_requested_at?->toIso8601String(),
            'expira_em' => $activo ? $ordem->approval_expires_at?->toIso8601String() : null,
            'assinado_por' => $ordem->approval_signed_by,
            'assinado_em' => $ordem->approval_signed_at?->toIso8601String(),
            'assinatura' => $ordem->approval_signature,
        ];
    }
}
