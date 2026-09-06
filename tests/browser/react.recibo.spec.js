import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * REGISTAR UM RECIBO EM REACT.
 *
 * O que este ecrã tem de próprio: diz sempre QUANTO FALTA e propõe esse valor.
 * É a diferença entre receber e adivinhar — quem está ao balcão com o cliente
 * à frente não tem de abrir a factura noutro separador para saber quanto pedir.
 */

const ECRA = '/invoicing/receipts/create';

test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto(ECRA);
    await expect(page.getByLabel(/^Tipo\b/)).toBeVisible({ timeout: 20_000 });
});

test('abre com os tres blocos e o dia de hoje', async ({ page }) => {
    await expect(page.getByText('De quem se recebe')).toBeVisible();
    await expect(page.getByText('Factura', { exact: true })).toBeVisible();
    await expect(page.getByText('Pagamento', { exact: true })).toBeVisible();

    const hoje = new Date().toISOString().slice(0, 10);
    await expect(page.getByLabel(/^Data\b/)).toHaveValue(hoje);
});

/** Trocar entre cliente e fornecedor troca a lista e limpa o que estava. */
test('troca entre cliente e fornecedor', async ({ page }) => {
    await expect(page.getByLabel(/^Cliente\b/)).toBeVisible();

    await page.getByLabel(/^Tipo\b/).selectOption('purchase');

    await expect(page.getByLabel(/^Fornecedor\b/)).toBeVisible();
    await expect(page.getByLabel(/^Cliente\b/)).toHaveCount(0);
});

/**
 * ESCOLHER A FACTURA DIZ QUANTO FALTA E PROPÕE ESSE VALOR.
 */
test('escolher a factura propoe o que falta', async ({ page }) => {
    const selector = page.getByLabel('Factura a receber');
    const semFacturas = page.getByText('Não há facturas por receber');

    // As facturas pedem-se ao servidor: espera-se pela resposta antes de
    // decidir se há ou não há. Sem isto o ensaio lia o ecrã a meio do
    // caminho, concluía que a bancada não tinha nada, e falhava quando a
    // lista chegava um instante depois.
    await expect(selector.or(semFacturas).first()).toBeVisible({ timeout: 20_000 });

    // A bancada pode não ter facturas por receber; nesse caso o ecrã diz-o.
    if ((await selector.count()) === 0) {
        await expect(semFacturas).toBeVisible();
        return;
    }

    const opcoes = await selector.locator('option').count();

    test.skip(opcoes < 2, 'a bancada não tem facturas por receber');

    await selector.selectOption({ index: 1 });

    await expect(page.getByText('Falta', { exact: true })).toBeVisible();

    // E o valor ficou preenchido com o que falta, não a zero.
    const valor = await page.getByLabel(/^Valor recebido\b/).inputValue();
    expect(Number(valor)).toBeGreaterThan(0);
});

test('sem valor o servidor recusa e o ecra diz onde', async ({ page }) => {
    await page.getByLabel(/^Cliente\b/).selectOption({ index: 1 });
    await page.getByLabel(/^Valor recebido\b/).fill('');

    await page.getByRole('button', { name: /Registar recibo/ }).click();

    await expect(page.getByRole('alert').first()).toBeVisible({ timeout: 20_000 });
});

test('nenhum erro na consola', async ({ page }) => {
    const erros = [];
    page.on('console', (m) => {
        if (m.type() === 'error') erros.push(m.text());
    });
    page.on('pageerror', (e) => erros.push(String(e)));

    await page.reload();
    await expect(page.getByLabel(/^Tipo\b/)).toBeVisible({ timeout: 20_000 });

    expect(erros).toEqual([]);
});

/**
 * A FACTURA QUE VEM DA LISTA ABRE JÁ ESCOLHIDA.
 *
 * Da lista de facturas carrega-se «Receber» e chega-se aqui com
 * `?invoice=`. O ecrã em Livewire lia-o no mount; ao passar para React
 * ficou por ler — quem recebe ao balcão tinha de procurar a factura outra
 * vez. Quem resolve é o servidor, que é quem sabe de que cliente ela é.
 */
test('vir da lista pela accao Receber abre com a factura e o valor', async ({ page }) => {
    await page.goto('/invoicing/sales/invoices');
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

    const receber = page.locator('a[href*="/invoicing/receipts/create?invoice="]').first();

    test.skip((await receber.count()) === 0, 'a bancada não tem facturas por receber');

    await receber.click();

    await expect(page.getByLabel(/^Cliente\b/)).toBeVisible({ timeout: 20_000 });
    await expect(page.getByLabel(/^Cliente\b/)).not.toHaveValue('');

    const factura = page.getByLabel('Factura a receber');
    await expect(factura).not.toHaveValue('');

    const valor = await page.getByLabel(/^Valor recebido\b/).inputValue();
    expect(Number(valor)).toBeGreaterThan(0);
});
