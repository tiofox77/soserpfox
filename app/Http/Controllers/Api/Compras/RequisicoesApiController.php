<?php

namespace App\Http\Controllers\Api\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\Requisicao;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Services\Compras\FluxoDaRequisicao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS REQUISIÇÕES DE COMPRA — o pedido interno, antes de haver fornecedor.
 *
 * QUEM PEDE NÃO É QUEM APROVA, e é essa separação a razão de ser de uma
 * requisição: sem ela, o circuito é só burocracia. Por isso `manage` (criar,
 * editar, submeter) e `decidir` (aprovar, recusar) são duas permissões
 * diferentes, e a porta exige a que corresponde ao acto — não basta esconder
 * botões no ecrã.
 *
 * UMA LINHA PODE NÃO ESTAR NO CATÁLOGO. Pedir algo que ainda não existe como
 * artigo é o caso mais comum de todos — uma peça, um serviço, uma reparação —
 * e obrigar a criar o artigo primeiro era obrigar a inventar dados.
 */
class RequisicoesApiController extends Controller
{
    public function __construct(private readonly FluxoDaRequisicao $fluxo) {}

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
        $this->exigir($request, 'compras.requisicoes.view');

        return response()->json([
            'estados' => collect(Requisicao::ESTADOS)
                ->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'armazens' => Warehouse::forTenant()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($a) => ['valor' => (string) $a->id, 'rotulo' => $a->name])->values(),
            'permissoes' => [
                'pode_gerir' => (bool) $request->user()?->can('compras.requisicoes.manage'),
                'pode_decidir' => (bool) $request->user()?->can('compras.requisicoes.decidir'),
            ],
        ]);
    }

    /** As sugestões do catálogo, a partir de duas letras. */
    public function artigos(Request $request): JsonResponse
    {
        $this->exigir($request, 'compras.requisicoes.view');

        $termo = trim((string) $request->input('procura'));

        if (mb_strlen($termo) < 2) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => Product::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('name', 'like', "%{$termo}%")->orWhere('code', 'like', "%{$termo}%"))
                ->orderBy('name')->limit(10)->get(['id', 'name', 'code', 'unit', 'cost'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'nome' => $p->name,
                    'codigo' => $p->code,
                    'unidade' => $p->unit,
                    'custo' => (float) $p->cost,
                ])->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'compras.requisicoes.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:20'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = Requisicao::forTenant()
            ->when(($filtros['estado'] ?? 'todos') !== 'todos', fn ($q) => $q->where('estado', $filtros['estado']))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('numero', 'like', $t)
                    ->orWhere('justificacao', 'like', $t)
                    ->orWhereHas('itens', fn ($i) => $i->where('descricao', 'like', $t)));
            })
            ->with(['autor:id,name', 'warehouse:id,name'])
            ->withCount('itens')
            ->latest()
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Requisicao $r) => $this->linha($r))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'rascunhos' => Requisicao::forTenant()->where('estado', 'rascunho')->count(),
                'submetidas' => Requisicao::forTenant()->where('estado', 'submetida')->count(),
                'aprovadas' => Requisicao::forTenant()->where('estado', 'aprovada')->count(),
                'total' => Requisicao::forTenant()->count(),
            ],
        ]);
    }

    private function linha(Requisicao $r): array
    {
        return [
            'id' => $r->id,
            'numero' => $r->numero,
            'estado' => $r->estado,
            'estado_rotulo' => __(Requisicao::ESTADOS[$r->estado] ?? $r->estado),
            'warehouse_id' => $r->warehouse_id,
            'armazem' => $r->warehouse?->name,
            'necessaria_em' => $r->necessaria_em?->format('Y-m-d'),
            'justificacao' => $r->justificacao,
            'motivo_recusa' => $r->motivo_recusa,
            'autor' => $r->autor?->name,
            'linhas' => (int) ($r->itens_count ?? $r->itens()->count()),
            'criada_em' => $r->created_at?->format('Y-m-d'),
            'pode_editar' => $r->podeEditar(),
            'pode_encomendar' => $r->podeEncomendar(),
        ];
    }

    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.requisicoes.view');

        $r = Requisicao::forTenant()
            ->with([
                'itens.produto:id,name,code', 'warehouse:id,name',
                'autor:id,name', 'decisor:id,name',
                'encomendas:id,numero,requisicao_id,estado',
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->linha($r) + [
                'decisor' => $r->decisor?->name,
                'decidida_em' => $r->decidida_em?->format('Y-m-d H:i'),
            ],
            'itens' => $r->itens->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'artigo' => $i->produto?->name,
                'codigo' => $i->produto?->code,
                'descricao' => $i->descricao,
                'quantidade' => (float) $i->quantidade,
                'encomendada' => (float) $i->quantidade_encomendada,
                'por_encomendar' => $i->porEncomendar(),
                'custo_estimado' => $i->custo_estimado !== null ? (float) $i->custo_estimado : null,
                'unidade' => $i->unidade,
                'notas' => $i->notas,
            ])->values(),
            'encomendas' => $r->encomendas->map(fn ($e) => [
                'id' => $e->id,
                'numero' => $e->numero,
                'estado' => $e->estado,
            ])->values(),
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'compras.requisicoes.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'warehouse_id' => ['nullable', Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', $tenantId)],
            'necessaria_em' => ['nullable', 'date'],
            'justificacao' => ['nullable', 'string', 'max:2000'],
            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.product_id' => ['nullable', 'integer'],
            'linhas.*.descricao' => ['required', 'string', 'max:255'],
            'linhas.*.quantidade' => ['required', 'numeric', 'min:0.0001'],
            'linhas.*.custo_estimado' => ['nullable', 'numeric', 'min:0'],
            'linhas.*.unidade' => ['nullable', 'string', 'max:20'],
            'linhas.*.notas' => ['nullable', 'string', 'max:255'],
        ], [
            'linhas.required' => __('Uma requisição sem linhas não pede nada.'),
        ]);

        $cabecalho = [
            'warehouse_id' => $dados['warehouse_id'] ?? null,
            'necessaria_em' => $dados['necessaria_em'] ?? null,
            'justificacao' => trim($dados['justificacao'] ?? '') ?: null,
        ];

        try {
            if ($id) {
                $r = $this->fluxo->actualizar(
                    Requisicao::forTenant()->findOrFail($id), $tenantId, $cabecalho, $dados['linhas'],
                );
                $mensagem = __('Requisição actualizada.');
            } else {
                $r = $this->fluxo->criar($tenantId, $request->user()?->id, $cabecalho, $dados['linhas']);
                $mensagem = __('Requisição criada como rascunho.');
            }
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => $mensagem,
            'data' => $this->linha($r->fresh(['autor', 'warehouse'])->loadCount('itens')),
        ], $id ? 200 : 201);
    }

    public function submeter(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.requisicoes.manage');

        return $this->correr(
            fn () => $this->fluxo->submeter(Requisicao::forTenant()->findOrFail($id), activeTenantId()),
            __('Requisição submetida para aprovação.'),
        );
    }

    /**
     * APROVAR É OUTRA AUTORIDADE.
     *
     * Quem pede não é quem aprova: `compras.requisicoes.decidir` é uma
     * permissão à parte da de gerir, e é a única coisa que faz do circuito mais
     * do que burocracia.
     */
    public function aprovar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.requisicoes.decidir');

        return $this->correr(
            fn () => $this->fluxo->aprovar(
                Requisicao::forTenant()->findOrFail($id), activeTenantId(), $request->user()?->id,
            ),
            __('Requisição aprovada — já pode virar encomenda.'),
        );
    }

    public function rejeitar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.requisicoes.decidir');

        $dados = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ], [], ['motivo' => __('motivo')]);

        return $this->correr(
            fn () => $this->fluxo->rejeitar(
                Requisicao::forTenant()->findOrFail($id), activeTenantId(),
                $request->user()?->id, $dados['motivo'],
            ),
            __('Requisição recusada.'),
        );
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'compras.requisicoes.manage');

        return $this->correr(
            fn () => $this->fluxo->cancelar(Requisicao::forTenant()->findOrFail($id), activeTenantId()),
            __('Requisição cancelada.'),
        );
    }

    /** Corre a acção e transforma um erro de domínio num 422 que se lê. */
    private function correr(callable $accao, string $sucesso): JsonResponse
    {
        try {
            $r = $accao();
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => $sucesso,
            'data' => $this->linha($r->fresh(['autor', 'warehouse'])->loadCount('itens')),
        ]);
    }
}
