<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\InvoicingSeries;
use App\Services\Invoicing\GestaoDeSeries;
use App\Services\Invoicing\GestorDeSeries;
use App\Services\Invoicing\SeriesCatalog;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A GESTÃO DAS SÉRIES DE DOCUMENTOS, para o ecrã em React.
 *
 * A ficha grava-se pelo `GestaoDeSeries`, o mesmo que o Livewire chama. Ver
 * é a permissão das séries; escrever é a mesma — o ecrã de sempre não
 * distingue, e este não inventa uma nova.
 */
class SeriesApiController extends Controller
{
    public function opcoes(Request $request, GestorDeSeries $catalogo): JsonResponse
    {
        $this->exigir($request, 'invoicing.series.view');

        $tipos = collect(GestaoDeSeries::TIPOS)->map(fn ($t) => [
            'valor' => $t,
            'rotulo' => __(GestorDeSeries::TIPOS[$t]['nome'] ?? ($t === 'transport' ? 'Guia de Transporte' : $t)),
            'prefixo' => $catalogo->prefixoDoCatalogo($t),
        ])->values();

        return response()->json([
            'tipos' => $tipos,
            'metodos' => collect(GestaoDeSeries::METODOS)->map(fn ($m) => ['valor' => $m, 'rotulo' => $m])->values(),
            'metodo_padrao' => InvoicingSeries::INVOICING_FEPC,
            'ano' => (int) now()->year,
            'permissoes' => ['pode_escrever' => true],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.series.view');

        $f = $request->validate(['procura' => ['nullable', 'string', 'max:60'], 'tipo' => ['nullable', 'string', 'max:30'], 'page' => ['nullable', 'integer', 'min:1']]);

        $pagina = InvoicingSeries::forTenant(activeTenantId())
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('document_type', $v))
            ->when($f['procura'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%{$v}%")->orWhere('series_code', 'like', "%{$v}%")))
            ->orderBy('document_type')->orderByDesc('is_default')->orderBy('series_code')
            ->paginate(15)
            ->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn (InvoicingSeries $s) => $this->linha($s))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'per_page' => $pagina->perPage(), 'total' => $pagina->total()],
        ]);
    }

    public function guardar(Request $request, GestaoDeSeries $gestao): JsonResponse
    {
        $this->exigir($request, 'invoicing.series.view');

        $d = $this->validar($request, $gestao);

        try {
            $s = $gestao->criar($d, activeTenantId());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['series_year' => [$e->getMessage()]]], 422);
        }

        return response()->json(['data' => $this->linha($s), 'message' => __('Série criada com sucesso!')], 201);
    }

    public function actualizar(Request $request, GestaoDeSeries $gestao, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.series.view');

        $s = InvoicingSeries::forTenant(activeTenantId())->findOrFail($id);
        $d = $this->validar($request, $gestao);

        try {
            $mensagem = $gestao->actualizar($s, $d, activeTenantId());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['series_year' => [$e->getMessage()]]], 422);
        }

        return response()->json(['data' => $this->linha($s->fresh()), 'message' => $mensagem]);
    }

    public function eliminar(Request $request, GestaoDeSeries $gestao, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.series.view');

        $s = InvoicingSeries::forTenant(activeTenantId())->findOrFail($id);

        try {
            $gestao->eliminar($s);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => __('Série eliminada com sucesso!')]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function validar(Request $request, GestaoDeSeries $gestao): array
    {
        $tipo = (string) $request->input('document_type', '');

        // O prefixo deriva do tipo ANTES de validar: a regra Rule::in fica
        // como rede, mas quem usa o formulário não pode ser parado por um
        // campo que não é dele para preencher.
        $request->merge(['prefix' => $gestao->prefixoParaGravar($tipo, $request->input('prefix'))]);

        return $request->validate($gestao->regras($tipo), [
            'prefix.in' => __('O prefixo deste tipo de documento é fixado pela AGT (:prefixo) e não pode ser outro.', ['prefixo' => InvoicingSeries::prefixoDe($tipo)]),
        ]);
    }

    private function linha(InvoicingSeries $s): array
    {
        return [
            'id' => $s->id,
            'document_type' => $s->document_type,
            'tipo_rotulo' => __(GestorDeSeries::TIPOS[$s->document_type]['nome'] ?? $s->document_type),
            'series_code' => $s->series_code,
            'name' => $s->name,
            'prefix' => $s->prefix,
            'include_year' => (bool) $s->include_year,
            'next_number' => (int) $s->next_number,
            'number_padding' => (int) $s->number_padding,
            'is_default' => (bool) $s->is_default,
            'is_active' => (bool) $s->is_active,
            'reset_yearly' => (bool) $s->reset_yearly,
            'description' => $s->description,
            'series_year' => $s->series_year,
            'establishment_number' => $s->establishment_number,
            'invoicing_method' => $s->invoicing_method,
            'agt_series_id' => $s->agt_series_id ?: null,
            'registada' => $s->isAGTRegistered(),
            'emitidos' => SeriesCatalog::documentosEmitidos($s),
            'exemplo' => $s->prefix . ' ' . $s->series_code . ($s->include_year ? '/' . ($s->series_year ?? now()->year) : '') . '/' . str_pad((string) $s->next_number, (int) $s->number_padding, '0', STR_PAD_LEFT),
        ];
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
