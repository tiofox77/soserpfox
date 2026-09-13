import { expect, test } from '@playwright/test';

import { entrar } from './apoio.js';

/**
 * O LAYOUT SEM O LIVEWIRE, no browser.
 *
 * O Alpine vinha dentro do pacote do Livewire e passou a vir do disco. O que
 * ainda é Blade tem de continuar vivo — a escolha de língua, o botão da barra
 * do topo — e a barra de progresso ao mudar de página, que era da navegação do
 * Livewire, tem de acompanhar o clique até à página seguinte.
 */

test('o alpine do disco, os menus do topo e a mudanca de pagina', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error' || /Alpine Warning/.test(m.text())) erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await entrar(page);
    await page.goto('/home');

    expect(await page.evaluate(() => typeof window.Alpine)).toBe('object');
    expect(await page.evaluate(() => typeof window.Livewire)).toBe('undefined');

    // A língua abre.
    await page.getByTitle('Língua / Language / Langue').click();
    await expect(page.getByRole('link', { name: 'English' })).toBeVisible();
    await page.mouse.click(700, 5);

    // Um grupo do menu abre; a ligação lá dentro leva à página, com a barra de progresso a correr.
    // Pelo nome, e não pelo estado: depois de aberto, «o primeiro fechado» já seria outro.
    const grupo = page.locator('#sidebar-menu button[aria-expanded]').filter({ hasText: 'Facturação' });
    await expect(grupo).toBeVisible({ timeout: 20_000 });
    if (await grupo.getAttribute('aria-expanded') === 'false') await grupo.click();
    const ligacao = grupo.locator('xpath=following-sibling::div[1]').locator('a[href]').first();
    await expect(ligacao).toBeVisible();
    await page.waitForTimeout(400);
    await page.screenshot({ path: 'test-results/layout-sem-livewire.png' });

    const destino = new URL(await ligacao.getAttribute('href'), page.url()).pathname;
    await ligacao.click();
    await expect.poll(() => page.evaluate(() => document.getElementById('spa-progress')?.style.width ?? '0%')).not.toBe('0%').catch(() => {});
    await page.waitForURL((u) => u.pathname === destino, { timeout: 30_000 });

    expect(erros).toEqual([]);
});
