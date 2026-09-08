import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS CATÁLOGOS DO RH EM REACT — departamentos, cargos e turnos.
 *
 * Os três passaram para o ecrã genérico de catálogos, o mesmo dos
 * fornecedores e dos bancos. O que aqui se prova é o que é NOVO neste ecrã e
 * que nenhum outro catálogo exercita:
 *
 *  · o campo de HORA, que é um `<input type="time">` e não uma caixa de texto;
 *  · os DIAS DA SEMANA, sete botões que se acendem;
 *  · a ATRIBUIÇÃO EM LOTE, que o ecrã dos turnos em Blade tinha e que se
 *    perderia se não viajasse.
 *
 * As regras — permissões, guardas de apagar, hora que sobrevive à viagem —
 * estão provadas em `CatalogosDoRhTest`, contra a mesma API.
 */

const ECRAS = [
    { morada: '/hr/departments', titulo: 'Departamentos', novo: 'Novo Departamento' },
    { morada: '/hr/positions', titulo: 'Cargos', novo: 'Novo Cargo' },
    { morada: '/hr/shifts', titulo: 'Turnos', novo: 'Novo Turno' },
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

for (const ecra of ECRAS) {
    test(`${ecra.morada} abre com a faixa e o botao de criar`, async ({ page }) => {
        await page.goto(ecra.morada);

        await expect(page.getByRole('heading', { name: ecra.titulo }).first()).toBeVisible({ timeout: 20_000 });
        await expect(page.getByRole('button', { name: ecra.novo }).first()).toBeVisible();
    });

    test(`${ecra.morada} nao tem erros na consola`, async ({ page }) => {
        const erros = [];
        page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.goto(ecra.morada);
        await expect(page.getByRole('button', { name: ecra.novo }).first()).toBeVisible({ timeout: 20_000 });

        expect(erros).toEqual([]);
    });
}

/**
 * O FORMULÁRIO DO TURNO tem os campos que a base guarda — e os dois que este
 * ecrã ganhou: a hora e os dias.
 */
test('o formulario do turno abre com a hora e os dias por omissao', async ({ page }) => {
    await page.goto('/hr/shifts');
    await page.getByRole('button', { name: 'Novo Turno' }).first().click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    // A HORA é um campo de hora a sério, com a máscara do sistema.
    const entrada = janela.getByLabel('Entrada', { exact: false }).first();
    await expect(entrada).toHaveAttribute('type', 'time');
    await expect(entrada).toHaveValue('08:00');
    await expect(janela.getByLabel('Saída', { exact: false }).first()).toHaveValue('17:00');

    // OS DIAS nascem de segunda a sexta — a semana de trabalho.
    const dias = janela.getByRole('group', { name: /Dias de trabalho/ });
    await expect(dias).toBeVisible();

    await expect(dias.locator('button[title="Segunda-feira"]')).toHaveAttribute('aria-pressed', 'true');
    await expect(dias.locator('button[title="Sexta-feira"]')).toHaveAttribute('aria-pressed', 'true');
    await expect(dias.locator('button[title="Sábado"]')).toHaveAttribute('aria-pressed', 'false');
    await expect(dias.locator('button[title="Domingo"]')).toHaveAttribute('aria-pressed', 'false');

    // E acendem-se ao carregar.
    await dias.locator('button[title="Sábado"]').click();
    await expect(dias.locator('button[title="Sábado"]')).toHaveAttribute('aria-pressed', 'true');

    // O resto dos campos do turno está lá.
    for (const campo of [/^Nome/, /^Código/, /^Horas por dia/, /^Cor/, /^Ordem/]) {
        await expect(janela.getByLabel(campo).first()).toBeVisible();
    }

    await expect(janela.getByLabel(/Turno nocturno/)).toBeVisible();
});

/**
 * GRAVAR UM TURNO E VOLTAR A ABRI-LO.
 *
 * É a viagem que partia: a hora ia como Carbon, voltava como data inteira, e
 * o campo abria vazio. Grava-se, reabre-se para editar, e a hora tem de estar
 * lá tal como se escreveu.
 */
test('a hora e os dias sobrevivem a gravar e reabrir', async ({ page }) => {
    await page.goto('/hr/shifts');
    await page.getByRole('button', { name: 'Novo Turno' }).first().click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    const nome = 'Turno de Ensaio ' + String(Date.now()).slice(-6);

    await janela.getByLabel(/^Nome/).first().fill(nome);
    await janela.getByLabel('Entrada', { exact: false }).first().fill('06:30');
    await janela.getByLabel('Saída', { exact: false }).first().fill('14:45');
    await janela.getByRole('group', { name: /Dias de trabalho/ }).locator('button[title="Sábado"]').click();

    const gravado = page.waitForResponse(
        (r) => r.url().includes('/catalogos/turnos') && r.request().method() === 'POST',
        { timeout: 20_000 },
    );

    await janela.getByRole('button', { name: /^Guardar$/ }).click();

    expect((await gravado).status()).toBe(201);
    await expect(janela).toBeHidden({ timeout: 20_000 });

    // Reabrir para editar: a hora está lá como se escreveu.
    await page.getByPlaceholder('Nome, código ou descrição').fill(nome);
    await expect(page.getByRole('cell', { name: nome, exact: true })).toBeVisible({ timeout: 20_000 });

    await page.getByRole('button', { name: new RegExp('^Editar: ' + nome) }).click();

    const edicao = page.getByRole('dialog');
    await expect(edicao).toBeVisible();
    await expect(edicao.getByLabel('Entrada', { exact: false }).first()).toHaveValue('06:30');
    await expect(edicao.getByLabel('Saída', { exact: false }).first()).toHaveValue('14:45');
    await expect(edicao.getByRole('group', { name: /Dias de trabalho/ }).locator('button[title="Sábado"]'))
        .toHaveAttribute('aria-pressed', 'true');
});

/**
 * ATRIBUIR EM LOTE — o modal que o ecrã em Blade tinha.
 *
 * Pôr trinta pessoas no turno da manhã uma a uma, pela ficha de cada uma, é
 * meia hora de trabalho. O modal escolhe-as todas de uma vez, e o que se
 * grava é a lista completa: quem for desmarcado sai.
 */
test('o modal de atribuir abre com a procura e o marcar todos', async ({ page }) => {
    await page.goto('/hr/shifts');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    const botao = page.getByRole('button', { name: /^Atribuir a / }).first();

    await expect(botao).toBeVisible();
    await botao.click();

    const janela = page.getByRole('dialog');

    await expect(janela).toBeVisible({ timeout: 20_000 });
    await expect(janela.getByRole('searchbox')).toBeVisible();
    await expect(janela.getByRole('button', { name: /Marcar todos|Desmarcar todos/ })).toBeVisible();
    await expect(janela.getByRole('button', { name: 'Gravar atribuição' })).toBeVisible();

    // A frase que explica a regra tem de estar à vista antes de se gravar.
    await expect(janela.getByText('Grava-se a lista completa')).toBeVisible();
});

/** Onde não se atribui, o botão não existe — os departamentos não o têm. */
test('o departamento nao oferece atribuicao', async ({ page }) => {
    await page.goto('/hr/departments');
    await expect(page.getByRole('button', { name: 'Novo Departamento' }).first()).toBeVisible({ timeout: 20_000 });

    await expect(page.getByRole('button', { name: /^Atribuir a / })).toHaveCount(0);
});
