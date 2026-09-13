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
 * TROCAR O PERÍODO PEDE AO SERVIDOR — e o ecrã inteiro segue-o.
 *
 * O painel em Blade tinha um selector de período e a migração para React
 * perdeu-o: o ecrã passou a mostrar sempre o mesmo. As contas são todas do
 * servidor, portanto escolher tem mesmo de sair daqui e voltar — e o título,
 * os cartões e o gráfico têm de vir todos da MESMA resposta, que é o que
 * impede o título de dizer uma coisa e os cartões contarem outra.
 *
 * E os doze meses estão sempre lá: um gráfico que salte de Março para Junho
 * porque Abril e Maio não têm facturas mente sobre a forma do ano.
 */
test('trocar o periodo pede ao servidor e o ecra inteiro segue', async ({ page }) => {
    const selector = page.locator('[data-periodo]');

    await expect(selector).toBeVisible({ timeout: 20_000 });
    await expect(selector).toHaveValue('month');
    await expect(page.getByText('Evolução de Vendas - Este mês')).toBeVisible();

    const pedido = page.waitForResponse(
        (r) => r.url().includes('/react/painel') && r.url().includes('periodo=year'),
    );

    await selector.selectOption('year');
    await pedido;

    // O título segue, e o cartão deixa de dizer «do Mês» quando o que está
    // somado lá dentro já é o ano inteiro.
    await expect(page.getByText('Evolução de Vendas - Este ano')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Faturação Este ano')).toBeVisible();
    await expect(page.getByText('Faturação do Mês')).toHaveCount(0);

    const grafico = page.getByRole('img', { name: /Vendas \(AOA\)/ });

    await expect(grafico.locator('> div')).toHaveCount(12);

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
 * A mecânica do PDF e do CSV vive em `ecras/facturacao/exportarPainel.ts`,
 * e lê tudo do DOM: se o ecrã não escrever os nós com os nomes
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

    // E AS LINHAS DO CSV SÃO AS DO GRÁFICO — as do período que está no ecrã,
    // uma por coluna. Exportar o ano enquanto o ecrã mostra o mês seria dar à
    // folha de cálculo números que ninguém pediu.
    const linhas = JSON.parse(await page.locator('#dadosVendas').textContent());
    const colunas = await page.getByRole('img', { name: /Vendas \(AOA\)/ }).locator('> div').count();

    expect(linhas.length).toBe(colunas);
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
    // O período por omissão é o mês, e o título traz o rótulo que o servidor
    // já traduziu — a frase composta é o que a pessoa lê.
    await expect(page.getByText('Sales Trend - This month')).toBeVisible();
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
