import type { Registo } from '../motor/base';
import { buildDocumentHtml, DOCUMENT_CSS } from './documento';
import { preencherMolde, type ExtrasDoPapel } from './molde';
import { buildShiftReportHtml } from './relatorio';
import { imprimirDocumentoHtml, pdfHtml, printHtml } from './saida';
import { buildTicketHtml, cssDoTalao } from './talao';
import { larguraEfectiva } from '@/ui/larguraDoTalao';

/**
 * A LARGURA DO ROLO (28/09/2026): a deste aparelho (escolhida no recibo e
 * guardada nele), senão a da empresa, senão 80 mm.
 */
export function larguraDoTalao(company: Registo = {}): 58 | 80 {
    return larguraEfectiva(company.talao_largura as number | string | null | undefined);
}

/**
 * O PAPEL DO PWA — talão, documento, relatório de turno, em impressora e PDF.
 *
 * Era o `public/js/pos-offline-ticket.js`. O `window.PosOfflineTicket` mantém-se
 * com a mesma forma: os ensaios de browser (e quem depura num telemóvel) usam-no.
 */

/** A FR dos Documentos é paga no acto: o recebido é o total e a referência local faz de número. */
function comoTalao(doc: Registo): Registo {
    return {
        ...doc,
        provisional_number: 'LOCAL-' + String(doc.local_uuid || '').slice(-6).toUpperCase(),
        payment_method: doc.payment_method || 'cash',
        amount_received: doc.amount_received != null ? doc.amount_received : doc.total,
    };
}

export const PosOfflineTicket = {
    buildTicketHtml,
    buildShiftReportHtml,
    buildDocumentHtml,
    preencherMolde,

    /** O papel de um documento: o molde do servidor preenchido, ou null se não há molde. */
    htmlDoDocumento(doc: Registo, _company: Registo, extras?: ExtrasDoPapel): string | null {
        return extras?.molde ? preencherMolde(extras.molde, doc, extras) : null;
    },

    print(sale: Registo, company: Registo = {}): void {
        const l = larguraDoTalao(company);
        printHtml(buildTicketHtml(sale, company, l), cssDoTalao(l));
    },

    /** O talão da venda em PDF, na largura do rolo, feito no aparelho. */
    pdfDoTalao(sale: Registo, company: Registo = {}): Promise<Blob> {
        const l = larguraDoTalao(company);

        return pdfHtml(buildTicketHtml(sale, company, l), cssDoTalao(l), { larguraMm: l });
    },

    /** O documento em PDF — A4; a FR vai como talão, como na impressão. */
    pdfDoDocumento(doc: Registo, company: Registo = {}, extras?: ExtrasDoPapel): Promise<Blob> {
        if (extras?.molde) {
            return pdfHtml(preencherMolde(extras.molde, doc, extras), null, { larguraMm: 210, a4: true, completo: true });
        }

        if (String(doc.doc_type || '').toUpperCase() === 'FR') {
            const l = larguraDoTalao(company);

            return pdfHtml(buildTicketHtml(comoTalao(doc), company, l), cssDoTalao(l), { larguraMm: l });
        }

        return pdfHtml(buildDocumentHtml(doc, company), DOCUMENT_CSS, { larguraMm: 210, a4: true });
    },

    /**
     * Cada papel no seu formato: COM MOLDE, o do servidor; sem molde, a FR é o
     * talão de 80 mm e a proforma/FT/NC saem em A4.
     */
    printDocument(doc: Registo, company: Registo = {}, extras?: ExtrasDoPapel): void {
        if (extras?.molde) {
            imprimirDocumentoHtml(preencherMolde(extras.molde, doc, extras), 'width=820,height=900');

            return;
        }

        if (String(doc.doc_type || '').toUpperCase() === 'FR') {
            const l = larguraDoTalao(company);
            printHtml(buildTicketHtml(comoTalao(doc), company, l), cssDoTalao(l));

            return;
        }

        printHtml(buildDocumentHtml(doc, company), DOCUMENT_CSS, 'width=820,height=900');
    },

    /** `comProdutos`: o fecho com as vendas artigo a artigo e os documentos. */
    printShiftReport(shiftData: Registo, sales: Registo[], company: Registo, comProdutos = false): void {
        printHtml(buildShiftReportHtml(shiftData, sales, company, comProdutos), cssDoTalao(larguraDoTalao(company)));
    },
};

export type Papel = typeof PosOfflineTicket;
