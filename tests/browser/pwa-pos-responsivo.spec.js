import { test, expect } from '@playwright/test';
import { entrar, esperarMotor, esperarCatalogo, sincronizar, irPara, avaliar } from './apoio.js';

/**
 * O POS não pode cortar cartões, em tamanho nenhum.
 *
 * O DEFEITO QUE ISTO FECHA: a barra de pesquisa era `sticky top-14` também no
 * desktop, onde a coluna dos artigos já tem o seu próprio scroll — empurrava-se
 * 56px para baixo do lugar dela e tapava 44px do topo da primeira fila. E a
 * altura era `calc(100vh-116px)`, um número mágico: o cabeçalho tem 60 e a
 * barra de baixo 77, portanto os últimos 21px ficavam por baixo dela.
 *
 * Nenhum dos dois se via num ecrã só — por isso o ensaio varre tamanhos.
 *
 * Os elementos procuram-se pelo ESTILO CALCULADO e não pelo nome da classe:
 * uma classe do Tailwind com dois pontos (`lg:overflow-y-auto`) precisa de
 * escape no selector, e um ensaio que se parte por causa disso mede o escape,
 * não o produto.
 */

const TAMANHOS = [
    // 360px é o Android barato que se vê a sério no balcão, e é onde a barra
    // de topo saía do ecrã. Não estava aqui, e por isso o defeito passou.
    { nome: 'telemóvel pequeno', w: 360, h: 800 },
    { nome: 'telemóvel', w: 390, h: 844 },
    { nome: 'telemóvel grande', w: 430, h: 932 },
    { nome: 'tablet', w: 820, h: 1180 },
    { nome: 'portátil', w: 1366, h: 768 },
    { nome: 'ecrã grande', w: 1920, h: 1080 },
];

for (const t of TAMANHOS) {
    test(`${t.nome} (${t.w}x${t.h}): nada cortado, nada escondido`, async ({ page }) => {
        await page.setViewportSize({ width: t.w, height: t.h });

        await entrar(page);
        await irPara(page, '/invoicing/offline');
        await esperarMotor(page);
        await sincronizar(page);
        await esperarCatalogo(page, 5);

        await irPara(page, '/invoicing/offline/pos');
        await esperarMotor(page);

        // Esperar pelos CARTÕES desenhados, e não um número de milissegundos:
        // o ecrã só pinta a grelha depois de ler a base, e o tempo que isso
        // leva muda com o tamanho do ecrã e com o que a máquina está a fazer.
        // Uma espera fixa passa umas vezes e falha outras.
        await page.waitForFunction(
            () => {
                const cartoes = [...document.querySelectorAll('.grid button')]
                    .filter((e) => e.offsetParent !== null);

                return cartoes.length > 0 && cartoes[0].getBoundingClientRect().height > 50;
            },
            null,
            { timeout: 30_000 }
        );

        const m = await avaliar(page, () => {
            const visivel = (e) => !!(e && e.offsetParent !== null);
            const cartoes = [...document.querySelectorAll('.grid button')].filter(visivel);

            if (!cartoes.length) {
                return { semCartoes: true };
            }

            const porPosicao = (pos, extra = () => true) =>
                [...document.querySelectorAll('body *')]
                    .filter(visivel)
                    .filter((e) => getComputedStyle(e).position === pos && extra(e));

            // A barra de pesquisa: sticky/fixa, larga, e por cima da grelha.
            const barra = porPosicao('sticky', (e) => e.getBoundingClientRect().height > 40)[0]
                || porPosicao('fixed', (e) => e.getBoundingClientRect().top < 200 && e.getBoundingClientRect().height > 40)[0];

            // A navegação de baixo: fixa, encostada ao fundo.
            const nav = porPosicao('fixed', (e) => {
                const b = e.getBoundingClientRect();

                return b.height > 40 && b.bottom >= window.innerHeight - 2;
            })[0];

            // O contentor que rola: o antepassado do cartão com overflow auto.
            let rolante = cartoes[0].parentElement;
            while (rolante && !['auto', 'scroll'].includes(getComputedStyle(rolante).overflowY)) {
                rolante = rolante.parentElement;
            }

            const c1 = cartoes[0].getBoundingClientRect();

            return {
                semCartoes: false,
                cartoes: cartoes.length,
                tapadoPelaBarra: barra
                    ? Math.max(0, Math.round(barra.getBoundingClientRect().bottom - c1.top))
                    : 0,
                tapadoPelaNav: (rolante && nav)
                    ? Math.max(0, Math.round(rolante.getBoundingClientRect().bottom - nav.getBoundingClientRect().top))
                    : 0,
                scrollHorizontal: document.documentElement.scrollWidth > window.innerWidth,
                larguraDoCartao: Math.round(c1.width),

                // O QUE TRANSBORDA DENTRO DA BARRA NÃO FAZ A PÁGINA ROLAR.
                //
                // O `scrollHorizontal` aqui em cima mede o documento, e por
                // isso deixou passar o defeito que se via no telemóvel: a
                // linha da pesquisa transbordava DENTRO da barra, o armazém e
                // o turno ficavam cortados na margem, e a página continuava a
                // caber. Um ensaio que só olha para o documento diz que está
                // tudo bem enquanto o operador vê metade dos botões.
                //
                // Mede-se a barra por dentro...
                barraTransborda: barra ? barra.scrollWidth > barra.clientWidth + 1 : false,

                // ...e mede-se a VIEWPORT DE LAYOUT, que é o sinal fiável.
                //
                // Quando alguma coisa não cabe, o Chromium não deixa
                // simplesmente transbordar: ALARGA a viewport de layout para o
                // conteúdo caber. O `innerWidth` passa de 360 para 424, e a
                // partir daí nada está "fora da margem" — a margem é que
                // cresceu. Foi por isso que a primeira versão deste ensaio não
                // viu nada com o defeito à frente.
                //
                // No telemóvel isto é a página inteira a encolher para caber
                // uma barra que não cabia: tudo fica mais pequeno e as pontas
                // ficam cortadas. Se a viewport de layout não bate com a
                // janela, alguma coisa não coube.
                larguraDeLayout: window.innerWidth,
            };
        });

        expect(m.semCartoes, 'os artigos têm de aparecer').toBe(false);
        expect(m.tapadoPelaBarra, 'a barra de pesquisa não pode tapar os cartões').toBe(0);
        expect(m.tapadoPelaNav, 'a barra de navegação não pode tapar o fim da lista').toBe(0);
        expect(m.scrollHorizontal, 'nunca pode haver scroll horizontal').toBe(false);
        expect(m.barraTransborda, 'a barra de topo não pode transbordar por dentro').toBe(false);
        expect(m.larguraDeLayout, 'nada pode alargar a viewport: a página ficaria encolhida no telemóvel').toBe(t.w);
        // Um cartão demasiado estreito deixa de ser tocável com o dedo.
        expect(m.larguraDoCartao, 'o cartão tem de continuar tocável').toBeGreaterThanOrEqual(120);
    });
}
