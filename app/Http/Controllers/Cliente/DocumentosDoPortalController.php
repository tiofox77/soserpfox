<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Api\Cliente\PortalDoClienteApiController;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Treasury\Account;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OS DOCUMENTOS NO PORTAL DO CLIENTE — o PDF da factura, o mesmo da empresa.
 *
 * O portal mostrava as facturas numa lista e não deixava abrir nenhuma: quem
 * queria pagar por transferência não tinha o papel com o valor e o IBAN.
 *
 * SÓ AS FACTURAS QUE O CLIENTE VÊ: a consulta é a do portal
 * (`PortalDoClienteApiController::facturasDe`) — dele, da empresa dele, sem
 * rascunhos e só das áreas que a empresa lhe deu. Qualquer outro número dá 404,
 * sem dizer se existe.
 */
class DocumentosDoPortalController extends Controller
{
    public function factura(Request $request, int $id): Response
    {
        $cliente = $request->user('client');

        $factura = PortalDoClienteApiController::facturasDe($cliente)
            ->with([
                'client' => fn ($q) => $q->withoutGlobalScopes(),
                'items.product' => fn ($q) => $q->withoutGlobalScopes(),
                'warehouse' => fn ($q) => $q->withoutGlobalScopes(),
                'creator',
                'creditNotes' => fn ($q) => $q->withoutGlobalScopes(),
                'series' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->findOrFail($id);

        $empresa = Tenant::findOrFail($cliente->tenant_id);

        $contas = Account::withoutGlobalScopes()->with('bank')
            ->where('tenant_id', $empresa->id)
            ->where('is_active', true)
            ->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')
            ->limit(4)
            ->get();

        $pdf = Pdf::loadView('pdf.invoicing.sales-invoice', [
            'paraPdf' => true,
            'invoice' => $factura,
            'tenant' => $empresa,
            'bankAccounts' => $contas,
            'qrCode' => getAGTQRData($factura, 140),
        ])->setPaper('A4', 'portrait');

        return $pdf->stream('factura_' . str_replace(['/', '\\', ' '], '_', $factura->invoice_number) . '.pdf');
    }
}
