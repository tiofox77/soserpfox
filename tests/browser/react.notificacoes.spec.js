import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * AS NOTIFICAÇÕES — os dois ecrãs, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Notificacoes`,
 * `NotificacoesSegredosTest`): os segredos não saem do servidor, um modelo de
 * outra empresa não se lê nem se testa, e ver não é configurar. O que aqui se
 * prova é o que só se vê no browser:
 *
 *  · que as duas moradas abrem sem um erro na consola;
 *  · que um campo de segredo abre VAZIO e diz que há um guardado — era este o
 *    defeito: o valor viajava para dentro da página, e o `type="password"`
 *    esconde-o no ecrã, não no código-fonte;
 *  · que um canal só pede a configuração dele quando está ligado;
 *  · que o editor de modelos tem as variáveis do módulo à vista de quem
 *    escreve, e conta os caracteres do SMS;
 *  · e que o teste diz, por escrito, que sai pelo mesmo caminho do envio a
 *    sério — o do ecrã antigo dizia «enviado com sucesso via SMS» sem nada ter
 *    saído.
 */

const MORADAS = [
    ['/notifications/settings', 'Notificações'],
    ['/notifications/templates', 'Modelos de Notificação'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test.describe('as moradas das notificações', () => {
    for (const [morada, titulo] of MORADAS) {
        test(`abre ${morada}`, async ({ page }) => {
            const erros = [];

            page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
            page.on('pageerror', (e) => erros.push(String(e)));

            const resposta = await page.goto(morada);

            expect(resposta?.status(), `${morada} respondeu ${resposta?.status()}`).toBeLessThan(400);

            await expect(
                page.locator('.ecra-react').getByRole('heading', { name: titulo }).first(),
            ).toBeVisible({ timeout: 20_000 });

            expect(erros, `consola de ${morada}`).toEqual([]);
        });
    }
});

/* ─── As definições ─────────────────────────────────────────────────── */

test.describe('as definições dos canais', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/notifications/settings');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Notificações' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * O SEGREDO ABRE VAZIO E DIZ QUE EXISTE.
     *
     * Sem o sinal, o campo em branco lia-se como «a minha senha desapareceu» —
     * e alguém a reescrevia por engano.
     */
    test('o campo de senha abre vazio e diz o estado', async ({ page }) => {
        const senha = page.getByLabel('Senha', { exact: false }).first();

        await expect(senha).toHaveValue('');
        await expect(page.getByText('Por configurar').first()).toBeVisible();
        await expect(page.getByText('Um campo de segredo em branco mantém o que já está guardado.')).toBeVisible();
    });

    /** O interruptor do canal diz o que acontece se ficar desligado. */
    test('o interruptor do email explica-se', async ({ page }) => {
        await expect(page.getByText('Sem isto, nenhum aviso de e-mail sai desta empresa.')).toBeVisible();
    });

    /** E o teste do e-mail diz para onde vai antes de se carregar nele. */
    test('o teste do email diz para onde vai', async ({ page }) => {
        await expect(page.getByRole('button', { name: 'Enviar e-mail de teste' })).toBeVisible();
        await expect(page.getByText('Vai para o próprio endereço remetente.')).toBeVisible();
    });

    /**
     * O SMS TEM UMA CHAVE E UM AVISO.
     *
     * Ligar a operadora sem chave era ficar com o canal «activo» e nenhum SMS a
     * sair — e ninguém a perceber porquê.
     */
    test('a aba do sms avisa que sem chave nao sai nada', async ({ page }) => {
        await page.getByRole('tab', { name: 'SMS' }).click();

        await expect(page.getByText('O SMS chega a quem não tem internet — e é o que custa dinheiro por mensagem.'))
            .toBeVisible();

        await page.getByRole('combobox', { name: /Operadora/ }).selectOption('telcosms');

        await expect(page.getByText('Sem chave, o canal fica activo e não sai SMS nenhum.')).toBeVisible();
        // A TelcoSMS não deixa escolher o remetente.
        await expect(page.getByRole('textbox', { name: /Remetente/ })).toBeDisabled();
    });

    /** O WhatsApp diz o que o distingue: só manda modelos aprovados. */
    test('a aba do whatsapp explica os modelos aprovados', async ({ page }) => {
        await page.getByRole('tab', { name: 'WhatsApp' }).click();

        await expect(page.getByText('O WhatsApp só manda MODELOS APROVADOS pelo fornecedor — texto livre é recusado.'))
            .toBeVisible();
        await expect(page.getByRole('button', { name: 'Buscar os modelos aprovados' })).toBeVisible();
    });
});

/* ─── Os modelos ────────────────────────────────────────────────────── */

test.describe('os modelos', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/notifications/templates');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Modelos de Notificação' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /** Um modelo sem canal nunca manda nada — e o ecrã conta-os. */
    test('conta os modelos sem canal', async ({ page }) => {
        await expect(page.getByText('Um modelo sem canal ligado nunca manda nada')).toBeVisible();
    });

    /**
     * AS VARIÁVEIS DO MÓDULO À VISTA DE QUEM ESCREVE.
     *
     * Quem escreve o texto não tem de decorar os nomes das variáveis nem ir
     * procurá-los a outro ecrã.
     */
    test('o editor mostra as variaveis do modulo e conta o sms', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo modelo' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('Variáveis deste módulo — carregue para copiar')).toBeVisible();

        // Ligar o SMS traz o texto — e a contagem de caracteres, porque acima
        // de 160 são duas mensagens e paga-se as duas.
        await modal.getByRole('checkbox', { name: 'SMS' }).check();

        await expect(modal.getByText(/caracteres — acima de 160 são duas mensagens/)).toBeVisible();

        // E o WhatsApp diz porque é que pede um SID e não um texto.
        await modal.getByRole('checkbox', { name: 'WhatsApp' }).check();

        await expect(modal.getByText(/O WhatsApp só manda modelos aprovados pelo fornecedor/)).toBeVisible();
    });

    /**
     * O TESTE DIZ POR ESCRITO QUE É O CAMINHO A SÉRIO.
     *
     * O do ecrã antigo reimplementava os canais por dentro e o do SMS era um
     * `TODO` que dizia «enviado com sucesso» sem nada ter saído.
     */
    test('o teste diz que sai pelo caminho do envio a serio', async ({ page }) => {
        await page.getByRole('button', { name: 'Testar' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('O teste sai pelo mesmo caminho do envio a sério — mesmas credenciais, mesmo texto, mesma operadora.'))
            .toBeVisible();
        await expect(modal.getByText('Por que canais')).toBeVisible();
        await expect(modal.getByRole('button', { name: 'Pôr dados de exemplo' })).toBeVisible();

        // A pré-visualização vem já com o texto POSTO, e não com as chavetas.
        await expect(modal.getByText('Como vai ficar')).toBeVisible();
    });
});
