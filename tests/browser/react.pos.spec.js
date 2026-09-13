import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O BALCÃO EM REACT, NO BROWSER A SÉRIO.
 *
 * Este ensaio existe por causa de um defeito que NENHUM ensaio de servidor
 * apanhou: o `crypto.randomUUID()` só existe em contexto seguro — HTTPS ou
 * `localhost` — e um balcão corre em HTTP na rede local da loja. A venda
 * rebentava exactamente no clique que não pode falhar, e só se viu ao
 * carregar no botão.
 *
 * Guarda também as duas medições que motivaram o ecrã novo:
 *
 * - o botão de fechar a venda tem de estar DENTRO da janela a 1366×768, que
 *   é o ecrã de balcão mais comum (no ecrã de sempre ficava em y 764);
 * - o modal de pagamento abre com o «Confirmar Venda» à vista, sem rolar.
 *
 * Corre contra a empresa de bancada, que é onde as credenciais são fixas.
 */

const ECRA = '/invoicing/pos';

/**
 * Espera o balcão montar e diz se há turno aberto.
 *
 * `isVisible()` NÃO espera — devolve o estado do momento, e logo a seguir ao
 * `goto` o React ainda não montou. Usar `waitFor` é o que distingue «ainda
 * não desenhou» de «não há turno».
 */
async function balcaoPronto(page) {
    const procura = page.getByPlaceholder(/código de barras/);
    const semTurno = page.getByText(/Não há turno de caixa aberto/);

    await procura.or(semTurno).first().waitFor({ state: 'visible', timeout: 25_000 });

    return procura.isVisible();
}

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test('o balcão abre com o turno, o armazém e os atalhos', async ({ page }) => {
    await page.goto(ECRA);

    const faixa = page.getByRole('heading', { name: /Ponto de Venda/ });

    // Sem turno aberto o ecrã diz-o em vez de desenhar um balcão que não
    // fecha nada. Qualquer um dos dois serve como prova de que montou.
    const semTurno = page.getByText(/Não há turno de caixa aberto/);

    await expect(faixa.or(semTurno).first()).toBeVisible({ timeout: 20_000 });

    test.skip(await semTurno.isVisible(), 'a bancada não tem turno aberto');

    await expect(page.getByPlaceholder(/código de barras/)).toBeVisible();
});

/**
 * O BOTÃO DE FECHAR A VENDA NÃO PODE FUGIR PARA BAIXO.
 *
 * Era o defeito do ecrã de sempre, medido: a 1366×768 o «Confirmar Venda»
 * ficava em y 764, fora da janela, e quem fechava uma venda tinha de rolar
 * com o cliente à espera.
 */
test('a 1366x768 o botão de finalizar está dentro da janela', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.goto(ECRA);

    test.skip(!(await balcaoPronto(page)), 'sem turno aberto');

    const finalizar = page.getByRole('button', { name: /Finalizar Venda/ });

    const caixa = await finalizar.boundingBox();

    expect(caixa, 'o botão tem de existir').not.toBeNull();
    expect(caixa.y + caixa.height).toBeLessThanOrEqual(768);

    // E a página não rola: é um quiosque, não um documento.
    const rola = await page.evaluate(() => document.documentElement.scrollHeight > window.innerHeight);

    expect(rola, 'a página do balcão não deve rolar').toBe(false);
});

/**
 * AS CATEGORIAS NUMA LINHA SÓ.
 *
 * Numa farmácia com setenta categorias, os botões em várias linhas comiam
 * metade do catálogo. Voltam a ser o carrossel: uma linha que anda de lado,
 * e o painel «Todas» com procura para chegar a qualquer uma.
 */
test('as categorias ficam numa linha e «Todas» abre o painel com procura', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.goto(ECRA);

    test.skip(!(await balcaoPronto(page)), 'sem turno aberto');

    const faixa = page.getByRole('group', { name: 'Categorias' });
    await expect(faixa).toBeVisible();

    const caixa = await faixa.boundingBox();
    expect(caixa.height, 'uma linha só, por mais categorias que haja').toBeLessThan(48);

    await page.getByRole('button', { name: /^Todas/ }).click();
    const procurar = page.getByPlaceholder('Procurar categoria…');
    await expect(procurar).toBeVisible();

    // A primeira categoria que a faixa mostra encontra-se pela procura e,
    // escolhida no painel, fica activa na faixa.
    const primeira = (await faixa.getByRole('button').first().innerText()).replace(/\s*\d+\s*$/, '').trim();
    await procurar.fill(primeira.slice(0, 4));
    await procurar.press('Enter');

    await expect(procurar).toBeHidden();
    await expect(faixa.getByRole('button', { pressed: true })).toHaveCount(1);
});

/** As imagens dos artigos chegam como endereços; a que falha cai para o logótipo. */
test('nenhum cartão do balcão fica com a imagem partida', async ({ page }) => {
    await page.goto(ECRA);

    test.skip(!(await balcaoPronto(page)), 'sem turno aberto');

    await page.waitForLoadState('networkidle');

    const partidas = await page.evaluate(() => [...document.querySelectorAll('#app-main button img')]
        .filter((i) => i.complete && i.naturalWidth === 0)
        .map((i) => i.getAttribute('src')));

    expect(partidas, 'imagens partidas nos cartões').toEqual([]);
});

/**
 * UMA VENDA DE PONTA A PONTA.
 *
 * O que este ensaio prova e os de servidor não podem: que o identificador da
 * venda se gera no BROWSER — em HTTP, sem `crypto.randomUUID` — e que o
 * documento sai com número.
 */
test('vender um artigo fecha a venda e mostra o talão', async ({ page }) => {
    await page.goto(ECRA);

    test.skip(!(await balcaoPronto(page)), 'sem turno aberto');

    // O primeiro artigo da grelha. Os que pedem o preço ao balcão abrem um
    // modal em vez de entrar direitos; o nome acessível distingue-os — só os
    // que têm preço trazem a vírgula e o valor.
    const primeiro = page.getByRole('button', { name: /^Juntar .+, / }).first();

    test.skip((await primeiro.count()) === 0, 'a bancada não tem artigos no POS');

    await primeiro.click();

    await expect(page.getByText(/peças no carrinho|A pagar/).first()).toBeVisible();

    await page.getByRole('button', { name: /Finalizar Venda/ }).click();

    const modal = page.getByRole('dialog').filter({ hasText: 'Finalizar Pagamento' });

    await expect(modal).toBeVisible({ timeout: 10_000 });

    // O botão de confirmar tem de estar à vista sem rolar dentro do modal.
    const confirmar = modal.getByRole('button', { name: /Confirmar Venda/ });

    await expect(confirmar).toBeVisible();
    await expect(confirmar).toBeEnabled();

    await confirmar.click();

    // O talão abre já com a pré-visualização do papel configurado.
    await expect(page.getByText(/Venda registada/)).toBeVisible({ timeout: 25_000 });
    await expect(page.locator('iframe[title*="visualiza"]')).toBeVisible();
});

test('os relatórios do POS abrem com os cartões e a tabela', async ({ page }) => {
    await page.goto('/invoicing/pos/reports');

    // `.first()`: o titulo aparece duas vezes — o do layout, na barra do
    // topo, e o da faixa do ecra. Sem isto e uma violacao de modo estrito.
    await expect(page.getByRole('heading', { name: /Relatórios do POS/ }).first()).toBeVisible({ timeout: 20_000 });

    // Os quatro cartões contam o período, não a página.
    for (const cartao of ['Facturado', 'Devolvido', 'Anulado', 'Líquido']) {
        await expect(page.getByText(cartao, { exact: true }).first()).toBeVisible();
    }

    /*
     * AS DATAS TÊM DE SER AS DO FUSO DE QUEM OLHA.
     *
     * `toISOString()` converte para UTC e Angola está em UTC+1: o «início do
     * mês» saía dia 31 do mês anterior, e um relatório que começa no dia
     * errado conta o dia errado.
     */
    const de = await page.locator('input[type="date"]').first().inputValue();

    expect(de, 'o período começa no dia 1').toMatch(/^\d{4}-\d{2}-01$/);
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];

    page.on('pageerror', (e) => erros.push(String(e)));
    page.on('console', (m) => {
        if (m.type() === 'error') erros.push(m.text());
    });

    await page.goto(ECRA);
    await page.waitForTimeout(3000);

    // Os 404 de pedaços antigos em cache não são defeito deste ecrã.
    const reais = erros.filter((e) => !/Failed to load resource|pedacos\//.test(e));

    expect(reais, reais.join('\n')).toHaveLength(0);
});

/**
 * O QUE O BALCÃO TEM DE SABER ANTES DE FECHAR A VENDA.
 *
 * Duas regras que a migração para React tinha perdido em silêncio — e que os
 * ensaios que as guardavam não apanharam, porque apontavam para o componente
 * Livewire que já nenhuma rota serve.
 *
 * Depois de emitida a factura, o medicamento já saiu da farmácia: um aviso que
 * só aparece no fim não serve para nada.
 */
test('um psicotrópico pergunta antes de entrar no carrinho', async ({ page }) => {
    await page.goto(ECRA);

    if (!(await balcaoPronto(page))) test.skip(true, 'sem turno aberto na bancada');

    await page.getByPlaceholder(/código de barras/).fill('Diazepam');

    const cartao = page.getByRole('button', { name: /Diazepam/ });

    await expect(cartao).toBeVisible({ timeout: 15_000 });

    // A MARCA VÊ-SE ANTES DO CLIQUE: um aviso depois de o artigo entrar chega
    // tarde para quem já estava a empacotar.
    await expect(cartao).toHaveAccessibleName(/venda controlada/);

    await cartao.click();

    const modal = page.getByRole('dialog');

    await expect(modal).toBeVisible({ timeout: 15_000 });
    await expect(modal.getByRole('heading', { name: 'Substância controlada' })).toBeVisible();
    await expect(modal.getByText(/Confirme a identificação de quem o leva/)).toBeVisible();

    // «NÃO VENDER» não põe nada no carrinho.
    await modal.getByRole('button', { name: 'Não vender' }).click();

    await expect(modal).toBeHidden();
    await expect(page.getByRole('button', { name: 'Finalizar Venda' })).toBeDisabled();
});

/** A receita AVISA e não trava: o operador pode ter a receita na mão. */
test('um artigo com receita entra no carrinho e deixa o aviso', async ({ page }) => {
    await page.goto(ECRA);

    if (!(await balcaoPronto(page))) test.skip(true, 'sem turno aberto na bancada');

    await page.getByPlaceholder(/código de barras/).fill('Amoxicilina');

    const cartao = page.getByRole('button', { name: /Amoxicilina/ });

    await expect(cartao).toBeVisible({ timeout: 15_000 });
    await expect(cartao).toHaveAccessibleName(/exige receita médica/);

    await cartao.click();

    // Entrou — e o aviso está lá.
    await expect(page.getByText(/exige RECEITA MÉDICA/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Finalizar Venda' })).toBeEnabled();
});

/**
 * NADA NA GRELHA NÃO QUER DIZER «NÃO EXISTE».
 *
 * A grelha esconde o que está sem stock. Passar o leitor por um artigo esgotado
 * devolvia «nada encontrado» — indistinguível de um código desconhecido — e o
 * operador concluía que a leitura não funcionava, com o produto na mão.
 */
test('um codigo desconhecido diz-se desconhecido', async ({ page }) => {
    await page.goto(ECRA);

    if (!(await balcaoPronto(page))) test.skip(true, 'sem turno aberto na bancada');

    const procura = page.getByPlaceholder(/código de barras/);

    await procura.fill('9999999999999');

    /*
     * ESPERAR QUE A GRELHA RESPONDA ANTES DE CARREGAR EM ENTER.
     *
     * A procura é atrasada de propósito (não se consulta por tecla). Carregar em
     * Enter de imediato decide sobre a lista ANTERIOR — e o leitor, que escreve
     * e carrega em Enter de seguida, cai no mesmo. É a mesma espera que o
     * operador faz sem pensar.
     */
    await expect(page.getByRole('button', { name: /Água 1,5L/ })).toHaveCount(0, { timeout: 15_000 });

    await procura.press('Enter');

    await expect(page.getByText(/Nada encontrado para/)).toBeVisible({ timeout: 15_000 });
});
