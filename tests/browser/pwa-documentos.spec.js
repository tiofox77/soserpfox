import { test, expect } from '@playwright/test';
import { entrar, esperarServiceWorker, esperarMotor, esperarCatalogo, lerBase, sincronizar, irPara, avaliar } from './apoio.js';

/**
 * A área de Documentos do PWA: fatura, fatura-recibo e proforma.
 *
 * São documentos FISCAIS. Um documento que se perde entre o telemóvel e o
 * servidor não é um ecrã feio — é dinheiro que não foi cobrado e uma
 * numeração que fica com um buraco. Por isso os ensaios seguem cada tipo do
 * princípio ao fim: criado sem rede, guardado, sincronizado, e existente no
 * servidor com número atribuído.
 *
 * NOTA SOBRE COMPRAS: o PWA não faz documentos de compra. Só de venda. Ver
 * o ensaio no fim, que fixa isso de propósito para não passar por esquecimento.
 */

async function aparelhoPreparado(page) {
    await entrar(page);
    await irPara(page, '/invoicing/offline');
    await esperarServiceWorker(page);
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 5);
}

/** Cria um documento pelo mesmo caminho que o ecrã usa. */
async function criarDocumento(page, tipo) {
    return avaliar(page, async (docType) => {
        const artigo = await window.SosPwa.db.products.toCollection().first();
        const cliente = await window.SosPwa.db.clients.toCollection().first();

        await window.SosPwa.createDraftOffline({
            doc_type: docType,
            client_id: cliente?.id ?? null,
            invoice_date: new Date().toISOString().slice(0, 10),
            due_date: new Date(Date.now() + 15 * 86400000).toISOString().slice(0, 10),
            notes: 'Criado pela bancada',
            items: [{
                product_id: artigo.id,
                product_name: artigo.name,
                quantity: 2,
                unit_price: artigo.price,
                tax_rate: 14,
                discount_percent: 0,
            }],
        });

        const doc = await window.SosPwa.db.draft_documents
            .orderBy('created_at').reverse().first();

        return { local_uuid: doc.local_uuid, doc_type: doc.doc_type, total: doc.total };
    }, tipo);
}

test.describe('PWA — Documentos', () => {
    /**
     * Cada tipo, do princípio ao fim: criado sem rede, sincronizado, e com
     * número do servidor. É o ciclo que interessa a quem vende.
     */
    for (const [tipo, nome] of [['FT', 'Fatura'], ['FR', 'Fatura-Recibo'], ['proforma', 'Proforma']]) {
        test(`${nome}: criada sem rede, sobe e recebe número`, async ({ page, context }) => {
            await aparelhoPreparado(page);

            await context.setOffline(true);
            await irPara(page, '/invoicing/offline/drafts');
            await esperarMotor(page);

            const doc = await criarDocumento(page, tipo);

            expect(doc.doc_type, 'o tipo tem de ser guardado tal como foi escolhido').toBe(tipo);
            expect(doc.total, 'o total tem de ser calculado localmente, para se ver na lista')
                .toBeGreaterThan(0);

            // Sem rede, fica à espera — e identificado.
            const naFila = (await lerBase(page, 'sync_queue'))
                .find((j) => j.payload?.local_uuid === doc.local_uuid);

            expect(naFila, 'o documento tem de entrar na fila').toBeTruthy();
            expect(naFila.op).toBe('create_draft');
            expect(naFila.tenant_id, 'sem carimbo pode ir para outra empresa').toBeTruthy();

            // A rede volta.
            await context.setOffline(false);

            // O servidor devolveu um id: o documento existe lá.
            //
            // A espera volta a pedir sincronização a cada volta, em vez de
            // sincronizar uma vez e ficar à espera. Uma sincronização que
            // apanhe a fila a meio devolve sem levar este trabalho, e o ensaio
            // ficava 45 segundos à espera de uma coisa que já ninguém ia
            // buscar — passava umas vezes e falhava outras, que é o pior que um
            // ensaio pode fazer.
            await expect
                .poll(
                    async () => {
                        const guardados = await lerBase(page, 'draft_documents');
                        const meu = guardados.find((d) => d.local_uuid === doc.local_uuid);

                        if (meu?._server_id) {
                            return meu._server_id;
                        }

                        await sincronizar(page).catch(() => {});

                        return null;
                    },
                    { message: `${nome} tem de existir no servidor`, timeout: 60_000, intervals: [1000] }
                )
                .not.toBeNull();
        });
    }

    /**
     * O total local tem de bater com a conta à mão.
     *
     * A lista offline mostra este número ao vendedor, e ele diz o valor ao
     * cliente antes de haver rede. Se estiver errado, foi dito um preço errado
     * a alguém.
     */
    test('o total calculado sem rede está certo', async ({ page, context }) => {
        await aparelhoPreparado(page);
        await context.setOffline(true);
        await irPara(page, '/invoicing/offline/drafts');
        await esperarMotor(page);

        const conta = await avaliar(page, async () => {
            const artigo = await window.SosPwa.db.products.toCollection().first();

            await window.SosPwa.createDraftOffline({
                doc_type: 'FT',
                invoice_date: new Date().toISOString().slice(0, 10),
                items: [{
                    product_id: artigo.id,
                    product_name: artigo.name,
                    quantity: 3,
                    unit_price: 1000,
                    tax_rate: 14,
                    discount_percent: 10,
                }],
            });

            const doc = await window.SosPwa.db.draft_documents.orderBy('created_at').reverse().first();

            return { subtotal: doc.subtotal, tax: doc.tax_amount ?? doc.tax, total: doc.total };
        });

        // 3 × 1000 = 3000; menos 10% = 2700; IVA 14% = 378; total 3078.
        expect(conta.subtotal).toBeCloseTo(2700, 2);
        expect(conta.total).toBeCloseTo(3078, 2);
    });

    /** Os documentos ficam visíveis na lista, sem rede. */
    test('a lista mostra os documentos sem rede', async ({ page, context }) => {
        await aparelhoPreparado(page);
        await context.setOffline(true);
        await irPara(page, '/invoicing/offline/drafts');
        await esperarMotor(page);

        await criarDocumento(page, 'FT');

        const guardados = await lerBase(page, 'draft_documents');
        expect(guardados.length, 'a lista lê da base local').toBeGreaterThan(0);

        await page.reload();
        await esperarMotor(page);

        const depois = await lerBase(page, 'draft_documents');
        expect(depois.length, 'recarregar sem rede não pode perder documentos')
            .toBe(guardados.length);
    });

    /**
     * A NOTA DE CRÉDITO NÃO SE FAZ AQUI — e isso é deliberado.
     *
     * O servidor escrevia-a na tabela das VENDAS: nascia uma factura que não
     * estornava nada e contava como receita. Saiu do formulário. Este ensaio
     * existe para que ninguém a volte a pôr sem perceber porquê saiu.
     */
    test('a nota de crédito não é oferecida no formulário', async ({ page }) => {
        await aparelhoPreparado(page);
        await irPara(page, '/invoicing/offline/drafts/new');

        await page.locator('[data-ensaio="tipo-documento"]').first().waitFor({ timeout: 15_000 });

        const tipos = await avaliar(page, () =>
            [...document.querySelectorAll('[data-ensaio="tipo-documento"]')].map((b) => b.dataset.tipo));

        expect(tipos, 'os três tipos de venda têm de estar lá').toEqual(
            expect.arrayContaining(['FT', 'FR', 'proforma'])
        );
        expect(tipos, 'a NC não pode voltar sem se perceber porque saiu').not.toContain('NC');
    });

    /**
     * O SERVIDOR RECUSA O QUE NÃO É DOCUMENTO DE VENDA.
     *
     * O PWA só faz vendas. Se um cliente adulterado (ou uma versão antiga)
     * mandar outro tipo, tem de levar recusa — e não criar um documento
     * estranho na base.
     */
    test('o servidor recusa tipos que não são de venda', async ({ page }) => {
        await aparelhoPreparado(page);

        const resposta = await avaliar(page, async () => {
            const r = await fetch('/api/v1/invoicing/drafts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    local_uuid: 'compra-' + Date.now(),
                    doc_type: 'FC',            // factura de COMPRA
                    invoice_date: new Date().toISOString().slice(0, 10),
                    items: [],
                }),
            });

            return r.status;
        });

        expect(resposta, 'um tipo fora de FT/FR/proforma tem de ser recusado').toBe(422);
    });
});
