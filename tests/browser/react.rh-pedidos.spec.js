import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS SEIS PEDIDOS DO RH EM REACT — um ecrã para todos.
 *
 * Férias, licenças, horas extras, turno nocturno, adiantamentos e descontos
 * passam pelo mesmo ecrã, com o `tipo` na morada. O que aqui se prova é que
 * cada um se desenha com o SEU esquema — título, cor, campos, colunas e
 * estados — e que o ecrã não sabe nada sobre nenhum deles.
 *
 * As regras — quem aprova, a recusa com motivo, pagar só depois de aprovado,
 * eliminar só o pendente — estão provadas em `ApiDosPedidosDeRhTest`.
 */

const ECRAS = [
    { morada: '/hr/vacations', titulo: 'Férias', novo: 'Novo Pedido', calendario: true },
    { morada: '/hr/leaves', titulo: 'Licenças e Faltas', novo: 'Nova Licença', calendario: true },
    { morada: '/hr/overtime', titulo: 'Horas Extras', novo: 'Lançar Horas', calendario: false },
    { morada: '/hr/overtime-night-shift', titulo: 'Turno Nocturno', novo: 'Lançar Noites', calendario: false },
    { morada: '/hr/advances', titulo: 'Adiantamentos', novo: 'Novo Adiantamento', calendario: false },
    { morada: '/hr/salary-discounts', titulo: 'Descontos Salariais', novo: 'Novo Desconto', calendario: false },
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

for (const ecra of ECRAS) {
    test(`${ecra.morada} abre com a faixa e o botao de criar`, async ({ page }) => {
        await page.goto(ecra.morada);

        await expect(page.getByRole('heading', { name: ecra.titulo }).first()).toBeVisible({ timeout: 20_000 });
        await expect(page.getByRole('button', { name: ecra.novo })).toBeVisible();
    });

    test(`${ecra.morada} nao tem erros na consola`, async ({ page }) => {
        const erros = [];
        page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.goto(ecra.morada);
        await expect(page.getByRole('button', { name: ecra.novo })).toBeVisible({ timeout: 20_000 });

        expect(erros).toEqual([]);
    });
}

/**
 * O CALENDÁRIO só existe onde o pedido OCUPA DIAS.
 *
 * Nas férias e nas licenças responde à pergunta que se faz antes de aprovar
 * mais um: «quem já está fora nessa semana?». Numa hora extra não há dias
 * ocupados e o botão nem aparece.
 */
test('o calendario existe nas ferias e nao nas horas extras', async ({ page }) => {
    await page.goto('/hr/vacations');
    await expect(page.getByRole('button', { name: 'Novo Pedido' })).toBeVisible({ timeout: 20_000 });

    const paraCalendario = page.getByRole('button', { name: 'Calendário' });
    await expect(paraCalendario).toBeVisible();

    await paraCalendario.click();

    // A grelha do mês, com os sete dias da semana no cabeçalho.
    await expect(page.getByRole('heading', { name: 'Calendário' })).toBeVisible({ timeout: 20_000 });
    for (const dia of ['Seg', 'Sex', 'Dom']) {
        await expect(page.getByText(dia, { exact: true }).first()).toBeVisible();
    }
    await expect(page.getByText('Por aprovar')).toBeVisible();

    // E volta-se à lista.
    await page.getByRole('button', { name: 'Lista' }).click();
    await expect(page.getByRole('heading', { name: 'Filtros' })).toBeVisible();

    // Nas horas extras não há calendário nenhum.
    await page.goto('/hr/overtime');
    await expect(page.getByRole('button', { name: 'Lançar Horas' })).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('button', { name: 'Calendário' })).toHaveCount(0);
});

/**
 * CADA PEDIDO DESENHA-SE COM O SEU ESQUEMA.
 *
 * O formulário das férias pede o ano de referência e o substituto; o da hora
 * extra pede o modo de lançamento e o tipo; o do adiantamento pede o valor e
 * as prestações. É o servidor que o diz — o ecrã é o mesmo.
 */
test('o formulario das ferias pede o que so as ferias pedem', async ({ page }) => {
    await page.goto('/hr/vacations');
    await page.getByRole('button', { name: 'Novo Pedido' }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    for (const campo of [/^Funcionário/, /^Ano de referência/, /^Tipo/, /^Início/, /^Fim/, /^Substituto/]) {
        await expect(janela.getByLabel(campo)).toBeVisible();
    }
});

test('o formulario da hora extra pede o modo de lancamento', async ({ page }) => {
    await page.goto('/hr/overtime');
    await page.getByRole('button', { name: 'Lançar Horas' }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    await expect(janela.getByLabel(/^Como se lança/)).toBeVisible();
    await expect(janela.getByLabel(/^Das/)).toHaveAttribute('type', 'time');
    await expect(janela.getByLabel(/^Às/)).toHaveAttribute('type', 'time');
    await expect(janela.getByLabel(/^Tipo/)).toBeVisible();
});

test('o formulario do adiantamento pede o valor e as prestacoes', async ({ page }) => {
    await page.goto('/hr/advances');
    await page.getByRole('button', { name: 'Novo Adiantamento' }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    await expect(janela.getByLabel(/^Valor pedido/)).toBeVisible();
    await expect(janela.getByLabel(/^Prestações/)).toBeVisible();
    await expect(janela.getByLabel(/^Motivo/)).toBeVisible();
});

/**
 * PEDIR, E O QUE O SERVIÇO RESPONDE.
 *
 * A recusa do serviço — «não tem dias de férias suficientes» — é uma regra de
 * negócio e não um erro de campo: aparece inteira, no topo do formulário.
 */
test('um pedido de ferias sem direito diz porque nao pode', async ({ page }) => {
    await page.goto('/hr/vacations');
    await page.getByRole('button', { name: 'Novo Pedido' }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    await janela.getByLabel(/^Funcionário/).selectOption({ index: 1 });
    await janela.getByLabel(/^Ano de referência/).fill(String(new Date().getFullYear()));

    const daquiA = (dias) => new Date(Date.now() + dias * 86_400_000).toISOString().slice(0, 10);

    // Sessenta dias seguidos: ninguém tem esse direito.
    await janela.getByLabel(/^Início/).fill(daquiA(30));
    await janela.getByLabel(/^Fim/).fill(daquiA(90));

    await janela.getByRole('button', { name: 'Registar' }).click();

    await expect(janela.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });
});

/** Um desconto salarial regista-se, e aparece na lista com o estado pendente. */
test('um desconto salarial regista-se e fica pendente', async ({ page }) => {
    await page.goto('/hr/salary-discounts');
    await page.getByRole('button', { name: 'Novo Desconto' }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible({ timeout: 20_000 });

    await janela.getByLabel(/^Funcionário/).selectOption({ index: 1 });
    await janela.getByLabel(/^Tipo de desconto/).selectOption('dano');
    await janela.getByLabel(/^Valor/).fill('45000');
    await janela.getByLabel(/^Prestações/).fill('3');
    await janela.getByLabel(/^Motivo/).fill('Ensaio automatizado de desconto salarial.');

    const gravado = page.waitForResponse(
        (r) => r.url().includes('/rh/pedidos/descontos') && r.request().method() === 'POST',
        { timeout: 20_000 },
    );

    await janela.getByRole('button', { name: 'Registar' }).click();

    expect((await gravado).status()).toBe(201);
    await expect(janela).toBeHidden({ timeout: 20_000 });

    // E está na lista, pendente e à espera de decisão.
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Pendente').first()).toBeVisible();
});

/**
 * A DECISÃO PEDE MOTIVO quando é recusa.
 *
 * O modal abre com a caixa do motivo, e a frase que explica que o que lá se
 * escreve é o que a pessoa vai ler.
 */
test('recusar abre o modal com a caixa do motivo', async ({ page }) => {
    await page.goto('/hr/salary-discounts');
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    const recusar = page.getByRole('button', { name: /^Recusar / }).first();

    await expect(recusar).toBeVisible();
    await recusar.click();

    const janela = page.getByRole('dialog');

    await expect(janela).toBeVisible({ timeout: 20_000 });
    await expect(janela.getByLabel(/^Motivo da recusa/)).toBeVisible();
    await expect(janela.getByText('é o que a pessoa vai ler')).toBeVisible();
});
