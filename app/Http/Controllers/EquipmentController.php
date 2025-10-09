<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use Illuminate\Http\Request;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class EquipmentController extends Controller
{
    public function generateQrCode($id)
    {
        try {
            $equipment = Equipment::where('tenant_id', activeTenantId())->findOrFail($id);
            
            // BaconQrCode v3.x syntax
            $renderer = new ImageRenderer(
                new RendererStyle(400, 2), // size, margin
                new SvgImageBackEnd()
            );
            
            $writer = new Writer($renderer);
            $qrCode = $writer->writeString($equipment->qr_code_url);
            
            return response($qrCode)->header('Content-Type', 'image/svg+xml');
        } catch (\Exception $e) {
            // Log do erro
            \Log::error('QR Code Generation Error: ' . $e->getMessage(), [
                'id' => $id,
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            // Retornar erro em formato texto
            return response('Erro ao gerar QR Code: ' . $e->getMessage(), 500)
                ->header('Content-Type', 'text/plain');
        }
    }

    public function printQrCode($id)
    {
        $equipment = Equipment::where('tenant_id', activeTenantId())
            ->with('category')
            ->findOrFail($id);
        return view('equipment.qrcode-print', compact('equipment'));
    }
}
