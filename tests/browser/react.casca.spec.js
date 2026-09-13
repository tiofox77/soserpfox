import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * A CASCA EM REACT — a única barra lateral.
 *
 * A de Blade saiu. A prova que importa passou a ser esta: a barra desenha
 * TODAS as ligações que a página lhe entrega (o MenuDaCasca, que o ensaio
 * `MenuDaCascaFielTest` compara com a gravação do menu de sempre). Depois, que
 * abre e fecha, que encolhe pelo botão da barra do topo, que se lembra de onde
 * se estava a ler, e que não deixa a página saltar enquanto chega.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

/** Todas as moradas do menu nas props da casca, pela ordem. */
const moradasDasProps = (page) => page.evaluate(() => {
    const props = JSON.parse(document.querySelector('[data-peca="casca"]').dataset.props);
    const fora = [];
    const ligacao = (e) => e && e.url && fora.push(e.url);
    const entradas = (lista) => lista.forEach((e) => {
        if (e.sub) entradas(e.sub.entradas);
        else ligacao(e);
    });
    props.menu.principal.forEach(ligacao);
    props.menu.grupos.forEach((g) => { if (g.simples) ligacao(g); entradas(g.entradas); });
    props.menu.superadmin.forEach((s) => s.entradas.forEach(ligacao));
    return fora.map((u) => u.replace(window.location.origin, '')).sort();
});

test('a barra desenha todas as ligacoes do menu', async ({ page }) => {
    await page.goto('/invoicing/dashboard');
    await expect(page.locator('[data-peca="casca"] #sidebar-menu')).toBeVisible({ timeout: 20_000 });

    const esperadas = await moradasDasProps(page);
    expect(esperadas.length).toBeGreaterThan(20);

    // Fechados, os grupos continuam no DOM (a dobra só anima a altura).
    const desenhadas = await page.evaluate(() =>
        Array.from(document.querySelectorAll('#sidebar-menu a[href]'))
            .map((a) => a.getAttribute('href').replace(window.location.origin, ''))
            .sort(),
    );

    expect(desenhadas).toEqual(esperadas);
});

test('a pagina activa acende no menu', async ({ page }) => {
    await page.goto('/invoicing/clients');
    await expect(page.locator('[data-peca="casca"] #sidebar-menu')).toBeVisible({ timeout: 20_000 });

    const activa = page.locator('#sidebar-menu a[aria-current="page"]');
    await expect(activa).toHaveCount(1);
    await expect(activa).toHaveAttribute('href', /\/invoicing\/clients$/);
});

test('um grupo abre e fecha, e fechado nao se entra nele', async ({ page }) => {
    await page.goto('/home');
    const grupo = page.locator('#sidebar-menu button[aria-expanded]').first();
    await expect(grupo).toBeVisible({ timeout: 20_000 });

    const antes = await grupo.getAttribute('aria-expanded');
    await grupo.click();
    await expect(grupo).toHaveAttribute('aria-expanded', antes === 'true' ? 'false' : 'true');

    const dobra = grupo.locator('xpath=following-sibling::div[1]');
    if (antes === 'true') {
        await expect(dobra).toHaveAttribute('inert', '');
    } else {
        await expect(dobra).not.toHaveAttribute('inert', '');
    }
});

test('o botao da barra do topo encolhe a barra lateral e a seta acompanha', async ({ page }) => {
    await page.goto('/invoicing/dashboard');
    const barra = page.locator('aside#app-sidebar');
    await expect(barra).toHaveAttribute('data-aberta', '1', { timeout: 20_000 });

    const botao = page.locator('header button:visible').first();
    await botao.click();
    await expect(barra).toHaveAttribute('data-aberta', '0');
    await expect(botao.locator('i')).toHaveClass(/fa-chevron-right/);

    // E lembra-se na página seguinte.
    await page.goto('/invoicing/clients');
    await expect(page.locator('aside#app-sidebar')).toHaveAttribute('data-aberta', '0', { timeout: 20_000 });
    await page.locator('header button:visible').first().click();
    await expect(page.locator('aside#app-sidebar')).toHaveAttribute('data-aberta', '1');
});

test('o lugar da barra fica reservado enquanto o react nao chega', async ({ page }) => {
    await page.route('**/react/*.js', async (rota) => {
        await new Promise((r) => setTimeout(r, 1500));
        await rota.continue();
    });

    await page.goto('/invoicing/dashboard', { waitUntil: 'domcontentloaded' });
    const lugar = page.locator('[data-peca="casca"]');
    const largura = await lugar.evaluate((el) => el.getBoundingClientRect().width);
    expect(largura).toBeGreaterThanOrEqual(80);

    await expect(page.locator('aside#app-sidebar')).toBeVisible({ timeout: 20_000 });
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto('/invoicing/dashboard');
    await expect(page.locator('[data-peca="casca"] #sidebar-menu')).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});
