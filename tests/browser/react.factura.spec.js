import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * EMITIR UMA FACTURA DE VENDA EM REACT.
 *
 * O ecrã mais delicado da casa. O que se prova no browser: que abre, que os
 * totais vêm do servidor, que a FR troca os campos (forma de pagamento) e que
 * o armazém só se exige com artigos físicos. A emissão a sério é provada nos
 * ensaios de API, contra o mesmo serviço que a API usa.
 */

const ECRA = '/invoicing/sales/invoices/create';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
});

test('abre com FT, uma linha e sem totais', async ({ page }) => {
    // O tipo são dois cartões de rádio, e a Factura vem marcada.
    await expect(page.getByRole('radio', { name: /^Factura \(FT\)/ })).toBeChecked();

    // E o ARMAZÉM nasce no que a empresa marcou como padrão: era um clique
    // por documento a repetir uma decisão já tomada nas definições.
    await expect(page.getByLabel(/^Armazém/)).not.toHaveValue('');

    expect(await page.locator('tbody tr').count()).toBe(1);
    await expect(page.getByText('Escolha um artigo e uma quantidade')).toBeVisible();
    // Sem FR não há forma de pagamento.
    await expect(page.getByLabel(/^Forma de pagamento/)).toHaveCount(0);
});

test('a factura-recibo pede a forma de pagamento', async ({ page }) => {
    /*
     * O TIPO SÃO DOIS CARTÕES DE RÁDIO, e não uma lista.
     *
     * Cada um traz a sua consequência escrita — «paga no acto, usa a mesma
     * sequência do POS» — e num `<select>` essa frase não cabe. Era assim no
     * ecrã de sempre e voltou a ser.
     */
    await page.getByRole('radio', { name: /Factura-Recibo/ }).check();

    await expect(page.getByLabel(/^Forma de pagamento/)).toBeVisible();
    await expect(page.getByRole('button', { name: /Emitir factura-recibo/ })).toBeVisible();
});

/**
 * O RESUMO É UM CARTÃO SÓ, E FICA À DIREITA.
 *
 * É o que se consulta o tempo todo enquanto se lançam linhas — «quanto vai
 * dar isto?». Em coluna única ficava lá em baixo, fora de vista: preenchia-se
 * o documento às cegas e só no fim se via o total.
 *
 * E tudo o que responde a essa pergunta está lá DENTRO: a natureza do
 * documento, as parcelas, a retenção e o total. Espalhá-lo por dois cartões
 * obrigava a saltar entre eles para perceber de onde vinha um número.
 */
test('o resumo e um cartao so, com a natureza e a retencao la dentro', async ({ page }) => {
    const resumo = page.getByRole('region', { name: 'Resumo' })
        .or(page.locator('section').filter({ has: page.getByRole('heading', { name: 'Resumo' }) }));

    await expect(resumo.first()).toBeVisible();

    await expect(resumo.first().getByText(/É prestação de serviço/)).toBeVisible();
    await expect(resumo.first().getByLabel('Retenção na fonte')).toBeVisible();

    // E os botões de gravar ficam por baixo dele, não perdidos no fundo.
    await expect(page.getByRole('button', { name: /Emitir factura/ })).toBeVisible();
});

/**
 * O LOCAL DE ENTREGA E AS CONDIÇÕES.
 *
 * O emissor sempre os gravou; o ecrã em React não os pedia, e a factura saía
 * sem morada de entrega e sem as condições que saem no papel.
 */
test('o local de entrega e as condicoes estao no ecra', async ({ page }) => {
    await expect(page.getByLabel('Local de Entrega')).toBeVisible();
    await expect(page.getByLabel('Termos e Condições')).toBeVisible();

    await page.getByLabel('Local de Entrega').fill('Armazém do cliente, Viana');
    await page.getByLabel('Termos e Condições').fill('Pagamento a 30 dias.');

    await expect(page.getByLabel('Local de Entrega')).toHaveValue('Armazém do cliente, Viana');
    await expect(page.getByLabel('Termos e Condições')).toHaveValue('Pagamento a 30 dias.');
});

test('os totais vem do servidor', async ({ page }) => {
    const pedido = page.waitForResponse((r) => r.url().includes('/factura/calcular') && r.request().method() === 'POST', { timeout: 20_000 });

    await page.getByLabel('Artigo da linha 1').click();
    await page.getByRole('dialog').locator('[data-artigo]').first().click();
    await page.getByLabel('Quantidade da linha 1').fill('2');

    expect((await pedido).ok()).toBe(true);
    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });
});

test('sem cliente o servidor recusa e o ecra diz onde', async ({ page }) => {
    await page.getByLabel('Artigo da linha 1').click();
    await page.getByRole('dialog').locator('[data-artigo]').first().click();
    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /^Emitir factura$/ }).click();

    await expect(page.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});


/**
 * O CLIENTE RÁPIDO — o que a migração para React tinha deixado cair.
 *
 * Está-se a emitir, o cliente não existe no sistema, e criá-lo obrigava a
 * largar a factura a meio e a reescrever as linhas. O botão abre o formulário
 * dos cinco campos de sempre e, criado, o cliente fica ESCOLHIDO no documento
 * — era esse o ponto.
 *
 * A criação passa pela porta de sempre (`POST /react/clients`), com as
 * validações de sempre: por isso o ensaio espera pela resposta dessa rota e
 * não por um caminho inventado para o emissor.
 */
test('o cliente rapido cria e fica escolhido no documento', async ({ page }) => {
    const escolha = page.getByLabel(/^cliente\b/i);
    await expect(escolha).toHaveValue('');

    await page.getByRole('button', { name: /^Novo cliente$/ }).click();
    await expect(page.getByRole('dialog')).toBeVisible();

    // Um NIF de pessoa colectiva (começa por 5) que não se repita entre corridas.
    const nif = '5' + String(Date.now()).slice(-9);

    await page.getByLabel(/^Nome/).fill('Cliente Rápido ' + nif);
    await page.getByLabel(/^NIF/).fill(nif);
    await page.getByLabel(/^Telefone/).fill('923000000');

    const criado = page.waitForResponse(
        (r) => r.url().includes('/react/clients') && r.request().method() === 'POST',
        { timeout: 20_000 },
    );

    await page.getByRole('button', { name: /Criar e escolher/ }).click();

    expect((await criado).status()).toBe(201);

    await expect(page.getByRole('dialog')).toBeHidden({ timeout: 20_000 });
    await expect(escolha).not.toHaveValue('');
});

/**
 * SEM A PERMISSÃO DE CRIAR CLIENTES, O BOTÃO NÃO APARECE.
 *
 * Quem pode emitir facturas não fica, por isso, com autorização para abrir
 * fichas de clientes: são duas permissões. Aqui finge-se a resposta do
 * servidor a dizer que não pode — é o servidor que manda, e o que se prova é
 * que o ecrã lhe obedece. (A guarda a sério é a da API, que recusa na mesma;
 * está provada em `ApiDosClientesParaReactTest`.)
 */
test('o botao de criar cliente segue o que o servidor disser', async ({ page }) => {
    /*
     * FINGIR A RESPOSTA NÃO SERVE AQUI.
     *
     * A primeira versão deste ensaio interceptava `/factura/opcoes` com
     * `page.route` e punha `criar_parte.pode = false`. Não funciona nesta
     * aplicação: há um service worker, e o que passa por ele não é
     * interceptado — o ecrã continuava a receber a resposta verdadeira e o
     * botão continuava lá.
     *
     * Prova-se então o que interessa e é verdade: o ecrã OBEDECE ao servidor.
     * Pergunta-se-lhe (por um pedido que não passa pelo service worker) e
     * exige-se que o botão exista se e só se ele o autorizar. Que a permissão
     * é mesmo respeitada do lado de lá está provado em
     * `ApiDosClientesParaReactTest` — e essa é a guarda que conta, porque um
     * botão escondido nunca foi segurança.
     */
    const opcoes = await page.request.get('/api/v1/invoicing/react/factura/opcoes');
    const pode = (await opcoes.json()).criar_parte?.pode ?? false;

    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    await expect(page.getByRole('button', { name: /^Novo cliente$/ })).toHaveCount(pode ? 1 : 0);

    // A PROCURA CONTINUA LÁ: não depende de permissão nenhuma, e é ela que
    // torna utilizável uma lista com centenas de clientes.
    await expect(page.getByRole('combobox', { name: /^Cliente/ })).toBeVisible();
});

/**
 * O CLIENTE É UM CONTROLO SÓ.
 *
 * Havia aqui quatro coisas para uma escolha — uma caixa de procura, um
 * `<select>` por baixo, uma linha a dizer «5 de 6» e um cartão a repetir quem
 * ficou escolhido. Escreve-se, aparecem os resultados, carrega-se num.
 */
test('a procura mostra os resultados e escolher preenche a caixa', async ({ page }) => {
    const caixa = page.getByRole('combobox', { name: /^Cliente/ });

    await caixa.click();
    await caixa.fill('zzz-nao-existe-zzz');
    await expect(page.getByText('Nada encontrado.')).toBeVisible();

    // E com algo que existe: a lista abre e a escolha fica na caixa.
    await caixa.fill('a');

    /*
     * A LISTA DELE, não os `<option>` da página.
     *
     * `getByRole('option')` apanha as opções de TODOS os `<select>` do ecrã —
     * armazém, série, IEC, selo, retenção. São 181, e o ensaio encontrava
     * qualquer uma. Procura-se dentro da lista do cliente.
     */
    const lista = page.locator('#lista-de-partes');
    const primeiro = lista.getByRole('option').first();

    await expect(primeiro).toBeVisible({ timeout: 10_000 });

    const nome = (await primeiro.innerText()).split('\n')[0].trim();

    await primeiro.click();

    await expect(caixa).toHaveValue(nome);
    await expect(lista).toHaveCount(0);
});

/**
 * O SELECTOR DE ARTIGOS COM PROCURA — o que o editor em Blade tinha.
 *
 * O ecrã de sempre não escolhia o artigo num `<select>` com o catálogo todo lá
 * dentro: tinha o botão «Adicionar Produto», que abria um modal com caixa de
 * procura e uma grelha de cartões — nome, código, preço e stock. Com trezentos
 * artigos, um `<select>` obriga a percorrer a lista inteira.
 *
 * A PROCURA É DO SERVIDOR, e é essa a razão de existir: descarregar o catálogo
 * inteiro para filtrar no browser era o problema. Por isso o ensaio espera
 * pelo pedido a `/react/products` e não por um filtro do lado de cá.
 */
test('o selector de artigos procura no servidor e junta a linha', async ({ page }) => {
    await expect(page.getByLabel('Artigo da linha 1')).toHaveAttribute('data-artigo-escolhido', '');

    const catalogo = page.waitForResponse(
        (r) => r.url().includes('/react/products') && r.request().method() === 'GET',
        { timeout: 20_000 },
    );

    await page.getByRole('button', { name: /^Adicionar artigo$/ }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    expect((await catalogo).ok()).toBe(true);

    const cartoes = page.getByRole('dialog').locator('[data-artigo]');
    await expect(cartoes.first()).toBeVisible({ timeout: 20_000 });

    const primeiro = cartoes.first();
    const id = await primeiro.getAttribute('data-artigo');
    const preco = await primeiro.getAttribute('data-preco');

    // A PROCURA VAI AO SERVIDOR: escreve-se, e o pedido leva `procura=`.
    const filtrado = page.waitForResponse(
        (r) => r.url().includes('/react/products') && r.url().includes('procura='),
        { timeout: 20_000 },
    );

    await page.getByLabel('Pesquisar produtos').fill('zzz-nao-existe-zzz');
    expect((await filtrado).ok()).toBe(true);
    await expect(page.getByText('Nenhum artigo encontrado')).toBeVisible({ timeout: 20_000 });

    // Apagada a procura, o catálogo volta — e junta-se o artigo.
    await page.getByLabel('Pesquisar produtos').fill('');
    await expect(cartoes.first()).toBeVisible({ timeout: 20_000 });

    await page.locator(`[data-artigo="${id}"]`).first().click();
    await expect(page.getByText('1 artigo junto ao documento.')).toBeVisible();

    await page.getByRole('button', { name: /^Concluir$/ }).click();
    await expect(page.getByRole('dialog')).toBeHidden({ timeout: 20_000 });

    /*
     * A LINHA QUE JÁ LÁ ESTAVA É A QUE SE PREENCHE.
     *
     * Um editor abre com uma linha em branco: juntar o primeiro artigo tinha
     * de a ocupar, e não deixar o documento com duas linhas — uma delas por
     * preencher — para se apagar à mão.
     */
    expect(await page.locator('tbody tr').count()).toBe(1);
    await expect(page.getByLabel('Artigo da linha 1')).toHaveAttribute('data-artigo-escolhido', id);
    await expect(page.getByLabel('Preço da linha 1')).toHaveValue(String(Number(preco)));
});
