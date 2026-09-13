import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { unlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import {
    CREDENCIAIS, entrar, esperarServiceWorker, esperarMotor,
    esperarCatalogo, sincronizar, irPara, avaliar,
} from './apoio.js';

/**
 * O PIN CRIADO COMO UMA PESSOA O CRIA — e depois usado sem rede.
 *
 * Os outros ensaios do PIN põem-no directamente na base (`bancada:pwa`), por
 * isso nunca passavam pelos dois ecrãs onde ele nasce: o «PIN de turno» de cada
 * um e o botão da chave na Gestão de Utilizadores. Aqui o PIN nasce nesses
 * ecrãs, viaja na sincronização e abre (ou não abre) o POS sem rede:
 *
 *  1. o próprio define o PIN → o novo entra sem rede e o antigo deixa de entrar;
 *  2. o gestor define o PIN de um colega (6 dígitos, com zero à frente);
 *  3. o que se recusa — PIN óbvio, os dois PIN diferentes, palavra-passe errada
 *     — recusa-se com a razão no campo, e o PIN que estava continua a valer;
 *  4. quem é desactivado deixa de entrar sem rede na sincronização seguinte;
 *  5. quem entra com rede SEM PIN é convidado a defini-lo e volta ao PWA — a
 *     página «PIN de turno» não tinha ligação em lado nenhum.
 *
 * No fim devolve à bancada os PIN de sempre.
 */

const PIN_MEU = '5827';
const PIN_DO_CAIXA = '048213';

function artisan(args) {
    execSync(`php artisan ${args}`, { cwd: process.cwd(), stdio: 'pipe' });
}

/**
 * Muda um utilizador DIRECTAMENTE no servidor (o que um gestor faria noutro
 * posto). Barras para a frente e aspas simples: um «\v» num caminho do Windows
 * dentro de aspas duplas do PHP é um tab vertical.
 */
function noServidor(email, campos) {
    const raiz = process.cwd().split('\\').join('/');
    const script = join(tmpdir(), `bancada-${Date.now()}.php`);
    writeFileSync(script, [
        '<?php',
        `require '${raiz}/vendor/autoload.php';`,
        `$app = require '${raiz}/bootstrap/app.php';`,
        "$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();",
        `\\App\\Models\\User::where('email', '${email}')->update(json_decode('${JSON.stringify(campos)}', true));`,
    ].join('\n'));
    try { execSync(`php "${script}"`, { cwd: process.cwd(), stdio: 'pipe' }); } finally { unlinkSync(script); }
}

/** Tira o PIN a alguém no servidor — o caso de quem nunca o definiu. */
const semPin = (email) => noServidor(email, { pos_pin_hash: null, pos_pin_set_at: null });

/** A empresa da bancada, lida do aparelho: há mais do que uma «Bancada» na base. */
let empresa = null;

test.afterAll(() => {
    if (!empresa) return;
    artisan(`pwa:definir-pin --tenant=${empresa} --email=${CREDENCIAIS.email} --pin=${CREDENCIAIS.pin} --forcar --aplicar`);
    artisan(`pwa:definir-pin --tenant=${empresa} --email=${CREDENCIAIS.caixa.email} --pin=${CREDENCIAIS.caixa.pin} --aplicar`);
});

/** O aparelho preparado com rede: sessão, motor, catálogo e funcionários. */
async function aparelhoSincronizado(page) {
    await irPara(page, '/invoicing/offline');
    try {
        await page.waitForFunction(() => navigator.serviceWorker?.controller != null, null, { timeout: 20_000 });
    } catch {
        // Na primeira visita o service worker nem sempre assume logo o comando.
        await page.reload();
        await esperarServiceWorker(page);
    }
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 1);
    empresa ??= await avaliar(page, () => window.SosPwa.db.meta.get('tenant_id').then((m) => m?.value));
}

/** O verificador do aparelho confere com este PIN? (o bcryptjs do próprio PWA) */
async function confere(page, email, pin) {
    return avaliar(page, async ([mail, segredo]) => {
        const emp = await window.SosPwa.db.employees.get(mail);
        if (!emp?.pin_hash) return null;
        const bc = window.bcrypt || window.dcodeIO?.bcrypt;

        return new Promise((ok) => bc.compare(segredo, emp.pin_hash, (e, r) => ok(!e && r)));
    }, [email, pin]);
}

async function sairDaSessao(page) {
    await avaliar(page, async () => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const r = await fetch('/invoicing/offline/sair?da_fila=1', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        });
        if (!r.ok) throw new Error('Falhou ao terminar a sessão: ' + r.status);
    });
}

/** Sem rede, na entrada: tenta o PIN e diz se chegou ao POS. */
async function entrarSemRede(page, context, email, pin) {
    await irPara(page, '/invoicing/offline/login');
    await context.setOffline(true);
    await page.reload();
    await esperarMotor(page);

    const alternar = page.getByRole('button', { name: 'Entrar com PIN offline' });
    const emailOffline = page.locator('#entrada-email-local');
    await expect.poll(async () => (await alternar.isVisible()) || (await emailOffline.isVisible()), { timeout: 15_000 }).toBe(true);
    if (await alternar.isVisible()) await alternar.click();

    await emailOffline.fill(email);
    await page.locator('#entrada-pin').fill(pin);
    await page.getByRole('button', { name: 'Entrar sem rede' }).click();

    const resultado = await Promise.race([
        page.waitForURL(/\/invoicing\/offline\/pos/, { timeout: 20_000 }).then(() => 'pos'),
        page.getByRole('alert').waitFor({ timeout: 20_000 }).then(() => 'recusado'),
    ]);

    await context.setOffline(false);

    return resultado;
}

test('o PIN que o próprio define no ecrã entra sem rede, e o antigo deixa de entrar', async ({ page, context }) => {
    await entrar(page);

    await irPara(page, '/invoicing/offline/pin');
    const ecra = page.locator('[data-definir-pin]');
    await expect(ecra).toBeVisible({ timeout: 20_000 });

    await ecra.getByLabel('PIN novo').fill(PIN_MEU);
    await ecra.getByLabel('Repita o PIN').fill(PIN_MEU);
    await ecra.getByLabel('A sua palavra-passe').fill(CREDENCIAIS.password);
    await ecra.getByRole('button', { name: /PIN/ }).click();
    await expect(page.getByText('PIN definido.').first()).toBeVisible({ timeout: 15_000 });

    await aparelhoSincronizado(page);
    expect(await confere(page, CREDENCIAIS.email, PIN_MEU), 'o PIN novo chegou ao aparelho').toBe(true);
    expect(await confere(page, CREDENCIAIS.email, CREDENCIAIS.pin), 'o antigo já não confere').toBe(false);

    await sairDaSessao(page);
    expect(await entrarSemRede(page, context, CREDENCIAIS.email, CREDENCIAIS.pin)).toBe('recusado');
    expect(await entrarSemRede(page, context, CREDENCIAIS.email, PIN_MEU)).toBe('pos');
});

test('o gestor define o PIN de um colega — seis dígitos com zero à frente', async ({ page, context }) => {
    await entrar(page);

    await irPara(page, '/users');
    await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Gestão de Utilizadores' })).toBeVisible({ timeout: 20_000 });

    const linha = page.locator('.ecra-react').locator('li, tr, article, div').filter({ hasText: CREDENCIAIS.caixa.email })
        .filter({ has: page.getByRole('button', { name: 'Definir PIN de turno' }) }).last();
    await linha.getByRole('button', { name: 'Definir PIN de turno' }).click();

    const modal = page.getByRole('dialog');
    await expect(modal).toBeVisible();
    const campos = modal.locator('input[inputmode="numeric"]');
    await campos.nth(0).fill(PIN_DO_CAIXA);
    await campos.nth(1).fill(PIN_DO_CAIXA);
    await modal.getByRole('button', { name: 'Definir PIN' }).click();
    await expect(modal).toBeHidden({ timeout: 15_000 });

    await aparelhoSincronizado(page);
    expect(await confere(page, CREDENCIAIS.caixa.email, PIN_DO_CAIXA), 'o zero à frente não se perdeu').toBe(true);

    await sairDaSessao(page);
    expect(await entrarSemRede(page, context, CREDENCIAIS.caixa.email, PIN_DO_CAIXA)).toBe('pos');
});

test('o que se recusa ao definir o PIN diz porquê — e o PIN que estava continua a valer', async ({ page }) => {
    await entrar(page);
    await aparelhoSincronizado(page);
    const antes = await avaliar(page, (mail) => window.SosPwa.db.employees.get(mail).then((e) => e?.pin_hash), CREDENCIAIS.email);

    await irPara(page, '/invoicing/offline/pin');
    const ecra = page.locator('[data-definir-pin]');
    await expect(ecra).toBeVisible({ timeout: 20_000 });

    const tentar = async (pin, repetido, senha) => {
        await ecra.getByLabel('PIN novo').fill(pin);
        await ecra.getByLabel('Repita o PIN').fill(repetido);
        await ecra.getByLabel('A sua palavra-passe').fill(senha);
        await ecra.getByRole('button', { name: /PIN/ }).click();
    };

    await tentar('1234', '1234', CREDENCIAIS.password);
    await expect(ecra.getByText('Escolha um PIN menos óbvio.')).toBeVisible({ timeout: 10_000 });

    await tentar('7390', '7391', CREDENCIAIS.password);
    await expect(ecra.getByText('Os dois PIN não coincidem.')).toBeVisible({ timeout: 10_000 });

    await tentar('7390', '7390', 'palavra-errada');
    await expect(ecra.getByText('Palavra-passe incorrecta.')).toBeVisible({ timeout: 10_000 });

    // Uma letra não entra no campo (fica «124»), e três dígitos não chegam.
    await tentar('12a4', '12a4', CREDENCIAIS.password);
    await expect(ecra.getByLabel('PIN novo')).toHaveValue('124');
    await expect(ecra.getByText('O PIN tem de ter 4 a 6 dígitos.')).toBeVisible({ timeout: 10_000 });

    await aparelhoSincronizado(page);
    const depois = await avaliar(page, (mail) => window.SosPwa.db.employees.get(mail).then((e) => e?.pin_hash), CREDENCIAIS.email);
    expect(depois, 'nenhuma tentativa recusada mexeu no PIN').toBe(antes);
});

test('quem é desactivado deixa de entrar sem rede na sincronização seguinte', async ({ page, context }) => {
    await entrar(page);
    await aparelhoSincronizado(page);
    expect(await confere(page, CREDENCIAIS.caixa.email, CREDENCIAIS.caixa.pin) !== null, 'o caixa está no aparelho').toBe(true);

    noServidor(CREDENCIAIS.caixa.email, { is_active: false });
    try {
        await sincronizar(page);
        expect(await avaliar(page, (mail) => window.SosPwa.db.employees.get(mail), CREDENCIAIS.caixa.email), 'desactivado sai do aparelho').toBeFalsy();

        await sairDaSessao(page);
        expect(await entrarSemRede(page, context, CREDENCIAIS.caixa.email, CREDENCIAIS.caixa.pin)).toBe('recusado');
    } finally {
        noServidor(CREDENCIAIS.caixa.email, { is_active: true });
    }
});

test('quem entra sem PIN é convidado a defini-lo, e volta ao PWA com ele', async ({ page, context }) => {
    await entrar(page);
    await aparelhoSincronizado(page);
    semPin(CREDENCIAIS.email);

    await sincronizar(page);
    const convite = page.locator('#pwa-definir-pin');
    await expect(convite, 'o convite aparece a quem não tem PIN').toBeVisible({ timeout: 15_000 });

    await convite.click();
    await page.waitForURL(/\/invoicing\/offline\/pin\?voltar=/, { timeout: 20_000 });

    const ecra = page.locator('[data-definir-pin]');
    await expect(ecra).toBeVisible({ timeout: 20_000 });
    await expect(ecra.getByText('Sem PIN')).toBeVisible();
    await ecra.getByLabel('PIN novo').fill(PIN_MEU);
    await ecra.getByLabel('Repita o PIN').fill(PIN_MEU);
    await ecra.getByLabel('A sua palavra-passe').fill(CREDENCIAIS.password);
    await ecra.getByRole('button', { name: 'Definir o PIN' }).click();

    await page.getByRole('link', { name: 'Voltar ao POS offline' }).click();
    await page.waitForURL(/\/invoicing\/offline(\/?$|\?)/, { timeout: 20_000 });
    await esperarMotor(page);
    await sincronizar(page);

    await expect(page.locator('#pwa-definir-pin'), 'com PIN, o convite sai').toBeHidden({ timeout: 15_000 });
    expect(await confere(page, CREDENCIAIS.email, PIN_MEU)).toBe(true);

    await sairDaSessao(page);
    expect(await entrarSemRede(page, context, CREDENCIAIS.email, PIN_MEU)).toBe('pos');
});