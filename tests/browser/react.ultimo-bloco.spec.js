import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O ÚLTIMO BLOCO EM REACT: turnos do POS, cópia offline, PIN e modelos de
 * proposta. Prova-se no browser que abrem e se desenham; as regras estão
 * na API.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test('o turno e o historico abrem', async ({ page }) => {
    await page.goto('/invoicing/pos/shifts');
    await expect(page.locator('[data-estado-turno]')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('button', { name: /Abrir turno|Fechar turno/ })).toBeVisible();

    await page.goto('/invoicing/pos/shift-history');
    await expect(page.locator('[data-historico-turnos]')).toBeVisible({ timeout: 20_000 });
    await expect(page.locator('[data-historico-turnos]').getByRole('table')).toBeVisible();
});

test('a copia offline e o pin abrem', async ({ page }) => {
    await page.goto('/invoicing/importar-copia-offline');
    if (await page.locator('[data-ecra]').count()) {
        await expect(page.locator('[data-copia-offline]')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByLabel('Ficheiro da cópia')).toBeAttached();
    }

    await page.goto('/invoicing/offline/pin');
    await expect(page.locator('[data-definir-pin]')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByLabel(/^PIN novo/)).toBeVisible();
});

test('os modelos de proposta e o editor abrem', async ({ page }) => {
    await page.goto('/invoicing/sales/quote-templates');
    if (!(await page.locator('[data-ecra]').count())) {
        test.skip(true, 'sem permissão para orçamentos nesta bancada');
    }
    await expect(page.locator('[data-modelos]')).toBeVisible({ timeout: 20_000 });

    const novo = page.getByRole('button', { name: /Novo modelo/ });
    if (await novo.count()) {
        await novo.click();
        await expect(page.locator('[data-arranque]')).toBeVisible();
        await page.getByRole('button', { name: /^Cancelar$/ }).click();
    }

    const editar = page.locator('[data-modelo]').first().getByRole('link', { name: /Editar/ });
    if (await editar.count()) {
        await editar.click();
        await expect(page.locator('[data-editor-de-modelo]')).toBeVisible({ timeout: 30_000 });
        await expect(page.locator('[data-previa]')).toBeVisible();
        await expect(page.locator('[data-blocos] li').first()).toBeVisible();
    }
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    for (const m of ['/invoicing/pos/shifts', '/invoicing/pos/shift-history', '/invoicing/offline/pin', '/invoicing/sales/quote-templates']) {
        await page.goto(m);
        await page.waitForLoadState('networkidle');
    }

    expect(erros).toEqual([]);
});
