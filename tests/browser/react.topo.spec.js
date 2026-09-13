import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O TOPO DE TODAS AS PÁGINAS — a empresa activa, o contador, o sino e as
 * mensagens da plataforma. Eram componentes Livewire; são peças React
 * (`data-peca`) que falam com `/api/v1/casca`.
 *
 * O que só se vê no browser: que aparecem sem esqueleto a empurrar o
 * cabeçalho, que abrem e fecham (clique fora e Escape), e que não há erros na
 * consola. A troca de empresa não se faz aqui: mexe na sessão da bancada.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test('as pecas do topo montam sem erros e sem Livewire', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto('/home');

    await expect(page.locator('[data-peca="casca/notificacoes"] button').first()).toBeVisible({ timeout: 20_000 });
    await expect(page.locator('[data-peca="casca/empresa"] button').first()).toBeVisible();
    // Nenhum componente Livewire no cabeçalho.
    expect(await page.evaluate(() => Array.from(document.querySelectorAll('header *')).filter((el) => el.hasAttribute('wire:id')).length)).toBe(0);

    expect(erros).toEqual([]);
});

test('o sino abre, filtra e fecha com Escape', async ({ page }) => {
    await page.goto('/home');

    const sino = page.locator('[data-peca="casca/notificacoes"]');
    await sino.getByRole('button', { name: 'Notificações' }).click();

    const janela = sino.getByRole('dialog', { name: 'Notificações' });
    await expect(janela).toBeVisible();

    const pedido = page.waitForResponse((r) => r.url().includes('/api/v1/casca/notificacoes') && r.url().includes('so_por_ler=0'));
    await janela.getByRole('button', { name: 'Só por ler' }).click();
    expect((await pedido).status()).toBe(200);

    await page.keyboard.press('Escape');
    await expect(janela).toBeHidden();
});

test('o selector de empresa mostra a activa e fecha ao clicar fora', async ({ page }) => {
    await page.goto('/home');

    const peca = page.locator('[data-peca="casca/empresa"]');
    await peca.getByRole('button').first().click();

    await expect(peca.getByRole('menu')).toBeVisible();
    await expect(peca.getByRole('menuitem').first()).toBeVisible();

    await page.locator('main').click({ position: { x: 5, y: 5 } });
    await expect(peca.getByRole('menu')).toBeHidden();
});
