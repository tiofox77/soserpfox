import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS ORÇAMENTOS, O IMOBILIZADO e as FAMÍLIAS, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Contabilidade`). O que aqui
 * se prova é o que só se vê no browser:
 *
 *  · que as três moradas abrem sem um erro na consola;
 *  · que o orçamento mostra o REALIZADO ao lado do previsto — a comparação que
 *    faltava por completo, e que é o ponto inteiro de um orçamento;
 *  · que o total do formulário sai da soma dos meses e não se escreve;
 *  · que o imobilizado LISTA o bem da bancada, com os totais contados: o ecrã
 *    antigo mostrava um paginador vazio e quatro zeros literais;
 *  · e que «Calcular amortizações» diz o que faz — calcular não é lançar.
 */

const MORADAS = [
    ['/accounting/budgets', 'Orçamentos'],
    ['/accounting/fixed-assets', 'Imobilizado'],
    ['/accounting/fixed-asset-categories', 'Famílias do Imobilizado'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test.describe('as moradas dos orçamentos e do imobilizado', () => {
    for (const [morada, titulo] of MORADAS) {
        test(`abre ${morada}`, async ({ page }) => {
            const erros = [];

            page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
            page.on('pageerror', (e) => erros.push(String(e)));

            const resposta = await page.goto(morada);

            expect(resposta?.status(), `${morada} respondeu ${resposta?.status()}`).toBeLessThan(400);

            await expect(
                page.locator('.ecra-react').getByRole('heading', { name: titulo }).first(),
            ).toBeVisible({ timeout: 20_000 });

            expect(erros, `consola de ${morada}`).toEqual([]);
        });
    }
});

/* ─── Os orçamentos ─────────────────────────────────────────────────── */

test.describe('os orçamentos', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accounting/budgets');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Orçamentos' }).first())
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * O REALIZADO AO LADO DO PREVISTO — a comparação que faltava.
     *
     * O ecrã antigo gravava doze números e mostrava-os outra vez.
     */
    test('mostra o realizado, o desvio e a execução', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        for (const coluna of ['Previsto', 'Realizado', 'Desvio', 'Execução']) {
            await expect(ecra.getByRole('columnheader', { name: coluna })).toBeVisible();
        }

        const linha = ecra.locator('tr', { hasText: 'Compras de mercadorias' });

        await expect(linha).toBeVisible();
        await expect(linha.getByText('Aprovado')).toBeVisible();
    });

    /** O mês a mês abre com as duas séries — previsto e real. */
    test('o mês a mês mostra as duas séries', async ({ page }) => {
        const ecra = page.locator('.ecra-react');
        const linha = ecra.locator('tr', { hasText: 'Compras de mercadorias' });

        await linha.getByRole('button', { name: /Ver .* mês a mês/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText('Realizado dentro do previsto')).toBeVisible();
        await expect(janela.getByText('Realizado acima do previsto')).toBeVisible();
        // E a tabela dos doze meses.
        await expect(janela.getByRole('cell', { name: 'Janeiro' })).toBeVisible();
        await expect(janela.getByRole('cell', { name: 'Dezembro' })).toBeVisible();
    });

    /** O TOTAL SAI DA SOMA, e o formulário diz que não se escreve. */
    test('o total do formulário sai da soma dos meses', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Novo Orçamento/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText(/O total sai da soma dos meses/)).toBeVisible();

        await janela.getByLabel('Previsto de Janeiro').fill('1000');
        await janela.getByLabel('Previsto de Fevereiro').fill('500');

        // 1.500,00 aparece sem ninguém o escrever.
        await expect(janela.getByText(/1[^0-9]?500,00/).first()).toBeVisible();
    });

    /** Espalhar um total pelos doze meses: é o que se faz em nove casos em dez. */
    test('espalhar um total preenche os doze meses', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Novo Orçamento/ }).click();

        const janela = page.locator('dialog[open]');

        await janela.getByLabel('Total a espalhar pelos doze meses').fill('1200');
        await janela.getByLabel('Total a espalhar pelos doze meses').blur();

        await expect(janela.getByLabel('Previsto de Janeiro')).toHaveValue('100');
        await expect(janela.getByLabel('Previsto de Dezembro')).toHaveValue('100');
    });
});

/* ─── O imobilizado ─────────────────────────────────────────────────── */

test.describe('o imobilizado', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accounting/fixed-assets');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Imobilizado' }).first())
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * O BEM APARECE, e os totais são contados.
     *
     * O ecrã antigo mostrava um paginador VAZIO construído à mão e quatro zeros
     * literais — e o gravar não gravava nada.
     */
    test('lista o bem da bancada com os totais contados', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.locator('tr', { hasText: 'Toyota Hilux da Bancada' })).toBeVisible();
        await expect(ecra.getByText(/1[^0-9]?200[^0-9]?000,00/).first()).toBeVisible();
        await expect(ecra.getByText('É o que entra no balanço')).toBeVisible();
    });

    /** CALCULAR NÃO É LANÇAR, e a janela diz-o. */
    test('a janela de calcular diz que calcular não é lançar', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Calcular amortizações/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText(/As linhas nascem em RASCUNHO/)).toBeVisible();
        await expect(janela.getByText(/Uma amortização já LANÇADA nunca é tocada/)).toBeVisible();
        await expect(janela.getByText(/Nunca abaixo do valor residual/)).toBeVisible();
    });

    /** A ficha do bem diz por onde a amortização se lança. */
    test('a ficha do bem mostra as três contas e o que falta amortizar', async ({ page }) => {
        const ecra = page.locator('.ecra-react');
        const linha = ecra.locator('tr', { hasText: 'Toyota Hilux da Bancada' });

        await linha.getByRole('button', { name: /Ver as amortizações/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText('Conta do bem')).toBeVisible();
        await expect(janela.getByText('Conta de gasto')).toBeVisible();
        await expect(janela.getByText('Amortizações acumuladas').first()).toBeVisible();
        await expect(janela.getByText('Por amortizar')).toBeVisible();
    });

    /** E o formulário mostra a conta da amortização enquanto se escreve. */
    test('o formulário do bem diz quanto vai amortizar por mês', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Novo Bem/ }).click();

        const janela = page.locator('dialog[open]');

        await janela.getByLabel('Valor de aquisição (Kz)').fill('1200000');
        await janela.getByLabel('Vida útil (anos)').fill('5');

        await expect(janela.getByText(/60 meses/)).toBeVisible();
        // O separador dos milhares em pt-PT é um espaço, não um ponto.
        await expect(janela.getByText(/20[^0-9]?000,00 Kz por mês/)).toBeVisible();

        // E escolher a família traz as omissões dela.
        await janela.getByLabel('Família').selectOption({ label: 'Viaturas' });

        await expect(janela.getByLabel('Vida útil (anos)')).toHaveValue('5');
    });
});
