<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Api\Invoicing\Concerns\ResolveOperadorOffline;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Restaurant\ComandaOffline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Recebe do PWA a comanda feita sem rede e repõe-na no servidor.
 *
 * Uma comanda inteira por pedido — mesa, artigos, envio à cozinha e, se a
 * conta já foi paga, o recebimento. Sem rede não há ids do servidor para
 * encadear quatro chamadas, e uma sequência de quatro pedidos numa rede que
 * volta aos soluços falha sempre a meio, deixando comandas abertas sem artigos
 * ou artigos sem conta.
 *
 * Idempotente: o mesmo `local_uuid` devolve a mesma comanda, o mesmo documento
 * e nunca uma segunda cópia de nada.
 */
class ComandaOfflineController extends Controller
{
    use ResolveOperadorOffline;

    public function store(Request $request, ComandaOffline $reposicao): JsonResponse
    {
        $tenantId = activeTenantId();

        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'Sem empresa activa.'], 403);
        }

        // A activação, do lado do servidor. O ecrã já se esconde sozinho quando
        // a empresa não tem o módulo, mas o ecrã é do cliente e o cliente
        // mente-se: quem fecha a porta é isto.
        if (!optional(Tenant::find($tenantId))->hasModule('restaurant')) {
            return response()->json([
                'success' => false,
                'error'   => 'O módulo de Restaurante não está activo para esta empresa.',
            ], 403);
        }

        $validador = Validator::make($request->all(), [
            'local_uuid'            => 'required|uuid',
            'venue_id'              => 'required|integer',
            'table_id'              => 'nullable|integer',
            'channel'               => 'nullable|in:table,counter,takeaway,delivery',
            'guest_count'           => 'nullable|integer|min:1|max:100',
            'client_id'             => 'nullable|integer',
            'notes'                 => 'nullable|string|max:2000',
            'operator_id'           => 'nullable|integer',
            'operator_email'        => 'nullable|string|max:191',
            'confirmar'             => 'nullable|boolean',

            'items'                 => 'required|array|min:1',
            'items.*.local_uuid'    => 'required|uuid',
            'items.*.product_id'    => 'required|integer',
            'items.*.quantity'      => 'required|numeric|min:0.001',
            'items.*.unit_price'    => 'nullable|numeric|min:0',
            'items.*.notes'         => 'nullable|string|max:500',

            'checkout'                          => 'nullable|array',
            'checkout.document_type'            => 'required_with:checkout|in:FR,FT',
            'checkout.client_id'                => 'nullable|integer',
            'checkout.payment_method_id'        => 'nullable|integer',
            'checkout.payments'                 => 'nullable|array',
            'checkout.payments.*.payment_method_id' => 'required_with:checkout.payments|integer',
            'checkout.payments.*.amount'        => 'required_with:checkout.payments|numeric|min:0.01',
        ]);

        if ($validador->fails()) {
            return response()->json([
                'success' => false,
                'error'   => 'Comanda offline inválida.',
                'errors'  => $validador->errors(),
            ], 422);
        }

        try {
            $resultado = $reposicao->repor(
                $validador->validated(),
                $tenantId,
                $this->operadorOffline($request, $tenantId)
            );
        } catch (\InvalidArgumentException $e) {
            // Regra de negócio recusada: a comanda não sobe assim como está e
            // repetir não a vai salvar. 422 para a fila do PWA a marcar como
            // recusada em vez de a tentar para sempre — com a mensagem, que é
            // o que diz ao empregado o que fazer (activar a ficha técnica do
            // prato, abrir o turno, o que for).
            Log::warning('Comanda offline recusada', [
                'local_uuid' => $request->input('local_uuid'),
                'motivo'     => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Comanda offline: erro ao repor', [
                'local_uuid' => $request->input('local_uuid'),
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        $comanda = $resultado['order'];
        $factura = $resultado['invoice'];

        return response()->json([
            'success'      => true,
            'id'           => $comanda->id,
            'local_uuid'   => $comanda->local_uuid,
            'order_number' => $comanda->order_number,
            'status'       => $comanda->status,
            'table_id'     => $comanda->table_id,
            'grand_total'  => (float) $comanda->grand_total,
            // O que correu diferente do que o aparelho julgava. O ecrã mostra
            // isto ao empregado — uma mesa que passou a balcão ou um preço que
            // mudou não pode ficar só no log do servidor.
            'avisos'       => $resultado['avisos'],
            'invoice'      => $factura ? $this->paraOTalao($factura) : null,
        ], 201);
    }

    /**
     * O documento, na forma que o talão precisa.
     *
     * O aparelho imprime um talão provisório no momento em que o cliente paga
     * — sem rede não há numeração da AGT que se possa inventar. Quando a
     * comanda sobe, é isto que transforma esse papel provisório num
     * comprovativo fiscal reimprimível: número, ATCUD, QR e hash.
     *
     * Os TOTAIS e as LINHAS vão tal como o servidor os gravou, e não como o
     * aparelho os calculou. Se o preço mudou entretanto, o talão reimpresso
     * tem de dizer o que está nos livros — misturar número real com totais
     * locais dá um documento que não bate com nada.
     */
    private function paraOTalao($factura): array
    {
        $qr = ['image' => null, 'atcud' => ''];

        try {
            $qr = getAGTQRData($factura->load(['client', 'items', 'tenant']), 100);
        } catch (\Throwable $e) {
            // Um QR que não sai não pode impedir a comanda de fechar: a venda
            // já está gravada e o número já foi atribuído.
            Log::warning('Comanda offline: QR não gerado', [
                'invoice' => $factura->id,
                'erro'    => $e->getMessage(),
            ]);
        }

        return [
            'id'              => $factura->id,
            'invoice_number'  => $factura->invoice_number,
            'invoice_type'    => $factura->invoice_type,
            'total'           => (float) $factura->total,
            'subtotal'        => (float) $factura->subtotal,
            'tax_amount'      => (float) $factura->tax_amount,
            'discount_amount' => (float) ($factura->discount_amount ?? 0),
            'atcud'           => $qr['atcud'] ?? ($factura->atcud ?? ''),
            'qr_image'        => $qr['image'] ?? null,
            'hash_short'      => $factura->saft_hash ? substr($factura->saft_hash, 0, 4) : null,
            'hash_control'    => $factura->hash_control ?? '1',
            'client_name'     => $factura->client?->name,
            'client_nif'      => $factura->client?->nif,
            'items'           => $factura->items->map(fn ($i) => [
                'product_id'   => $i->product_id,
                'product_name' => $i->product_name,
                'quantity'     => (float) $i->quantity,
                'unit_price'   => (float) $i->unit_price,
                'tax_rate'     => (float) $i->tax_rate,
                'total'        => (float) $i->total,
            ])->values()->all(),
        ];
    }
}
