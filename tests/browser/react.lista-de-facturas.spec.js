import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * A LISTA DE FACTURAS EM REACT, NO BROWSER A SÉRIO.
 *
 * Os ensaios em Vitest provam o componente contra respostas inventadas. Este
 * prova a outra metade, que nenhum deles cobre: que o pacote carrega, que o
 * React encontra o ponto de montagem posto pelo Blade, e que fala com a API
 * verdadeira com a sessão verdadeira.
 *
 * Corre contra a empresa de bancada (`php artisan bancada:pwa`), que é onde as
 * credenciais são fixas e conhecidas de propósito.
 */

const ECRA = '/invoicing/sales/invoices/novo-ecra';

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test('o React monta-se dentro do layout de sempre', async ({ page }) => {
    await page.goto(ECRA);

    // O menu e o cabeçalho continuam a ser desenhados pelo Laravel: é isso que
    // permite migrar um ecrã de cada vez.
    await expect(page.locator('[data-ecra="facturacao/lista-de-facturas"]')).toBeVisible();

    // E o React tomou conta dele — o esqueleto do Blade deu lugar à tabela.
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('columnheader', { name: 'Número' })).toBeVisible();
});

test('mostra facturas verdadeiras vindas da API', async ({ page }) => {
    await page.goto(ECRA);

    const linhas = page.locator('tbody tr');

    await expect(linhas.first()).toBeVisible({ timeout: 20_000 });
    expect(await linhas.count()).toBeGreaterThan(0);

    // O total escreve-se à maneira daqui: milhares separados e vírgula decimal.
    await expect(page.locator('tbody tr').first()).toContainText(/\d{1,3}([  .]\d{3})*,\d{2}/);
});

test('procurar não recarrega a página', async ({ page }) => {
    await page.goto(ECRA);
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    // Marca-se a página. Se houver navegação, a marca desaparece — e é isso
    // que distingue um ecrã React de um Livewire a trocar o corpo todo.
    await page.evaluate(() => { window.__marca = 'aqui'; });

    await page.getByPlaceholder('Número, série ou cliente').fill('FT');
    await page.waitForResponse((r) => r.url().includes('procura=FT'), { timeout: 20_000 });

    expect(await page.evaluate(() => window.__marca)).toBe('aqui');
});

test('os filtros pedem ao servidor e a tabela responde', async ({ page }) => {
    await page.goto(ECRA);
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    const pedido = page.waitForResponse(
        (r) => r.url().includes('/sales-invoices') && r.url().includes('estado=draft'),
        { timeout: 20_000 },
    );

    await page.getByLabel('Estado').selectOption('draft');

    const resposta = await pedido;

    expect(resposta.ok()).toBe(true);
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];

    page.on('console', (m) => {
        if (m.type() === 'error') erros.push(m.text());
    });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto(ECRA);
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

/**
 * E O ECRÃ DE SEMPRE CONTINUA LÁ.
 *
 * Enquanto a migração durar, a morada antiga não pode deixar de funcionar por
 * um segundo — é o que permite voltar atrás sem publicar nada.
 */
test('a lista Livewire continua a abrir na morada de sempre', async ({ page }) => {
    await page.goto('/invoicing/sales/invoices');

    await expect(page.locator('[data-ecra]')).toHaveCount(0);
    await expect(page.getByText('Lista de Faturas de Venda')).toBeVisible();
});
