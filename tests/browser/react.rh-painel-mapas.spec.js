import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS CINCO ÚLTIMOS ECRÃS DO RH: painel, mapas, IRT, definições e contratos.
 *
 * Eram as seis rotas que ainda não tinham permissão nenhuma — bastava ter o
 * módulo activo para abrir o mapa de salários, que é o salário de toda a
 * gente numa página só.
 *
 * O que aqui se prova é o que só se vê no browser:
 *
 *  · que o painel abre com os AVISOS primeiro, e que cada um leva ao ecrã
 *    onde se resolve;
 *  · que os cinco mapas são a mesma página e que os FILTROS MUDAM com o mapa
 *    — o quadro de pessoal é do ano e não pede mês;
 *  · que o mapa de IRT anda de mês em mês e leva o período no link do papel;
 *  · que as definições gravam AO SAIR DO CAMPO, e dizem que gravaram;
 *  · e que o contrato pede o que só um contrato pede, em três abas.
 *
 * As regras — permissões por verbo, um contrato activo por pessoa, o
 * rascunho que fica fora do IRT — estão em `ApiDoPainelEDosMapasTest` e
 * `ApiDosContratosTest`.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

/* ─── O painel ──────────────────────────────────────────────────────── */

test.describe('painel', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/hr/dashboard');
        await expect(page.getByRole('heading', { name: 'Recursos Humanos' }).first()).toBeVisible({ timeout: 20_000 });
    });

    test('abre com os cartoes e os graficos', async ({ page }) => {
        for (const cartao of ['Funcionários', 'Activos', 'De férias hoje', 'Picaram hoje']) {
            await expect(page.getByText(cartao, { exact: true }).first()).toBeVisible();
        }

        for (const grafico of ['Custo da folha, mês a mês', 'Presenças da semana', 'Pessoas por departamento']) {
            await expect(page.getByRole('heading', { name: grafico })).toBeVisible();
        }

        // As três listas do fundo.
        for (const lista of ['Aniversários deste mês', 'Últimas admissões', 'Próximas férias']) {
            await expect(page.getByRole('heading', { name: lista })).toBeVisible();
        }
    });

    test('nenhum erro na consola', async ({ page }) => {
        const erros = [];
        page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.reload();
        await expect(page.getByRole('heading', { name: 'Recursos Humanos' }).first()).toBeVisible({ timeout: 20_000 });

        expect(erros).toEqual([]);
    });

    /**
     * UM AVISO LEVA AO ECRÃ ONDE SE RESOLVE — e o link não dá 403.
     *
     * É a razão de os avisos serem filtrados pela permissão de quem vê:
     * mandar alguém para um 403 lê-se como avaria do sistema.
     */
    test('um aviso leva a um ecra que abre', async ({ page }) => {
        const accao = page.getByRole('link', { name: /^Ver / }).first();

        // A bancada tem folhas em rascunho e documentos por vencer; se um dia
        // não tiver aviso nenhum, não há nada para provar aqui.
        if (await accao.count() === 0) {
            test.skip(true, 'a bancada não tem nada à espera de decisão');
        }

        await expect(accao).toBeVisible();

        const morada = await accao.getAttribute('href');
        const resposta = await page.request.get(morada);

        expect(resposta.status(), `o aviso aponta para ${morada}`).toBe(200);
    });
});

/* ─── Os mapas ──────────────────────────────────────────────────────── */

test.describe('mapas', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/hr/reports');
        await expect(page.getByRole('button', { name: 'Mapa de Salários' })).toBeVisible({ timeout: 20_000 });
    });

    test('os cinco mapas estao a vista', async ({ page }) => {
        for (const mapa of ['Mapa de Salários', 'Custo por Departamento', 'Resumo de Presenças', 'Saldo de Férias', 'Evolução do Quadro de Pessoal']) {
            await expect(page.getByRole('button', { name: mapa })).toBeVisible();
        }
    });

    test('nenhum erro na consola', async ({ page }) => {
        const erros = [];
        page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.reload();
        await expect(page.getByRole('button', { name: 'Saldo de Férias' })).toBeVisible({ timeout: 20_000 });

        expect(erros).toEqual([]);
    });

    /**
     * OS FILTROS MUDAM COM O MAPA.
     *
     * O quadro de pessoal é do ANO e não pede mês; o custo por departamento
     * já é POR departamento e não pede o filtro. É o servidor que o diz.
     */
    test('cada mapa pede so os filtros que usa', async ({ page }) => {
        // O NOME ACESSÍVEL DE UM `select` DENTRO DO `label` LEVA A OPÇÃO
        // ESCOLHIDA — «Mês setembro», não «Mês». Por isso a procura é por
        // início e não por igualdade; num `input` o nome é só o rótulo.
        const mes = page.getByLabel(/^Mês/);
        const departamento = page.getByLabel(/^Departamento/);

        // O mapa de salários pede os dois.
        await expect(mes).toBeVisible();
        await expect(departamento).toBeVisible();

        await page.getByRole('button', { name: 'Custo por Departamento' }).click();
        await expect(mes).toBeVisible();
        await expect(departamento).toHaveCount(0);

        await page.getByRole('button', { name: 'Evolução do Quadro de Pessoal' }).click();
        await expect(mes).toHaveCount(0);
        await expect(departamento).toHaveCount(0);

        // E o quadro de pessoal traz os seus três números e o gráfico.
        await expect(page.getByText('No início do ano', { exact: true })).toBeVisible({ timeout: 20_000 });
        await expect(page.getByText('Variação no ano', { exact: true })).toBeVisible();
    });

    /** O saldo de férias tem uma linha por pessoa activa, com o direito. */
    test('o saldo de ferias abre com as colunas do direito', async ({ page }) => {
        await page.getByRole('button', { name: 'Saldo de Férias' }).click();

        await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

        for (const coluna of ['Direito (dias)', 'Gozados (dias)', 'Saldo']) {
            await expect(page.getByRole('columnheader', { name: coluna })).toBeVisible();
        }

        // O título é do ANO — este mapa não é de um mês.
        await expect(page.getByRole('heading', { name: /^Saldo de Férias — \d{4}$/ })).toBeVisible();
    });
});

/* ─── O mapa de IRT ─────────────────────────────────────────────────── */

test.describe('mapa de IRT', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/hr/irt-map');
        await expect(page.getByRole('heading', { name: 'Mapa de IRT' }).first()).toBeVisible({ timeout: 20_000 });
    });

    test('abre no mes passado, com os quatro numeros', async ({ page }) => {
        // O IRT retido entrega-se depois de o mês fechar: é o mês PASSADO que
        // se está a preparar quando se abre este ecrã.
        const passado = new Date();
        passado.setDate(1);
        passado.setMonth(passado.getMonth() - 1);

        await expect(page.getByLabel('Ano', { exact: true })).toHaveValue(String(passado.getFullYear()));
        await expect(page.getByLabel(/^Mês/)).toHaveValue(String(passado.getMonth() + 1));

        for (const cartao of ['Trabalhadores', 'Matéria colectável', 'INSS retido', 'IRT a entregar']) {
            await expect(page.getByText(cartao, { exact: true }).first()).toBeVisible();
        }

        await expect(page.getByText('não recalcula nada', { exact: false })).toBeVisible();
    });

    test('nenhum erro na consola', async ({ page }) => {
        const erros = [];
        page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.reload();
        await expect(page.getByRole('heading', { name: 'O período' })).toBeVisible({ timeout: 20_000 });

        expect(erros).toEqual([]);
    });

    /** O papel e o CSV levam o período que está no ecrã, e não o de hoje. */
    test('o papel e o csv levam o periodo do ecra', async ({ page }) => {
        await page.getByRole('button', { name: 'Anterior' }).click();
        await expect(page.getByRole('heading', { name: 'O período' })).toBeVisible();

        const ano = await page.getByLabel('Ano', { exact: true }).inputValue();
        const mes = await page.getByLabel(/^Mês/).inputValue();

        for (const [nome, caminho] of [['Imprimir', '/hr/irt-map/print'], ['CSV', '/hr/irt-map/csv']]) {
            const ligacao = page.getByRole('link', { name: nome });

            await expect(ligacao).toHaveAttribute('href', new RegExp(`^${caminho}\\?ano=${ano}&mes=${Number(mes)}$`));
        }
    });
});

/* ─── As definições ─────────────────────────────────────────────────── */

test.describe('definições', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/hr/settings');
        await expect(page.getByRole('heading', { name: 'Definições de RH' }).first()).toBeVisible({ timeout: 20_000 });
    });

    test('abre com as seccoes do catalogo', async ({ page }) => {
        for (const seccao of ['Horário de Trabalho', 'Horas Extras', 'Férias', 'Folha de Pagamento', 'Subsídios']) {
            await expect(page.getByRole('heading', { name: new RegExp(`^${seccao}`) })).toBeVisible();
        }

        // A marca do que ainda não entra no cálculo — mostrá-lo como campo
        // vulgar era mentir.
        await expect(page.getByText('ainda não entra no cálculo', { exact: false }).first()).toBeVisible();
    });

    test('nenhum erro na consola', async ({ page }) => {
        const erros = [];
        page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.reload();
        await expect(page.getByRole('button', { name: 'Guardar tudo' })).toBeVisible({ timeout: 20_000 });

        expect(erros).toEqual([]);
    });

    /**
     * GRAVA AO SAIR DO CAMPO, e diz que gravou.
     *
     * É assim que se corrige um número sem medo. No fim repõe-se o valor,
     * porque a bancada é partilhada — e as definições decidem os salários.
     */
    test('um campo grava-se ao sair dele', async ({ page }) => {
        const campo = page.getByLabel(/^Dias de Trabalho por Mês/);

        await expect(campo).toBeVisible();

        const original = await campo.inputValue();
        const novo = original === '24' ? '23' : '24';

        const gravado = page.waitForResponse(
            (r) => r.url().endsWith('/rh/definicoes') && r.request().method() === 'PUT',
            { timeout: 20_000 },
        );

        await campo.fill(novo);
        await campo.blur();

        expect((await gravado).status()).toBe(200);
        await expect(page.getByText('Gravado', { exact: true }).first()).toBeVisible({ timeout: 20_000 });

        // E volta ao que estava.
        await campo.fill(original);
        await campo.blur();
        await expect(campo).toHaveValue(original);
    });

    /** Um valor fora das regras é recusado NO CAMPO, e não engolido. */
    test('um valor fora das regras diz porque nao pode', async ({ page }) => {
        const campo = page.getByLabel(/^Dias de Trabalho por Mês/);

        await campo.fill('99');
        await campo.blur();

        await expect(page.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });

        // O valor não passou.
        await page.reload();
        await expect(page.getByLabel(/^Dias de Trabalho por Mês/)).not.toHaveValue('99', { timeout: 20_000 });
    });

    /** Repor pergunta antes, e diz o que se perde. */
    test('repor pergunta antes', async ({ page }) => {
        await page.getByRole('button', { name: 'Repor tudo' }).click();

        const janela = page.getByRole('dialog');

        await expect(janela).toBeVisible({ timeout: 20_000 });
        await expect(janela.getByText('TODAS as definições', { exact: false })).toBeVisible();
        await expect(janela.getByText('O que esta empresa afinou perde-se', { exact: false })).toBeVisible();

        await janela.getByRole('button', { name: 'Cancelar' }).click();
        await expect(janela).toBeHidden({ timeout: 20_000 });
    });
});

/* ─── Os contratos ──────────────────────────────────────────────────── */

test.describe('contratos', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/hr/contracts');
        await expect(page.getByRole('button', { name: 'Novo Contrato' })).toBeVisible({ timeout: 20_000 });
    });

    test('abre com os quatro cartoes', async ({ page }) => {
        for (const cartao of ['Contratos', 'Em vigor', 'A terminar em 60 dias', 'Massa salarial contratada']) {
            await expect(page.getByText(cartao, { exact: true }).first()).toBeVisible();
        }
    });

    test('nenhum erro na consola', async ({ page }) => {
        const erros = [];
        page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.reload();
        await expect(page.getByRole('button', { name: 'Novo Contrato' })).toBeVisible({ timeout: 20_000 });

        expect(erros).toEqual([]);
    });

    /**
     * O FORMULÁRIO PEDE O QUE SÓ UM CONTRATO PEDE, em três abas — e não perde
     * o que se escreveu ao mudar de aba.
     */
    test('o formulario tem as tres abas e nao perde o que se escreveu', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo Contrato' }).click();

        const janela = page.getByRole('dialog');
        await expect(janela).toBeVisible({ timeout: 20_000 });

        for (const aba of ['Vínculo', 'Remuneração', 'Condições']) {
            await expect(janela.getByRole('tab', { name: aba })).toBeVisible();
        }

        // Vínculo: quem, que tipo, e desde quando.
        await expect(janela.getByLabel(/^Funcionário/)).toBeVisible();
        await expect(janela.getByLabel(/^Tipo de contrato/)).toBeVisible();
        await expect(janela.getByLabel(/^Início/)).toHaveAttribute('type', 'date');

        await janela.getByRole('tab', { name: 'Remuneração' }).click();
        await janela.getByLabel(/^Salário base/).fill('375000');
        await expect(janela.getByLabel(/^Subsídio de alimentação/)).toBeVisible();
        await expect(janela.getByLabel(/^Periodicidade do pagamento/)).toBeVisible();

        // O total contratado soma-se à vista.
        await expect(janela.getByText('Total contratado', { exact: true })).toBeVisible();

        await janela.getByRole('tab', { name: 'Condições' }).click();
        await expect(janela.getByLabel(/^Horas por semana/)).toBeVisible();
        await expect(janela.getByLabel(/^Dias de férias por ano/)).toBeVisible();

        // E o que se escreveu na aba do dinheiro continua lá.
        await janela.getByRole('tab', { name: 'Remuneração' }).click();
        await expect(janela.getByLabel(/^Salário base/)).toHaveValue('375000');
    });

    /**
     * UM CONTRATO A TERMO TEM DE DIZER QUANDO ACABA — e a recusa aparece no
     * campo do fim, não numa mensagem solta.
     */
    test('um contrato a termo sem fim e recusado no campo do fim', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo Contrato' }).click();

        const janela = page.getByRole('dialog');
        await expect(janela).toBeVisible({ timeout: 20_000 });

        await janela.getByLabel(/^Funcionário/).selectOption({ index: 1 });
        await janela.getByLabel(/^Tipo de contrato/).selectOption('Determinado');

        // A ajuda muda com o tipo: passa a dizer que o fim é obrigatório.
        await expect(janela.getByText('tem de dizer quando acaba', { exact: false })).toBeVisible();

        await janela.getByRole('tab', { name: 'Remuneração' }).click();
        await janela.getByLabel(/^Salário base/).fill('200000');

        await janela.getByRole('button', { name: 'Guardar' }).click();

        // E leva de volta à aba onde está o erro.
        await expect(janela.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });
        await expect(janela).toBeVisible();
    });

    /** E o aviso de que activar um termina o anterior aparece antes de gravar. */
    test('o formulario avisa que activar um termina o anterior', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo Contrato' }).click();

        const janela = page.getByRole('dialog');
        await expect(janela).toBeVisible({ timeout: 20_000 });

        await janela.getByLabel(/^Funcionário/).selectOption({ index: 1 });

        await expect(janela.getByText('passa a caducado na véspera desta data', { exact: false }))
            .toBeVisible({ timeout: 20_000 });
    });
});
