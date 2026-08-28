import { test, expect } from '@playwright/test';
import { entrar, esperarMotor, esperarCatalogo, sincronizar, irPara, avaliar } from './apoio.js';

/**
 * O ecrã inicial do PWA.
 *
 * É o primeiro que o empregado vê de manhã, e estava a servir duas plateias ao
 * mesmo tempo: quem vem vender e quem vem resolver um problema. Sete botões
 * técnicos abertos — incluindo "Reset Total — apaga TUDO incl. pendentes" — no
 * meio do caminho de quem só quer abrir o POS.
 *
 * As ferramentas continuam todas cá. O que estes ensaios fixam é a ORDEM das
 * coisas: à superfície fica o que se usa para vender; o resto abre-se numa
 * gaveta. E o que apaga dados fica atrás dessa gaveta, sempre.
 */

async function inicio(page) {
    await entrar(page);
    await irPara(page, '/invoicing/offline');
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 5);
    await irPara(page, '/invoicing/offline');
    await esperarMotor(page);

    // O banner de instalação tapa o fundo do ecrã e não faz parte do que se
    // mede aqui.
    await avaliar(page, () => {
        document.getElementById('pwa-install-banner')?.remove();
        document.querySelectorAll('.fixed.bottom-20').forEach((e) => e.remove());
    });
}

/** O que está mesmo à vista, sem abrir gavetas nem rolar. */
async function visivelDeImediato(page) {
    return avaliar(page, () => {
        const dentroDeGaveta = (e) => !!e.closest('details:not([open])');

        return [...document.querySelectorAll('button, a')]
            .filter((e) => e.offsetParent !== null && !dentroDeGaveta(e))
            .map((e) => (e.textContent || '').replace(/\s+/g, ' ').trim())
            .filter(Boolean);
    });
}

test.describe('PWA — ecrã inicial', () => {
    /**
     * O ENSAIO QUE IMPORTA. "Apagar tudo" leva as vendas por sincronizar com
     * ele. Um dedo enganado no ecrã inicial não pode chegar lá.
     */
    test('o que apaga dados não está à vista', async ({ page }) => {
        await inicio(page);

        const visiveis = (await visivelDeImediato(page)).join(' | ').toLowerCase();

        expect(visiveis).not.toContain('apagar tudo');
        expect(visiveis).not.toContain('limpar catálogo');
        expect(visiveis).not.toContain('reset');
    });

    /** Nem as ferramentas de suporte — diagnóstico, sincronizações forçadas. */
    test('as ferramentas de suporte ficam na gaveta', async ({ page }) => {
        await inicio(page);

        const visiveis = (await visivelDeImediato(page)).join(' | ').toLowerCase();

        expect(visiveis).not.toContain('diagnosticar');
        expect(visiveis).not.toContain('sincronização completa');
    });

    /**
     * Mas continuam TODAS lá: esconder não é apagar. Quem vem resolver um
     * problema abre a gaveta e encontra tudo o que encontrava antes.
     */
    test('a gaveta tem as ferramentas todas', async ({ page }) => {
        await inicio(page);

        const naGaveta = await avaliar(page, () => {
            const gaveta = document.querySelector('details');

            if (!gaveta) { return null; }

            gaveta.open = true;

            return [...gaveta.querySelectorAll('button')]
                .map((b) => (b.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase())
                .join(' | ');
        });

        expect(naGaveta, 'tem de haver uma gaveta de ferramentas').not.toBeNull();
        expect(naGaveta).toContain('sincronização completa');
        expect(naGaveta).toContain('guardar cópia');
        expect(naGaveta).toContain('diagnosticar');
        expect(naGaveta).toContain('limpar catálogo');
        expect(naGaveta).toContain('apagar tudo');
    });

    /** O que se usa para vender está à vista, e sem ter de rolar. */
    test('vender está à vista', async ({ page }) => {
        await inicio(page);

        const acima = await avaliar(page, () => {
            const alvo = [...document.querySelectorAll('a')]
                .find((a) => /invoicing\/offline\/pos$/.test(a.getAttribute('href') || '')
                    && (a.textContent || '').toLowerCase().includes('vender'));

            if (!alvo) { return null; }

            return alvo.getBoundingClientRect().top < window.innerHeight;
        });

        expect(acima, 'o atalho de vender tem de existir').not.toBeNull();
        expect(acima, 'e tem de caber no primeiro ecrã').toBe(true);
    });

    /**
     * O cabeçalho não pode partir-se em duas linhas e chocar com os botões —
     * era o que acontecia com o número da versão, a data e o "sync agora"
     * todos na mesma linha.
     */
    test('o cabeçalho cabe numa linha', async ({ page }) => {
        await page.setViewportSize({ width: 360, height: 800 });
        await inicio(page);

        const m = await avaliar(page, () => {
            const cabecalho = document.querySelector('header');
            const versao = cabecalho?.querySelectorAll('p')[1];

            if (!versao) { return null; }

            const linha = parseFloat(getComputedStyle(versao).lineHeight) || 16;

            return {
                alturaDaVersao: versao.getBoundingClientRect().height,
                umaLinha: linha * 1.6,
                scrollHorizontal: document.documentElement.scrollWidth > window.innerWidth,
            };
        });

        expect(m, 'o cabeçalho tem de ter a linha da versão').not.toBeNull();
        expect(m.alturaDaVersao, 'a versão não pode partir para uma segunda linha')
            .toBeLessThan(m.umaLinha);
        expect(m.scrollHorizontal, 'nunca pode haver scroll horizontal').toBe(false);
    });
});
