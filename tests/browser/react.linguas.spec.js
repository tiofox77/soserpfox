import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS ECRÃS EM REACT FALAM AS TRÊS LÍNGUAS — no browser a sério.
 *
 * Os ensaios de PHP provam que a página anuncia o dicionário e que a porta o
 * serve; os de vitest provam o `t()`. O que só se prova aqui é o resto do
 * caminho: que o pacote vai buscar o dicionário ANTES de montar, e que o ecrã
 * aparece traduzido — sem passar por português à frente de quem está a olhar.
 *
 * A escolha da língua faz-se por `?lang=`, que é o que o menu do topo usa.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test.afterEach(async ({ page }) => {
    // A empresa de bancada é partilhada, e a língua fica guardada no perfil e
    // num cookie de um ano: deixá-la em inglês estragava os outros ensaios.
    await page.goto('/invoicing/sales/invoices?lang=pt');
    await expect(page.locator('[data-ecra]')).toBeVisible({ timeout: 20_000 });
});

test('em ingles o ecra monta ja traduzido', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto('/invoicing/sales/invoices?lang=en');

    // O dicionário é pedido ao servidor — uma vez, e antes de haver ecrã.
    await expect(page.locator('[data-ecra]')).toBeVisible({ timeout: 20_000 });
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    // «Filtros» → «Filters» e «Estado» → «Status»: duas frases que já existiam
    // no dicionário do Blade e que os ecrãs em React reaproveitam.
    await expect(page.getByText('Filters', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Status')).toBeVisible();

    // E o que não tem tradução sai em português, que é o que o Laravel faz —
    // nunca a chave crua nem um espaço em branco.
    await expect(page.locator('[data-ecra]')).not.toContainText(':atributo');

    expect(erros).toEqual([]);
});

test('em portugues nao se pede dicionario nenhum', async ({ page }) => {
    const pedidos = [];
    page.on('request', (r) => { if (r.url().includes('/react/traducoes/')) pedidos.push(r.url()); });

    await page.goto('/invoicing/sales/invoices?lang=pt');
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    await expect(page.getByText('Filtros', { exact: true })).toBeVisible();
    expect(pedidos).toEqual([]);
});
