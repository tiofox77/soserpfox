import { test, expect } from '@playwright/test';
import {
    entrar, esperarMotor, esperarCatalogo, irPara, avaliar, sincronizar, garantirTurno,
} from './apoio.js';

/**
 * O que o aparelho faz quando o servidor diz "esta empresa não pode emitir".
 *
 * O lado do servidor está provado em PHP (SubscricaoExpiradaFechaOPwaTest): a
 * API responde 402 com o motivo em JSON. Aqui prova-se a outra metade, que é
 * a que custava dinheiro: o MOTOR tem de reconhecer essa recusa.
 *
 * O defeito era este. A recusa chegava como um 302 para a página de renovação;
 * o `fetch` segue redireccionamentos, por isso o motor recebia HTML com estado
 * 200. Resultado: o `ping` dizia "online", o aparelho continuava a vender
 * convencido de que depois sincronizava, e a fila batia numa porta fechada até
 * gastar as cinco tentativas — altura em que as vendas eram marcadas como
 * falhadas e saíam do caminho. Vendas reais, já cobradas ao cliente, perdidas
 * por uma factura por pagar que não é do operador.
 *
 * A recusa é simulada por intercepção e não por uma subscrição mesmo expirada:
 * o que se mede aqui é a REACÇÃO do motor a uma resposta, e mexer na
 * subscrição da bancada estragava todos os outros ensaios que correm a seguir.
 */

/** Faz a API responder 402 a tudo, como um servidor de uma empresa em dívida. */
async function servidorRecusa(page) {
    await page.route('**/api/v1/invoicing/**', (rota) =>
        rota.fulfill({
            status: 402,
            contentType: 'application/json',
            body: JSON.stringify({
                success: false,
                code: 'subscription_expired',
                error: 'A subscrição desta empresa expirou.',
            }),
        })
    );
}

async function prepararAparelho(page) {
    await entrar(page);
    await irPara(page, '/invoicing/offline/pos');
    await esperarMotor(page);
    await esperarCatalogo(page);
    await sincronizar(page);
    await garantirTurno(page);
}

/** Um artigo do catálogo local que dê para vender. */
async function artigoVendavel(page) {
    return avaliar(page, async () => {
        const todos = await window.SosPwa.db.products.toArray();
        const b = todos.find((p) => (parseFloat(p.price) || 0) > 0);

        return b ? { id: b.id, name: b.name, price: parseFloat(b.price), tax_rate: parseFloat(b.tax_rate) || 0 } : null;
    });
}

/** Vende uma linha e devolve o local_uuid. */
async function vender(page, artigo, nota) {
    return avaliar(page, async ({ a, nota }) => {
        const v = await window.SosPwa.createPosSaleOffline({
            client_name: 'Consumidor Final',
            payment_method: 'cash',
            notes: nota,
            items: [{
                product_id: a.id,
                product_name: a.name,
                quantity: 1,
                unit_price: a.price,
                tax_rate: a.tax_rate,
                discount_percent: 0,
            }],
        });

        return v.local_uuid;
    }, { a: artigo, nota });
}

/**
 * O QUE ESTE FICHEIRO NÃO CONSEGUE MEDIR, e porquê.
 *
 * O `ping` — que é o que o `estadoDaLigacao()` usa para devolver
 * `'subscricao_expirada'` — passa pelo service worker, que o serve com um
 * `fetch` próprio. Um pedido que nasce DENTRO do service worker não é
 * apanhado pelo `page.route` do Playwright, por isso a intercepção aqui não
 * lhe chega e o ping responde como se nada fosse.
 *
 * É uma limitação do banco de ensaio, não do produto: o servidor a responder
 * 402 ao ping está provado em PHP (SubscricaoExpiradaFechaOPwaTest), e a
 * reacção do motor ao 402 está provada aqui pelo POST da venda, que percorre
 * exactamente o mesmo `fetchJson` e o mesmo tratamento. (O `sync` por GET
 * também passa pelo service worker; o POST não, e é o caminho que interessa.)
 */
test.describe('subscrição expirada', () => {
    test('o motor marca a recusa, e não a confunde com falta de rede', async ({ page }) => {
        await prepararAparelho(page);

        const artigo = await artigoVendavel(page);
        expect(artigo, 'não há artigo com preço no catálogo local').not.toBeNull();

        await servidorRecusa(page);
        await esperarMotor(page);

        await vender(page, artigo, 'ensaio marca a recusa');
        await sincronizar(page);

        const marcado = await avaliar(page, () => window.SosPwa.state.subscriptionExpired === true);

        // Não basta falhar: tem de ficar registado COMO subscrição expirada.
        // Uma falha genérica seria arquivada como falta de rede, e o aparelho
        // continuava a prometer ao operador que depois sincronizava.
        expect(marcado, 'a recusa por subscrição não ficou marcada no motor').toBe(true);
    });

    test('o aviso aparece e diz o que se passa', async ({ page }) => {
        await prepararAparelho(page);

        const artigo = await artigoVendavel(page);

        await servidorRecusa(page);
        await esperarMotor(page);

        await vender(page, artigo, 'ensaio aviso');
        await sincronizar(page);

        await page.waitForFunction(
            () => {
                const b = document.getElementById('pwa-status-subscricao');

                return !!b && !b.classList.contains('hidden');
            },
            null,
            { timeout: 10_000 }
        );

        const texto = await page.textContent('#pwa-status-subscricao');

        expect(texto).toContain('Subscrição expirada');
        // Diz que nada se perde. É a única coisa que o operador precisa de
        // saber no momento, e é verdade — ver o ensaio seguinte.
        expect(texto).toContain('guardadas');
    });

    test('a venda que ficou por subir NÃO se perde nem gasta tentativas', async ({ page }) => {
        await prepararAparelho(page);

        const artigo = await avaliar(page, async () => {
            const todos = await window.SosPwa.db.products.toArray();
            const b = todos.find((p) => (parseFloat(p.price) || 0) > 0);

            return b ? { id: b.id, name: b.name, price: parseFloat(b.price), tax_rate: parseFloat(b.tax_rate) || 0 } : null;
        });

        expect(artigo, 'não há artigo com preço no catálogo local').not.toBeNull();

        await servidorRecusa(page);

        const uuid = await avaliar(page, async (a) => {
            const v = await window.SosPwa.createPosSaleOffline({
                client_name: 'Consumidor Final',
                payment_method: 'cash',
                notes: 'ensaio subscricao expirada',
                items: [{
                    product_id: a.id,
                    product_name: a.name,
                    quantity: 1,
                    unit_price: a.price,
                    tax_rate: a.tax_rate,
                    discount_percent: 0,
                }],
            });

            return v.local_uuid;
        }, artigo);

        // Seis passagens: mais do que as cinco tentativas que marcavam um
        // trabalho como falhado. É exactamente o cenário que perdia as vendas.
        for (let i = 0; i < 6; i++) {
            await sincronizar(page);
        }

        const trabalho = await avaliar(page, async (u) => {
            const todos = await window.SosPwa.db.sync_queue.toArray();

            return todos.find((j) => j.payload?.local_uuid === u) ?? null;
        }, uuid);

        expect(trabalho, 'a venda desapareceu da fila').not.toBeNull();
        expect(trabalho.status, 'a venda foi descartada por causa da subscrição').toBe('pending');
        expect(trabalho.retries || 0, 'a recusa por subscrição gastou tentativas').toBe(0);

        // E a venda continua na base do aparelho, por sincronizar.
        const venda = await avaliar(page, async (u) => {
            const t = await window.SosPwa.db.pos_sales.toArray();

            return t.find((v) => v.local_uuid === u) ?? null;
        }, uuid);

        expect(venda, 'a venda desapareceu do aparelho').not.toBeNull();
        expect(venda._synced).toBe(0);
    });

    test('quando a empresa renova, a venda retida sobe', async ({ page }) => {
        await prepararAparelho(page);

        const artigo = await avaliar(page, async () => {
            const todos = await window.SosPwa.db.products.toArray();
            const b = todos.find((p) => (parseFloat(p.price) || 0) > 0);

            return b ? { id: b.id, name: b.name, price: parseFloat(b.price), tax_rate: parseFloat(b.tax_rate) || 0 } : null;
        });

        await servidorRecusa(page);

        const uuid = await avaliar(page, async (a) => {
            const v = await window.SosPwa.createPosSaleOffline({
                client_name: 'Consumidor Final',
                payment_method: 'cash',
                notes: 'ensaio renovacao',
                items: [{
                    product_id: a.id,
                    product_name: a.name,
                    quantity: 1,
                    unit_price: a.price,
                    tax_rate: a.tax_rate,
                    discount_percent: 0,
                }],
            });

            return v.local_uuid;
        }, artigo);

        await sincronizar(page);

        // A renovação: o servidor volta a aceitar.
        await page.unroute('**/api/v1/invoicing/**');

        await sincronizar(page);

        await page.waitForFunction(
            async (u) => {
                const t = await window.SosPwa.db.pos_sales.toArray();

                return t.find((v) => v.local_uuid === u)?._synced === 1;
            },
            uuid,
            { timeout: 60_000 }
        );

        const venda = await avaliar(page, async (u) => {
            const t = await window.SosPwa.db.pos_sales.toArray();

            return t.find((v) => v.local_uuid === u) ?? null;
        }, uuid);

        expect(venda._server_number, 'a venda retida não chegou a ser emitida depois da renovação').toBeTruthy();
        expect(venda._server_hash, 'a venda subiu sem assinatura do servidor').toBeTruthy();
    });
});
