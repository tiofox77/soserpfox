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
    await expect(page.getByRole('status')).toBeVisible({ timeout: 20_000 });
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
    await page.getByRole('button', { name: /Novo artigo/ }).click();

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
    await page.getByRole('button', { name: /Novo artigo/ }).click();

    const janela = page.getByRole('dialog');

    await expect(janela.getByLabel('Stock mínimo')).toBeVisible();

    await janela.getByLabel(/^Tipo\b/).selectOption('servico');

    await expect(janela.getByLabel('Stock mínimo')).toHaveCount(0);
    await expect(janela.getByLabel('Quantidade inicial')).toHaveCount(0);
});

/** O imposto: ou taxa do catálogo, ou isenção com motivo. Nunca à mão. */
test('o imposto troca entre taxa do catalogo e motivo de isencao', async ({ page }) => {
    await page.getByRole('button', { name: /Novo artigo/ }).click();

    const janela = page.getByRole('dialog');

    await janela.getByLabel(/^Imposto\b/).selectOption('iva');
    await expect(janela.getByLabel('Taxa')).toBeVisible();
    await expect(janela.getByLabel(/^Motivo da isenção\b/)).toHaveCount(0);

    await janela.getByLabel(/^Imposto\b/).selectOption('isento');
    await expect(janela.getByLabel(/^Motivo da isenção\b/)).toBeVisible();
    await expect(janela.getByLabel('Taxa')).toHaveCount(0);
});

test('cria um artigo e ele aparece na lista', async ({ page }) => {
    const nome = 'Artigo React ' + String(Date.now()).slice(-6);

    await page.getByRole('button', { name: /Novo artigo/ }).click();

    const janela = page.getByRole('dialog');

    await janela.getByLabel(/^Nome\b/).fill(nome);
    await janela.getByLabel(/^Preço\b/).fill('1500');
    await janela.getByLabel(/^Categoria\b/).selectOption({ index: 1 });
    await janela.getByLabel(/^Imposto\b/).selectOption('isento');
    await janela.getByLabel(/^Motivo da isenção\b/).fill('M99');
    await janela.getByRole('button', { name: 'Guardar' }).click();

    await expect(page.getByRole('status')).toContainText('Artigo criado', { timeout: 20_000 });

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
        await page.getByRole('button', { name: /Novo artigo/ }).click();

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
    await page.getByRole('button', { name: /Novo artigo/ }).click();

    const janela = page.getByRole('dialog');

    // Sem perfil e sem dados, a secção não se impõe — mas está a um clique.
    const revelar = janela.getByRole('button', { name: /campos próprios do ramo/ });
    await expect(revelar).toBeVisible();
    await revelar.click();

    await janela.getByLabel(/Exige receita médica/).check();

    await janela.getByLabel(/^Nome\b/).fill(nome);
    await janela.getByLabel(/^Preço\b/).fill('2500');
    await janela.getByLabel(/^Categoria\b/).selectOption({ index: 1 });
    await janela.getByLabel(/^Imposto\b/).selectOption('isento');
    await janela.getByLabel(/^Motivo da isenção\b/).fill('M99');
    await janela.getByRole('button', { name: 'Guardar' }).click();

    await expect(page.getByRole('status')).toContainText('Artigo criado', { timeout: 20_000 });

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

/** As imagens: escolher uma mostra a pré-visualização antes de ela subir. */
test('a imagem de destaque mostra-se antes de subir', async ({ page }) => {
    await page.getByRole('button', { name: /Novo artigo/ }).click();

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

