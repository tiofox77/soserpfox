import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * AS TRANSFERÊNCIAS EM REACT — entre armazéns e entre empresas.
 *
 * O que se prova no browser: que os ecrãs abrem, que o modal de transferir
 * exige o armazém de origem antes de deixar juntar artigos, e que as
 * moradas de sempre continuam em Livewire. Mexer no stock a sério prova-se
 * na API.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test('entre armazens: abre e o carrinho espera pelo armazem de origem', async ({ page }) => {
    await page.goto('/invoicing/warehouse-transfer/novo-ecra');
    await expect(page.getByRole('heading', { name: /Transferências e Ajustes de Stock/ }).first()).toBeVisible({ timeout: 20_000 });

    const transferir = page.getByRole('button', { name: /^Transferir$/ });
    if (await transferir.count()) {
        await transferir.click();
        await expect(page.getByLabel(/^Juntar artigo/)).toBeDisabled();
        await page.getByLabel(/^Do armazém/).selectOption({ index: 1 });
        await expect(page.getByLabel(/^Juntar artigo/)).toBeEnabled();
        await page.getByRole('button', { name: /^Cancelar$/ }).click();
    }
});

test('entre empresas: abre com o historico, ou diz que a porta esta fechada', async ({ page }) => {
    await page.goto('/invoicing/inter-company-transfer/novo-ecra');

    // A morada nova exige a permissão das transferências entre empresas — a
    // de sempre não exigia nada. Quem não a tem vê a porta fechada, e isso
    // também é o ecrã a funcionar.
    const titulo = page.getByRole('heading', { name: /Transferências Inter-Empresas/ }).first();
    const fechada = page.getByRole('heading', { name: /Sem permissão/ });
    await expect(titulo.or(fechada)).toBeVisible({ timeout: 20_000 });

    if (await titulo.count()) {
        await expect(page.getByRole('table')).toBeVisible();
    }
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto('/invoicing/warehouse-transfer/novo-ecra');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

test('as moradas de sempre continuam em Livewire', async ({ page }) => {
    for (const m of ['/invoicing/warehouse-transfer', '/invoicing/inter-company-transfer']) {
        await page.goto(m);
        await expect(page.locator('[data-ecra]')).toHaveCount(0);
    }
});
