import { test, expect } from '@playwright/test';
import {
    CREDENCIAIS, entrar, esperarServiceWorker, esperarMotor,
    esperarCatalogo, sincronizar, irPara, avaliar,
} from './apoio.js';

/**
 * Esqueci o PIN, sem rede.
 *
 * O PIN de turno é um bcrypt: não se "acha", só se repõe — e até aqui só se
 * repunha com rede. Um caixa que o esquecesse ficava fora da caixa até a
 * internet voltar. Agora um gestor presente autoriza um PIN novo no próprio
 * aparelho, sem rede, e o servidor decide quando a rede voltar.
 */

const PIN_NOVO = '8642';

async function preparado(page) {
    await entrar(page);
    await irPara(page, '/invoicing/offline');
    await esperarServiceWorker(page);
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 1);

    // A página tem de estar guardada ANTES de faltar a rede. Vem no precache
    // da instalação; abre-se uma vez com rede para não depender do momento
    // em que o service worker instalou.
    await irPara(page, '/invoicing/offline/pin-esquecido');
    await irPara(page, '/invoicing/offline');
    await esperarMotor(page);
}

async function sair(page) {
    await avaliar(page, async () => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const r = await fetch('/invoicing/offline/sair?da_fila=1', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        });
        if (!r.ok) throw new Error('Falhou ao terminar a sessão: ' + r.status);
    });
}

test('sem rede, o gestor repõe o PIN do caixa; com rede, o servidor aceita', async ({ page, context }) => {
    await preparado(page);

    const gestor = await avaliar(page, (email) => window.SosPwa.db.employees.get(email), CREDENCIAIS.email);
    expect(gestor?.pode_repor_pin, 'o gestor chega ao aparelho com o direito de autorizar').toBe(true);
    const caixa = await avaliar(page, (email) => window.SosPwa.db.employees.get(email), CREDENCIAIS.caixa.email);
    expect(caixa?.pin_hash, 'o caixa tem de estar sincronizado no aparelho').toBeTruthy();
    expect(caixa?.pode_repor_pin, 'um caixa não autoriza reposições').toBeFalsy();

    await sair(page);
    await context.setOffline(true);

    await page.goto('/invoicing/offline/pin-esquecido?voltar=/invoicing/offline/login', { waitUntil: 'domcontentloaded' });
    await esperarMotor(page);

    const campos = page.locator('form input');
    await expect(campos).toHaveCount(5, { timeout: 15_000 });
    await campos.nth(0).fill(CREDENCIAIS.caixa.email);
    await campos.nth(1).fill(CREDENCIAIS.email);
    await campos.nth(2).fill(CREDENCIAIS.pin);
    await campos.nth(3).fill(PIN_NOVO);
    await campos.nth(4).fill(PIN_NOVO);
    await page.getByRole('button', { name: 'Repor o PIN' }).click();

    await expect(page.getByText('PIN reposto para')).toBeVisible({ timeout: 30_000 });

    // O caixa entra com o PIN novo — e já não entra com o antigo.
    await page.getByRole('button', { name: 'Entrar com o PIN novo' }).click();
    await page.waitForURL(/\/invoicing\/offline\/login/, { timeout: 15_000 });
    await esperarMotor(page);

    const alternar = page.getByRole('button', { name: 'Entrar com PIN offline' });
    const emailOffline = page.locator('input[type="email"]').last();
    await expect.poll(async () => (await alternar.isVisible()) || (await emailOffline.isVisible()), { timeout: 15_000 }).toBe(true);
    if (await alternar.isVisible()) await alternar.click();

    await emailOffline.fill(CREDENCIAIS.caixa.email);
    await page.locator('input[inputmode="numeric"]').fill(CREDENCIAIS.caixa.pin);
    await page.getByRole('button', { name: 'Entrar sem rede' }).click();
    await expect(page.getByText('Email ou PIN que não conferem.')).toBeVisible({ timeout: 15_000 });

    await page.locator('input[inputmode="numeric"]').fill(PIN_NOVO);
    await page.getByRole('button', { name: 'Entrar sem rede' }).click();
    await page.waitForURL(/\/invoicing\/offline\/pos/, { timeout: 20_000 });

    // A rede volta. O gestor entra (é a conta com sessão no aparelho) e a
    // fila sobe: o servidor aceita, e na sincronização seguinte a lista dos
    // funcionários já traz o verificador que ele guardou.
    await context.setOffline(false);
    await entrar(page);
    await irPara(page, '/invoicing/offline');
    await esperarMotor(page);
    await sincronizar(page);
    await sincronizar(page);

    const fila = await avaliar(page, () =>
        window.SosPwa.db.sync_queue.filter((j) => j.op === 'repor_pin').toArray()
            .then((js) => js.map((j) => ({ status: j.status, erro: j.last_error || null })))
    );
    expect(fila.filter((j) => j.status === 'failed'), 'o servidor não pode ter recusado').toEqual([]);
    expect(fila.filter((j) => j.status === 'pending'), 'a reposição tem de ter subido').toEqual([]);

    const confere = await avaliar(page, async ([email, pin]) => {
        const emp = await window.SosPwa.db.employees.get(email);
        return emp?.pin_hash ? await window.SosPwa._bcryptCompare(pin, emp.pin_hash) : null;
    }, [CREDENCIAIS.caixa.email, PIN_NOVO]);
    expect(confere, 'o verificador vindo do servidor tem de conferir com o PIN novo').toBe(true);

    // Deixa a bancada como a encontrou: o caixa volta ao PIN de origem, pelo
    // mesmo caminho (com rede, a fila sobe logo). Os ensaios seguintes contam
    // com esse PIN para o caixa se identificar.
    const reposto = await avaliar(page, (c) => window.SosPwa.reporPinOffline(c), {
        email: CREDENCIAIS.caixa.email, gestorEmail: CREDENCIAIS.email,
        gestorPin: CREDENCIAIS.pin, pinNovo: CREDENCIAIS.caixa.pin,
    });
    expect(reposto.ok, 'repor o PIN de origem').toBe(true);
    await sincronizar(page);
    await sincronizar(page);
});

test('o PIN errado do gestor não repõe nada', async ({ page, context }) => {
    await preparado(page);
    const antes = await avaliar(page, (email) => window.SosPwa.db.employees.get(email).then((e) => e?.pin_hash), CREDENCIAIS.caixa.email);
    expect(antes).toBeTruthy();
    await sair(page);
    await context.setOffline(true);

    await page.goto('/invoicing/offline/pin-esquecido', { waitUntil: 'domcontentloaded' });
    await esperarMotor(page);

    const campos = page.locator('form input');
    await expect(campos).toHaveCount(5, { timeout: 15_000 });
    await campos.nth(0).fill(CREDENCIAIS.caixa.email);
    await campos.nth(1).fill(CREDENCIAIS.email);
    await campos.nth(2).fill('0000');
    await campos.nth(3).fill(PIN_NOVO);
    await campos.nth(4).fill(PIN_NOVO);
    await page.getByRole('button', { name: 'Repor o PIN' }).click();

    await expect(page.getByText('Email ou PIN do gestor que não conferem.')).toBeVisible({ timeout: 15_000 });

    const depois = await avaliar(page, (email) => window.SosPwa.db.employees.get(email).then((e) => e?.pin_hash), CREDENCIAIS.caixa.email);
    expect(depois, 'o verificador do caixa ficou como estava').toBe(antes);
});

test('um caixa não autoriza a reposição de um colega', async ({ page, context }) => {
    await preparado(page);
    await sair(page);
    await context.setOffline(true);

    await page.goto('/invoicing/offline/pin-esquecido', { waitUntil: 'domcontentloaded' });
    await esperarMotor(page);

    const campos = page.locator('form input');
    await expect(campos).toHaveCount(5, { timeout: 15_000 });
    await campos.nth(0).fill(CREDENCIAIS.email);
    await campos.nth(1).fill(CREDENCIAIS.caixa.email);
    await campos.nth(2).fill(CREDENCIAIS.caixa.pin);
    await campos.nth(3).fill(PIN_NOVO);
    await campos.nth(4).fill(PIN_NOVO);
    await page.getByRole('button', { name: 'Repor o PIN' }).click();

    await expect(page.getByText('Esta conta não pode autorizar: só quem gere utilizadores.')).toBeVisible({ timeout: 15_000 });
});
