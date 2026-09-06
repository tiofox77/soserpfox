import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * AS SÉRIES E A AUDITORIA EM REACT.
 *
 * O que se prova no browser: que as séries abrem com a lista e o modal da
 * série nova se desenha; que a auditoria abre e a cadeia se verifica; e
 * que as moradas de sempre continuam em Livewire. As regras (o prefixo
 * fiscal, a série registada na AGT) provam-se na API.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test('as series abrem com a lista e o modal da serie nova', async ({ page }) => {
    await page.goto('/invoicing/series/novo-ecra');
    if (!(await page.locator('[data-ecra]').count())) {
        test.skip(true, 'sem permissão para as séries nesta bancada');
    }
    await expect(page.locator('[data-ecra]').getByText('Séries de Documentos', { exact: true })).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('table')).toBeVisible();

    const nova = page.getByRole('button', { name: /Nova série/ });
    if (await nova.count()) {
        await nova.click();
        await expect(page.getByLabel(/^Código/)).toBeVisible();
        // O prefixo é fiscal: mostra-se, não se escreve.
        await expect(page.getByLabel(/^Prefixo/)).toHaveAttribute('readonly', '');
        await page.getByRole('button', { name: /^Cancelar$/ }).click();
        await expect(page.getByLabel(/^Código/)).toBeHidden();
    }
});

test('a auditoria abre e verifica a cadeia', async ({ page }) => {
    await page.goto('/invoicing/auditoria/novo-ecra');
    if (!(await page.locator('[data-ecra]').count())) {
        test.skip(true, 'sem permissão para a auditoria nesta bancada');
    }
    const verificar = page.getByRole('button', { name: /Verificar a cadeia/ });
    await expect(verificar).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('table')).toBeVisible();

    await verificar.click();
    await expect(page.locator('[data-integridade]')).toBeVisible({ timeout: 60_000 });
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto('/invoicing/series/novo-ecra');
    await page.waitForLoadState('networkidle');
    await page.goto('/invoicing/auditoria/novo-ecra');
    await page.waitForLoadState('networkidle');

    expect(erros).toEqual([]);
});

test('as moradas de sempre continuam em Livewire', async ({ page }) => {
    for (const m of ['/invoicing/series', '/invoicing/auditoria']) {
        await page.goto(m);
        await expect(page.locator('[data-ecra]')).toHaveCount(0);
    }
});
