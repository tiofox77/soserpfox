import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O MÓDULO DO SALÃO — os sete ecrãs, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Salao`): a tabela de
 * transições, a sobreposição de horas, o cliente obrigatório, as permissões
 * porta a porta. O que aqui se prova é o que só se vê no browser:
 *
 *  · que as oito moradas abrem, e sem um erro na consola — que é a classe de
 *    defeito mais cara deste projecto;
 *  · que o painel desenha a AGENDA POR CADEIRA, e não uma lista por hora;
 *  · que as marcações têm as duas vistas, lista e calendário, no mesmo ecrã;
 *  · que o formulário da marcação NÃO tem campo de preço nem de duração — saem
 *    do catálogo, e um preço escrito à mão era um desconto que ninguém deu;
 *  · que as alergias da cliente se vêem sem abrir separador nenhum;
 *  · e que as definições separam a agenda da página pública.
 *
 * A bancada monta-se com `php artisan bancada:pwa`.
 */

/**
 * As moradas, e o título que o ECRÃ desenha (não o do layout).
 *
 * A diferença importa: o cabeçalho da página vem do Blade e aparece mesmo que
 * o React nunca monte. Procurar o título DENTRO da ilha é o que distingue um
 * ecrã que abriu de uma página que ficou no esqueleto cinzento.
 */
const MORADAS = [
    ['/salon/dashboard', 'Painel do Salão'],
    ['/salon/appointments', 'Marcações'],
    ['/salon/services', 'Serviços'],
    ['/salon/services/categories', 'Serviços'],
    ['/salon/professionals', 'Profissionais'],
    ['/salon/clients', 'Clientes do Salão'],
    ['/salon/reports/time', 'Relatório de Tempos'],
    ['/salon/settings', 'Definições do Salão'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

/* ─── Todas abrem ───────────────────────────────────────────────────── */

test.describe('as moradas do salão', () => {
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

/* ─── O painel ──────────────────────────────────────────────────────── */

test.describe('o painel', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/salon/dashboard');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Painel do Salão' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * A AGENDA É POR CADEIRA, e é a razão de ser do ecrã.
     *
     * Um salão não trabalha por lista de marcações: trabalha por pessoa. Quem
     * abre isto de manhã quer ver quem tem o dia cheio e quem tem buracos — e
     * isso não se vê numa lista ordenada por hora.
     */
    test('desenha a agenda por profissional e nao uma lista por hora', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'A agenda do dia' })).toBeVisible();
        await expect(page.getByText('Quem tem o dia cheio, e quem tem buracos')).toBeVisible();
    });

    /**
     * AS FALTAS TÊM CARTÃO PRÓPRIO.
     *
     * Um salão com muitos «não compareceu» tem um problema de confirmação, não
     * de procura — e isso não se via em lado nenhum.
     */
    test('as faltas tem cartao proprio', async ({ page }) => {
        await expect(page.getByText('Faltas', { exact: true })).toBeVisible();
        await expect(page.getByText('Mede a confirmação, não a procura')).toBeVisible();
    });

    /** E o dia anda para trás e para a frente sem sair da página. */
    test('o dia anda sem recarregar a pagina', async ({ page }) => {
        await expect(page.getByRole('button', { name: 'Dia seguinte' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Hoje' })).toBeVisible();
    });
});

/* ─── As marcações ──────────────────────────────────────────────────── */

test.describe('as marcações', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/salon/appointments');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Marcações' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /** As duas vistas do mesmo dia, no mesmo ecrã. */
    test('a lista e o calendario sao o mesmo ecra', async ({ page }) => {
        await expect(page.getByRole('button', { name: 'Lista' })).toBeVisible();

        await page.getByRole('button', { name: 'Calendário' }).click();

        // O calendário começa e acaba em semanas inteiras — a grelha tem
        // sempre os sete dias da semana por cabeçalho.
        await expect(page.getByText('Seg', { exact: true }).first()).toBeVisible({ timeout: 15_000 });
    });

    /**
     * O FORMULÁRIO NÃO TEM PREÇO NEM DURAÇÃO.
     *
     * Quem marca escolhe serviços; é o catálogo que diz quanto tempo levam e
     * quanto custam. Um preço escrito no browser era um desconto que ninguém
     * deu, e uma duração à mão era a agenda do dia a desalinhar-se.
     */
    test('o formulario nao deixa escrever o preco nem a duracao', async ({ page }) => {
        await page.getByRole('button', { name: 'Nova marcação' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('A duração e o preço saem dos serviços escolhidos')).toBeVisible();

        // A cliente é OBRIGATÓRIA: a coluna é NOT NULL, e o ecrã antigo dava-a
        // como opcional — marcar sem cliente rebentava com um erro de base de
        // dados à frente de quem estava ao telefone.
        await expect(modal.getByRole('combobox', { name: /Cliente/ })).toBeVisible();

        // E não há caixa de preço nem de minutos.
        await expect(modal.getByRole('spinbutton')).toHaveCount(0);
    });
});

/* ─── Os serviços ───────────────────────────────────────────────────── */

test.describe('os serviços', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/salon/services');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Serviços' }).first())
            .toBeVisible({ timeout: 20_000 });
    });

    /** As categorias vivem ao lado: a ordem delas É a ordem da carta de serviços. */
    test('as categorias estao no mesmo ecra', async ({ page }) => {
        await page.getByRole('tab', { name: 'Categorias' }).click();

        await expect(page.getByText('A ordem daqui é a ordem por que os serviços aparecem'))
            .toBeVisible({ timeout: 15_000 });
    });

    /** E a duração é o campo que manda — é ela que enche a agenda. */
    test('o servico novo pede a duracao', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo serviço' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('É ela que enche a agenda.')).toBeVisible();
    });
});

/* ─── Os profissionais ──────────────────────────────────────────────── */

test.describe('os profissionais', () => {
    /**
     * O HORÁRIO DECIDE O QUE A PÁGINA PÚBLICA OFERECE.
     *
     * Sem dias de trabalho e sem horas de entrada e de saída, a página de
     * marcação não oferece hora nenhuma — e ninguém percebia porquê.
     */
    test('a ficha pede os dias e as horas de trabalho', async ({ page }) => {
        await page.goto('/salon/professionals');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Profissionais' }))
            .toBeVisible({ timeout: 20_000 });

        await page.getByRole('button', { name: 'Novo profissional' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('O horário decide o que a página de marcação oferece')).toBeVisible();
        await expect(modal.getByText('Serviços que faz')).toBeVisible();
    });
});

/* ─── As clientes ───────────────────────────────────────────────────── */

test.describe('as clientes', () => {
    /**
     * AS ALERGIAS NÃO SE ESCONDEM ATRÁS DE UM SEPARADOR.
     *
     * Num salão, uma tinta no couro cabeludo de quem é alérgica é uma ida ao
     * hospital: têm de estar no formulário, à vista, e não num painel que
     * ninguém abre.
     */
    test('a ficha mostra as alergias a vista', async ({ page }) => {
        await page.goto('/salon/clients');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Clientes do Salão' }))
            .toBeVisible({ timeout: 20_000 });

        await page.getByRole('button', { name: 'Nova cliente' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('Alergias', { exact: true })).toBeVisible();
        await expect(modal.getByPlaceholder('Amoníaco, latex, perfume…')).toBeVisible();
    });
});

/* ─── Os tempos ─────────────────────────────────────────────────────── */

test.describe('o relatório de tempos', () => {
    /**
     * O PREVISTO AO LADO DO REAL.
     *
     * É a única forma de saber se a agenda está bem montada: um serviço que o
     * catálogo diz durar 30 minutos e leva sempre 50 enche o dia de atrasos.
     */
    test('poe o previsto ao lado do real', async ({ page }) => {
        await page.goto('/salon/reports/time');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Relatório de Tempos' }))
            .toBeVisible({ timeout: 20_000 });

        await expect(page.getByText('O previsto ao lado do real — é o que monta a agenda')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Por profissional' })).toBeVisible();
    });
});

/* ─── As definições ─────────────────────────────────────────────────── */

test.describe('as definições', () => {
    /**
     * DUAS GRAVAÇÕES QUE NÃO SE MISTURAM: as regras da agenda de um lado, a
     * página pública do outro. Guardar o horário não devia publicar textos
     * ainda por rever, nem o contrário.
     */
    test('separam a agenda da pagina publica', async ({ page }) => {
        await page.goto('/salon/settings');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Definições do Salão' }))
            .toBeVisible({ timeout: 20_000 });

        await expect(page.getByRole('heading', { name: 'Horário da casa' })).toBeVisible();

        await page.getByRole('tab', { name: 'Página de marcação' }).click();

        // O endereço público é o que está no cartaz e no QR já impresso —
        // mudá-lo parte tudo isso, e o ecrã tem de o dizer.
        await expect(page.getByRole('heading', { name: 'O endereço da página' }))
            .toBeVisible({ timeout: 15_000 });
    });
});
