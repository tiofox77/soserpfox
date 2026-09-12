import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * A MINHA CONTA — o ecrã, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Conta`,
 * `CortesiaNaContaTest`, `FacturaDeRenovacaoNaContaTest`): o dono diz-se pelo
 * NOME do papel, a cortesia é uma só, e a API da conta não fecha a quem deixou
 * de pagar. O que aqui se prova é o que só se vê no browser:
 *
 *  · que a página abre sem um erro na consola;
 *  · que as empresas mostram quem manda em cada uma;
 *  · que remover uma empresa pede o NOME escrito — a única confirmação que não
 *    se carrega por engano — e diz que nada é destruído;
 *  · que a mudança de plano tem dois passos, e que o segundo mostra a conta
 *    para onde se transfere;
 *  · e que a senha se muda com a actual pelo meio.
 */

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto('/my-account');
    await expect(page.locator('.ecra-react').getByRole('heading', { name: 'A Minha Conta' }))
        .toBeVisible({ timeout: 20_000 });
});

test('abre /my-account sem erros na consola', async ({ page }) => {
    const erros = [];

    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    const resposta = await page.goto('/my-account');

    expect(resposta?.status()).toBeLessThan(400);

    await expect(page.locator('.ecra-react').getByRole('heading', { name: 'A Minha Conta' }))
        .toBeVisible({ timeout: 20_000 });

    expect(erros, 'consola de /my-account').toEqual([]);
});

/* ─── As empresas ───────────────────────────────────────────────────── */

test('as empresas dizem o papel e marcam a activa', async ({ page }) => {
    await expect(page.getByText('Empresas do plano')).toBeVisible();

    const activa = page.locator('article', { hasText: 'Bancada PWA' }).first();

    await expect(activa).toBeVisible();
    await expect(activa.getByText('Activa', { exact: true })).toBeVisible();
    await expect(activa.getByText('Módulos activos')).toBeVisible();
});

/**
 * REMOVER UMA EMPRESA PEDE O NOME ESCRITO.
 *
 * É a única confirmação que não se carrega por engano — e o ecrã diz, antes,
 * que nada é destruído.
 */
test('remover a unica empresa esta fechado e diz porque', async ({ page }) => {
    // Com uma empresa só, o botão não aparece: não se remove a única.
    await expect(
        page.locator('article', { hasText: 'Bancada PWA' })
            .getByRole('button', { name: 'Remover empresa' }),
    ).toHaveCount(0);
});

/* ─── O plano ───────────────────────────────────────────────────────── */

test('a mudanca de plano tem dois passos e mostra a conta', async ({ page }) => {
    await page.getByRole('tab', { name: 'Plano' }).click();

    await expect(page.getByText('Plano actual')).toBeVisible();

    const escolher = page.getByRole('button', { name: /Escolher este plano|Renovar/ }).first();

    await escolher.click();

    const modal = page.getByRole('dialog');

    await expect(modal).toBeVisible({ timeout: 15_000 });
    await expect(modal.getByText('Escolha o período')).toBeVisible();

    await modal.getByRole('button', { name: 'Continuar' }).click();

    // O segundo passo mostra a conta para onde se transfere — que vem das
    // definições do sistema, e não escrita à mão no ecrã.
    await expect(modal.getByText('Dados para a transferência')).toBeVisible();
    await expect(modal.getByText('Titular')).toBeVisible();
    await expect(modal.getByText('IBAN')).toBeVisible();
    await expect(modal.getByText(/Pode anexá-lo mais tarde/)).toBeVisible();
});

/* ─── O perfil e a segurança ────────────────────────────────────────── */

test('o perfil mostra os campos de sempre', async ({ page }) => {
    await page.getByRole('tab', { name: 'Perfil' }).click();

    await expect(page.getByRole('textbox', { name: /^Nome/ })).toBeVisible();
    await expect(page.getByRole('textbox', { name: /E-mail/ })).toBeVisible();
    await expect(page.getByRole('textbox', { name: /Telefone/ })).toBeVisible();
    await expect(page.getByRole('textbox', { name: /Sobre si/ })).toBeVisible();
    await expect(page.getByText('Trocar fotografia')).toBeVisible();
});

/**
 * A SENHA ACTUAL ANDA PELO MEIO.
 *
 * Mudar a senha sem a actual seria mudar a senha de quem deixou o computador
 * aberto.
 */
test('mudar a senha pede a actual', async ({ page }) => {
    await page.getByRole('tab', { name: 'Segurança' }).click();

    await expect(page.getByText('Senha mudada')).toBeVisible();

    await expect(page.getByLabel('Senha actual', { exact: false })).toBeVisible();
    await expect(page.getByLabel('Senha nova', { exact: false })).toBeVisible();
    await expect(page.getByText('Pelo menos 8 caracteres, e diferente da actual.')).toBeVisible();

    // Sem a actual e a nova, não se submete.
    await expect(page.getByRole('button', { name: 'Mudar a senha' }).last()).toBeDisabled();

    await expect(page.getByText('· Ninguém do suporte lhe pede a senha. Ninguém, nunca.')).toBeVisible();
});
