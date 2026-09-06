import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * AS QUEBRAS E OS LOTES EM REACT.
 *
 * O que se prova no browser: que abrem com o relatório e a lista, que os
 * formulários se desenham, e que as moradas de sempre servem o
 * React. Mexer no stock a sério prova-se na API.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test('as quebras abrem com o periodo e o relatorio', async ({ page }) => {
    await page.goto('/invoicing/quebras');
    await expect(page.getByText('Perdido no período')).toBeVisible({ timeout: 20_000 });
    await expect(page.locator('[data-custo]')).toBeVisible();
    await expect(page.getByRole('table')).toBeVisible();
});

test('os lotes abrem com os cartoes e o modal do lote novo', async ({ page }) => {
    await page.goto('/invoicing/product-batches');
    await expect(page.getByText('Lotes activos', { exact: true })).toBeVisible({ timeout: 20_000 });

    const novo = page.getByRole('button', { name: /Novo lote/ });
    if (await novo.count()) {
        await novo.click();
        await expect(page.getByLabel(/^Número do lote/)).toBeVisible();
        await page.getByRole('button', { name: /^Cancelar$/ }).click();
        await expect(page.getByLabel(/^Número do lote/)).toBeHidden();
    }
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto('/invoicing/quebras');
    await expect(page.getByText('Perdido no período')).toBeVisible({ timeout: 20_000 });
    await page.goto('/invoicing/product-batches');
    await expect(page.getByText('Lotes activos', { exact: true })).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});
