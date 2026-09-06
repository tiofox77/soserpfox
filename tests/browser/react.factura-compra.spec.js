import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * REGISTAR UMA FACTURA DE COMPRA EM REACT.
 *
 * O que se prova no browser: que abre, que os totais vêm do servidor, que o
 * armazém é obrigatório (a compra dá entrada de stock) e que o servidor
 * recusa sem fornecedor e diz onde. O registo a sério — stock, lotes, custo —
 * é provado nos ensaios de API, contra o mesmo serviço que a API usa.
 */

const ECRA = '/invoicing/purchases/invoices/create';

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


/**
 * DUPLICAR UMA COMPRA: o conteúdo viaja, o stock não se mexe.
 *
 * Da lista carrega-se em duplicar e chega-se aqui com `?duplicar=` — o
 * fornecedor e as linhas já preenchidos, sem número e sem estado. Abrir este
 * ecrã não regista nada e não dá entrada de nada: o stock só entra quando a
 * compra for mesmo registada.
 */
test('duplicar traz o conteudo da compra e nao regista nada', async ({ page }) => {
    await page.goto('/invoicing/purchases/invoices');

    await expect(
        page.getByRole('table').or(page.getByText('Nenhum documento com estes filtros')),
    ).toBeVisible({ timeout: 20_000 });

    if (!(await page.getByRole('table').isVisible())) {
        test.skip(true, 'a empresa de bancada não tem facturas de compra');
    }

    await page.getByRole('link', { name: /^Duplicar / }).first().click();

    await expect(page.locator('[data-duplicado-de]')).toBeVisible({ timeout: 20_000 });
    await expect(page.locator('[data-documento-aberto]')).toHaveCount(0);

    // O conteúdo veio. Em expressão regular e sem ligar a maiúsculas: o
    // rótulo dos campos obrigatórios é «Fornecedor* (obrigatório)».
    await expect(page.getByLabel(/^fornecedor\b/i)).not.toHaveValue('');
    await expect(page.getByLabel('Artigo da linha 1')).not.toHaveValue('');

    // E o que se pode fazer é REGISTAR uma compra nova — não actualizar a velha.
    await expect(page.getByRole('button', { name: /^Registar compra$/ })).toBeVisible();
});

/**
 * UM `?duplicar=` QUE NÃO EXISTE DIZ-SE — e não abre um formulário meio feito.
 *
 * Acontece com um atalho guardado, um separador aberto de antes, ou o id de
 * outra empresa colado na barra de endereço: o servidor responde 404 (com o
 * escopo da empresa e o do autor já aplicados) e o ecrã tem de o mostrar. Um
 * formulário em branco, sem uma palavra, mandava a pessoa registar de novo uma
 * compra que ela julgava estar a duplicar.
 */
test('duplicar um documento que nao existe diz que nao abriu', async ({ page }) => {
    await page.goto('/invoicing/purchases/invoices/create?duplicar=99999999');

    await expect(page.getByRole('alert')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Não foi possível abrir o registo de compras')).toBeVisible();
});