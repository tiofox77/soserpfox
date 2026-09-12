import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS DADOS DA EMPRESA — o ecrã, no browser.
 *
 * As regras vivem nos ensaios de PHP (`DadosDaEmpresaPermissaoTest`,
 * `GeografiaDaMoradaTest`, `tests/Feature/Empresa`): ver e mudar são direitos
 * diferentes, o país é ISO, a cidade acompanha o município, e mudar de regime
 * pede um sim escrito. O que aqui se prova é o que só se vê no browser:
 *
 *  · que a página abre sem um erro na consola;
 *  · que o RESUMO FISCAL vem antes do formulário — um regime não se escolhe no
 *    abstracto, escolhe-se a olhar para o imposto e para os produtos;
 *  · que escolher outro regime mostra as CONSEQUÊNCIAS antes de se guardar, e
 *    que guardar pede confirmação;
 *  · e que a morada é a cascata de sempre: em Angola, listas; fora dela, texto.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto('/empresa');
    await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Dados da Empresa' }))
        .toBeVisible({ timeout: 20_000 });
});

test('abre /empresa sem erros na consola', async ({ page }) => {
    const erros = [];

    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    const resposta = await page.goto('/empresa');

    expect(resposta?.status()).toBeLessThan(400);

    await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Dados da Empresa' }))
        .toBeVisible({ timeout: 20_000 });

    expect(erros, 'consola de /empresa').toEqual([]);
});

/**
 * O RESUMO FISCAL VEM ANTES DO FORMULÁRIO.
 *
 * Um regime não se escolhe no abstracto: escolhe-se a olhar para o imposto que
 * vai passar a sair nos documentos e para quantos produtos vão mudar de mão.
 */
test('o resumo fiscal vem antes do formulario', async ({ page }) => {
    await expect(page.getByText('Regime actual')).toBeVisible();
    await expect(page.getByText('Imposto por omissão', { exact: true })).toBeVisible();
    await expect(page.getByText('Isentos sem motivo')).toBeVisible();
});

/**
 * AS CONSEQUÊNCIAS DIZEM-SE ANTES DE ACONTECEREM.
 *
 * Ao guardar, o imposto por omissão e o regime de todos os produtos mudam de
 * uma vez. Descobrir isso depois é descobri-lo com as facturas do mês já
 * emitidas à taxa errada.
 */
test('mudar de regime mostra as consequencias e pede confirmacao', async ({ page }) => {
    await expect(page.getByText('Afeta impostos e produtos')).toBeVisible();

    // O regime de não sujeição — o que não liquida IVA nenhum.
    await page.getByRole('radio', { name: /Não Sujeição/ }).check();

    await expect(page.getByText(/Está a mudar de regime:/)).toBeVisible();
    await expect(page.getByText('Ao guardar, o sistema vai aplicar automaticamente:')).toBeVisible();
    await expect(page.getByText(/Todos os produtos passam a isentos com o motivo M04/)).toBeVisible();

    await page.getByRole('button', { name: 'Guardar alterações' }).click();

    const modal = page.getByRole('dialog');

    await expect(modal).toBeVisible({ timeout: 15_000 });
    await expect(modal.getByText(/Os produtos vão ser actualizados em massa/)).toBeVisible();

    // NÃO SE GUARDA NADA: o ensaio sai pela porta do «manter», para não mexer
    // no regime fiscal da bancada.
    await modal.getByRole('button', { name: /Manter/ }).click();

    await expect(modal).toBeHidden();
    await expect(page.getByText(/Está a mudar de regime:/)).toBeHidden();
});

/**
 * A MORADA É A CASCATA DE SEMPRE.
 *
 * Em Angola a divisão administrativa escolhe-se de listas que vêm do servidor;
 * fora de Angola não há divisão que se possa impor e escreve-se.
 */
test('a morada muda de forma quando se sai de angola', async ({ page }) => {
    const pais = page.getByRole('combobox', { name: /País/ });

    await pais.selectOption('AO');

    await expect(page.getByRole('combobox', { name: /^Província/ })).toBeVisible();
    await expect(page.getByRole('combobox', { name: /Município/ })).toBeVisible();

    await pais.selectOption('PT');

    // Fora de Angola, província e cidade são texto livre.
    await expect(page.getByRole('textbox', { name: /Província \/ Estado/ })).toBeVisible();
    await expect(page.getByRole('combobox', { name: /Município/ })).toHaveCount(0);
});

/** E o logótipo diz onde é que vai parar. */
test('o logotipo diz onde e que aparece', async ({ page }) => {
    await expect(page.getByText('PNG ou JPG, até 2 MB. Sai nas facturas e nos recibos.')).toBeVisible();
});
