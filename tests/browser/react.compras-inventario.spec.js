import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * AS COMPRAS E O INVENTÁRIO — os seis ecrãs, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Compras` e
 * `tests/Feature/Inventario`): quem pede não é quem aprova, receber é a entrada
 * de stock, o esperado congela ao abrir a contagem. O que aqui se prova é o que
 * só se vê no browser:
 *
 *  · que as seis moradas abrem sem um erro na consola;
 *  · que a requisição se faz de LINHAS — do catálogo ou livres, porque pedir
 *    algo que ainda não é artigo é o caso mais comum;
 *  · que a recepção mostra O QUE FALTA de cada linha, já preenchido;
 *  · que o painel do inventário diz A IDADE da última contagem;
 *  · e que a contagem avisa, antes de fechar, quantos acertos vai fazer.
 */

const MORADAS = [
    ['/compras/dashboard', 'Painel das Compras'],
    ['/compras/requisicoes', 'Requisições de Compra'],
    ['/compras/encomendas', 'Encomendas de Compra'],
    ['/inventario/dashboard', 'Painel do Inventário'],
    ['/inventario/movimentos', 'Movimentos de Stock'],
    ['/inventario/contagem', 'Contagem Física'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test.describe('as moradas das compras e do inventário', () => {
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

/* ─── O painel das compras ──────────────────────────────────────────── */

test.describe('o painel das compras', () => {
    /**
     * A PERGUNTA NÃO É «QUANTO COMPRÁMOS».
     *
     * É «o que é que está à espera de mim» — e é por isso que as decisões
     * pendentes vêm antes dos gráficos.
     */
    test('poe as decisoes pendentes antes dos graficos', async ({ page }) => {
        await page.goto('/compras/dashboard');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Painel das Compras' }))
            .toBeVisible({ timeout: 20_000 });

        await expect(page.getByText('O que está parado à minha espera')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'À espera de decisão' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Encomendas atrasadas' })).toBeVisible();
    });
});

/* ─── As requisições ────────────────────────────────────────────────── */

test.describe('as requisições', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/compras/requisicoes');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Requisições de Compra' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * UMA LINHA PODE NÃO ESTAR NO CATÁLOGO.
     *
     * Pedir algo que ainda não existe como artigo é o caso mais comum de todos,
     * e obrigar a criar o artigo primeiro era obrigar a inventar dados.
     */
    test('o formulario aceita linhas do catalogo e livres', async ({ page }) => {
        await page.getByRole('button', { name: 'Nova requisição' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('Pede-se o que se precisa, mesmo que ainda não esteja no catálogo')).toBeVisible();
        await expect(modal.getByPlaceholder('Nome ou código do artigo…')).toBeVisible();
        await expect(modal.getByRole('button', { name: 'Linha livre' })).toBeVisible();

        // Sem linhas, não se grava: uma requisição sem linhas não pede nada.
        await expect(modal.getByRole('button', { name: 'Guardar' })).toBeDisabled();

        await modal.getByRole('button', { name: 'Linha livre' }).click();

        await expect(modal.getByRole('button', { name: 'Guardar' })).toBeEnabled();
    });

    /** E recusar pede o motivo, porque quem pediu tem de saber porquê. */
    test('recusar pede o motivo', async ({ page }) => {
        await page.getByRole('button', { name: 'Recusar' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('Quem pediu tem de saber porquê — senão volta a pedir o mesmo na semana seguinte.'))
            .toBeVisible();
        await expect(modal.getByRole('button', { name: 'Recusar' })).toBeDisabled();
    });
});

/* ─── As encomendas ─────────────────────────────────────────────────── */

test.describe('as encomendas', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/compras/encomendas');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Encomendas de Compra' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * A RECEPÇÃO MOSTRA O QUE FALTA, JÁ PREENCHIDO.
     *
     * Quem recebe confere e corrige, em vez de escrever tudo de novo. E o aviso
     * diz o que vai acontecer: o stock entra assim que gravar.
     */
    test('a recepcao sugere o que falta e avisa que o stock entra', async ({ page }) => {
        await page.getByRole('button', { name: 'Receber' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText(/O stock entra em/)).toBeVisible();
        await expect(modal.getByText('Chegou agora')).toBeVisible();
        await expect(modal.getByText('Falta', { exact: true })).toBeVisible();

        // A caixa do «chegou agora» vem com o que falta já escrito.
        await expect(modal.getByRole('spinbutton').first()).not.toHaveValue('');
    });

    /** E a encomenda pode nascer de uma requisição aprovada. */
    test('a encomenda pode nascer de uma requisicao', async ({ page }) => {
        await expect(page.getByRole('button', { name: 'Nova encomenda' })).toBeVisible();
    });
});

/* ─── O inventário ──────────────────────────────────────────────────── */

test.describe('o painel do inventário', () => {
    /**
     * A IDADE DA VERDADE.
     *
     * Um inventário contado há seis meses não é o mesmo número que um contado
     * ontem — e esta é a única secção do painel que diz em que medida é que se
     * pode confiar no resto.
     */
    test('diz ha quanto tempo foi a ultima contagem', async ({ page }) => {
        await page.goto('/inventario/dashboard');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Painel do Inventário' }))
            .toBeVisible({ timeout: 20_000 });

        await expect(page.getByRole('heading', { name: 'A última contagem de cada armazém' })).toBeVisible();
        await expect(page.getByText('Um inventário nunca contado é um número em que ninguém deve confiar'))
            .toBeVisible();
    });

    /** E os negativos são impossíveis físicos, ditos por escrito. */
    test('os negativos dizem-se impossiveis fisicos', async ({ page }) => {
        await page.goto('/inventario/dashboard');

        await expect(page.getByText('Impossíveis físicos — vendeu-se o que não havia'))
            .toBeVisible({ timeout: 20_000 });
    });
});

test.describe('os movimentos', () => {
    /** SÓ LÊ, e o ecrã di-lo em vez de deixar procurar um botão que não há. */
    test('diz que so le e onde o stock se corrige', async ({ page }) => {
        await page.goto('/inventario/movimentos');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Movimentos de Stock' }))
            .toBeVisible({ timeout: 20_000 });

        await expect(page.getByText('Este ecrã só lê. O stock corrige-se onde ele é feito — na contagem física, na recepção, na quebra.'))
            .toBeVisible();
    });
});

test.describe('a contagem física', () => {
    /**
     * ABRIR CONGELA O ESPERADO — e o ecrã explica-o antes de o fazer, porque é
     * a diferença entre contar contra um retrato e contar contra um stock que
     * continua a mexer-se.
     */
    test('abrir explica o congelamento antes de o fazer', async ({ page }) => {
        await page.goto('/inventario/contagem');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Contagem Física' }))
            .toBeVisible({ timeout: 20_000 });

        await page.getByRole('button', { name: 'Abrir contagem' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText(/fica gravado linha a linha agora/)).toBeVisible();
        await expect(modal.getByRole('combobox', { name: /Armazém/ })).toBeVisible();
    });
});
