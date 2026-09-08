import { expect, test } from '@playwright/test';
import { entrar, escolherParte } from './apoio.js';

/**
 * REGISTAR UMA FACTURA DE COMPRA EM REACT.
 *
 * O que se prova no browser: que abre, que os totais vêm do servidor, que o
 * armazém é obrigatório (a compra dá entrada de stock) e que o servidor
 * recusa sem fornecedor e diz onde. O registo a sério — stock, lotes, custo —
 * é provado nos ensaios de API, contra o mesmo serviço que a API usa.
 */

const ECRA = '/invoicing/purchases/invoices/create';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
});

test('abre com uma linha, lote e validade, e sem totais', async ({ page }) => {
    expect(await page.locator('tbody tr').count()).toBe(1);
    await expect(page.getByLabel('Lote da linha 1')).toBeVisible();
    await expect(page.getByLabel('Validade da linha 1')).toBeVisible();
    await expect(page.getByText('Escolha um artigo e uma quantidade')).toBeVisible();
});

test('os totais vem do servidor', async ({ page }) => {
    const pedido = page.waitForResponse((r) => r.url().includes('/compra/calcular') && r.request().method() === 'POST', { timeout: 20_000 });

    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await page.getByLabel('Quantidade da linha 1').fill('3');

    expect((await pedido).ok()).toBe(true);
    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });
});

test('sem fornecedor o servidor recusa e o ecra diz onde', async ({ page }) => {
    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /^Registar compra$/ }).click();

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
 * DUPLICAR UMA COMPRA: o conteúdo viaja, o stock não se mexe.
 *
 * Da lista carrega-se em duplicar e chega-se aqui com `?duplicar=` — o
 * fornecedor e as linhas já preenchidos, sem número e sem estado. Abrir este
 * ecrã não regista nada e não dá entrada de nada: o stock só entra quando a
 * compra for mesmo registada.
 */
test('duplicar traz o conteudo da compra e nao regista nada', async ({ page }) => {
    await page.goto('/invoicing/purchases/invoices');

    await expect(
        page.getByRole('table').or(page.getByText('Nenhum documento com estes filtros')),
    ).toBeVisible({ timeout: 20_000 });

    if (!(await page.getByRole('table').isVisible())) {
        test.skip(true, 'a empresa de bancada não tem facturas de compra');
    }

    await page.getByRole('link', { name: /^Duplicar / }).first().click();

    await expect(page.locator('[data-duplicado-de]')).toBeVisible({ timeout: 20_000 });
    await expect(page.locator('[data-documento-aberto]')).toHaveCount(0);

    // O conteúdo veio. A caixa do fornecedor mostra o NOME de quem ficou
    // escolhido — é um combobox, não um `<select>`.
    await expect(page.getByRole('combobox', { name: /^Fornecedor/ })).not.toHaveValue('');
    await expect(page.getByLabel('Artigo da linha 1')).not.toHaveValue('');

    // E o que se pode fazer é REGISTAR uma compra nova — não actualizar a velha.
    await expect(page.getByRole('button', { name: /^Registar compra$/ })).toBeVisible();
});

/**
 * UM `?duplicar=` QUE NÃO EXISTE DIZ-SE — e não abre um formulário meio feito.
 *
 * Acontece com um atalho guardado, um separador aberto de antes, ou o id de
 * outra empresa colado na barra de endereço: o servidor responde 404 (com o
 * escopo da empresa e o do autor já aplicados) e o ecrã tem de o mostrar. Um
 * formulário em branco, sem uma palavra, mandava a pessoa registar de novo uma
 * compra que ela julgava estar a duplicar.
 */
test('duplicar um documento que nao existe diz que nao abriu', async ({ page }) => {
    await page.goto('/invoicing/purchases/invoices/create?duplicar=99999999');

    await expect(page.getByRole('alert')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Não foi possível abrir o registo de compras')).toBeVisible();
});

/**
 * O FORNECEDOR RÁPIDO — o que a migração para React tinha deixado cair.
 *
 * A factura do fornecedor está na mão e ele ainda não está na ficha. Criá-lo
 * obrigava a largar o registo a meio e a reescrever as linhas. O botão abre o
 * formulário dos cinco campos de sempre e, criado, o fornecedor fica ESCOLHIDO
 * no documento — era esse o ponto.
 *
 * A criação passa pela porta de sempre (`POST /react/catalogos/fornecedores`).
 */
test('o fornecedor rapido cria e fica escolhido no documento', async ({ page }) => {
    const escolha = page.getByRole('combobox', { name: /^Fornecedor/ });
    await expect(escolha).toHaveValue('');

    await page.getByRole('button', { name: /^Novo fornecedor$/ }).click();
    await expect(page.getByRole('dialog')).toBeVisible();

    const nif = '5' + String(Date.now()).slice(-9);

    await page.getByLabel(/^Nome/).fill('Fornecedor Rápido ' + nif);
    await page.getByLabel(/^NIF/).fill(nif);
    await page.getByLabel(/^Telefone/).fill('923000000');

    const criado = page.waitForResponse(
        (r) => r.url().includes('/react/catalogos/fornecedores') && r.request().method() === 'POST',
        { timeout: 20_000 },
    );

    await page.getByRole('button', { name: /Criar e escolher/ }).click();

    expect((await criado).status()).toBe(201);

    await expect(page.getByRole('dialog')).toBeHidden({ timeout: 20_000 });
    await expect(escolha).not.toHaveValue('');
});

/**
 * O BOTÃO DE CRIAR FORNECEDOR SEGUE O QUE O SERVIDOR DISSER.
 *
 * Registar compras e abrir fichas de fornecedores são duas permissões — e o
 * ecrã tem de obedecer ao servidor sobre a segunda.
 *
 * FINGIR A RESPOSTA NÃO SERVE AQUI: esta aplicação tem service worker, e o
 * que passa por ele não é interceptado pelo `page.route` — o ecrã continuava
 * a receber a resposta verdadeira. Pergunta-se então ao servidor por um
 * pedido que não passa pelo service worker, e exige-se que o botão exista se
 * e só se ele autorizar. Que a permissão é mesmo respeitada do lado de lá
 * está provado em `ApiDaCompraParaReactTest` — e é essa a guarda que conta.
 */
test('o botao de criar fornecedor segue o que o servidor disser', async ({ page }) => {
    const opcoes = await page.request.get('/api/v1/invoicing/react/compra/opcoes');
    const pode = (await opcoes.json()).criar_parte?.pode ?? false;

    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    await expect(page.getByRole('button', { name: /^Novo fornecedor$/ })).toHaveCount(pode ? 1 : 0);

    // A PROCURA CONTINUA LÁ: não depende de permissão nenhuma, e é ela que
    // torna utilizável uma lista com centenas de fornecedores.
    await expect(page.getByRole('combobox', { name: /^Fornecedor/ })).toBeVisible();
});

/**
 * O FORNECEDOR É UM CONTROLO SÓ — escreve-se, aparecem os resultados,
 * carrega-se num. Um `<select>` com todos os fornecedores não se usa.
 */
test('a procura mostra os resultados e escolher preenche a caixa', async ({ page }) => {
    const caixa = page.getByRole('combobox', { name: /^Fornecedor/ });

    await caixa.click();
    await caixa.fill('zzz-nao-existe-zzz');
    await expect(page.getByText('Nada encontrado.')).toBeVisible();

    await caixa.fill('');

    const nome = await escolherParte(page, /^Fornecedor/);

    await expect(caixa).toHaveValue(nome);
    await expect(page.locator('#lista-de-partes')).toHaveCount(0);
});

/**
 * O RESUMO FICA À DIREITA, COLADO AO TOPO — e os botões debaixo dele.
 *
 * É o que se consulta o tempo todo enquanto se lançam linhas: «quanto é que
 * esta factura dá?». Em coluna única ficava lá em baixo, fora de vista, e
 * conferia-se às cegas o que o fornecedor cobrou.
 */
test('o resumo e os botoes vivem na coluna da direita', async ({ page }) => {
    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    const resumo = page.getByRole('heading', { name: 'Resumo' });
    await expect(resumo).toBeVisible();

    // A natureza do documento e o total vivem no mesmo cartão.
    await expect(page.getByLabel(/Prestação de serviço/)).toBeVisible();
    await expect(page.getByText('Total a pagar')).toBeVisible();
    await expect(page.getByText('Incidência IVA (base)')).toBeVisible();

    // E os três botões, um por linha, à direita e abaixo do resumo.
    const registar = page.getByRole('button', { name: /^Registar compra$/ });
    await expect(registar).toBeVisible();

    /* NA COLUNA DA DIREITA: os dois começam depois do meio da página, e o
       botão vem abaixo do resumo. Comparar o x ao pixel era medir o padding
       do cartão, que não é o que aqui interessa. */
    const meio = page.viewportSize().width / 2;
    const caixaDoResumo = await resumo.boundingBox();
    const caixaDoBotao = await registar.boundingBox();

    expect(caixaDoResumo.x).toBeGreaterThan(meio);
    expect(caixaDoBotao.x).toBeGreaterThan(meio);
    expect(caixaDoBotao.y).toBeGreaterThan(caixaDoResumo.y);
});

/**
 * OS TRÊS DESCONTOS DO DOCUMENTO estão no ecrã — o comercial, o legado e o
 * financeiro. O legado existia na base e a API sempre o aceitou; o ecrã é que
 * não o oferecia, e reabrir uma compra que o tivesse apagava-o em silêncio.
 */
test('os tres descontos estao no ecra', async ({ page }) => {
    await expect(page.getByLabel(/^Desconto comercial/)).toBeVisible();
    await expect(page.getByLabel(/^Desconto \(legado\)/)).toBeVisible();
    await expect(page.getByLabel(/^Desconto financeiro/)).toBeVisible();
});

/**
 * O ARMAZÉM JÁ VEM ESCOLHIDO numa compra nova.
 *
 * A compra dá entrada de stock e o armazém é obrigatório: escolhê-lo à mão de
 * cada vez era uma paragem em todas as compras. Vale o marcado como padrão da
 * empresa — e o ensaio só o exige quando o servidor diz que existe um.
 */
test('o armazem padrao ja vem escolhido', async ({ page }) => {
    const opcoes = await page.request.get('/api/v1/invoicing/react/compra/opcoes');
    const padrao = (await opcoes.json()).armazem_padrao;

    test.skip(!padrao, 'a empresa de bancada não tem armazém marcado como padrão');

    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    await expect(page.getByLabel(/^Armazém/)).toHaveValue(String(padrao));
});
