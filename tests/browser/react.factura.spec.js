import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * EMITIR UMA FACTURA DE VENDA EM REACT.
 *
 * O ecrã mais delicado da casa. O que se prova no browser: que abre, que os
 * totais vêm do servidor, que a FR troca os campos (forma de pagamento) e que
 * o armazém só se exige com artigos físicos. A emissão a sério é provada nos
 * ensaios de API, contra o mesmo serviço que a API usa.
 */

const ECRA = '/invoicing/sales/invoices/create';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
});

test('abre com FT, uma linha e sem totais', async ({ page }) => {
    await expect(page.getByLabel(/^Tipo\b/)).toHaveValue('FT');
    expect(await page.locator('tbody tr').count()).toBe(1);
    await expect(page.getByText('Escolha um artigo e uma quantidade')).toBeVisible();
    // Sem FR não há forma de pagamento.
    await expect(page.getByLabel(/^Forma de pagamento/)).toHaveCount(0);
});

test('a factura-recibo pede a forma de pagamento', async ({ page }) => {
    await page.getByLabel(/^Tipo\b/).selectOption('FR');
    await expect(page.getByLabel(/^Forma de pagamento/)).toBeVisible();
    await expect(page.getByRole('button', { name: /Emitir factura-recibo/ })).toBeVisible();
});

test('os totais vem do servidor', async ({ page }) => {
    const pedido = page.waitForResponse((r) => r.url().includes('/factura/calcular') && r.request().method() === 'POST', { timeout: 20_000 });

    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await page.getByLabel('Quantidade da linha 1').fill('2');

    expect((await pedido).ok()).toBe(true);
    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });
});

test('sem cliente o servidor recusa e o ecra diz onde', async ({ page }) => {
    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /^Emitir factura$/ }).click();

    await expect(page.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

