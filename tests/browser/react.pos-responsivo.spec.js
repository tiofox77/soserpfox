import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O BALCÃO ONLINE DO MONITOR GRANDE AO TELEMÓVEL.
 *
 * O que partia antes de 2026-09-13, medido:
 *
 * - a 1024×768, com a barra lateral aberta, quatro colunas de artigos em
 *   300 px — cartões de 59 px — e o «Finalizar Venda» 11 px abaixo da janela;
 * - nos tablets em pé o «Finalizar» ficava fora do ecrã;
 * - no telemóvel o catálogo mostrava uma fila e o carrinho ficava espremido;
 * - a 2560 eram cinco cartões de 352 px.
 *
 * A regra: nunca rolar de lado, cartões tocáveis, e o fecho da venda sempre à
 * vista — ao lado, a partir de 768 px; na barra de baixo, no telemóvel.
 */

const TAMANHOS = [
    [2560, 1440], [1920, 1080], [1440, 900], [1366, 768], [1280, 720],
    [1024, 768], [820, 1180], [768, 1024], [412, 915], [390, 844], [360, 740],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

for (const [w, h] of TAMANHOS) {
    test(`${w}x${h}: nada rola de lado e o fecho da venda está à vista`, async ({ page }) => {
        await page.setViewportSize({ width: w, height: h });
        await page.goto('/invoicing/pos');

        const procura = page.getByPlaceholder(/código de barras/);
        const semTurno = page.getByText(/Não há turno de caixa aberto/);
        await procura.or(semTurno).first().waitFor({ state: 'visible', timeout: 25_000 });
        test.skip(await semTurno.isVisible(), 'sem turno aberto');

        const juntar = page.getByRole('button', { name: /^Juntar .+, / });
        await juntar.first().waitFor({ timeout: 20_000 });
        await juntar.first().click();

        const m = await page.evaluate(() => {
            const dentro = (b) => b && b.offsetParent !== null && (() => {
                const r = b.getBoundingClientRect();
                return r.top >= 0 && r.bottom <= window.innerHeight && r.left >= 0 && r.right <= window.innerWidth;
            })();
            const botoes = [...document.querySelectorAll('button')];
            const cartoes = [...document.querySelectorAll('#app-main button[aria-label^="Juntar"]')].filter((e) => e.offsetParent);

            return {
                rolaDeLado: document.documentElement.scrollWidth > window.innerWidth,
                larguraDeLayout: window.innerWidth,
                finalizarAoLado: dentro(botoes.find((b) => /Finalizar Venda/.test(b.textContent || ''))),
                finalizarNaBarra: dentro(botoes.find((b) => /^\s*Finalizar\s*$/.test(b.textContent || ''))),
                cartao: cartoes[0] ? Math.round(cartoes[0].getBoundingClientRect().width) : 0,
            };
        });

        expect(m.rolaDeLado, 'nunca pode haver scroll horizontal').toBe(false);
        expect(m.larguraDeLayout, 'nada pode alargar a viewport').toBe(w);
        expect(m.cartao, 'o cartão tem de continuar tocável').toBeGreaterThanOrEqual(120);

        if (w >= 768) {
            expect(m.finalizarAoLado, 'o «Finalizar Venda» ao lado, dentro da janela').toBe(true);
        } else {
            expect(m.finalizarNaBarra, 'no telemóvel, o «Finalizar» na barra de baixo').toBe(true);

            // E a folha do carrinho abre com o «Finalizar Venda» à vista.
            await page.getByRole('button', { name: 'Ver o carrinho' }).click();
            const folha = page.getByRole('dialog', { name: 'Carrinho' });
            await expect(folha).toBeVisible();
            await expect(folha.getByRole('button', { name: /Finalizar Venda/ })).toBeInViewport();
        }
    });
}
