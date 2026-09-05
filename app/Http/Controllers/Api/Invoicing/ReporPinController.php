<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Pwa\ReporPinSemRede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * A fila do aparelho traz um PIN de turno reposto sem rede.
 *
 * Aceite → 200. Recusado → 422 com o motivo à cabeça do JSON: o aparelho
 * trata um 4xx como recusa definitiva e guarda o texto no `last_error` da
 * fila — é isso que o operador vai ler na gaveta dos pendentes.
 */
class ReporPinController extends Controller
{
    public function __invoke(Request $request, ReporPinSemRede $servico): JsonResponse
    {
        $tenantId = activeTenantId();

        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'No active tenant'], 403);
        }

        $validator = Validator::make($request->all(), [
            'local_uuid'    => 'required|string|max:80',
            'user_id'       => 'required|integer',
            'authorized_by' => 'required|integer',
            'pin_hash'      => 'required|string|size:60',
            'reposto_em'    => 'nullable|date',
            'aparelho'      => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $registo = $servico->aplicar($validator->validated(), $request->user(), $tenantId);

        if (!$registo->foiAceite()) {
            return response()->json([
                'error'   => $registo->motivo,
                'success' => false,
                'estado'  => $registo->estado,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'estado'  => $registo->estado,
            'user_id' => $registo->user_id,
        ]);
    }
}
