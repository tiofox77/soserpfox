import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * AS DEFINIÇÕES DA FACTURAÇÃO EM REACT.
 *
 * O que se prova no browser: que abre com os separadores, que a forma vem
 * preenchida do servidor, que as séries aparecem com o prefixo do catálogo
 * e que o modal da série nova mostra o prefixo sem o deixar editar. Guardar
 * a sério prova-se nos ensaios de API, contra o mesmo serviço que a API
 * usa — a empresa de bancada é partilhada e não se mexe nas definições dela.
 */

const ECRA = '/invoicing/settings';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('tab', { name: /Padrões/ })).toBeVisible({ timeout: 20_000 });
});

test('abre nos padroes com a forma preenchida', async ({ page }) => {
    await expect(page.getByRole('tab', { name: /Padrões/ })).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByLabel(/^Moeda\b/)).toHaveValue(/AOA|USD|EUR/);
    await expect(page.getByRole('button', { name: /Guardar definições/ })).toBeVisible();
});

test('as series mostram o prefixo do catalogo', async ({ page }) => {
    await page.getByRole('tab', { name: /Documentos e séries/ }).click();

    await expect(page.getByText('Fatura de Venda')).toBeVisible();
    await expect(page.getByText('FT', { exact: true }).first()).toBeVisible();
    // A barra de guardar não faz sentido aqui: cada série grava-se por si.
    await expect(page.getByRole('button', { name: /Guardar definições/ })).toHaveCount(0);
});

test('a serie nova mostra o prefixo sem o deixar editar', async ({ page }) => {
    await page.getByRole('tab', { name: /Documentos e séries/ }).click();
    await page.getByRole('button', { name: /Nova série/ }).first().click();

    const prefixo = page.getByLabel(/^Prefixo\b/);
    await expect(prefixo).toBeVisible();
    await expect(prefixo).toHaveAttribute('readonly', '');
    await expect(page.getByLabel(/^Código\b/)).toBeVisible();

    await page.getByRole('button', { name: /^Cancelar$/ }).click();
    await expect(page.getByLabel(/^Código\b/)).toBeHidden();
});

test('os separadores trocam o conteudo', async ({ page }) => {
    await page.getByRole('tab', { name: /Ponto de venda/ }).click();
    await expect(page.getByText('Validar stock antes de vender')).toBeVisible();

    await page.getByRole('tab', { name: /PWA/ }).click();
    await expect(page.getByText('O menu do aparelho')).toBeVisible();
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByRole('tab', { name: /Padrões/ })).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

