import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O PAINEL DO ADQUIRENTE EM REACT.
 *
 * O que se prova aqui: que o ecrã abre com o período do mês e a consola
 * limpa. Listar, confirmar ou rejeitar fala com a AGT a sério — nada disso
 * se prova numa bancada, e é de propósito que a lista só sai depois de
 * alguém carregar em «Listar facturas».
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test('as facturas recebidas abrem com o periodo do mes', async ({ page }) => {
    await page.goto('/invoicing/agt-adquirente');
    if (!(await page.locator('[data-ecra]').count())) {
        test.skip(true, 'sem permissão para a AGT nesta bancada');
    }

    await expect(page.locator('[data-ambiente]')).toBeVisible({ timeout: 30_000 });

    // O período nasce do primeiro dia do mês até hoje — é o servidor que o diz.
    const hoje = new Date();
    const mes = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}`;
    await expect(page.getByLabel('De')).toHaveValue(`${mes}-01`);
    await expect(page.getByLabel('Até')).not.toHaveValue('');

    // A tabela está de pé e vazia: abrir a página não bate à porta da AGT.
    await expect(page.locator('[data-facturas]')).toBeVisible();
    await expect(page.locator('[data-facturas] tbody tr')).toHaveCount(1);
    await expect(page.locator('[data-facturas]')).toContainText('Listar facturas');
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto('/invoicing/agt-adquirente');
    await page.waitForLoadState('networkidle');

    expect(erros).toEqual([]);
});
