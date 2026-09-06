import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * AS GUIAS DE TRANSPORTE EM REACT.
 *
 * O que se prova no browser: que a lista abre, que o modal da guia nova se
 * desenha com o tipo e as linhas, que o servidor recusa sem cliente e diz
 * onde, e que a morada de sempre serve o React. Emitir a sério
 * prova-se na API.
 */

const ECRA = '/invoicing/transport-guides';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
});

test('abre a lista e o modal da guia nova', async ({ page }) => {
    await page.getByRole('button', { name: /Nova guia/ }).click();
    await expect(page.getByLabel(/^Tipo\b/)).toHaveValue('GT');
    await expect(page.getByLabel(/^Cliente\b/)).toBeVisible();
    await expect(page.getByText('Sem linhas.')).toBeVisible();
});

test('juntar um artigo cria uma linha', async ({ page }) => {
    await page.getByRole('button', { name: /Nova guia/ }).click();
    await page.getByLabel('Artigo a juntar').selectOption({ index: 1 });
    await page.getByRole('button', { name: /^Juntar$/ }).click();

    await expect(page.getByLabel('Quantidade da linha 1')).toHaveValue('1');
});

test('sem cliente o servidor recusa e o ecra diz onde', async ({ page }) => {
    await page.getByRole('button', { name: /Nova guia/ }).click();
    await page.getByLabel('Artigo a juntar').selectOption({ index: 1 });
    await page.getByRole('button', { name: /^Juntar$/ }).click();
    await page.getByRole('button', { name: /Registar guia/ }).click();

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

