<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Api\Compras\EncomendasApiController;
use App\Http\Controllers\Controller;
use App\Models\Compras\PedidoDePagamento;
use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;
use App\Services\Compras\Aprovacoes;
use App\Services\Compras\FluxoDoPagamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS PAGAMENTOS A FORNECEDORES — o lado da TESOURARIA (27/09/2026).
 *
 * É aqui que chegam os pedidos que as Compras fazem. O tesoureiro vê o que
 * tem de pagar, a encomenda de onde vem (só para ler — não a altera), e paga:
 * escolhe a conta ou a caixa, a forma e a data. O dinheiro sai da tesouraria
 * pelo `FluxoDoPagamento`, com recibo de compra e movimento de saída.
 *
 * VER E PAGAR SÃO PERMISSÕES DIFERENTES (`treasury.pagamentos.view` e
 * `.pagar`); aprovar, quando a empresa o exige, é das Compras
 * (`compras.pagamentos.aprovar`) e pode fazer-se daqui também.
 */
class PagamentosAFornecedoresApiController extends Controller
{
    public function __construct(
        private readonly FluxoDoPagamento $fluxo,
        private readonly Aprovacoes $aprovacoes,
    ) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'treasury.pagamentos.view');

        $tenantId = (int) activeTenantId();

        return response()->json([
            'estados' => collect(PedidoDePagamento::ESTADOS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'formas' => EncomendasApiController::formas(),
            // O SALDO de cada conta e caixa: o tesoureiro «verifica o valor»
            // antes de pagar — e vê logo se o dinheiro chega.
            'contas' => Account::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('account_name')
                ->get(['id', 'account_name', 'current_balance'])
                ->map(fn ($c) => ['id' => $c->id, 'nome' => $c->account_name, 'saldo' => (float) $c->current_balance])->values(),
            'caixas' => CashRegister::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'current_balance', 'status'])
                ->map(fn ($c) => [
                    'id' => $c->id, 'nome' => $c->name, 'saldo' => (float) $c->current_balance, 'aberta' => $c->status === 'open',
                ])->values(),
            'permissoes' => [
                'pode_pagar' => (bool) $request->user()?->can('treasury.pagamentos.pagar'),
                'pode_aprovar' => (bool) $request->user()?->can('compras.pagamentos.aprovar'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'treasury.pagamentos.view');

        $filtros = $request->validate([
            'estado' => ['nullable', 'string', 'max:20'],
            'procura' => ['nullable', 'string', 'max:120'],
            'meus' => ['nullable', 'boolean'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $estado = $filtros['estado'] ?? 'por_pagar';

        $lista = PedidoDePagamento::forTenant()
            ->when($estado !== 'todos', fn ($q) => $q->where('estado', $estado))
            ->when($request->boolean('meus'), fn ($q) => $q->where('tesoureiro_id', $request->user()->id))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';
                $q->where(fn ($w) => $w->where('numero', 'like', $t)
                    ->orWhereHas('fornecedor', fn ($f) => $f->where('name', 'like', $t))
                    ->orWhereHas('encomenda', fn ($e) => $e->where('numero', 'like', $t)));
            })
            ->with(['fornecedor:id,name', 'encomenda:id,numero,estado,total', 'pedidoPor:id,name', 'pagoPor:id,name', 'tesoureiro:id,name', 'recibo:id,receipt_number'])
            // Os por pagar primeiro pelo prazo; o resto do mais recente para trás.
            ->orderByRaw("CASE WHEN estado = 'por_pagar' THEN 0 WHEN estado = 'em_aprovacao' THEN 1 ELSE 2 END")
            ->orderByRaw('data_limite IS NULL, data_limite')
            ->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 15);

        $base = fn () => PedidoDePagamento::forTenant();

        return response()->json([
            'data' => collect($lista->items())->map(fn (PedidoDePagamento $p) => EncomendasApiController::linhaDoPagamento($p, $this->aprovacoes) + [
                'fornecedor' => $p->fornecedor?->name,
                'encomenda' => $p->encomenda ? ['id' => $p->encomenda->id, 'numero' => $p->encomenda->numero] : null,
            ])->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'por_pagar' => $base()->where('estado', 'por_pagar')->count(),
                'valor_por_pagar' => (float) $base()->where('estado', 'por_pagar')->sum('valor'),
                'atrasados' => $base()->where('estado', 'por_pagar')->whereNotNull('data_limite')->whereDate('data_limite', '<', today())->count(),
                'em_aprovacao' => $base()->where('estado', 'em_aprovacao')->count(),
                'pagos_no_mes' => (float) $base()->where('estado', 'pago')->where('pago_em', '>=', now()->startOfMonth())->sum('valor'),
            ],
        ]);
    }

    /** O pedido e a encomenda de onde vem — para ler, não para mudar. */
    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'treasury.pagamentos.view');

        $p = PedidoDePagamento::forTenant()
            ->with(['fornecedor:id,name,nif,phone,email', 'encomenda.itens', 'encomenda.factura:id,invoice_number,status,total,paid_amount',
                'pedidoPor:id,name', 'pagoPor:id,name', 'tesoureiro:id,name', 'recibo:id,receipt_number'])
            ->findOrFail($id);

        $enc = $p->encomenda;

        return response()->json([
            'data' => EncomendasApiController::linhaDoPagamento($p, $this->aprovacoes) + [
                'fornecedor' => $p->fornecedor?->name,
                'fornecedor_nif' => $p->fornecedor?->nif,
                'encomenda' => $enc ? [
                    'id' => $enc->id,
                    'numero' => $enc->numero,
                    'estado' => __(\App\Models\Compras\Encomenda::ESTADOS[$enc->estado] ?? $enc->estado),
                    'total' => (float) $enc->total,
                    'pago' => $enc->valorPago(),
                    'factura' => $enc->factura?->invoice_number,
                    'itens' => $enc->itens->map(fn ($i) => [
                        'descricao' => $i->descricao,
                        'quantidade' => (float) $i->quantidade,
                        'unidade' => $i->unidade,
                        'preco_unitario' => (float) $i->preco_unitario,
                        'total' => (float) $i->total,
                    ])->values(),
                ] : null,
            ],
        ]);
    }

    /** PAGAR — o dinheiro sai da conta ou da caixa escolhida. */
    public function pagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'treasury.pagamentos.pagar');

        $tenantId = (int) activeTenantId();

        $dados = $request->validate([
            'forma' => ['required', 'string', Rule::in(array_column(EncomendasApiController::formas(), 'valor'))],
            'account_id' => ['nullable', 'integer', Rule::exists('treasury_accounts', 'id')->where('tenant_id', $tenantId)],
            'cash_register_id' => ['nullable', 'integer', Rule::exists('treasury_cash_registers', 'id')->where('tenant_id', $tenantId)],
            'data' => ['nullable', 'date', 'before_or_equal:today'],
            'referencia' => ['nullable', 'string', 'max:120'],
        ], [
            'forma.required' => __('Diga como foi pago.'),
            'data.before_or_equal' => __('Um pagamento regista-se quando é feito — não com data futura.'),
        ]);

        try {
            $p = $this->fluxo->pagar(PedidoDePagamento::forTenant()->findOrFail($id), $tenantId, $request->user(), $dados);
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => __('Pagamento :n registado — o dinheiro saiu da tesouraria (recibo :r).', [
                'n' => $p->numero, 'r' => $p->recibo()->value('receipt_number'),
            ]),
        ]);
    }

    /** DEVOLVER o pedido a quem o fez — com motivo. */
    public function recusar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'treasury.pagamentos.pagar');

        $dados = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:500']], [], ['motivo' => __('motivo')]);

        try {
            $p = $this->fluxo->recusarNaTesouraria(PedidoDePagamento::forTenant()->findOrFail($id), (int) activeTenantId(), $request->user(), $dados['motivo']);
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json(['message' => __('Pedido :n devolvido a quem o fez.', ['n' => $p->numero])]);
    }

    /** APROVAR OU RECUSAR o pedido, quando a empresa exige aprovação dos pagamentos. */
    public function decidir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.pagamentos.aprovar');

        $dados = $request->validate([
            'aprova' => ['required', 'boolean'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $p = $this->fluxo->decidir(
                PedidoDePagamento::forTenant()->findOrFail($id), (int) activeTenantId(), $request->user(),
                (bool) $dados['aprova'], $dados['comentario'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => match ($p->estado) {
                'por_pagar' => __('Pagamento :n aprovado — já está na tesouraria.', ['n' => $p->numero]),
                'recusado' => __('Pagamento :n recusado.', ['n' => $p->numero]),
                default => __('A sua aprovação ficou registada.'),
            },
        ]);
    }

    /** Quem pediu (ou quem gere as compras) pode desistir do pedido antes de ser pago. */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        $p = PedidoDePagamento::forTenant()->findOrFail($id);

        abort_unless(
            (int) $p->pedido_por === (int) $request->user()?->id || $request->user()?->can('compras.encomendas.manage'),
            403, __('Sem permissão para esta operação.'),
        );

        try {
            $p = $this->fluxo->cancelar($p, (int) activeTenantId());
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json(['message' => __('Pedido :n cancelado.', ['n' => $p->numero])]);
    }
}
