import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const base = 'https://soserp.vip';
const equipa = JSON.parse(process.env.EQUIPA || '[]');

if (equipa.length < 3) {
    throw new Error('EQUIPA precisa de tres operadores com email e PIN.');
}

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20_000 });
const context = browser.contexts()[0];
const page = context.pages().find((candidate) => candidate.url().includes('soserp.vip'));

if (!page) throw new Error('O Chrome do Android nao tem o SOSERP aberto.');
async function setNetworkOffline(offline) {
    execFileSync('adb', ['shell', 'cmd', 'connectivity', 'airplane-mode', offline ? 'enable' : 'disable']);
    execFileSync('adb', ['shell', 'svc', 'wifi', offline ? 'disable' : 'enable']);
    execFileSync('adb', ['shell', 'svc', 'data', offline ? 'disable' : 'enable']);
    await page.waitForFunction((expected) => navigator.onLine === !expected, offline, { timeout: 30_000 });
}

async function waitForEngine() {
    await page.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 45_000 });
}

async function go(path) {
    await page.goto(base + path, { waitUntil: 'domcontentloaded', timeout: 45_000 }).catch(() => {});
    await waitForEngine();
}

async function sync() {
    await page.evaluate(() => {
        window.__loginTestSync = { done: false, error: null };
        void window.SosPwa.sync(true)
            .then(() => { window.__loginTestSync.done = true; })
            .catch((error) => { window.__loginTestSync.error = String(error?.message || error); });
    });
    await page.waitForFunction(
        () => window.__loginTestSync?.done || window.__loginTestSync?.error,
        null,
        { timeout: 60_000 },
    );
    const result = await page.evaluate(() => window.__loginTestSync);
    if (result.error) throw new Error(result.error);
}

await setNetworkOffline(false);
await go('/invoicing/offline/pos');
await sync();

const preparation = await page.evaluate(async () => ({
    tenant: (await window.SosPwa.db.meta.get('tenant_id'))?.value,
    employees: await window.SosPwa.db.employees.count(),
    pending: await window.SosPwa.db.sync_queue.where('status').equals('pending').count(),
    failed: await window.SosPwa.db.sync_queue.where('status').equals('failed').count(),
}));
console.log(`PREPARACAO | ${JSON.stringify(preparation)}`);

const results = [];
await setNetworkOffline(true);

try {
    for (const operator of equipa.slice(0, 3)) {
        await page.evaluate(() => {
            sessionStorage.removeItem('pwa_unlocked');
            sessionStorage.removeItem('pwa_unlocked_at');
        });
        await go('/invoicing/offline/login');
        await page.waitForSelector('[data-ensaio="entrada"][data-estado="offline"]', { timeout: 20_000 });
        await page.locator('#entrada-email-local').fill(operator.email);
        await page.locator('#entrada-pin').fill(operator.pin);
        // Submete pelo proprio formulario. No Chrome Android, durante a
        // animacao de entrada, camadas irmas podem interceptar o toque.
        await page.locator('#entrada-pin').press('Enter');
        await page.waitForURL('**/invoicing/offline/pos', { timeout: 20_000 });
        await waitForEngine();

        const state = await page.evaluate(async () => ({
            online: navigator.onLine,
            unlocked: Boolean(window.SosPwa.isPwaUnlocked?.()),
            activeEmail: (await window.SosPwa.db.meta.get('user'))?.value?.email || null,
        }));
        const ok = state.online === false
            && state.unlocked === true
            && state.activeEmail?.toLowerCase() === operator.email.toLowerCase();
        results.push({ email: operator.email, ok, ...state });
        console.log(`${ok ? 'PASSOU' : 'FALHOU'} | ${operator.email} | offline=${!state.online} | ativo=${state.activeEmail}`);
        if (results.length === 3) {
            await page.screenshot({ path: 'storage/app/pwa-android-test/3-logins-offline.png', fullPage: true });
        }
    }
} finally {
    await setNetworkOffline(false);
}

await go('/invoicing/offline/pos');
await sync();
const finalState = await page.evaluate(async () => ({
    online: navigator.onLine,
    activeEmail: (await window.SosPwa.db.meta.get('user'))?.value?.email || null,
    pending: await window.SosPwa.db.sync_queue.where('status').equals('pending').count(),
    failed: await window.SosPwa.db.sync_queue.where('status').equals('failed').count(),
}));
console.log(`FINAL | ${JSON.stringify(finalState)}`);

if (results.length !== 3 || results.some((result) => !result.ok)) process.exitCode = 1;
await browser.close();
