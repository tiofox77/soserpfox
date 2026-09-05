import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * AS CINCO LISTAS QUE SÃO O MESMO ECRÃ.
 *
 * Proformas de venda e de compra, orçamentos, facturas de compra e recibos
 * saem todos do mesmo componente React. O que muda vem do servidor: o título,
 * o cabeçalho da coluna da outra parte, os estados que existem mesmo, e se há
 * saldo a mostrar.
 *
 * Se um deles abrir e outro não, o defeito é da configuração — e é isso que
 * este ficheiro apanha, correndo os cinco.
 */

const LISTAS = [
    { morada: '/invoicing/sales/proformas/novo-ecra', titulo: 'Proformas de Venda', parte: 'Cliente' },
    { morada: '/invoicing/sales/quotes/novo-ecra', titulo: 'Orçamentos', parte: 'Cliente' },
    { morada: '/invoicing/purchases/invoices/novo-ecra', titulo: 'Facturas de Compra', parte: 'Fornecedor' },
    { morada: '/invoicing/purchases/proformas/novo-ecra', titulo: 'Proformas de Compra', parte: 'Fornecedor' },
    { morada: '/invoicing/receipts/novo-ecra', titulo: 'Recibos', parte: 'Cliente' },
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

for (const lista of LISTAS) {
    test(`abre: ${lista.titulo}`, async ({ page }) => {
        const erros = [];
        page.on('console', (m) => {
            if (m.type() === 'error') erros.push(m.text());
        });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.goto(lista.morada);

        // Ou traz documentos, ou diz que não há — nunca fica em branco.
        await expect(
            page.getByRole('table').or(page.getByText('Nenhum documento com estes filtros')),
        ).toBeVisible({ timeout: 20_000 });

        // O cabeçalho da coluna sabe se é cliente ou fornecedor.
        const tabela = page.getByRole('table');
        if (await tabela.isVisible()) {
            await expect(page.getByRole('columnheader', { name: lista.parte })).toBeVisible();
            await expect(page.getByRole('columnheader', { name: 'Falta pagar' })).toHaveCount(
                lista.titulo === 'Facturas de Compra' ? 1 : 0,
            );
        }

        expect(erros).toEqual([]);
    });
}

/** E as cinco moradas de sempre continuam a abrir em Livewire. */
test('as moradas de sempre nao mexeram', async ({ page }) => {
    for (const morada of [
        '/invoicing/sales/proformas',
        '/invoicing/sales/quotes',
        '/invoicing/purchases/invoices',
        '/invoicing/purchases/proformas',
        '/invoicing/receipts',
    ]) {
        await page.goto(morada);
        await expect(page.locator('[data-ecra]')).toHaveCount(0);
    }
});
