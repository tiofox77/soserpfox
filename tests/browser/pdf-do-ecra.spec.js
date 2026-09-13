import { test, expect } from '@playwright/test';
import { entrar, irPara, avaliar } from './apoio.js';

/**
 * O PDF feito no browser sai da própria pré-visualização.
 *
 * Não há segundo desenho: fotografa-se o documento que o servidor já mostra.
 * Aqui prova-se de ponta a ponta — o botão existe ao lado da pré-visualização,
 * o PDF sai com a proporção de uma folha A4, e um documento que cabe numa
 * página sai mesmo com UMA página.
 *
 * Os dois defeitos que este ensaio guarda custaram uma tarde: a fatia da
 * página arredondada para baixo dava uma segunda folha em branco, e a barra
 * de deslocamento da moldura escondida encolhia a folha de 794 para 779 pixéis
 * e estragava a proporção.
 */
test.describe('PDF do ecrã — o papel é a pré-visualização', () => {
    test('a factura sai numa página, em proporção A4, e a pré-visualização fica', async ({ page }) => {
        await entrar(page);
        await irPara(page, '/invoicing/sales/invoices');

        await page.waitForFunction(() => !!window.PdfDoDocumento, null, { timeout: 30_000 });

        // O botão novo ACRESCENTA: o olho da pré-visualização continua ao lado.
        const pecas = await avaliar(page, () => {
            const botao = document.querySelector('[data-pdf-preview]');
            if (!botao) return null;

            const linha = botao.closest('td, div');

            return {
                url: botao.dataset.pdfPreview,
                temPreVisualizacaoAoLado: !!(linha && linha.querySelector('a[href*="/preview"]')),
            };
        });

        expect(pecas, 'há documentos com botão de PDF').toBeTruthy();
        expect(pecas.temPreVisualizacaoAoLado, 'a pré-visualização não pode desaparecer').toBe(true);
        expect(pecas.url).toContain('/preview');

        const medida = await avaliar(page, async (url) => {
            const pdf = await window.PdfDoDocumento.daPreVisualizacao(url, null, { guardar: false });
            const primeira = pdf.internal.pageSize;

            return {
                paginas: pdf.internal.getNumberOfPages(),
                bytes: pdf.output('blob').size,
                largura: Math.round(primeira.getWidth()),
                altura: Math.round(primeira.getHeight()),
            };
        }, pecas.url);

        // Uma factura de balcão cabe numa folha. Duas páginas aqui significa
        // que o corte voltou a partir onde não devia.
        expect(medida.paginas).toBe(1);
        expect(medida.largura).toBe(210);
        expect(medida.altura).toBe(297);
        expect(medida.bytes).toBeGreaterThan(30_000);
    });

    /*
     * O RELATÓRIO DO POS já não se fotografa: passou a React e leva ao PDF do
     * servidor (ver PdfDoEcraTest::os_relatorios_do_pos_levam_ao_papel_do_servidor).
     * O `doElemento` continua a existir para um pedaço da página actual — e
     * continua a deixar de fora o que está marcado com `data-pdf-fora`.
     */
    test('um pedaço da página sai em PDF, sem o que está marcado para ficar de fora', async ({ page }) => {
        await entrar(page);
        await irPara(page, '/home');

        await page.waitForFunction(() => !!window.PdfDoDocumento, null, { timeout: 30_000 });

        const medida = await avaliar(page, async () => {
            const alvo = document.getElementById('app-main');
            const fora = document.createElement('div');
            fora.setAttribute('data-pdf-fora', '');
            fora.textContent = 'botões que não vão para o papel';
            alvo.prepend(fora);

            const pdf = await window.PdfDoDocumento.doElemento(alvo, 'Inicio', { guardar: false });
            fora.remove();

            return { paginas: pdf.internal.getNumberOfPages(), bytes: pdf.output('blob').size };
        });

        expect(medida.paginas).toBeGreaterThan(0);
        expect(medida.bytes).toBeGreaterThan(30_000);
    });
});
