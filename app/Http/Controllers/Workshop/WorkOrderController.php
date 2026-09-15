<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop\WorkOrder;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    /**
     * Página de Preview para Impressão
     */
    public function printPreview($id)
    {
        $workOrder = WorkOrder::with([
            'vehicle',
            'mechanic',
            'items.service',
            'items.product',
            'items.mechanic',
            // O check-in (OF-01): danos no desenho, combustível, acessórios e a assinatura.
            'checkin',
            // O termo de entrega (OF-13): km à saída, o conferido e a assinatura de quem levantou.
            'handover',
            // O sinistro (OF-15): seguradora, processo e franquia no papel.
            'claim.insurer',
        ])
        ->where('tenant_id', activeTenantId())
        ->findOrFail($id);

        return view('workshop.work-orders.print-preview', compact('workOrder'));
    }

    /**
     * A ETIQUETA DA CHAVE (OF-20): TAG#, matrícula, OS e um QR que abre a folha
     * de obra no telemóvel de quem a lê (com sessão iniciada).
     *
     * `?formato=etiqueta` (62 × 40 mm, uma por folha, para impressora de
     * etiquetas) ou `a4` (oito por folha); `?copias=` quantas.
     */
    public function etiqueta(Request $request, $id)
    {
        $workOrder = WorkOrder::with('vehicle')->where('tenant_id', activeTenantId())->findOrFail($id);

        $formato = $request->query('formato') === 'a4' ? 'a4' : 'etiqueta';
        $copias = max(1, min(8, (int) $request->query('copias', $formato === 'a4' ? 8 : 1)));
        $link = route('workshop.work-orders') . '?ordem=' . $workOrder->id;

        $qr = (new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(240, 1),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        )))->writeString($link);
        // Só o <svg>: a declaração XML no meio do HTML não serve.
        $qr = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $qr);

        $empresa = \App\Models\Tenant::find($workOrder->tenant_id)?->name;

        return view('workshop.work-orders.etiqueta', compact('workOrder', 'formato', 'copias', 'qr', 'link', 'empresa'));
    }
}
