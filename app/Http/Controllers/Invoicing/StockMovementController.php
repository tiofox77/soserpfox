<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Documento de Movimentação de Stock.
 *
 * O ecrã de stock regista várias entradas e saídas de uma vez; este controller
 * imprime esse lote como um documento único, identificado pela referência
 * MOV/AAAA/NNNNNN.
 *
 * Não é documento fiscal — é interno, de conferência de armazém. Por isso não
 * leva assinatura AGT, hash SAFT nem série de faturação.
 */
class StockMovementController extends Controller
{
    /**
     * A vista certa para o lote.
     *
     * Uma transferência não cabe no documento de entradas e saídas: tem DOIS
     * armazéns e cada produto tem duas pernas, uma de cada lado. Metê-la no
     * mesmo layout dava uma tabela onde o mesmo artigo aparecia duas vezes com
     * sinais opostos e sem dizer de onde para onde foi.
     */
    private function vistaDoLote($movimentos): string
    {
        // Inter-empresas fica no documento de entradas e saídas, apesar de a
        // perna de saída ser do tipo `transfer`. Dentro de cada empresa a
        // movimentação tem UM lado só — a outra perna pertence à outra
        // empresa e nem sequer é visível daqui. No documento de transferência,
        // que junta as duas pernas de cada artigo, saía metade da tabela vazia.
        if ($movimentos->contains(fn ($m) => $m->reference_type === 'inter_company')) {
            return 'pdf.invoicing.stock-movement-batch';
        }

        return $movimentos->contains(fn ($m) => $m->type === 'transfer')
            ? 'pdf.invoicing.stock-transfer-batch'
            : 'pdf.invoicing.stock-movement-batch';
    }

    /** PDF do lote, para ver e imprimir. */
    public function batchPdf(string $reference)
    {
        [$movimentos, $tenant, $armazem] = $this->carregarLote($reference);

        $pdf = Pdf::loadView($this->vistaDoLote($movimentos), [
            'paraPdf'    => true,
            'reference'  => $reference,
            'movimentos' => $movimentos,
            'tenant'     => $tenant,
            'armazem'    => $armazem,
        ]);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'defaultFont'          => 'DejaVu Sans',
            // Sem acesso remoto de propósito: o logótipo já vai embutido em
            // data URI pelo partial, e deixar isto ligado faria o DomPDF ir à
            // rede a partir do conteúdo do documento.
            'isRemoteEnabled'      => false,
        ]);

        $this->numerarPaginas($pdf);

        return $pdf->stream(
            'movimentacao_' . str_replace(['/', '\\', ' '], '_', $reference) . '.pdf'
        );
    }

    /**
     * Escreve "Página N de M" no rodapé de cada folha.
     *
     * Um lote de conferência passa dos 15 produtos com facilidade e transborda
     * para folhas seguintes. Sem numeração ninguém sabe se recebeu o documento
     * todo — que é exactamente o que um documento de conferência serve para
     * permitir verificar. O DomPDF não resolve contadores de página em CSS.
     *
     * Duas coisas que têm de ser assim, ambas medidas:
     *
     *  - `render()` ANTES. O `page_text` percorre as páginas que já existem e
     *    resolve ali os marcadores; chamado antes do render só há uma página e
     *    o resultado era um solitário "Página 1 de 1" num documento de duas.
     *
     *  - Fonte explícita. Com a fonte por omissão (DejaVu Sans, embutida em
     *    subconjunto), o texto sai codificado.
     */
    private function numerarPaginas($pdf): void
    {
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
    }

    /** Mesma coisa em HTML, para acertar o layout sem esperar pelo DomPDF. */
    public function batchPreview(string $reference)
    {
        [$movimentos, $tenant, $armazem] = $this->carregarLote($reference);

        return view($this->vistaDoLote($movimentos), [
            'reference'  => $reference,
            'movimentos' => $movimentos,
            'tenant'     => $tenant,
            'armazem'    => $armazem,
        ]);
    }

    /**
     * Carrega o lote da empresa activa.
     *
     * O filtro por empresa está no scopeDoLote e é o que impede que a
     * referência de uma empresa sirva para ler o documento de outra: as
     * sequências são por empresa, portanto o MOV/2026/000001 existe em todas.
     */
    private function carregarLote(string $reference): array
    {
        $utilizador = auth()->user();

        // `edit` também serve. Quem grava a movimentação tem de conseguir
        // imprimir o comprovativo dela: um papel com `edit` e sem `view`
        // registava o lote e depois levava 403 ao abrir o documento — o
        // trabalho ficava feito e o papel por imprimir.
        // A permissão de transferência também serve, pelo mesmo motivo: quem
        // transfere entre armazéns tem de conseguir imprimir a guia, e essa
        // gente costuma ter `warehouse-transfer.create` sem ter nada de stock.
        abort_unless(
            $utilizador?->can('invoicing.stock.view')
            || $utilizador?->can('invoicing.stock.edit')
            || $utilizador?->can('invoicing.warehouse-transfer.create'),
            403,
            'Sem permissão para ver movimentações de stock.'
        );

        // `withTrashed` no artigo: o Product tem soft delete e um documento de
        // conferência tem de continuar a dizer o que se movimentou mesmo depois
        // de o artigo sair do catálogo. Sem isto, a reimpressão de um lote de
        // há três meses trocava metade das linhas por "(produto removido)" e
        // perdia o código — e é justamente na reimpressão que o documento serve
        // para alguma coisa.
        $movimentos = StockMovement::doLote($reference)
            ->with([
                'product' => fn ($q) => $q->withTrashed(),
                'user',
                'warehouse',
            ])
            ->get();

        abort_if($movimentos->isEmpty(), 404, 'Movimentação não encontrada.');

        $tenant = Tenant::find(activeTenantId());

        // O lote é sempre de um só armazém (o modal só permite um), mas lê-se do
        // movimento e não do ecrã: numa reimpressão o filtro do ecrã já mudou.
        $armazem = Warehouse::find($movimentos->first()->warehouse_id);

        return [$movimentos, $tenant, $armazem];
    }
}
