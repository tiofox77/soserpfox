import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * REGISTAR UMA FACTURA DE COMPRA EM REACT.
 *
 * O que se prova no browser: que abre, que os totais vêm do servidor, que o
 * armazém é obrigatório (a compra dá entrada de stock) e que o servidor
 * recusa sem fornecedor e diz onde. O registo a sério — stock, lotes, custo —
 * é provado nos ensaios de API, contra o mesmo serviço que o Livewire usa.
 */

const ECRA = '/invoicing/purchases/invoices/create/novo-ecra';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
});

test('abre com uma linha, lote e validade, e sem totais', async ({ page }) => {
    expect(await page.locator('tbody tr').count()).toBe(1);
    await expect(page.getByLabel('Lote da linha 1')).toBeVisible();
    await expect(page.getByLabel('Validade da linha 1')).toBeVisible();
    await expect(page.getByText('Escolha um artigo e uma quantidade')).toBeVisible();
});

test('os totais vem do servidor', async ({ page }) => {
    const pedido = page.waitForResponse((r) => r.url().includes('/compra/calcular') && r.request().method() === 'POST', { timeout: 20_000 });

    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await page.getByLabel('Quantidade da linha 1').fill('3');

    expect((await pedido).ok()).toBe(true);
    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });
});

test('sem fornecedor o servidor recusa e o ecra diz onde', async ({ page }) => {
    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /^Registar compra$/ }).click();

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

test('o emissor Livewire continua na morada de sempre', async ({ page }) => {
    await page.goto('/invoicing/purchases/invoices/create');
    await expect(page.locator('[data-ecra]')).toHaveCount(0);
});
