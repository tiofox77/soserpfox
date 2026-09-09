import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O MÓDULO DO HOTEL — os treze ecrãs, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Hotel`): a dedução dos
 * adiantamentos, o escopo de empresa, a tabela de transições, o preço das três
 * camadas. O que aqui se prova é o que só se vê no browser:
 *
 *  · que as treze moradas abrem, e sem um erro na consola — que é a classe de
 *    defeito mais cara deste projecto;
 *  · que a limpeza tem DUAS vistas da mesma coisa e que a planta se pinta;
 *  · que o calendário desenha as barras com o nome de quem lá dorme;
 *  · que o balcão só deixa avançar depois de haver quarto;
 *  · que o check-out mostra a conta ANTES de se carregar em fechar;
 *  · e que a PÁGINA PÚBLICA abre sem sessão nenhuma, com as cores da casa.
 *
 * A bancada monta-se com `php artisan bancada:pwa`: seis quartos, três
 * reservas (uma por estado), cinco tarefas de limpeza e a casa com endereço
 * público em `/hotel/booking/hotel-da-bancada`.
 */

/**
 * As treze moradas, e o título que o ECRÃ desenha (não o do layout).
 *
 * A diferença importa: o cabeçalho da página vem do Blade e aparece mesmo que
 * o React nunca monte. Procurar o título DENTRO da ilha é o que distingue um
 * ecrã que abriu de uma página que ficou no esqueleto cinzento.
 */
const MORADAS = [
    ['/hotel/dashboard', 'Painel do Hotel'],
    ['/hotel/reservations', 'Reservas'],
    ['/hotel/calendar', 'Calendário'],
    ['/hotel/walk-in', 'Balcão'],
    ['/hotel/checkout', 'Check-out'],
    ['/hotel/housekeeping', 'Housekeeping'],
    ['/hotel/maintenance', 'Manutenção'],
    ['/hotel/rates', 'Tarifas'],
    ['/hotel/seasons', 'Épocas'],
    ['/hotel/reports', 'Relatórios do Hotel'],
    ['/hotel/settings', 'Definições do Hotel'],
    ['/hotel/kiandastay', 'KiandaStay'],
    ['/hotel/rooms', 'Quartos'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

/* ─── Todas abrem ───────────────────────────────────────────────────── */

test.describe('as moradas do hotel', () => {
    for (const [morada, titulo] of MORADAS) {
        test(`abre ${morada}`, async ({ page }) => {
            const erros = [];

            page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
            page.on('pageerror', (e) => erros.push(String(e)));

            const resposta = await page.goto(morada);

            expect(resposta?.status(), `${morada} respondeu ${resposta?.status()}`).toBeLessThan(400);

            // DENTRO da ilha: prova que o React montou e desenhou a faixa.
            await expect(
                page.locator('.ecra-react').getByRole('heading', { name: titulo }).first(),
            ).toBeVisible({ timeout: 20_000 });

            expect(erros, `consola de ${morada}`).toEqual([]);
        });
    }
});

/* ─── A limpeza ─────────────────────────────────────────────────────── */

test.describe('limpeza', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/hotel/housekeeping');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Housekeeping' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * DUAS VISTAS DA MESMA COISA, e de propósito: o quadro é a fila de
     * trabalho, a planta responde a «este quarto pode ser vendido?».
     */
    test('o quadro e a planta sao a mesma coisa vista de duas maneiras', async ({ page }) => {
        for (const coluna of ['Pendentes', 'Em curso', 'Concluídas', 'Com problemas']) {
            await expect(page.getByRole('heading', { name: coluna })).toBeVisible();
        }

        await page.getByRole('tab', { name: 'Quartos' }).click();

        // A planta agrupa por piso, e o filtro do piso aparece com ela.
        await expect(page.getByRole('heading', { name: /^Piso/ }).first()).toBeVisible();
        await expect(page.getByRole('combobox', { name: /Piso/ })).toBeVisible();

        // E a legenda diz o que cada cor quer dizer — sem ela é um mosaico.
        for (const estado of ['Limpo', 'Sujo', 'Fora de Serviço']) {
            await expect(page.getByText(estado, { exact: true }).first()).toBeVisible();
        }
    });

    /** A ficha de uma tarefa abre com a lista de verificação. */
    test('a ficha de uma tarefa traz a lista de verificacao', async ({ page }) => {
        // A bancada põe uma limpeza de saída URGENTE no quarto 102.
        await page.getByRole('button', { name: /102/ }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('Lista de verificação')).toBeVisible();

        // E o que se pode fazer a seguir, que é um passo só.
        await expect(modal.getByRole('button', { name: 'Iniciar' })).toBeVisible();
    });
});

/* ─── O calendário ──────────────────────────────────────────────────── */

test.describe('calendario', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/hotel/calendar');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Calendário' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * AS BARRAS TRAZEM O NOME DE QUEM LÁ DORME.
     *
     * Todas diziam «Sem hóspede» — liam a ficha antiga (`hotel_guests`), que
     * está vazia.
     */
    test('as barras trazem o nome do hospede', async ({ page }) => {
        await expect(page.getByRole('button', { name: /Aurora Kiala/ }).first())
            .toBeVisible({ timeout: 15_000 });
        await expect(page.getByRole('button', { name: /Bento Mavungo/ }).first()).toBeVisible();
    });

    /** Andar no tempo muda o período — e o campo «Ir para» anda com ele. */
    test('anda de mes em mes', async ({ page }) => {
        const dia = page.getByRole('textbox', { name: /Ir para/ });

        const antes = await dia.inputValue();

        await page.getByLabel('Período seguinte').click();

        await expect(async () => {
            expect(await dia.inputValue()).not.toBe(antes);
        }).toPass({ timeout: 15_000 });
    });
});

/* ─── O balcão ──────────────────────────────────────────────────────── */

test.describe('balcao', () => {
    /**
     * NÃO SE AVANÇA SEM QUARTO.
     *
     * O passo seguinte só abre depois de haver tipo E quarto escolhidos: uma
     * entrada sem quarto deixava um hóspede hospedado em lado nenhum.
     */
    test('so avanca depois de haver quarto', async ({ page }) => {
        await page.goto('/hotel/walk-in');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Balcão' }))
            .toBeVisible({ timeout: 20_000 });

        const seguinte = page.getByRole('button', { name: 'Seguinte' });

        await expect(seguinte).toBeDisabled();

        await page.getByRole('button', { name: /Duplo/ }).click();

        // Os quartos livres aparecem — e são os livres PARA ESTAS DATAS.
        await expect(page.getByRole('heading', { name: /Quartos livres/ }))
            .toBeVisible({ timeout: 15_000 });

        await page.locator('ul li button').filter({ hasText: /^\d{3}/ }).first().click();

        await expect(seguinte).toBeEnabled();
    });
});

/* ─── O check-out ───────────────────────────────────────────────────── */

test.describe('check-out', () => {
    /**
     * A CONTA APARECE ANTES DE SE FECHAR.
     *
     * É o ecrã onde um erro custa um documento fiscal a mais na cadeia SAFT:
     * o que se cobra tem de estar à vista antes do botão.
     */
    test('a conta aparece antes do botao de fechar', async ({ page }) => {
        await page.goto('/hotel/checkout');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Check-out' }))
            .toBeVisible({ timeout: 20_000 });

        await page.getByRole('button', { name: 'Check-out' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });

        for (const linha of ['Alojamento', 'Consumos', 'Imposto', 'Total', 'Por receber']) {
            await expect(modal.getByText(linha, { exact: true }).first()).toBeVisible();
        }

        // E a caixa de facturar diz o que faz com os adiantamentos.
        await expect(modal.getByText(/adiantamentos é abatido/)).toBeVisible();
    });
});

/* ─── As tarifas ────────────────────────────────────────────────────── */

test.describe('tarifas', () => {
    /** As quatro abas são a mesma pergunta vista por camadas. */
    test('as quatro abas trocam o conteudo', async ({ page }) => {
        await page.goto('/hotel/rates');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Tarifas' }))
            .toBeVisible({ timeout: 20_000 });

        // O calendário é a primeira aba de propósito: é a única maneira de ver
        // as três camadas juntas, e traz a legenda do que subiu e do que desceu.
        await expect(page.getByText('acima do base')).toBeVisible({ timeout: 15_000 });

        await page.getByRole('tab', { name: 'Dias da semana' }).click();
        await expect(page.getByRole('columnheader', { name: 'Domingo' })).toBeVisible();

        await page.getByRole('tab', { name: 'Dias especiais' }).click();
        await expect(page.getByRole('button', { name: 'Nova tarifa de dia' })).toBeVisible();

        await page.getByRole('tab', { name: 'Épocas' }).click();
        await expect(page.getByText(/prioridade|Nenhuma época/).first()).toBeVisible();
    });
});

/* ─── A página pública ──────────────────────────────────────────────── */

test.describe('a pagina publica', () => {
    /**
     * ABRE SEM SESSÃO NENHUMA — é o que ela é.
     *
     * Um hóspede chega-lhe de um cartaz ou de uma ligação do Instagram. E
     * pinta-se com as CORES DA CASA: é a única página do produto onde quem se
     * apresenta é o hotel, e não o ERP.
     */
    test('abre sem sessao e com as cores da casa', async ({ browser }) => {
        const contexto = await browser.newContext();
        const page = await contexto.newPage();

        const erros = [];

        page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
        page.on('pageerror', (e) => erros.push(String(e)));

        await page.goto('/hotel/booking/hotel-da-bancada');

        await expect(page.getByRole('heading', { name: 'Hotel da Bancada' }).first())
            .toBeVisible({ timeout: 20_000 });

        // O cabeçalho é do SERVIDOR: o que o WhatsApp mostra tem de estar no
        // HTML antes de o JavaScript correr.
        await expect(page).toHaveTitle(/Hotel da Bancada/);
        await expect(page.locator('meta[property="og:title"]')).toHaveCount(1);

        // Não há menu nem barra lateral: não é um ecrã de dentro.
        await expect(page.locator('aside')).toHaveCount(0);

        expect(erros).toEqual([]);

        await contexto.close();
    });

    /** E o preço de cada quarto sai das tarifas da casa, com a conta feita. */
    test('mostra os quartos com o preco das datas', async ({ browser }) => {
        const contexto = await browser.newContext();
        const page = await contexto.newPage();

        await page.goto('/hotel/booking/hotel-da-bancada');
        await expect(page.getByRole('heading', { name: 'Hotel da Bancada' }).first())
            .toBeVisible({ timeout: 20_000 });

        await page.getByRole('button', { name: /Ver quartos/ }).click();

        await expect(page.getByRole('heading', { name: 'Escolha o quarto' }))
            .toBeVisible({ timeout: 15_000 });

        // Cada cartão traz o preço por noite E o total das noites pedidas.
        await expect(page.getByText(/Kz \/noite/).first()).toBeVisible();
        await expect(page.getByText(/noite\(s\) = /).first()).toBeVisible();

        await contexto.close();
    });
});
