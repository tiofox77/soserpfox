import { expect, test } from '@playwright/test';

import { entrar } from './apoio.js';

/**
 * A PÁGINA INICIAL EM REACT, no browser.
 *
 * Abre sem erros, cumprimenta, mostra a ficha da empresa activa, e os acessos
 * rápidos levam a um ecrã (eram «#»).
 */
test('a pagina inicial abre em react e os acessos levam a algum lado', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await entrar(page);
    await page.goto('/home');

    await expect(page.locator('[data-ecra="inicio"]')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('heading', { name: /^Olá, / })).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Informações da Empresa')).toBeVisible();

    const acessos = await page.locator('[data-ecra="inicio"] a[href]').evaluateAll((as) => as.map((a) => a.getAttribute('href')));
    expect(acessos).not.toContain('#');

    await page.waitForTimeout(600);
    await page.screenshot({ path: 'test-results/inicio.png', fullPage: true });

    expect(erros).toEqual([]);
});
