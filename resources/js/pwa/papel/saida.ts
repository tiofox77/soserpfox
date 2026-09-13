import { t } from '@/i18n';

import { PRINT_CSS } from './talao';

/**
 * A SAÍDA DO PAPEL: a janela de impressão e o PDF feito no aparelho.
 *
 * O html2canvas e o jsPDF vêm de /vendor (pré-guardados pelo service worker):
 * sem rede é quando o PDF para o WhatsApp mais falta faz.
 */

// O `window.html2canvas` e o `window.jspdf` estão declarados em casca/bibliotecas.ts.

/** Uma página HTML interna (talão, relatório, documento) — abre e imprime. */
export function printHtml(interior: string, css?: string | null, janela?: string): void {
    // O <title> é da janela do navegador, não do documento impresso — traduz-se.
    imprimirDocumentoHtml(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>${t('Ticket POS')}</title>
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;700&display=swap" rel="stylesheet">
            <style>${css || PRINT_CSS}</style></head><body>${interior}</body></html>`, janela);
}

/** Um documento HTML inteiro (o molde do servidor, preenchido) — abre e imprime. */
export function imprimirDocumentoHtml(documentoHtml: string, janela?: string): void {
    const win = window.open('', '_blank', janela || 'width=380,height=700');

    if (!win) {
        // Aviso da aplicação (o `<Dialogos />` ouve-o) e não um `alert()`.
        window.dispatchEvent(new CustomEvent('pwa:aviso', {
            detail: { id: Date.now(), tipo: 'erro', texto: t('O navegador bloqueou a janela de impressão. Permita pop-ups para este site.'), duracao: 8000 },
        }));

        return;
    }

    win.document.write(documentoHtml);
    win.document.close();

    const imprimir = () => {
        try { win.focus(); win.print(); } catch (e) { console.error(e); }
        setTimeout(() => { try { win.close(); } catch { /* já fechada */ } }, 800);
    };

    // SÓ SE IMPRIME COM A PÁGINA ASSENTE. O molde passa dos 700 KB (logótipo
    // embutido); chamar o print cedo dava, no Android, «There was a problem
    // printing the page».
    const quandoPronto = (fn: () => void) => {
        const espera = () => (win.document.readyState === 'complete' ? setTimeout(fn, 600) : setTimeout(espera, 100));
        espera();
    };

    const imgs = win.document.images;
    if (!imgs || imgs.length === 0) {
        quandoPronto(imprimir);

        return;
    }

    let carregadas = 0;
    const uma = () => { if (++carregadas >= imgs.length) quandoPronto(imprimir); };

    for (let i = 0; i < imgs.length; i++) {
        const img = imgs[i]!;
        if (img.complete) uma();
        else { img.addEventListener('load', uma); img.addEventListener('error', uma); }
    }

    setTimeout(() => { if (carregadas < imgs.length) imprimir(); }, 3000);
}

function esperarImagens(doc: Document, prazoMs = 3000): Promise<void> {
    const imgs = Array.from(doc.images || []);
    if (!imgs.length) return Promise.resolve();

    return new Promise((resolve) => {
        let feitas = 0;
        const uma = () => { if (++feitas >= imgs.length) resolve(); };

        imgs.forEach((img) => {
            if (img.complete) { uma(); return; }
            img.addEventListener('load', uma, { once: true });
            img.addEventListener('error', uma, { once: true });
        });

        setTimeout(resolve, prazoMs);
    });
}

/**
 * O PDF NO PRÓPRIO APARELHO, a partir do MESMO HTML que vai para a impressora.
 *
 * Não há um segundo desenho: o html2canvas desenha o HTML de sempre e o jsPDF
 * embrulha a imagem. É PDF de imagem — chega para ir pelo WhatsApp. Emitido e
 * com rede, o PDF a sério é o do servidor (ver `pdfDe`).
 */
export async function pdfHtml(
    interior: string,
    css: string | null,
    opcoes: { larguraMm?: number; a4?: boolean; escala?: number; completo?: boolean } = {},
): Promise<Blob> {
    const o = { larguraMm: 80, a4: false, escala: 2, completo: false, ...opcoes };

    if (!window.html2canvas || !window.jspdf?.jsPDF) {
        throw new Error(t('O gerador de PDF ainda não carregou. Tente outra vez.'));
    }

    const larguraPx = Math.round(o.larguraMm * 96 / 25.4);
    const iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.cssText = `position:fixed;left:-10000px;top:0;width:${larguraPx}px;height:10px;border:0;opacity:0;pointer-events:none;`;
    document.body.appendChild(iframe);

    try {
        const d = iframe.contentDocument!;
        d.open();
        d.write(o.completo
            ? interior
            : `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>${css || PRINT_CSS} html,body{margin:0;padding:0;background:#fff;}</style></head><body>${interior}</body></html>`);
        d.close();

        // A BARRA DE DESLOCAMENTO ROUBAVA 15 PIXÉIS À FOLHA e a proporção deixava
        // de ser A4 (um documento de duas linhas saía em duas páginas). Sem
        // barra, e a moldura cresce ANTES de se medir.
        const semBarra = d.createElement('style');
        semBarra.textContent = 'html{scrollbar-width:none}html::-webkit-scrollbar{display:none}';
        (d.head || d.documentElement).appendChild(semBarra);
        iframe.style.height = '4000px';

        await esperarImagens(d, 3000);

        // A altura é a do DOCUMENTO — o documentElement trazia as margens e uma página a mais.
        const corpo = d.body;
        const altura = Math.max(corpo.scrollHeight, 1);
        iframe.style.height = altura + 'px';

        const canvas = await window.html2canvas(corpo, {
            scale: o.escala, useCORS: true, backgroundColor: '#ffffff', logging: false,
            width: larguraPx, height: altura, windowWidth: larguraPx, windowHeight: altura,
        });

        const { jsPDF } = window.jspdf;
        let pdf: InstanceType<typeof jsPDF>;

        if (o.a4) {
            const pagW = 210;
            const pagH = 297;
            const fatiaPx = Math.floor(canvas.width * pagH / pagW);
            // A TOLERÂNCIA NÃO É ENFEITE: a fatia arredonda para baixo e um
            // documento de uma folha exacta gerava uma segunda página em branco.
            const tolerancia = Math.max(2, Math.ceil(canvas.width * 0.004));

            pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
            let primeira = true;

            for (let topo = 0; topo + tolerancia < canvas.height; topo += fatiaPx) {
                const parte = document.createElement('canvas');
                parte.width = canvas.width;
                parte.height = Math.min(fatiaPx, canvas.height - topo);
                parte.getContext('2d')!.drawImage(canvas, 0, topo, canvas.width, parte.height, 0, 0, canvas.width, parte.height);
                if (!primeira) pdf.addPage();
                pdf.addImage(parte.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, pagW, parte.height * pagW / canvas.width);
                primeira = false;
            }
        } else {
            // O talão: uma página só, com a altura do próprio talão.
            const alturaMm = Math.max(canvas.height * o.larguraMm / canvas.width, 40);
            pdf = new jsPDF({ unit: 'mm', format: [o.larguraMm, alturaMm], orientation: 'portrait' });
            pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, o.larguraMm, canvas.height * o.larguraMm / canvas.width);
        }

        return pdf.output('blob') as Blob;
    } finally {
        iframe.remove();
    }
}
