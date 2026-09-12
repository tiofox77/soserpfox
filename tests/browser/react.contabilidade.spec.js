import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * A CONTABILIDADE — os três ecrãs, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Contabilidade`): o
 * lançamento a zeros, a linha com débito e crédito, a conta de agregação, a
 * data fora do período, o rascunho preso, o confirmado que não se apaga e o
 * estorno. O que aqui se prova é o que só se vê no browser:
 *
 *  · que as três moradas abrem sem um erro na consola;
 *  · que o painel abre com SALDOS em cima, e não com contagens de contas —
 *    «Total Ativo: 42» eram quarenta e duas contas, e não respondia a nada;
 *  · que o plano de contas se lê como uma ÁRVORE e que a conta de agregação e
 *    a bloqueada se distinguem de relance;
 *  · que a RAZÃO da conta abre ali, com saldo de abertura — não havia ecrã
 *    nenhum onde ver o extracto de uma conta;
 *  · e que o equilíbrio do lançamento se vê ENQUANTO SE ESCREVE, em vez de
 *    aparecer como recusa depois de o formulário estar todo preenchido.
 */

const MORADAS = [
    ['/accounting/dashboard', 'Dashboard Contabilidade'],
    ['/accounting/accounts', 'Plano de Contas'],
    ['/accounting/moves', 'Lançamentos Contabilísticos'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test.describe('as moradas da contabilidade', () => {
    for (const [morada, titulo] of MORADAS) {
        test(`abre ${morada}`, async ({ page }) => {
            const erros = [];

            page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
            page.on('pageerror', (e) => erros.push(String(e)));

            const resposta = await page.goto(morada);

            expect(resposta?.status(), `${morada} respondeu ${resposta?.status()}`).toBeLessThan(400);

            await expect(
                page.locator('.ecra-react').getByRole('heading', { name: titulo }).first(),
            ).toBeVisible({ timeout: 20_000 });

            expect(erros, `consola de ${morada}`).toEqual([]);
        });
    }
});

/* ─── O painel ──────────────────────────────────────────────────────── */

test.describe('o painel da contabilidade', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accounting/dashboard');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Dashboard Contabilidade' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * OS SALDOS ESTÃO EM CIMA, e o RESULTADO tem lugar próprio.
     *
     * O painel antigo abria com quatro contagens de contas. O resultado do
     * período — proveitos menos gastos, o número que se procura primeiro — não
     * estava em lado nenhum.
     */
    test('abre com os saldos e com o resultado do período', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        for (const natureza of ['Activo', 'Passivo', 'Proveitos', 'Gastos']) {
            await expect(ecra.getByText(natureza, { exact: true }).first()).toBeVisible();
        }

        await expect(ecra.getByText(/Resultado do período/)).toBeVisible();
        await expect(ecra.getByText(/Um rascunho é uma intenção/)).toBeVisible();
    });

    /**
     * AS CONTAGENS DO PLANO ficam mais abaixo, onde servem — e incluem as duas
     * que explicam por que é que uma conta não aparece ao lançar.
     */
    test('as contagens do plano dizem quantas contas não recebem movimento', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByRole('heading', { name: 'O plano de contas' })).toBeVisible();
        await expect(ecra.getByText('Somam as filhas')).toBeVisible();
        await expect(ecra.getByText('Não recebem lançamentos')).toBeVisible();
    });

    /** A bancada tem rascunhos: o aviso leva-os à lista. */
    test('avisa que há rascunhos por confirmar', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByText(/em rascunho — não entram em saldo nenhum/)).toBeVisible();
        await expect(ecra.getByRole('link', { name: /Ver os rascunhos/ })).toBeVisible();
    });
});

/* ─── O plano de contas ─────────────────────────────────────────────── */

test.describe('o plano de contas', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accounting/accounts');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Plano de Contas' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /** A de agregação e a bloqueada distinguem-se sem abrir nada. */
    test('a conta de agregação e a bloqueada lêem-se de relance', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByText('Disponibilidades', { exact: true }).first()).toBeVisible();
        await expect(ecra.getByText('Agregação', { exact: true }).first()).toBeVisible();

        // A «Conta encerrada» da bancada está bloqueada.
        const bloqueada = ecra.locator('tr', { hasText: 'Conta encerrada' });

        await expect(bloqueada.getByText('Bloqueada')).toBeVisible();
    });

    /**
     * A RAZÃO DA CONTA — o extracto que não existia em ecrã nenhum.
     *
     * Começa no SALDO DE ABERTURA: sem ele, o saldo final não bate com nada.
     */
    test('a razão da conta abre com saldo de abertura e acumulado', async ({ page }) => {
        const ecra = page.locator('.ecra-react');
        const caixa = ecra.locator('tr', { hasText: 'Caixa' }).first();

        await caixa.getByRole('button', { name: /Ver a razão/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela).toBeVisible();
        await expect(janela.getByText('Saldo de abertura').first()).toBeVisible();
        await expect(janela.getByText('Saldo final')).toBeVisible();
        await expect(janela.getByText(/Só entram lançamentos confirmados/)).toBeVisible();
    });

    /**
     * O NÍVEL NÃO SE ESCREVE: sai da mãe, e a janela diz de onde vem.
     *
     * Era um campo livre — e um nível que não concorda com a mãe desalinha a
     * árvore inteira nos relatórios.
     */
    test('o nível da conta sai da mãe e não se escreve à mão', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: 'Nova Conta' }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByRole('heading', { name: 'Nova Conta' })).toBeVisible();
        await expect(janela.getByText('raiz da árvore', { exact: true })).toBeVisible();

        // Escolher uma mãe de agregação faz o nível subir sozinho.
        await janela.getByLabel('Conta Pai').selectOption({ index: 1 });

        await expect(janela.getByText('sai da conta-mãe', { exact: true })).toBeVisible();

        // E as três abas do formulário de sempre continuam lá.
        for (const aba of ['Dados Gerais', 'Avançado', 'Configurações']) {
            await expect(janela.getByRole('tab', { name: aba })).toBeVisible();
        }
    });

    /** Apagar uma conta com movimento avisa ANTES de o servidor recusar. */
    test('apagar uma conta com movimento avisa que se bloqueia em vez de apagar', async ({ page }) => {
        const ecra = page.locator('.ecra-react');
        const caixa = ecra.locator('tr', { hasText: 'Caixa' }).first();

        await caixa.getByRole('button', { name: /Eliminar/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText(/Bloqueie-a em vez de a apagar/)).toBeVisible();
    });
});

/* ─── Os lançamentos ────────────────────────────────────────────────── */

test.describe('os lançamentos', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accounting/moves');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Lançamentos Contabilísticos' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * OS RASCUNHOS PRESOS num período fechado.
     *
     * Ficavam para sempre a parecer trabalho por acabar, e ninguém sabia
     * porquê: já não se podem confirmar.
     */
    test('avisa dos rascunhos presos em períodos fechados', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByText(/em períodos já fechados: não se podem confirmar/)).toBeVisible();

        // E a linha presa não oferece o botão de confirmar.
        const preso = ecra.locator('tr', { hasText: 'DIV-00005' });

        await expect(preso).toBeVisible();
        await expect(preso.getByRole('button', { name: /^Confirmar/ })).toHaveCount(0);
    });

    /** Um confirmado não tem botão de apagar — tem de estornar. */
    test('um confirmado estorna-se, não se apaga', async ({ page }) => {
        const ecra = page.locator('.ecra-react');
        const confirmado = ecra.locator('tr', { hasText: 'DIV-00003' });

        await expect(confirmado.getByRole('button', { name: /^Eliminar/ })).toHaveCount(0);
        await confirmado.getByRole('button', { name: /^Estornar/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText(/O original FICA/)).toBeVisible();
    });

    /**
     * O EQUILÍBRIO VÊ-SE ENQUANTO SE ESCREVE.
     *
     * O ecrã antigo somava ao gravar e devolvia «não está equilibrado» com o
     * formulário todo preenchido — e não dizia por quanto.
     */
    test('o equilíbrio aparece enquanto se escreve, e o botão diz o que falta', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: 'Novo Lançamento' }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByRole('heading', { name: 'Novo Lançamento' })).toBeVisible();

        // Um lançamento todo a zeros não é um lançamento, e diz-se.
        await expect(janela.getByText(/um lançamento todo a zeros não é um lançamento/)).toBeVisible();
        await expect(janela.getByRole('button', { name: 'Preencha os valores' })).toBeDisabled();

        // Só um lado preenchido: a diferença aparece, com o valor.
        await janela.getByLabel('Débito da linha 1').fill('1000');

        await expect(janela.getByText(/Diferença de/)).toBeVisible();

        // E ao fechar a conta, fica equilibrado.
        await janela.getByLabel('Crédito da linha 2').fill('1000');

        await expect(janela.getByText('Equilibrado')).toBeVisible();
        await expect(janela.getByRole('button', { name: 'Guardar em rascunho' })).toBeEnabled();
    });

    /**
     * DÉBITO E CRÉDITO NA MESMA LINHA não existe: escrever num limpa o outro.
     *
     * Era possível montar a linha e levar com a recusa no fim.
     */
    test('escrever no débito limpa o crédito da mesma linha', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: 'Novo Lançamento' }).click();

        const janela = page.locator('dialog[open]');
        const debito = janela.getByLabel('Débito da linha 1');
        const credito = janela.getByLabel('Crédito da linha 1');

        await credito.fill('500');
        await debito.fill('800');

        await expect(credito).toHaveValue('');
        await expect(debito).toHaveValue('800');
    });

    /** A referência vem do diário, e o campo diz porque é que pode ficar vazio. */
    test('a referência pode ficar vazia e o diário dá a seguinte', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: 'Novo Lançamento' }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText(/Deixe vazio para o diário dar a seguinte/)).toBeVisible();
        await expect(janela.getByLabel('Referência')).toHaveValue('');
    });
});
