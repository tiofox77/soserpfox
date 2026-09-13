import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS ARTIGOS EM REACT.
 *
 * O que este ecrã tem de diferente dos outros dois é a REGRA DO STOCK: a
 * quantidade só existe ao criar. A editar, o stock é o que as linhas dizem, e
 * mexer nele aqui revertia vendas feitas entretanto.
 *
 * E a REGRA DO PERFIL, que é a outra que aqui se prova: o perfil do negócio
 * decide o que aparece POR OMISSÃO, nunca o que existe nem o que se esconde.
 * Um artigo com receita marcada mostra o campo da receita mesmo com o perfil
 * de farmácia desligado — senão, desligar uma definição de visualização
 * deixava dados gravados sem forma de os ver nem de os corrigir.
 */

const ECRA = '/invoicing/products';
const DEFINICOES = '/invoicing/settings';

/**
 * Liga ou desliga os perfis do negócio.
 *
 * A empresa de bancada é partilhada, por isso quem lhe mexe repõe-na — o
 * ensaio que liga um perfil desliga-o outra vez, aconteça o que acontecer.
 */
async function definirPerfis(page, perfis) {
    await page.goto(DEFINICOES);
    await page.getByRole('tab', { name: /Perfil do negócio/ }).click();

    for (const [rotulo, ligado] of Object.entries(perfis)) {
        const caixa = page.getByLabel(rotulo);
        await expect(caixa).toBeVisible();

        if ((await caixa.isChecked()) !== ligado) {
            await caixa.setChecked(ligado);
        }
    }

    await page.getByRole('button', { name: /Guardar definições/ }).click();
    await expect(page.locator('[data-ensaio="avisos-de-canto"] [role="status"]').first()).toBeVisible({ timeout: 20_000 });
}

/**
 * ESCOLHE O REGIME DE IVA.
 *
 * Deixou de ser uma caixa de escolha e passou a dois CARTÕES, como o ecrã em
 * Blade tinha: são duas opções, e um `<select>` esconde a segunda atrás de um
 * clique. Por baixo continuam a ser `radio` a sério — mas com o input em
 * `sr-only`, quem se carrega é no rótulo.
 */
async function escolherRegime(janela, qual) {
    const rotulo = qual === 'iva' ? 'Sujeito a IVA' : 'Isento de IVA';

    await janela.getByText(rotulo, { exact: true }).click();
}

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
});

test('a lista abre com os artigos da empresa', async ({ page }) => {
    await expect(page.getByRole('columnheader', { name: 'Stock' })).toBeVisible();
    expect(await page.locator('tbody tr').count()).toBeGreaterThan(0);
});

/**
 * O STOCK NÃO SE EDITA AQUI.
 *
 * A criar há «Quantidade inicial». A editar não há campo nenhum — só o valor
 * a dizer onde se ajusta.
 */
test('a quantidade so existe ao criar, nunca ao editar', async ({ page }) => {
    await page.getByRole('button', { name: /Novo Produto/i }).click();

    const novo = page.getByRole('dialog');
    await expect(novo.getByLabel('Quantidade inicial')).toBeVisible();
    await novo.getByRole('button', { name: 'Cancelar' }).click();

    // Agora a editar um que já exista.
    await page.locator('tbody tr').first().getByRole('button', { name: /^Editar / }).click();

    const edicao = page.getByRole('dialog');
    await expect(edicao).toBeVisible();

    await expect(edicao.getByLabel('Quantidade inicial')).toHaveCount(0);
    await expect(edicao.getByText('ajusta-se na Gestão de Stock')).toBeVisible();
});

/** Um serviço não gere stock — os campos de stock desaparecem. */
test('escolher servico faz desaparecer o stock', async ({ page }) => {
    await page.getByRole('button', { name: /Novo Produto/i }).click();

    const janela = page.getByRole('dialog');

    await expect(janela.getByLabel('Stock mínimo')).toBeVisible();

    await janela.getByLabel(/^Tipo\b/).selectOption('servico');

    await expect(janela.getByLabel('Stock mínimo')).toHaveCount(0);
    await expect(janela.getByLabel('Quantidade inicial')).toHaveCount(0);
});

/** O imposto: ou taxa do catálogo, ou isenção com motivo. Nunca à mão. */
test('o imposto troca entre taxa do catalogo e motivo de isencao', async ({ page }) => {
    await page.getByRole('button', { name: /Novo Produto/i }).click();

    const janela = page.getByRole('dialog');

    await escolherRegime(janela, 'iva');
    // `/^Taxa/` e não 'Taxa': o cartão «Sujeito a IVA» explica-se com
    // «Produto com taxa de IVA», e um selector por substring apanhava os dois.
    await expect(janela.getByLabel(/^Taxa/)).toBeVisible();
    await expect(janela.getByLabel(/^Motivo de Isenção/)).toHaveCount(0);

    await escolherRegime(janela, 'isento');
    await expect(janela.getByLabel(/^Motivo de Isenção/)).toBeVisible();
    await expect(janela.getByLabel(/^Taxa/)).toHaveCount(0);
});

test('cria um artigo e ele aparece na lista', async ({ page }) => {
    const nome = 'Artigo React ' + String(Date.now()).slice(-6);

    await page.getByRole('button', { name: /Novo Produto/i }).click();

    const janela = page.getByRole('dialog');

    await janela.getByLabel(/^Nome\b/).fill(nome);
    await janela.getByLabel(/^Preço\b/).fill('1500');
    await janela.getByLabel(/^Categoria\b/).selectOption({ index: 1 });
    await escolherRegime(janela, 'isento');
    await janela.getByLabel(/^Motivo de Isenção/).selectOption('M04');
    await janela.getByRole('button', { name: 'Guardar' }).click();

    await expect(page.locator('[data-ensaio="avisos-de-canto"]')).toContainText('Artigo criado', { timeout: 20_000 });

    await page.getByPlaceholder('Nome, código, SKU ou código de barras').fill(nome);
    await expect(page.getByRole('cell', { name: new RegExp(nome) }).first()).toBeVisible({
        timeout: 20_000,
    });
});

/**
 * COM O PERFIL LIGADO, A SECÇÃO DO RAMO APARECE SOZINHA.
 *
 * Uma farmácia não tem de descobrir onde estão os campos dos medicamentos: liga
 * o perfil nas Definições e eles estão à vista no formulário do artigo.
 */
test('com o perfil de farmacia ligado a seccao do sector aparece', async ({ page }) => {
    try {
        await definirPerfis(page, { Farmácia: true });

        await page.goto(ECRA);
        await page.getByRole('button', { name: /Novo Produto/i }).click();

        const janela = page.getByRole('dialog');

        // Nem sequer há o botão a perguntar «este artigo tem campos do ramo?»:
        // o perfil já respondeu por ele.
        await expect(janela.getByRole('button', { name: /campos próprios do ramo/ })).toHaveCount(0);

        // A secção está lá e chama-se pelo ramo que o perfil ligou — só a
        // farmácia está ligada, por isso não diz «Detalhes específicos» nem
        // mistura vestuário. Num artigo novo começa recolhida: 99% do catálogo
        // não usa nada disto, e um formulário que se abre com trinta campos à
        // frente é um formulário que ninguém lê.
        const seccao = janela.getByRole('button', { name: /Medicamento/ });
        await expect(seccao).toBeVisible();
        await expect(seccao).toHaveAttribute('aria-expanded', 'false');

        await seccao.click();

        await expect(janela.getByLabel(/Exige receita médica/)).toBeVisible();
        await expect(janela.getByLabel(/^Dosagem/)).toBeVisible();
        await expect(janela.getByLabel(/N.º de registo ARMED/)).toBeVisible();

        // E o que não é do ramo ligado não se impõe — fica a um clique.
        await expect(janela.getByLabel(/^Tamanho/)).toHaveCount(0);
        await expect(
            janela.getByRole('button', { name: 'Este artigo também é vestuário' }),
        ).toBeVisible();
    } finally {
        // A empresa de bancada é partilhada: fica como estava.
        await definirPerfis(page, { Farmácia: false });
    }
});

/**
 * O LIMITE QUE NÃO SE CRUZA: um valor gravado não desaparece com o perfil
 * desligado.
 *
 * Sem perfil nenhum ligado, a secção fica atrás de um botão — mas quem marcar
 * um artigo como sujeito a receita tem de o voltar a ver ao abri-lo, senão
 * ficava com um dado gravado, activo no POS, e sem nada no ecrã a explicá-lo.
 */
test('um valor gravado continua a ver-se com o perfil desligado', async ({ page }) => {
    await definirPerfis(page, {
        Farmácia: false,
        'Vestuário e calçado': false,
        Cosmética: false,
        'Mercearia e supermercado': false,
    });

    const nome = 'Xarope React ' + String(Date.now()).slice(-6);

    await page.goto(ECRA);
    await page.getByRole('button', { name: /Novo Produto/i }).click();

    const janela = page.getByRole('dialog');

    // Sem perfil e sem dados, a secção não se impõe — mas está a um clique.
    const revelar = janela.getByRole('button', { name: /campos próprios do ramo/ });
    await expect(revelar).toBeVisible();
    await revelar.click();

    await janela.getByLabel(/Exige receita médica/).check();

    await janela.getByLabel(/^Nome\b/).fill(nome);
    await janela.getByLabel(/^Preço\b/).fill('2500');
    await janela.getByLabel(/^Categoria\b/).selectOption({ index: 1 });
    await escolherRegime(janela, 'isento');
    await janela.getByLabel(/^Motivo de Isenção/).selectOption('M04');
    await janela.getByRole('button', { name: 'Guardar' }).click();

    await expect(page.locator('[data-ensaio="avisos-de-canto"]')).toContainText('Artigo criado', { timeout: 20_000 });

    // O crachá da lista segue o DADO do artigo, não o perfil da empresa.
    await page.getByPlaceholder('Nome, código, SKU ou código de barras').fill(nome);
    const linha = page.locator('tbody tr').filter({ hasText: nome }).first();
    await expect(linha).toBeVisible({ timeout: 20_000 });
    await expect(linha.getByText('Receita', { exact: true })).toBeVisible();

    // E ao reabrir a ficha, com o perfil na mesma desligado, o campo está lá
    // — já revelado, e ainda marcado.
    await linha.getByRole('button', { name: /^Editar / }).click();

    const edicao = page.getByRole('dialog');
    await expect(edicao.getByRole('button', { name: /campos próprios do ramo/ })).toHaveCount(0);
    await expect(edicao.getByLabel(/Exige receita médica/)).toBeChecked();
});

/**
 * A MARCA, O FORNECEDOR E O CONTROLO DE LOTES.
 *
 * Os seis campos que a migração para React tinha deixado para trás. Os quatro
 * de lote são o que liga o artigo ao módulo de Lotes e Validades — sem eles o
 * artigo nunca lá entra.
 */
test('a marca, o fornecedor e o controlo de lotes estao no formulario', async ({ page }) => {
    await page.getByRole('button', { name: /Novo Produto/i }).click();

    const janela = page.getByRole('dialog');

    await expect(janela.getByLabel(/^Marca/)).toBeVisible();
    await expect(janela.getByLabel(/^Fornecedor/)).toBeVisible();

    await expect(janela.getByLabel(/Rastrear por Lotes/)).toBeVisible();
    await expect(janela.getByLabel(/Controlar Validade/)).toBeVisible();
    await expect(janela.getByLabel(/Exigir Lote na Compra/)).toBeVisible();
    await expect(janela.getByLabel(/Exigir Lote na Venda/)).toBeVisible();

    // Um serviço não tem remessa nem prazo de validade: os lotes desaparecem
    // com o stock. A marca e o fornecedor ficam — um serviço também se compra.
    await janela.getByLabel(/^Tipo\b/).selectOption('servico');

    await expect(janela.getByLabel(/Rastrear por Lotes/)).toHaveCount(0);
    await expect(janela.getByLabel(/Exigir Lote na Venda/)).toHaveCount(0);
    await expect(janela.getByLabel(/^Marca/)).toBeVisible();
});

/** E gravam: um artigo marcado volta a abrir marcado. */
test('o controlo de lotes grava e a ficha volta a abrir marcada', async ({ page }) => {
    const nome = 'Lote React ' + String(Date.now()).slice(-6);

    await page.getByRole('button', { name: /Novo Produto/i }).click();

    const janela = page.getByRole('dialog');

    await janela.getByLabel(/^Nome\b/).fill(nome);
    await janela.getByLabel(/^Preço\b/).fill('3500');
    await janela.getByLabel(/^Categoria\b/).selectOption({ index: 1 });
    await escolherRegime(janela, 'isento');
    await janela.getByLabel(/^Motivo de Isenção/).selectOption('M04');

    await janela.getByLabel(/Rastrear por Lotes/).check();
    await janela.getByLabel(/Controlar Validade/).check();

    await janela.getByRole('button', { name: 'Guardar' }).click();

    await expect(page.locator('[data-ensaio="avisos-de-canto"]')).toContainText('Artigo criado', { timeout: 20_000 });

    await page.getByPlaceholder('Nome, código, SKU ou código de barras').fill(nome);

    const linha = page.locator('tbody tr').filter({ hasText: nome }).first();
    await expect(linha).toBeVisible({ timeout: 20_000 });

    await linha.getByRole('button', { name: /^Editar / }).click();

    const edicao = page.getByRole('dialog');
    await expect(edicao.getByLabel(/Rastrear por Lotes/)).toBeChecked();
    await expect(edicao.getByLabel(/Controlar Validade/)).toBeChecked();
    await expect(edicao.getByLabel(/Exigir Lote na Venda/)).not.toBeChecked();
});

/** As imagens: escolher uma mostra a pré-visualização antes de ela subir. */
test('a imagem de destaque mostra-se antes de subir', async ({ page }) => {
    await page.getByRole('button', { name: /Novo Produto/i }).click();

    const janela = page.getByRole('dialog');

    await expect(janela.getByText('Imagem de destaque')).toBeVisible();

    // Um PNG de 1x1, feito aqui: não se guarda um ficheiro no repositório só
    // para isto.
    await janela.locator('input[type="file"]').first().setInputFiles({
        name: 'foto.png',
        mimeType: 'image/png',
        buffer: Buffer.from(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            'base64',
        ),
    });

    await expect(janela.getByAltText('A imagem escolhida, ainda por enviar')).toBeVisible();
    await expect(janela.getByRole('button', { name: 'Cancelar a escolha' })).toBeVisible();
});

/**
 * A FICHA DO ARTIGO — o modal de VER que a migração não trouxe.
 *
 * Quem só quer consultar não tem de abrir o formulário de edição, que é onde
 * se estraga uma ficha por engano. É informação, não um formulário: prova-se
 * que abre, que mostra o que interessa, e que dali se salta para editar.
 */
test('a ficha do artigo abre para ler e salta para editar', async ({ page }) => {
    await page.locator('tbody [aria-label^="Ver "]').first().click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });
    await expect(janela.getByText('Detalhes do Produto')).toBeVisible();

    // Os blocos da ficha, com os números lá dentro.
    await expect(janela.getByText('Preço de Venda')).toBeVisible();
    await expect(janela.getByText('Informação Fiscal')).toBeVisible();

    // E dali salta-se para o formulário, sem passar pela lista.
    await janela.getByRole('button', { name: /Editar Produto/ }).click();
    await expect(page.getByLabel(/^Nome/)).toBeVisible({ timeout: 20_000 });
});

/**
 * O RASTREIO: PARA ONDE FOI ESTE ARTIGO.
 *
 * As vendas ao lado dos movimentos de stock, e a diferença entre os dois — que
 * é o que denuncia o artigo que aparece disponível mas cuja baixa falha. A
 * funcionalidade inteira faltava: nem ecrã, nem API, nem contas.
 */
test('o rastreio junta as vendas aos movimentos de stock', async ({ page }) => {
    const pedido = page.waitForResponse(
        (r) => r.url().includes('/rastreio') && r.request().method() === 'GET',
        { timeout: 20_000 },
    );

    await page.locator('tbody [aria-label^="Rastrear "]').first().click();

    expect((await pedido).ok()).toBe(true);

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    // Os quatro cartões, incluindo o que justifica o ecrã.
    await expect(janela.getByText('Vendido − saídas')).toBeVisible({ timeout: 20_000 });

    // As duas listas, lado a lado.
    await expect(janela.getByText(/^Vendas \(/)).toBeVisible();
    await expect(janela.getByText(/^Movimentos de stock \(/)).toBeVisible();

    // O PERÍODO MUDA O QUE SE PEDE ao servidor — não é um filtro do lado de cá.
    const outroPeriodo = page.waitForResponse(
        (r) => r.url().includes('/rastreio') && r.url().includes('dias=30'),
        { timeout: 20_000 },
    );

    await janela.getByLabel('Período do rastreio').selectOption('30');
    expect((await outroPeriodo).ok()).toBe(true);
});

/**
 * O MOTIVO DA ISENÇÃO É UMA LISTA FECHADA — os códigos oficiais da AGT.
 *
 * Escrito à mão («isento», «art 12»), o motivo é recusado pela AGT no envio da
 * factura, muito depois de a venda estar feita. O campo era de texto livre.
 */
test('o motivo da isencao vem da lista oficial da AGT', async ({ page }) => {
    await page.getByRole('button', { name: /Novo Produto/i }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible();

    // O regime escolhe-se em dois cartões, e não numa caixa de escolha.
    await escolherRegime(janela, 'isento');

    const motivo = janela.getByLabel(/^Motivo de Isenção/);
    await expect(motivo).toBeVisible();

    // É um `select` com grupos por tipo de imposto, e não um campo de texto.
    await expect(motivo.locator('optgroup')).not.toHaveCount(0);
    await expect(motivo.locator('option[value="M01"]')).toHaveCount(1);
});

/**
 * A LIXEIRA E O RESTAURO.
 *
 * O artigo apagado sempre foi recuperável — o modelo tem SoftDeletes — e
 * durante anos não houve como o desfazer pela aplicação, só com SQL directo.
 */
test('a lixeira mostra os eliminados e deixa restaurar', async ({ page }) => {
    const pedido = page.waitForResponse(
        (r) => r.url().includes('/react/products') && r.url().includes('eliminados=1'),
        { timeout: 20_000 },
    );

    await page.getByRole('button', { name: /Ver eliminados/ }).click();
    expect((await pedido).ok()).toBe(true);

    // Ou há apagados e cada linha oferece o restauro, ou não há nenhum e a
    // lista di-lo. As duas respostas são certas — a errada seria a lista
    // normal, que é o que acontecia antes de o filtro existir.
    const restaurar = page.getByRole('button', { name: /^Restaurar$/ });
    const vazio = page.getByText('Nenhum artigo com estes filtros');

    await expect(restaurar.first().or(vazio)).toBeVisible({ timeout: 20_000 });

    // E volta-se ao catálogo pelo mesmo botão.
    await page.getByRole('button', { name: /Voltar ao catálogo/ }).click();
    await expect(page.getByRole('button', { name: /Ver eliminados/ })).toBeVisible();
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

