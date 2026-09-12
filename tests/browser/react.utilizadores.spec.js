import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS UTILIZADORES, OS PAPÉIS E OS CONVITES — os três ecrãs, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Utilizadores`,
 * `AdminDefinePinTest`, `ModalDePapeisTest`): o papel é por empresa, quem tem
 * documentos não se apaga, um papel com gente não se elimina, e o catálogo só
 * mostra o que a empresa tem activo. O que aqui se prova é o que só se vê no
 * browser:
 *
 *  · que as três moradas abrem sem um erro na consola;
 *  · que o formulário do utilizador pede EMPRESA A EMPRESA o papel, porque é
 *    essa a forma do problema e não um `select` só;
 *  · que o tecto do plano se lê ANTES de alguém escrever o formulário todo;
 *  · que o PIN de turno se repõe daqui, e que o ecrã diz para que serve;
 *  · que o modal do papel mostra os módulos com a contagem de cada um, e tem
 *    os atalhos de marcar tudo e de só consulta;
 *  · e que apagar um papel com gente está fechado à chave, não escondido.
 */

const MORADAS = [
    ['/users', 'Gestão de Utilizadores'],
    ['/users/roles-permissions', 'Papéis e Permissões'],
    ['/users/invitations', 'Convites'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

test.describe('as moradas dos utilizadores', () => {
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

/* ─── A lista ───────────────────────────────────────────────────────── */

test.describe('a lista de utilizadores', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/users');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Gestão de Utilizadores' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * O TECTO DO PLANO LÊ-SE ANTES.
     *
     * Nada pior do que preencher o formulário todo para levar com «limite
     * atingido» no fim — e o número que conta é o das contas ACTIVAS.
     */
    test('mostra o tecto do plano antes de se abrir o formulario', async ({ page }) => {
        await expect(page.getByText('Utilizadores do plano')).toBeVisible();
        await expect(page.getByText('Só as contas activas contam. Desactivar liberta um lugar; eliminar não é preciso.'))
            .toBeVisible();
    });

    /**
     * O PAPEL É POR EMPRESA — e o formulário tem de mostrar isso.
     *
     * A mesma pessoa é gerente numa casa e caixa noutra. Um `select` só, com
     * «o papel», mentia numa das duas.
     */
    test('o formulario pede o papel empresa a empresa', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo utilizador' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('Empresas e papéis')).toBeVisible();
        await expect(modal.getByText('O papel vale dentro da empresa onde foi dado. Só aparecem as empresas a que tem acesso.'))
            .toBeVisible();

        // Um selector de papel POR EMPRESA, e não um só para tudo.
        await expect(modal.getByRole('combobox', { name: /Papel em/ }).first()).toBeVisible();

        // A palavra-passe é pedida com confirmação — é uma conta nova.
        await expect(modal.getByLabel('Confirmar palavra-passe')).toBeVisible();
    });

    /** O PIN de turno repõe-se daqui, e o ecrã diz para que serve. */
    test('o pin de turno explica-se antes de se escrever', async ({ page }) => {
        await page.getByRole('button', { name: 'Definir PIN de turno' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('É com este PIN que se abre turno no POS quando não há rede. Vai para os tablets na próxima sincronização.'))
            .toBeVisible();
        await expect(modal.getByText('4 a 6 dígitos. Nada de 1234 nem da data de nascimento.')).toBeVisible();
    });

    /** E eliminar diz porque é que, às vezes, não se pode. */
    test('eliminar avisa que quem emitiu documentos nao se apaga', async ({ page }) => {
        await page.getByRole('button', { name: 'Eliminar utilizador' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText(/Quem já emitiu documentos NÃO se elimina/)).toBeVisible();
    });
});

/* ─── Os papéis ─────────────────────────────────────────────────────── */

test.describe('os papéis', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/users/roles-permissions');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Papéis e Permissões' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * O MODAL DO PAPEL É POR MÓDULO, com a contagem de cada um.
     *
     * São 340 permissões: numa lista corrida ninguém as lê. Por módulo, com
     * «x/y» à frente, vê-se de relance o que já está dado.
     */
    test('o modal do papel mostra os modulos e os atalhos', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo papel' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('Só os módulos que esta empresa tem activos aparecem aqui')).toBeVisible();
        await expect(modal.getByRole('button', { name: 'Utilizadores e Papéis' })).toBeVisible();
        await expect(modal.getByRole('button', { name: 'Marcar tudo' })).toBeVisible();
        await expect(modal.getByRole('button', { name: 'Só consulta' })).toBeVisible();
        await expect(modal.getByText('Começar a partir de')).toBeVisible();
    });

    /** Marcar o módulo inteiro muda a contagem — e o botão passa a tirar. */
    test('marcar tudo muda a contagem do modulo', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo papel' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('0 permissões marcadas')).toBeVisible();

        await modal.getByRole('button', { name: 'Marcar tudo' }).click();

        await expect(modal.getByText('0 permissões marcadas')).toBeHidden();
        await expect(modal.getByRole('button', { name: 'Tirar tudo' })).toBeVisible();
    });

    /**
     * UM PAPEL COM GENTE NÃO SE APAGA — e o botão diz-lo em vez de o esconder.
     *
     * Esconder deixava quem olha a pensar que se tinha enganado a procurar.
     */
    test('o papel com gente nao se apaga', async ({ page }) => {
        const linha = page.locator('li', { hasText: 'Balcão da Bancada' }).first();

        await expect(linha).toBeVisible();
        await expect(linha.getByRole('button', { name: 'Eliminar papel' })).toBeDisabled();
    });

    /** O catálogo diz o que já não se faz aqui: inventar permissões. */
    test('o catalogo explica que as permissoes nao se inventam', async ({ page }) => {
        await page.getByRole('tab', { name: 'Catálogo de permissões' }).click();

        await expect(page.getByText(/Não se inventam permissões/)).toBeVisible();
    });

    /** E atribuir vale só nesta empresa. */
    test('atribuir diz que vale so nesta empresa', async ({ page }) => {
        await page.getByRole('tab', { name: 'Atribuir' }).click();

        await page.getByRole('button', { name: 'Papéis' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('O que aqui se muda vale só nesta empresa. Os papéis que esta pessoa tem noutras casas não se tocam.'))
            .toBeVisible();
    });
});

/* ─── Os convites ───────────────────────────────────────────────────── */

test.describe('os convites', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/users/invitations');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Convites' }).first())
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * CONVIDAR NÃO É CRIAR UMA CONTA.
     *
     * Quem é convidado escolhe a sua própria palavra-passe — e o ecrã diz-o,
     * porque é a diferença que justifica haver dois caminhos.
     */
    test('o convite diz que a palavra-passe e de quem o recebe', async ({ page }) => {
        await page.getByRole('button', { name: 'Convidar' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('A palavra-passe é escolhida por quem recebe o convite. Ninguém aqui a chega a saber.'))
            .toBeVisible();
        await expect(modal.getByRole('combobox', { name: /Papel/ })).toBeVisible();
        await expect(modal.getByText('O convite vale sete dias')).toBeVisible();
    });
});
