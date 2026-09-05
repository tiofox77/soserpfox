<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Restaurant\Order;
use App\Models\Tenant;

class RestaurantDocumentController extends Controller
{
    public function consultationReceipt(int $id)
    {
        $order = Order::with(['items', 'table', 'venue', 'waiter', 'client'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);

        abort_if($order->items->isEmpty(), 404, 'A comanda não tem artigos.');

        return view('restaurant.documents.consultation-receipt', [
            'order' => $order,
            'tenant' => Tenant::findOrFail(activeTenantId()),
            'autoPrint' => request()->boolean('print'),
        ]);
    }

    public function fiscalDocument(int $id)
    {
        $invoice = SalesInvoice::with(['client', 'items.product', 'payments', 'series'])
            ->where('tenant_id', activeTenantId())
            ->where('source_module', 'restaurant')
            ->findOrFail($id);

        app(\App\Services\Audit\AuditRecorder::class)
            ->imprimiu('talão fiscal do restaurante', $invoice, ['formato' => 'talao']);

        return view('restaurant.documents.fiscal-ticket', [
            'invoice' => $invoice,
            'tenant' => Tenant::findOrFail(activeTenantId()),
            'autoPrint' => request()->boolean('print'),
        ]);
    }
}
