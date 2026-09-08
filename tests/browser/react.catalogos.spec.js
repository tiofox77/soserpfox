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

        await page.getByRole('button', { name: /^Nov[oa] / }).click();
        await expect(page.getByLabel(c.campo)).toBeVisible();
        await page.getByRole('button', { name: /^Cancelar$/ }).click();
        await expect(page.getByLabel(c.campo)).toBeHidden();
    });
}

/**
 * O ÍCONE ESCOLHE-SE DE UMA GALERIA, e não escrevendo o código.
 *
 * Era uma caixa de texto onde se escrevia `fa-money-bill` e se esperava pelo
 * melhor: quem não sabe o Font Awesome de cor não tinha por onde começar, e
 * um código mal escrito não dá erro — dá um quadrado vazio na lista, que só
 * se descobre depois de gravado.
 *
 * Prova-se aqui o que só um browser prova: que a galeria abre DENTRO do
 * formulário (não noutra janela, que roubaria o Escape à de fora), que a
 * procura é em português, e que escolher escreve o código certo no campo.
 */
test('o icone escolhe-se de uma galeria, com procura em portugues', async ({ page }) => {
    await page.goto('/invoicing/categories');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /^Nova Categoria$/ }).click();

    const abrir = page.getByRole('button', { name: /^Ícone: / });

    await expect(abrir).toBeVisible();
    await abrir.click();

    // A PROCURA É PELO NOME, não pelo código: quem quer um café escreve
    // «café», não `mug-hot`.
    await page.getByLabel('Procurar ícone').fill('café');

    const encontrado = page.getByRole('button', { name: 'café', exact: true });

    await expect(encontrado).toBeVisible({ timeout: 10_000 });
    await encontrado.click();

    // E o campo fica com o código certo, com a galeria fechada por trás.
    await expect(page.getByRole('button', { name: 'Ícone: fa-mug-hot' })).toBeVisible();
    await expect(page.getByLabel('Procurar ícone')).toBeHidden();

    await page.getByRole('button', { name: /^Cancelar$/ }).click();
});

test('cria uma marca, ve-a na lista e apaga-a', async ({ page }) => {
    const nome = 'Marca ensaio ' + Date.now();

    await page.goto('/invoicing/brands');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /^Nov[oa] / }).click();
    await page.getByLabel(/^Nome\b/).fill(nome);
    await page.getByRole('button', { name: /^Guardar$/ }).click();

    await expect(page.getByRole('status')).toContainText('criad', { timeout: 20_000 });
    await page.getByPlaceholder(/Nome ou descrição/).fill(nome);
    await expect(page.getByRole('cell', { name: nome, exact: true })).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: `Apagar: ${nome}` }).click();
    await page.getByRole('button', { name: /^Apagar$/ }).click();
    await expect(page.getByRole('status')).toContainText('apagad', { timeout: 20_000 });
});

/**
 * A FICHA DO FORNECEDOR — o modal de VER, com o extrato.
 *
 * É o que se olha antes de negociar um preço: quanto já lhe comprámos, quanto
 * se lhe deve, o que mais lhe compramos. Só os fornecedores o têm — uma marca
 * não tem extrato nenhum, e uma janela que abre vazia faz acreditar que não se
 * comprou nada.
 */
test('so o fornecedor tem ficha de ver, e ela traz o extrato', async ({ page }) => {
    await page.goto('/invoicing/brands');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    // Nas marcas o botão nem existe.
    await expect(page.locator('tbody [aria-label^="Ver:"]')).toHaveCount(0);

    await page.goto('/invoicing/suppliers');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    await page.locator('tbody [aria-label^="Ver:"]').first().click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });
    await expect(janela.getByText('Identificação')).toBeVisible();

    const pedido = page.waitForResponse(
        (r) => r.url().includes('/extrato') && r.request().method() === 'GET',
        { timeout: 20_000 },
    );

    await janela.getByRole('tab', { name: /Extrato/ }).click();
    expect((await pedido).ok()).toBe(true);

    // Do lado do fornecedor a palavra é COMPRADO, e não facturado.
    await expect(janela.getByText('Comprado')).toBeVisible({ timeout: 20_000 });
});

test('o erro de validacao aparece no campo', async ({ page }) => {
    await page.goto('/invoicing/warehouses');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /^Nov[oa] / }).click();
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
