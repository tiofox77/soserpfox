<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Invoicing\Concerns\ResolveOperadorOffline;
use App\Models\Invoicing\PosShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Abertura e fecho de turno POS a partir do PWA offline.
 *
 * Operações IDEMPOTENTES por operador:
 *  - open : se já existe turno aberto do operador, devolve-o (não duplica).
 *  - close: se não existe turno aberto, devolve o último fechado (já fechado).
 *
 * Permite que o caixa seja aberto/fechado offline e sincronizado quando a
 * internet voltar (fila FIFO do PWA garante: abrir → vendas → fechar).
 */
class PosShiftController extends Controller
{
    use ResolveOperadorOffline;

    public function open(Request $request): JsonResponse
    {
        $tenantId = activeTenantId();
        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'No active tenant'], 403);
        }

        $validator = Validator::make($request->all(), [
            'local_uuid'      => 'nullable|string|max:80',
            'opening_balance' => 'required|numeric|min:0',
            'opening_notes'   => 'nullable|string|max:1000',
            'opened_at_local' => 'nullable|date',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // O operador que abre o turno offline (PIN), validado; senao a sessao.
        $operatorId = $this->operadorOffline($request, $tenantId);

        // Idempotência: já existe turno aberto deste operador → devolver
        $existing = PosShift::where('tenant_id', $tenantId)
            ->where('user_id', $operatorId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
        if ($existing) {
            return response()->json([
                'success'      => true,
                'already_open' => true,
                'shift'        => $this->shiftPayload($existing),
            ]);
        }

        try {
            $openedAt = \App\Helpers\DateHelper::doDispositivo($request->input('opened_at_local')) ?? now();

            $shift = PosShift::createSafely([
                'tenant_id'       => $tenantId,
                'user_id'         => $operatorId,
                'status'          => 'open',
                'opened_at'       => $openedAt,
                'opening_balance' => (float) $request->input('opening_balance', 0),
                'opening_notes'   => $request->input('opening_notes'),
                'opened_ip'       => $request->ip(),
            ], $tenantId);

            // A caixa do operador abre com o turno, e o saldo fica o que foi
            // declarado — a mesma porta do balcão web (TurnosDoPos). Sem isto o
            // turno do PWA deixava a caixa fechada, e o numerário das vendas
            // podia ir parar à gaveta de outra pessoa.
            (new \App\Services\POS\TurnosDoPos((int) $tenantId, (int) $operatorId))->abrirACaixa($shift, $request->input('opening_notes'));

            return response()->json([
                'success'      => true,
                'already_open' => false,
                'shift'        => $this->shiftPayload($shift),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('PosShiftController@open: erro ao abrir turno offline', [
                'local_uuid' => $request->input('local_uuid'),
                'error'      => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function close(Request $request): JsonResponse
    {
        $tenantId = activeTenantId();
        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'No active tenant'], 403);
        }

        $validator = Validator::make($request->all(), [
            'local_uuid'        => 'nullable|string|max:80',
            'actual_cash'       => 'required|numeric|min:0',
            'closing_notes'     => 'nullable|string|max:1000',
            'difference_reason' => 'nullable|string|max:1000',
            'closed_at_local'   => 'nullable|date',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // O operador que fecha o turno offline (PIN), validado; senao a sessao.
        $operatorId = $this->operadorOffline($request, $tenantId);

        $shift = PosShift::where('tenant_id', $tenantId)
            ->where('user_id', $operatorId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        // Idempotência: já não há turno aberto → devolver o último fechado
        if (!$shift) {
            $last = PosShift::where('tenant_id', $tenantId)
                ->where('user_id', $operatorId)
                ->where('status', 'closed')
                ->latest('closed_at')
                ->first();
            return response()->json([
                'success'        => true,
                'already_closed' => true,
                'shift'          => $last ? $this->shiftPayload($last) : null,
            ]);
        }

        try {
            // Garantir totais atualizados (inclui vendas sincronizadas antes na mesma fila)
            $shift->recalculateTotals();
            $shift->close(
                (float) $request->input('actual_cash'),
                $request->input('closing_notes'),
                $request->input('difference_reason'),
                $operatorId
            );

            // A caixa fecha com o turno e a quebra ou sobra fica lançada.
            (new \App\Services\POS\TurnosDoPos((int) $tenantId, (int) $operatorId))->fecharACaixa($shift->fresh());

            // Preservar hora local do fecho offline, se enviada
            if ($fechoNoDispositivo = \App\Helpers\DateHelper::doDispositivo($request->input('closed_at_local'))) {
                $shift->closed_at = $fechoNoDispositivo;
                $shift->save();
            }

            return response()->json([
                'success'        => true,
                'already_closed' => false,
                'shift'          => $this->shiftPayload($shift->fresh()),
            ]);
        } catch (\Throwable $e) {
            Log::error('PosShiftController@close: erro ao fechar turno offline', [
                'shift_id' => $shift->id,
                'error'    => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Estado atual do turno do operador (verificação rápida no PWA).
     */
    public function status(): JsonResponse
    {
        $tenantId = activeTenantId();
        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'No active tenant'], 403);
        }

        $shift = PosShift::where('tenant_id', $tenantId)
            ->where('user_id', auth()->id())
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        return response()->json([
            'success' => true,
            'open'    => (bool) $shift,
            'shift'   => $shift ? $this->shiftPayload($shift) : null,
        ]);
    }

    protected function shiftPayload(PosShift $s): array
    {
        return [
            'id'              => $s->id,
            'number'          => $s->shift_number,
            'status'          => $s->status,
            'opened_at'       => optional($s->opened_at)->toIso8601String(),
            'closed_at'       => optional($s->closed_at)->toIso8601String(),
            'opening_balance' => (float) $s->opening_balance,
            'cash_sales'      => (float) $s->cash_sales,
            'saidas_da_gaveta'   => ($gaveta = $s->movimentosDaGaveta())['saidas'],
            'entradas_na_gaveta' => $gaveta['entradas'],
            'total_sales'     => (float) $s->total_sales,
            // Aberto, o esperado conta já com as saídas e entradas da gaveta.
            'expected_cash'   => $s->status === 'open' ? $s->dinheiroEsperado() : (float) ($s->expected_cash ?? 0),
            'actual_cash'     => (float) ($s->actual_cash ?? 0),
            'cash_difference' => (float) ($s->cash_difference ?? 0),
        ];
    }
}
