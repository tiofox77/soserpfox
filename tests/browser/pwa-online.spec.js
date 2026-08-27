import { test, expect } from '@playwright/test';
import { entrar, esperarServiceWorker, esperarMotor, esperarCatalogo, contar, empresaLocal, sincronizar, irPara, avaliar } from './apoio.js';

/**
 * O que tem de estar de pé COM rede, para que o offline seja sequer possível.
 *
 * Um PWA que não guarda nada enquanto tem rede não tem nada para mostrar
 * quando a perde. Estes ensaios medem a preparação; os de offline medem o
 * resultado.
 */
test.describe('PWA — com rede', () => {
    test('a aplicação abre e o service worker assume o comando', async ({ page }) => {
        await entrar(page);
        await irPara(page, '/invoicing/offline');

        await esperarServiceWorker(page);

        // expect.poll e não evaluate: o PWA recarrega-se sozinho depois do
        // primeiro sync (o padrão `pwa:synced`), e um evaluate apanhado a meio
        // dessa navegação morre com "Execution context was destroyed" — uma
        // falha do ensaio, não do produto.
        await expect
            .poll(
                () => avaliar(page, () => navigator.serviceWorker.controller !== null).catch(() => false),
                { message: 'o service worker tem de controlar a página', timeout: 30_000 }
            )
            .toBe(true);
    });

    test('o motor offline arranca e abre a base local', async ({ page }) => {
        await entrar(page);
        await irPara(page, '/invoicing/offline');
        await esperarMotor(page);

        const aberta = await avaliar(page, () => window.SosPwa.db.isOpen());
        expect(aberta).toBe(true);
    });

    /**
     * O DEFEITO QUE ISTO APANHA: o precache do service worker apontava para
     * CDN que a aplicação já não pede. Sem estes ficheiros em cache não há
     * offline nenhum — sem Dexie o motor nem arranca.
     */
    test('o motor e o desenho ficam guardados em cache', async ({ page }) => {
        await entrar(page);
        await irPara(page, '/invoicing/offline');
        await esperarServiceWorker(page);

        const guardados = await avaliar(page, async () => {
            const nomes = await caches.keys();
            const urls = [];

            for (const nome of nomes) {
                const c = await caches.open(nome);
                for (const req of await c.keys()) {
                    urls.push(new URL(req.url).pathname);
                }
            }

            return urls;
        });

        for (const essencial of [
            '/vendor/js/dexie.min.js',
            '/vendor/js/alpine.min.js',
            '/vendor/js/tailwind.js',
            '/js/pwa-invoicing.js',
        ]) {
            expect(guardados, `${essencial} tem de estar em cache antes de faltar a rede`)
                .toContain(essencial);
        }
    });

    test('o catálogo e os clientes descem para a base local', async ({ page }) => {
        await entrar(page);
        await irPara(page, '/invoicing/offline');
        await esperarMotor(page);
        await sincronizar(page);
        await esperarCatalogo(page, 5);

        expect(await contar(page, 'products')).toBeGreaterThanOrEqual(5);
        expect(await contar(page, 'clients')).toBeGreaterThanOrEqual(1);
        expect(await empresaLocal(page)).not.toBeNull();
    });

    test('as páginas do PWA respondem todas', async ({ page }) => {
        await entrar(page);

        for (const rota of [
            '/invoicing/offline',
            '/invoicing/offline/pos',
            '/invoicing/offline/catalog',
            '/invoicing/offline/clients',
            '/invoicing/offline/drafts',
        ]) {
            await irPara(page, rota);

            // O que interessa é a PÁGINA ter aparecido, não o código HTTP:
            // com o service worker no comando, a resposta pode vir da cache e
            // o objecto de navegação vir a nulo sem nada de errado. Um `goto`
            // que devolva 200 e mostre o ecrã de "sem ligação" também passaria
            // por um teste de código de estado — e não devia.
            await expect(page.locator('body'), `${rota} tem de abrir`)
                .not.toContainText('Sem Conexão à Internet');

            expect(page.url(), `${rota} não pode desviar para outro lado`).toContain(rota);
        }
    });
});
