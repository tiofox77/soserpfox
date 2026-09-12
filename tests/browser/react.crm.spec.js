import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O MÓDULO DO CRM — os quatro ecrãs, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/CRM`): a conversão que
 * cria o cliente da facturação, a probabilidade que acompanha a etapa, os
 * segredos do Meta que nunca voltam ao ecrã. O que aqui se prova é o que só se
 * vê no browser:
 *
 *  · que as moradas abrem sem um erro na consola;
 *  · que a LINHA DE CRIAR está sempre aberta no topo dos leads — um formulário
 *    de vinte campos para registar uma chamada é a razão por que ninguém
 *    regista chamadas;
 *  · que a lista e o funil são o mesmo ecrã, e que o funil mostra o PONDERADO
 *    por coluna, que é o número honesto;
 *  · e que os segredos do Meta aparecem como «configurado» e não como texto.
 */

const MORADAS = [
    ['/crm/dashboard', 'Painel do CRM'],
    ['/crm/leads', 'Leads'],
    ['/crm/oportunidades', 'Oportunidades'],
    ['/crm/funil-vendas', 'Oportunidades'],
    ['/crm/integracoes', 'Integração Meta'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test.describe('as moradas do CRM', () => {
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
    /**
     * GANHO NÃO É COBRADO — e é a distinção que o painel passou a fazer.
     *
     * O funil sabia dizer quanto se fechou e nunca quanto virou documento, que
     * é a pergunta que paga as contas.
     */
    test('separa o ganho do facturado', async ({ page }) => {
        await page.goto('/crm/dashboard');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Painel do CRM' }))
            .toBeVisible({ timeout: 20_000 });

        await expect(page.getByText('Funil aberto', { exact: true })).toBeVisible();
        await expect(page.getByText('Ganho no mês', { exact: true })).toBeVisible();
        await expect(page.getByText('Já facturado', { exact: true })).toBeVisible();
    });

    /** E as tarefas atrasadas vêm à cabeça, não no fundo da página. */
    test('as tarefas por fazer vem antes dos graficos', async ({ page }) => {
        await page.goto('/crm/dashboard');

        await expect(page.getByRole('heading', { name: 'O que está por fazer' }))
            .toBeVisible({ timeout: 20_000 });
        await expect(page.getByText('Por prazo — as atrasadas à cabeça')).toBeVisible();
    });
});

/* ─── Os leads ──────────────────────────────────────────────────────── */

test.describe('os leads', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/crm/leads');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Leads' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * A LINHA DE CRIAR ESTÁ SEMPRE ABERTA.
     *
     * Não é um botão que abre uma janela: quem está ao telefone escreve o nome,
     * o número, e carrega em Enter. Tudo o resto pode esperar.
     */
    test('a linha de criar esta sempre a vista', async ({ page }) => {
        await expect(page.getByPlaceholder('Quem ligou…')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Pôr na fila' })).toBeVisible();
    });

    /** Perder pede motivo — e o botão só liga quando o motivo tem substância. */
    test('perder um lead pede o motivo', async ({ page }) => {
        await page.getByRole('button', { name: 'Dar por perdido' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('«Perdido sem razão» não ensina nada. Os motivos somados dizem onde se perde.'))
            .toBeVisible();
        await expect(modal.getByRole('button', { name: 'Registar' })).toBeDisabled();

        await modal.getByRole('textbox').fill('Foi a outro fornecedor');

        await expect(modal.getByRole('button', { name: 'Registar' })).toBeEnabled();
    });

    /** Converter abre a oportunidade já com o título preenchido. */
    test('converter sugere o titulo da oportunidade', async ({ page }) => {
        await page.getByRole('button', { name: 'Converter' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('Nasce o cliente e, com ele, a primeira oportunidade')).toBeVisible();
        await expect(modal.getByRole('textbox').first()).not.toBeEmpty();
    });
});

/* ─── As oportunidades ──────────────────────────────────────────────── */

test.describe('as oportunidades', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/crm/oportunidades');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Oportunidades' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * A LISTA E O FUNIL SÃO O MESMO ECRÃ.
     *
     * Eram duas moradas para o mesmo assunto visto de duas maneiras, e quem
     * movia um cartão no funil tinha de ir à lista para lhe mexer no valor.
     */
    test('a lista e o funil sao separadores do mesmo ecra', async ({ page }) => {
        await page.getByRole('tab', { name: 'Funil' }).click();

        // O PONDERADO POR COLUNA é o que torna o quadro honesto.
        await expect(page.getByText(/^Ponderado:/).first()).toBeVisible({ timeout: 15_000 });

        await page.getByRole('tab', { name: 'Lista' }).click();
        await expect(page.getByPlaceholder('Título ou cliente…')).toBeVisible({ timeout: 15_000 });
    });

    /** E o cartão do funil move-se uma etapa para cada lado. */
    test('o cartao do funil move se para os dois lados', async ({ page }) => {
        await page.getByRole('tab', { name: 'Funil' }).click();

        await expect(page.getByRole('button', { name: 'Etapa seguinte' }).first())
            .toBeVisible({ timeout: 15_000 });
        await expect(page.getByRole('button', { name: 'Etapa anterior' }).first()).toBeVisible();
    });

    /** O formulário diz que a etapa traz a probabilidade consigo. */
    test('o formulario liga a etapa a probabilidade', async ({ page }) => {
        await page.getByRole('button', { name: 'Nova oportunidade' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('A etapa traz consigo a probabilidade')).toBeVisible();
        await expect(modal.getByText('A probabilidade da etapa é a que pesa o valor no funil.')).toBeVisible();
    });
});

/* ─── O Meta ────────────────────────────────────────────────────────── */

test.describe('a integração Meta', () => {
    /**
     * OS SEGREDOS NÃO VOLTAM AO ECRÃ.
     *
     * O que se vê é «configurado» ou um campo vazio — e o aviso de que deixar
     * em branco mantém o que lá está, que é o que acontece sempre que alguém
     * abre isto só para mudar o nome da página.
     */
    test('os segredos aparecem como configurado, nao como texto', async ({ page }) => {
        await page.goto('/crm/integracoes');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Integração Meta' }))
            .toBeVisible({ timeout: 20_000 });

        await expect(page.getByText('App Secret')).toBeVisible();
        await expect(page.getByText('Token do WhatsApp')).toBeVisible();

        // Os campos de segredo são de palavra-passe: não mostram o que lá está.
        const segredos = page.locator('.ecra-react input[type="password"]');

        await expect(segredos.first()).toBeVisible();
        await expect(await segredos.count()).toBeGreaterThanOrEqual(3);
    });

    /** O par que se cola no Meta está pronto a copiar. */
    test('o url do webhook e o token estao a mao', async ({ page }) => {
        await page.goto('/crm/integracoes');

        await expect(page.getByRole('heading', { name: 'O que se cola no painel do Meta' }))
            .toBeVisible({ timeout: 20_000 });

        // Os dois CONTROLOS, e não o texto: o subtítulo do cartão repete as
        // mesmas palavras, e procurar pelo texto apanha os dois.
        await expect(page.getByRole('textbox', { name: /URL do webhook/ })).toBeVisible();
        await expect(page.getByRole('textbox', { name: /Token de verificação/ })).toBeVisible();
    });

    /** E o teste da ligação diz, ao lado, que não manda mensagem nenhuma. */
    test('o teste da ligacao avisa que nao envia nada', async ({ page }) => {
        await page.goto('/crm/integracoes');

        await expect(page.getByRole('button', { name: 'Testar a ligação' }))
            .toBeVisible({ timeout: 20_000 });
        await expect(page.getByText('Pergunta ao Meta pelo próprio número. Não envia mensagem nenhuma.'))
            .toBeVisible();
    });
});
