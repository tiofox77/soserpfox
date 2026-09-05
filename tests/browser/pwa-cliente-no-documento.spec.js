import { test, expect } from '@playwright/test';
import {
    entrar, esperarServiceWorker, esperarMotor, esperarCatalogo,
    lerBase, sincronizar, irPara, avaliar,
} from './apoio.js';

/**
 * O CLIENTE CRIADO SEM REDE, DENTRO DO DOCUMENTO CRIADO SEM REDE.
 *
 * A queixa, por palavras do dono: «criando um cliente offline e colocando na
 * fatura offline não sincroniza cliente e a fatura sai como consumidor
 * final». E: «fazendo fatura ou proforma em offline não mostra a fatura».
 *
 * Os ensaios que existiam criavam o documento pelo motor, com um cliente que
 * JÁ tinha id do servidor. Nunca seguiram o percurso de quem está ao balcão:
 * o formulário do cliente, o selector de cliente do documento, a lista, e
 * depois a rede a voltar. É esse percurso que aqui se faz, com toques.
 */

const CARIMBO = Date.now().toString(36);

async function aparelhoPreparado(page) {
    await entrar(page);
    await irPara(page, '/invoicing/offline');
    await esperarServiceWorker(page);
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 5);

    // Os formulários têm de estar guardados ANTES de faltar a rede.
    await irPara(page, '/invoicing/offline/clients/new');
    await irPara(page, '/invoicing/offline/drafts/new');
    await irPara(page, '/invoicing/offline');
    await esperarMotor(page);
}

/** O que o servidor mostra do documento: a página de pré-visualização. */
async function previewDoServidor(page, tipo, idNoServidor) {
    const rota = tipo === 'proforma' ? 'proformas' : 'invoices';

    return avaliar(page, async (url) => {
        const r = await fetch(url, { credentials: 'same-origin' });

        return { status: r.status, texto: (await r.text()).replace(/\s+/g, ' ') };
    }, `/invoicing/sales/${rota}/${idNoServidor}/preview`);
}

test.describe('PWA — cliente criado sem rede dentro do documento', () => {
    for (const [tipo, nome] of [['FT', 'Fatura'], ['proforma', 'Proforma']]) {
        test(`${nome}: o cliente novo sobe e o documento sai em nome dele`, async ({ page, context }) => {
            await aparelhoPreparado(page);

            // Um nome ÚNICO por ensaio e por tentativa. Com o carimbo do
            // ficheiro, a proforma criava um cliente com o nome do da factura
            // (já sincronizado) e o selector apanhava o antigo, com id do
            // servidor — e o documento deixava de apontar para o local.
            const nomeCliente = `Cliente Balcão ${CARIMBO}${Math.random().toString(36).slice(2, 6)}`;
            const nif = '5' + String(Date.now()).slice(-9);

            await context.setOffline(true);

            // 1) O cliente, pelo formulário.
            await irPara(page, '/invoicing/offline/clients/new');
            await esperarMotor(page);
            await page.locator('input[x-model="form.name"]').fill(nomeCliente);
            await page.locator('input[x-model="form.nif"]').fill(nif);
            await page.locator('input[x-model="form.phone"]').fill('923000123');
            await page.getByRole('button', { name: /Guardar Cliente/ }).click();
            await expect(page.getByText(/Cliente guardado/)).toBeVisible({ timeout: 10_000 });
            await page.waitForURL(/\/invoicing\/offline\/clients$/, { timeout: 15_000 });
            await esperarMotor(page);

            // Ficou no aparelho, com id local — e aparece na lista.
            await expect
                .poll(async () => (await lerBase(page, 'clients')).some((c) => c.name === nomeCliente), { timeout: 10_000 })
                .toBe(true);
            await expect(page.getByText(nomeCliente).first()).toBeVisible({ timeout: 10_000 });

            // 2) O documento, pelo formulário, com ESSE cliente.
            await irPara(page, '/invoicing/offline/drafts/new');
            await esperarMotor(page);
            // O tipo, pelo botão do ecrã («Fatura» ou «Proforma»). Pelo texto
            // inteiro e não pelo papel: «Fatura» também é o começo de
            // «Fatura-Recibo».
            await page.locator('button', { hasText: new RegExp(`^\\s*${nome}\\s*$`) }).first().waitFor({ timeout: 15_000 });
            await page.locator('button', { hasText: new RegExp(`^\\s*${nome}\\s*$`) }).first().click();

            await page.getByRole('button', { name: /Selecionar cliente/ }).click();
            await page.locator('input[x-model="clientSearch"]').fill(nomeCliente);
            await page.locator('[x-show="showClientPicker"] button[type="button"]', { hasText: nomeCliente }).first().click();
            await expect(page.getByText(nomeCliente).first()).toBeVisible();

            await page.getByRole('button', { name: /Adicionar/ }).first().click();
            await page.locator('input[x-model="productSearch"]').waitFor({ timeout: 10_000 });
            await page.locator('[x-show="showProductPicker"] button[type="button"]').first().click();

            await page.getByRole('button', { name: /Emitir Documento/ }).click();
            await expect(page.getByText(/Documento guardado/)).toBeVisible({ timeout: 10_000 });

            // 2b) IMPRIME-SE SEM REDE: o papel provisório, com o nome do cliente.
            //
            // A impressora fica de fora — guarda-se o HTML que ia para ela e
            // é esse que se lê. Uma factura sem número leva a faixa de
            // provisório; a proforma leva o aviso de que não serve de factura.
            const papel = await avaliar(page, async ([nome, t]) => {
                const balde = [];
                window.PosOfflineTicket.printDocument = (doc, company) => {
                    balde.push(window.PosOfflineTicket.buildDocumentHtml(doc, company));
                };
                const d = (await window.SosPwa.db.draft_documents.orderBy('created_at').reverse().toArray())
                    .find((x) => x.client_name === nome && x.doc_type === t);
                await window.SosPwa.imprimirDocumento(d.local_uuid);
                const html = balde[0] || '';
                return { comNome: html.includes(nome), provisorio: html.includes('DOCUMENTO PROVISÓRIO'), naoServe: html.includes('NÃO SERVE DE FACTURA') };
            }, [nomeCliente, tipo]);
            expect(papel.comNome, 'o papel sem rede tem de ser em nome do cliente').toBe(true);
            if (tipo === 'proforma') expect(papel.naoServe).toBe(true);
            else expect(papel.provisorio, 'sem número fiscal, o papel diz que é provisório').toBe(true);

            // 3) A lista mostra-o, em nome do cliente, por enviar.
            await irPara(page, '/invoicing/offline/drafts');
            await esperarMotor(page);
            await expect(page.getByText(nomeCliente).first()).toBeVisible({ timeout: 10_000 });
            await expect(page.getByText('Pendente').first()).toBeVisible();

            const local = (await lerBase(page, 'draft_documents')).find((d) => d.client_name === nomeCliente);
            expect(local, 'o documento ficou guardado no aparelho').toBeTruthy();
            expect(local.doc_type).toBe(tipo);
            expect(local.client_local_uuid, 'o documento aponta para o cliente local').toBeTruthy();

            // 4) A rede volta.
            await context.setOffline(false);

            const idNoServidor = await expect
                .poll(async () => {
                    const meu = (await lerBase(page, 'draft_documents')).find((d) => d.local_uuid === local.local_uuid);
                    if (meu?._server_id) return meu._server_id;
                    await sincronizar(page).catch(() => {});
                    return null;
                }, { message: `${nome} tem de chegar ao servidor`, timeout: 60_000, intervals: [1000] })
                .not.toBeNull()
                .then(async () => (await lerBase(page, 'draft_documents')).find((d) => d.local_uuid === local.local_uuid)._server_id);

            // O cliente ficou com id do servidor — e não ficou nada na fila a falhar.
            const cliente = (await lerBase(page, 'clients')).find((c) => c.name === nomeCliente);
            expect(cliente, 'o cliente continua no aparelho').toBeTruthy();
            expect(Number.isInteger(cliente.id), 'o cliente tem de ter id do servidor').toBe(true);

            const falhados = (await lerBase(page, 'sync_queue')).filter((j) => j.status === 'failed');
            expect(falhados.map((j) => `${j.op}: ${j.last_error}`), 'nada pode ter falhado').toEqual([]);

            // 5) E O SERVIDOR DIZ O NOME DELE — não «Consumidor Final».
            const preview = await previewDoServidor(page, tipo, idNoServidor);
            expect(preview.status).toBe(200);
            expect(preview.texto, 'o documento no servidor tem de ser em nome do cliente').toContain(nomeCliente);
            expect(preview.texto).not.toContain('Consumidor Final');

            // 6) Com rede e já emitido, imprimir abre o PDF do servidor — o
            //    mesmo papel do ecrã grande — e não o provisório.
            const abre = await avaliar(page, async (uuid) => {
                const urls = [];
                window.open = (u) => { urls.push(u); return {}; };
                await window.SosPwa.imprimirDocumento(uuid);
                return urls[0] || null;
            }, local.local_uuid);
            expect(abre, 'a impressão com rede abre a pré-visualização do servidor')
                .toBe(`/invoicing/sales/${tipo === 'proforma' ? 'proformas' : 'invoices'}/${idNoServidor}/preview`);
        });
    }

    /**
     * O CLIENTE RÁPIDO DO POS, SEM NIF.
     *
     * Ao balcão a maioria dos clientes não dá contribuinte. O modal do POS
     * cria-o só com o nome; a API não pode recusá-lo por isso — e se o
     * recusar, a venda segue em nome do Consumidor Final sem ninguém dar
     * por nada.
     */
    test('POS: cliente rápido sem NIF sobe e a venda sai em nome dele', async ({ page, context }) => {
        await aparelhoPreparado(page);

        const nomeCliente = `Freguês ${CARIMBO}`;

        await context.setOffline(true);
        await irPara(page, '/invoicing/offline/pos');
        await esperarMotor(page);

        const venda = await avaliar(page, async (n) => {
            const cliente = await window.SosPwa.createClientOffline({ name: n, type: 'pessoa_fisica' });
            const artigo = await window.SosPwa.db.products.toCollection().first();

            const v = await window.SosPwa.createPosSaleOffline({
                client_id: null,
                client_local_uuid: cliente.local_uuid,
                client_name: n,
                payment_method: 'cash',
                amount_received: Number(artigo.price),
                items: [{
                    product_id: artigo.id, product_name: artigo.name,
                    quantity: 1, unit_price: Number(artigo.price), tax_rate: Number(artigo.tax_rate || 0),
                }],
            });

            return { local_uuid: v.local_uuid, cliente_local_uuid: cliente.local_uuid };
        }, nomeCliente);

        await context.setOffline(false);

        await expect
            .poll(async () => {
                const v = (await lerBase(page, 'pos_sales')).find((s) => s.local_uuid === venda.local_uuid);
                if (v?._server_id) return v._server_id;
                await sincronizar(page).catch(() => {});
                return null;
            }, { message: 'a venda tem de chegar ao servidor', timeout: 60_000, intervals: [1000] })
            .not.toBeNull();

        const falhados = (await lerBase(page, 'sync_queue')).filter((j) => j.status === 'failed');
        expect(falhados.map((j) => `${j.op}: ${j.last_error}`), 'o cliente sem NIF não pode ter sido recusado').toEqual([]);

        const cliente = (await lerBase(page, 'clients')).find((c) => c.local_uuid === venda.cliente_local_uuid);
        expect(Number.isInteger(cliente?.id), 'o cliente sem NIF tem de ter id do servidor').toBe(true);

        const v = (await lerBase(page, 'pos_sales')).find((s) => s.local_uuid === venda.local_uuid);
        const preview = await previewDoServidor(page, 'FR', v._server_id);
        expect(preview.status).toBe(200);
        expect(preview.texto, 'a venda no servidor tem de ser em nome do freguês').toContain(nomeCliente);
    });
});
