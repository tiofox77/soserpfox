import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS CLIENTES EM REACT — o primeiro ecrã que escreve.
 *
 * A lista provou a leitura. Aqui prova-se o resto no browser a sério: gravar,
 * ver o erro do servidor no campo certo, e o travão de apagar quem tem
 * documentos.
 *
 * Escreve na empresa de bancada (`php artisan bancada:pwa`), com um NIF por
 * ensaio para dois ensaios não tropeçarem um no outro.
 */

const ECRA = '/invoicing/clients';

/** Um NIF de empresa que passa no verificador: 10 dígitos. */
function nifDeEnsaio() {
    return '5' + String(Date.now()).slice(-9);
}

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
});

test('a tabela abre com os clientes da empresa', async ({ page }) => {
    await expect(page.getByRole('columnheader', { name: 'NIF' })).toBeVisible();
    expect(await page.locator('tbody tr').count()).toBeGreaterThan(0);
});

test('cria um cliente e ele aparece na lista', async ({ page }) => {
    const nif = nifDeEnsaio();
    const nome = 'Ensaio React ' + nif.slice(-5);

    await page.getByRole('button', { name: /Novo Cliente/i }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible();

    await janela.getByLabel(/^NIF\b/).fill(nif);
    await janela.getByLabel(/^Nome\b/).fill(nome);
    await janela.getByRole('button', { name: 'Guardar' }).click();

    await expect(page.getByRole('status')).toContainText('Cliente criado', { timeout: 20_000 });

    // E está mesmo na lista, vindo do servidor.
    await page.getByPlaceholder('Nome, NIF, email ou telefone').fill(nif);

    /*
     * PROCURA-SE O NOME, E NÃO A CÉLULA INTEIRA.
     *
     * A célula do nome deixou de ser só o nome: leva a medalha e, por baixo, a
     * condição de pagamento do cliente — como o ecrã de sempre. Um
     * `cell, exact: true` compara o texto TODO da célula e passou a falhar
     * assim que a condição lá apareceu. O que interessa provar é que o nome
     * chegou à lista.
     */
    await expect(
        page.locator('tbody').getByText(nome, { exact: true }),
    ).toBeVisible({ timeout: 20_000 });
});

/**
 * O ERRO DO SERVIDOR VAI PARA O CAMPO A QUE PERTENCE.
 *
 * Um «não foi possível gravar» solto obriga a adivinhar qual dos dez campos
 * está mal.
 */
test('mostra o erro de validação no campo certo', async ({ page }) => {
    await page.getByRole('button', { name: /Novo Cliente/i }).click();

    const janela = page.getByRole('dialog');

    // Nome com duas letras e sem NIF: o servidor recusa os dois.
    await janela.getByLabel(/^Nome\b/).fill('ab');
    await janela.getByRole('button', { name: 'Guardar' }).click();

    await expect(janela.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });

    // A janela NÃO fecha com erro — o que se escreveu continua lá.
    await expect(janela).toBeVisible();
    await expect(janela.getByLabel(/^Nome\b/)).toHaveValue('ab');
});

/**
 * UM CLIENTE COM DOCUMENTOS NÃO SE APAGA.
 *
 * A factura aponta para ele: sem cliente, é um documento fiscal órfão e o
 * SAFT deixa de fechar. O botão fica apagado, e diz porquê.
 */
test('não deixa apagar quem tem documentos', async ({ page }) => {
    // A bancada tem facturas para o «Cliente da Bancada».
    await page.getByPlaceholder('Nome, NIF, email ou telefone').fill('Bancada');
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    const linha = page.locator('tbody tr').first();
    const documentos = Number(await linha.locator('td').nth(5).innerText());

    if (documentos > 0) {
        await expect(linha.locator('[aria-disabled="true"]')).toBeVisible();
        await expect(linha.getByRole('button', { name: /^Apagar / })).toHaveCount(0);
    }
});

/**
 * O ACESSO AO PORTAL DO CLIENTE ESTÁ NO FORMULÁRIO.
 *
 * A API aceitava-o desde sempre, mas o ecrã não o mostrava — e o que o ecrã
 * não mostra ninguém usa: nenhum cliente conseguia receber uma senha.
 *
 * E o portal autentica pelo email: o ecrã diz-lo ANTES de o servidor recusar,
 * porque descobrir a regra num 422 é descobri-la tarde.
 */
test('o acesso ao portal aparece e pede o email', async ({ page }) => {
    await page.getByRole('button', { name: /Novo Cliente/i }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible();

    const caixa = janela.getByLabel('Dar acesso ao portal do cliente');
    await expect(caixa).toBeVisible();

    // Com o acesso desligado não há senha nenhuma para escrever.
    await expect(janela.getByLabel('Senha do portal')).toHaveCount(0);

    await caixa.check();

    await expect(janela.getByText('é por lá que o cliente entra no portal')).toBeVisible();
    await expect(janela.getByLabel('Senha do portal')).toBeVisible();

    // Com email, o aviso sai da frente.
    // Exacto: o bloco do portal tem «Avisar o cliente por email», e o
    // getByLabel por pedaço apanhava os dois.
    await janela.getByLabel('Email', { exact: true }).fill('portal.ensaio@exemplo.ao');
    await expect(janela.getByText('é por lá que o cliente entra no portal')).toHaveCount(0);
});

/**
 * PROVÍNCIA → MUNICÍPIO, com as listas que vêm do servidor.
 *
 * O formulário só tinha província e cidade; o município e o bairro, que a API
 * grava e o SAFT leva, não tinham por onde ser escritos.
 */
test('escolher a província enche os municípios', async ({ page }) => {
    await page.getByRole('button', { name: /Novo Cliente/i }).click();

    const janela = page.getByRole('dialog');
    // Ancorado no princípio: o nome acessível de um `select` leva-lhe a opção
    // escolhida colada, e o «Escolha primeiro a província» do município fazia
    // um `getByLabel('Província')` apanhar os dois campos.
    const municipio = janela.getByLabel(/^Município/);

    // Sem província escolhida não há município que faça sentido.
    await expect(municipio).toBeDisabled();

    await janela.getByLabel(/^Província/).selectOption('Benguela');

    await expect(municipio).toBeEnabled();
    await expect(municipio.locator('option', { hasText: 'Lobito' })).toHaveCount(1);

    await municipio.selectOption('Lobito');
    await expect(municipio).toHaveValue('Lobito');

    // E o bairro sugere sem fechar: é um campo de texto com sugestões.
    await expect(janela.getByLabel('Bairro')).toBeEditable();
});

/**
 * A CONDIÇÃO DE PAGAMENTO E O CELULAR — os campos que a migração deixou cair.
 *
 * A condição é a que dá o VENCIMENTO às facturas deste cliente; o celular é o
 * número que a maioria dos clientes angolanos atende. Ambos estavam na ficha
 * de sempre e nenhum tinha por onde se escrever.
 */
test('o formulario tem condicao de pagamento, celular e logotipo', async ({ page }) => {
    await page.getByRole('button', { name: /Novo Cliente/i }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible();

    await expect(janela.getByLabel('Celular')).toBeVisible();
    await expect(janela.getByLabel('Logótipo')).toBeVisible();

    const condicao = janela.getByLabel('Condição de Pagamento');
    await expect(condicao).toBeVisible();

    /*
     * UM CLIENTE NOVO ABRE COM A CONDIÇÃO PADRÃO DA EMPRESA.
     *
     * O modelo põe-na a quem vier sem nenhuma, mas mostrá-la aqui deixa
     * TROCÁ-LA antes de gravar, em vez de descobrir o prazo depois, na primeira
     * factura.
     */
    await expect(condicao).not.toHaveValue('');
});

/**
 * A FICHA DO CLIENTE — o modal de VER, com o extrato.
 *
 * São as duas perguntas que se fazem antes de dar crédito: quanto já comprou, e
 * de quanto em quanto tempo volta. Faltavam a janela e as contas.
 */
test('a ficha do cliente abre com o extrato e os quatro separadores', async ({ page }) => {
    await page.locator('tbody [aria-label^="Ver "]').first().click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    // A ficha abre no primeiro separador, com os contactos.
    await expect(janela.getByText('Identificação')).toBeVisible();
    await expect(janela.getByText('Contactos')).toBeVisible();

    // O EXTRATO só se pede quando se abre o separador — não à cabeça.
    const pedido = page.waitForResponse(
        (r) => r.url().includes('/extrato') && r.request().method() === 'GET',
        { timeout: 20_000 },
    );

    await janela.getByRole('tab', { name: /Extrato/ }).click();
    expect((await pedido).ok()).toBe(true);

    await expect(janela.getByText('Ticket Médio')).toBeVisible({ timeout: 20_000 });

    /*
     * A MÉDIA DE DIAS NUNCA É NEGATIVA.
     *
     * O `diffInDays` do Carbon 3 devolve valor COM SINAL, e o cálculo herdado
     * do Livewire comparava cada data com a anterior: a ficha de um cliente
     * com 52 facturas mostrava «−0,1 dias».
     */
    await expect(janela.getByText(/^-/)).toHaveCount(0);

    // Os outros dois separadores abrem sem ir buscar nada de novo.
    await janela.getByRole('tab', { name: /Produtos/ }).click();
    await expect(janela.getByRole('columnheader', { name: 'Produto' })).toBeVisible();

    await janela.getByRole('tab', { name: /Frequência/ }).click();
    await expect(janela.getByText(/Frequência de Compra/)).toBeVisible();
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];

    page.on('console', (m) => {
        if (m.type() === 'error') erros.push(m.text());
    });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

