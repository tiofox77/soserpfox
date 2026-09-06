import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * REGISTAR UM ADIANTAMENTO EM REACT.
 *
 * O que se prova no browser: que abre com o dia de hoje e a forma de
 * pagamento, que o servidor recusa sem cliente e diz onde, e que a morada
 * de sempre serve o React. Registar a sério prova-se na API.
 */

const ECRA = '/invoicing/advances/create';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('button', { name: /Registar adiantamento/ })).toBeVisible({ timeout: 20_000 });
});

test('abre com o dia de hoje e a forma de pagamento', async ({ page }) => {
    await expect(page.getByLabel(/^Data do pagamento/)).toHaveValue(new Date().toISOString().slice(0, 10));
    await expect(page.getByLabel(/^Forma de pagamento/)).toHaveValue('cash');
});

test('sem cliente o servidor recusa e o ecra diz onde', async ({ page }) => {
    await page.getByLabel(/^Valor/).fill('1000');
    await page.getByRole('button', { name: /Registar adiantamento/ }).click();

    await expect(page.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByRole('button', { name: /Registar adiantamento/ })).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

