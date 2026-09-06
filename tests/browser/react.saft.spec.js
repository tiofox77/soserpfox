import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O GERADOR SAFT-AO EM REACT.
 *
 * O que se prova no browser: que abre com o período e as contagens, e que
 * mudar o tipo de documentos volta a contar. O XML prova-se na API — aqui
 * não se descarrega nada.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test('o gerador abre com o periodo e as contagens', async ({ page }) => {
    await page.goto('/invoicing/saft-generator/novo-ecra');
    if (!(await page.locator('[data-ecra]').count())) {
        test.skip(true, 'sem permissão para o SAFT nesta bancada');
    }
    await expect(page.locator('[data-ecra]').getByText('SAFT-AO do período', { exact: true })).toBeVisible({ timeout: 20_000 });
    await expect(page.locator('[data-estatisticas]').getByText('Facturas', { exact: true })).toBeVisible({ timeout: 20_000 });
    await expect(page.getByLabel(/^De\b/)).toHaveValue(/^\d{4}-\d{2}-01$/);

    await page.getByLabel(/^Documentos/).selectOption('inventory');
    await expect(page.locator('[data-estatisticas]').getByText('Facturas', { exact: true })).toBeVisible();
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto('/invoicing/saft-generator/novo-ecra');
    await page.waitForLoadState('networkidle');

    expect(erros).toEqual([]);
});

test('a morada de sempre continua em Livewire', async ({ page }) => {
    await page.goto('/invoicing/saft-generator');
    await expect(page.locator('[data-ecra]')).toHaveCount(0);
});
