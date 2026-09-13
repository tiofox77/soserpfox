/**
 * AS BIBLIOTECAS PESADAS, SÓ QUANDO FAZEM FALTA.
 *
 * O html2canvas e o jsPDF são 560 KB servidos de /vendor/js. Não entram no
 * pacote do React de propósito: só descem na primeira vez que alguém carrega
 * num botão de PDF, e ficam guardadas para as seguintes.
 */
declare global {
    interface Window {
        html2canvas?: (el: HTMLElement, opcoes?: Record<string, unknown>) => Promise<HTMLCanvasElement>;
        jspdf?: { jsPDF: new (...args: unknown[]) => JsPdf };
    }
}

export type JsPdf = {
    addPage: () => void;
    addImage: (dados: string, formato: string, x: number, y: number, largura: number, altura: number) => void;
    save: (nome: string) => void;
    text: (texto: string, x: number, y: number) => void;
    setFontSize: (n: number) => void;
    setTextColor: (...cor: number[]) => void;
    getNumberOfPages: () => number;
    output: (tipo: string) => unknown;
};

const aCarregar = new Map<string, Promise<void>>();

export function carregarScript(src: string): Promise<void> {
    const existente = aCarregar.get(src);
    if (existente) return existente;

    const promessa = new Promise<void>((resolve, reject) => {
        const s = document.createElement('script');
        s.src = src;
        s.addEventListener('load', () => resolve(), { once: true });
        s.addEventListener('error', () => {
            aCarregar.delete(src);
            reject(new Error(`Não foi possível carregar ${src}`));
        }, { once: true });
        document.head.appendChild(s);
    });

    aCarregar.set(src, promessa);

    return promessa;
}

export async function garantirGeradorDePdf(): Promise<void> {
    if (window.html2canvas && window.jspdf?.jsPDF) return;

    await Promise.all(['/vendor/js/html2canvas.min.js', '/vendor/js/jspdf.umd.min.js'].map(carregarScript));

    if (!window.html2canvas || !window.jspdf?.jsPDF) {
        throw new Error('O gerador de PDF não ficou disponível.');
    }
}
