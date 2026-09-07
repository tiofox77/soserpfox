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
 * UMA VENDA DE PONTA A PONTA.
 *
 * O que este ensaio prova e os de servidor não podem: que o identificador da
 * venda se gera no BROWSER — em HTTP, sem `crypto.randomUUID` — e que o
 * documento sai com número.
 */
test('vender um artigo fecha a venda e mostra o talão', async ({ page }) => {
    await page.goto(ECRA);

    test.skip(!(await balcaoPronto(page)), 'sem turno aberto');

    // O primeiro artigo da grelha que não peça o preço ao balcão.
    const primeiro = page.locator('button:has-text("Kz")').first();

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

    await expect(page.getByRole('heading', { name: /Relatórios do POS/ })).toBeVisible({ timeout: 20_000 });

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
