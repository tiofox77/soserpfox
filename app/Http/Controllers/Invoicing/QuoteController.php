<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\QuoteTemplate;
use App\Models\Invoicing\SalesQuote;
use App\Services\Invoicing\Propostas\RenderizadorDeProposta;
use Barryvdh\DomPDF\Facade\Pdf;

class QuoteController extends Controller
{
    // Cada um vê os documentos que emitiu; com `invoicing.documents.all`
    // vê os de todos. Esconder na lista e entregar em PDF não esconde nada.
    use \App\Traits\EscopoDeAutor;

    public function generatePdf($id)
    {
        try {
            $quote = SalesQuote::with(['client', 'items.product', 'warehouse', 'creator'])
                ->where('tenant_id', activeTenantId())
                ->tap(fn ($q) => $this->escoparAoAutor($q))
                ->findOrFail($id);

            $tenant = \App\Models\Tenant::find(activeTenantId());

            $bankAccounts = \App\Models\Treasury\Account::with('bank')
                ->where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->where('show_on_invoice', true)
                ->orderBy('invoice_display_order')
                ->limit(4)
                ->get();

            // Modelo de proposta escolhido para este orçamento (ou o padrão da
            // empresa). Sem nenhum, sai o desenho antigo — nenhum orçamento
            // já feito muda de aspecto só porque este módulo passou a existir.
            $modelo = $this->modeloDoOrcamento($quote);

            $pdf = $modelo
                ? Pdf::loadHTML(tap(app(RenderizadorDeProposta::class), fn ($r) => $r->paraPdf = true)->render($modelo, $quote, $tenant))
                : Pdf::loadView('pdf.invoicing.quote', [
                    'paraPdf' => true,
                    'quote' => $quote,
                    'tenant' => $tenant,
                    'bankAccounts' => $bankAccounts,
                ]);

            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                // Nada de ir buscar endereços de fora: as imagens são do disco (auditoria de 2026-09-13).
                'isRemoteEnabled' => false,
                'defaultFont' => 'Arial'
            ]);

            $filename = 'orcamento_' . str_replace(['/', '\\', ' '], '_', $quote->quote_number) . '.pdf';
            return $pdf->stream($filename);

        } catch (\Exception $e) {
            return view('pdf.invoicing.quote', [
                'quote' => SalesQuote::with(['client', 'items', 'warehouse'])
                    ->where('tenant_id', activeTenantId())
                    ->tap(fn ($q) => $this->escoparAoAutor($q))
                    ->findOrFail($id),
                'tenant' => \App\Models\Tenant::find(activeTenantId()),
            ]);
        }
    }

    public function previewHtml($id)
    {
        $quote = SalesQuote::with(['client', 'items.product', 'warehouse', 'creator'])
            ->where('tenant_id', activeTenantId())
            ->tap(fn ($q) => $this->escoparAoAutor($q))
            ->findOrFail($id);

        $tenant = \App\Models\Tenant::find(activeTenantId());

        $bankAccounts = \App\Models\Treasury\Account::with('bank')
            ->where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')
            ->limit(4)
            ->get();

        if ($modelo = $this->modeloDoOrcamento($quote)) {
            // Uma página feita de conteúdo dos utilizadores não corre scripts, mesmo que algum escape à limpeza.
            return response(app(RenderizadorDeProposta::class)->render($modelo, $quote, $tenant))
                ->header('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; font-src 'self' data:; sandbox");
        }

        return view('pdf.invoicing.quote', [
            'quote' => $quote,
            'tenant' => $tenant,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    /**
     * Pré-visualizar um MODELO sozinho, sem orçamento — é o botão "Ver PDF" da
     * lista e do editor. Sai com dados de exemplo.
     */
    public function previewModelo($id)
    {
        $modelo = QuoteTemplate::where('tenant_id', activeTenantId())->findOrFail($id);

        $html = tap(app(RenderizadorDeProposta::class), fn ($r) => $r->paraPdf = true)
            ->renderExemplo($modelo, \App\Models\Tenant::find(activeTenantId()));

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false, 'defaultFont' => 'Arial']);

        return $pdf->stream('modelo_' . \Str::slug($modelo->nome) . '.pdf');
    }

    /**
     * O modelo a usar: o que foi escolhido no orçamento, senão o padrão da
     * empresa, senão nenhum (e sai o desenho antigo).
     */
    private function modeloDoOrcamento(SalesQuote $quote): ?QuoteTemplate
    {
        if ($quote->quote_template_id) {
            $escolhido = QuoteTemplate::where('tenant_id', $quote->tenant_id)
                ->find($quote->quote_template_id);

            if ($escolhido) {
                return $escolhido;
            }
        }

        return QuoteTemplate::where('tenant_id', $quote->tenant_id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }
}
