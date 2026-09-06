import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * A AGT EM REACT.
 *
 * O que se prova no browser: que o ecrã abre com os dois ambientes lado a
 * lado e o cabeçalho a dizer qual emite; que ver o outro ambiente propõe
 * activá-lo sem o activar; e que a ficha do contribuinte abre. Falar com a
 * AGT a sério não se prova aqui — nada sai de uma bancada.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test('a agt abre com os dois ambientes e so propoe activar o outro', async ({ page }) => {
    await page.goto('/invoicing/agt-settings');
    if (!(await page.locator('[data-ecra]').count())) {
        test.skip(true, 'sem permissão para a AGT nesta bancada');
    }
    const ecra = page.locator('[data-ecra]');
    await expect(page.locator('[data-a-emitir]')).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('[data-ambiente="sandbox"]')).toBeVisible();
    await expect(page.locator('[data-ambiente="production"]')).toBeVisible();
    await expect(page.locator('[data-relatorio]')).toBeVisible();

    const aEmitir = await page.locator('[data-a-emitir]').innerText();
    const outro = aEmitir.includes('Produção') ? 'sandbox' : 'production';

    // No ambiente activo não há nada a activar.
    await expect(ecra.getByRole('button', { name: /Passar a emitir aqui/ })).toHaveCount(0);

    await page.locator(`[data-ambiente="${outro}"]`).click();
    await expect(page.locator(`[data-ambiente="${outro}"]`)).toHaveAttribute('aria-pressed', 'true', { timeout: 30_000 });
    // Não se carrega: o cabeçalho continua a dizer a verdade sobre o que emite.
    await expect(page.locator('[data-a-emitir]')).toHaveText(aEmitir);

    await ecra.getByRole('tab', { name: /Chaves/ }).click();
    await expect(page.locator('[data-chaves]')).toBeVisible();
    await ecra.getByRole('tab', { name: /Submissões/ }).click();
    await expect(ecra.getByRole('table')).toBeVisible();
});

test('a ficha do contribuinte abre com o nif', async ({ page }) => {
    await page.goto('/invoicing/agt-credentials');
    if (!(await page.locator('[data-ecra]').count())) {
        test.skip(true, 'sem permissão para a AGT nesta bancada');
    }
    await expect(page.locator('[data-estado]')).toBeVisible({ timeout: 30_000 });
    await expect(page.getByLabel(/^NIF do contribuinte/)).toBeVisible();
    await expect(page.getByLabel(/^Estabelecimento/)).not.toHaveValue('');
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto('/invoicing/agt-settings');
    await page.waitForLoadState('networkidle');
    await page.goto('/invoicing/agt-credentials');
    await page.waitForLoadState('networkidle');

    expect(erros).toEqual([]);
});
