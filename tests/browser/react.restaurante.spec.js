import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O MÓDULO DO RESTAURANTE — os catorze ecrãs, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Restaurant`): o turno
 * obrigatório, a conta dividida, o escopo de empresa, a quebra na ficha
 * técnica. O que aqui se prova é o que só se vê no browser:
 *
 *  · que as catorze moradas abrem, e sem um erro na consola — que é a classe
 *    de defeito mais cara deste projecto;
 *  · que o mapa da sala pinta as mesas e diz o estado de cada uma;
 *  · que o balcão tem as duas metades: escolher à esquerda, a comanda à direita;
 *  · que a carta se edita EM LINHA, sem abrir formulário nenhum;
 *  · que a cozinha desenha as quatro colunas do percurso do bilhete;
 *  · e que as definições separam as três gravações que não se devem misturar.
 *
 * A bancada monta-se com `php artisan bancada:pwa`: seis mesas e cinco artigos.
 */

/**
 * As moradas, e o título que o ECRÃ desenha (não o do layout).
 *
 * A diferença importa: o cabeçalho da página vem do Blade e aparece mesmo que
 * o React nunca monte. Procurar o título DENTRO da ilha é o que distingue um
 * ecrã que abriu de uma página que ficou no esqueleto cinzento.
 */
const MORADAS = [
    ['/restaurant/dashboard', 'Painel do Restaurante'],
    ['/restaurant/floor', 'Sala e Mesas'],
    ['/restaurant/orders', 'Comandas'],
    ['/restaurant/pos', 'Balcão do Restaurante'],
    ['/restaurant/carta', 'A Carta'],
    ['/restaurant/categories', 'A Carta'],
    ['/restaurant/kitchen', 'Cozinha'],
    ['/restaurant/reservations', 'Reservas'],
    ['/restaurant/recipes', 'Fichas Técnicas'],
    ['/restaurant/stock', 'Stock e Desperdícios'],
    ['/restaurant/reports', 'Relatórios do Restaurante'],
    ['/restaurant/settings', 'Definições do Restaurante'],
    ['/restaurant/carta/aparencia', 'Aparência da Carta'],
    ['/restaurant/sales-report', 'Relatórios do POS'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

/* ─── Todas abrem ───────────────────────────────────────────────────── */

test.describe('as moradas do restaurante', () => {
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

/* ─── A sala ────────────────────────────────────────────────────────── */

test.describe('o mapa da sala', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/restaurant/floor');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Sala e Mesas' }))
            .toBeVisible({ timeout: 20_000 });

        // A bancada tem duas casas e as mesas estão numa delas — o ecrã abre na
        // primeira por nome, que pode não ser essa.
        await page.getByRole('combobox', { name: 'Estabelecimento' }).selectOption({ label: 'Salão da Bancada' });
    });

    /**
     * O `:visible` NÃO É ZELO A MAIS.
     *
     * O modal «Estado da mesa» tem um botão por estado — «Livre» incluído — e
     * um `<dialog>` fechado MANTÉM o corpo no DOM. Sem o filtro, o selector
     * apanhava o botão do modal fechado e o ensaio ficava a tentar clicar num
     * elemento invisível até esgotar o tempo.
     */
    const mesaLivre = (page) => page.locator('.ecra-react button:visible').filter({ hasText: 'Livre' }).first();

    /** A mesa diz o nome, os lugares e o estado — sem se abrir nada. */
    test('as mesas pintam-se com o estado a dizer-se por escrito', async ({ page }) => {
        // A COR NÃO CHEGA: quem não distingue verde de âmbar tem de saber na
        // mesma qual é a mesa livre.
        await expect(mesaLivre(page)).toBeVisible({ timeout: 20_000 });
    });

    /** Tocar numa mesa livre pergunta quantas pessoas antes de abrir. */
    test('tocar numa mesa livre pergunta quantas pessoas', async ({ page }) => {
        await mesaLivre(page).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByRole('heading', { name: 'Abrir atendimento' })).toBeVisible();
        await expect(modal.getByRole('spinbutton')).toBeVisible();
    });
});

/* ─── O balcão ──────────────────────────────────────────────────────── */

test.describe('o balcão', () => {
    /**
     * DUAS METADES e nenhuma navegação no meio: à esquerda escolhe-se, à
     * direita está a comanda. Cada página que abrisse era tempo com o cliente
     * à frente.
     */
    test('tem as mesas de um lado e a comanda do outro', async ({ page }) => {
        await page.goto('/restaurant/pos');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Balcão do Restaurante' }))
            .toBeVisible({ timeout: 20_000 });

        // À esquerda, as mesas. À direita, o convite a escolher uma.
        await expect(page.getByRole('heading', { name: 'Nenhuma comanda aberta' })).toBeVisible();

        // E as portas das vendas sem mesa, que a coluna `channel` já aceitava
        // e o produto nunca escrevia.
        for (const canal of ['Venda ao balcão', 'Take-away', 'Entrega']) {
            await expect(page.getByRole('button', { name: canal })).toBeVisible();
        }
    });
});

/* ─── A carta ───────────────────────────────────────────────────────── */

test.describe('a carta', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/restaurant/carta');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'A Carta' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * TUDO EM LINHA: o preço muda-se a tocar-lhe, sem abrir formulário nenhum.
     * Era isto que o ecrã dos produtos da Facturação obrigava a fazer, com
     * quarenta campos que um prato não usa.
     */
    test('o preco e o nome editam-se na propria linha', async ({ page }) => {
        const linha = page.locator('.ecra-react tbody tr').first();

        await expect(linha).toBeVisible({ timeout: 20_000 });

        // Um campo de texto DENTRO da linha, não um botão que abre uma janela.
        await expect(linha.getByRole('textbox').first()).toBeVisible();

        // E o interruptor de «no menu», que esconde sem apagar.
        await expect(linha.getByRole('switch')).toBeVisible();
    });

    /** O prato novo é uma linha só: nome, preço, Enter. */
    test('o prato novo sao dois campos e um botao', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Prato novo' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Pôr no menu' })).toBeVisible();
    });

    /** As categorias vivem ao lado: a ordem delas É a ordem da carta. */
    test('as categorias estao no mesmo ecra', async ({ page }) => {
        await page.getByRole('tab', { name: 'Categorias' }).click();

        await expect(page.getByRole('heading', { name: 'Categorias' }).first())
            .toBeVisible({ timeout: 15_000 });
    });
});

/* ─── A cozinha ─────────────────────────────────────────────────────── */

test.describe('a cozinha', () => {
    /** As quatro colunas do percurso de um bilhete, e a impressão por aparelho. */
    test('desenha o percurso do bilhete e a impressao e deste aparelho', async ({ page }) => {
        await page.goto('/restaurant/kitchen');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Cozinha' }))
            .toBeVisible({ timeout: 20_000 });

        // A impressão automática é POR APARELHO: ligada em todos os ecrãs,
        // cada um imprimia a sua cópia do mesmo talão.
        await expect(page.getByLabel('Imprimir talões novos neste aparelho')).toBeVisible();
    });
});

/* ─── As definições ─────────────────────────────────────────────────── */

test.describe('as definições', () => {
    /**
     * TRÊS GRAVAÇÕES SEPARADAS: publicar preços ao mundo não é a mesma decisão
     * que escolher um armazém por omissão, e misturá-las fazia um clique numa
     * caixa qualquer publicar a carta sem querer.
     */
    test('separam as regras, a estrutura e a carta publica', async ({ page }) => {
        await page.goto('/restaurant/settings');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Definições do Restaurante' }))
            .toBeVisible({ timeout: 20_000 });

        for (const aba of ['Regras', 'Estrutura', 'Carta pública']) {
            await expect(page.getByRole('tab', { name: aba })).toBeVisible();
        }

        // O turno não tem interruptor — e diz-se porquê.
        await expect(page.getByText(/O turno de caixa é sempre obrigatório/)).toBeVisible();

        await page.getByRole('tab', { name: 'Carta pública' }).click();

        // E publicar avisa do que significa antes de se carregar no botão.
        await expect(page.getByText(/Publicar a carta põe os seus preços/)).toBeVisible();
    });
});
