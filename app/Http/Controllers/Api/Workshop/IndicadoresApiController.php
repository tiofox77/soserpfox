<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Services\Workshop\IndicadoresDaOficina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * OS INDICADORES DA OFICINA (15/09/2026, OF-18).
 *
 * São dinheiro e margens: pedem a permissão dos relatórios da oficina. O
 * período anterior tem o mesmo número de dias e acaba na véspera do escolhido.
 */
class IndicadoresApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('workshop.reports.view'), 403, __('Sem permissão para esta operação.'));

        $dados = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ]);
        $tenantId = activeTenantId();

        $de = Carbon::parse($dados['de'] ?? now()->startOfMonth())->startOfDay();
        $ate = Carbon::parse($dados['ate'] ?? now())->endOfDay();
        $dias = (int) $de->diffInDays($ate) + 1;
        $antesAte = $de->copy()->subDay()->endOfDay();
        $antesDe = $antesAte->copy()->subDays($dias - 1)->startOfDay();

        return response()->json([
            'periodo' => ['de' => $de->toDateString(), 'ate' => $ate->toDateString(), 'dias' => $dias],
            'anterior_periodo' => ['de' => $antesDe->toDateString(), 'ate' => $antesAte->toDateString()],
            'actual' => IndicadoresDaOficina::calcular($tenantId, $de, $ate),
            'anterior' => IndicadoresDaOficina::calcular($tenantId, $antesDe, $antesAte),
            'meses' => IndicadoresDaOficina::meses($tenantId),
        ]);
    }
}
