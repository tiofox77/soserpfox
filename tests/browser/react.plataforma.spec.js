import { expect, test } from '@playwright/test';

/**
 * OS ECRÃS DO DONO DA PLATAFORMA, no browser.
 *
 * As regras vivem em `tests/Feature/Plataforma` e nos ensaios das empresas. O
 * que aqui se prova é o que só se vê no browser:
 *
 *  · que as cinco moradas do superadmin abrem, dentro do layout do superadmin,
 *    sem um erro na consola;
 *  · que um utilizador de empresa NÃO entra — nem na página, nem na API;
 *  · que a lista de empresas filtra pelos cartões de estado;
 *  · que o formulário do plano tem os quatro ciclos e o tecto de documentos;
 *  · que o módulo tem campo para as dependências, e a galeria de ícones.
 *
 * Entra com o dono da bancada (`bancada:pwa` → dono@pwa.local), que existe só em
 * ambiente local.
 */

const DONO = { email: 'dono@pwa.local', password: 'bancada-pwa-2026' };

const MORADAS = [
    ['/superadmin/dashboard', 'Painel da Plataforma'],
    ['/superadmin/analytics', 'Analítica e visitantes'],
    ['/superadmin/tenants', 'Empresas'],
    ['/superadmin/plans', 'Planos'],
    ['/superadmin/modules', 'Módulos'],
    ['/superadmin/billing', 'Facturação da plataforma'],
];

async function entrarComoDono(page) {
    await page.goto('/login');
    await page.fill('input[name="email"]', DONO.email);
    await page.fill('input[name="password"]', DONO.password);
    await page.click('button[type="submit"]');
    await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 30_000 });
}

async function abrir(page, morada, titulo) {
    await page.goto(morada);
    await expect(page.locator('.ecra-react').getByRole('heading', { name: titulo, exact: true }).first())
        .toBeVisible({ timeout: 20_000 });
}

test.describe('as moradas da plataforma', () => {
    test.beforeEach(async ({ page }) => {
        await entrarComoDono(page);
    });

    for (const [morada, titulo] of MORADAS) {
        test(`abre ${morada}`, async ({ page }) => {
            const erros = [];

            page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
            page.on('pageerror', (e) => erros.push(String(e)));

            const resposta = await page.goto(morada);

            expect(resposta?.status(), `${morada} respondeu ${resposta?.status()}`).toBeLessThan(400);

            await expect(page.locator('.ecra-react').getByRole('heading', { name: titulo, exact: true }).first())
                .toBeVisible({ timeout: 20_000 });

            // O LAYOUT DO SUPERADMIN, não o de uma empresa: o ecrã vive dentro
            // da casa do dono.
            await expect(page.locator('[data-ecra]')).toHaveCount(1);

            expect(erros, `consola de ${morada}`).toEqual([]);
        });
    }
});

test.describe('a porta', () => {
    /** UM UTILIZADOR DE EMPRESA não entra, nem pela página nem pela API. */
    test('um utilizador de empresa recebe 403', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', 'bancada@pwa.local');
        await page.fill('input[name="password"]', 'bancada-pwa-2026');
        await page.click('button[type="submit"]');
        await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 30_000 });

        const pagina = await page.goto('/superadmin/tenants');
        expect(pagina?.status()).toBe(403);

        const api = await page.request.get('/api/v1/plataforma/react/empresas', {
            headers: { Accept: 'application/json' },
        });
        expect(api.status()).toBe(403);
    });
});

test.describe('as empresas', () => {
    test.beforeEach(async ({ page }) => {
        await entrarComoDono(page);
        await abrir(page, '/superadmin/tenants', 'Empresas');
    });

    test('a bancada aparece na lista, com os sinais de vida', async ({ page }) => {
        await page.getByPlaceholder('Nome, email, NIF…').fill('Bancada PWA');

        const cartao = page.locator('article', { hasText: 'Bancada PWA' }).first();

        await expect(cartao).toBeVisible({ timeout: 20_000 });
        // As acções estão sempre à vista — escondidas até ao passar do rato,
        // num tablet não havia como lhes chegar.
        await expect(cartao.getByRole('button', { name: 'Utilizadores' })).toBeVisible();
        await expect(cartao.getByRole('button', { name: 'Plano', exact: true })).toBeVisible();
        await expect(cartao.getByText(/factura\(s\)\/30d/)).toBeVisible();
    });

    test('um cartão de estado filtra e fica aceso', async ({ page }) => {
        const cartao = page.getByRole('button', { name: /Nunca usaram/ });

        await cartao.click();
        await expect(cartao).toHaveAttribute('aria-pressed', 'true');

        await cartao.click();
        await expect(cartao).toHaveAttribute('aria-pressed', 'false');
    });

    test('a janela dos utilizadores mostra quem lá trabalha', async ({ page }) => {
        await page.getByPlaceholder('Nome, email, NIF…').fill('Bancada PWA');

        const cartao = page.locator('article', { hasText: 'Bancada PWA' }).first();

        await cartao.getByRole('button', { name: 'Utilizadores' }).click();

        const janela = page.getByRole('dialog');

        await expect(janela.getByText('bancada@pwa.local')).toBeVisible({ timeout: 20_000 });
        await expect(janela.getByRole('button', { name: 'Juntar pessoa' })).toBeVisible();
    });

    test('o formulário de nova empresa tem o país', async ({ page }) => {
        await page.getByRole('button', { name: 'Nova empresa' }).click();

        const janela = page.getByRole('dialog');

        await expect(janela.getByLabel(/^País/)).toBeVisible();
        await expect(janela.getByLabel(/^NIF/)).toBeVisible();

        // O identificador sai do nome enquanto ninguém lhe tocar.
        await janela.getByLabel(/^Nome/).first().fill('Casa do Ensaio');
        await expect(janela.getByLabel(/^Identificador/)).toHaveValue('casa-do-ensaio');
    });

    test('o plano da empresa mostra o resumo calculado pelo servidor', async ({ page }) => {
        await page.getByPlaceholder('Nome, email, NIF…').fill('Bancada PWA');

        const cartao = page.locator('article', { hasText: 'Bancada PWA' }).first();

        await cartao.getByRole('button', { name: 'Plano', exact: true }).click();

        const janela = page.getByRole('dialog');

        await janela.getByRole('radio').first().click();
        await expect(janela.getByText('Total a pagar')).toBeVisible({ timeout: 20_000 });
        await expect(janela.getByText(/Período: hoje →/)).toBeVisible();
    });
});

test.describe('os planos', () => {
    test.beforeEach(async ({ page }) => {
        await entrarComoDono(page);
        await abrir(page, '/superadmin/plans', 'Planos');
    });

    /** OS QUATRO CICLOS e o TECTO DE DOCUMENTOS — que o formulário nunca teve. */
    test('o formulário tem os quatro ciclos, o tecto e a montra', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo plano' }).first().click();

        const janela = page.getByRole('dialog');

        for (const rotulo of [/^Mensal \(Kz\)/, /^Trimestral \(Kz\)/, /^Semestral \(Kz\)/, /^Anual \(Kz\)/, /^Documentos/]) {
            await expect(janela.getByLabel(rotulo)).toBeVisible();
        }

        await expect(janela.getByText('Na montra', { exact: true })).toBeVisible();
        await expect(janela.getByText('Promocional', { exact: true })).toBeVisible();

        // A sugestão faz a conta do comando de consola: 5% e 10%.
        await janela.getByLabel(/^Mensal \(Kz\)/).fill('10000');
        await janela.getByRole('button', { name: 'Sugerir a partir do mensal' }).click();
        await expect(janela.getByLabel(/^Trimestral \(Kz\)/)).toHaveValue('28500');
        await expect(janela.getByLabel(/^Semestral \(Kz\)/)).toHaveValue('54000');
    });
});

test.describe('os módulos', () => {
    test.beforeEach(async ({ page }) => {
        await entrarComoDono(page);
        await abrir(page, '/superadmin/modules', 'Módulos');
    });

    test('o formulário tem as dependências e a galeria de ícones', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo módulo' }).first().click();

        const janela = page.getByRole('dialog');

        await expect(janela.getByText('Precisa de', { exact: true })).toBeVisible();
        await expect(janela.getByRole('button', { name: /Ícone do módulo/ })).toBeVisible();
    });
});

test.describe('a analítica', () => {
    test.beforeEach(async ({ page }) => {
        await entrarComoDono(page);
        await abrir(page, '/superadmin/analytics', 'Analítica e visitantes');
    });

    test('os sete separadores abrem sem rebentar', async ({ page }) => {
        const erros = [];

        page.on('pageerror', (e) => erros.push(String(e)));

        for (const nome of [/^Visitantes \(/, 'De onde vêm', 'Região', 'Pesquisas', 'Funil', 'Ao vivo', 'Visão geral']) {
            await page.getByRole('tab', { name: nome }).click();
            await expect(page.getByRole('tab', { name: nome })).toHaveAttribute('aria-selected', 'true');
        }

        expect(erros).toEqual([]);
    });

    test('mudar o período volta a pedir os números', async ({ page }) => {
        const pedido = page.waitForResponse((r) => r.url().includes('/api/v1/plataforma/react/analitica') && r.url().includes('periodo=30d'));

        await page.getByRole('button', { name: '30 dias' }).click();

        expect((await pedido).status()).toBe(200);
        await expect(page.getByRole('button', { name: '30 dias' })).toHaveAttribute('aria-pressed', 'true');
    });
});

test.describe('a facturação', () => {
    test.beforeEach(async ({ page }) => {
        await entrarComoDono(page);
        await abrir(page, '/superadmin/billing', 'Facturação da plataforma');
    });

    test('os três separadores abrem, e o das subscrições pede a sua lista', async ({ page }) => {
        const pedido = page.waitForResponse((r) => r.url().includes('/api/v1/plataforma/react/facturacao/subscricoes'));

        await page.getByRole('tab', { name: /^Subscrições/ }).click();
        expect((await pedido).status()).toBe(200);

        await page.getByRole('tab', { name: /^Facturas/ }).click();
        await expect(page.getByRole('button', { name: 'Nova factura' })).toBeVisible();
    });

    test('a factura nova vem numerada e o total soma-se sozinho', async ({ page }) => {
        await page.getByRole('tab', { name: /^Facturas/ }).click();
        await page.getByRole('button', { name: 'Nova factura' }).click();

        const janela = page.getByRole('dialog');

        await expect(janela.getByLabel(/^Número/)).not.toHaveValue('', { timeout: 20_000 });
        await janela.getByLabel(/^Subtotal/).fill('10000');
        await janela.getByLabel(/^Imposto/).fill('1400');
        await expect(janela.getByText(/11[^0-9]?400,00/)).toBeVisible();
    });

    test('a configuração do SAF-T abre e fecha', async ({ page }) => {
        const botao = page.getByRole('button', { name: /Software certificado/ });

        await botao.click();
        await expect(botao).toHaveAttribute('aria-expanded', 'true');
        await expect(page.getByLabel(/^Versão do formato/)).toBeVisible();
    });
});
