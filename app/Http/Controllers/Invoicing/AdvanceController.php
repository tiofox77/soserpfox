<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\Advance;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class AdvanceController extends Controller
{
    public function generatePdf($id)
    {
        try {
            // Buscar adiantamento com relacionamentos
            $advance = Advance::with(['client', 'creator'])
                ->where('tenant_id', activeTenantId())
                ->findOrFail($id);
            
            // Buscar dados do tenant
            $tenant = Tenant::find(activeTenantId());
            
            // Buscar contas bancárias para exibir no adiantamento (máximo 4)
            $bankAccounts = \App\Models\Treasury\Account::with('bank')
                ->where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->where('show_on_invoice', true)
                ->orderBy('invoice_display_order')
                ->limit(4)
                ->get();
            
            // Configurar PDF com options
            $pdf = Pdf::loadView('pdf.invoicing.advance', [
                'advance' => $advance,
                'tenant' => $tenant,
                'bankAccounts' => $bankAccounts,
            ]);
            
            // Configurar tamanho A4 e orientação
            $pdf->setPaper('A4', 'portrait');
            
            // Configurar opções do DomPDF
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial'
            ]);
            
            // Retornar PDF para visualização no navegador
            $filename = 'adiantamento_' . str_replace(['/', '\\', ' '], '_', $advance->advance_number) . '.pdf';
            return $pdf->stream($filename);
            
        } catch (\Exception $e) {
            // Se houver erro, retornar a view HTML diretamente para debug
            return view('pdf.invoicing.advance', [
                'advance' => Advance::with(['client'])
                    ->where('tenant_id', activeTenantId())
                    ->findOrFail($id),
                'tenant' => Tenant::find(activeTenantId()),
                'bankAccounts' => collect(),
            ]);
        }
    }
    
    public function previewHtml($id)
    {
        // Buscar adiantamento com relacionamentos
        $advance = Advance::with(['client', 'creator'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        // Buscar dados do tenant
        $tenant = Tenant::find(activeTenantId());
        
        // Buscar contas bancárias para exibir no adiantamento (máximo 4)
        $bankAccounts = \App\Models\Treasury\Account::with('bank')
            ->where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')
            ->limit(4)
            ->get();
        
        // Retornar view HTML diretamente (sem PDF)
        return view('pdf.invoicing.advance', [
            'advance' => $advance,
            'tenant' => $tenant,
            'bankAccounts' => $bankAccounts,
        ]);
    }
}
