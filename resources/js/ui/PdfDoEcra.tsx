import { ligarPdfDoDocumento } from '@/casca/pdfDoDocumento';
import { FOCO, RAIO, cls } from '@/ui/tokens';

// O ouvinte liga-se uma vez, onde quer que haja um destes botões — também
// fora do layout principal. Ligar duas vezes não faz nada.
ligarPdfDoDocumento();

/**
 * DESCARREGAR O DOCUMENTO EM PDF, FEITO NO BROWSER.
 *
 * PORQUE EXISTE. O PDF do servidor é desenhado pelo DomPDF, que não sabe
 * flexbox: os modelos trazem uma camada de correcções só para ele e o papel
 * nunca é exactamente a pré-visualização. A forma de não haver dois desenhos é
 * não desenhar duas vezes — fotografa-se a própria pré-visualização, que já é
 * o desenho aprovado, e embrulha-se numa folha A4.
 *
 * Isto ACRESCENTA, não substitui: o PDF do servidor continua ao lado, e é ele
 * que serve a quem precisa de texto para copiar ou pesquisar.
 *
 * O QUE ESTE COMPONENTE É. Só o contrato do botão. A mecânica inteira
 * (html2canvas + jsPDF, o corte em páginas, a moldura escondida) vive em
 * `casca/pdfDoDocumento.ts`, que ouve o clique POR DELEGAÇÃO no documento — e
 * é por isso que funciona com DOM feito pelo React, que troca as linhas da
 * tabela a cada filtro. Aqui não se escuta o clique, de propósito: o botão é
 * os seus `data-*` e mais nada — um manipulador próprio seria uma segunda
 * mecânica a divergir da primeira.
 */
export function PdfDoEcra({
    url,
    nome,
    titulo = 'Descarregar PDF',
}: {
    /** O endereço da pré-visualização do documento (`…/{id}/preview`). */
    url: string;
    /** Nome do ficheiro. Vazio, sai do título da própria pré-visualização. */
    nome?: string;
    titulo?: string;
}) {
    return (
        <button
            type="button"
            data-pdf-preview={url}
            {...(nome ? { 'data-pdf-nome': nome } : {})}
            data-pdf-erro="Não foi possível gerar o PDF."
            title={titulo}
            aria-label={titulo}
            className={cls('p-2 text-rose-600 transition hover:bg-rose-50', RAIO, FOCO)}
        >
            <i className="fas fa-file-arrow-down" aria-hidden="true" />
        </button>
    );
}
