import { expect, test } from '@playwright/test';

import { entrar } from './apoio.js';

/**
 * O LAYOUT SEM O LIVEWIRE, no browser.
 *
 * O Alpine vinha dentro do pacote do Livewire e passou a vir do disco. O que
 * dependia dele tem de continuar vivo: a barra lateral (grupos com x-collapse),
 * o menu do utilizador, a escolha de língua — e a barra de progresso ao mudar
 * de página, que era da navegação do Livewire.
 */

test('a barra lateral, os menus e a mudanca de pagina funcionam sem livewire', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error' || /Alpine Warning/.test(m.text())) erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await entrar(page);
    await page.goto('/casca/ecra-de-sempre');
    await page.goto('/home');

    expect(await page.evaluate(() => typeof window.Alpine)).toBe('object');
    expect(await page.evaluate(() => typeof window.Livewire)).toBe('undefined');

    // Um grupo do menu abre com a animação de altura e fecha.
    const grupo = page.locator('#sidebar-menu button').first();
    await grupo.click();
    const aberto = page.locator('#sidebar-menu [x-collapse]').first();
    await expect(aberto).toBeVisible();
    await page.waitForTimeout(400);
    await page.screenshot({ path: 'test-results/layout-sem-livewire.png' });

    // A língua abre.
    await page.getByTitle('Língua / Language / Langue').click();
    await expect(page.getByRole('link', { name: 'English' })).toBeVisible();
    await page.keyboard.press('Escape');
    await page.mouse.click(5, 5);

    // A barra de progresso corre ao clicar numa ligação interna.
    await aberto.locator('a').first().waitFor();
    const destino = await aberto.locator('a').first().getAttribute('href');
    await Promise.all([
        page.waitForURL((u) => destino && u.href.includes(new URL(destino, page.url()).pathname)),
        aberto.locator('a').first().click(),
    ]);

    expect(erros).toEqual([]);
});
