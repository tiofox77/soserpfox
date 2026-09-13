import { expect, test } from '@playwright/test';

/**
 * A CARTA PÚBLICA DO RESTAURANTE EM REACT, no browser — sem sessão nenhuma.
 *
 * A bancada (`bancada:pwa`) publica a carta em /menu/bancada-pwa com o WhatsApp
 * ligado. Abre-se pelo QR da MESA-1, procura-se, escolhe-se, e a barra do pedido
 * leva a mensagem com a mesa à cabeça. As escolhas sobrevivem a um recarregar.
 */

test('a carta abre sem sessao, escolhe-se e o whatsapp leva a mesa', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.context().clearCookies();
    await page.goto('/menu/bancada-pwa/MESA-1');

    await expect(page.getByRole('heading', { level: 1 })).toBeVisible({ timeout: 30_000 });
    await expect(page.getByText(/Mesa/).first()).toBeVisible();

    await page.waitForTimeout(600);
    await page.screenshot({ path: 'test-results/carta-online-topo.png' });

    const adicionar = page.getByRole('button', { name: /^Adicionar / });
    await expect(adicionar.first()).toBeVisible();
    const total = await adicionar.count();
    expect(total).toBeGreaterThan(0);

    await adicionar.first().click();
    await adicionar.first().click();
    await expect(page.getByText(/2 artigos/)).toBeVisible();

    const whatsapp = page.getByRole('link', { name: /Enviar pedido por WhatsApp/ });
    await expect(whatsapp).toBeVisible();
    const href = await whatsapp.getAttribute('href');
    const texto = decodeURIComponent(href.split('?text=')[1]);
    expect(texto.split('\n')[1]).toMatch(/Mesa/);
    expect(texto).toMatch(/^Pedido por /);
    expect(texto).toMatch(/2x /);

    await page.waitForTimeout(500);
    await page.screenshot({ path: 'test-results/carta-online.png', fullPage: false });

    // Um recarregar sem querer não esvazia o pedido.
    await page.reload();
    await expect(page.getByText(/2 artigos/)).toBeVisible({ timeout: 30_000 });

    // A pesquisa filtra sem ir ao servidor.
    await page.getByLabel('Procurar prato').fill('zzzz-nada-disto');
    await expect(page.getByText('Nenhum prato encontrado')).toBeVisible();
    await page.getByLabel('Procurar prato').fill('');

    await page.getByRole('button', { name: 'Limpar', exact: true }).last().click();
    await expect(whatsapp).toBeHidden();

    expect(erros).toEqual([]);
});

test('uma carta que nao existe da 404', async ({ page }) => {
    const r = await page.goto('/menu/carta-que-nao-existe-de-todo');
    expect(r.status()).toBe(404);
});
