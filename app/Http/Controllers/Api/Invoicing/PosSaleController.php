<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\POS\PosSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Recebe vendas POS criadas offline no PWA e cria a Fatura-Recibo fiscal real
 * (com hash SAFT-AO, tesouraria, turno e stock). Idempotente via local_uuid.
 *
 * Devolve o número AGT definitivo + QR Code para o cliente substituir o
 * número provisório impresso offline.
 */
class PosSaleController extends Controller
{
    public function store(Request $request, PosSaleService $service): JsonResponse
    {
        $tenantId = activeTenantId();
        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'No active tenant'], 403);
        }

        $validator = Validator::make($request->all(), [
            'local_uuid'          => 'required|string|max:80',
            'client_id'           => 'nullable|integer',
            'payment_method'      => 'nullable|string|max:30',
            'amount_received'     => 'nullable|numeric|min:0',
            'discount_commercial' => 'nullable|numeric|min:0|max:100',
            'notes'               => 'nullable|string|max:2000',
            'created_at_local'    => 'nullable|date',
            'items'                       => 'required|array|min:1',
            'items.*.product_id'          => 'nullable|integer',
            'items.*.product_name'        => 'required|string|max:255',
            'items.*.quantity'            => 'required|numeric|min:0.0001',
            'items.*.unit_price'          => 'required|numeric|min:0',
            'items.*.tax_rate'            => 'nullable|numeric|min:0|max:100',
            'items.*.is_service'          => 'nullable|boolean',
            'items.*.unit'                => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $invoice = $service->createFromPayload(
                $validator->validated(),
                $tenantId,
                auth()->id()
            );

            // QR Code AGT definitivo (server-side)
            $qr = ['data' => '', 'image' => null, 'atcud' => ''];
            try {
                $qr = getAGTQRData($invoice->load(['client', 'items', 'tenant']), 100);
            } catch (\Throwable $e) {
                Log::warning('PosSaleController: erro QR', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success'        => true,
                'id'             => $invoice->id,
                'local_uuid'     => $invoice->local_uuid,
                'invoice_number' => $invoice->invoice_number,
                'total'          => (float) $invoice->total,
                'atcud'          => $qr['atcud'] ?? ($invoice->atcud ?? ''),
                'qr_image'       => $qr['image'] ?? null,
                'hash_short'     => $invoice->saft_hash ? substr($invoice->saft_hash, 0, 4) : null,
                'hash_control'   => $invoice->hash_control ?? '1',
                // Totais e linhas TAL COMO GRAVADOS pelo servidor: o talão
                // reimpresso mostrava número/QR reais mas totais locais (a 14%
                // de um catálogo desactualizado). O dispositivo passa a
                // substituir os seus valores por estes.
                'subtotal'        => (float) $invoice->subtotal,
                'tax_amount'      => (float) $invoice->tax_amount,
                'discount_amount' => (float) ($invoice->discount_amount ?? 0),
                'items'           => $invoice->items->map(fn($i) => [
                    'product_id'         => $i->product_id,
                    'product_name'       => $i->product_name,
                    'quantity'           => (float) $i->quantity,
                    'unit_price'         => (float) $i->unit_price,
                    'tax_rate'           => (float) $i->tax_rate,
                    'tax_amount'         => (float) $i->tax_amount,
                    'tax_code'           => $i->tax_code,
                    'tax_exemption_code' => $i->tax_exemption_code,
                    'total'              => (float) $i->total,
                ])->values()->all(),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('PosSaleController: erro ao criar venda POS offline', [
                'local_uuid' => $request->input('local_uuid'),
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
