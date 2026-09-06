import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * A GESTÃO DE STOCK EM REACT.
 *
 * O que se prova no browser: que abre com os cartões e a tabela, que o
 * modal da movimentação em lote se desenha e procura artigos no servidor, e
 * que a morada de sempre serve o React. Mexer no stock a sério
 * prova-se na API — a bancada é partilhada.
 */

const ECRA = '/invoicing/stock';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByText('Abaixo do mínimo', { exact: true })).toBeVisible({ timeout: 20_000 });
});

test('abre com os cartoes e a tabela', async ({ page }) => {
    await expect(page.getByText('Valor ao custo')).toBeVisible();
    await expect(page.getByRole('table')).toBeVisible();
});

test('a movimentacao em lote procura artigos no servidor', async ({ page }) => {
    await page.getByRole('button', { name: /Movimentação em lote/ }).click();

    const pedido = page.waitForResponse((r) => r.url().includes('/stock/artigos') && r.request().method() === 'GET', { timeout: 20_000 });
    await page.getByLabel(/^Juntar artigo/).fill('a');
    expect((await pedido).ok()).toBe(true);

    await page.getByRole('button', { name: /^Cancelar$/ }).click();
    await expect(page.getByLabel(/^Juntar artigo/)).toBeHidden();
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByText('Abaixo do mínimo', { exact: true })).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

