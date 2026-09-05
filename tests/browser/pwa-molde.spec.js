import { test, expect } from '@playwright/test';
import {
    entrar, esperarServiceWorker, esperarMotor, esperarCatalogo,
    lerBase, sincronizar, irPara, avaliar,
} from './apoio.js';

/**
 * O papel do documento sem rede é o MESMO da pré-visualização do servidor.
 *
 * Não por parecer: por ser. O servidor manda o seu modelo com marcas, o
 * aparelho preenche-o. Aqui compara-se o papel feito sem rede com a
 * pré-visualização do mesmo documento depois de emitido — as mesmas classes
 * de desenho, os mesmos números.
 */

async function aparelhoPreparado(page) {
    await entrar(page);
    await irPara(page, '/invoicing/offline/drafts');
    await esperarServiceWorker(page);
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 2);
    await page.waitForFunction(async () => !!(await window.SosPwa.db.meta.get('molde_FT')), null, { timeout: 60_000 });
}

/** As classes CSS usadas no HTML — o esqueleto do desenho. */
const classesDe = (html) => {
    const d = new DOMParser().parseFromString(html, 'text/html');
    const c = new Set();
    d.querySelectorAll('.main-content [class]').forEach((el) => el.classList.forEach((k) => c.add(k)));
    return Array.from(c).sort();
};

const textoDe = (html) => new DOMParser().parseFromString(html, 'text/html').querySelector('.main-content').textContent.replace(/\s+/g, ' ');

test.describe('PWA — o papel sem rede é o modelo do servidor', () => {
    for (const [tipo, nome] of [['FT', 'Fatura'], ['proforma', 'Proforma']]) {
        test(`${nome}: sem rede sai do molde, e bate com a pré-visualização depois de emitida`, async ({ page, context }) => {
            await aparelhoPreparado(page);

            const cliente = await avaliar(page, async () => {
                const c = await window.SosPwa.db.clients.filter((x) => Number.isInteger(x.id) && !!x.nif).first();
                return c ? { id: c.id, name: c.name, nif: c.nif } : null;
            });
            expect(cliente, 'a bancada tem clientes com NIF').toBeTruthy();

            await context.setOffline(true);

            const uuid = await avaliar(page, async ([t, cli]) => {
                const artigos = await window.SosPwa.db.products.limit(2).toArray();
                const r = await window.SosPwa.createDraftOffline({
                    doc_type: t, client_id: cli.id, client_name: cli.name,
                    invoice_date: new Date().toISOString().slice(0, 10), due_date: '2026-10-03',
                    discount_commercial: 100, discount_financial: 0, is_service: false,
                    items: [
                        { product_id: artigos[0].id, product_name: artigos[0].name, description: 'linha com descrição', quantity: 2, unit_price: Number(artigos[0].price), tax_rate: 14, discount_percent: 10 },
                        { product_id: artigos[1].id, product_name: artigos[1].name, quantity: 1, unit_price: 500, tax_rate: 14, discount_percent: 0 },
                    ],
                });
                return r.local_uuid;
            }, [tipo, cliente]);

            // O papel sem rede, pelo mesmo caminho da impressão e do PDF.
            const semRede = await avaliar(page, (u) => window.SosPwa.htmlDoPapel(u), uuid);
            expect(semRede, 'com molde no aparelho, há papel').toBeTruthy();
            expect(semRede).not.toMatch(/%%[A-Z_0-9]+%%/);
            expect(semRede).not.toContain('LINHA-AUXILIAR');
            expect(semRede).toContain('DOCUMENTO PROVISÓRIO');

            const textoSemRede = await avaliar(page, (h) => {
                const d = new DOMParser().parseFromString(h, 'text/html');
                return d.querySelector('.main-content').textContent.replace(/\s+/g, ' ');
            }, semRede);
            expect(textoSemRede).toContain(cliente.name);
            expect(textoSemRede).toContain('NIF: ' + cliente.nif);
            expect(textoSemRede).toContain('linha com descrição');
            expect(textoSemRede).toMatch(/n\.º LOCAL-[A-Z0-9]{6}/);

            // A rede volta: o documento é emitido e o servidor mostra a sua pré-visualização.
            await context.setOffline(false);
            const idNoServidor = await expect
                .poll(async () => {
                    const d = (await lerBase(page, 'draft_documents')).find((x) => x.local_uuid === uuid);
                    if (d?._server_id) return d._server_id;
                    await sincronizar(page).catch(() => {});
                    return null;
                }, { timeout: 60_000, intervals: [1000] })
                .not.toBeNull()
                .then(async () => (await lerBase(page, 'draft_documents')).find((x) => x.local_uuid === uuid)._server_id);

            const rota = tipo === 'proforma' ? 'proformas' : 'invoices';
            const online = await avaliar(page, async (url) => (await fetch(url, { credentials: 'same-origin' })).text(), `/invoicing/sales/${rota}/${idNoServidor}/preview`);

            // O MESMO ESQUELETO: as classes de desenho do papel sem rede são as
            // da pré-visualização (a faixa de provisório é a única coisa a mais).
            const [classesOffline, classesOnline] = await avaliar(page, ([a, b]) => {
                const classesDe = (html) => {
                    const d = new DOMParser().parseFromString(html, 'text/html');
                    const c = new Set();
                    d.querySelectorAll('.main-content [class]').forEach((el) => el.classList.forEach((k) => c.add(k)));
                    return Array.from(c).sort();
                };
                return [classesDe(a), classesDe(b)];
            }, [semRede, online]);
            expect(classesOffline.filter((k) => k !== 'pwa-provisorio')).toEqual(classesOnline);

            // OS MESMOS NÚMEROS: os totais que o cliente lê.
            const textoOnline = await avaliar(page, (h) => {
                const d = new DOMParser().parseFromString(h, 'text/html');
                return d.querySelector('.main-content').textContent.replace(/\s+/g, ' ');
            }, online);
            const totais = (t) => (t.match(/Total Ilíquido [0-9.,]+|Desc\. Comercial [0-9.,]+|Total da Fatura [0-9.,]+|Total a Pagar [0-9.,]+|Total Proforma [0-9.,]+/g) || []);
            expect(totais(textoSemRede)).toEqual(totais(textoOnline));
        });
    }
});
