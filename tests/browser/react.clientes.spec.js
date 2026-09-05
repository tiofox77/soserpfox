import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * OS CLIENTES EM REACT — o primeiro ecrã que escreve.
 *
 * A lista provou a leitura. Aqui prova-se o resto no browser a sério: gravar,
 * ver o erro do servidor no campo certo, e o travão de apagar quem tem
 * documentos.
 *
 * Escreve na empresa de bancada (`php artisan bancada:pwa`), com um NIF por
 * ensaio para dois ensaios não tropeçarem um no outro.
 */

const ECRA = '/invoicing/clients/novo-ecra';

/** Um NIF de empresa que passa no verificador: 10 dígitos. */
function nifDeEnsaio() {
    return '5' + String(Date.now()).slice(-9);
}

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });
});

test('a tabela abre com os clientes da empresa', async ({ page }) => {
    await expect(page.getByRole('columnheader', { name: 'NIF' })).toBeVisible();
    expect(await page.locator('tbody tr').count()).toBeGreaterThan(0);
});

test('cria um cliente e ele aparece na lista', async ({ page }) => {
    const nif = nifDeEnsaio();
    const nome = 'Ensaio React ' + nif.slice(-5);

    await page.getByRole('button', { name: /Novo cliente/ }).click();

    const janela = page.getByRole('dialog');
    await expect(janela).toBeVisible();

    await janela.getByLabel(/^NIF\b/).fill(nif);
    await janela.getByLabel(/^Nome\b/).fill(nome);
    await janela.getByRole('button', { name: 'Guardar' }).click();

    await expect(page.getByRole('status')).toContainText('Cliente criado', { timeout: 20_000 });

    // E está mesmo na lista, vindo do servidor.
    await page.getByPlaceholder('Nome, NIF, email ou telefone').fill(nif);
    // `exact` porque a célula das acções tem o nome no aria-label dos botões
    // («Editar <nome>») e sem isso o selector apanha duas.
    await expect(page.getByRole('cell', { name: nome, exact: true })).toBeVisible({ timeout: 20_000 });
});

/**
 * O ERRO DO SERVIDOR VAI PARA O CAMPO A QUE PERTENCE.
 *
 * Um «não foi possível gravar» solto obriga a adivinhar qual dos dez campos
 * está mal.
 */
test('mostra o erro de validação no campo certo', async ({ page }) => {
    await page.getByRole('button', { name: /Novo cliente/ }).click();

    const janela = page.getByRole('dialog');

    // Nome com duas letras e sem NIF: o servidor recusa os dois.
    await janela.getByLabel(/^Nome\b/).fill('ab');
    await janela.getByRole('button', { name: 'Guardar' }).click();

    await expect(janela.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });

    // A janela NÃO fecha com erro — o que se escreveu continua lá.
    await expect(janela).toBeVisible();
    await expect(janela.getByLabel(/^Nome\b/)).toHaveValue('ab');
});

/**
 * UM CLIENTE COM DOCUMENTOS NÃO SE APAGA.
 *
 * A factura aponta para ele: sem cliente, é um documento fiscal órfão e o
 * SAFT deixa de fechar. O botão fica apagado, e diz porquê.
 */
test('não deixa apagar quem tem documentos', async ({ page }) => {
    // A bancada tem facturas para o «Cliente da Bancada».
    await page.getByPlaceholder('Nome, NIF, email ou telefone').fill('Bancada');
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    const linha = page.locator('tbody tr').first();
    const documentos = Number(await linha.locator('td').nth(5).innerText());

    if (documentos > 0) {
        await expect(linha.locator('[aria-disabled="true"]')).toBeVisible();
        await expect(linha.getByRole('button', { name: /^Apagar / })).toHaveCount(0);
    }
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];

    page.on('console', (m) => {
        if (m.type() === 'error') erros.push(m.text());
    });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

test('o ecrã Livewire dos clientes continua na morada de sempre', async ({ page }) => {
    await page.goto('/invoicing/clients');

    await expect(page.locator('[data-ecra]')).toHaveCount(0);
});
