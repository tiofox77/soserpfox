import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * A FICHA DO FUNCIONÁRIO EM REACT.
 *
 * O ecrã mais denso do RH: cinquenta campos em cinco abas, nove documentos e
 * uma fotografia. O que aqui se prova é o que só se vê no browser:
 *
 *  · que as cinco abas existem e que trocar de aba NÃO PERDE o que já se
 *    escreveu na outra — o defeito clássico de um formulário com separadores;
 *  · que gravar leva à aba onde está o erro, em vez de o formulário se
 *    recusar a gravar sem dizer porquê;
 *  · que os campos que o ecrã em Blade nunca ofereceu lá estão.
 *
 * As regras — permissões por verbo, colunas a dobrar, escolhas desta empresa,
 * documentos — estão provadas em `ApiDosFuncionariosTest`.
 */

const ECRA = '/hr/employees';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('button', { name: 'Novo Funcionário' })).toBeVisible({ timeout: 20_000 });
});

test('abre com os quatro cartoes e a lista', async ({ page }) => {
    for (const cartao of ['Funcionários', 'Activos', 'De licença', 'Documentos a vencer']) {
        await expect(page.getByText(cartao, { exact: true }).first()).toBeVisible();
    }
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByRole('button', { name: 'Novo Funcionário' })).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

/**
 * AS CINCO ABAS, e o que cada uma pede.
 *
 * Os campos listados são os que o ecrã em Blade tinha MAIS os que a base
 * guardava e ele nunca ofereceu — turno, chefia, cessação, os três subsídios
 * que a folha calcula, e os beneficiários.
 */
test('o formulario tem as cinco abas com os campos de cada uma', async ({ page }) => {
    await page.getByRole('button', { name: 'Novo Funcionário' }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    const abas = janela.getByRole('tablist');

    for (const nome of ['Pessoais', 'Documentos', 'Morada', 'Vínculo', 'Remuneração']) {
        await expect(abas.getByRole('tab', { name: nome })).toBeVisible();
    }

    // Pessoais: os obrigatórios.
    await expect(janela.getByLabel(/^Primeiro nome/)).toBeVisible();
    await expect(janela.getByLabel(/^Último nome/)).toBeVisible();
    await expect(janela.getByLabel(/^Segurança Social/)).toBeVisible();

    // Vínculo: os que faltavam no ecrã de sempre.
    await abas.getByRole('tab', { name: 'Vínculo' }).click();
    for (const campo of [/^Turno/, /^Chefia/, /^Cessação/, /^Tipo de vínculo/, /^Estado/]) {
        await expect(janela.getByLabel(campo)).toBeVisible();
    }

    // Remuneração: os três subsídios que a folha calcula.
    await abas.getByRole('tab', { name: 'Remuneração' }).click();
    for (const campo of [/^Abono de família/, /^Subsídio de cargo/, /^Subsídio de desempenho/, /^IBAN/]) {
        await expect(janela.getByLabel(campo)).toBeVisible();
    }
    await expect(janela.getByRole('heading', { name: 'Beneficiários' })).toBeVisible();

    // Documentos: os nove blocos.
    await abas.getByRole('tab', { name: 'Documentos' }).click();
    for (const doc of ['Bilhete de Identidade', 'Passaporte', 'Carta de Condução', 'Registo Criminal', 'Período Experimental']) {
        await expect(janela.getByText(doc, { exact: true })).toBeVisible();
    }
});

/**
 * TROCAR DE ABA NÃO PERDE O QUE JÁ SE ESCREVEU.
 *
 * É o defeito clássico de um formulário com separadores: o painel escondido
 * desmonta-se e leva o que lá estava. Aqui fica montado, apenas oculto.
 */
test('trocar de aba nao perde o que ja se escreveu', async ({ page }) => {
    await page.getByRole('button', { name: 'Novo Funcionário' }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    const abas = janela.getByRole('tablist');

    await janela.getByLabel(/^Primeiro nome/).fill('Ana');

    await abas.getByRole('tab', { name: 'Remuneração' }).click();
    await janela.getByLabel(/^Salário base/).fill('250000');

    await abas.getByRole('tab', { name: 'Pessoais' }).click();
    await expect(janela.getByLabel(/^Primeiro nome/)).toHaveValue('Ana');

    await abas.getByRole('tab', { name: 'Remuneração' }).click();
    await expect(janela.getByLabel(/^Salário base/)).toHaveValue('250000');
});

/**
 * GRAVAR SEM NOME LEVA À ABA DO ERRO.
 *
 * Um campo obrigatório por preencher numa aba escondida era um formulário que
 * se recusava a gravar sem dizer porquê. O separador acende um ponto com a
 * contagem, e quem grava é levado lá.
 */
test('gravar sem nome leva a aba do erro', async ({ page }) => {
    await page.getByRole('button', { name: 'Novo Funcionário' }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    const abas = janela.getByRole('tablist');

    // Vai-se para outra aba antes de gravar: é aí que o defeito antigo mordia.
    await abas.getByRole('tab', { name: 'Morada' }).click();
    await expect(abas.getByRole('tab', { name: 'Morada' })).toHaveAttribute('aria-selected', 'true');

    await janela.getByRole('button', { name: 'Criar ficha' }).click();

    // Volta-se sozinho aos Pessoais, que é onde o nome falta.
    await expect(abas.getByRole('tab', { name: /^Pessoais/ })).toHaveAttribute('aria-selected', 'true', { timeout: 20_000 });
    await expect(janela.getByRole('alert').first()).toBeVisible();
});

/**
 * CRIAR UMA FICHA — e o formulário FICA ABERTO na ficha nova.
 *
 * Os documentos e a fotografia só sobem quando a ficha já tem id; fechar
 * depois de criar obrigava a procurar a pessoa outra vez para lhe carregar o
 * BI.
 */
test('criar uma ficha deixa o formulario aberto para os anexos', async ({ page }) => {
    await page.getByRole('button', { name: 'Novo Funcionário' }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    const apelido = 'Ensaio' + String(Date.now()).slice(-6);

    await janela.getByLabel(/^Primeiro nome/).fill('Bancada');
    await janela.getByLabel(/^Último nome/).fill(apelido);

    const gravado = page.waitForResponse(
        (r) => r.url().includes('/rh/funcionarios') && r.request().method() === 'POST',
        { timeout: 20_000 },
    );

    await janela.getByRole('button', { name: 'Criar ficha' }).click();

    expect((await gravado).status()).toBe(201);

    // O título passa a «Editar», e os anexos deixam de dizer «grave primeiro».
    await expect(janela.getByText('Editar funcionário')).toBeVisible({ timeout: 20_000 });

    await janela.getByRole('tablist').getByRole('tab', { name: 'Documentos' }).click();
    await expect(janela.getByText('Grave a ficha para anexar')).toHaveCount(0);

    await janela.getByRole('button', { name: 'Fechar', exact: true }).last().click();

    // E aparece na lista.
    await page.getByPlaceholder('Nome, número, email, telefone ou NIF').fill(apelido);
    await expect(page.getByText('Bancada ' + apelido)).toBeVisible({ timeout: 20_000 });
});

/** A ficha de leitura abre sem se poder estragar nada. */
test('a ficha de leitura abre com as quatro seccoes', async ({ page }) => {
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: /^Ver ficha de / }).first().click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    for (const seccao of ['Contacto', 'Identidade', 'Vínculo', 'Remuneração', 'Documentos']) {
        await expect(janela.getByText(seccao, { exact: true }).first()).toBeVisible();
    }

    await expect(janela.getByRole('link', { name: /Ficha em PDF/ })).toBeVisible();
});

/** A importação diz de onde vem e o que salta. */
test('a importacao oferece as duas origens', async ({ page }) => {
    await page.getByRole('button', { name: 'Importar' }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    await expect(janela.getByRole('button', { name: /Técnicos/ })).toBeVisible();
    await expect(janela.getByRole('button', { name: /Pessoal do hotel/ })).toBeVisible();
    await expect(janela.getByText('Quem já tiver ficha com o mesmo email')).toBeVisible();
});
