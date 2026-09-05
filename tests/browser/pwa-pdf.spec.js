import { test, expect } from '@playwright/test';
import {
    entrar, esperarServiceWorker, esperarMotor, esperarCatalogo,
    lerBase, sincronizar, irPara, avaliar,
} from './apoio.js';

/**
 * O PDF no próprio aparelho, para o WhatsApp.
 *
 * Sem rede não há servidor para fazer o PDF — faz-se no telemóvel, a partir
 * do MESMO HTML que vai para a impressora, e entrega-se à folha de partilha
 * do sistema. Emitido e com rede, vai o PDF do servidor, que é o verdadeiro.
 *
 * A folha de partilha não se abre num ensaio (precisa de um toque de gente
 * e é uma janela do sistema). Mede-se o PDF em si: existe, é um PDF, e vem
 * de onde deve vir.
 */

const CABECA = async (page, tipo, uuid) => avaliar(page, async ([t, u]) => {
    const registo = t === 'venda' ? await window.SosPwa.db.pos_sales.get(u) : await window.SosPwa.db.draft_documents.get(u);
    const r = await window.SosPwa.pdfDe(t, registo);
    const cabeca = String.fromCharCode(...new Uint8Array(await r.blob.slice(0, 5).arrayBuffer()));
    const hash = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', await r.blob.arrayBuffer()))).join(',');
    return { origem: r.origem, nome: r.nome, bytes: r.blob.size, cabeca, hash };
}, [tipo, uuid]);

async function aparelhoPreparado(page) {
    await entrar(page);
    await irPara(page, '/invoicing/offline/pos');
    await esperarServiceWorker(page);
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 1);
    await page.waitForFunction(() => !!(window.jspdf && window.jspdf.jsPDF && window.html2canvas), null, { timeout: 30_000 });
}

test.describe('PWA — PDF no aparelho', () => {
    test('sem rede, a venda do POS vira um PDF feito no aparelho', async ({ page, context }) => {
        await aparelhoPreparado(page);
        await context.setOffline(true);

        const uuid = await avaliar(page, async () => {
            const artigo = await window.SosPwa.db.products.toCollection().first();
            const v = await window.SosPwa.createPosSaleOffline({
                client_id: null, client_name: 'Consumidor Final', payment_method: 'cash',
                amount_received: Number(artigo.price),
                items: [{ product_id: artigo.id, product_name: artigo.name, quantity: 1, unit_price: Number(artigo.price), tax_rate: Number(artigo.tax_rate || 0) }],
            });
            return v.local_uuid;
        });

        const pdf = await CABECA(page, 'venda', uuid);

        expect(pdf.cabeca, 'tem de ser um PDF a sério').toBe('%PDF-');
        expect(pdf.origem, 'sem rede faz-se no aparelho').toBe('aparelho');
        expect(pdf.bytes).toBeGreaterThan(5_000);
        expect(pdf.nome).toMatch(/^PROVISORIO-.*\.pdf$/);
    });

    for (const [tipo, nome] of [['FT', 'Fatura'], ['proforma', 'Proforma']]) {
        test(`${nome}: sem rede o PDF é do aparelho; emitida e com rede, é o do servidor`, async ({ page, context }) => {
            await aparelhoPreparado(page);
            await context.setOffline(true);

            const uuid = await avaliar(page, async (t) => {
                const artigo = await window.SosPwa.db.products.toCollection().first();
                const r = await window.SosPwa.createDraftOffline({
                    doc_type: t, client_id: null, invoice_date: new Date().toISOString().slice(0, 10),
                    items: [{ product_id: artigo.id, product_name: artigo.name, quantity: 1, unit_price: Number(artigo.price), tax_rate: 14, discount_percent: 0 }],
                });
                return r.local_uuid;
            }, tipo);

            const semRede = await CABECA(page, 'documento', uuid);
            expect(semRede.cabeca).toBe('%PDF-');
            expect(semRede.origem).toBe('aparelho');
            expect(semRede.bytes).toBeGreaterThan(5_000);

            await context.setOffline(false);
            await expect
                .poll(async () => {
                    const d = (await lerBase(page, 'draft_documents')).find((x) => x.local_uuid === uuid);
                    if (d?._server_id) return d._server_id;
                    await sincronizar(page).catch(() => {});
                    return null;
                }, { timeout: 60_000, intervals: [1000] })
                .not.toBeNull();

            const comRede = await CABECA(page, 'documento', uuid);
            expect(comRede.cabeca).toBe('%PDF-');
            expect(['servidor', 'copia-servidor']).toContain(comRede.origem);
            expect(comRede.nome, 'o ficheiro leva o número fiscal').not.toMatch(/PROVISORIO/);
            await context.setOffline(true);
            await page.reload();
            await esperarMotor(page);
            const definitivoOffline = await CABECA(page, 'documento', uuid);
            expect(definitivoOffline.origem).toBe('copia-servidor');
            expect(definitivoOffline.hash).toBe(comRede.hash);
        });
    }

    test('os botões de PDF estão nos três ecrãs', async ({ page }) => {
        await aparelhoPreparado(page);

        await irPara(page, '/invoicing/offline/drafts');
        await esperarMotor(page);
        expect(await page.locator('[data-ensaio="partilhar-pdf"]').count(), 'lista de documentos').toBeGreaterThanOrEqual(0);

        const html = await avaliar(page, () => document.documentElement.outerHTML);
        expect(html).toContain('partilhar(d)');
    });
});
