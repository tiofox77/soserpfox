import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * PAGAR UMA FACTURA A PARTIR DAS LISTAS EM REACT.
 *
 * O botão só aparece nas facturas com saldo. Na bancada pode não haver
 * nenhuma: o que se prova sem depender disso é que as listas abrem sem
 * erro com o modal a bordo; havendo uma por pagar, que o modal abre com o
 * que falta e se fecha. Pagar a sério prova-se na API.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

for (const lista of ['/invoicing/sales/invoices/novo-ecra', '/invoicing/purchases/invoices/novo-ecra']) {
    test(`a lista ${lista} abre sem erros com o modal a bordo`, async ({ page }) => {
        const erros = [];
        page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.goto(lista);
        // Com facturas há tabela; sem nenhuma, a lista diz que não há. As duas
        // são a lista a funcionar.
        await expect(page.getByRole('table').or(page.getByText(/Nenhum documento|Nenhuma factura/))).toBeVisible({ timeout: 20_000 });

        const pagar = page.getByRole('button', { name: /^Pagar / }).first();

        if (await pagar.count()) {
            await pagar.click();
            await expect(page.locator('[data-por-pagar]')).toBeVisible({ timeout: 20_000 });
            await expect(page.getByRole('button', { name: /Registar pagamento/ })).toBeVisible();
            await page.getByRole('button', { name: /^Cancelar$/ }).click();
            await expect(page.locator('[data-por-pagar]')).toBeHidden();
        }

        expect(erros).toEqual([]);
    });
}
