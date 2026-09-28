import { test, expect } from '@playwright/test';
import { entrar, esperarMotor, esperarCatalogo, sincronizar, irPara, garantirTurno } from './apoio.js';

/**
 * A MÁQUINA DE VENDA PORTÁTIL — Sunmi V2s e semelhantes (28/09/2026).
 *
 * Ecrã de 5,99" a 720×1440 com densidade 2: o browser vê 360 px de largura.
 * A altura útil é o que sobra das barras do Android — 592 px no Chrome (com a
 * barra de endereço) e 648 px com o PWA instalado. É mais baixo do que
 * qualquer telemóvel dos outros ensaios (740–932), e é na altura que um ecrã
 * de balcão parte: o botão de fechar a venda fica abaixo da dobra.
 *
 * Uma venda inteira, nos dois balcões (PWA e PDV), sem rolar de lado e com o
 * botão de cada passo à vista. Com FOTOS=<pasta> guarda uma fotografia de cada
 * passo.
 */

const V2S = [
    { nome: 'Chrome', w: 360, h: 592 },
    { nome: 'instalado', w: 360, h: 648 },
];

test.use({ deviceScaleFactor: 2, isMobile: true, hasTouch: true });

const foto = async (page, nome) => {
    if (process.env.FOTOS) {
        await page.screenshot({ path: `${process.env.FOTOS}/${nome}.png` });
    }
};

/**
 * O aviso dos cookies, a esta altura, tapa o «Entrar» — quem usa a máquina
 * responde-lhe uma vez. Aqui responde-se «Só os necessários».
 */
const entrarNaV2s = async (page) => {
    await page.goto('/login');
    const soNecessarios = page.getByRole('button', { name: 'Só os necessários' });
    if (await soNecessarios.isVisible().catch(() => false)) {
        await soNecessarios.click();
    }
    await entrar(page);
};

/** Nada rola de lado e nada alarga a viewport de layout. */
const semRolarDeLado = (page) => page.evaluate(() => ({
    rolaDeLado: document.documentElement.scrollWidth > window.innerWidth,
    larguraDeLayout: window.innerWidth,
}));

for (const v of V2S) {
    test(`PWA ${v.nome} (${v.w}x${v.h}): vender do catálogo ao recibo`, async ({ page }) => {
        await page.setViewportSize({ width: v.w, height: v.h });

        await entrarNaV2s(page);
        await irPara(page, '/invoicing/offline');
        await esperarMotor(page);
        await sincronizar(page);
        await esperarCatalogo(page, 3);
        await garantirTurno(page);

        await irPara(page, '/invoicing/offline/pos');
        await esperarMotor(page);

        const cartoes = page.locator('.grid button:visible');
        await cartoes.first().waitFor({ timeout: 30_000 });
        await foto(page, `pwa-${v.h}-1-catalogo`);

        expect(await semRolarDeLado(page)).toEqual({ rolaDeLado: false, larguraDeLayout: v.w });

        await cartoes.nth(0).click();
        await cartoes.nth(1).click();

        // A barra «ver carrinho» a flutuar no fundo.
        const verCarrinho = page.getByRole('button', { name: /carrinho/i }).filter({ visible: true }).last();
        await expect(verCarrinho).toBeInViewport();
        await foto(page, `pwa-${v.h}-2-dois-artigos`);
        await verCarrinho.click();

        const folha = page.getByRole('complementary', { name: 'Carrinho' }).filter({ visible: true });
        const finalizar = folha.getByRole('button', { name: /Finalizar Venda/ });
        await expect(finalizar).toBeVisible();
        await page.waitForTimeout(400); // a folha sobe com transição
        await foto(page, `pwa-${v.h}-3-carrinho`);

        // O «Finalizar Venda» tem de estar à vista sem rolar a folha.
        await expect(finalizar, 'o Finalizar Venda à vista na folha do carrinho').toBeInViewport({ ratio: 1 });

        await finalizar.click();

        const recibo = page.getByRole('dialog', { name: /Venda/ });
        await expect(recibo).toBeVisible({ timeout: 20_000 });
        await page.waitForTimeout(400);
        await foto(page, `pwa-${v.h}-4-recibo`);

        for (const nome of [/Imprimir/, /PDF/, /Nova Venda/]) {
            await expect(recibo.getByRole('button', { name: nome }), `${nome} no recibo`).toBeInViewport({ ratio: 1 });
        }

        await expect(recibo.getByRole('button', { name: '58 mm' })).toBeInViewport({ ratio: 1 });
        expect(await semRolarDeLado(page)).toEqual({ rolaDeLado: false, larguraDeLayout: v.w });
    });

    test(`PDV ${v.nome} (${v.w}x${v.h}): vender do catálogo ao talão`, async ({ page }) => {
        await page.setViewportSize({ width: v.w, height: v.h });

        await entrarNaV2s(page);
        await page.goto('/invoicing/pos');

        const procura = page.getByPlaceholder(/código de barras/);
        const semTurno = page.getByText(/Não há turno de caixa aberto/);
        await procura.or(semTurno).first().waitFor({ state: 'visible', timeout: 25_000 });
        test.skip(await semTurno.isVisible(), 'sem turno aberto — corre primeiro o do PWA');

        const juntar = page.getByRole('button', { name: /^Juntar .+, / });
        await juntar.first().waitFor({ timeout: 20_000 });
        await foto(page, `pdv-${v.h}-1-catalogo`);

        expect(await semRolarDeLado(page)).toEqual({ rolaDeLado: false, larguraDeLayout: v.w });

        await juntar.nth(0).click();
        await juntar.nth(1).click();

        const barra = page.getByRole('button', { name: /^\s*Finalizar\s*$/ });
        await expect(barra, 'o Finalizar na barra de baixo').toBeInViewport({ ratio: 1 });
        await foto(page, `pdv-${v.h}-2-dois-artigos`);

        await page.getByRole('button', { name: 'Ver o carrinho' }).click();
        const folha = page.getByRole('dialog', { name: 'Carrinho' });
        await expect(folha).toBeVisible();
        await page.waitForTimeout(400);
        await foto(page, `pdv-${v.h}-3-carrinho`);
        await expect(folha.getByRole('button', { name: /Finalizar Venda/ })).toBeInViewport({ ratio: 1 });

        await folha.getByRole('button', { name: /Finalizar Venda/ }).click();

        const modal = page.getByRole('dialog').filter({ hasText: 'Finalizar Pagamento' });
        await expect(modal).toBeVisible({ timeout: 10_000 });
        await page.waitForTimeout(300);
        await foto(page, `pdv-${v.h}-4-pagamento`);

        const confirmar = modal.getByRole('button', { name: /Confirmar Venda/ });
        await expect(confirmar, 'o Confirmar Venda à vista sem rolar').toBeInViewport({ ratio: 1 });
        await confirmar.click();

        await expect(page.getByText(/Venda registada/)).toBeVisible({ timeout: 25_000 });
        await page.waitForTimeout(800);
        await foto(page, `pdv-${v.h}-5-talao`);

        const talao = page.getByRole('dialog').filter({ hasText: /Venda registada/ });
        await expect(talao.getByRole('button', { name: /Talão 58 mm/ })).toBeInViewport({ ratio: 1 });
        await expect(talao.getByRole('button', { name: /Imprimir/ }).first()).toBeInViewport({ ratio: 1 });
        expect(await semRolarDeLado(page)).toEqual({ rolaDeLado: false, larguraDeLayout: v.w });
    });
}
