import { expect, test } from '@playwright/test';

/**
 * A PÁGINA PÚBLICA DO SALÃO EM REACT, no browser — sem sessão nenhuma.
 *
 * A empresa de bancada tem o salão em /agendar/meu-salao-1. Abre-se a montra,
 * abre-se a marcação, escolhe-se um serviço e um profissional, e os horários
 * chegam do servidor. Não se finaliza: não se cria marcação nenhuma.
 */

test('a montra abre e a marcacao chega aos horarios', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.context().clearCookies();
    await page.goto('/agendar/meu-salao-1');

    await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible({ timeout: 30_000 });
    await page.waitForTimeout(600);
    await page.screenshot({ path: 'test-results/salao-montra.png' });

    await page.getByRole('button', { name: 'Agendar Agora' }).click();
    const dialogo = page.getByRole('dialog');
    await expect(dialogo.getByRole('heading', { name: 'Escolha os Serviços' })).toBeVisible();

    const servicos = dialogo.getByRole('checkbox');
    if (await servicos.count() === 0) {
        test.info().annotations.push({ type: 'aviso', description: 'o salão de bancada não tem serviços online' });
        return;
    }

    await servicos.first().click();
    await expect(servicos.first()).toHaveAttribute('aria-checked', 'true');
    await dialogo.getByRole('button', { name: /Continuar/ }).click();

    await expect(dialogo.getByText('Data e Horário')).toBeVisible();
    const pessoas = dialogo.getByRole('radiogroup', { name: 'Profissional' }).getByRole('radio');
    if (await pessoas.count() > 0) {
        await pessoas.first().click();
        await expect(dialogo.getByRole('radiogroup', { name: 'Horário' }).or(dialogo.getByText('Sem horários disponíveis'))).toBeVisible();
    }
    await page.waitForTimeout(400);
    await page.screenshot({ path: 'test-results/salao-horarios.png' });

    // Escape fecha a marcação.
    await page.keyboard.press('Escape');
    await expect(dialogo).toBeHidden();

    expect(erros).toEqual([]);
});
