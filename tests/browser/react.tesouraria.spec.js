import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * A TESOURARIA EM REACT, num browser a sério.
 *
 * O varrimento de `react.todas-as-paginas` já prova que as dez moradas abrem.
 * Aqui prova-se o que só um browser prova: que os modais abrem com os campos
 * todos, que os filtros filtram, que os separadores dos relatórios trocam o
 * mapa, e que o caminho de estornar uma venda leva à nota de crédito em vez
 * de mexer no dinheiro por trás dela.
 *
 * NÃO SE GRAVA NADA. As contas e os saldos provam-se nos ensaios de API, que
 * correm numa base própria; escrever dinheiro na bancada deixava rasto em
 * todos os outros ensaios que a partilham.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

/**
 * A LISTA ASSENTOU — a tabela visível não chega.
 *
 * O `<table>` aparece antes das linhas: enquanto a API responde, o corpo tem
 * um esqueleto de carregamento. Um `count()` nesse instante devolve zero, e
 * um `test.skip` atrás dele salta o ensaio EM SILÊNCIO, o que é pior do que
 * falhar: fica verde a não provar nada. Espera-se pelo esqueleto sair.
 */
async function listaPronta(page) {
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
    await expect.poll(async () => await page.locator('[aria-busy="true"]').count(), { timeout: 20_000 }).toBe(0);
}

/* ─── Os movimentos ───────────────────────────────────────────────────── */

test('o modal de novo movimento traz os treze campos', async ({ page }) => {
    await page.goto('/treasury/transactions');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: 'Nova Transação' }).click();

    /*
     * DENTRO DA JANELA, e não na página.
     *
     * A lista por trás tem filtros com os mesmos nomes — Categoria, Conta,
     * Caixa — e um `getByLabel` solto encontra dois. Procurar dentro do
     * diálogo é o que faz a pergunta certa: «o MODAL tem este campo?».
     */
    const modal = page.getByRole('dialog');

    /*
     * PELO PAPEL E PELO NOME, e não por `getByLabel`.
     *
     * Os campos que têm uma linha de ajuda por baixo — a Categoria, aqui, e a
     * Taxa nas transferências — guardam-na DENTRO do `<label>`, e o
     * `getByLabel` deixa de os encontrar. O nome acessível inclui-a, e é esse
     * que um leitor de ecrã lê: é por ele que se pergunta.
     */
    for (const [papel, nome] of [
        ['combobox', /^Tipo\b/], ['combobox', /^Categoria\b/], ['spinbutton', /^Valor\b/],
        ['combobox', /^Moeda\b/], ['textbox', /^Data da Transação\b/], ['combobox', /^Método de Pagamento\b/],
        ['combobox', /^Conta Bancária\b/], ['combobox', /^Caixa\b/], ['textbox', /^Referência\b/],
        ['combobox', /^Status\b/], ['textbox', /^Descrição\b/], ['textbox', /^Notas\b/],
    ]) {
        await expect(modal.getByRole(papel, { name: nome })).toBeVisible();
    }

    // E O AVISO DO DESTINO, que é o que evita a pergunta «porque é que o
    // saldo não mexeu?».
    await expect(modal.getByText(/nunca os dois/i)).toBeVisible();

    await page.getByRole('button', { name: /^Cancelar$/ }).click();
    // O diálogo INTEIRO fecha. Perguntar por um campo lá dentro passaria à
    // mesma se o nome estivesse errado — um locator que não encontra nada
    // está «escondido» e a asserção fica verde sem provar coisa nenhuma.
    await expect(modal).toBeHidden();
});

test('a ficha de um movimento abre com o documento por tras', async ({ page }) => {
    await page.goto('/treasury/transactions');
    await listaPronta(page);

    const ver = page.getByRole('button', { name: /^Ver / }).first();

    test.skip(!(await ver.count()), 'a bancada não tem movimentos');

    await ver.click();

    await expect(page.getByRole('heading', { name: 'Detalhes da Transação' })).toBeVisible();
    await expect(page.getByText('Número da Transação')).toBeVisible();
    await expect(page.getByText('Informações de Auditoria')).toBeVisible();
});

/**
 * ESTORNAR UMA VENDA LEVA À NOTA DE CRÉDITO.
 *
 * Um movimento com factura por trás não se anula com um movimento de sinal
 * contrário: anular uma venda é emitir uma nota de crédito, com linhas
 * escolhidas, imposto recalculado, stock reposto e comunicação à AGT. O
 * botão tem de ser uma LIGAÇÃO para lá, e não um modal de estorno.
 */
test('creditar uma venda leva ao relatorio do pos, nao a um estorno', async ({ page }) => {
    await page.goto('/treasury/transactions');
    await listaPronta(page);

    const creditar = page.getByRole('link', { name: /^Creditar .* com nota de crédito$/ }).first();

    test.skip(!(await creditar.count()), 'a bancada não tem vendas com factura');

    await expect(creditar).toHaveAttribute('href', /\/invoicing\/pos\/reports\?credit_transaction=\d+/);
});

test('limpar filtros volta a mostrar tudo', async ({ page }) => {
    await page.goto('/treasury/transactions');
    await listaPronta(page);

    const total = async () => (await page.locator('[data-ecra] tbody tr').count());
    const antes = await total();

    test.skip(antes === 0, 'a bancada não tem movimentos');

    // Uma procura que não apanha nada — e a lista fica vazia.
    await page.getByPlaceholder(/Número, descrição ou referência/).fill('zzz-nao-existe-zzz');
    await expect(page.getByText('Nenhuma transação encontrada')).toBeVisible({ timeout: 15_000 });

    await page.getByRole('button', { name: 'Limpar filtros' }).click();
    await expect.poll(total, { timeout: 15_000 }).toBe(antes);
});

/* ─── As transferências ───────────────────────────────────────────────── */

test('o modal de transferencia agrupa contas e caixas', async ({ page }) => {
    await page.goto('/treasury/transfers');
    await expect(page.getByRole('heading', { name: 'Transferências entre Contas' })).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: 'Nova Transferência' }).click();

    const modal = page.getByRole('dialog');

    // Pelo papel e pelo nome: a Taxa leva a ajuda dentro do `<label>`, e o
    // `getByLabel` deixa de a encontrar.
    for (const [papel, nome] of [
        ['combobox', /^De\b/], ['combobox', /^Para\b/], ['spinbutton', /^Valor\b/],
        ['spinbutton', /^Taxa\b/], ['textbox', /^Data\b/], ['combobox', /^Moeda\b/],
        ['textbox', /^Descrição\b/], ['textbox', /^Referência\b/],
    ]) {
        await expect(modal.getByRole(papel, { name: nome })).toBeVisible();
    }

    /*
     * AS LISTAS VÊM AGRUPADAS. Uma conta bancária e um caixa não são a mesma
     * coisa, e uma lista corrida de vinte nomes não o diz.
     *
     * Um grupo VAZIO não se desenha — e a bancada pode não ter contas nem
     * caixas nenhuns. O que se exige é que cada bolso que exista esteja
     * dentro de um grupo, e não solto ao lado do «Selecionar origem…».
     */
    const origem = modal.getByRole('combobox', { name: /^De\b/ });
    const bolsos = (await origem.locator('option').count()) - 1;

    if (bolsos > 0) {
        await expect(origem.locator('optgroup')).not.toHaveCount(0);
        await expect(origem.locator('optgroup option')).toHaveCount(bolsos);
    }

    // A TAXA SAI DA ORIGEM, e o campo di-lo: sem isso, quem a preenche fica
    // sem saber de que lado é que ela desconta.
    await expect(modal.getByText('Sai também da origem.')).toBeVisible();

    await page.getByRole('button', { name: /^Cancelar$/ }).click();
    await expect(modal).toBeHidden();
});

/* ─── Os catálogos ────────────────────────────────────────────────────── */

/**
 * UM REGISTO ABRE COM O VALOR QUE TEM.
 *
 * Ao passar os catálogos para o ecrã genérico escreveram-se listas de
 * escolha que não batiam certo com a base: as formas de pagamento traziam
 * `bank` e `manual`, que não existem, sem `bank_transfer`, `digital_wallet`
 * nem `check`, que existem em dezenas. Abrir a «Transferência Bancária» que
 * já lá está dava um campo EM BRANCO — e guardar dava erro de validação
 * sobre o valor do próprio registo.
 *
 * É o género de defeito que nenhum ensaio de API apanha se o ensaio usar os
 * mesmos valores errados: o que o prova é abrir o que a empresa tem.
 */
test('editar uma forma de pagamento mostra o tipo que ela tem', async ({ page }) => {
    await page.goto('/treasury/payment-methods');
    await listaPronta(page);

    /*
     * SEM `test.skip` À FRENTE DE UM `count()`.
     *
     * A empresa de bancada TEM uma forma de transferência — nasce com ela. Um
     * guarda de contagem aqui só serviria para o ensaio se calar quando o
     * ecrã não desenhasse a lista, que é precisamente o caso que ele existe
     * para apanhar. Espera-se pelo botão, e se não vier, falha.
     */
    const editar = page.getByRole('button', { name: /^Editar: Transferência/i }).first();

    await expect(editar).toBeVisible({ timeout: 20_000 });
    await editar.click();

    const tipo = page.getByRole('dialog').getByRole('combobox', { name: /^Tipo\b/ });

    await expect(tipo).toBeVisible();
    await expect(tipo).not.toHaveValue('', { timeout: 10_000 });
});

/* ─── O painel ────────────────────────────────────────────────────────── */

test('o painel diz que o saldo nao segue o periodo', async ({ page }) => {
    await page.goto('/treasury/dashboard');
    await expect(page.getByRole('heading', { name: 'Tesouraria' }).first()).toBeVisible({ timeout: 20_000 });

    // O SALDO É DE AGORA e os outros números são do período. Dizê-lo evita a
    // pergunta de porque é que um mudou ao trocar de semana e o outro não.
    await expect(page.getByText('Agora, não do período')).toBeVisible();

    await expect(page.getByText('Facturar não é receber')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Movimentos recentes' })).toBeVisible();

    /*
     * TROCAR DE PERÍODO NÃO MEXE NO SALDO TOTAL.
     *
     * O cartão é o único da página com a nota «Agora, não do período»: é por
     * ela que se lhe chega sem depender da forma da caixa à volta. Que os
     * números batem certo prova-se na API; aqui prova-se que o botão troca o
     * período sem o saldo ir atrás.
     */
    const cartaoDoSaldo = page.getByText('Agora, não do período').locator('xpath=..');
    const antes = (await cartaoDoSaldo.innerText()).trim();

    await page.getByRole('button', { name: 'Este ano' }).click();

    // O período muda de facto: o saldo do período segue-o.
    await expect(page.getByRole('button', { name: 'Este ano' })).toHaveAttribute('aria-pressed', 'true');
    await expect.poll(async () => (await cartaoDoSaldo.innerText()).trim(), { timeout: 15_000 }).toBe(antes);
});

/* ─── Os relatórios ───────────────────────────────────────────────────── */

test('os quatro relatorios trocam o mapa', async ({ page }) => {
    await page.goto('/treasury/reports');
    await expect(page.getByRole('heading', { name: 'Relatórios Financeiros' })).toBeVisible({ timeout: 20_000 });

    // O fluxo de caixa abre por omissão e fecha a conta.
    await expect(page.getByText('Saldo inicial')).toBeVisible();
    await expect(page.getByText('Saldo final')).toBeVisible();

    await page.getByRole('tab', { name: 'Demonstração de Resultados' }).click();
    await expect(page.getByText('Receita bruta')).toBeVisible({ timeout: 15_000 });
    // O QUE O MAPA NÃO SABE está escrito nele: sem isto era um número que
    // ninguém consegue justificar.
    await expect(page.getByText(/Aproximação/)).toBeVisible();

    /*
     * `exact: true` NOS DOIS ÚLTIMOS.
     *
     * «Por receber» também aparece dentro de «Nada por receber neste
     * período», e uma procura solta encontra os dois. O rótulo do cartão é o
     * que interessa aqui.
     */
    await page.getByRole('tab', { name: 'Contas a Receber' }).click();
    await expect(page.getByText('Por receber', { exact: true })).toBeVisible({ timeout: 15_000 });
    await expect(page.getByText('Já vencido', { exact: true })).toBeVisible();

    await page.getByRole('tab', { name: 'Contas a Pagar' }).click();
    await expect(page.getByText('Por pagar', { exact: true })).toBeVisible({ timeout: 15_000 });
});

test('as descargas levam o tipo e as datas escolhidas', async ({ page }) => {
    await page.goto('/treasury/reports');
    await expect(page.getByRole('heading', { name: 'Relatórios Financeiros' })).toBeVisible({ timeout: 20_000 });

    await page.getByRole('tab', { name: 'Demonstração de Resultados' }).click();
    await expect(page.getByText('Receita bruta')).toBeVisible({ timeout: 15_000 });

    // AS MORADAS VÊM DO SERVIDOR. O ecrã não compõe o URL de um relatório, e
    // por isso não se engana no tipo nem nas datas.
    for (const nome of ['PDF', 'Excel']) {
        await expect(page.getByRole('link', { name: nome })).toHaveAttribute('href', /tipo=dre.*de=\d{4}-\d{2}-\d{2}.*ate=\d{4}-\d{2}-\d{2}/);
    }
});
