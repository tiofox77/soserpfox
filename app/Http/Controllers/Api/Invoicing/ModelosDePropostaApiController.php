<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\QuoteTemplate;
use App\Services\Invoicing\Propostas\EdicaoDeModelo;
use App\Services\Invoicing\Propostas\GestaoDeModelos;
use App\Services\Invoicing\Propostas\ModelosDeArranque;
use App\Services\Invoicing\Propostas\TiposDeBloco;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OS MODELOS DE PROPOSTA, para os ecrãs em React: a lista e o editor.
 *
 * Vivem sob as permissões de orçamento — quem faz orçamentos é quem precisa
 * de mexer nos modelos. A lista grava pela `GestaoDeModelos` e o editor pela
 * `EdicaoDeModelo`, as mesmas dos ecrãs Livewire. O editor é sem estado: cada
 * acção abre o modelo, aplica, grava e devolve o estado com a pré-visualização.
 */
class ModelosDePropostaApiController extends Controller
{
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.quotes.view');

        return response()->json([
            'arranque' => collect(ModelosDeArranque::catalogo())->map(fn ($m, $chave) => [
                'chave' => $chave, 'nome' => $m['nome'], 'descricao' => $m['descricao'], 'icone' => $m['icone'], 'cor' => $m['cor'],
            ])->values(),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('invoicing.sales.quotes.create'),
                'pode_editar' => (bool) $request->user()?->can('invoicing.sales.quotes.edit'),
                'pode_eliminar' => (bool) $request->user()?->can('invoicing.sales.quotes.delete'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.quotes.view');
        $f = $request->validate(['procura' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);

        $pagina = $this->gestao()->lista($f['procura'] ?? '')->paginate(12)->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn (QuoteTemplate $m) => $this->linha($m))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'per_page' => $pagina->perPage(), 'total' => $pagina->total()],
        ]);
    }

    /** Cria de um modelo de arranque, ou vazio. Devolve o modelo para se abrir o editor. */
    public function criar(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.quotes.create');
        $d = $request->validate(['arranque' => ['nullable', 'string', 'max:40']]);

        try {
            $modelo = ($d['arranque'] ?? null)
                ? $this->gestao()->criarDeArranque($d['arranque'], $request->user()->id)
                : $this->gestao()->criarVazio($request->user()->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->linha($modelo->loadCount('orcamentos')), 'message' => __('Modelo criado.')], 201);
    }

    public function duplicar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.quotes.create');

        try {
            $copia = $this->gestao()->duplicar($id, $request->user()->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->linha($copia->loadCount('orcamentos')), 'message' => __('Modelo duplicado.')], 201);
    }

    public function tornarPadrao(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.quotes.edit');

        try {
            $modelo = $this->gestao()->tornarPadrao($id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => __('":nome" passa a ser o modelo padrão.', ['nome' => $modelo->nome])]);
    }

    public function eliminar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.quotes.delete');

        try {
            $this->gestao()->eliminar($id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => __('Modelo eliminado.')]);
    }

    /* ─── O editor ────────────────────────────────────────────────────── */

    public function editor(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.quotes.edit');

        try {
            $edicao = new EdicaoDeModelo($this->gestao()->abrir($id), null, $request->input('seleccionado'));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'estado' => $edicao->estado(),
            'previa' => $edicao->previa(activeTenant()),
            'catalogo' => collect(TiposDeBloco::catalogo())->map(fn ($t, $tipo) => ['tipo' => $tipo, 'nome' => $t['nome'], 'icone' => $t['icone'] ?? 'fa-square', 'ajuda' => $t['ajuda'] ?? '', 'padroes' => $t['padroes'] ?? []])->values(),
            'variaveis' => TiposDeBloco::variaveis(),
            'estilos_padrao' => QuoteTemplate::ESTILOS_PADRAO,
        ]);
    }

    /** Uma acção do editor: aplica, grava e devolve o estado novo com a pré-visualização. */
    public function accao(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.quotes.edit');
        $d = $request->validate([
            'accao' => ['required', 'in:adicionar,remover,duplicar,mover,reordenar,campo,layout,pagina,estilo,renomear,guardar'],
            'seleccionado' => ['nullable', 'string', 'max:40'],
            'tipo' => ['nullable', 'string', 'max:40'],
            'id' => ['nullable', 'string', 'max:40'],
            'direccao' => ['nullable', 'integer', 'in:-1,1'],
            'ids' => ['nullable', 'array'],
            'campo' => ['nullable', 'string', 'max:60'],
            'chave' => ['nullable', 'string', 'max:60'],
            'valor' => ['nullable'],
            'layout' => ['nullable', 'array'],
            'nome' => ['nullable', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $edicao = new EdicaoDeModelo($this->gestao()->abrir($id), null, $d['seleccionado'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        match ($d['accao']) {
            'adicionar' => $edicao->adicionar((string) ($d['tipo'] ?? '')),
            'remover' => $edicao->remover((string) ($d['id'] ?? '')),
            'duplicar' => $edicao->duplicar((string) ($d['id'] ?? '')),
            'mover' => $edicao->mover((string) ($d['id'] ?? ''), (int) ($d['direccao'] ?? 1)),
            'reordenar' => $edicao->reordenar(array_map('strval', $d['ids'] ?? [])),
            'campo' => $edicao->campo((string) ($d['id'] ?? $edicao->seleccionado ?? ''), (string) ($d['campo'] ?? ''), $d['valor'] ?? null),
            'layout' => $edicao->layout((string) ($d['id'] ?? ''), (array) ($d['layout'] ?? [])),
            'pagina' => $edicao->adicionarPagina(),
            'estilo' => $edicao->estilo((string) ($d['chave'] ?? ''), $d['valor'] ?? null),
            'renomear' => $edicao->renomear((string) ($d['nome'] ?? $edicao->nome), (string) ($d['descricao'] ?? $edicao->descricao)),
            default => null,
        };

        $edicao->gravar();

        return response()->json([
            'estado' => $edicao->estado(),
            'previa' => $edicao->previa(activeTenant()),
            'message' => $d['accao'] === 'guardar' ? __('Modelo guardado.') : null,
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function gestao(): GestaoDeModelos
    {
        return new GestaoDeModelos((int) activeTenantId());
    }

    private function linha(QuoteTemplate $m): array
    {
        return [
            'id' => $m->id,
            'nome' => $m->nome,
            'descricao' => $m->descricao,
            'sector' => $m->sector,
            'is_default' => (bool) $m->is_default,
            'is_active' => (bool) ($m->is_active ?? true),
            'blocos_n' => count((array) $m->blocos),
            'orcamentos_n' => (int) ($m->orcamentos_count ?? 0),
            'cor' => ($m->estilos['cor_principal'] ?? QuoteTemplate::ESTILOS_PADRAO['cor_principal']),
            'actualizado' => optional($m->updated_at)->format('d/m/Y H:i'),
            'editor' => url("invoicing/sales/quote-templates/{$m->id}/edit"),
            'previa' => url("invoicing/sales/quote-templates/{$m->id}/preview"),
        ];
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
