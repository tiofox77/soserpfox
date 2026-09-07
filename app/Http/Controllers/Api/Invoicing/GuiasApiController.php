<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\TransportGuide;
use App\Models\Product;
use App\Services\Invoicing\EmissorDeGuias;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AS GUIAS DE TRANSPORTE E DE REMESSA, para o ecrã em React.
 *
 * A emissão, a anulação e a comunicação à AGT vivem no `EmissorDeGuias`, o
 * mesmo que o Livewire chama. A PERMISSÃO é a do menu: quem vê as guias ou
 * as notas de débito — o ecrã de sempre não tem guarda própria, e este não
 * inventa uma nova.
 */
class GuiasApiController extends Controller
{
    private const PERMISSOES = ['invoicing.transport-guides.view', 'invoicing.debit-notes.view'];

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request);

        $tenantId = activeTenantId();

        return response()->json([
            'tipos' => collect(TransportGuide::TYPES)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'clientes' => Client::where('tenant_id', $tenantId)->orderBy('name')->limit(500)->get(['id', 'name', 'nif']),
            // As facturas de onde se copia a mercadoria: as que não foram anuladas nem creditadas.
            'facturas' => SalesInvoice::where('tenant_id', $tenantId)
                ->whereNotIn('status', ['cancelled', 'credited'])
                ->orderByDesc('invoice_date')->limit(200)
                ->get(['id', 'invoice_number', 'client_id']),
            'artigos' => Product::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('name')->limit(500)->get(['id', 'name', 'unit']),
            'permissoes' => ['pode_criar' => true],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request);

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $pagina = TransportGuide::with('client')
            ->where('tenant_id', activeTenantId())
            ->when($filtros['procura'] ?? null, fn ($q, $v) => $q->where('guide_number', 'like', "%{$v}%"))
            ->orderByDesc('issue_date')->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn ($g) => $this->linha($g))->values(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    /** O cliente e as linhas de uma factura, para copiar para a guia. */
    public function linhasDaFactura(Request $request, EmissorDeGuias $emissor, int $factura): JsonResponse
    {
        $this->exigir($request);

        $f = SalesInvoice::with('items')->where('tenant_id', activeTenantId())->findOrFail($factura);

        return response()->json([
            'client_id' => $f->client_id,
            'linhas' => $emissor->linhasDaFactura($f),
        ]);
    }

    public function guardar(Request $request, EmissorDeGuias $emissor): JsonResponse
    {
        $this->exigir($request);

        $dados = $request->validate($emissor->regras() + [
            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.product_id' => ['nullable', 'integer'],
            'linhas.*.product_name' => ['nullable', 'string', 'max:255'],
            'linhas.*.description' => ['nullable', 'string', 'max:255'],
            'linhas.*.quantity' => ['required', 'numeric', 'min:0'],
            'linhas.*.unit' => ['nullable', 'string', 'max:20'],
        ], [
            'linhas.required' => __('Adicione pelo menos um item com quantidade.'),
        ]);

        abort_unless(Client::where('tenant_id', activeTenantId())->whereKey($dados['client_id'])->exists(), 422, __('Cliente desconhecido nesta empresa.'));

        try {
            $g = $emissor->emitir($dados, $dados['linhas'], activeTenantId(), $request->user()?->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['linhas' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'data' => $this->linha($g->load('client')),
            'message' => __('Guia :n registada.', ['n' => $g->guide_number]),
        ], 201);
    }

    public function comunicar(Request $request, EmissorDeGuias $emissor, int $id): JsonResponse
    {
        $this->exigir($request);

        $g = TransportGuide::where('tenant_id', activeTenantId())->findOrFail($id);
        $r = $emissor->comunicar($g);

        return response()->json(['data' => $this->linha($g->fresh('client')), 'message' => $r['mensagem']], $r['ok'] ? 200 : 422);
    }

    public function anular(Request $request, EmissorDeGuias $emissor, int $id): JsonResponse
    {
        $this->exigir($request);

        $g = TransportGuide::where('tenant_id', activeTenantId())->findOrFail($id);
        $emissor->anular($g);

        return response()->json(['message' => __('Guia :n anulada.', ['n' => $g->guide_number])]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function linha(TransportGuide $g): array
    {
        return [
            'id' => $g->id,
            'numero' => $g->guide_number,
            'tipo' => $g->type,
            'tipo_rotulo' => $g->typeLabel(),
            'cliente' => $g->client?->name ?? __('Consumidor Final'),
            // A VIATURA: a lista de sempre tinha-a em coluna própria, e é por
            // ela que se sabe qual dos camiões leva a mercadoria.
            'viatura' => $g->vehicle_plate,
            'data' => optional($g->issue_date)->toDateString() ?? (string) $g->issue_date,
            'estado' => $g->status,
            'assinada' => ! empty($g->jws_signature),
            'agt' => $g->agt_status,
            'atcud' => $g->atcud,
            'pdf' => '/invoicing/transport-guides/' . $g->id . '/pdf',
        ];
    }

    private function exigir(Request $request): void
    {
        abort_unless($request->user()?->canAny(self::PERMISSOES), 403, __('Sem permissão para esta operação.'));
    }
}
