import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS CATÁLOGOS DA CONTABILIDADE, os PERÍODOS, as MOEDAS e a ANALÍTICA.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Contabilidade`). O que aqui
 * se prova é o que só se vê no browser:
 *
 *  · que as seis moradas abrem sem um erro na consola;
 *  · que o centro de custo PENDURADO NOUTRO aparece na lista — a do Livewire só
 *    trazia os de raiz, e um centro filho não existia em ecrã nenhum;
 *  · que os períodos dizem, escrito, porque é que um deles ainda não fecha —
 *    antes era preciso carregar em Fechar para descobrir;
 *  · que o ecrã das moedas avisa que a lista é da PLATAFORMA;
 *  · e que a analítica abre já na primeira dimensão, com as etiquetas dela.
 */

const MORADAS = [
    ['/accounting/journals', 'Diários'],
    ['/accounting/document-types', 'Tipos de Documento'],
    ['/accounting/cost-centers', 'Centros de Custo'],
    ['/accounting/periods', 'Períodos Contabilísticos'],
    ['/accounting/currencies', 'Moedas e Câmbios'],
    ['/accounting/analytics', 'Contabilidade Analítica'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test.describe('as moradas dos catálogos da contabilidade', () => {
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

/* ─── Os diários ────────────────────────────────────────────────────── */

test.describe('os diários', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accounting/journals');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Diários' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * O DIÁRIO COM LANÇAMENTOS não oferece o botão de apagar: os lançamentos
     * ficariam a apontar para um id que já não existe.
     */
    test('o diário com lançamentos não se pode apagar', async ({ page }) => {
        const ecra = page.locator('.ecra-react');
        const linha = ecra.locator('tr', { hasText: 'Diário de Operações Diversas' });

        await expect(linha).toBeVisible();
        await expect(linha.getByRole('button', { name: /Eliminar/ })).toHaveCount(0);
    });

    /** O contador da referência mostra-se, e diz para que serve. */
    test('o formulário explica o prefixo e o contador da referência', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Novo Diário/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText(/«DG-» dá «DG-00001»/)).toBeVisible();
        await expect(janela.getByText(/Só se mexe ao trazer numeração de outro sistema/)).toBeVisible();
    });
});

/* ─── Os centros de custo ───────────────────────────────────────────── */

test.describe('os centros de custo', () => {
    /**
     * O PENDURADO NOUTRO APARECE.
     *
     * A lista do Livewire só trazia os de raiz (`whereNull('parent_id')`): um
     * centro pendurado noutro não aparecia em lado nenhum, e não havia como o
     * editar.
     */
    test('o centro pendurado noutro aparece na lista', async ({ page }) => {
        await page.goto('/accounting/cost-centers');

        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByRole('heading', { name: 'Centros de Custo' })).toBeVisible({ timeout: 20_000 });
        await expect(ecra.locator('tr', { hasText: 'CC-LOJA' }).first()).toBeVisible();

        const filho = ecra.locator('tr', { hasText: 'CC-BALCAO' });

        await expect(filho).toBeVisible();
        // E diz de quem é filho pelo NOME, não pelo id: a coluna de referência
        // mostrava o número cru.
        await expect(filho.getByText('CC-LOJA · Loja da Bancada')).toBeVisible();
    });
});

/* ─── Os períodos ───────────────────────────────────────────────────── */

test.describe('os períodos', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accounting/periods');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Períodos Contabilísticos' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * O QUE SEGURA O FECHO, ESCRITO.
     *
     * A bancada tem um rascunho no mês corrente: o período de hoje não fecha, e
     * o ecrã diz porquê em vez de deixar carregar no botão para descobrir.
     */
    test('diz porque é que um período ainda não fecha', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByText('Períodos que ainda não fecham')).toBeVisible();
        await expect(ecra.getByText(/em rascunho\. Confirme-os ou apague-os/).first()).toBeVisible();

        // O período do mês corrente está marcado como o de hoje.
        await expect(ecra.getByText('é o de hoje')).toBeVisible();
    });

    /** GERAR O EXERCÍCIO — o que faltava por completo, e é incremental. */
    test('gerar o exercício explica que é incremental', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Gerar o exercício/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText(/um período fechado nunca é reaberto por aqui/)).toBeVisible();
        await expect(janela.getByRole('button', { name: 'Gerar' })).toBeEnabled();
    });

    /** E um período à mão avisa da sobreposição antes de a tentar. */
    test('o período à mão avisa da sobreposição', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Novo Período/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText(/não pode sobrepor-se a outro período/)).toBeVisible();
    });
});

/* ─── As moedas ─────────────────────────────────────────────────────── */

test.describe('as moedas e os câmbios', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accounting/currencies');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Moedas e Câmbios' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /** A LISTA É DA PLATAFORMA, e o ecrã antigo não o dizia em lado nenhum. */
    test('avisa que a lista é da plataforma', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByText(/é da plataforma, não desta empresa/)).toBeVisible();
        await expect(ecra.locator('tr', { hasText: 'Kwanza' })).toBeVisible();
    });

    /** O câmbio diz em palavras o que a taxa quer dizer. */
    test('a janela do câmbio traduz a taxa em palavras', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Novo Câmbio/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByText(/ela é CORRIGIDA — não se cria uma segunda/)).toBeVisible();

        // Os `<select>` são os dois primeiros da janela: «De» e «Para». Pelo
        // rótulo, «De» apanhava também a ajuda da taxa («…moeda de origem»).
        await janela.locator('select').first().selectOption({ label: 'USD · Dólar dos EUA' });
        await janela.locator('select').nth(1).selectOption({ label: 'AOA · Kwanza' });
        await janela.locator('input[type="number"]').fill('920.5');

        await expect(janela.getByText(/1 USD vale/)).toBeVisible();
    });
});

/* ─── A analítica ───────────────────────────────────────────────────── */

test.describe('a analítica', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accounting/analytics');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Contabilidade Analítica' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * ABRE JÁ NA PRIMEIRA DIMENSÃO.
     *
     * A do Livewire abria com as etiquetas vazias até se carregar numa
     * dimensão, e nada dizia que era preciso — parecia não haver etiquetas.
     */
    test('abre na primeira dimensão com as etiquetas dela', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await expect(ecra.getByText('Canal de venda').first()).toBeVisible();
        await expect(ecra.getByText('Projecto', { exact: true }).first()).toBeVisible();

        // A dimensão obrigatória marca-se.
        await expect(ecra.getByText('obrigatória').first()).toBeVisible();

        // E as etiquetas da escolhida já estão na tabela.
        await expect(ecra.getByRole('heading', { name: /Etiquetas de/ })).toBeVisible();
        await expect(ecra.locator('tr', { hasText: 'Balcão' }).first()).toBeVisible();
    });

    /** Trocar de dimensão troca as etiquetas. */
    test('trocar de dimensão troca as etiquetas', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        await ecra.getByRole('button', { name: /Projecto/ }).first().click();

        await expect(ecra.getByRole('heading', { name: /Etiquetas de «Projecto»/ })).toBeVisible();
        await expect(ecra.locator('tr', { hasText: 'Obra do Kilamba' })).toBeVisible();

        // A etiqueta inactiva lê-se como tal.
        const encerrada = ecra.locator('tr', { hasText: 'Obra antiga (encerrada)' });

        await expect(encerrada).toBeVisible();
        await expect(encerrada.getByText('Inactiva')).toBeVisible();
    });

    /** E editar uma etiqueta abre-a preenchida — não abre um formulário vazio. */
    test('editar uma etiqueta abre-a preenchida', async ({ page }) => {
        const ecra = page.locator('.ecra-react');

        const linha = ecra.locator('tr', { hasText: 'Balcão' }).first();

        await linha.getByRole('button', { name: /Editar a etiqueta/ }).click();

        const janela = page.locator('dialog[open]');

        await expect(janela.getByRole('heading', { name: 'Editar Etiqueta' })).toBeVisible();
        await expect(janela.getByLabel('Código')).toHaveValue('BALCAO');
        await expect(janela.getByText(/Único dentro desta dimensão/)).toBeVisible();
    });
});
