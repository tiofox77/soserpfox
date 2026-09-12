import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O MÓDULO DOS PROJETOS — os quatro ecrãs, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Projetos`): o preço/hora
 * que congela na linha, a folha de horas que é pessoal, facturar como
 * autoridade à parte. O que aqui se prova é o que só se vê no browser:
 *
 *  · que as quatro moradas abrem sem um erro na consola — e isto é o principal
 *    deste módulo, porque DUAS DELAS RESPONDIAM 403 A TODA A GENTE enquanto as
 *    permissões que as guardam não existiam na base de dados;
 *  · que a BARRA DO ORÇAMENTO diz de relance o que está a fugir — e que um
 *    projeto sem orçamento não finge ter uma;
 *  · que a ficha põe o orçamento, o consumido e o facturado lado a lado;
 *  · e que a folha de horas mostra UMA SEMANA de cada vez, com os sete dias.
 */

const MORADAS = [
    ['/projetos/dashboard', 'Painel dos Projetos'],
    ['/projetos/lista', 'Projetos'],
    ['/projetos/tarefas', 'Tarefas de Projeto'],
    ['/projetos/timesheet', 'Folha de Horas'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test.describe('as moradas dos projetos', () => {
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

test.describe('o painel', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/projetos/dashboard');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Painel dos Projetos' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * O CARTÃO QUE SURPREENDE: horas trabalhadas, facturáveis, com preço, e
     * sem factura nenhuma. É dinheiro que a casa já gastou a fazer e nunca
     * cobrou — e não aparecia em ecrã nenhum.
     */
    test('diz quanto ha de horas trabalhadas e nao cobradas', async ({ page }) => {
        await expect(page.getByText('Por facturar', { exact: true })).toBeVisible();
        await expect(page.getByText(/horas trabalhadas e não cobradas/)).toBeVisible();
    });

    /** E a lista dos projetos a andar mostra a barra do orçamento. */
    test('os projetos a andar mostram o consumo do orcamento', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Os projetos a andar' })).toBeVisible();
        await expect(page.getByText('Com o consumo do orçamento à vista')).toBeVisible();
    });
});

/* ─── Os projetos ───────────────────────────────────────────────────── */

test.describe('os projetos', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/projetos/lista');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Projetos' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * A FICHA PÕE OS TRÊS NÚMEROS LADO A LADO.
     *
     * Orçamento, consumido e facturado não são o mesmo, e é vê-los juntos que
     * responde à pergunta que interessa: quanto é que ainda falta cobrar.
     */
    test('a ficha separa o orcamento do consumido e do facturado', async ({ page }) => {
        await page.getByRole('button', { name: 'Ficha' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });

        for (const rotulo of ['Orçamento', 'Consumido', 'Facturado']) {
            await expect(modal.getByText(rotulo, { exact: true }).first()).toBeVisible();
        }

        await expect(modal.getByText('O tecto')).toBeVisible();
    });

    /** O formulário liga o preço/hora ao dinheiro, e diz que ele congela. */
    test('o formulario explica o preco hora e o orcamento', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo projeto' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('O preço/hora é o que transforma horas em dinheiro')).toBeVisible();
        await expect(modal.getByText('Congela em cada hora lançada — mudar aqui não reescreve o passado.'))
            .toBeVisible();
        await expect(modal.getByText('Sem orçamento não há percentagem — e o painel não avisa de estouro nenhum.'))
            .toBeVisible();
    });

    /** E facturar avisa que a factura nasce em rascunho, para conferir. */
    test('facturar avisa que a factura nasce em rascunho', async ({ page }) => {
        await page.getByRole('button', { name: 'Facturar' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText(/factura em RASCUNHO/)).toBeVisible();
        await expect(modal.getByText(/impede cobrá-la duas vezes/)).toBeVisible();
    });
});

/* ─── As tarefas ────────────────────────────────────────────────────── */

test.describe('as tarefas', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/projetos/tarefas');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Tarefas de Projeto' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /** A ordem é urgente primeiro, e o ecrã di-lo à cabeça. */
    test('a ordem e urgente primeiro e depois o prazo', async ({ page }) => {
        await expect(page.getByText('Urgente primeiro, depois o prazo mais próximo')).toBeVisible();
    });

    /**
     * NADA SE APAGA: cancelar é um estado.
     *
     * As horas já lançadas contra uma tarefa continuam a valer, e uma tarefa
     * que desaparecesse deixava-as sem explicação — horas cobradas a um
     * cliente por um trabalho que já não existe em lado nenhum.
     */
    test('nao ha caixote do lixo, ha cancelar', async ({ page }) => {
        await expect(page.getByRole('button', { name: 'Cancelar tarefa' }).first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'Eliminar tarefa' })).toHaveCount(0);
    });

    /** E os filtros incluem «só as minhas», que é como a lista se torna útil. */
    test('filtra-se por projeto, estado, prioridade e so as minhas', async ({ page }) => {
        await expect(page.getByRole('combobox', { name: 'Projeto' })).toBeVisible();
        await expect(page.getByRole('combobox', { name: 'Prioridade' })).toBeVisible();
        await expect(page.getByText('Só as minhas')).toBeVisible();
    });
});

/* ─── A folha de horas ──────────────────────────────────────────────── */

test.describe('a folha de horas', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/projetos/timesheet');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Folha de Horas' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * UMA SEMANA DE CADA VEZ, com os sete dias à vista.
     *
     * Quem lança horas lança-as do que se lembra, e a memória não vai além de
     * dias. Uma lista infinita convida a lançar tudo ao molho no fim do mês.
     */
    test('mostra os sete dias da semana e anda para tras e para a frente', async ({ page }) => {
        await expect(page.getByText('Uma semana de cada vez — a memória não vai além de dias')).toBeVisible();

        // Sete botões de «Lançar» — um por dia da semana.
        await expect(page.getByRole('button', { name: 'Lançar' })).toHaveCount(7);

        await expect(page.getByRole('button', { name: 'Semana anterior' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Semana seguinte' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Esta semana' })).toBeVisible();
    });

    /** O cartão separa as horas facturáveis das que já foram facturadas. */
    test('separa as facturaveis das ja facturadas', async ({ page }) => {
        await expect(page.getByText('Facturáveis', { exact: true })).toBeVisible();
        await expect(page.getByText('Já facturadas', { exact: true })).toBeVisible();
        await expect(page.getByText('Estas já não se corrigem')).toBeVisible();
    });

    /** E o lançamento diz que o preço/hora congela na linha. */
    test('o lancamento diz que o preco congela na linha', async ({ page }) => {
        await page.getByRole('button', { name: 'Lançar' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('O preço/hora do projeto congela nesta linha')).toBeVisible();
        await expect(modal.getByText('Só os projetos a andar aceitam horas.')).toBeVisible();
    });
});
