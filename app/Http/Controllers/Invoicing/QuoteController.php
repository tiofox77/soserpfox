<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\SalesQuote;
use Barryvdh\DomPDF\Facade\Pdf;

class QuoteController extends Controller
{
    public function generatePdf($id)
    {
        try {
            $quote = SalesQuote::with(['client', 'items.product', 'warehouse', 'creator'])
                ->where('tenant_id', activeTenantId())
                ->findOrFail($id);

            $tenant = \App\Models\Tenant::find(activeTenantId());

            $bankAccounts = \App\Models\Treasury\Account::with('bank')
                ->where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->where('show_on_invoice', true)
                ->orderBy('invoice_display_order')
                ->limit(4)
                ->get();

            $pdf = Pdf::loadView('pdf.invoicing.quote', [
                'paraPdf' => true,
                'quote' => $quote,
                'tenant' => $tenant,
                'bankAccounts' => $bankAccounts,
            ]);

            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial'
            ]);

            $filename = 'orcamento_' . str_replace(['/', '\\', ' '], '_', $quote->quote_number) . '.pdf';
            return $pdf->stream($filename);

        } catch (\Exception $e) {
            return view('pdf.invoicing.quote', [
                'quote' => SalesQuote::with(['client', 'items', 'warehouse'])
                    ->where('tenant_id', activeTenantId())
                    ->findOrFail($id),
                'tenant' => \App\Models\Tenant::find(activeTenantId()),
            ]);
        }
    }

    public function previewHtml($id)
    {
        $quote = SalesQuote::with(['client', 'items.product', 'warehouse', 'creator'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);

        $tenant = \App\Models\Tenant::find(activeTenantId());

        $bankAccounts = \App\Models\Treasury\Account::with('bank')
            ->where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')
            ->limit(4)
            ->get();

        return view('pdf.invoicing.quote', [
            'quote' => $quote,
            'tenant' => $tenant,
            'bankAccounts' => $bankAccounts,
        ]);
    }
}
