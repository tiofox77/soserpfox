<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\Receipt;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    public function generatePdf($id)
    {
        $receipt = Receipt::with(['client', 'supplier', 'invoice', 'creator'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        $tenant = Tenant::find(activeTenantId());
        
        $pdf = Pdf::loadView('pdf.invoicing.receipt', [
            'receipt' => $receipt,
            'tenant' => $tenant,
        ]);
        
        $pdf->setPaper('A4', 'portrait');
        
        $filename = 'recibo_' . str_replace(['/', '\\'], '_', $receipt->receipt_number) . '.pdf';
        return $pdf->stream($filename);
    }
    
    public function previewHtml($id)
    {
        $receipt = Receipt::with(['client', 'supplier', 'invoice', 'creator'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        $tenant = Tenant::find(activeTenantId());
        
        return view('pdf.invoicing.receipt', [
            'receipt' => $receipt,
            'tenant' => $tenant,
        ]);
    }
}
