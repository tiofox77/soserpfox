import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * A LISTA DE FACTURAS EM REACT, NO BROWSER A SÉRIO.
 *
 * Os ensaios em Vitest provam o componente contra respostas inventadas. Este
 * prova a outra metade, que nenhum deles cobre: que o pacote carrega, que o
 * React encontra o ponto de montagem posto pelo Blade, e que fala com a API
 * verdadeira com a sessão verdadeira.
 *
 * Corre contra a empresa de bancada (`php artisan bancada:pwa`), que é onde as
 * credenciais são fixas e conhecidas de propósito.
 */

const ECRA = '/invoicing/sales/invoices';

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test('o React monta-se dentro do layout de sempre', async ({ page }) => {
    await page.goto(ECRA);

    // O menu e o cabeçalho continuam a ser desenhados pelo Laravel: é isso que
    // permite migrar um ecrã de cada vez.
    await expect(page.locator('[data-ecra="facturacao/lista-de-facturas"]')).toBeVisible();

    // E o React tomou conta dele — o esqueleto do Blade deu lugar à tabela.
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('columnheader', { name: 'Número' })).toBeVisible();
});

test('mostra facturas verdadeiras vindas da API', async ({ page }) => {
    await page.goto(ECRA);

    const linhas = page.locator('tbody tr');

    await expect(linhas.first()).toBeVisible({ timeout: 20_000 });
    expect(await linhas.count()).toBeGreaterThan(0);

    // O total escreve-se à maneira daqui: milhares separados e vírgula decimal.
    await expect(page.locator('tbody tr').first()).toContainText(/\d{1,3}([  .]\d{3})*,\d{2}/);
});

test('procurar não recarrega a página', async ({ page }) => {
    await page.goto(ECRA);
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    // Marca-se a página. Se houver navegação, a marca desaparece — e é isso
    // que distingue um ecrã React de um Livewire a trocar o corpo todo.
    await page.evaluate(() => { window.__marca = 'aqui'; });

    await page.getByPlaceholder('Número, série ou cliente').fill('FT');
    await page.waitForResponse((r) => r.url().includes('procura=FT'), { timeout: 20_000 });

    expect(await page.evaluate(() => window.__marca)).toBe('aqui');
});

test('os filtros pedem ao servidor e a tabela responde', async ({ page }) => {
    await page.goto(ECRA);
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    const pedido = page.waitForResponse(
        (r) => r.url().includes('/sales-invoices') && r.url().includes('estado=draft'),
        { timeout: 20_000 },
    );

    await page.getByLabel('Estado').selectOption('draft');

    const resposta = await pedido;

    expect(resposta.ok()).toBe(true);
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];

    page.on('console', (m) => {
        if (m.type() === 'error') erros.push(m.text());
    });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.goto(ECRA);
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

/**
 * A ENTRADA DO MENU DAS FATURAS-RECIBO ABRE FILTRADA.
 *
 * O menu liga-as por `?type=FR`. A lista em React nascia sempre sem filtro:
 * a entrada existia e mostrava tudo.
 */
test('a morada das faturas-recibo abre com o filtro posto', async ({ page }) => {
    await page.goto('/invoicing/sales/invoices?type=FR');
    await expect(page.locator('[data-ecra]')).toBeVisible({ timeout: 20_000 });

    // O rótulo é desenhado em maiúsculas por CSS: em texto (que o Playwright
    // compara sem ligar a maiúsculas), não em expressão regular.
    await expect(page.getByLabel('Tipo')).toHaveValue('FR', { timeout: 20_000 });
});

/**
 * DUPLICAR: aproveita o trabalho, nunca a identidade.
 *
 * O botão leva ao ecrã de emissão de sempre, com `?duplicar=` na morada — a
 * mesma morada que o ecrã em Livewire usava. O que chega lá é CONTEÚDO: o
 * cliente e as linhas ficam preenchidos, mas o documento nasce novo, sem
 * número e sem série, e nada foi gravado por se ter carregado no botão.
 */
test('duplicar leva o conteúdo da factura para um documento novo', async ({ page }) => {
    await page.goto(ECRA);
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    const duplicar = page.getByRole('link', { name: 'Duplicar para novo documento' }).first();

    await expect(duplicar).toBeVisible();
    await expect(duplicar).toHaveAttribute(
        'href',
        /\/invoicing\/sales\/invoices\/create\?duplicar=\d+$/,
    );

    await duplicar.click();

    // O ecrã diz de onde isto veio — quem duplicou quer ver que apanhou o
    // documento certo antes de emitir.
    await expect(page.locator('[data-duplicado-de]')).toBeVisible({ timeout: 20_000 });

    // E NÃO é uma edição: a faixa do documento aberto não existe aqui.
    await expect(page.locator('[data-documento-aberto]')).toHaveCount(0);

    // O conteúdo veio: cliente escolhido e pelo menos uma linha com artigo.
    //
    // Em expressão regular e sem ligar a maiúsculas: `getByLabel('Cliente')`
    // compara por pedaço e apanhava também a «Região fiscal», cuja opção
    // escolhida diz «Pela província do cliente».
    await expect(page.getByLabel(/^cliente\b/i)).not.toHaveValue('');
    await expect(page.getByLabel('Artigo da linha 1')).not.toHaveAttribute('data-artigo-escolhido', '');

    // A data é a de hoje, e não a do original.
    const hoje = new Date();
    const iso = [
        hoje.getFullYear(),
        String(hoje.getMonth() + 1).padStart(2, '0'),
        String(hoje.getDate()).padStart(2, '0'),
    ].join('-');
    // Pela posição e não pelo rótulo: 'Data' compara por pedaço e apanhava
    // também a «Data de entrega» e o «Vencimento». A primeira data do
    // formulário é a data do documento.
    await expect(page.locator('input[type="date"]').first()).toHaveValue(iso);
});
/**
 * OS CARTÕES DO TOPO SOMAM VALORES, E SOMAM O QUE ESTÁ FILTRADO.
 *
 * O ecrã em Blade tinha-os e a migração deixou só a contagem. As somas vêm do
 * SERVIDOR, dentro do `meta` da mesma resposta da lista — não se somam aqui as
 * linhas da página, que mudariam ao carregar em «Seguinte».
 */
test('os cartões do topo mostram as somas que vêm do servidor', async ({ page }) => {
    const resposta = page.waitForResponse(
        (r) => r.url().includes('/sales-invoices?') || r.url().endsWith('/sales-invoices'),
        { timeout: 20_000 },
    );

    await page.goto(ECRA);

    const somas = (await (await resposta).json()).meta.somas;

    expect(somas).toMatchObject({
        facturado: expect.any(Number),
        por_receber: expect.any(Number),
        vencido: expect.any(Number),
    });

    // Os quatro cartões estão à vista, e os de dinheiro escrevem-se à maneira
    // daqui: milhares separados, vírgula decimal e a moeda ao lado.
    for (const rotulo of ['Documentos', 'Facturado', 'Por receber', 'Vencido']) {
        await expect(page.getByText(rotulo, { exact: true }).first()).toBeVisible({ timeout: 20_000 });
    }

    const facturado = page.getByText('Facturado', { exact: true }).first().locator('..');

    await expect(facturado).toContainText('Kz');
    await expect(facturado).toContainText(/\d{1,3}([  .]\d{3})*,\d{2}/);
});

/**
 * CADA LINHA OFERECE OS DOIS PAPÉIS.
 *
 * O do servidor (DomPDF) tem texto para copiar e pesquisar. O do ECRÃ é a
 * própria pré-visualização fotografada — é isso que garante que o papel nunca
 * diverge do que se vê. Nenhum substitui o outro, e a lista em React tinha
 * ficado só com o primeiro.
 */
test('cada factura tem só o PDF do ecrã (o do servidor saiu — saía torto)', async ({ page }) => {
    await page.goto(ECRA);

    const linha = page.locator('tbody tr').first();

    await expect(linha).toBeVisible({ timeout: 20_000 });

    await expect(linha.locator('a[href$="/pdf"]')).toHaveCount(0);

    // O botão do PDF do ecrã não é uma ligação: é os seus `data-*`, que o
    // ouvinte por delegação em casca/pdfDoDocumento.ts reconhece.
    const doEcra = linha.locator('button[data-pdf-preview]');

    await expect(doEcra).toHaveCount(1);
    await expect(doEcra).toHaveAttribute(
        'data-pdf-preview',
        /^\/invoicing\/sales\/invoices\/\d+\/preview$/,
    );
});
