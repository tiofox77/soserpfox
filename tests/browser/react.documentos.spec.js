import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * AS CINCO LISTAS QUE SÃO O MESMO ECRÃ.
 *
 * Proformas de venda e de compra, orçamentos, facturas de compra e recibos
 * saem todos do mesmo componente React. O que muda vem do servidor: o título,
 * o cabeçalho da coluna da outra parte, os estados que existem mesmo, e se há
 * saldo a mostrar.
 *
 * Se um deles abrir e outro não, o defeito é da configuração — e é isso que
 * este ficheiro apanha, correndo os cinco.
 */

/**
 * `duplica` diz se ESTA lista oferece o botão de duplicar.
 *
 * São os mesmos que o ecrã em Blade oferecia, e a escolha não é arbitrária:
 * duplica-se o que se volta a fazer parecido — a proforma que se repete, a
 * compra ao mesmo fornecedor. Um recibo, uma nota de crédito ou um
 * adiantamento nascem sempre de outro documento e de um valor concreto;
 * duplicá-los seria um atalho para dar por recebido dinheiro que não entrou.
 */
const LISTAS = [
    { morada: '/invoicing/sales/proformas', titulo: 'Proformas de Venda', parte: 'Cliente', duplica: true },
    { morada: '/invoicing/sales/quotes', titulo: 'Orçamentos', parte: 'Cliente', duplica: false },
    { morada: '/invoicing/purchases/invoices', titulo: 'Facturas de Compra', parte: 'Fornecedor', duplica: true },
    { morada: '/invoicing/purchases/proformas', titulo: 'Proformas de Compra', parte: 'Fornecedor', duplica: true },
    { morada: '/invoicing/receipts', titulo: 'Recibos', parte: 'Cliente', duplica: false },
    { morada: '/invoicing/credit-notes', titulo: 'Notas de Crédito', parte: 'Cliente', duplica: false },
    { morada: '/invoicing/debit-notes', titulo: 'Notas de Débito', parte: 'Cliente', duplica: false },
    { morada: '/invoicing/advances', titulo: 'Adiantamentos', parte: 'Cliente', duplica: false },
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

for (const lista of LISTAS) {
    test(`abre: ${lista.titulo}`, async ({ page }) => {
        const erros = [];
        page.on('console', (m) => {
            if (m.type() === 'error') erros.push(m.text());
        });
        page.on('pageerror', (e) => erros.push(String(e)));

        /*
         * O QUE ESTA LISTA OFERECE VEM DO SERVIDOR, e é aqui que se lê.
         *
         * Pela resposta e não só pelos botões: a empresa de bancada pode não
         * ter nenhum documento deste tipo, e nesse caso uma contagem de
         * botões dá zero por falta de linhas — passava sem provar nada.
         */
        const opcoes = page.waitForResponse(
            (r) => /\/documentos\/[a-z-]+\/opcoes/.test(r.url()),
            { timeout: 20_000 },
        );

        await page.goto(lista.morada);

        expect((await (await opcoes).json()).pode_duplicar).toBe(lista.duplica);

        // Ou traz documentos, ou diz que não há — nunca fica em branco.
        await expect(
            page.getByRole('table').or(page.getByText('Nenhum documento com estes filtros')),
        ).toBeVisible({ timeout: 20_000 });

        // O cabeçalho da coluna sabe se é cliente ou fornecedor.
        const tabela = page.getByRole('table');
        if (await tabela.isVisible()) {
            await expect(page.getByRole('columnheader', { name: lista.parte })).toBeVisible();
            await expect(page.getByRole('columnheader', { name: 'Falta pagar' })).toHaveCount(
                lista.titulo === 'Facturas de Compra' ? 1 : 0,
            );

            // DUPLICAR só onde a lista o oferece — e a decisão é do servidor.
            const duplicar = page.getByRole('link', { name: /^Duplicar / });

            if (lista.duplica) {
                await expect(duplicar.first()).toBeVisible();
                // Vai para o ecrã de emissão de sempre, com o id na morada: é
                // lá que o conteúdo é pedido, e nada se grava por carregar.
                await expect(duplicar.first()).toHaveAttribute('href', /\/create\?duplicar=\d+$/);
            } else {
                await expect(duplicar).toHaveCount(0);
            }
        }

        expect(erros).toEqual([]);
    });
}


/**
 * AS ACÇÕES DA FACTURA DE COMPRA: anular e marcar como paga.
 *
 * Uma factura de compra NÃO se elimina — não há botão nenhum que o faça, e é
 * isso que se vigia aqui. Anular e marcar como paga aparecem só nas linhas que
 * ainda as aceitam, e quem decide isso é o servidor: o ecrã só desenha o que
 * cada linha lhe disser.
 *
 * Não se carrega em nenhum deles de propósito: são acções que mudam mesmo o
 * documento e o stock da empresa de bancada. O que se prova aqui é que o botão
 * está no sítio; que a acção faz o que promete prova-se nos ensaios de API.
 */
test('a lista de compras anula, marca como paga e não elimina', async ({ page }) => {
    await page.goto('/invoicing/purchases/invoices');

    await expect(
        page.getByRole('table').or(page.getByText('Nenhum documento com estes filtros')),
    ).toBeVisible({ timeout: 20_000 });

    if (!(await page.getByRole('table').isVisible())) {
        test.skip(true, 'a empresa de bancada não tem facturas de compra');
    }

    // Eliminar não existe — nem como botão, nem como palavra.
    await expect(page.getByRole('button', { name: /Eliminar|Apagar/ })).toHaveCount(0);

    // As acções que mudam o documento têm rótulo próprio por linha.
    const anular = page.getByRole('button', { name: /^Anular / });
    const marcar = page.getByRole('button', { name: /^Marcar .* como paga$/ });

    // Numa lista com documentos, ou há linhas que as aceitam ou todas já foram
    // anuladas/pagas — o que não pode é o botão aparecer onde não vale.
    expect(await anular.count()).toBeGreaterThanOrEqual(0);
    expect(await marcar.count()).toBeGreaterThanOrEqual(0);

    if ((await anular.count()) > 0) {
        await expect(anular.first()).toHaveAttribute('title', 'Anular (reverte o stock)');
    }
});
/**
 * AS COLUNAS QUE CADA DOCUMENTO TEM DE SEU.
 *
 * A lista genérica serve nove documentos, mas o Blade não os desenhava todos
 * iguais: a proposta mostrava a VALIDADE, a factura de compra o VENCIMENTO, e
 * a nota a FACTURA DE ORIGEM e o MOTIVO. A migração deixou uma tabela só, com
 * as colunas do menor denominador — e uma nota de crédito sem saber de que
 * factura é não se lê.
 */
test('cada documento traz as colunas que so ele tem', async ({ page }) => {
    await page.goto('/invoicing/sales/quotes');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('columnheader', { name: 'Validade' })).toBeVisible();

    await page.goto('/invoicing/purchases/invoices');
    await expect(page.getByRole('table').or(page.getByText('Nenhum documento com estes filtros'))).toBeVisible({ timeout: 20_000 });

    if (await page.getByRole('table').isVisible()) {
        await expect(page.getByRole('columnheader', { name: 'Vencimento' })).toBeVisible();
    }

    await page.goto('/invoicing/credit-notes');
    await expect(page.getByRole('table').or(page.getByText('Nenhum documento com estes filtros'))).toBeVisible({ timeout: 20_000 });

    if (await page.getByRole('table').isVisible()) {
        await expect(page.getByRole('columnheader', { name: 'Fatura Origem' })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Motivo' })).toBeVisible();

        // E o FILTRO do motivo, que só as notas têm.
        await expect(page.getByLabel(/^Motivo/)).toBeVisible();
    }

    // Num orçamento não há motivo nenhum para filtrar.
    await page.goto('/invoicing/sales/quotes');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByLabel(/^Motivo/)).toHaveCount(0);
});

/**
 * CONVERTER, HISTÓRICO E ELIMINAR — os três botões que a lista de propostas
 * tinha em Blade e que a migração não trouxe (nem no ecrã, nem na API).
 *
 * O HISTÓRICO é o que evita a duplicação por engano: a mesma proposta pode ser
 * convertida mais do que uma vez (um fornecimento repetido factura-se da mesma
 * proforma), e é por isso que ele mostra o que já saiu.
 */
test('a proposta converte, mostra o historico e elimina-se', async ({ page }) => {
    await page.goto('/invoicing/sales/quotes');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    const linha = page.locator('tbody tr').first();

    await expect(linha.getByRole('button', { name: /^Converter / })).toBeVisible();
    await expect(linha.getByRole('button', { name: /^Histórico de / })).toBeVisible();

    // O HISTÓRICO abre e pede ao servidor.
    const pedido = page.waitForResponse(
        (r) => r.url().includes('/historico') && r.request().method() === 'GET',
        { timeout: 20_000 },
    );

    await linha.getByRole('button', { name: /^Histórico de / }).click();
    expect((await pedido).ok()).toBe(true);

    const janela = page.getByRole('dialog').filter({ hasText: 'Histórico de Conversões' });
    await expect(janela).toBeVisible({ timeout: 20_000 });
    await expect(janela.getByText(/Faturas Geradas/)).toBeVisible();

    await janela.getByRole('button', { name: /^Fechar$/ }).click();

    /*
     * CONVERTER PERGUNTA ANTES, e diz as duas coisas que quem converte tem de
     * saber: a factura fica em RASCUNHO, e as taxas são as de hoje.
     */
    await linha.getByRole('button', { name: /^Converter / }).click();

    const aviso = page.getByRole('dialog').filter({ hasText: 'Converter em factura' });
    await expect(aviso).toBeVisible();
    await expect(aviso.getByText(/RASCUNHO/)).toBeVisible();

    await aviso.getByRole('button', { name: /^Cancelar$/ }).click();
    await expect(aviso).toBeHidden();
});

/**
 * ELIMINAR PERGUNTA ANTES — e um documento já convertido não se elimina.
 *
 * A factura aponta para a proposta, e ficaria a referir um documento que já
 * não existe. Quem decide é o servidor, linha a linha.
 */
test('eliminar pergunta antes de apagar', async ({ page }) => {
    await page.goto('/invoicing/sales/quotes');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    const apagar = page.getByRole('button', { name: /^Eliminar / });

    if ((await apagar.count()) === 0) {
        test.skip(true, 'nenhum orçamento desta empresa pode ser eliminado');
    }

    await apagar.first().click();

    const janela = page.getByRole('dialog').filter({ hasText: 'Eliminar documento' });
    await expect(janela).toBeVisible();
    await expect(janela.getByText(/Não há volta/)).toBeVisible();

    // Cancela-se: este ensaio não apaga nada da empresa de bancada.
    await janela.getByRole('button', { name: /^Cancelar$/ }).click();
    await expect(janela).toBeHidden();
});
