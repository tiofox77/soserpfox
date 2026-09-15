<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Services\Workshop\FacturacaoDeFrotas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A FACTURAÇÃO DE FROTAS (15/09/2026, OF-16).
 *
 * Ver pede ver ordens; emitir a factura pede a permissão de criar facturas da
 * facturação, como o botão de facturar de cada ordem.
 */
class FrotasApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');

        return response()->json([
            'data' => FacturacaoDeFrotas::clientes(activeTenantId()),
            'pode_facturar' => (bool) $request->user()?->can('invoicing.sales.invoices.create'),
        ]);
    }

    public function ordens(Request $request, int $cliente): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');
        $dados = $request->validate(['de' => ['nullable', 'date'], 'ate' => ['nullable', 'date']]);

        $ordens = FacturacaoDeFrotas::ordens(activeTenantId(), $cliente,
            ! empty($dados['de']) ? Carbon::parse($dados['de']) : null,
            ! empty($dados['ate']) ? Carbon::parse($dados['ate']) : null);

        return response()->json([
            'data' => $ordens,
            'total' => round((float) $ordens->where('pode', true)->sum('total'), 2),
        ]);
    }

    public function facturar(Request $request, int $cliente): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.create');
        $dados = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['integer']]);

        try {
            $factura = FacturacaoDeFrotas::facturar(activeTenantId(), $cliente, array_map('intval', $dados['ids']));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => trans_choice('Factura :numero emitida com :n ordem.|Factura :numero emitida com :n ordens.', count($dados['ids']), ['numero' => $factura->invoice_number, 'n' => count($dados['ids'])]),
            'factura' => ['id' => $factura->id, 'numero' => $factura->invoice_number, 'total' => round((float) $factura->total, 2), 'morada' => route('invoicing.sales.invoices.preview', $factura->id)],
        ]);
    }
}
