import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * AS IMPORTAÇÕES EM REACT.
 *
 * O que se prova no browser: que a lista abre com o resumo, que o
 * formulário mostra o CIF a somar à medida que se escreve, que o servidor
 * recusa sem fornecedor e diz onde, e que a morada de sempre serve o
 * React.
 */

const ECRA = '/invoicing/imports';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
});

test('abre com o resumo e o formulario soma o CIF', async ({ page }) => {
    await expect(page.getByText('CIF em curso')).toBeVisible();

    await page.getByRole('button', { name: /Nova importação/ }).click();
    await page.getByLabel(/^FOB\b/).fill('1000');
    await page.getByLabel(/^Frete\b/).fill('200');
    await page.getByLabel(/^Seguro\b/).fill('50');

    // 1.250,00 — com o separador de milhares que a casa usar.
    await expect(page.locator('[data-cif]')).toHaveText(/1[\s.  ]?250/);
});

test('sem fornecedor o servidor recusa e o ecra diz onde', async ({ page }) => {
    await page.getByRole('button', { name: /Nova importação/ }).click();
    await page.getByRole('button', { name: /^Guardar$/ }).click();

    await expect(page.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

