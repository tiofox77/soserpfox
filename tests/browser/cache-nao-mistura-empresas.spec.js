import { test, expect } from '@playwright/test';
import { entrar, esperarServiceWorker, irPara } from './apoio.js';

/**
 * O cache do service worker não pode guardar páginas da retaguarda.
 *
 * A QUEIXA: «estou na Farmácia Luk Simões, troco para a Neves Bendinha, vou a
 * Produtos e ainda aparecem coisas da Luk Simões; tenho de passar lá duas
 * vezes». A causa não era a troca de empresa, que está bem feita e faz um
 * redireccionamento do servidor. Era o service worker.
 *
 * A barra lateral usa `wire:navigate`, e o Livewire vai buscar a página com um
 * `fetch` que só leva o cabeçalho `X-Livewire-Navigate` — sem `Accept`. A
 * pergunta «tem text/html?» dava NÃO, a página escapava a todas as regras e
 * caía no stale-while-revalidate: a cópia velha era servida primeiro e a nova
 * só chegava por trás. Como o cache guarda por endereço, e o endereço de
 * Produtos é o mesmo nas duas empresas, via-se a casa errada.
 */
test.describe('O cache das páginas não mistura empresas', () => {
    // Produtos foi o exemplo da queixa, mas o problema era de TODAS as páginas:
    // «mesmo em facturas tinha de actualizar 2 ou 3 vezes».
    const PAGINAS = [
        '/invoicing/products',
        '/invoicing/sales/invoices',
        '/invoicing/sales/proformas',
        '/invoicing/clients',
        '/invoicing/pos/reports',
    ];

    test('nenhuma página da retaguarda fica guardada, seja qual for', async ({ page }) => {
        await entrar(page);
        await irPara(page, '/invoicing/offline/drafts');
        await esperarServiceWorker(page);

        await page.goto('/home');
        await page.waitForTimeout(1000);

        // Os dois caminhos por onde uma página chega: a navegação normal e um
        // pedido sem `Accept` de HTML (era o do `wire:navigate`; o Livewire
        // saiu, mas qualquer `fetch` de uma página faz o mesmo).
        for (const caminho of PAGINAS) {
            await irPara(page, caminho);
            await page.evaluate((p) => fetch(p, { credentials: 'same-origin', headers: { 'X-Livewire-Navigate': '1' } }).then((r) => r.text()), caminho);
            await page.waitForTimeout(800);
        }

        const guardadas = await page.evaluate(async () => {
            const cache = await caches.open('dynamic-paginas');

            return (await cache.keys()).map((r) => new URL(r.url).pathname);
        });

        const retaguarda = guardadas.filter((p) => !p.startsWith('/invoicing/offline'));
        expect(retaguarda, 'nenhuma página da retaguarda pode ficar no cache').toEqual([]);

        // O PWA continua guardado. Não se exige uma página em concreto: quais
        // ficam depende de quando a instalação acabou.
        expect(guardadas.some((p) => p.startsWith('/invoicing/offline')), 'o PWA continua no cache').toBe(true);
    });

    test('uma página da retaguarda pedida por fetch não fica guardada', async ({ page }) => {
        await entrar(page);
        await irPara(page, '/invoicing/offline/drafts');
        await esperarServiceWorker(page);

        await page.goto('/home');
        await page.waitForTimeout(1000);

        // Um pedido de página feito por `fetch`, sem Accept de HTML — é a raiz do problema.
        const pedidos = [];
        page.on('request', (r) => {
            if (r.url().includes('/invoicing/products')) {
                pedidos.push(r.headers()['accept'] || null);
            }
        });

        await page.evaluate(() => fetch('/invoicing/products', { credentials: 'same-origin', headers: { 'X-Livewire-Navigate': '1' } }).then((r) => r.text()));
        await page.waitForTimeout(1500);

        expect(pedidos.length, 'o navigate foi mesmo buscar a página').toBeGreaterThan(0);

        const guardadas = await page.evaluate(async () => {
            const cache = await caches.open('dynamic-paginas');
            const chaves = await cache.keys();

            return chaves.map((r) => new URL(r.url).pathname);
        });

        // A retaguarda NUNCA entra: é conteúdo de uma empresa.
        const retaguarda = guardadas.filter((p) => !p.startsWith('/invoicing/offline'));
        expect(retaguarda, 'nenhuma página da retaguarda pode ficar no cache').toEqual([]);

        // O PWA continua guardado: é o que o sustenta sem rede.
        expect(guardadas).toContain('/invoicing/offline/pos');
        expect(guardadas).toContain('/invoicing/offline/drafts');
    });

    test('a página do PWA continua a ser servida sem rede', async ({ page, context }) => {
        await entrar(page);
        await irPara(page, '/invoicing/offline/pos');
        await esperarServiceWorker(page);
        await page.waitForTimeout(1500);

        await context.setOffline(true);

        try {
            await page.goto('/invoicing/offline/pos');
            const corpo = await page.evaluate(() => document.body.innerText.length);

            // Sem rede, o POS abre à mesma. Se isto partir, o PWA morreu.
            expect(corpo).toBeGreaterThan(50);
        } finally {
            await context.setOffline(false);
        }
    });
});
