import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O PAINEL DA FACTURAÇÃO EM REACT.
 *
 * O que este ecrã prova, e nenhum dos outros provava: um gráfico desenhado
 * sem biblioteca nenhuma, e os números a virem do MESMO serviço que o painel
 * em Blade usa — que é a razão de os dois poderem existir ao mesmo tempo.
 */

const ECRA = '/invoicing/dashboard';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
});

test('mostra os cartões e o gráfico do ano', async ({ page }) => {
    await expect(page.getByText('Faturação do Mês')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Recebimentos', { exact: true })).toBeVisible();
    await expect(page.getByText('Valores Vencidos', { exact: true })).toBeVisible();

    // O gráfico é SVG/HTML, não canvas: lê-se pelo rótulo.
    await expect(page.getByRole('img', { name: /Vendas \(AOA\)/ })).toBeVisible();
});

/**
 * OS DOZE MESES ESTÃO SEMPRE LÁ.
 *
 * Um gráfico que salte de Março para Junho porque Abril e Maio não têm
 * facturas mente sobre a forma do ano.
 */
test('o grafico tem os doze meses, mesmo os vazios', async ({ page }) => {
    const grafico = page.getByRole('img', { name: /Vendas \(AOA\)/ });
    await expect(grafico).toBeVisible({ timeout: 20_000 });

    expect(await grafico.locator('> div').count()).toBe(12);

    // E as barras TÊM ALTURA. Contar doze colunas não chega: elas existiam e
    // eram todas de altura zero — o gráfico aparecia vazio com a legenda a
    // dizer «máximo 124 773 Kz».
    const alturas = await grafico.locator('> div > div').evaluateAll(
        (bs) => bs.map((b) => b.getBoundingClientRect().height),
    );

    expect(Math.max(...alturas)).toBeGreaterThan(20);
});

test('as quatro caixas do estado nao se sobrepoem', async ({ page }) => {
    await expect(page.getByText('Estado das Faturas', { exact: true })).toBeVisible({ timeout: 20_000 });

    for (const caixa of ['Pagas', 'Parc. Pagas', 'Vencidas']) {
        await expect(page.getByText(caixa, { exact: true })).toBeVisible();
    }
});

/**
 * OS DOIS BOTÕES DE EXPORTAR, e a mesa que eles precisam de encontrar posta.
 *
 * A mecânica do PDF e do CSV vive em `/js/painel-facturacao.js`, carregado
 * pelo layout, e lê tudo do DOM: se o ecrã não escrever os nós com os nomes
 * certos, os botões existem e não fazem nada. É por isso que aqui se lê o nó
 * a sério, e não a fonte.
 */
test('os botoes de exportar tem a mesa posta', async ({ page }) => {
    await expect(page.getByRole('button', { name: /Exportar PDF/ })).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('button', { name: 'Excel' })).toBeVisible();

    const textos = await page.locator('#textosPainel').textContent();
    const cfg = JSON.parse(textos);

    expect(cfg.intl).toBe('pt-PT');
    expect(cfg.t.titulo).toBe('Dashboard de Faturação');
    expect(cfg.t.vendasAoa).toBe('Vendas (AOA)');

    /*
     * O CSV NÃO PODE LEVAR SEPARADOR DE MILHARES.
     *
     * Um «1.234,56» dentro de um ficheiro separado por vírgulas abre na folha
     * de cálculo com uma coluna a mais e o valor partido em dois. Os valores
     * formatados servem o PDF; estes, os `…Cru`, servem o CSV.
     */
    const crus = Object.entries(cfg.valores).filter(([chave]) => chave.endsWith('Cru'));

    expect(crus.length).toBe(4);

    for (const [chave, valor] of crus) {
        expect(valor, `${chave} leva vírgula e parte a coluna do CSV`).not.toContain(',');
        expect(valor).toMatch(/^-?\d+\.\d{2}$/);
    }

    // E as linhas do CSV, uma por mês do ano.
    const linhas = JSON.parse(await page.locator('#dadosVendas').textContent());

    expect(linhas.length).toBe(12);
    expect(linhas[0]).toHaveProperty('total');
});

/**
 * O PAINEL EM INGLÊS, do título ao gráfico.
 *
 * É o par que decide se o sistema PARECE traduzido: alguém escolhe inglês,
 * cai no painel, e tem à frente o menu, o título e os cartões. Basta um dos
 * três ficar em português para se ler como «a tradução não funciona».
 */
test('em ingles o painel inteiro muda de lingua', async ({ page }) => {
    await page.goto(`${ECRA}?lang=en`);

    // O título da página é do servidor; os cartões são do React.
    await expect(page.getByRole('heading', { name: 'Invoicing Dashboard' })).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Monthly Revenue')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Awaiting payment')).toBeVisible();
    await expect(page.getByText('Sales Trend - This Year')).toBeVisible();
    await expect(page.getByRole('img', { name: /Sales \(AOA\)/ })).toBeVisible();

    // O formato dos números segue a língua: em inglês o milhar é vírgula e o
    // decimal é ponto. Era isto que o 'pt-PT' escrito à mão escondia.
    const cfg = JSON.parse(await page.locator('#textosPainel').textContent());

    expect(cfg.intl).toBe('en-GB');
    expect(cfg.t.titulo).toBe('Invoicing Dashboard');

    // E os valores crus continuam sem separador nenhum — em inglês a vírgula
    // do milhar era mesmo capaz de aparecer.
    for (const [chave, valor] of Object.entries(cfg.valores)) {
        if (chave.endsWith('Cru')) expect(valor).not.toContain(',');
    }
});

test.afterEach(async ({ page }) => {
    // A empresa de bancada é partilhada, e a língua fica guardada no perfil e
    // num cookie de um ano: deixá-la em inglês estragava os outros ensaios.
    await page.goto(`${ECRA}?lang=pt`);
    await expect(page.locator('[data-ecra]')).toBeVisible({ timeout: 20_000 });
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByText('Faturação do Mês')).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});
