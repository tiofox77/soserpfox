import { carregarScript } from '@/casca/bibliotecas';

/**
 * EXPORTAR O PAINEL DA FACTURAÇÃO — PDF e CSV.
 *
 * Era o `public/js/painel-facturacao.js`, que lia tudo do DOM (os nós
 * `textosPainel` e `dadosVendas`) porque tinha nascido para sobreviver à
 * navegação do Livewire. O ecrã em React continua a escrever esses nós; esta
 * função lê-os da mesma maneira, para o PDF e o CSV saírem exactamente como
 * saíam.
 */
type TextosDoPainel = {
    intl: string;
    t: Record<string, string>;
    valores: Record<string, string>;
};

function lerJson<T>(id: string): T | null {
    const no = document.getElementById(id);
    if (!no?.textContent) return null;

    try {
        return JSON.parse(no.textContent) as T;
    } catch {
        return null;
    }
}

function textos(): TextosDoPainel {
    return lerJson<TextosDoPainel>('textosPainel') ?? { intl: 'pt-PT', t: {}, valores: {} };
}

export async function exportarPdfDoPainel(): Promise<void> {
    const cfg = textos();

    await carregarScript('/vendor/js/jspdf.umd.min.js');

    const doc = new window.jspdf!.jsPDF('p', 'mm', 'a4');
    doc.setFontSize(18);
    doc.setTextColor(59, 130, 246);
    doc.text(cfg.t.titulo ?? '', 15, 20);

    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text(String(cfg.t.geradoEm ?? '').replace(':data', new Date().toLocaleDateString(cfg.intl)), 15, 28);

    doc.setFontSize(12);
    doc.setTextColor(0);

    let y = 40;
    ([
        [cfg.t.facturado, cfg.valores.facturado],
        [cfg.t.recebido, cfg.valores.recebido],
        [cfg.t.pendente, cfg.valores.pendente],
        [cfg.t.vencido, cfg.valores.vencido],
    ] as const).forEach(([rotulo, valor]) => {
        doc.text(`${rotulo}: ${valor} AOA`, 15, y);
        y += 8;
    });

    doc.save('dashboard-faturacao.pdf');
}

/**
 * OS VALORES DO CSV NÃO LEVAM SEPARADOR DE MILHARES: um `1.234,56` num ficheiro
 * separado por vírgulas abre com uma coluna a mais. Os valores `…Cru` vêm já
 * com ponto decimal.
 */
export function exportarCsvDoPainel(): void {
    const cfg = textos();
    const dados = lerJson<Array<{ date: string; total: number }>>('dadosVendas') ?? [];

    let csv = `${cfg.t.data},${cfg.t.valorAoa}\n`;
    dados.forEach((i) => { csv += `${new Date(i.date).toLocaleDateString(cfg.intl)},${i.total}\n`; });
    csv += `\n${cfg.t.estatisticas}\n`;
    csv += `${cfg.t.facturado},${cfg.valores.facturadoCru}\n`;
    csv += `${cfg.t.recebido},${cfg.valores.recebidoCru}\n`;
    csv += `${cfg.t.pendente},${cfg.valores.pendenteCru}\n`;
    csv += `${cfg.t.vencido},${cfg.valores.vencidoCru}\n`;

    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    const ligacao = document.createElement('a');
    ligacao.href = url;
    ligacao.download = 'dashboard-faturacao.csv';
    ligacao.style.visibility = 'hidden';
    document.body.appendChild(ligacao);
    ligacao.click();
    ligacao.remove();
    URL.revokeObjectURL(url);
}
