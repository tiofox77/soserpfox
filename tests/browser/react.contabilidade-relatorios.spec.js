import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * A RECONCILIAÇÃO, OS RELATÓRIOS e as DEFINIÇÕES, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Contabilidade`). O que aqui
 * se prova é o que só se vê no browser:
 *
 *  · que as três moradas abrem sem um erro na consola;
 *  · que o extracto ABRE — o botão «Ver» da lista não tinha clique nenhum, e
 *    sem ele um extracto importado era um saco fechado;
 *  · que as linhas casadas e as que faltam se distinguem, e que uma linha por
 *    casar traz a sugestão com a confiança à frente;
 *  · que o balancete fecha e se descarrega;
 *  · que trocar de mapa NÃO calcula os outros (o ecrã antigo calculava o
 *    balancete inteiro em todas as visitas);
 *  · e que as definições dizem que sincronizar é incremental.
 */

const MORADAS = [
    ['/accounting/reconciliation', 'Reconciliação Bancária'],
    ['/accounting/reports', 'Relatórios da Contabilidade'],
    ['/accounting/settings', 'Definições da Contabilidade'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test.describe('as moradas dos relatórios e das definições', () => {
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

/* ─── A reconciliação ───────────────────────────────────────────────── */

test.describe('a reconciliação bancária', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accounting/reconciliation');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Reconciliação Bancária' }).first())
            .toBeVisible({ timeout: 20_000 });
    });

    /** A conciliação da bancada aparece, com o que falta casar de relance. */
    test('lista a conciliação com o que falta casar', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByText('1 por casar')).toBeVisible();
        await expect(ecra.getByText('Linhas do extracto sem lançamento')).toBeVisible();
    });

    /**
     * O EXTRACTO ABRE — e é o ecrã que não existia.
     *
     * O botão «Ver» da lista era um `<button>` sem `wire:click`: as linhas do
     * extracto não se viam, não se casavam à mão, e o casamento manual e as
     * sugestões — escritos e completos no serviço — não tinham quem os chamasse.
     */
    test('abre o extracto e distingue a linha casada da que falta', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Abrir o extracto/ }).first().click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText(/Calcular não é lançar|Depósito do caixa/).first()).toBeVisible();

        // A que casou sozinha traz a etiqueta de casada.
        const casada = janela.locator('li', { hasText: 'Depósito do caixa' }).first();

        await expect(casada.getByText('Desfazer')).toBeVisible();

        // E a despesa bancária, que ninguém lançou, continua por casar.
        const porCasar = janela.locator('li', { hasText: 'Despesas de manutenção de conta' });

        await expect(porCasar.getByText('Por casar')).toBeVisible();
        await expect(porCasar.getByText(/Nenhum lançamento parecido nos cinco dias em volta/)).toBeVisible();
    });

    /** E diz os três saldos: o do extracto, o contabilístico e a diferença. */
    test('o extracto mostra os três saldos', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Abrir o extracto/ }).first().click();

        const janela = page.locator('dialog[open]');

        for (const rotulo of ['Saldo do extracto', 'Saldo contabilístico', 'Diferença', 'Casadas']) {
            await expect(janela.getByText(rotulo, { exact: true })).toBeVisible();
        }
    });

    /** A importação explica o formato do CSV antes de alguém errar o ficheiro. */
    test('a janela de importar explica o CSV', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Importar extracto/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText(/quatro colunas: data, referência, descrição e valor/)).toBeVisible();
    });
});

/* ─── Os relatórios ─────────────────────────────────────────────────── */

test.describe('os relatórios', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accounting/reports');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Relatórios da Contabilidade' }).first())
            .toBeVisible({ timeout: 20_000 });
    });

    /** Os dez mapas, cada um a dizer o que responde. */
    test('mostra os dez mapas com a descrição de cada um', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        for (const nome of ['Balancete de Verificação', 'Balanço', 'Razão Geral', 'Mapa de IVA']) {
            await expect(ecra.getByRole('button', { name: new RegExp(nome) })).toBeVisible();
        }

        // A descrição aparece no cartão E na faixa (o mapa escolhido): a
        // primeira serve.
        await expect(ecra.getByText(/Todas as contas com movimento no período/).first()).toBeVisible();
    });

    /** O balancete abre por omissão, soma e fecha. */
    test('o balancete abre e fecha', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByRole('columnheader', { name: 'Débito' })).toBeVisible();
        await expect(ecra.getByRole('cell', { name: 'Caixa', exact: true })).toBeVisible();
        // Fecha: a coluna do saldo do rodapé traz um traço, não uma diferença.
        await expect(ecra.getByText(/O balancete não fecha/)).toHaveCount(0);
    });

    /**
     * SÓ OS FORMATOS QUE O MAPA TEM.
     *
     * Os dois botões apareciam em todos os mapas e respondiam «Tipo de
     * relatório não suporta exportação» depois do clique.
     */
    test('só mostra os formatos que aquele mapa descarrega', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        // O balancete só tem folha de cálculo.
        await expect(ecra.getByRole('link', { name: 'Folha de cálculo' })).toBeVisible();
        await expect(ecra.getByRole('link', { name: 'PDF' })).toHaveCount(0);

        // O balanço tem os dois.
        await ecra.getByRole('button', { name: /Balanço/ }).click();

        await expect(ecra.getByRole('link', { name: 'PDF' })).toBeVisible();
        await expect(ecra.getByRole('link', { name: 'Folha de cálculo' })).toBeVisible();
    });

    /** A razão pede a conta em vez de mostrar lixo. */
    test('a razão pede a conta', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Razão Geral/ }).click();

        await expect(ecra.getByText('Escolha uma conta')).toBeVisible();

        await ecra.getByLabel('Conta').selectOption({ index: 1 });

        // Na tabela, não na descrição do cartão — que também fala de «saldo de
        // abertura».
        await expect(ecra.getByRole('cell', { name: 'Saldo de abertura' })).toBeVisible();
    });

    /** E o mapa de IVA diz quando o plano não tem contas marcadas. */
    test('o mapa de IVA explica quando não há contas marcadas', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Mapa de IVA/ }).click();

        // A faixa muda primeiro; esperar por ela antes de olhar ao conteúdo.
        await expect(ecra.getByText(/IVA liquidado e dedutível, e o que se entrega/).first()).toBeVisible();

        /* Na bancada o plano é pequeno e não tem contas de IVA marcadas: o mapa
           di-lo em vez de mostrar três zeros. */
        await expect(
            ecra.getByText(/Sem contas de IVA marcadas no plano|A entregar ao Estado/).first(),
        ).toBeVisible({ timeout: 20_000 });
    });
});

/* ─── As definições ─────────────────────────────────────────────────── */

test.describe('as definições', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accounting/settings');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Definições da Contabilidade' }).first())
            .toBeVisible({ timeout: 20_000 });
    });

    /** SINCRONIZAR É INCREMENTAL, e o ecrã di-lo — o botão parecia destrutivo. */
    test('diz que sincronizar é incremental', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByText(/Cada botão ACRESCENTA o que falta/)).toBeVisible();
        await expect(ecra.getByText(/um período fechado não reabre/)).toBeVisible();
    });

    /** Os oito eventos da facturação, com o estado de cada mapeamento. */
    test('lista os eventos da integração', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByRole('cell', { name: 'Fatura de venda (FT/FR)' })).toBeVisible();
        await expect(ecra.getByRole('cell', { name: 'Recebimento em banco' })).toBeVisible();
        await expect(ecra.getByText('Por configurar').first()).toBeVisible();
    });

    /** O mapeamento explica o que «confirma sozinho» quer dizer. */
    test('o mapeamento explica o confirma sozinho', async ({ page }) => {
        const ecra = page.locator('.ecra-react');
        const linha = ecra.locator('tr', { hasText: 'Fatura de venda' });

        await linha.getByRole('button', { name: /Configurar|Editar/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText(/O lançamento nasce confirmado e conta logo nos saldos/)).toBeVisible();
        await expect(janela.getByText(/Um mapeamento inactivo não gera lançamento nenhum/)).toBeVisible();
    });

    /** E a acção destrutiva avisa que vai ser recusada com lançamentos. */
    test('avisa que apagar vai ser recusado com lançamentos', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByText(/esta acção vai ser recusada/)).toBeVisible();
    });
});
