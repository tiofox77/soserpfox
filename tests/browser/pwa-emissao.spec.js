import { test, expect } from '@playwright/test';
import {
    entrar, esperarServiceWorker, esperarMotor, esperarCatalogo,
    irPara, avaliar, sincronizar, garantirTurno, lerBase,
} from './apoio.js';

/**
 * A emissão de documentos com rede e sem rede, e o que o servidor faz com ela.
 *
 * É o ensaio que fecha o círculo do PWA. Tudo o resto mede se a aplicação
 * ABRE sem rede; isto mede se o que ela produz sem rede vale alguma coisa
 * quando chega ao servidor — que é a única pergunta que interessa a quem
 * vende.
 *
 * Três coisas têm de ser verdade ao mesmo tempo, e falham por razões
 * diferentes:
 *
 *   · A VENDA NÃO SE PERDE. Sai da caixa sem rede, fica na fila, e sobe
 *     inteira quando houver rede. Uma venda que desaparece numa fila com um
 *     erro que ninguém lê é o defeito mais caro que este produto pode ter.
 *   · O SERVIDOR É QUEM ASSINA. O aparelho não inventa números fiscais nem
 *     hashes: emite com um número provisório (PEND-…) e o servidor devolve o
 *     definitivo, assinado e encadeado. Um documento que voltasse do sync sem
 *     assinatura seria um documento que a AGT não aceita.
 *   · O CLIENTE NOVO CHEGA LÁ. Criado sem rede, com um id local, e reconhecido
 *     como o mesmo cliente depois de subir — sem duplicar.
 *
 * O que NÃO se prova aqui: a cadeia de hash em si. Essa mede-se do lado do
 * servidor, onde vive — ver `agt:verificar-cadeia` e o
 * CadeiaDeAssinaturasTest. Aqui só se confirma que o documento voltou
 * assinado.
 */

/** Um carimbo que sobrevive ao sync e permite reencontrar o que este ensaio criou. */
const CARIMBO = `ensaio-emissao-${Date.now()}`;

/** Prepara o aparelho: entrar, service worker no comando, catálogo em casa, turno aberto. */
async function prepararAparelho(page) {
    await entrar(page);
    await irPara(page, '/invoicing/offline/pos');
    await esperarServiceWorker(page);
    await esperarMotor(page);
    await esperarCatalogo(page);
    await sincronizar(page);
    await garantirTurno(page);
}

/** Um artigo do catálogo local que dê para vender. */
async function artigoVendavel(page) {
    return avaliar(page, async () => {
        const todos = await window.SosPwa.db.products.toArray();
        const bom = todos.find((p) => (parseFloat(p.price) || 0) > 0);

        return bom ? { id: bom.id, name: bom.name, price: parseFloat(bom.price), tax_rate: parseFloat(bom.tax_rate) || 0 } : null;
    });
}

/** Vende uma linha e devolve o local_uuid da venda. */
async function vender(page, artigo, quantidade, nota) {
    return avaliar(page, async ({ artigo, quantidade, nota }) => {
        const venda = await window.SosPwa.createPosSaleOffline({
            client_name: 'Consumidor Final',
            payment_method: 'cash',
            notes: nota,
            // A MESMA FORMA QUE O ECRÃ DO POS PÕE NO CARRINHO.
            // O servidor exige `product_name`; um ensaio que mandasse `name`
            // media um caminho que o produto nunca percorre.
            items: [{
                product_id: artigo.id,
                product_name: artigo.name,
                quantity: quantidade,
                unit_price: artigo.price,
                tax_rate: artigo.tax_rate,
                discount_percent: 0,
            }],
        });

        return venda.local_uuid;
    }, { artigo, quantidade, nota });
}

/** O registo de uma venda tal como está agora na base do aparelho. */
async function lerVenda(page, uuid) {
    return avaliar(page, async (u) => {
        const todas = await window.SosPwa.db.pos_sales.toArray();

        return todas.find((v) => v.local_uuid === u) ?? null;
    }, uuid);
}

test.describe('emissão de documentos, com rede e sem rede', () => {
    /**
     * O DESCONTO EM PERCENTAGEM TEM DE CHEGAR À FACTURA COMO PERCENTAGEM.
     *
     * O POS manda o desconto em % (é o que o operador escreve) e o talão do
     * aparelho faz as contas assim; o servidor tratava-o como Kz — 10% de
     * desconto saíam 10 Kz na factura. Depois de subir, o aparelho substitui os
     * seus totais pelos do servidor: se os dois não baterem, o talão
     * reimpresso muda de valor nas mãos do cliente.
     */
    test('uma venda com 10% de desconto feita sem rede sobe com o mesmo total', async ({ page, context }) => {
        await prepararAparelho(page);

        const artigo = await artigoVendavel(page);

        await context.setOffline(true);
        const noAparelho = await avaliar(page, async (a) => {
            const v = await window.SosPwa.createPosSaleOffline({
                client_name: 'Consumidor Final',
                payment_method: 'cash',
                discount_commercial: 10,
                notes: 'ensaio desconto 10%',
                items: [{ product_id: a.id, product_name: a.name, quantity: 4, unit_price: a.price, tax_rate: a.tax_rate }],
            });

            return { uuid: v.local_uuid, total: v.total, desconto: v.discount_amount };
        }, artigo);
        await context.setOffline(false);

        expect(noAparelho.desconto, 'o aparelho aplica 10% sobre o líquido').toBeCloseTo(artigo.price * 4 * 0.1, 2);

        await sincronizar(page);
        await page.waitForFunction(
            async (u) => (await window.SosPwa.db.pos_sales.toArray()).find((v) => v.local_uuid === u)?._synced === 1,
            noAparelho.uuid,
            { timeout: 60_000 },
        );

        const doServidor = await lerVenda(page, noAparelho.uuid);

        expect(doServidor.discount_amount, 'o servidor tem de dar o mesmo desconto').toBeCloseTo(noAparelho.desconto, 1);
        expect(doServidor.total, 'o total emitido tem de ser o do talão').toBeCloseTo(noAparelho.total, 1);
    });

    test('com rede: a venda sobe e volta com número e assinatura do servidor', async ({ page }) => {
        await prepararAparelho(page);

        const artigo = await artigoVendavel(page);
        expect(artigo, 'não há artigo com preço no catálogo local').not.toBeNull();

        const uuid = await vender(page, artigo, 1, `${CARIMBO}-online`);
        await sincronizar(page);

        const venda = await lerVenda(page, uuid);

        expect(venda, 'a venda desapareceu da base do aparelho').not.toBeNull();
        expect(venda._synced, 'a venda ficou por sincronizar mesmo com rede').toBe(1);

        // O NÚMERO É DO SERVIDOR. O aparelho emite PEND-…; se ficasse assim,
        // a venda não tinha existência fiscal.
        expect(venda._server_number, 'o servidor não devolveu número de factura').toBeTruthy();
        expect(venda._server_number).not.toMatch(/^PEND-/);

        // E vem assinada. É o que distingue um documento fiscal de um papel
        // com um total escrito.
        expect(venda._server_hash, 'o documento voltou sem assinatura').toBeTruthy();
    });

    test('sem rede: a venda fica na fila e nada se perde', async ({ page, context }) => {
        await prepararAparelho(page);

        const artigo = await artigoVendavel(page);
        const filaAntes = await avaliar(page, () => window.SosPwa.db.sync_queue.where('status').equals('pending').count());

        await context.setOffline(true);

        const uuid = await vender(page, artigo, 2, `${CARIMBO}-offline`);

        const venda = await lerVenda(page, uuid);

        expect(venda, 'a venda não chegou a ser gravada sem rede').not.toBeNull();
        expect(venda._synced, 'uma venda sem rede não pode nascer sincronizada').toBe(0);

        // O número provisório é legível de propósito: é o que o cliente leva no
        // talão enquanto o definitivo não existe.
        expect(venda.provisional_number).toMatch(/^PEND-/);

        const filaDepois = await avaliar(page, () => window.SosPwa.db.sync_queue.where('status').equals('pending').count());
        expect(filaDepois, 'a venda não entrou na fila').toBe(filaAntes + 1);

        await context.setOffline(false);
    });

    test('a venda feita sem rede sobe assinada quando a rede volta', async ({ page, context }) => {
        await prepararAparelho(page);

        const artigo = await artigoVendavel(page);

        await context.setOffline(true);
        const uuid = await vender(page, artigo, 3, `${CARIMBO}-reposta`);
        await context.setOffline(false);

        await sincronizar(page);

        await page.waitForFunction(
            async (u) => {
                const todas = await window.SosPwa.db.pos_sales.toArray();

                return todas.find((v) => v.local_uuid === u)?._synced === 1;
            },
            uuid,
            { timeout: 60_000 }
        );

        const venda = await lerVenda(page, uuid);

        expect(venda._server_number, 'a venda subiu mas sem número definitivo').toBeTruthy();
        expect(venda._server_number).not.toMatch(/^PEND-/);
        expect(venda._server_hash, 'a venda reposta voltou sem assinatura do servidor').toBeTruthy();
    });

    test('sem série registada na AGT o documento sai SEM ATCUD, e não com um inventado', async ({ page }) => {
        await prepararAparelho(page);

        const artigo = await artigoVendavel(page);
        const uuid = await vender(page, artigo, 1, `${CARIMBO}-atcud`);

        await sincronizar(page);

        const venda = await lerVenda(page, uuid);

        // O ATCUD é <código de validação da AGT>-<sequencial>. A bancada nunca
        // registou a série, por isso não há código — e um documento sem ATCUD
        // é a resposta CERTA.
        //
        // O que não pode acontecer é sair um `0-45`: um identificador fiscal
        // falso, impresso e entregue ao cliente, que parece válido e não é. Um
        // campo vazio nota-se; um zero passa despercebido durante meses.
        expect(venda._server_atcud || '', 'saiu um ATCUD inventado numa série por registar')
            .not.toMatch(/^0-/);
    });

    test('sincronizar duas vezes não emite o documento duas vezes', async ({ page, context }) => {
        await prepararAparelho(page);

        const artigo = await artigoVendavel(page);

        await context.setOffline(true);
        const uuid = await vender(page, artigo, 1, `${CARIMBO}-idempotente`);
        await context.setOffline(false);

        await sincronizar(page);
        const primeira = await lerVenda(page, uuid);

        // A segunda passagem é o ensaio: uma rede que oscila faz isto sozinha.
        await sincronizar(page);
        const segunda = await lerVenda(page, uuid);

        expect(segunda._server_number, 'o mesmo local_uuid gerou um segundo número')
            .toBe(primeira._server_number);

        const comEsteUuid = await avaliar(page, async (u) => {
            const todas = await window.SosPwa.db.pos_sales.toArray();

            return todas.filter((v) => v.local_uuid === u).length;
        }, uuid);

        expect(comEsteUuid, 'a venda duplicou na base do aparelho').toBe(1);
    });

    test('cliente criado sem rede sobe e passa a ter id do servidor', async ({ page, context }) => {
        await prepararAparelho(page);

        const nome = `Cliente ${CARIMBO}`;

        await context.setOffline(true);

        const local = await avaliar(page, async (n) => {
            const c = await window.SosPwa.createClientOffline({
                name: n,
                nif: '999999999',
                phone: '900000000',
            });

            return { local_uuid: c.local_uuid, id: c.id };
        }, nome);

        // Sem rede, o cliente existe mas com um id local — de propósito: um id
        // inventado que colidisse com um do servidor era pior do que não ter.
        expect(String(local.id)).toMatch(/^local_/);

        await context.setOffline(false);
        await sincronizar(page);

        await page.waitForFunction(
            async (u) => {
                const todos = await window.SosPwa.db.clients.toArray();
                const c = todos.find((x) => x.local_uuid === u);

                return !!c && c._synced === 1;
            },
            local.local_uuid,
            { timeout: 60_000 }
        );

        const clientes = await lerBase(page, 'clients');
        const meus = clientes.filter((c) => c.name === nome);

        expect(meus.length, 'o cliente duplicou ao sincronizar').toBe(1);
        expect(meus[0]._synced).toBe(1);
        expect(String(meus[0].id), 'o cliente ficou com o id local depois de subir').not.toMatch(/^local_/);
        expect(Number(meus[0].id), 'o servidor não devolveu um id numérico').toBeGreaterThan(0);
    });
});
