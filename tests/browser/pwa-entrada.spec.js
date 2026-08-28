import { test, expect } from '@playwright/test';
import { entrar, esperarMotor, esperarCatalogo, sincronizar, irPara, avaliar } from './apoio.js';

/**
 * A entrada do PWA, e os TRÊS estados que ela tem de distinguir.
 *
 * O defeito que isto fecha custou a encontrar porque as duas camadas diziam
 * coisas opostas sobre o mesmo facto:
 *
 *   1. a sessão do Laravel expira — basta o aparelho ficar parado;
 *   2. o ping devolve 401 e o motor contava isso como "sem rede": o ecrã de
 *      entrada anunciava "Sem ligação — entrada local" com o telemóvel cheio
 *      de sinal;
 *   3. o operador punha o PIN, que CONFERE — é um desbloqueio local e não sabe
 *      nada da sessão do servidor;
 *   4. a aplicação saltava para o POS, o pedido ia pela rede que existe, o
 *      `auth` respondia com um desvio para /login, e o operador aterrava na
 *      entrada normal.
 *
 * Resultado visto de fora: "ponho o PIN e não entra; se puser errado, diz que
 * está errado". O PIN funcionava; o que não funcionava era o destino.
 *
 * Uma sessão morta não é falta de ligação. Com rede, quem resolve é a
 * palavra-passe — e é isso que o ecrã tem de oferecer.
 */

/** Deixa o aparelho preparado: catálogo sincronizado e funcionários com PIN. */
async function aparelhoPreparado(page) {
    await entrar(page);
    await irPara(page, '/invoicing/offline');
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 5);
}

/** O que o ecrã de entrada está a mostrar. */
async function ecraDeEntrada(page) {
    return avaliar(page, () => {
        const alpine = window.Alpine?.$data?.(document.querySelector('[x-data]'));
        const visivel = (s) => !!document.querySelector(s)?.offsetParent;

        return {
            estado: alpine?.estado ?? null,
            formPalavraPasse: visivel('input[name="password"]'),
            formPin: visivel('input[inputmode="numeric"]'),
        };
    });
}

test.describe('PWA — entrada', () => {
    /**
     * O ENSAIO QUE FECHA O DEFEITO. Sessão morta COM rede tem de levar à
     * palavra-passe, nunca ao PIN: o PIN não cria sessão, e com rede o pedido
     * seguinte vai ao servidor e volta para trás.
     */
    test('sessão expirada com rede pede a palavra-passe, não o PIN', async ({ page, context }) => {
        await aparelhoPreparado(page);

        // Matar a sessão do servidor sem tocar no que está no aparelho: é
        // exactamente o que acontece quando a sessão caduca sozinha.
        await context.clearCookies();

        await irPara(page, '/invoicing/offline/login');
        await esperarMotor(page);

        await expect
            .poll(async () => (await ecraDeEntrada(page)).estado, { timeout: 20_000 })
            .toBe('sessao_expirada');

        const ecra = await ecraDeEntrada(page);

        expect(ecra.formPalavraPasse, 'com rede, quem resolve é a palavra-passe').toBe(true);
        expect(ecra.formPin, 'o PIN não cria sessão — oferecê-lo aqui é um beco').toBe(false);
    });

    /** Sem rede nenhuma, o PIN é o caminho — e continua a ser oferecido. */
    test('sem rede, a entrada é pelo PIN', async ({ page, context }) => {
        await aparelhoPreparado(page);

        await context.setOffline(true);

        await irPara(page, '/invoicing/offline/login');
        await esperarMotor(page);

        await expect
            .poll(async () => (await ecraDeEntrada(page)).estado, { timeout: 20_000 })
            .toBe('offline');

        const ecra = await ecraDeEntrada(page);

        expect(ecra.formPin, 'sem rede tem de haver entrada local').toBe(true);
        expect(ecra.formPalavraPasse, 'o formulário do servidor não serve sem servidor').toBe(false);
    });

    /**
     * A QUEIXA, INTEIRA: o PIN tem de LEVAR a algum lado.
     *
     * O PIN conferia — um PIN errado dizia logo que estava errado — e depois a
     * aplicação não saía do sítio. Verificar só que o PIN é aceite deixava
     * passar isto: o que falhava era o destino, não a verificação.
     */
    test('sem rede, o PIN leva mesmo ao POS', async ({ page, context }) => {
        await aparelhoPreparado(page);

        // O ecrã de entrada tem de estar guardado ANTES de faltar a rede — é
        // isso que o precache do service worker garante.
        await irPara(page, '/invoicing/offline/login');
        await esperarMotor(page);

        await context.setOffline(true);
        await irPara(page, '/invoicing/offline/login');
        await esperarMotor(page);

        const entrou = await avaliar(page, async () => {
            const r = await window.SosPwa.verifyOfflineAuth('bancada@pwa.local', '4321');

            return { ok: r.ok, motivo: r.reason || null, destrancado: window.SosPwa.isPwaUnlocked() };
        });

        expect(entrou.ok, 'o PIN da bancada tem de conferir').toBe(true);
        expect(entrou.destrancado).toBe(true);

        // E agora o que faltava: chegar lá.
        await irPara(page, '/invoicing/offline/pos');

        await expect(page.locator('body'), 'não pode aterrar na entrada normal')
            .not.toContainText('Palavra-passe');
        await expect(page.locator('body')).not.toContainText('Sem Conexão à Internet');

        await esperarMotor(page);

        expect(
            await avaliar(page, () => window.SosPwa.db.products.count()),
            'tem de ser mesmo o POS, com catálogo'
        ).toBeGreaterThanOrEqual(5);
    });

    /** Com sessão viva, entra-se como sempre. */
    test('com sessão viva, o estado é online', async ({ page }) => {
        await aparelhoPreparado(page);

        await irPara(page, '/invoicing/offline/login');
        await esperarMotor(page);

        await expect
            .poll(async () => (await ecraDeEntrada(page)).estado, { timeout: 20_000 })
            .toBe('online');
    });

    /**
     * O motor tem de saber distinguir os três — é dele que o ecrã depende.
     * O `checkRealOnline()` continua a responder sim ou não, que é o que a
     * sincronização precisa: sem sessão não se sincroniza, tal como sem rede.
     */
    test('o motor distingue sessão morta de falta de rede', async ({ page, context }) => {
        await aparelhoPreparado(page);

        // Perguntar em vez de esperar um número de milissegundos: limpar as
        // bolachas não tem efeito instantâneo no pedido seguinte, e uma
        // afirmação seca passava duas vezes em três. Um ensaio que falha às
        // vezes é pior do que um que falha sempre.
        const estado = () => avaliar(page, () => window.SosPwa.estadoDaLigacao());

        await expect.poll(estado, { timeout: 15_000 }).toBe('online');

        await context.clearCookies();

        await expect
            .poll(estado, { message: 'uma sessão morta não é falta de rede', timeout: 15_000 })
            .toBe('sessao_expirada');

        expect(
            await avaliar(page, () => window.SosPwa.checkRealOnline()),
            'mas continua a não dar para sincronizar'
        ).toBe(false);

        await context.setOffline(true);

        await expect.poll(estado, { timeout: 15_000 }).toBe('offline');
    });
});
