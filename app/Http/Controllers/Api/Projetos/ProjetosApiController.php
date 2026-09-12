<?php

namespace App\Http\Controllers\Api\Projetos;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Projetos\Projeto;
use App\Models\Projetos\Tarefa;
use App\Models\User;
use App\Services\Invoicing\ModuleInvoiceService;
use App\Services\Projetos\FluxoDoProjeto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS PROJETOS — o orçamento, o que já se gastou, e o que falta cobrar.
 *
 * UM PROJETO TEM TRÊS NÚMEROS que se confundem e não são o mesmo: o ORÇAMENTO
 * (o tecto), o CONSUMIDO (as horas lançadas ao preço a que foram lançadas) e o
 * FACTURADO (o que já saiu em documento). O ecrã mostra os três lado a lado —
 * sem isso, facturava-se e perdia-se o rasto.
 *
 * A FACTURAÇÃO PASSA PELA PORTA ÚNICA (`ModuleInvoiceService`), a mesma do
 * hotel, da oficina e do salão. As horas facturadas ficam marcadas com a
 * factura que as levou, e é essa marca que impede facturá-las duas vezes.
 */
class ProjetosApiController extends Controller
{
    public function __construct(private readonly FluxoDoProjeto $fluxo) {}

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
        $this->exigir($request, 'projetos.view');

        $tenantId = activeTenantId();

        return response()->json([
            'estados' => collect(Projeto::ESTADOS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'clientes' => Client::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->name])->values(),
            'pessoas' => User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
                ->orderBy('name')->get(['users.id', 'users.name'])
                ->map(fn ($u) => ['valor' => (string) $u->id, 'rotulo' => $u->name])->values(),
            'permissoes' => [
                'pode_gerir' => (bool) $request->user()?->can('projetos.gerir'),
                'pode_facturar' => (bool) $request->user()?->can('projetos.facturar'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'projetos.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:20'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = Projeto::forTenant()
            ->when(($filtros['estado'] ?? 'todos') !== 'todos',
                fn ($q) => $q->where('estado', $filtros['estado']))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('nome', 'like', $t)->orWhere('codigo', 'like', $t));
            })
            ->with(['cliente:id,name', 'responsavel:id,name'])
            ->latest()
            ->paginate($filtros['por_pagina'] ?? 12);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Projeto $p) => $this->linha($p))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'total' => Projeto::forTenant()->count(),
                'activos' => Projeto::forTenant()->where('estado', 'activo')->count(),
                'concluidos' => Projeto::forTenant()->where('estado', 'concluido')->count(),
            ],
        ]);
    }

    private function linha(Projeto $p): array
    {
        $orcamento = (float) $p->orcamento;
        $consumido = $p->consumido();

        return [
            'id' => $p->id,
            'codigo' => $p->codigo,
            'nome' => $p->nome,
            'client_id' => $p->client_id,
            'cliente' => $p->cliente?->name,
            'responsavel_id' => $p->responsavel_id,
            'responsavel' => $p->responsavel?->name,
            'estado' => $p->estado,
            'estado_rotulo' => __(Projeto::ESTADOS[$p->estado] ?? $p->estado),
            'inicio' => $p->data_inicio?->format('Y-m-d'),
            'fim_previsto' => $p->data_fim_prevista?->format('Y-m-d'),
            'orcamento' => $orcamento,
            'valor_hora' => (float) $p->valor_hora,
            'descricao' => $p->descricao,
            'horas' => $p->horasLancadas(),
            'consumido' => $consumido,
            'percentagem' => $p->percentagemDoOrcamento(),
            'acima_do_orcamento' => $p->acimaDoOrcamento(),
            'aceita_horas' => $p->aceitaHoras(),
        ];
    }

    /** A ficha: os três números lado a lado, e os documentos que dela saíram. */
    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'projetos.view');

        $p = Projeto::forTenant()
            ->with(['cliente:id,name', 'responsavel:id,name', 'autor:id,name'])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->linha($p) + [
                'autor' => $p->autor?->name,
                /*
                 * O LAÇO FECHA AQUI: o que já saiu para cobrança e em que
                 * documentos. Sem isto, facturava-se e perdia-se o rasto — e
                 * ninguém sabia se aquelas horas já tinham sido cobradas.
                 */
                'facturado' => $p->facturado(),
                'horas_facturadas' => $p->horasFacturadas(),
                // O serviço devolve as três medidas juntas; o ecrã precisa
                // delas separadas — o valor para o aviso, as horas para a
                // frase, e o número de linhas para saber se há o que facturar.
                'por_facturar' => (float) ($porFacturar = $this->fluxo->porFacturar($p))['valor'],
                'horas_por_facturar' => (float) $porFacturar['horas'],
                'linhas_por_facturar' => (int) $porFacturar['linhas'],
            ],
            'facturas' => collect($p->facturas())->map(fn ($f) => [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'dia' => $f->invoice_date?->format('Y-m-d'),
                'estado' => $f->status,
                'total' => (float) $f->total,
            ])->values(),
            'tarefas' => [
                'abertas' => $p->tarefas()->whereIn('estado', Tarefa::ABERTAS)->count(),
                'total' => $p->tarefas()->count(),
            ],
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'projetos.gerir');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:200'],
            'client_id' => ['nullable', Rule::exists('invoicing_clients', 'id')->where('tenant_id', $tenantId)],
            'responsavel_id' => ['nullable', 'integer'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim_prevista' => ['nullable', 'date'],
            'orcamento' => ['nullable', 'numeric', 'min:0'],
            'valor_hora' => ['nullable', 'numeric', 'min:0'],
            'descricao' => ['nullable', 'string', 'max:2000'],
        ], [], ['nome' => __('nome')]);

        try {
            if ($id) {
                $p = $this->fluxo->actualizar(Projeto::forTenant()->findOrFail($id), $tenantId, $dados);
                $mensagem = __('Projeto actualizado.');
            } else {
                $p = $this->fluxo->criar($tenantId, $request->user()?->id, $dados);
                $mensagem = __('Projeto criado como rascunho.');
            }
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => $mensagem,
            'data' => $this->linha($p->fresh(['cliente', 'responsavel'])),
        ], $id ? 200 : 201);
    }

    public function estado(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'projetos.gerir');

        $dados = $request->validate([
            'estado' => ['required', Rule::in(array_keys(Projeto::ESTADOS))],
        ]);

        try {
            $p = $this->fluxo->mudarEstado(
                Projeto::forTenant()->findOrFail($id), activeTenantId(), $dados['estado'],
            );
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => __('Projeto :codigo: :estado.', [
                'codigo' => $p->codigo, 'estado' => __(Projeto::ESTADOS[$p->estado] ?? $p->estado),
            ]),
            'data' => $this->linha($p->fresh(['cliente', 'responsavel'])),
        ]);
    }

    /**
     * FACTURAR AS HORAS — e é uma fronteira de autoridade.
     *
     * Facturar emite um documento a um cliente: pede permissão própria, e não
     * a de gerir projetos. Quem organiza o trabalho não é necessariamente quem
     * pode cobrar por ele.
     */
    public function facturar(Request $request, int $id, ModuleInvoiceService $facturacao): JsonResponse
    {
        $this->exigir($request, 'projetos.facturar');

        try {
            $factura = $this->fluxo->facturarHoras(
                Projeto::forTenant()->findOrFail($id), activeTenantId(), $facturacao,
            );
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => __('Factura :numero criada em rascunho com as horas do projeto.', [
                'numero' => $factura->invoice_number,
            ]),
            'factura' => [
                'id' => $factura->id,
                'numero' => $factura->invoice_number,
                'total' => (float) $factura->total,
            ],
        ]);
    }
}
