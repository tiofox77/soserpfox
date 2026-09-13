import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O PONTO E A FOLHA EM REACT — os dois ecrãs onde um erro custa dinheiro.
 *
 * Uma falta a menos no ponto é dinheiro a mais no salário; uma folha paga
 * duas vezes desconta duas prestações de adiantamento. O que aqui se prova é
 * o que só se vê no browser:
 *
 *  · que o ponto abre no DIA DE HOJE e mostra QUEM AINDA NÃO PICOU, com o
 *    botão que marca a entrada num clique;
 *  · que a vista do mês é outra vista do mesmo ecrã, e volta atrás;
 *  · que o segundo ponto do mesmo dia é recusado NO CAMPO CERTO, em vez de
 *    entrar caladamente e contar a presença duas vezes;
 *  · que o ciclo da folha está sempre à vista — criar, conferir, aprovar,
 *    pagar — e que o «marcar paga» AVISA antes, porque é o passo que abate os
 *    adiantamentos e não se desfaz;
 *  · que a linha de cada trabalhador abre nos quatro grupos do recibo.
 *
 * As regras — permissões, horas contadas, meia-noite, deduções — estão
 * provadas em `ApiDoPontoEDaFolhaTest` e em `PresencaDuplicadaTest`.
 *
 * O DIA E O MÊS DE ENSAIO são antigos de propósito: a bancada é partilhada e
 * um ensaio que escreve no mês corrente estraga o que os outros medem.
 */

const DIA = '2019-03-04';
const ANO = 2019;

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

/* ─── Presenças ─────────────────────────────────────────────────────── */

test.describe('presenças', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/hr/attendance');
        await expect(page.getByRole('button', { name: 'Marcar ponto' })).toBeVisible({ timeout: 20_000 });
    });

    test('abre com a faixa, os cartoes e os tres botoes', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Presenças' }).first()).toBeVisible();

        for (const botao of ['Mês', 'Importar picagens', 'Marcar ponto']) {
            await expect(page.getByRole('button', { name: botao })).toBeVisible();
        }

        for (const cartao of ['Horas trabalhadas', 'Atrasos']) {
            await expect(page.getByText(cartao, { exact: true }).first()).toBeVisible();
        }
    });

    test('nenhum erro na consola', async ({ page }) => {
        const erros = [];
        page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.reload();
        await expect(page.getByRole('button', { name: 'Marcar ponto' })).toBeVisible({ timeout: 20_000 });

        expect(erros).toEqual([]);
    });

    /**
     * QUEM AINDA NÃO PICOU HOJE, e o clique que marca a entrada.
     *
     * É a razão de existir do cartão: no fim da manhã é esta a lista que
     * interessa, e escrever a hora à mão para cada um era o que se queria
     * evitar. Marca-se um, e ele sai da lista.
     */
    test('o cartao de por marcar hoje marca a entrada num clique', async ({ page }) => {
        const cartao = page.getByText('Por marcar hoje', { exact: true });

        // Se toda a gente já picou hoje, o cartão não existe — e não há nada
        // para provar aqui. A bancada tem três pessoas, por isso existe.
        await expect(cartao).toBeVisible();

        const chips = page.locator('button').filter({ has: page.locator('i.fa-right-to-bracket') });
        const antes = await chips.count();

        expect(antes).toBeGreaterThan(0);

        const nome = (await chips.first().innerText()).trim();

        const marcado = page.waitForResponse(
            (r) => r.url().includes('/rh/presencas/entrada/') && r.request().method() === 'POST',
            { timeout: 20_000 },
        );

        await chips.first().click();

        expect((await marcado).status()).toBe(201);

        // O recado diz a quem, e o nome sai da lista de quem falta.
        await expect(page.locator('[data-ensaio="avisos-de-canto"]')).toContainText(nome, { timeout: 20_000 });
        await expect(chips).toHaveCount(antes - 1, { timeout: 20_000 });

        // E aparece na tabela do dia, com a entrada preenchida e a saída por
        // marcar — que é o botão da coluna seguinte.
        const linha = page.getByRole('row').filter({ hasText: nome });
        await expect(linha.first()).toBeVisible();
        await expect(linha.first().getByRole('button', { name: 'Saída' })).toBeVisible();

        // E desfaz-se: a bancada é partilhada, e um ponto deixado para trás
        // tirava o nome da lista de quem falta na corrida seguinte.
        await linha.first().getByRole('button', { name: `Eliminar o ponto de ${nome}` }).click();
        await page.getByRole('dialog').getByRole('button', { name: 'Eliminar', exact: true }).click();
        await expect(page.getByRole('dialog')).toBeHidden({ timeout: 20_000 });
        await expect(chips).toHaveCount(antes, { timeout: 20_000 });
    });

    /** O MÊS é outra vista do mesmo ecrã — e volta-se à lista. */
    test('a vista do mes abre a grelha e volta ao dia', async ({ page }) => {
        await page.getByRole('button', { name: 'Mês' }).click();

        await expect(page.getByRole('heading', { name: 'O mês' })).toBeVisible({ timeout: 20_000 });
        await expect(page.getByRole('button', { name: 'Anterior' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Seguinte' })).toBeVisible();

        // A legenda diz o que cada letra da grelha quer dizer. A letra e a
        // palavra vivem no mesmo elemento («PPresente»), por isso a procura é
        // por pedaço e não por igualdade.
        for (const estado of ['Presente', 'Atrasado', 'Meio dia', 'Ausente', 'Doente', 'De férias']) {
            await expect(page.getByText(estado).first()).toBeVisible();
        }

        await page.getByRole('button', { name: 'Dia' }).click();
        await expect(page.getByRole('heading', { name: 'Filtros' })).toBeVisible({ timeout: 20_000 });
    });

    /** O formulário pede tudo o que um ponto tem — e as horas são horas. */
    test('o formulario do ponto pede o dia, o estado e as duas horas', async ({ page }) => {
        await page.getByRole('button', { name: 'Marcar ponto' }).click();

        const janela = page.getByRole('dialog');
        await expect(janela).toBeVisible({ timeout: 20_000 });

        await expect(janela.getByLabel(/^Funcionário/)).toBeVisible();
        await expect(janela.getByLabel(/^Dia/)).toHaveAttribute('type', 'date');
        await expect(janela.getByLabel(/^Estado/)).toBeVisible();
        await expect(janela.getByLabel(/^Entrada/)).toHaveAttribute('type', 'time');
        await expect(janela.getByLabel(/^Saída/)).toHaveAttribute('type', 'time');
        await expect(janela.getByLabel(/^Notas/)).toBeVisible();

        // A frase que explica a única conta que o ecrã não faz sozinho.
        await expect(janela.getByText('a saída antes da entrada é do dia seguinte', { exact: false })).toBeVisible();
    });

    /**
     * UM PONTO POR PESSOA E POR DIA — e a recusa aparece NO CAMPO.
     *
     * Duas linhas do mesmo dia contavam a presença a dobrar na folha. O ecrã
     * não a esconde nem a repete: diz-o debaixo do «Dia», e manda editar o
     * que lá está.
     */
    test('o segundo ponto do mesmo dia e recusado no campo do dia', async ({ page }) => {
        // O período de ensaio, para não mexer no dia de hoje da bancada.
        await page.getByLabel('De', { exact: true }).fill(DIA);
        await page.getByLabel('Até', { exact: true }).fill(DIA);
        await expect(page.getByRole('heading', { name: 'Filtros' })).toBeVisible();

        // Se ficou de uma corrida anterior, apaga-se primeiro.
        const lixo = page.getByRole('button', { name: /^Eliminar o ponto de / });

        if (await lixo.count() > 0) {
            await lixo.first().click();
            await page.getByRole('dialog').getByRole('button', { name: 'Eliminar', exact: true }).click();
            await expect(page.getByRole('dialog')).toBeHidden({ timeout: 20_000 });
        }

        const marcar = async () => {
            await page.getByRole('button', { name: 'Marcar ponto' }).click();

            const janela = page.getByRole('dialog');
            await expect(janela).toBeVisible({ timeout: 20_000 });

            await janela.getByLabel(/^Funcionário/).selectOption({ index: 1 });
            await janela.getByLabel(/^Dia/).fill(DIA);
            await janela.getByLabel(/^Entrada/).fill('08:00');
            await janela.getByLabel(/^Saída/).fill('16:00');
            await janela.getByRole('button', { name: 'Guardar' }).click();

            return janela;
        };

        const primeira = page.waitForResponse(
            (r) => r.url().endsWith('/rh/presencas') && r.request().method() === 'POST',
            { timeout: 20_000 },
        );

        const janela = await marcar();

        expect((await primeira).status()).toBe(201);
        await expect(janela).toBeHidden({ timeout: 20_000 });

        // Oito horas contadas — não escritas.
        await expect(page.getByRole('cell', { name: '8', exact: true }).first()).toBeVisible({ timeout: 20_000 });

        // E o mesmo outra vez: recusado, com a razão debaixo do campo.
        const segunda = await marcar();

        await expect(segunda.getByRole('alert').filter({ hasText: 'Já existe um ponto para esta pessoa neste dia' }))
            .toBeVisible({ timeout: 20_000 });
        await expect(segunda).toBeVisible();

        // Fecha-se e limpa-se o que o ensaio escreveu.
        await segunda.getByRole('button', { name: 'Cancelar' }).click();
        await expect(page.getByRole('dialog')).toBeHidden({ timeout: 20_000 });

        await page.getByRole('button', { name: /^Eliminar o ponto de / }).first().click();
        await page.getByRole('dialog').getByRole('button', { name: 'Eliminar', exact: true }).click();
        await expect(page.getByText('Nenhum ponto neste período')).toBeVisible({ timeout: 20_000 });
    });

    /** O ficheiro do relógio de ponto: o sistema e o ficheiro, e nada mais. */
    test('o modal de importar picagens pede o sistema e o ficheiro', async ({ page }) => {
        await page.getByRole('button', { name: 'Importar picagens' }).click();

        const janela = page.getByRole('dialog');
        await expect(janela).toBeVisible({ timeout: 20_000 });

        await expect(janela.getByLabel(/^Sistema/)).toBeVisible();
        await expect(janela.getByLabel(/^Ficheiro/)).toHaveAttribute('type', 'file');
        await expect(janela.getByText('Reimportar o mesmo ficheiro actualiza', { exact: false })).toBeVisible();

        // Sem ficheiro escolhido não há nada para importar.
        await expect(janela.getByRole('button', { name: 'Importar', exact: true })).toBeDisabled();
    });
});

/* ─── Folha de pagamento ────────────────────────────────────────────── */

test.describe('folha de pagamento', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/hr/payroll');
        await expect(page.getByRole('button', { name: 'Nova Folha' })).toBeVisible({ timeout: 20_000 });
    });

    test('abre com a faixa e os quatro cartoes', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Folha de Pagamento' }).first()).toBeVisible();

        for (const cartao of ['Folhas', 'Rascunhos', 'Aprovadas por pagar', 'Líquido do ano']) {
            await expect(page.getByText(cartao, { exact: true }).first()).toBeVisible();
        }
    });

    test('nenhum erro na consola', async ({ page }) => {
        const erros = [];
        page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.reload();
        await expect(page.getByRole('button', { name: 'Nova Folha' })).toBeVisible({ timeout: 20_000 });

        expect(erros).toEqual([]);
    });

    /**
     * CRIAR É JÁ CALCULAR, e conferir é abrir a linha de alguém.
     *
     * A folha nasce com a linha de cada trabalhador feita — o `createPayroll`
     * calcula-a — e o ecrã diz isso na frase do modal. No fim, o rascunho que
     * o ensaio criou é eliminado: assim corre outra vez amanhã.
     */
    test('uma folha cria-se ja calculada e a linha abre nos quatro grupos', async ({ page }) => {
        await page.getByLabel('Ano', { exact: true }).fill(String(ANO));

        // A LISTA DE 2019 TEM DE TER CHEGADO antes de se procurar o que ficou
        // para trás: contar os botões enquanto ela ainda mostra o ano
        // corrente dá zero, e a criação rebentava com «já existe».
        await expect(page.getByRole('button', { name: 'Marcar paga' })).toBeVisible({ timeout: 20_000 });

        // O que ficou de uma corrida anterior sai primeiro.
        const lixo = page.getByRole('button', { name: /^Eliminar a folha / });

        if (await lixo.count() > 0) {
            await lixo.first().click();
            await page.getByRole('dialog').getByRole('button', { name: 'Eliminar', exact: true }).click();
            await expect(page.getByRole('dialog')).toBeHidden({ timeout: 20_000 });
        }

        await page.getByRole('button', { name: 'Nova Folha' }).click();

        const nova = page.getByRole('dialog');
        await expect(nova).toBeVisible({ timeout: 20_000 });
        await expect(nova.getByText('nasce com a linha de cada trabalhador já calculada', { exact: false })).toBeVisible();

        await nova.getByLabel(/^Mês/).selectOption('1');
        await nova.getByLabel(/^Ano/).fill(String(ANO));

        const criada = page.waitForResponse(
            (r) => r.url().endsWith('/rh/folha') && r.request().method() === 'POST',
            { timeout: 30_000 },
        );

        await nova.getByRole('button', { name: 'Criar folha' }).click();

        expect((await criada).status()).toBe(201);

        // O detalhe abre sozinho: uma folha criada é para se conferir.
        const detalhe = page.getByRole('dialog');
        await expect(detalhe.getByText('Período:', { exact: false })).toBeVisible({ timeout: 30_000 });

        // A LINHA DE CADA TRABALHADOR, nos quatro grupos do recibo. Escolhe-se
        // pelo número: a bancada tem gente antiga sem salário nenhum, e uma
        // linha a zeros não provava que os grupos se preenchem.
        const linha = detalhe.getByRole('button', { expanded: false }).filter({ hasText: 'BANC-001' }).first();
        await expect(linha).toBeVisible();
        await linha.click();

        for (const grupo of ['Ganhos', 'Impostos', 'Descontos', 'Tempo']) {
            await expect(detalhe.getByRole('tab', { name: grupo })).toBeVisible();
        }

        await expect(detalhe.getByText('Salário base', { exact: true })).toBeVisible();

        await detalhe.getByRole('tab', { name: 'Tempo' }).click();
        await expect(detalhe.getByText('Dias úteis do mês', { exact: true })).toBeVisible();

        await expect(detalhe.getByRole('button', { name: 'Acertar descontos' })).toBeVisible();

        await detalhe.getByRole('button', { name: 'Fechar' }).last().click();
        await expect(page.getByRole('dialog')).toBeHidden({ timeout: 20_000 });

        // O CARTÃO DO MÊS, com o ciclo à vista: refazer as contas e aprovar,
        // que são os dois passos de um rascunho. O `visible` é preciso porque
        // o filtro de estado tem uma opção com o mesmo nome, escondida.
        await expect(page.getByText('Rascunho', { exact: true }).filter({ visible: true }).first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'Refazer as contas' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Aprovar' })).toBeVisible();

        // E sai o que o ensaio criou.
        await page.getByRole('button', { name: /^Eliminar a folha / }).first().click();
        await page.getByRole('dialog').getByRole('button', { name: 'Eliminar', exact: true }).click();
        await expect(page.getByRole('dialog')).toBeHidden({ timeout: 20_000 });
        await expect(page.getByRole('button', { name: 'Refazer as contas' })).toHaveCount(0);
    });

    /**
     * A FOLHA APROVADA: não se elimina, e o pagar avisa antes.
     *
     * Corre sobre a folha aprovada da bancada — Fevereiro de 2019 —, que
     * existe precisamente porque uma folha aprovada já não se apaga: um
     * ensaio que a aprovasse não poderia correr uma segunda vez.
     */
    test('a folha aprovada nao se elimina e o pagar avisa antes', async ({ page }) => {
        await page.getByLabel('Ano', { exact: true }).fill(String(ANO));

        // Visível, porque o filtro de estado tem uma opção com este nome.
        await expect(page.getByText('Aprovada', { exact: true }).filter({ visible: true }).first())
            .toBeVisible({ timeout: 20_000 });
        await expect(page.getByRole('button', { name: /^Eliminar a folha / })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Recalcular' })).toBeVisible();

        // PAGAR PERGUNTA ANTES, e diz o que mais acontece.
        await page.getByRole('button', { name: 'Marcar paga' }).first().click();

        const aviso = page.getByRole('dialog');
        await expect(aviso).toBeVisible({ timeout: 20_000 });
        await expect(aviso.getByText('abate a prestação dos adiantamentos', { exact: false })).toBeVisible();
        await expect(aviso.getByText('Não se desfaz', { exact: false })).toBeVisible();

        // Cancela-se: pagar é o passo que não se desfaz, e a bancada ficava
        // com uma folha paga para sempre.
        await aviso.getByRole('button', { name: 'Cancelar' }).click();
        await expect(page.getByRole('dialog')).toBeHidden({ timeout: 20_000 });
    });
});
