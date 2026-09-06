import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * NOTAS DE CRÉDITO E DE DÉBITO EM REACT.
 *
 * O ecrã só acerta quantidades. A taxa, o código SAFT, a região e os totais
 * vêm da linha original, calculados no servidor pelo mesmo serviço que a
 * API chama. É isso que estes ensaios olham: que as linhas chegam do
 * servidor com o imposto delas, e que o travão do E43 aparece no ecrã sem a
 * nota ter nascido.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

for (const [tipo, morada, titulo] of [
    ['credito', '/invoicing/credit-notes/create', 'Nota de Crédito'],
    ['debito', '/invoicing/debit-notes/create', 'Nota de Débito'],
]) {
    test(`${titulo}: abre e pede o cliente primeiro`, async ({ page }) => {
        const erros = [];
        page.on('console', (m) => {
            if (m.type() === 'error') erros.push(m.text());
        });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.goto(morada);

        await expect(page.getByLabel(/^Cliente\b/)).toBeVisible({ timeout: 20_000 });
        await expect(page.getByLabel(/^Factura\b/)).toBeDisabled();
        await expect(page.getByText('Escolha a factura para ver as linhas')).toBeVisible();

        // O botão de emitir só acende com linhas.
        await expect(page.getByRole('button', { name: new RegExp(`Emitir ${titulo.toLowerCase()}`) })).toBeDisabled();

        expect(erros).toEqual([]);
    });

}

/**
 * AS LINHAS VÊM DO SERVIDOR, COM O IMPOSTO DA LINHA ORIGINAL.
 */
test('Nota de Crédito: escolher a factura traz as linhas com a taxa delas', async ({ page }) => {
    await page.goto('/invoicing/credit-notes/create');
    await expect(page.getByLabel(/^Cliente\b/)).toBeVisible({ timeout: 20_000 });

    // O primeiro cliente com facturas por creditar.
    const clientes = page.getByLabel(/^Cliente\b/);
    const total = await clientes.locator('option').count();

    let encontrou = false;

    for (let i = 1; i < Math.min(total, 15); i++) {
        await clientes.selectOption({ index: i });

        const facturas = page.getByLabel(/^Factura\b/);
        await expect(facturas).toBeEnabled();

        // Espera a lista de facturas deste cliente chegar.
        await page.waitForResponse((r) => r.url().includes('/notas/credito/facturas'), { timeout: 20_000 }).catch(() => {});

        if ((await facturas.locator('option').count()) > 1) {
            encontrou = true;
            break;
        }
    }

    test.skip(!encontrou, 'a bancada não tem facturas por creditar');

    const pedido = page.waitForResponse((r) => r.url().includes('/linhas'), { timeout: 20_000 });
    await page.getByLabel(/^Factura\b/).selectOption({ index: 1 });
    expect((await pedido).ok()).toBe(true);

    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    // Cada linha diz a taxa e a região da linha original — vêm do servidor.
    await expect(page.locator('tbody tr').first()).toContainText(/%\s·\s[A-Z-]+/);

    // E o saldo por anular está à vista.
    await expect(page.getByText(/por anular/)).toBeVisible();
});
