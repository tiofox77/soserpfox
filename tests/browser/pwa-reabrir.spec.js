import { test, expect } from '@playwright/test';
import { entrar, esperarServiceWorker, esperarMotor, esperarCatalogo, sincronizar, irPara, avaliar } from './apoio.js';

/**
 * FECHAR A APLICAÇÃO E VOLTAR A ABRIR, sem rede.
 *
 * A queixa, palavra por palavra: "enquanto o utilizador tiver com a app aberta
 * funciona; se fechar e voltar a abrir pede PIN, e colocando não entra".
 *
 * Fechar a aplicação apaga o `sessionStorage` — e é lá que vive o desbloqueio
 * do PWA (`pwa_unlocked`). Por isso volta a pedir o PIN: isso está certo. O que
 * não pode é o PIN não levar a lado nenhum.
 *
 * Um separador novo no MESMO contexto reproduz isto ao certo: o
 * `sessionStorage` nasce vazio, mas o IndexedDB, os caches e o service worker
 * continuam lá — exactamente como num telemóvel onde se fecha a aplicação.
 *
 * A rota de arranque importa: o `start_url` fica gravado no aparelho no dia da
 * instalação. Os PWA instalados antes do redesenho arrancam em `/dashboard`,
 * que é um REDIRECT — e sem servidor não há quem redireccione. É por isso que
 * os dois arranques são ensaiados.
 */

async function aparelhoPreparado(page) {
    await entrar(page);
    await irPara(page, '/invoicing/offline');
    await esperarServiceWorker(page);
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 5);

    // Passar pelo POS COM rede: é o que o deixa guardado para depois.
    await irPara(page, '/invoicing/offline/pos');
    await esperarMotor(page);
}

/** O que se vê depois de reabrir. */
async function ecra(page) {
    return avaliar(page, () => {
        const visivel = (s) => !!document.querySelector(s)?.offsetParent;

        return {
            titulo: document.title,
            caminho: location.pathname,
            pedePin: visivel('#pwa-offline-login-password') || visivel('input[inputmode="numeric"]'),
            temPos: !!document.querySelector('.grid button'),
            semLigacao: document.body.innerText.includes('Sem Conexão'),
        };
    });
}

/**
 * O CICLO FECHADO — a queixa, reproduzida.
 *
 * Um aparelho que instalou o service worker SEM sessão fica só com a entrada em
 * cache: as páginas da aplicação respondem 302 e o `guardarPagina`, e bem, não
 * guarda desvios. Mas a entrada estava na lista de recurso — logo, sem rede,
 * QUALQUER endereço do PWA devolvia a entrada.
 *
 * O operador via isto: põe o PIN, a aplicação salta para o POS, e aparece a
 * entrada outra vez. O endereço dizia POS e o conteúdo era a porta. Lido de
 * fora: "coloco o PIN e não entra".
 *
 * Uma página só pode substituir outra quando é DA APLICAÇÃO. Não havendo
 * nenhuma, diz-se que não há: faltar é melhor do que mentir.
 */
test('a entrada nunca é servida no lugar de outra página', async ({ page, context }) => {
    await aparelhoPreparado(page);
    await irPara(page, '/invoicing/offline/login');
    await esperarMotor(page);

    // Deixar só a entrada, como num aparelho que nunca teve sessão ao instalar.
    await avaliar(page, async () => {
        const c = await caches.open('dynamic-paginas');

        for (const k of await c.keys()) {
            if (new URL(k.url).pathname !== '/invoicing/offline/login') {
                await c.delete(k);
            }
        }
    });

    await context.setOffline(true);
    await page.close();

    const nova = await context.newPage();
    await nova.goto('/invoicing/offline/pos', { waitUntil: 'domcontentloaded' }).catch(() => {});
    await nova.waitForTimeout(1200);

    const noPos = await ecra(nova);

    expect(noPos.pedePin, 'o endereço do POS não pode devolver o ecrã de entrada').toBe(false);
    expect(noPos.semLigacao, 'sem nada guardado, diz-se a verdade').toBe(true);

    // Mas a entrada continua a servir-se a si própria.
    await nova.goto('/invoicing/offline/login', { waitUntil: 'domcontentloaded' }).catch(() => {});
    await nova.waitForTimeout(1200);

    expect((await ecra(nova)).pedePin, 'a entrada abre no seu próprio endereço').toBe(true);
});

for (const arranque of ['/invoicing/offline/pos', '/dashboard']) {
    test(`reabrir sem rede em ${arranque}: o PIN tem de deixar entrar`, async ({ page, context }) => {
        await aparelhoPreparado(page);

        // ---- FECHAR A APLICAÇÃO ----
        await page.close();

        // ---- O SERVIDOR DEIXA DE EXISTIR ----
        await context.setOffline(true);

        // ---- VOLTAR A ABRIR ----
        const nova = await context.newPage();
        await nova.goto(arranque, { waitUntil: 'domcontentloaded' }).catch(() => {});
        await nova.waitForTimeout(1500);

        const aoAbrir = await ecra(nova);

        expect(aoAbrir.semLigacao, 'reabrir sem rede não pode dar a página de "sem ligação"').toBe(false);

        await esperarMotor(nova);

        // O desbloqueio morreu com o separador — pedir o PIN outra vez está
        // certo. O que se mede é o que acontece A SEGUIR.
        expect(
            await avaliar(nova, () => window.SosPwa.isPwaUnlocked()),
            'fechar a aplicação apaga o desbloqueio deste separador'
        ).toBe(false);

        // ---- O PIN ----
        const entrou = await avaliar(nova, () =>
            window.SosPwa.verifyOfflineAuth('bancada@pwa.local', '4321')
        );

        expect(entrou.ok, 'o PIN tem de conferir sem rede').toBe(true);

        // ---- E TEM DE LEVAR AO POS ----
        //
        // É aqui que a queixa vive. O PIN sempre conferiu; o que falhava era
        // chegar a algum lado depois dele.
        await nova.goto('/invoicing/offline/pos', { waitUntil: 'domcontentloaded' }).catch(() => {});
        await nova.waitForTimeout(1500);

        const depois = await ecra(nova);

        expect(depois.semLigacao, 'depois do PIN não pode aparecer "sem ligação"').toBe(false);
        await esperarMotor(nova);

        expect(
            await avaliar(nova, () => window.SosPwa.db.products.count()),
            'tem de ser mesmo o POS, com catálogo'
        ).toBeGreaterThanOrEqual(5);
    });
}
