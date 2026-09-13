import { expect, test } from '@playwright/test';

/**
 * O PORTAL DO CLIENTE EM REACT, no browser.
 *
 * Entra com o cliente da bancada (`bancada:pwa` → cliente@pwa.local), percorre
 * as seis páginas sem erros na consola, e prova as duas coisas que só se vêem
 * aqui: o formulário de entrada diz o erro sem recarregar, e o menu do
 * utilizador (que era Alpine) abre e fecha.
 */

const CLIENTE = { email: 'cliente@pwa.local', password: 'bancada-pwa-2026' };

async function entrarNoPortal(page) {
    await page.goto('/client/login');
    await page.getByLabel(/Email/).fill(CLIENTE.email);
    await page.getByLabel(/Senha/).fill(CLIENTE.password);
    await page.getByRole('button', { name: /Entrar no Portal/ }).click();
    await page.waitForURL(/\/client\/dashboard/, { timeout: 30_000 });
}

test('uma senha errada diz porquê, sem sair da pagina', async ({ page }) => {
    await page.goto('/client/login');
    await page.getByLabel(/Email/).fill(CLIENTE.email);
    await page.getByLabel(/Senha/).fill('errada-de-proposito');
    await page.getByRole('button', { name: /Entrar no Portal/ }).click();

    await expect(page.getByRole('alert')).toContainText(/credenciais/);
    await expect(page).toHaveURL(/\/client\/login/);
});

test('as paginas do portal abrem sem erros', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await entrarNoPortal(page);

    for (const [morada, titulo] of [
        ['/client/dashboard', /Bem-vindo/],
        ['/client/statement', 'Extrato Financeiro'],
        ['/client/events', 'Meus Eventos'],
        ['/client/invoices', 'Minhas Faturas'],
        ['/client/proformas', 'Minhas Proformas'],
        ['/client/profile', 'Meu Perfil'],
    ]) {
        const r = await page.goto(morada);
        expect(r?.status(), morada).toBeLessThan(400);
        await expect(page.locator('[data-ecra]').getByRole('heading', { level: 1, name: titulo })).toBeVisible({ timeout: 20_000 });
    }

    expect(erros).toEqual([]);
});

test('o menu do utilizador abre e fecha com Escape', async ({ page }) => {
    await entrarNoPortal(page);

    const menu = page.locator('nav [data-menu]').first();
    await menu.getByRole('button', { name: 'Menu do utilizador' }).click();
    await expect(menu.getByRole('link', { name: /Meu Perfil/ })).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(menu.getByRole('link', { name: /Meu Perfil/ })).toBeHidden();
});
