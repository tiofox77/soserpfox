import { test, expect } from '@playwright/test';
import { entrar, esperarServiceWorker, esperarMotor, esperarCatalogo, contar, lerBase, sincronizar } from './apoio.js';

/**
 * O PWA sem rede.
 *
 * `context.setOffline(true)` corta a rede ao nível do browser: os pedidos
 * falham como falham no telemóvel de um vendedor sem cobertura. Não é o
 * mesmo que mexer no `navigator.onLine`, que só engana o código que o
 * consulta e deixa os `fetch` a passar — e é aí que os PWA costumam parecer
 * funcionar em ensaio e falhar na rua.
 *
 * Todos os ensaios preparam o aparelho COM rede primeiro. É o que um vendedor
 * faz: sincroniza na loja e vai para a rua. Cortar a rede num aparelho que
 * nunca sincronizou não mede o produto, mede o óbvio.
 */

/** Prepara o aparelho como se tivesse acabado de sincronizar na loja. */
async function aparelhoPreparado(page) {
    await entrar(page);
    await page.goto('/invoicing/offline');
    await esperarServiceWorker(page);
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 5);
}

test.describe('PWA — sem rede', () => {
    /**
     * O ENSAIO QUE IMPORTA. Se isto falhar, nada do resto interessa: o
     * vendedor abre a aplicação e não tem POS.
     */
    test('o POS abre sem rede', async ({ page, context }) => {
        await aparelhoPreparado(page);

        await context.setOffline(true);

        const resposta = await page.goto('/invoicing/offline/pos');

        // Não pode ser a página de "sem ligação": tem de ser o POS.
        expect(resposta, 'a navegação tem de devolver alguma coisa').not.toBeNull();
        await expect(page.locator('body')).not.toContainText('Sem Conexão à Internet');

        // E o motor tem de estar vivo, não só o HTML.
        await esperarMotor(page);
        expect(await contar(page, 'products')).toBeGreaterThanOrEqual(5);
    });

    test('o catálogo continua legível sem rede', async ({ page, context }) => {
        await aparelhoPreparado(page);
        await context.setOffline(true);

        await page.goto('/invoicing/offline/catalog');
        await esperarMotor(page);

        const artigos = await lerBase(page, 'products');
        expect(artigos.length).toBeGreaterThanOrEqual(5);
        expect(artigos.map((a) => a.name)).toContain('Água 1,5L');
    });

    test('o aparelho sabe que está sem rede', async ({ page, context }) => {
        await aparelhoPreparado(page);
        await context.setOffline(true);

        // O motor não confia só no navigator.onLine — confirma por ping.
        await expect
            .poll(
                () => page.evaluate(() => window.SosPwa.state.realOnline).catch(() => true),
                { message: 'o motor tem de reconhecer que não há rede', timeout: 30_000 }
            )
            .toBe(false);
    });

    /**
     * A venda feita sem rede tem de ficar na fila, com identificador próprio
     * e carimbo da empresa. Sem identificador, cada reenvio cria um documento
     * novo; sem carimbo, pode ir parar aos livros de outra empresa.
     */
    test('uma venda feita sem rede fica na fila, identificada e carimbada', async ({ page, context }) => {
        await aparelhoPreparado(page);

        const empresa = await page.evaluate(async () => (await window.SosPwa.db.meta.get('tenant_id'))?.value);
        const antes = await contar(page, 'sync_queue');

        await context.setOffline(true);
        await page.goto('/invoicing/offline/pos');
        await esperarMotor(page);

        // Enfileira uma venda pelo motor, que é o caminho que o ecrã usa.
        await page.evaluate(async () => {
            const artigo = await window.SosPwa.db.products.toCollection().first();

            await window.SosPwa.enqueue('create_pos_sale', {
                local_uuid: 'ensaio-' + Date.now(),
                client_id: null,
                payment_method: 'cash',
                amount_received: 1000,
                items: [{
                    product_id: artigo.id,
                    product_name: artigo.name,
                    quantity: 1,
                    unit_price: artigo.price,
                }],
            }, false);
        });

        const fila = await lerBase(page, 'sync_queue');
        expect(fila.length, 'a venda tem de entrar na fila').toBe(antes + 1);

        const trabalho = fila[fila.length - 1];
        expect(trabalho.op).toBe('create_pos_sale');
        expect(trabalho.status).toBe('pending');
        expect(trabalho.payload.local_uuid, 'sem identificador local, cada reenvio duplica').toBeTruthy();
        expect(trabalho.tenant_id, 'sem carimbo, pode ir para os livros de outra empresa').toBe(empresa);
    });

    /** Recarregar sem rede não pode perder o que está por enviar. */
    test('a fila sobrevive a recarregar sem rede', async ({ page, context }) => {
        await aparelhoPreparado(page);
        await context.setOffline(true);
        await page.goto('/invoicing/offline/pos');
        await esperarMotor(page);

        await page.evaluate(() => window.SosPwa.enqueue('create_pos_sale', {
            local_uuid: 'sobrevive-' + Date.now(),
            payment_method: 'cash',
            items: [],
        }, false));

        const antes = await contar(page, 'sync_queue');

        await page.reload();
        await esperarMotor(page);

        expect(await contar(page, 'sync_queue'), 'a fila não pode desaparecer num recarregar').toBe(antes);
    });
});
