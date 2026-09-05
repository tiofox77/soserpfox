import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * EMITIR UMA PROPOSTA EM REACT.
 *
 * O que este ecrã tem de diferente de todos os anteriores: os totais NÃO são
 * calculados aqui. A cada alteração de linha pergunta-se ao servidor e
 * mostra-se o que ele responder. É isso que estes ensaios provam — que a conta
 * vem de lá, e que o documento gravado tem o número da série.
 */

const ECRA = '/invoicing/sales/proformas/create/novo-ecra';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
});

test('abre com uma linha e sem totais', async ({ page }) => {
    await expect(page.getByLabel(/^Cliente\b/)).toBeVisible();
    expect(await page.locator('tbody tr').count()).toBe(1);

    // Sem quantidade não há nada para contar, e diz-se em vez de mostrar zeros.
    await expect(page.getByText('Escolha um artigo e uma quantidade')).toBeVisible();
});

/**
 * OS TOTAIS VÊM DO SERVIDOR.
 *
 * Escolhe-se um artigo, e é um pedido a `/calcular` que traz os números — não
 * uma conta feita no browser.
 */
test('os totais vem do servidor', async ({ page }) => {
    const pedido = page.waitForResponse(
        (r) => r.url().includes('/emissor/proformas-venda/calcular') && r.request().method() === 'POST',
        { timeout: 20_000 },
    );

    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await page.getByLabel('Quantidade da linha 1').fill('2');

    const resposta = await pedido;
    expect(resposta.ok()).toBe(true);

    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Incidência de IVA')).toBeVisible();
});

test('acrescenta e apaga linhas', async ({ page }) => {
    await page.getByRole('button', { name: /Nova linha/ }).click();
    expect(await page.locator('tbody tr').count()).toBe(2);

    await page.getByRole('button', { name: 'Apagar linha 2' }).click();
    expect(await page.locator('tbody tr').count()).toBe(1);

    // A ÚLTIMA NÃO SE APAGA: um documento sem linhas não é um documento.
    await expect(page.getByRole('button', { name: 'Apagar linha 1' })).toHaveCount(0);
});

test('grava e devolve o numero da serie', async ({ page }) => {
    await page.getByLabel(/^Cliente\b/).selectOption({ index: 1 });
    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await page.getByLabel('Quantidade da linha 1').fill('3');

    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /Gravar rascunho/ }).click();

    await expect(page.getByText('Gravado como rascunho')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('button', { name: /Abrir o documento/ })).toBeVisible();
});

/** Sem cliente, o servidor recusa e o ecrã diz onde. */
test('o erro de validacao aparece no campo', async ({ page }) => {
    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await page.getByLabel('Quantidade da linha 1').fill('1');

    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /Gravar rascunho/ }).click();

    await expect(page.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => {
        if (m.type() === 'error') erros.push(m.text());
    });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

test('o emissor Livewire continua na morada de sempre', async ({ page }) => {
    await page.goto('/invoicing/sales/proformas/create');

    await expect(page.locator('[data-ecra]')).toHaveCount(0);
});
