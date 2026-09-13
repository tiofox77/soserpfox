import { avisar } from './avisos';
import { garantirGeradorDePdf, type JsPdf } from './bibliotecas';

/**
 * PDF A PARTIR DO QUE ESTÁ NO ECRÃ — feito no browser, sem servidor.
 *
 * PORQUE EXISTE. O PDF do servidor é desenhado pelo DomPDF, que não sabe
 * flexbox. A forma de não haver dois desenhos é não desenhar duas vezes:
 * fotografa-se a própria pré-visualização, que já é o desenho aprovado, e
 * embrulha-se num PDF. As bibliotecas só descem quando alguém carrega no botão.
 *
 * Era o `public/js/pdf-do-documento.js`, carregado pelo layout. Passou para
 * dentro do pacote do React sem mudar o contrato: um botão com
 * `data-pdf-preview` (ou `data-pdf-elemento`) e o clique ouvido por DELEGAÇÃO
 * no documento — as linhas das tabelas nascem e morrem a cada filtro.
 *
 * LIMITE HONESTO: o PDF sai em imagem. Para texto pesquisável, o PDF do
 * servidor continua ao lado.
 */

const A4_LARGURA_MM = 210;
const A4_ALTURA_MM = 297;
const PX_POR_MM = 96 / 25.4;

type Opcoes = { escala?: number; seletor?: string; guardar?: boolean };

/** O logotipo e o QR são imagens: fotografar antes de carregarem dá buracos brancos. */
function esperarImagens(doc: Document, limiteMs = 4000): Promise<unknown> {
    const todas = Promise.all(Array.from(doc.images).map((img) => img.complete
        ? Promise.resolve()
        : new Promise((r) => { img.addEventListener('load', r, { once: true }); img.addEventListener('error', r, { once: true }); })));

    return Promise.race([todas, new Promise((r) => setTimeout(r, limiteMs))]);
}

/**
 * A imagem do documento cortada em folhas A4. A TOLERÂNCIA não é enfeite: a
 * fatia arredonda para baixo, e um documento de uma folha exacta gerava uma
 * segunda página em branco.
 */
function pdfDeCanvas(canvas: HTMLCanvasElement): JsPdf {
    const pdf = new window.jspdf!.jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
    const alturaFatiaPx = Math.floor((canvas.width * A4_ALTURA_MM) / A4_LARGURA_MM);
    const tolerancia = Math.max(2, Math.ceil(canvas.width * 0.004));
    let primeira = true;

    for (let topo = 0; topo + tolerancia < canvas.height; topo += alturaFatiaPx) {
        const altura = Math.min(alturaFatiaPx, canvas.height - topo);
        const fatia = document.createElement('canvas');
        fatia.width = canvas.width;
        fatia.height = altura;
        fatia.getContext('2d')!.drawImage(canvas, 0, topo, canvas.width, altura, 0, 0, canvas.width, altura);

        if (!primeira) pdf.addPage();
        pdf.addImage(fatia.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, A4_LARGURA_MM, (altura * A4_LARGURA_MM) / canvas.width);
        primeira = false;
    }

    return pdf;
}

/** A altura é a do DOCUMENTO, não a da página que o contém (senão entram as margens e uma folha a mais). */
function fotografar(elemento: HTMLElement, escala = 2): Promise<HTMLCanvasElement> {
    const larguraPx = Math.round(elemento.getBoundingClientRect().width) || Math.round(A4_LARGURA_MM * PX_POR_MM);
    const alturaPx = Math.max(elemento.scrollHeight, 1);

    return window.html2canvas!(elemento, {
        scale: escala,
        useCORS: true,
        backgroundColor: '#ffffff',
        logging: false,
        width: larguraPx,
        height: alturaPx,
        windowWidth: larguraPx,
        windowHeight: alturaPx,
        // Barras de acções, filtros e paginação marcadas com data-pdf-fora ficam de fora do papel.
        ignoreElements: (el: Element) => el.nodeType === 1 && el.hasAttribute('data-pdf-fora'),
    });
}

export function nomeSeguro(nome: string | null | undefined): string {
    const limpo = String(nome || 'documento').replace(/[\\/:*?"<>|]+/g, '-').replace(/\s+/g, '_').replace(/\.pdf$/i, '');

    return `${limpo || 'documento'}.pdf`;
}

/** `guardar: false` faz o PDF sem o descarregar — é o que os ensaios usam para medir páginas. */
function guardar(pdf: JsPdf, nome: string | null | undefined, opcoes: Opcoes): JsPdf {
    if (opcoes.guardar !== false) pdf.save(nomeSeguro(nome));

    return pdf;
}

/**
 * O PDF de uma pré-visualização, pelo seu endereço: o MESMO HTML do separador
 * novo, numa moldura escondida à largura de uma folha.
 */
export async function daPreVisualizacao(url: string, nomeFicheiro?: string | null, opcoes: Opcoes = {}): Promise<JsPdf> {
    const o = { escala: 2, seletor: '.page-wrapper', ...opcoes };
    await garantirGeradorDePdf();

    const resposta = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
    if (!resposta.ok) throw new Error(`A pré-visualização respondeu ${resposta.status}.`);

    const html = await resposta.text();
    const moldura = document.createElement('iframe');
    moldura.setAttribute('aria-hidden', 'true');
    moldura.style.cssText = `position:fixed;left:-10000px;top:0;width:${Math.round(A4_LARGURA_MM * PX_POR_MM)}px;height:10px;border:0;opacity:0;pointer-events:none`;
    document.body.appendChild(moldura);

    try {
        const doc = moldura.contentDocument!;
        doc.open();
        doc.write(html);
        doc.close();

        // A barra de deslocamento roubava 15 px à folha e partia a proporção A4:
        // sem barra, e a moldura cresce ANTES de se medir.
        const semBarra = doc.createElement('style');
        semBarra.textContent = 'html{scrollbar-width:none}html::-webkit-scrollbar{display:none}';
        (doc.head || doc.documentElement).appendChild(semBarra);
        moldura.style.height = '4000px';

        await esperarImagens(doc, 4000);

        const alvo = (doc.querySelector(o.seletor) as HTMLElement | null) || doc.body;
        moldura.style.height = `${Math.max(alvo.scrollHeight, 1)}px`;
        await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));

        // O nome sai do TÍTULO da própria pré-visualização — «Fatura de Venda FT A/000035».
        return guardar(pdfDeCanvas(await fotografar(alvo, o.escala)), nomeFicheiro || doc.title, o);
    } finally {
        moldura.remove();
    }
}

/** O PDF de um pedaço da página actual — um relatório, um resumo de turno. */
export async function doElemento(elemento: string | HTMLElement, nomeFicheiro?: string | null, opcoes: Opcoes = {}): Promise<JsPdf> {
    await garantirGeradorDePdf();

    const alvo = typeof elemento === 'string' ? (document.querySelector(elemento) as HTMLElement | null) : elemento;
    if (!alvo) throw new Error('Não encontrei o que fotografar na página.');

    await esperarImagens(document, 2000);

    return guardar(pdfDeCanvas(await fotografar(alvo, opcoes.escala ?? 2)), nomeFicheiro, opcoes);
}

/** Enquanto trabalha, o botão diz que está a trabalhar — senão carrega-se duas vezes e saem dois ficheiros. */
function ocupar(botao: HTMLElement): () => void {
    const icone = botao.querySelector('i');
    const original = icone?.className ?? null;

    botao.dataset.aTrabalhar = '1';
    botao.setAttribute('aria-busy', 'true');
    botao.style.pointerEvents = 'none';
    botao.style.opacity = '0.6';
    if (icone) icone.className = 'fas fa-spinner fa-spin';

    return () => {
        delete botao.dataset.aTrabalhar;
        botao.removeAttribute('aria-busy');
        botao.style.pointerEvents = '';
        botao.style.opacity = '';
        if (icone && original) icone.className = original;
    };
}

let ligado = false;

/** O ouvinte por delegação e o `window.PdfDoDocumento` (que os ensaios de browser usam). */
export function ligarPdfDoDocumento(): void {
    if (ligado) return;
    ligado = true;

    document.addEventListener('click', (evento) => {
        const botao = (evento.target as Element | null)?.closest?.('[data-pdf-preview], [data-pdf-elemento]') as HTMLElement | null;
        if (!botao || botao.dataset.aTrabalhar) return;

        evento.preventDefault();
        const libertar = ocupar(botao);
        const nome = botao.dataset.pdfNome || null;

        const trabalho = botao.dataset.pdfPreview
            ? daPreVisualizacao(botao.dataset.pdfPreview, nome)
            : doElemento(botao.dataset.pdfElemento!, nome || document.title);

        trabalho
            .catch((erro) => {
                console.error('[PDF]', erro);
                avisar(botao.dataset.pdfErro || 'Não foi possível gerar o PDF.', 'erro');
            })
            .finally(libertar);
    });

    (window as unknown as { PdfDoDocumento: unknown }).PdfDoDocumento = { garantirBibliotecas: garantirGeradorDePdf, daPreVisualizacao, doElemento, nomeSeguro };
}
