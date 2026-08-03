<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\TransportGuide;
use Barryvdh\DomPDF\Facade\Pdf;

class TransportGuideController extends Controller
{
    public function pdf($id)
    {
        $guide = TransportGuide::with(['items', 'client', 'invoice'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);

        $tenant = auth()->user()?->tenant;

        $pdf = Pdf::loadView('invoicing.pdf.transport-guide', [
            'guide' => $guide,
            'company' => [
                'name' => $tenant?->name ?? 'Empresa',
                'nif' => $tenant?->nif ?? '',
                'address' => $tenant?->address ?? '',
            ],
        ]);

        return $pdf->stream('guia_' . $guide->guide_number . '.pdf');
    }
}
