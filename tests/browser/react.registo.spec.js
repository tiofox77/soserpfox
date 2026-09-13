import { expect, test } from '@playwright/test';

/**
 * O REGISTO DE UMA CONTA NOVA EM REACT, no browser.
 *
 * Percorre os passos sem finalizar (não se cria empresa nenhuma): os erros de
 * cada passo aparecem por baixo do campo, o regime escolhe-se num cartão, o
 * plano pago mostra os dados da transferência, e um F5 a meio não perde a
 * empresa — só pede a palavra-passe outra vez.
 */

const SENHA = 'segredo-forte-123';

test('os passos do registo, do nome ao pagamento, e o F5 a meio', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.context().clearCookies();
    await page.goto('/register');
    await expect(page.getByRole('heading', { name: 'Crie sua conta' })).toBeVisible({ timeout: 30_000 });

    // Recomeça limpo, se a sessão trouxer progresso de um ensaio anterior.
    await page.getByRole('button', { name: /^Próximo/ }).click();
    await expect(page.getByRole('alert').first()).toBeVisible();

    const email = `ensaio${Date.now()}@exemplo.ao`;
    await page.getByLabel(/Nome Completo/).fill('Ana Ensaio Silva');
    await page.getByLabel(/^Email/).fill(email);
    await page.getByLabel(/^Senha/).fill(SENHA);
    await page.getByLabel(/Confirmar Senha/).fill(SENHA);
    await page.getByRole('button', { name: /^Próximo/ }).click();

    await expect(page.getByRole('heading', { name: 'Dados da Empresa' })).toBeVisible();
    const nif = String(500000000 + Math.floor(Math.random() * 99999999));
    await page.getByLabel(/Nome da Empresa/).fill('Padaria do Ensaio');
    await page.getByLabel(/NIF da empresa/).fill(nif);
    await page.getByText('Regime Simplificado', { exact: true }).click();
    await expect(page.getByRole('radio', { name: /Regime Simplificado/ })).toBeChecked();

    // O F5 a meio: a empresa fica, a palavra-passe volta a ser pedida.
    await page.waitForTimeout(1200); // o guardar automático espera 800 ms
    await page.reload();
    await expect(page.getByText(/Os seus dados foram guardados/)).toBeVisible({ timeout: 30_000 });
    await page.getByLabel(/^Senha/).fill(SENHA);
    await page.getByLabel(/Confirmar Senha/).fill(SENHA);
    await page.getByRole('button', { name: /^Próximo/ }).click();
    await expect(page.getByLabel(/Nome da Empresa/)).toHaveValue('Padaria do Ensaio');
    await page.screenshot({ path: 'test-results/registo-empresa.png', fullPage: true });

    await page.getByRole('button', { name: /^Próximo/ }).click();
    await expect(page.getByRole('heading', { name: 'Escolha seu Plano' })).toBeVisible();
    await page.waitForTimeout(800);
    await page.screenshot({ path: 'test-results/registo-planos.png', fullPage: true });

    // Um plano pago, se houver: mostra o IBAN e pede o comprovativo.
    const pago = page.getByRole('radio').filter({ hasNotText: /^0\b/ }).filter({ hasNotText: 'Já utilizado' }).last();
    await pago.click();
    await page.getByRole('button', { name: /^Próximo/ }).click();
    await expect(page.getByText('Plano Selecionado')).toBeVisible();
    await page.waitForTimeout(600);
    await page.screenshot({ path: "test-results/registo-pagamento.png", fullPage: true });

    // Sem aceitar os termos não se finaliza.
    await expect(page.getByRole('button', { name: /Finalizar cadastro/ })).toBeDisabled();

    // Recomeçar pede confirmação e volta ao passo 1.
    await page.getByRole('button', { name: 'Recomeçar', exact: true }).first().click();
    await page.getByRole('dialog').getByRole('button', { name: 'Recomeçar' }).click();
    await expect(page.getByRole('heading', { name: 'Crie sua conta' })).toBeVisible();
    await expect(page.getByLabel(/Nome Completo/)).toHaveValue('');

    expect(erros.filter((e) => !/422|Failed to load resource/.test(e))).toEqual([]);
});
