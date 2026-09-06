import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * A CASCA EM REACT.
 *
 * A prova que importa: o menu que o React desenha tem EXACTAMENTE as mesmas
 * ligações que o menu de sempre — porque vêm ambos do MenuDaCasca. Depois,
 * que abre e fecha, que encolhe pelo botão da barra do topo, e que se volta
 * à casca de sempre sem deixar rasto (a sessão é só deste ensaio).
 */

const ligacoesDoMenu = async (page) => {
    // Só as do menu, abertas ou fechadas: os grupos fechados não desenham as
    // dependentes no React, por isso abrem-se todos antes de contar.
    await page.evaluate(() => {
        document.querySelectorAll('#sidebar-menu button[aria-expanded="false"]').forEach((b) => b.click());
    });
    await page.waitForTimeout(300);
    await page.evaluate(() => {
        document.querySelectorAll('#sidebar-menu button[aria-expanded="false"]').forEach((b) => b.click());
    });
    await page.waitForTimeout(300);

    return page.evaluate(() =>
        Array.from(document.querySelectorAll('#sidebar-menu a[href]'))
            .map((a) => a.getAttribute('href').replace(window.location.origin, ''))
            .sort(),
    );
};

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test.afterEach(async ({ page }) => {
    // Nunca deixar a sessão do ensaio na casca nova.
    await page.goto('/casca/ecra-de-sempre');
});

test('o menu em React tem as mesmas ligacoes que o de sempre', async ({ page }) => {
    await page.goto('/invoicing/dashboard');
    await expect(page.locator('#sidebar-menu')).toBeVisible({ timeout: 20_000 });
    // No Blade o Alpine só esconde: as ligações estão todas no DOM.
    const deSempre = await page.evaluate(() =>
        Array.from(document.querySelectorAll('#sidebar-menu a[href]'))
            .map((a) => a.getAttribute('href').replace(window.location.origin, ''))
            .sort(),
    );
    expect(deSempre.length).toBeGreaterThan(20);

    await page.goto('/casca/novo-ecra');
    await page.goto('/invoicing/dashboard');
    await expect(page.locator('[data-ecra="casca"] #sidebar-menu')).toBeVisible({ timeout: 20_000 });

    const emReact = await ligacoesDoMenu(page);

    expect(emReact).toEqual(deSempre);
});

test('a pagina activa acende no menu novo', async ({ page }) => {
    await page.goto('/casca/novo-ecra');
    await page.goto('/invoicing/clients');
    await expect(page.locator('[data-ecra="casca"]')).toBeVisible({ timeout: 20_000 });

    const activa = page.locator('#sidebar-menu a[aria-current="page"]');
    await expect(activa).toHaveCount(1);
    await expect(activa).toHaveAttribute('href', /\/invoicing\/clients$/);
});

test('o botao da barra do topo encolhe a barra lateral', async ({ page }) => {
    await page.goto('/casca/novo-ecra');
    await page.goto('/invoicing/dashboard');
    const barra = page.locator('aside#app-sidebar');
    await expect(barra).toHaveAttribute('data-aberta', '1', { timeout: 20_000 });

    await page.locator('header button:visible').first().click();
    await expect(barra).toHaveAttribute('data-aberta', '0');
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto('/casca/novo-ecra');
    await page.goto('/invoicing/dashboard');
    await expect(page.locator('[data-ecra="casca"] #sidebar-menu')).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

test('volta-se a casca de sempre', async ({ page }) => {
    await page.goto('/casca/novo-ecra');
    await page.goto('/casca/ecra-de-sempre');
    await page.goto('/invoicing/dashboard');
    await expect(page.locator('#sidebar-menu')).toBeVisible({ timeout: 20_000 });
    await expect(page.locator('[data-ecra="casca"]')).toHaveCount(0);
});
