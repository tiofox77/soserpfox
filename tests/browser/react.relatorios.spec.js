import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS RELATÓRIOS EM REACT.
 *
 * O que se prova no browser: que a porta abre com as secções; que um mapa
 * abre no ecrã genérico com os cartões, a tabela e o CSV; que os gráficos se
 * desenham; e que as moradas de sempre continuam em Livewire.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test('a porta abre com as seccoes e leva ao mapa de vendas', async ({ page }) => {
    await page.goto('/invoicing/reports/novo-ecra');
    if (!(await page.locator('[data-ecra]').count())) {
        test.skip(true, 'sem permissão para os relatórios nesta bancada');
    }
    await expect(page.locator('[data-hub]')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('heading', { name: 'Rentabilidade & Análise' })).toBeVisible();
    await expect(page.locator('[data-mapa]')).toHaveCount(23);

    await page.locator('[data-mapa="sales"]').click();
    await expect(page.locator('[data-relatorio="sales"]')).toBeVisible({ timeout: 20_000 });
    await expect(page.locator('[data-cartoes]').getByText('Documentos', { exact: true })).toBeVisible();
    await expect(page.locator('[data-relatorio="sales"]').getByRole('table')).toBeVisible();
    await expect(page.locator('[data-csv]')).toHaveAttribute('href', /novo-ecra\/csv/);

    // Mudar o período volta a pedir os números.
    await page.getByLabel(/^Período/).selectOption('year');
    await expect(page.locator('[data-relatorio="sales"]')).toBeVisible();
});

test('os mapas sem periodo e com duas tabelas abrem', async ({ page }) => {
    await page.goto('/invoicing/reports/vat/novo-ecra');
    if (!(await page.locator('[data-ecra]').count())) {
        test.skip(true, 'sem permissão para os relatórios nesta bancada');
    }
    await expect(page.locator('[data-relatorio="vat"]')).toBeVisible({ timeout: 20_000 });
    await expect(page.locator('[data-relatorio="vat"]').getByRole('table')).toHaveCount(2);

    await page.goto('/invoicing/reports/aging-clients/novo-ecra');
    await expect(page.locator('[data-relatorio="aging-clients"]')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByLabel(/^Período/)).toHaveCount(0);
});

test('os graficos desenham-se', async ({ page }) => {
    await page.goto('/invoicing/reports/charts/novo-ecra');
    if (!(await page.locator('[data-ecra]').count())) {
        test.skip(true, 'sem permissão para os relatórios nesta bancada');
    }
    await expect(page.locator('[data-relatorio="charts"]')).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('[data-graficos]')).toBeVisible();
    await expect(page.locator('[data-cartoes]').getByText('Ticket médio', { exact: true })).toBeVisible();
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    for (const m of ['/invoicing/reports/novo-ecra', '/invoicing/reports/sales/novo-ecra', '/invoicing/reports/account-statement/novo-ecra', '/invoicing/expiry-report/novo-ecra']) {
        await page.goto(m);
        await page.waitForLoadState('networkidle');
    }

    expect(erros).toEqual([]);
});

test('as moradas de sempre continuam em Livewire', async ({ page }) => {
    for (const m of ['/invoicing/reports', '/invoicing/reports/sales', '/invoicing/expiry-report']) {
        await page.goto(m);
        await expect(page.locator('[data-ecra]')).toHaveCount(0);
    }
});
