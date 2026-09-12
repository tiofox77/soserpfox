import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O SUPORTE — os dois ecrãs, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Suporte`): o número do
 * pedido é por empresa, um pedido é de quem o abriu, o voto não atravessa
 * empresas e a contagem vem das linhas. O que aqui se prova é o que só se vê no
 * browser:
 *
 *  · que as duas moradas abrem sem um erro na consola;
 *  · que o pedido «à espera de si» tem mesmo onde responder — era este o
 *    estado que durante anos não tinha sítio nenhum, e o botão «Ver Detalhes»
 *    do ecrã antigo não fazia nada;
 *  · que o fio mostra quem escreveu o quê;
 *  · e que o voto se lê de relance: o botão marcado, o total ao lado.
 */

const MORADAS = [
    ['/support/tickets', 'Pedidos de Suporte'],
    ['/support/features', 'Quadro de Melhorias'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test.describe('as moradas do suporte', () => {
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

/* ─── Os pedidos ────────────────────────────────────────────────────── */

test.describe('os pedidos de suporte', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/support/tickets');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Pedidos de Suporte' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * «À ESPERA DE SI» é o estado que existia e não tinha onde responder.
     *
     * Um pedido parado à espera de uma resposta que o ecrã não deixava
     * escrever é um pedido morto — e o cartão diz-o por palavras.
     */
    test('o cartao explica o que e estar a espera de si', async ({ page }) => {
        await expect(page.getByText('O suporte perguntou algo e ainda não teve resposta')).toBeVisible();
    });

    /** O fio abre-se, mostra quem escreveu o quê, e aceita a resposta. */
    test('o fio do pedido deixa responder', async ({ page }) => {
        await page.locator('li', { hasText: 'Não consigo emitir a nota de crédito' })
            .getByRole('button', { name: 'Ver' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });

        // A pergunta do suporte está no fio, identificada como tal.
        await expect(modal.getByText('Suporte', { exact: true }).first()).toBeVisible();
        await expect(modal.getByText(/Consegue dizer-nos o número da factura/)).toBeVisible();

        await expect(modal.getByText('Responder devolve o pedido à fila do suporte.')).toBeVisible();

        // Sem texto não se envia: uma resposta vazia não responde nada.
        await expect(modal.getByRole('button', { name: 'Enviar resposta' })).toBeDisabled();

        await modal.getByRole('textbox', { name: /Responder/ }).fill('É a série FT A, número 12.');

        await expect(modal.getByRole('button', { name: 'Enviar resposta' })).toBeEnabled();
    });

    /** E o formulário do pedido diz o que ajuda a resolvê-lo mais depressa. */
    test('o formulario pede o concreto', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo pedido' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('O que fez, o que esperava, e o que apareceu. Se houver um número de documento, escreva-o.'))
            .toBeVisible();
        await expect(modal.getByText(/Uma captura de ecrã vale por meia página de descrição/)).toBeVisible();
        await expect(modal.getByRole('combobox', { name: /Prioridade/ })).toBeVisible();
        await expect(modal.getByRole('combobox', { name: /Categoria/ })).toBeVisible();
    });
});

/* ─── O quadro de melhorias ─────────────────────────────────────────── */

test.describe('o quadro de melhorias', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/support/features');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Quadro de Melhorias' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /** O VOTO lê-se de relance: o botão marcado e o total ao lado. */
    test('o voto marcado ve-se', async ({ page }) => {
        const votada = page.locator('li', { hasText: 'Exportar o mapa de vendas' });

        await expect(votada).toBeVisible();
        await expect(votada.getByRole('button', { name: 'Retirar o voto' })).toHaveAttribute('aria-pressed', 'true');

        const porVotar = page.locator('li', { hasText: 'Pesquisar o cliente pelo telefone' });

        await expect(porVotar.getByRole('button', { name: 'Votar' })).toHaveAttribute('aria-pressed', 'false');
    });

    /**
     * SÓ SE RETIRA O QUE NINGUÉM VOTOU.
     *
     * A partir do primeiro voto a sugestão já não é só de quem a escreveu — e
     * é por isso que o caixote do lixo desaparece da que está votada.
     */
    test('a sugestao votada nao se retira', async ({ page }) => {
        await expect(
            page.locator('li', { hasText: 'Exportar o mapa de vendas' })
                .getByRole('button', { name: 'Retirar sugestão' }),
        ).toHaveCount(0);

        await expect(
            page.locator('li', { hasText: 'Pesquisar o cliente pelo telefone' })
                .getByRole('button', { name: 'Retirar sugestão' }),
        ).toBeVisible();
    });

    /** E sugerir explica o que convence: o problema, não a solução. */
    test('sugerir pede o problema e nao a solucao', async ({ page }) => {
        await page.getByRole('button', { name: 'Sugerir melhoria' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('O que faz falta, e sobretudo PARA QUÊ — o problema convence mais do que a solução.'))
            .toBeVisible();
    });
});
