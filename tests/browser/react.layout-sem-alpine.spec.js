import { expect, test } from '@playwright/test';

import { entrar } from './apoio.js';

/**
 * O LAYOUT SEM ALPINE, SEM JQUERY E SEM SCRIPTS SOLTOS, no browser.
 *
 * A barra do topo (língua, botão da barra lateral, suporte) era Alpine, e o
 * fim do layout eram `<script>` com jQuery e toastr (sessão expirada, barra de
 * progresso, service worker, PDF do ecrã). Passou tudo a peças React. O que se
 * prova aqui é que cada uma continua a fazer o que fazia.
 */

test('a barra do topo, o suporte, os avisos, a sessao e a mudanca de pagina', async ({ page }) => {
    const erros = [];
    // O 419 é o que o próprio ensaio simula no keep-alive.
    page.on('console', (m) => { if (m.type() === 'error' && !/status of 419/.test(m.text())) erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));
    page.on('response', (r) => { if (r.status() >= 500) erros.push(`${r.status()} ${r.url()}`); });

    await entrar(page);
    await page.goto('/home');

    expect(await page.evaluate(() => ({ alpine: typeof window.Alpine, jquery: typeof window.jQuery, toastr: typeof window.toastr, livewire: typeof window.Livewire })))
        .toEqual({ alpine: 'undefined', jquery: 'undefined', toastr: 'undefined', livewire: 'undefined' });

    // O PDF do ecrã fica ligado sem o ficheiro antigo.
    await page.waitForFunction(() => !!window.PdfDoDocumento, null, { timeout: 30_000 });

    // A língua abre, e as ligações são a mesma página com ?lang=.
    await page.getByTitle('Língua / Language / Langue').click();
    const ingles = page.getByRole('menuitem', { name: 'English' });
    await expect(ingles).toBeVisible();
    expect(new URL(await ingles.getAttribute('href')).searchParams.get('lang')).toBe('en');
    await page.keyboard.press('Escape');
    await expect(ingles).toBeHidden();

    // O botão do topo encolhe a barra lateral, e a escolha fica num cookie para o servidor.
    await page.setViewportSize({ width: 1440, height: 900 });
    const barra = page.locator('[data-peca="casca"] aside');
    await expect(barra).toBeVisible({ timeout: 20_000 });
    const larguraAntes = (await barra.boundingBox()).width;
    await page.getByRole('button', { name: /Encolher o menu|Abrir o menu/ }).click();
    await expect.poll(async () => (await barra.boundingBox()).width).not.toBe(larguraAntes);
    const cookie = (await page.context().cookies()).find((c) => c.name === 'casca_aberta');
    expect(cookie?.value).toBe(larguraAntes > 100 ? '0' : '1');
    await page.getByRole('button', { name: /Encolher o menu|Abrir o menu/ }).click();
    await expect.poll(async () => (await barra.boundingBox()).width).toBe(larguraAntes);

    // O suporte abre, troca de separador, fecha-se e volta.
    await page.getByRole('button', { name: 'Precisa de ajuda?' }).click();
    await expect(page.getByText('Centro de Suporte')).toBeVisible();
    await page.getByRole('tab', { name: /Melhorias/ }).click();
    await expect(page.getByRole('link', { name: /Sugerir Melhoria/ })).toBeVisible();
    await page.getByRole('button', { name: 'Precisa de ajuda?' }).hover();
    await page.getByRole('button', { name: 'Fechar o suporte' }).click();
    await expect(page.getByRole('button', { name: 'Mostrar o suporte' })).toBeVisible();
    expect(await page.evaluate(() => localStorage.getItem('suporte-escondido'))).toBe('1');
    await page.getByRole('button', { name: 'Mostrar o suporte' }).click();
    await expect(page.getByRole('button', { name: 'Precisa de ajuda?' })).toBeVisible();

    // Um aviso de canto, pelo evento que o gerador de PDF e o service worker usam.
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('casca:aviso', { detail: { texto: 'Conexão restaurada!', tipo: 'ok', titulo: 'Online', duracao: 4000 } })));
    await expect(page.getByRole('status').filter({ hasText: 'Conexão restaurada!' })).toBeVisible();
    await page.screenshot({ path: 'test-results/layout-sem-alpine.png' });

    // A sessão morta: o keep-alive responde 419 e aparece o aviso, sem recarregar às cegas.
    await page.route('**/keep-alive', (r) => r.fulfill({ status: 419, body: '' }));
    await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
    await expect(page.getByRole('alertdialog')).toContainText('Sessão expirada');
    await expect(page.getByRole('alertdialog').getByRole('link', { name: 'Iniciar sessão' })).toHaveAttribute('href', /\/login$/);
    await page.unroute('**/keep-alive');

    // A mudança de página pela barra lateral, com a barra de progresso a correr.
    await page.goto('/home');
    const grupo = page.locator('#sidebar-menu button[aria-expanded]').filter({ hasText: 'Facturação' });
    await expect(grupo).toBeVisible({ timeout: 20_000 });
    if (await grupo.getAttribute('aria-expanded') === 'false') await grupo.click();
    const ligacao = grupo.locator('xpath=following-sibling::div[1]').locator('a[href]').first();
    await expect(ligacao).toBeVisible();

    const destino = new URL(await ligacao.getAttribute('href'), page.url()).pathname;
    await ligacao.click();
    await expect.poll(() => page.evaluate(() => document.getElementById('spa-progress')?.style.width ?? '0%')).not.toBe('0%').catch(() => {});
    await page.waitForURL((u) => u.pathname === destino, { timeout: 30_000 });

    expect(erros).toEqual([]);
});
