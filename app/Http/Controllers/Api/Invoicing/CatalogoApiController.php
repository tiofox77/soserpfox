<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\Catalogos;
use App\Services\Invoicing\ExtratoDaParte;
use App\Support\Geografia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * OS CATÁLOGOS, para o ecrã em React — uma API só para seis catálogos.
 *
 * Tudo o que distingue um catálogo do outro vem do esquema (`Catalogos`):
 * regras, guardas, o que se normaliza, quem pode o quê. Este controlador
 * valida com as regras do esquema, chama as suas funções, e devolve linhas
 * na forma que a tabela mostra. Cada verbo exige a sua permissão.
 */
class CatalogoApiController extends Controller
{
    public function opcoes(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['ver']);

        $tenantId = activeTenantId();
        $this->antes($def, $tenantId);

        return response()->json([
            'titulo' => __($def['titulo']),
            'singular' => __($def['singular']),
            'icone' => $def['icone'],
            // A COR E A FRASE DA FAIXA. Cada catálogo tinha a sua cor no ecrã
            // em Blade — os fornecedores laranja, as categorias ciano, as
            // marcas rosa — e não era enfeite: reconhece-se a página pela
            // faixa antes de ler o título.
            'cor' => $def['cor'] ?? 'primaria',
            'descricao' => __($def['descricao'] ?? ''),
            // «Novo Fornecedor», «Nova Categoria» — o rótulo do botão de criar,
            // por catálogo. Um «Novo(a) fornecedor» montado à mão a partir do
            // singular não é português, e noutras línguas é pior.
            'novo' => __($def['novo'] ?? 'Novo registo'),
            'pesquisa' => __($def['pesquisa_ajuda']),
            'colunas' => array_map(fn ($c) => array_merge($c, ['rotulo' => __($c['rotulo'])]), $def['colunas']),
            'campos' => $def['campos'],
            'filtros' => array_map(fn ($f) => array_merge($f, [
                'rotulo' => __($f['rotulo']),
                'tipo' => $f['tipo'] ?? 'escolha',
                'ajuda' => isset($f['ajuda']) ? __($f['ajuda']) : null,
            ]), $def['filtros']),
            // Se este catálogo aceita o intervalo de datas de criação: é o
            // ecrã que desenha os dois campos, e só onde eles servem.
            'datas' => ! empty($def['datas']),
            // Se este catálogo tem extrato — a janela de VER com as contas.
            'extrato' => ! empty($def['extrato']),
            'accoes' => $def['accoes'],
            'referencias' => $this->referencias($def, $tenantId),
            'geografia' => ! empty($def['geografia']) ? [
                'paises' => collect(Geografia::paises())->map(fn ($nome, $codigo) => ['valor' => $codigo, 'rotulo' => $nome])->values(),
                'provincias' => Geografia::provincias(),
                'municipios' => collect(Geografia::provincias())->mapWithKeys(fn ($p) => [$p => Geografia::municipios($p)]),
                'pais_padrao' => Geografia::PAIS_PADRAO,
            ] : null,
            'permissoes' => [
                'pode_escrever' => (bool) $request->user()?->can($def['permissoes']['criar']),
            ],
            'voltar' => $def['rota'],
        ]);
    }

    public function index(Request $request, string $tipo): AnonymousResourceCollection
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['ver']);

        $tenantId = activeTenantId();
        $this->antes($def, $tenantId);

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            // O intervalo de datas, só nos catálogos que o declaram — a
            // consulta ignora-o nos outros, mas aceitar o que não se usa
            // convida a acreditar que filtra.
            'de' => [empty($def['datas']) ? 'prohibited' : 'nullable', 'date'],
            'ate' => [empty($def['datas']) ? 'prohibited' : 'nullable', 'date'],
        ] + array_fill_keys(array_column($def['filtros'], 'chave'), ['nullable', 'string', 'max:80']));

        $referencias = $this->referencias($def, $tenantId);

        $pagina = Catalogos::consulta($def, $tenantId, $filtros)
            ->paginate($filtros['por_pagina'] ?? 15)
            ->withQueryString()
            ->through(fn (Model $m) => Catalogos::linha($def, $m, $referencias));

        return JsonResource::collection($pagina);
    }

    public function store(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['criar']);

        $tenantId = activeTenantId();
        $dados = $this->validar($request, $def, null, $tenantId);

        $m = $def['modelo']::create(array_merge($dados, ['tenant_id' => $tenantId]));
        $this->depois($def, $m);

        return response()->json([
            'data' => Catalogos::linha($def, $m->fresh(), $this->referencias($def, $tenantId)),
            'message' => __(':nome criado(a).', ['nome' => __($def['singular'])]),
        ], 201);
    }

    public function update(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['editar']);

        $tenantId = activeTenantId();
        $m = $this->encontrar($def, $tenantId, $id);
        $dados = $this->validar($request, $def, $m, $tenantId);

        $m->update($dados);
        $this->depois($def, $m);

        return response()->json([
            'data' => Catalogos::linha($def, $m->fresh(), $this->referencias($def, $tenantId)),
            'message' => __(':nome guardado(a).', ['nome' => __($def['singular'])]),
        ]);
    }

    public function destroy(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['apagar']);

        $m = $this->encontrar($def, activeTenantId(), $id);

        // A guarda é a do esquema: com compras, artigos ou stock não se apaga.
        if (! $def['accoes']['apagar'] || ! ($def['pode_apagar'])($m)) {
            return response()->json(['message' => __('Não é possível apagar: está em uso.')], 422);
        }

        if (isset($def['ao_apagar'])) {
            ($def['ao_apagar'])($m);
        }

        $m->delete();

        return response()->json(['message' => __(':nome apagado(a).', ['nome' => __($def['singular'])])]);
    }

    /** Activar/desactivar, ou tornar padrão — o que o esquema permitir. */
    public function accao(Request $request, string $tipo, int $id, string $accao): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['editar']);

        abort_unless(in_array($accao, ['activar', 'padrao'], true) && ! empty($def['accoes'][$accao]), 404);

        $tenantId = activeTenantId();
        $m = $this->encontrar($def, $tenantId, $id);

        if ($accao === 'activar') {
            $m->update(['is_active' => ! $m->is_active]);
            $mensagem = $m->is_active ? __('Activado(a).') : __('Desactivado(a).');
        } else {
            ($def['padrao'])($m);
            $mensagem = __('Definido(a) como padrão.');
        }

        return response()->json([
            'data' => Catalogos::linha($def, $m->fresh(), $this->referencias($def, $tenantId)),
            'message' => $mensagem,
        ]);
    }

    /**
     * O EXTRATO DE UM FORNECEDOR — o que já lhe comprámos.
     *
     * É a ficha que o ecrã de sempre abria no modal de ver: as contas, as
     * últimas facturas de compra, os artigos que mais lhe compramos e a
     * frequência mês a mês. É o que se olha antes de negociar um preço ou de
     * decidir se vale a pena mudar de fornecedor.
     *
     * SÓ NOS FORNECEDORES: uma marca ou uma unidade de medida não têm extrato
     * nenhum, e um endereço que responde a todos os catálogos com listas vazias
     * faz acreditar que o fornecedor não comprou nada.
     */
    public function extrato(Request $request, string $tipo, int $id, ExtratoDaParte $extrato): JsonResponse
    {
        abort_unless($tipo === 'fornecedores', 404, __('Este catálogo não tem extrato.'));

        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['ver']);

        $fornecedor = $this->encontrar($def, activeTenantId(), $id);

        return response()->json($extrato->doFornecedor($fornecedor));
    }

    /** O logótipo do fornecedor: um ficheiro na pasta dele, como sempre. */
    public function logotipo(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['editar']);
        abort_unless(! empty($def['accoes']['logotipo']), 404);

        $request->validate(['logotipo' => ['required', 'image', 'max:2048']]);

        $tenantId = activeTenantId();
        $m = $this->encontrar($def, $tenantId, $id);

        $pasta = 'suppliers/' . $m->id;
        $nome = 'logo_' . Str::slug($m->name) . '.' . $request->file('logotipo')->getClientOriginalExtension();

        if ($m->logo && Storage::disk('public')->exists($m->logo)) {
            Storage::disk('public')->delete($m->logo);
        }

        $m->update(['logo' => $request->file('logotipo')->storeAs($pasta, $nome, 'public')]);

        return response()->json([
            'data' => Catalogos::linha($def, $m->fresh(), $this->referencias($def, $tenantId)),
            'message' => __('Logótipo guardado.'),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function definicao(string $tipo): array
    {
        abort_unless(Catalogos::existe($tipo), 404, __('Catálogo desconhecido.'));

        return Catalogos::um($tipo);
    }

    private function antes(array $def, int $tenantId): void
    {
        if (isset($def['antes'])) {
            ($def['antes'])($tenantId);
        }
    }

    private function referencias(array $def, int $tenantId): array
    {
        return isset($def['referencias']) ? ($def['referencias'])($tenantId) : [];
    }

    private function encontrar(array $def, int $tenantId, int $id): Model
    {
        return $def['modelo']::query()->where('tenant_id', $tenantId)->findOrFail($id);
    }

    /**
     * As regras do esquema, mais a validação própria do catálogo (o nome
     * único, a categoria-mãe desta empresa), e depois o `preparar`.
     */
    private function validar(Request $request, array $def, ?Model $existente, int $tenantId): array
    {
        $chaves = array_column($def['campos'], 'chave');
        $dados = $request->validate($def['regras']);
        $dados = array_intersect_key($request->only($chaves), array_flip($chaves)) + $dados;

        if (isset($def['validar'])) {
            $erros = ($def['validar'])($dados, $existente, $tenantId);
            if ($erros) {
                throw ValidationException::withMessages(array_map(fn ($e) => [$e], $erros));
            }
        }

        if (isset($def['preparar'])) {
            $dados = ($def['preparar'])($dados, $existente, $tenantId);
        }

        return array_intersect_key($dados, array_flip(array_merge($chaves, ['sort_order', 'saft_code', 'compound_tax', 'address', 'country', 'province', 'municipality', 'neighbourhood', 'city', 'postal_code'])));
    }

    private function depois(array $def, Model $m): void
    {
        if (isset($def['depois'])) {
            ($def['depois'])($m);
        }
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
