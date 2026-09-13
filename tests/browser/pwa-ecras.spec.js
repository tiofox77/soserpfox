import { test, expect } from '@playwright/test';
import { entrar, esperarMotor, esperarCatalogo, sincronizar, irPara, avaliar } from './apoio.js';

/**
 * OS ONZE ECRÃS DO PWA DESENHAM-SE — com rede e sem ela.
 *
 * O PWA passou de Blade+Alpine para React (resources/js/pwa). A casca do
 * servidor é a mesma para todos e o ecrã sai do ENDEREÇO. Estes ensaios provam
 * o que se perdia sem se ver: o pacote montou (o ecrã de carregamento saiu),
 * nenhum ecrã rebentou (o limite de erro não aparece), a consola não tem erros
 * de JavaScript, e sem rede cada endereço desenha o SEU ecrã — e não o que
 * calhou ficar guardado.
 *
 * Cada ecrã deixa uma fotografia em test-results/pwa-ecras/ para quem revê.
 */

const ECRAS = [
    { rota: '/invoicing/offline', nome: 'inicio', ve: /Ferramentas/ },
    { rota: '/invoicing/offline/catalog', nome: 'catalogo', ve: /Catálogo/ },
    { rota: '/invoicing/offline/clients', nome: 'clientes', ve: /Clientes/ },
    { rota: '/invoicing/offline/clients/new', nome: 'novo-cliente', ve: /Guardar Cliente/ },
    { rota: '/invoicing/offline/drafts', nome: 'documentos', ve: /Documentos/ },
    { rota: '/invoicing/offline/drafts/new', nome: 'novo-documento', ve: /Emitir Documento/ },
    { rota: '/invoicing/offline/pos', nome: 'pos', ve: /produtos/ },
    { rota: '/invoicing/offline/restaurant', nome: 'restaurante', ve: /Mesas|Restaurante|sala|módulo/i },
];

async function montado(page) {
    await page.waitForFunction(() => window.__pwaMontado === true && !document.getElementById('pwa-a-carregar'), null, { timeout: 30_000 });
}

test.describe('PWA — os ecrãs em React', () => {
    test('com rede, cada ecrã monta sem erros', async ({ page }) => {
        const erros = [];
        page.on('pageerror', (e) => erros.push(e.message));

        await entrar(page);
        await irPara(page, '/invoicing/offline');
        await esperarMotor(page);
        await sincronizar(page);
        await esperarCatalogo(page, 5);

        for (const e of ECRAS) {
            await irPara(page, e.rota);
            await montado(page);
            await esperarMotor(page);

            await expect(page.locator('#pwa-raiz'), `${e.nome}: o ecrã tem de aparecer`).toContainText(e.ve, { timeout: 15_000 });
            await expect(page.getByText('Este ecrã encontrou um erro.'), `${e.nome}: não pode rebentar`).toHaveCount(0);

            await page.screenshot({ path: `test-results/pwa-ecras/${e.nome}.png`, fullPage: true });
        }

        expect(erros, 'nenhum erro de JavaScript').toEqual([]);
    });

    test('as páginas públicas montam sem sessão', async ({ page }) => {
        for (const [rota, ve, nome] of [
            ['/invoicing/offline/login', /Entrar|PIN/, 'entrada'],
            ['/invoicing/offline/pin-esquecido', /Esqueci o PIN/, 'pin-esquecido'],
        ]) {
            await irPara(page, rota);
            await montado(page);
            await expect(page.locator('#pwa-raiz')).toContainText(ve);
            await page.screenshot({ path: `test-results/pwa-ecras/${nome}.png`, fullPage: true });
        }
    });

    /**
     * SEM REDE, O ENDEREÇO MANDA.
     *
     * Só o POS ficou guardado. Abrir os clientes sem rede servia o POS debaixo
     * do endereço dos clientes (o recurso do service worker); com a casca única
     * o ecrã é o do endereço.
     */
    test('sem rede, um endereço servido de recurso desenha o seu ecrã', async ({ page, context }) => {
        await entrar(page);
        await irPara(page, '/invoicing/offline/pos');
        await page.waitForFunction(() => navigator.serviceWorker?.controller !== null, null, { timeout: 45_000 });
        await esperarMotor(page);
        await sincronizar(page);
        await irPara(page, '/invoicing/offline/pos');
        await montado(page);

        // Deixar só o POS no cache das páginas.
        await avaliar(page, async () => {
            const c = await caches.open('dynamic-paginas');
            for (const k of await c.keys()) {
                if (new URL(k.url).pathname !== '/invoicing/offline/pos') await c.delete(k);
            }
        });

        await context.setOffline(true);
        await irPara(page, '/invoicing/offline/clients');
        await montado(page);

        await expect(page.locator('#pwa-raiz')).toContainText(/Clientes/);
        await expect(page.locator('#pwa-raiz')).not.toContainText(/de [0-9]+ produtos/);

        await context.setOffline(false);
    });
});
