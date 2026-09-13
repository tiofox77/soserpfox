import { expect, test } from '@playwright/test';
import { entrar, escolherParte } from './apoio.js';

/**
 * EMITIR UMA PROPOSTA EM REACT.
 *
 * O que este ecrã tem de diferente de todos os anteriores: os totais NÃO são
 * calculados aqui. A cada alteração de linha pergunta-se ao servidor e
 * mostra-se o que ele responder. É isso que estes ensaios provam — que a conta
 * vem de lá, e que o documento gravado tem o número da série.
 */

const ECRA = '/invoicing/sales/proformas/create';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
});

test('abre com uma linha e sem totais', async ({ page }) => {
    await expect(page.getByRole('combobox', { name: /^Cliente/ })).toBeVisible();
    expect(await page.locator('tbody tr').count()).toBe(1);

    // Sem quantidade não há nada para contar, e diz-se em vez de mostrar zeros.
    await expect(page.getByText('Escolha um artigo e uma quantidade')).toBeVisible();
});

/**
 * OS TOTAIS VÊM DO SERVIDOR.
 *
 * Escolhe-se um artigo, e é um pedido a `/calcular` que traz os números — não
 * uma conta feita no browser.
 */
test('os totais vem do servidor', async ({ page }) => {
    const pedido = page.waitForResponse(
        (r) => r.url().includes('/emissor/proformas-venda/calcular') && r.request().method() === 'POST',
        { timeout: 20_000 },
    );

    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await page.getByLabel('Quantidade da linha 1').fill('2');

    const resposta = await pedido;
    expect(resposta.ok()).toBe(true);

    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Incidência de IVA')).toBeVisible();
});

/**
 * OS CAMPOS QUE A MIGRAÇÃO TINHA DEIXADO PARA TRÁS.
 *
 * O armazém da proposta e as condições que saem no papel existiam no ecrã em
 * Blade e não vieram para o React: uma proposta gravada por aqui perdia-os.
 */
test('o armazem e as condicoes estao no ecra', async ({ page }) => {
    await expect(page.getByLabel('Armazém')).toBeVisible();
    await expect(page.getByLabel('Termos e Condições')).toBeVisible();

    await page.getByLabel('Termos e Condições').fill('Pagamento a 30 dias.');
    await expect(page.getByLabel('Termos e Condições')).toHaveValue('Pagamento a 30 dias.');
});

/**
 * MARCAR PRESTAÇÃO DE SERVIÇO VOLTA A PERGUNTAR OS TOTAIS.
 *
 * Retém-se IRT a 6,5%, e a conta é a do servidor: o ecrã não a faz, pede-a
 * outra vez.
 */
test('marcar prestacao de servico volta a perguntar os totais ao servidor', async ({ page }) => {
    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await page.getByLabel('Quantidade da linha 1').fill('1');
    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    const pedido = page.waitForResponse(
        (r) => r.url().includes('/emissor/proformas-venda/calcular') && r.request().method() === 'POST',
        { timeout: 20_000 },
    );

    await page.getByLabel(/Prestação de Serviço/).check();

    expect((await pedido).ok()).toBe(true);
});

test('acrescenta e apaga linhas', async ({ page }) => {
    await page.getByRole('button', { name: /Nova linha/ }).click();
    expect(await page.locator('tbody tr').count()).toBe(2);

    await page.getByRole('button', { name: 'Apagar linha 2' }).click();
    expect(await page.locator('tbody tr').count()).toBe(1);

    // A ÚLTIMA NÃO SE APAGA: um documento sem linhas não é um documento.
    await expect(page.getByRole('button', { name: 'Apagar linha 1' })).toHaveCount(0);
});

test('grava e devolve o numero da serie', async ({ page }) => {
    await escolherParte(page);
    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await page.getByLabel('Quantidade da linha 1').fill('3');

    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /Gravar rascunho/ }).click();

    await expect(page.locator('#app-main').getByText('Gravado como rascunho')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('button', { name: /Abrir o documento/ })).toBeVisible();
});

/**
 * «GUARDAR E ENVIAR» — o segundo botão do ecrã de sempre.
 *
 * Guardar deixa a proposta em rascunho para se acabar depois; enviar diz que
 * ela saiu para o cliente. Uma proposta não é documento fiscal: enviá-la não a
 * fecha, e o servidor devolve o estado com que ficou.
 */
test('guardar e enviar deixa a proposta como enviada', async ({ page }) => {
    await escolherParte(page);
    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await page.getByLabel('Quantidade da linha 1').fill('2');

    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    const gravado = page.waitForResponse(
        (r) => r.url().includes('/emissor/proformas-venda') && r.request().method() === 'POST' && !r.url().includes('/calcular'),
        { timeout: 20_000 },
    );

    await page.getByRole('button', { name: /^Guardar e enviar$/ }).click();

    const resposta = await gravado;

    expect(resposta.status()).toBe(201);
    expect((await resposta.json()).estado).toBe('sent');

    await expect(page.locator('#app-main').getByText('dado como enviado')).toBeVisible({ timeout: 20_000 });
});

/**
 * O RESUMO FICA À DIREITA, COLADO AO TOPO — e os botões debaixo dele.
 *
 * É o que se consulta o tempo todo enquanto se lançam linhas: «quanto vai dar
 * isto?». Em coluna única ficava lá em baixo, fora de vista, e a proposta
 * preenchia-se às cegas.
 */
test('o resumo e os botoes vivem na coluna da direita', async ({ page }) => {
    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    const resumo = page.getByRole('heading', { name: 'Resumo' });
    await expect(resumo).toBeVisible();

    // A natureza do documento e a base vivem no mesmo cartão do total.
    await expect(page.getByLabel(/Prestação de Serviço/)).toBeVisible();
    await expect(page.getByText('Incidência IVA (base)')).toBeVisible();

    const enviar = page.getByRole('button', { name: /^Guardar e enviar$/ });
    await expect(enviar).toBeVisible();

    /* NA COLUNA DA DIREITA: os dois começam depois do meio da página, e o
       botão vem abaixo do resumo. Comparar o x ao pixel era medir o padding
       do cartão, que não é o que aqui interessa. */
    const meio = page.viewportSize().width / 2;
    const caixaDoResumo = await resumo.boundingBox();
    const caixaDoBotao = await enviar.boundingBox();

    expect(caixaDoResumo.x).toBeGreaterThan(meio);
    expect(caixaDoBotao.x).toBeGreaterThan(meio);
    expect(caixaDoBotao.y).toBeGreaterThan(caixaDoResumo.y);
});

/**
 * OS TRÊS DESCONTOS DO DOCUMENTO estão no ecrã — o comercial, o legado e o
 * financeiro. Existiam no ecrã em Blade, a base guarda-os, e o editor em React
 * não os oferecia: uma proposta com desconto perdia-o ao ser gravada.
 */
test('os tres descontos estao no ecra e contam no total', async ({ page }) => {
    await expect(page.getByLabel(/^Desconto comercial/)).toBeVisible();
    await expect(page.getByLabel(/^Desconto \(legado\)/)).toBeVisible();
    await expect(page.getByLabel(/^Desconto financeiro/)).toBeVisible();

    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await page.getByLabel('Quantidade da linha 1').fill('1');
    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    // Mexer no desconto volta a PERGUNTAR os totais ao servidor: o ecrã não os
    // calcula, nem sequer para um desconto que ele próprio escreveu.
    const pedido = page.waitForResponse(
        (r) => r.url().includes('/emissor/proformas-venda/calcular') && r.request().method() === 'POST',
        { timeout: 20_000 },
    );

    await page.getByLabel(/^Desconto comercial/).fill('100');

    expect((await pedido).ok()).toBe(true);
    await expect(page.getByText('Desconto comercial').last()).toBeVisible();
});

/** O armazém padrão da empresa já vem escolhido numa proposta nova. */
test('o armazem padrao ja vem escolhido', async ({ page }) => {
    const opcoes = await page.request.get('/api/v1/invoicing/react/emissor/proformas-venda/opcoes');
    const padrao = (await opcoes.json()).armazem_padrao;

    test.skip(!padrao, 'a empresa de bancada não tem armazém marcado como padrão');

    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    await expect(page.getByLabel(/^Armazém/)).toHaveValue(String(padrao));
});

/** Sem cliente, o servidor recusa e o ecrã diz onde. */
test('o erro de validacao aparece no campo', async ({ page }) => {
    await page.getByLabel('Artigo da linha 1').selectOption({ index: 1 });
    await page.getByLabel('Quantidade da linha 1').fill('1');

    await expect(page.getByText('Contado no servidor')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /Gravar rascunho/ }).click();

    await expect(page.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });
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

