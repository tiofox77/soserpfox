import { expect, test } from '@playwright/test';

import { CREDENCIAIS } from './apoio.js';

/**
 * AS PÁGINAS DE ENTRADA EM REACT, no browser.
 *
 * Os formulários continuam a ser POST aos controladores de sempre. Prova-se
 * aqui o que só se vê a sério: que um login errado volta com o erro e o email
 * escrito, que o olho mostra a senha, que se chega à recuperação e se volta, e
 * que entrar leva para dentro — sem um erro na consola.
 */

test('entrar, errar, recuperar e voltar', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto('/login');
    await expect(page.getByRole('heading', { name: 'Bem-vindo de volta' })).toBeVisible();
    expect(await page.evaluate(() => document.querySelector('script[src*="cdn.tailwindcss.com"]'))).toBeNull();

    // Um login errado volta com o erro e com o email que se escreveu.
    await page.fill('input[name="email"]', 'ninguem@exemplo.ao');
    await page.fill('input[name="password"]', 'errada-de-proposito');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/login$/);
    await expect(page.getByRole('alert').first()).toBeVisible();
    await expect(page.locator('input[name="email"]')).toHaveValue('ninguem@exemplo.ao');
    await expect(page.locator('input[name="password"]')).toHaveValue('');

    // O olho mostra e esconde a senha.
    await page.fill('input[name="password"]', 'abc');
    await page.getByRole('button', { name: 'Mostrar a senha' }).click();
    await expect(page.locator('input[name="password"]')).toHaveAttribute('type', 'text');
    await page.getByRole('button', { name: 'Esconder a senha' }).click();
    await expect(page.locator('input[name="password"]')).toHaveAttribute('type', 'password');

    // A recuperação, e o caminho de volta.
    await page.getByRole('link', { name: 'Esqueceu a senha?' }).click();
    await expect(page.getByRole('heading', { name: 'Recuperar palavra-passe' })).toBeVisible();
    await page.screenshot({ path: 'test-results/entrada-recuperar.png' });
    await page.getByRole('link', { name: /Voltar ao login/ }).click();
    await expect(page.getByRole('heading', { name: 'Bem-vindo de volta' })).toBeVisible();

    // E entrar leva para dentro.
    await page.fill('input[name="email"]', CREDENCIAIS.email);
    await page.fill('input[name="password"]', CREDENCIAIS.password);
    await page.screenshot({ path: 'test-results/entrada-login.png' });
    await page.click('button[type="submit"]');
    await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 30_000 });

    expect(erros.filter((e) => !/status of 422|status of 419/.test(e))).toEqual([]);
});
