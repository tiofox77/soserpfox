import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

const ECRA = '/invoicing/settings/notification-gateways';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('heading', { name: 'Gateways de Notificação' })).toBeVisible({ timeout: 20_000 });
});

test('abre em React com os tres canais e sem revelar segredos', async ({ page }) => {
    await expect(page.locator('[data-ecra="facturacao/gateways-de-notificacao"]')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Email' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'SMS' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'WhatsApp' })).toBeVisible();
    const segredos = await page.locator('input[type="password"]').evaluateAll((els) => els.map((e) => e.value));
    expect(segredos.every((valor) => valor === '')).toBe(true);
    await expect(page.getByRole('button', { name: /Guardar configurações/ })).toBeVisible();
});

test('troca os canais e preserva todos os campos essenciais', async ({ page }) => {
    await page.getByRole('button', { name: 'Email' }).click();
    await expect(page.getByLabel(/Servidor SMTP/)).toBeVisible();
    await expect(page.getByLabel(/Email remetente/)).toBeVisible();

    await page.getByRole('button', { name: 'WhatsApp' }).click();
    await expect(page.getByLabel(/Business Account ID/)).toBeVisible();
    await expect(page.getByText('Quando notificar')).toBeVisible();
});

test('a tabela fecha e a pagina nao transborda no telemovel', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Gateways de Notificação' })).toBeVisible();

    const medidas = await page.evaluate(() => ({ largura: document.documentElement.clientWidth, conteudo: document.documentElement.scrollWidth }));
    expect(medidas.conteudo).toBeLessThanOrEqual(medidas.largura);

    const tabela = page.locator('[data-ecra] table').first();
    await expect(tabela).toBeVisible();
    expect(await tabela.locator('thead th').count()).toBe(await tabela.locator('tbody tr').first().locator('td').count());
});

test('nao dispara fornecedores externos durante a verificacao visual', async ({ page }) => {
    const externas = [];
    page.on('request', (r) => {
        if (/telcosms|d7networks|twilio|whatsapp/i.test(r.url())) externas.push(r.url());
    });

    await page.getByRole('button', { name: 'Visão geral' }).click();
    await page.getByRole('button', { name: 'SMS', exact: true }).click();
    await page.getByRole('button', { name: 'Email', exact: true }).click();
    expect(externas).toEqual([]);
});
