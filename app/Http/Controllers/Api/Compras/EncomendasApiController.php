<?php

namespace App\Http\Controllers\Api\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\Encomenda;
use App\Models\Compras\Requisicao;
use App\Models\Invoicing\Warehouse;
use App\Models\Supplier;
use App\Models\Compras\DefinicoesDasCompras;
use App\Models\Compras\PedidoDePagamento;
use App\Services\Compras\Aprovacoes;
use App\Services\Compras\FluxoDaEncomenda;
use App\Services\Compras\FluxoDoPagamento;
use App\Services\Compras\RastoDaCompra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS ENCOMENDAS AO FORNECEDOR — enviar, receber e facturar.
 *
 * A RECEPÇÃO É O MOMENTO EM QUE O STOCK ENTRA, e é o acto de mais consequência
 * deste ecrã: por isso tem permissão própria (`compras.encomendas.receber`),
 * como a contagem física. Receber a mais do que se encomendou não é um engano
 * de escrita — é stock que entra sem ninguém ter pedido.
 *
 * E SÓ SE FACTURA O QUE JÁ CHEGOU, uma vez só. A encomenda guarda a factura que
 * dela nasceu, e é essa marca que impede pagar duas vezes o mesmo material.
 */
class EncomendasApiController extends Controller
{
    public function __construct(
        private readonly FluxoDaEncomenda $fluxo,
        private readonly Aprovacoes $aprovacoes,
    ) {}

    /** As regras da empresa, lidas uma vez por pedido. */
    private ?DefinicoesDasCompras $regras = null;

    private function regras(): DefinicoesDasCompras
    {
        return $this->regras ??= DefinicoesDasCompras::da((int) activeTenantId());
    }

    /**
     * As formas de pagamento que se podem sugerir ou usar — as da tesouraria,
     * pelo código que o `LancamentoDeDinheiro` entende.
     *
     * @return list<array{valor: string, rotulo: string}>
     */
    public static function formas(): array
    {
        return collect([
            'transfer' => 'Transferência bancária',
            'cash' => 'Dinheiro',
            'multicaixa' => 'Multicaixa',
            'card' => 'Cartão',
            'check' => 'Cheque',
            'other' => 'Outro',
        ])->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values()->all();
    }

    /**
     * Quem pode pagar fornecedores nesta empresa — é a lista de onde se escolhe
     * o tesoureiro a quem o pedido vai.
     *
     * @return list<array{valor: string, rotulo: string}>
     */
    public static function tesoureiros(): array
    {
        $empresa = auth()->user()?->activeTenant();

        if (! $empresa) {
            return [];
        }

        return $empresa->users()
            ->wherePivot('is_active', true)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->filter(fn ($u) => $u->can('treasury.pagamentos.pagar'))
            ->map(fn ($u) => ['valor' => (string) $u->id, 'rotulo' => $u->name])
            ->values()->all();
    }

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
        $this->exigir($request, 'compras.encomendas.view');

        return response()->json([
            'estados' => collect(Encomenda::ESTADOS)
                ->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'fornecedores' => Supplier::where('tenant_id', activeTenantId())->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($f) => ['valor' => (string) $f->id, 'rotulo' => $f->name])->values(),
            'armazens' => Warehouse::forTenant()->where('is_active', true)
                ->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default'])
                ->map(fn ($a) => [
                    'valor' => (string) $a->id, 'rotulo' => $a->name, 'padrao' => (bool) $a->is_default,
                ])->values(),
            /*
             * AS REQUISIÇÕES COM LINHAS POR ENCOMENDAR.
             *
             * É por aqui que o circuito se fecha: uma requisição aprovada vira
             * encomenda sem se reescrever nada. Só aparecem as que ainda têm
             * alguma coisa por encomendar — as outras já lá estão.
             */
            'requisicoes' => Requisicao::forTenant()
                ->whereIn('estado', ['aprovada', 'encomendada'])
                ->with('itens')->latest()->get()
                ->filter(fn (Requisicao $r) => $r->itens->contains(fn ($i) => $i->porEncomendar() > 0))
                ->take(15)
                ->map(fn (Requisicao $r) => [
                    'valor' => (string) $r->id,
                    'rotulo' => $r->numero,
                    'linhas' => $r->itens->filter(fn ($i) => $i->porEncomendar() > 0)->count(),
                ])->values(),
            'permissoes' => [
                'pode_gerir' => (bool) $request->user()?->can('compras.encomendas.manage'),
                'pode_receber' => (bool) $request->user()?->can('compras.encomendas.receber'),
                'pode_aprovar' => (bool) $request->user()?->can('compras.encomendas.aprovar'),
                'pode_facturar' => (bool) $request->user()?->can('compras.encomendas.facturar'),
                'pode_pedir_pagamento' => (bool) $request->user()?->can('compras.pagamentos.solicitar'),
                'pode_aprovar_pagamentos' => (bool) $request->user()?->can('compras.pagamentos.aprovar'),
            ],
            'regras' => [
                'aprovacoes_encomenda' => (int) $this->regras()->aprovacoes_encomenda,
                'aprovacoes_pagamento' => (int) $this->regras()->aprovacoes_pagamento,
                'tesoureiro_id' => $this->regras()->tesoureiro_id ? (string) $this->regras()->tesoureiro_id : null,
            ],
            'formas' => self::formas(),
            'tesoureiros' => self::tesoureiros(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'compras.encomendas.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:20'],
            'fornecedor' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = Encomenda::forTenant()
            ->when(($filtros['estado'] ?? 'todos') !== 'todos', fn ($q) => $q->where('estado', $filtros['estado']))
            ->when($filtros['fornecedor'] ?? null, fn ($q, $f) => $q->where('supplier_id', $f))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('numero', 'like', $t)
                    ->orWhereHas('fornecedor', fn ($f) => $f->where('name', 'like', $t))
                    ->orWhereHas('itens', fn ($i) => $i->where('descricao', 'like', $t)));
            })
            ->with(['fornecedor:id,name', 'warehouse:id,name', 'itens', 'factura:id,invoice_number,status,total', 'pagamentos'])
            ->latest()
            ->paginate($filtros['por_pagina'] ?? 15);

        $abertas = fn () => Encomenda::forTenant()->whereIn('estado', Encomenda::ABERTOS);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Encomenda $e) => $this->linha($e))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'abertas' => $abertas()->count(),
                'atrasadas' => $abertas()->whereNotNull('entrega_prevista')
                    ->whereDate('entrega_prevista', '<', today())->count(),
                'por_facturar' => Encomenda::forTenant()
                    ->whereIn('estado', ['parcial', 'recebida'])->whereNull('purchase_invoice_id')->count(),
                'valor_aberto' => (float) $abertas()->sum('total'),
                'em_aprovacao' => Encomenda::forTenant()->where('estado', 'em_aprovacao')->count(),
                'pagamentos_por_pagar' => (float) PedidoDePagamento::forTenant()->whereIn('estado', ['em_aprovacao', 'por_pagar'])->sum('valor'),
            ],
        ]);
    }

    private function linha(Encomenda $e): array
    {
        $itens = $e->relationLoaded('itens') ? $e->itens : $e->itens()->get();

        $pedido = (float) $itens->sum('quantidade');
        $recebido = (float) $itens->sum('quantidade_recebida');

        // O DINHEIRO: o que se deve, o que já foi pedido à tesouraria e o que
        // ela já pagou — é o que diz de relance em que pé está o pagamento.
        $pagamentos = $e->relationLoaded('pagamentos') ? $e->pagamentos : $e->pagamentos()->get();
        $activos = $pagamentos->whereIn('estado', PedidoDePagamento::ACTIVOS);
        $valorPedido = round((float) $activos->sum('valor'), 2);
        $valorPago = round((float) $pagamentos->where('estado', 'pago')->sum('valor'), 2);
        $aPagar = $e->valorAPagar();
        $necessarias = (int) $this->regras()->aprovacoes_encomenda;
        $user = request()->user();

        return [
            'id' => $e->id,
            'numero' => $e->numero,
            'estado' => $e->estado,
            'estado_rotulo' => __(Encomenda::ESTADOS[$e->estado] ?? $e->estado),
            'supplier_id' => $e->supplier_id,
            'fornecedor' => $e->fornecedor?->name,
            'warehouse_id' => $e->warehouse_id,
            'armazem' => $e->warehouse?->name,
            'requisicao_id' => $e->requisicao_id,
            'data' => $e->data_encomenda?->format('Y-m-d'),
            'entrega_prevista' => $e->entrega_prevista?->format('Y-m-d'),
            'atrasada' => $e->estaAtrasada(),
            'notas' => $e->notas,
            'subtotal' => (float) $e->subtotal,
            'total' => (float) $e->total,
            'linhas' => $itens->count(),
            'quantidade_pedida' => $pedido,
            'quantidade_recebida' => $recebido,
            // A PERCENTAGEM DO QUE CHEGOU: é o que diz de relance se a
            // encomenda está fechada, em parte, ou ainda por sair de casa.
            'percentagem_recebida' => $pedido > 0 ? round($recebido / $pedido * 100, 1) : 0.0,
            'pode_editar' => $e->podeEditar(),
            'pode_receber' => $e->podeReceber(),
            'pode_facturar' => $e->podeFacturar(),
            'motivo_recusa' => $e->motivo_recusa,
            'aprovacao' => [
                'exigida' => $necessarias > 0,
                'necessarias' => $necessarias,
                'sins' => $e->estado === 'em_aprovacao' ? $this->aprovacoes->sins($e, (int) $e->ronda_aprovacao) : 0,
                'pode_votar' => $e->estado === 'em_aprovacao'
                    && (bool) $user?->can('compras.encomendas.aprovar')
                    && $this->aprovacoes->podeVotar($e, $user, (int) $e->ronda_aprovacao),
            ],
            'pagamento' => [
                'a_pagar' => $aPagar,
                'pedido' => $valorPedido,
                'pago' => $valorPago,
                'por_pedir' => max(0, round($aPagar - $valorPedido, 2)),
                'estado' => $valorPago >= $aPagar - 0.01 && $aPagar > 0 ? 'pago'
                    : ($valorPago > 0 ? 'parcial' : ($valorPedido > 0 ? 'pedido' : 'sem')),
                'pode_pedir' => in_array($e->estado, Encomenda::PAGAVEIS, true) && $aPagar - $valorPedido > 0.005,
            ],
            'factura' => $e->factura ? [
                'id' => $e->factura->id,
                'numero' => $e->factura->invoice_number,
                'estado' => $e->factura->status,
            ] : null,
        ];
    }

    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.encomendas.view');

        $e = Encomenda::forTenant()
            ->with([
                'itens.produto:id,name,code', 'fornecedor:id,name,phone,email',
                'warehouse:id,name', 'autor:id,name', 'requisicao:id,numero',
                'factura:id,invoice_number,status,total', 'pagamentos.pedidoPor:id,name',
                'pagamentos.pagoPor:id,name', 'pagamentos.tesoureiro:id,name', 'pagamentos.recibo:id,receipt_number',
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->linha($e) + [
                'autor' => $e->autor?->name,
                'requisicao' => $e->requisicao?->numero,
                'fornecedor_telefone' => $e->fornecedor?->phone,
                'fornecedor_email' => $e->fornecedor?->email,
            ],
            'itens' => $e->itens->map(fn ($i) => $this->linhaDoItem($i))->values(),
            'pagamentos' => $e->pagamentos->map(fn (PedidoDePagamento $p) => self::linhaDoPagamento($p, $this->aprovacoes))->values(),
            'aprovacoes' => $this->aprovacoes->historico($e),
            // O RASTO: cada passo da compra com quem o deu e quando.
            'rasto' => app(RastoDaCompra::class)->daEncomenda($e),
        ]);
    }

    /** Um pedido de pagamento, como o ecrã das Compras e o da Tesouraria o mostram. */
    public static function linhaDoPagamento(PedidoDePagamento $p, Aprovacoes $aprovacoes): array
    {
        $user = request()->user();

        return [
            'id' => $p->id,
            'numero' => $p->numero,
            'estado' => $p->estado,
            'estado_rotulo' => __($p->estadoRotulo()),
            'valor' => (float) $p->valor,
            'data_limite' => $p->data_limite?->format('Y-m-d'),
            'atrasado' => $p->estado === 'por_pagar' && $p->data_limite !== null && $p->data_limite->lt(today()),
            'forma_sugerida' => $p->forma_sugerida,
            'notas' => $p->notas,
            'pedido_por' => $p->pedidoPor?->name,
            'pedido_em' => $p->created_at?->format('Y-m-d H:i'),
            'tesoureiro' => $p->tesoureiro?->name,
            'motivo_recusa' => $p->motivo_recusa,
            'pago_por' => $p->pagoPor?->name,
            'pago_em' => $p->pago_em?->format('Y-m-d H:i'),
            'forma_paga' => $p->forma_paga,
            'recibo' => $p->recibo?->receipt_number,
            'aprovacao' => $p->estado === 'em_aprovacao' ? [
                'sins' => $aprovacoes->sins($p, (int) $p->ronda),
                'necessarias' => max(1, $aprovacoes->necessarias($p)),
                'pode_votar' => (bool) $user?->can('compras.pagamentos.aprovar') && $aprovacoes->podeVotar($p, $user, (int) $p->ronda),
            ] : null,
            'votos' => $aprovacoes->historico($p),
        ];
    }

    /**
     * PEDIR A APROVAÇÃO da encomenda, quando a empresa a exige.
     */
    public function pedirAprovacao(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.encomendas.manage');

        return $this->correr(
            fn () => $this->fluxo->pedirAprovacao(Encomenda::forTenant()->findOrFail($id), activeTenantId()),
            __('Encomenda enviada para aprovação.'),
        );
    }

    /**
     * APROVAR OU RECUSAR — outra autoridade. Quem fez a encomenda não vota
     * nela, e recusar exige motivo (as regras vivem em `Aprovacoes`).
     */
    public function decidir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.encomendas.aprovar');

        $dados = $request->validate([
            'aprova' => ['required', 'boolean'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->correr(
            fn () => $this->fluxo->decidir(
                Encomenda::forTenant()->findOrFail($id), activeTenantId(), $request->user(),
                (bool) $dados['aprova'], $dados['comentario'] ?? null,
            ),
            $request->boolean('aprova') ? __('A sua aprovação ficou registada.') : __('Encomenda recusada — voltou a rascunho com o seu motivo.'),
        );
    }

    /**
     * SOLICITAR PAGAMENTO — a encomenda vai à tesouraria. Quem pede não paga:
     * o pagamento é feito no ecrã da tesouraria, por quem tem essa permissão.
     */
    public function pedirPagamento(Request $request, FluxoDoPagamento $pagamentos, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.pagamentos.solicitar');

        $formas = array_column(self::formas(), 'valor');

        $dados = $request->validate([
            'valor' => ['required', 'numeric', 'min:0.01'],
            'data_limite' => ['nullable', 'date'],
            'forma' => ['nullable', 'string', 'in:' . implode(',', $formas)],
            'notas' => ['nullable', 'string', 'max:1000'],
            'tesoureiro_id' => ['nullable', 'integer', Rule::in(array_map('intval', array_column(self::tesoureiros(), 'valor')))],
        ], [
            'tesoureiro_id.in' => __('Essa pessoa não pode pagar fornecedores nesta empresa.'),
        ]);

        try {
            $p = $pagamentos->pedir(Encomenda::forTenant()->findOrFail($id), (int) activeTenantId(), (int) $request->user()->id, $dados);
        } catch (\InvalidArgumentException $ex) {
            $this->recusa($ex->getMessage());
        }

        return response()->json([
            'message' => $p->estado === 'em_aprovacao'
                ? __('Pedido :n criado — segue para aprovação antes da tesouraria.', ['n' => $p->numero])
                : __('Pedido :n enviado à tesouraria.', ['n' => $p->numero]),
            'data' => $this->linha(Encomenda::forTenant()->with(['fornecedor', 'warehouse', 'itens', 'factura', 'pagamentos'])->findOrFail($id)),
        ], 201);
    }

    private function linhaDoItem($i): array
    {
        return [
            'id' => $i->id,
            'product_id' => $i->product_id,
            'artigo' => $i->produto?->name,
            'codigo' => $i->produto?->code,
            'descricao' => $i->descricao,
            'quantidade' => (float) $i->quantidade,
            'recebida' => (float) $i->quantidade_recebida,
            'por_receber' => $i->porReceber(),
            'preco_unitario' => (float) $i->preco_unitario,
            'desconto_percent' => (float) $i->desconto_percent,
            'total' => (float) $i->total,
            'unidade' => $i->unidade,
        ];
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'compras.encomendas.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'supplier_id' => ['required', Rule::exists('invoicing_suppliers', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['nullable', Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', $tenantId)],
            'requisicao_id' => ['nullable', Rule::exists('compras_requisicoes', 'id')->where('tenant_id', $tenantId)],
            'data_encomenda' => ['nullable', 'date'],
            'entrega_prevista' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.product_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('invoicing_products', 'id')->where('tenant_id', $tenantId)],
            'linhas.*.descricao' => ['required', 'string', 'max:255'],
            'linhas.*.quantidade' => ['required', 'numeric', 'min:0.0001'],
            'linhas.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'linhas.*.desconto_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'linhas.*.unidade' => ['nullable', 'string', 'max:20'],
        ], [
            'supplier_id.required' => __('Uma encomenda precisa de fornecedor.'),
            'linhas.required' => __('Uma encomenda sem linhas não encomenda nada.'),
        ]);

        $cabecalho = [
            'supplier_id' => $dados['supplier_id'],
            'warehouse_id' => $dados['warehouse_id'] ?? null,
            'requisicao_id' => $dados['requisicao_id'] ?? null,
            'data_encomenda' => $dados['data_encomenda'] ?? today()->toDateString(),
            'entrega_prevista' => $dados['entrega_prevista'] ?? null,
            'notas' => trim($dados['notas'] ?? '') ?: null,
        ];

        try {
            if ($id) {
                $e = $this->fluxo->actualizar(
                    Encomenda::forTenant()->findOrFail($id), $tenantId, $cabecalho, $dados['linhas'],
                );
                $mensagem = __('Encomenda actualizada.');
            } else {
                $e = $this->fluxo->criar($tenantId, $request->user()?->id, $cabecalho, $dados['linhas']);
                $mensagem = __('Encomenda criada como rascunho.');
            }
        } catch (\InvalidArgumentException $ex) {
            $this->recusa($ex->getMessage());
        }

        return response()->json([
            'message' => $mensagem,
            'data' => $this->linha($e->fresh(['fornecedor', 'warehouse', 'itens', 'factura'])),
        ], $id ? 200 : 201);
    }

    /** Puxar as linhas por encomendar de uma requisição aprovada. */
    public function daRequisicao(Request $request): JsonResponse
    {
        $this->exigir($request, 'compras.encomendas.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'requisicao_id' => ['required', Rule::exists('compras_requisicoes', 'id')->where('tenant_id', $tenantId)],
            'supplier_id' => ['required', Rule::exists('invoicing_suppliers', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['nullable', Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', $tenantId)],
        ], [
            'supplier_id.required' => __('Escolha primeiro o fornecedor.'),
        ]);

        $req = Requisicao::forTenant()->with('itens')->findOrFail($dados['requisicao_id']);

        try {
            $e = $this->fluxo->daRequisicao(
                $req, $tenantId, $request->user()?->id, (int) $dados['supplier_id'],
                ['warehouse_id' => $dados['warehouse_id'] ?? null],
            );
        } catch (\InvalidArgumentException $ex) {
            $this->recusa($ex->getMessage());
        }

        return response()->json([
            'message' => __('Encomenda :enc criada da requisição :req.', [
                'enc' => $e->numero, 'req' => $req->numero,
            ]),
            'data' => $this->linha($e->fresh(['fornecedor', 'warehouse', 'itens', 'factura'])),
        ], 201);
    }

    public function enviar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.encomendas.manage');

        return $this->correr(
            fn () => $this->fluxo->enviar(Encomenda::forTenant()->findOrFail($id), activeTenantId()),
            __('Encomenda enviada ao fornecedor.'),
        );
    }

    public function confirmar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.encomendas.manage');

        return $this->correr(
            fn () => $this->fluxo->confirmar(Encomenda::forTenant()->findOrFail($id), activeTenantId()),
            __('Encomenda confirmada pelo fornecedor.'),
        );
    }

    /** O que falta de cada linha — é isto que o ecrã de recepção sugere. */
    public function recepcao(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.encomendas.receber');

        $e = Encomenda::forTenant()->with(['itens.produto:id,name,code', 'fornecedor:id,name', 'warehouse:id,name'])
            ->findOrFail($id);

        if (! $e->podeReceber()) {
            $this->recusa(__('Esta encomenda não está à espera de mercadoria.'));
        }

        return response()->json([
            'data' => $this->linha($e),
            'itens' => $e->itens->map(fn ($i) => $this->linhaDoItem($i))->values(),
        ]);
    }

    /**
     * RECEBER É A ENTRADA DE STOCK.
     *
     * Tem permissão própria porque é o acto de mais consequência deste ecrã: a
     * partir daqui há mercadoria na casa e há dinheiro a dever.
     */
    public function receber(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.encomendas.receber');

        $dados = $request->validate([
            'quantidades' => ['required', 'array', 'min:1'],
            'quantidades.*' => ['nullable', 'numeric', 'min:0'],
        ], ['quantidades.required' => __('Diga o que chegou.')]);

        return $this->correr(
            fn () => $this->fluxo->receber(
                Encomenda::forTenant()->findOrFail($id), activeTenantId(),
                $request->user()?->id, $dados['quantidades'],
            ),
            __('Mercadoria recebida — o stock já entrou.'),
        );
    }

    public function facturar(Request $request, int $id): JsonResponse
    {
        // Facturar tem permissão própria: a regularização documental pode
        // ser de outra pessoa (27/09/2026).
        $this->exigir($request, 'compras.encomendas.facturar');

        try {
            $factura = $this->fluxo->facturar(
                Encomenda::forTenant()->findOrFail($id), activeTenantId(), $request->user()?->id,
            );
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => __('Factura de compra :numero criada em rascunho.', [
                'numero' => $factura->invoice_number,
            ]),
            'data' => $this->linha(
                Encomenda::forTenant()->with(['fornecedor', 'warehouse', 'itens', 'factura'])->findOrFail($id),
            ),
        ]);
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.encomendas.manage');

        return $this->correr(
            fn () => $this->fluxo->cancelar(Encomenda::forTenant()->findOrFail($id), activeTenantId()),
            __('Encomenda cancelada.'),
        );
    }

    private function correr(callable $accao, string $sucesso): JsonResponse
    {
        try {
            $e = $accao();
        } catch (\InvalidArgumentException $ex) {
            $this->recusa($ex->getMessage());
        }

        return response()->json([
            'message' => $sucesso,
            'data' => $this->linha($e->fresh(['fornecedor', 'warehouse', 'itens', 'factura', 'pagamentos'])),
        ]);
    }
}
