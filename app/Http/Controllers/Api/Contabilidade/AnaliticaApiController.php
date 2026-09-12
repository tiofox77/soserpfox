<?php

namespace App\Http\Controllers\Api\Contabilidade;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AnalyticDimension;
use App\Models\Accounting\AnalyticTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A CONTABILIDADE ANALÍTICA — as dimensões e as suas etiquetas.
 *
 * Uma DIMENSÃO é uma pergunta que se faz a cada lançamento («que projecto?»,
 * «que loja?»); as ETIQUETAS são as respostas possíveis. Uma dimensão
 * obrigatória é uma pergunta que não se pode deixar em branco.
 *
 * O QUE ESTAVA PARTIDO, e era grave:
 *
 *  · EDITAR UMA ETIQUETA CRIAVA OUTRA. O `editTag()` carregava a etiqueta no
 *    formulário e o `saveTag()` fazia sempre `create()` — quem corrigisse um
 *    nome ficava com duas etiquetas iguais e os lançamentos repartidos entre
 *    elas.
 *  · NÃO HAVIA ESCOPO DE EMPRESA: `AnalyticTag::findOrFail($id)` lia a etiqueta
 *    de outra companhia pelo id, e gravar uma etiqueta não verificava se a
 *    dimensão escolhida era desta casa.
 *  · A DIMENSÃO NÃO SE EDITAVA NEM SE APAGAVA: `saveDimension()` só criava, e
 *    um código escrito com um erro ficava lá para sempre.
 *  · E O CÓDIGO NÃO ERA ÚNICO em lado nenhum.
 */
class AnaliticaApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function tenantId(): int
    {
        return (int) activeTenantId();
    }

    /** Uma dimensão desta empresa — o escopo que o `findOrFail` não tinha. */
    private function dimensaoDaCasa(int $id): AnalyticDimension
    {
        return AnalyticDimension::where('tenant_id', $this->tenantId())->findOrFail($id);
    }

    /**
     * Uma etiqueta desta empresa.
     *
     * A tabela das etiquetas não tem `tenant_id` — pendura da dimensão — e por
     * isso o escopo faz-se pelo `whereHas`. Sem ele, o id bastava para ler e
     * mexer na etiqueta de outra companhia.
     */
    private function etiquetaDaCasa(int $id): AnalyticTag
    {
        return AnalyticTag::whereHas(
            'dimension',
            fn ($q) => $q->where('tenant_id', $this->tenantId())
        )->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.analytics.view');

        $tenantId = $this->tenantId();

        $filtros = $request->validate([
            'dimensao' => ['nullable', 'integer'],
            'procura' => ['nullable', 'string', 'max:60'],
            'estado' => ['nullable', Rule::in(['todas', 'activas', 'inactivas'])],
        ]);

        $dimensoes = AnalyticDimension::where('tenant_id', $tenantId)
            ->withCount(['tags', 'tags as etiquetas_activas' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')->get();

        /*
         * A DIMENSÃO ESCOLHIDA, ou a primeira.
         *
         * O ecrã antigo abria com a lista de etiquetas vazia até se carregar
         * numa dimensão, e nada dizia que era preciso — parecia que não havia
         * etiquetas nenhumas.
         */
        $escolhida = $filtros['dimensao'] ?? null;

        if ($escolhida && ! $dimensoes->contains('id', (int) $escolhida)) {
            $escolhida = null;
        }

        $escolhida = (int) ($escolhida ?: ($dimensoes->first()->id ?? 0));

        $etiquetas = $escolhida === 0 ? collect() : AnalyticTag::where('dimension_id', $escolhida)
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('code', 'like', $t)->orWhere('name', 'like', $t));
            })
            ->when(($filtros['estado'] ?? 'todas') === 'activas', fn ($q) => $q->where('is_active', true))
            ->when(($filtros['estado'] ?? 'todas') === 'inactivas', fn ($q) => $q->where('is_active', false))
            ->orderBy('code')->get();

        return response()->json([
            'dimensoes' => $dimensoes->map(fn (AnalyticDimension $d) => [
                'id' => $d->id,
                'codigo' => $d->code,
                'nome' => $d->name,
                'obrigatoria' => (bool) $d->is_mandatory,
                'etiquetas' => (int) $d->tags_count,
                'etiquetas_activas' => (int) $d->etiquetas_activas,
            ])->values(),

            'escolhida' => $escolhida ?: null,

            'etiquetas' => $etiquetas->map(fn (AnalyticTag $e) => [
                'id' => $e->id,
                'codigo' => $e->code,
                'nome' => $e->name,
                'descricao' => $e->description,
                'activa' => (bool) $e->is_active,
            ])->values(),

            'resumo' => [
                'dimensoes' => $dimensoes->count(),
                'obrigatorias' => $dimensoes->where('is_mandatory', true)->count(),
                'etiquetas' => (int) $dimensoes->sum('tags_count'),
            ],

            'permissoes' => [
                // A analítica só tem `view` no catálogo de permissões; gerir os
                // seus catálogos é do mesmo nível dos centros de custo.
                'gerir' => (bool) $request->user()?->can('accounting.cost-centers.manage'),
            ],
        ]);
    }

    /* ─── As dimensões ─────────────────────────────────────────────────── */

    public function guardarDimensao(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'accounting.cost-centers.manage');

        $tenantId = $this->tenantId();

        // Confirma que é desta empresa ANTES de validar contra ela.
        $dimensao = $id ? $this->dimensaoDaCasa($id) : new AnalyticDimension();

        $dados = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('analytic_dimensions', 'code')->where('tenant_id', $tenantId)->ignore($id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'is_mandatory' => ['boolean'],
        ], [], ['code' => __('código'), 'name' => __('nome')]);

        $dimensao->fill([
            'tenant_id' => $tenantId,
            'code' => trim($dados['code']),
            'name' => trim($dados['name']),
            'is_mandatory' => (bool) ($dados['is_mandatory'] ?? false),
        ])->save();

        return response()->json([
            'message' => $id ? __('Dimensão actualizada.') : __('Dimensão criada.'),
            'id' => $dimensao->id,
        ], $id ? 200 : 201);
    }

    public function apagarDimensao(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.cost-centers.manage');

        $dimensao = $this->dimensaoDaCasa($id);

        // Apagar a dimensão deixaria as etiquetas dela soltas, a apontar para
        // uma pergunta que já não existe.
        $etiquetas = AnalyticTag::where('dimension_id', $id)->count();

        if ($etiquetas > 0) {
            throw ValidationException::withMessages([
                'geral' => [__('Esta dimensão tem :n etiqueta(s). Apague-as primeiro.', ['n' => $etiquetas])],
            ]);
        }

        $nome = $dimensao->name;
        $dimensao->delete();

        return response()->json(['message' => __('Dimensão :nome eliminada.', ['nome' => $nome])]);
    }

    /* ─── As etiquetas ─────────────────────────────────────────────────── */

    public function guardarEtiqueta(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'accounting.cost-centers.manage');

        // EDITAR EDITA. O ecrã antigo carregava a etiqueta e gravava sempre uma
        // nova: corrigir um nome deixava duas etiquetas iguais e os lançamentos
        // repartidos entre elas.
        $etiqueta = $id ? $this->etiquetaDaCasa($id) : new AnalyticTag();

        $dados = $request->validate([
            'dimension_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ], [], ['dimension_id' => __('dimensão'), 'code' => __('código'), 'name' => __('nome')]);

        // A DIMENSÃO É DESTA EMPRESA: gravar não verificava, e uma etiqueta
        // podia ir pendurar-se na dimensão de outra companhia.
        $dimensao = $this->dimensaoDaCasa((int) $dados['dimension_id']);

        // O código é único DENTRO DA DIMENSÃO: dois «LOJA-1» na mesma pergunta
        // não se distinguem em relatório nenhum.
        $repetido = AnalyticTag::where('dimension_id', $dimensao->id)
            ->where('code', trim($dados['code']))
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->exists();

        if ($repetido) {
            throw ValidationException::withMessages([
                'code' => [__('Já existe uma etiqueta com esse código nesta dimensão.')],
            ]);
        }

        $etiqueta->fill([
            'dimension_id' => $dimensao->id,
            'code' => trim($dados['code']),
            'name' => trim($dados['name']),
            'description' => $dados['description'] ?? null,
            'is_active' => (bool) ($dados['is_active'] ?? true),
        ])->save();

        return response()->json([
            'message' => $id ? __('Etiqueta actualizada.') : __('Etiqueta criada.'),
        ], $id ? 200 : 201);
    }

    public function apagarEtiqueta(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.cost-centers.manage');

        $etiqueta = $this->etiquetaDaCasa($id);
        $nome = $etiqueta->name;

        $etiqueta->delete();

        return response()->json(['message' => __('Etiqueta :nome eliminada.', ['nome' => $nome])]);
    }
}
