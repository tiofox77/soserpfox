import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS ARTIGOS EM REACT.
 *
 * O que este ecrã tem de diferente dos outros dois é a REGRA DO STOCK: a
 * quantidade só existe ao criar. A editar, o stock é o que as linhas dizem, e
 * mexer nele aqui revertia vendas feitas entretanto. É isso que se prova.
 */

const ECRA = '/invoicing/products/novo-ecra';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
});

test('a lista abre com os artigos da empresa', async ({ page }) => {
    await expect(page.getByRole('columnheader', { name: 'Stock' })).toBeVisible();
    expect(await page.locator('tbody tr').count()).toBeGreaterThan(0);
});

/**
 * O STOCK NÃO SE EDITA AQUI.
 *
 * A criar há «Quantidade inicial». A editar não há campo nenhum — só o valor
 * a dizer onde se ajusta.
 */
test('a quantidade so existe ao criar, nunca ao editar', async ({ page }) => {
    await page.getByRole('button', { name: /Novo artigo/ }).click();

    const novo = page.getByRole('dialog');
    await expect(novo.getByLabel('Quantidade inicial')).toBeVisible();
    await novo.getByRole('button', { name: 'Cancelar' }).click();

    // Agora a editar um que já exista.
    await page.locator('tbody tr').first().getByRole('button', { name: /^Editar / }).click();

    const edicao = page.getByRole('dialog');
    await expect(edicao).toBeVisible();

    await expect(edicao.getByLabel('Quantidade inicial')).toHaveCount(0);
    await expect(edicao.getByText('ajusta-se na Gestão de Stock')).toBeVisible();
});

/** Um serviço não gere stock — os campos de stock desaparecem. */
test('escolher servico faz desaparecer o stock', async ({ page }) => {
    await page.getByRole('button', { name: /Novo artigo/ }).click();

    const janela = page.getByRole('dialog');

    await expect(janela.getByLabel('Stock mínimo')).toBeVisible();

    await janela.getByLabel('Tipo').selectOption('servico');

    await expect(janela.getByLabel('Stock mínimo')).toHaveCount(0);
    await expect(janela.getByLabel('Quantidade inicial')).toHaveCount(0);
});

/** O imposto: ou taxa do catálogo, ou isenção com motivo. Nunca à mão. */
test('o imposto troca entre taxa do catalogo e motivo de isencao', async ({ page }) => {
    await page.getByRole('button', { name: /Novo artigo/ }).click();

    const janela = page.getByRole('dialog');

    await janela.getByLabel('Imposto').selectOption('iva');
    await expect(janela.getByLabel('Taxa')).toBeVisible();
    await expect(janela.getByLabel('Motivo da isenção')).toHaveCount(0);

    await janela.getByLabel('Imposto').selectOption('isento');
    await expect(janela.getByLabel('Motivo da isenção')).toBeVisible();
    await expect(janela.getByLabel('Taxa')).toHaveCount(0);
});

test('cria um artigo e ele aparece na lista', async ({ page }) => {
    const nome = 'Artigo React ' + String(Date.now()).slice(-6);

    await page.getByRole('button', { name: /Novo artigo/ }).click();

    const janela = page.getByRole('dialog');

    await janela.getByLabel('Nome').fill(nome);
    await janela.getByLabel('Preço').fill('1500');
    await janela.getByLabel('Categoria').selectOption({ index: 1 });
    await janela.getByLabel('Imposto').selectOption('isento');
    await janela.getByLabel('Motivo da isenção').fill('M99');
    await janela.getByRole('button', { name: 'Guardar' }).click();

    await expect(page.getByRole('status')).toContainText('Artigo criado', { timeout: 20_000 });

    await page.getByPlaceholder('Nome, código, SKU ou código de barras').fill(nome);
    await expect(page.getByRole('cell', { name: new RegExp(nome) }).first()).toBeVisible({
        timeout: 20_000,
    });
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

test('o ecra Livewire dos produtos continua na morada de sempre', async ({ page }) => {
    await page.goto('/invoicing/products');

    await expect(page.locator('[data-ecra]')).toHaveCount(0);
});
