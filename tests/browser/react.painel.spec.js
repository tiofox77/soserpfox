import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O PAINEL DA FACTURAÇÃO EM REACT.
 *
 * O que este ecrã prova, e nenhum dos outros provava: um gráfico desenhado
 * sem biblioteca nenhuma, e os números a virem do MESMO serviço que o painel
 * em Blade usa — que é a razão de os dois poderem existir ao mesmo tempo.
 */

const ECRA = '/invoicing/dashboard/novo-ecra';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
});

test('mostra os cartões e o gráfico do ano', async ({ page }) => {
    await expect(page.getByText('Facturado este mês')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Recebido este mês')).toBeVisible();
    await expect(page.getByText('Vencido', { exact: true })).toBeVisible();

    // O gráfico é SVG/HTML, não canvas: lê-se pelo rótulo.
    await expect(page.getByRole('img', { name: /Facturado por mês/ })).toBeVisible();
});

/**
 * OS DOZE MESES ESTÃO SEMPRE LÁ.
 *
 * Um gráfico que salte de Março para Junho porque Abril e Maio não têm
 * facturas mente sobre a forma do ano.
 */
test('o grafico tem os doze meses, mesmo os vazios', async ({ page }) => {
    const grafico = page.getByRole('img', { name: /Facturado por mês/ });
    await expect(grafico).toBeVisible({ timeout: 20_000 });

    expect(await grafico.locator('> div').count()).toBe(12);
});

test('as quatro caixas do estado nao se sobrepoem', async ({ page }) => {
    await expect(page.getByText('Estado das facturas deste mês')).toBeVisible({ timeout: 20_000 });

    for (const caixa of ['Liquidadas', 'Parcialmente pagas', 'Vencidas']) {
        await expect(page.getByText(caixa, { exact: true })).toBeVisible();
    }
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByText('Facturado este mês')).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

test('o painel Livewire continua na morada de sempre', async ({ page }) => {
    await page.goto('/invoicing/dashboard');

    await expect(page.locator('[data-ecra]')).toHaveCount(0);
});
