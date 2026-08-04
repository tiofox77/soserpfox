<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\Invoicing\ContaCorrenteQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Extracto de conta corrente em PDF.
 *
 * É este documento que se envia a um cliente que pergunta o que deve — por isso
 * tem de sair com cabeçalho da empresa, período e saldo transportado, e não uma
 * lista solta de facturas.
 */
class AccountStatementController extends Controller
{
    public function pdf(Request $request)
    {
        abort_unless(auth()->user()?->can('invoicing.reports.view'), 403);

        $entidade = $request->query('entidade') === ContaCorrenteQuery::FORNECEDOR
            ? ContaCorrenteQuery::FORNECEDOR
            : ContaCorrenteQuery::CLIENTE;

        $id = (int) $request->query('id');

        // Sempre dentro da empresa activa: o id vem do URL.
        $titular = $entidade === ContaCorrenteQuery::CLIENTE
            ? Client::where('tenant_id', activeTenantId())->find($id)
            : Supplier::where('tenant_id', activeTenantId())->find($id);

        abort_unless($titular, 404, 'Conta não encontrada.');

        $de  = $request->query('de', now()->startOfYear()->format('Y-m-d'));
        $ate = $request->query('ate', now()->format('Y-m-d'));

        $consulta = new ContaCorrenteQuery(activeTenantId(), $entidade, $titular->id, $de, $ate);

        $pdf = Pdf::loadView('pdf.invoicing.account-statement', [
            'entidade'   => $entidade,
            'titular'    => $titular,
            'de'         => $de,
            'ate'        => $ate,
            'movimentos' => $consulta->movimentos(),
            'resumo'     => $consulta->resumo(),
            'tenant'     => Tenant::find(activeTenantId()),
        ]);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'defaultFont'          => 'DejaVu Sans',
            'isRemoteEnabled'      => false,
        ]);

        // Numeração DEPOIS do render e com fonte explícita — ver a nota longa
        // em StockMovementController::numerarPaginas.
        $dom = $pdf->getDomPDF();
        $dom->render();

        $canvas = $dom->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 110,
            $canvas->get_height() - 24,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $dom->getFontMetrics()->getFont('helvetica', 'normal'),
            8,
            [0.42, 0.45, 0.5]
        );

        return $pdf->stream(
            'extracto_' . $entidade . '_' . preg_replace('/[^A-Za-z0-9]+/', '_', $titular->name) . '.pdf'
        );
    }
}
