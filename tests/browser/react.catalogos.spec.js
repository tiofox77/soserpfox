import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS SEIS CATÁLOGOS EM REACT, num ecrã só.
 *
 * O que se prova no browser: que cada um abre com a sua tabela e o seu
 * botão de criar, que o formulário se desenha do esquema, e que a morada de
 * sempre serve o React. Gravar a sério prova-se nos ensaios de API;
 * aqui cria-se e apaga-se uma marca, para não deixar rasto na bancada.
 */

const CATALOGOS = [
    { rota: '/invoicing/suppliers', titulo: 'Fornecedores', campo: /^Nome\b/ },
    { rota: '/invoicing/categories', titulo: 'Categorias', campo: /^Nome\b/ },
    { rota: '/invoicing/brands', titulo: 'Marcas', campo: /^Nome\b/ },
    { rota: '/invoicing/warehouses', titulo: 'Armazéns', campo: /^Localização\b/ },
    { rota: '/invoicing/payment-terms', titulo: 'Condições de Pagamento', campo: /^Dias até ao vencimento\b/ },
    { rota: '/invoicing/taxes', titulo: 'Impostos', campo: /^Código SAFT\b/ },
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

for (const c of CATALOGOS) {
    test(`abre: ${c.titulo}`, async ({ page }) => {
        await page.goto(c.rota);
        await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByRole('heading', { name: c.titulo }).first()).toBeVisible();

        await page.getByRole('button', { name: /^Novo\(a\)/ }).click();
        await expect(page.getByLabel(c.campo)).toBeVisible();
        await page.getByRole('button', { name: /^Cancelar$/ }).click();
        await expect(page.getByLabel(c.campo)).toBeHidden();
    });
}

test('cria uma marca, ve-a na lista e apaga-a', async ({ page }) => {
    const nome = 'Marca ensaio ' + Date.now();

    await page.goto('/invoicing/brands');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /^Novo\(a\)/ }).click();
    await page.getByLabel(/^Nome\b/).fill(nome);
    await page.getByRole('button', { name: /^Guardar$/ }).click();

    await expect(page.getByRole('status')).toContainText('criad', { timeout: 20_000 });
    await page.getByPlaceholder(/Nome ou descrição/).fill(nome);
    await expect(page.getByRole('cell', { name: nome, exact: true })).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: `Apagar: ${nome}` }).click();
    await page.getByRole('button', { name: /^Apagar$/ }).click();
    await expect(page.getByRole('status')).toContainText('apagad', { timeout: 20_000 });
});

test('o erro de validacao aparece no campo', async ({ page }) => {
    await page.goto('/invoicing/warehouses');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /^Novo\(a\)/ }).click();
    await page.getByRole('button', { name: /^Guardar$/ }).click();

    await expect(page.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto('/invoicing/taxes');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});
