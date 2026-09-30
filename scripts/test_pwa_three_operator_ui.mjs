import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const base = 'https://soserp.vip';
const team = JSON.parse(process.env.EQUIPA || '[]');
if (team.length !== 3) throw new Error('EQUIPA precisa exactamente de tres operadores.');

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20_000 });
const context = browser.contexts()[0];
const page = context.pages().find((candidate) => candidate.url().startsWith(`${base}/invoicing/offline`));
if (!page) throw new Error('O POS PWA de producao nao esta aberto no Chrome Android.');
context.on('page', (popup) => {
    if (popup !== page) void page.bringToFront().catch(() => {});
});
await page.bringToFront();

let tag = `ANDROID-3OP-UI-${Date.now()}`;
const evidenceDir = 'storage/app/pwa-android-test';
const sales = [];
let recoveredShift = false;

async function network(offline) {
    execFileSync('adb', ['shell', 'cmd', 'connectivity', 'airplane-mode', offline ? 'enable' : 'disable']);
    execFileSync('adb', ['shell', 'svc', 'wifi', offline ? 'disable' : 'enable']);
    execFileSync('adb', ['shell', 'svc', 'data', offline ? 'disable' : 'enable']);
    if (!offline && !await page.evaluate(() => navigator.onLine)) {
        await page.reload({ waitUntil: 'domcontentloaded', timeout: 45_000 }).catch(() => {});
    }
    await page.waitForFunction((value) => navigator.onLine === !value, offline, { timeout: 30_000 });
}

async function engine() {
    await page.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 45_000 });
}

async function go(path) {
    await page.goto(base + path, { waitUntil: 'domcontentloaded', timeout: 45_000 }).catch(() => {});
    await engine();
}

async function pendingQueue() {
    return page.evaluate(async () => (await window.SosPwa.db.sync_queue.toArray())
        .filter((job) => job.status !== 'done')
        .map((job) => ({
            id: job.id,
            op: job.op,
            status: job.status,
            operator: job.payload?.operator_email || null,
            retries: Number(job.retries || 0),
            error: job.last_error || null,
        })));
}

async function closePrintTabs() {
    await page.bringToFront();
}

async function clickAndDismissPrint(locator) {
    setTimeout(() => {
        try { execFileSync('adb', ['shell', 'input', 'keyevent', '4']); } catch {}
    }, 2_200);
    await locator.evaluate((button) => button.click(), { timeout: 20_000 });
    await new Promise((resolve) => setTimeout(resolve, 3_000));
    await page.bringToFront();
}

try {
    await network(false);
    await go('/invoicing/offline/pos');

    const prep = await page.evaluate(async () => ({
        tenant: (await window.SosPwa.db.meta.get('tenant_id'))?.value,
        employees: await window.SosPwa.db.employees.count(),
        pendingJobs: (await window.SosPwa.db.sync_queue.toArray()).filter((job) => job.status !== 'done').map((job) => ({ op: job.op, operator: job.payload?.operator_email, note: job.payload?.opening_notes })),
        script: [...document.scripts].map((script) => script.src).find((src) => src.includes('/pwa-app/pwa-')),
    }));
    const resumingFirst = prep.pendingJobs.length === 2
        && prep.pendingJobs[0]?.op === 'open_pos_shift'
        && prep.pendingJobs[1]?.op === 'create_pos_sale'
        && prep.pendingJobs.every((job) => job.operator === team[0].email);
    const existingRecovery = prep.pendingJobs.length === 1
        && prep.pendingJobs[0]?.op === 'close_pos_shift'
        && prep.pendingJobs[0]?.operator === team[0].email;
    if (existingRecovery) recoveredShift = true;
    if (resumingFirst) tag = String(prep.pendingJobs[0].note).replace(/ abertura 1$/, '');
    if (prep.tenant !== 96 || prep.employees < 3 || (!resumingFirst && !existingRecovery && prep.pendingJobs.length !== 0) || !prep.script?.includes('pwa-9WAdnzcS.js')) {
        throw new Error(`Preparacao invalida: ${JSON.stringify(prep)}`);
    }
    console.log(`PASSOU DEPLOY | tenant=${prep.tenant} | operadores=${prep.employees} | asset=${prep.script.split('/').pop()}`);

    await network(true);
    console.log('ANDROID OFFLINE | os tres fluxos seguintes serao feitos sem rede');

    for (const [index, operator] of team.entries()) {
        await page.evaluate(() => {
            sessionStorage.removeItem('pwa_unlocked');
            sessionStorage.removeItem('pwa_unlocked_at');
        });
        await go('/invoicing/offline/login');
        await page.waitForSelector('[data-ensaio="entrada"][data-estado="offline"]', { timeout: 20_000 });
        await page.locator('#entrada-email-local').fill(operator.email);
        await page.locator('#entrada-pin').fill(operator.pin);
        await page.getByRole('button', { name: /Entrar sem rede/i }).click();
        await page.waitForURL('**/invoicing/offline/pos', { timeout: 20_000 });
        await engine();

        const active = await page.evaluate(async () => (await window.SosPwa.db.meta.get('user'))?.value?.email);
        if (active?.toLowerCase() !== operator.email.toLowerCase()) throw new Error(`Login incorrecto: ${active}`);

        if (index === 0 && (await page.getByTitle('Gerir turno').innerText()).includes('Turno')) {
            await page.getByTitle('Gerir turno').click();
            const recoveryCash = await page.evaluate(async () => {
                const shift = (await window.SosPwa.db.meta.get('shift'))?.value || {};
                return Number(shift.opening_balance || 0) + Number(shift.cash_sales || 0);
            });
            await page.locator('#turno-contado').fill(String(recoveryCash));
            await page.locator('#turno-notas-fecho').fill(`RECUPERACAO antes de ${tag}`);
            await clickAndDismissPrint(page.getByRole('button', { name: /Fechar e Imprimir/i }));
            await page.getByTitle('Gerir turno').filter({ hasText: /S\/ turno/i }).waitFor({ timeout: 20_000 });
            await closePrintTabs();
            recoveredShift = true;
            console.log('RECUPERACAO | turno anterior da Ana fechado offline sem perda');
        }

        const resumeThisOperator = index === 0 && resumingFirst;
        if (!resumeThisOperator) {
            await page.getByTitle('Gerir turno').click();
            await page.locator('#turno-saldo').fill('0');
            await page.locator('#turno-notas').fill(`${tag} abertura ${index + 1}`);
            await page.getByRole('button', { name: /^Abrir turno$/i }).last().click({ force: true });
            await page.getByTitle('Gerir turno').filter({ hasText: /Turno/i }).waitFor({ timeout: 15_000 });

            await page.getByRole('button', { name: /Água 1,5L/i }).click();
            await page.getByRole('button', { name: /Ver carrinho/i }).click();
            await clickAndDismissPrint(page.getByRole('button', { name: /^Finalizar Venda$/i }));
            await page.getByRole('button', { name: /Nova Venda/i }).waitFor({ timeout: 20_000 });
            await closePrintTabs();
        }

        const localSale = await page.evaluate(async () => {
            const rows = await window.SosPwa.db.pos_sales.toArray();
            return rows.filter((row) => row._synced !== 1).at(-1) || null;
        });
        if (!localSale) throw new Error(`Venda local nao encontrada para operador ${index + 1}`);

        if (!resumeThisOperator) await page.getByRole('button', { name: /Nova Venda/i }).evaluate((button) => button.click());
        await page.getByTitle('Gerir turno').click();
        await page.locator('#turno-contado').fill(String(Number(localSale.total)));
        await page.locator('#turno-notas-fecho').fill(`${tag} fecho ${index + 1}`);
        await clickAndDismissPrint(page.getByRole('button', { name: /Fechar e Imprimir/i }));
        await page.getByTitle('Gerir turno').filter({ hasText: /S\/ turno/i }).waitFor({ timeout: 20_000 });
        await closePrintTabs();

        const jobs = (await pendingQueue()).filter((job) => job.operator === operator.email).slice(-3);
        const expected = ['open_pos_shift', 'create_pos_sale', 'close_pos_shift'];
        if (JSON.stringify(jobs.map((job) => job.op)) !== JSON.stringify(expected)) {
            throw new Error(`Fila incorrecta para operador ${index + 1}: ${JSON.stringify(jobs)}`);
        }
        sales.push({ operator: operator.email, uuid: localSale.local_uuid, total: Number(localSale.total) });
        await page.screenshot({ path: `${evidenceDir}/${tag}-operador-${index + 1}-offline.png` });
        console.log(`PASSOU OFFLINE ${index + 1} | login+turno+venda+fecho | total=${Number(localSale.total)} | fila=${jobs.map((job) => job.op).join('>')}`);
    }

    const before = await pendingQueue();
    const expectedPending = 9 + (recoveredShift ? 1 : 0);
    if (before.length !== expectedPending || before.some((job) => job.status !== 'pending' || job.retries !== 0)) {
        throw new Error(`Fila offline deveria ter ${expectedPending} pendentes limpos: ${JSON.stringify(before)}`);
    }
    console.log(`PASSOU FILA OFFLINE | pendentes=${before.length} | retries=0`);

    await network(false);
    await page.getByRole('button', { name: /Sincronizar agora/i }).click();
    await page.waitForFunction(async () => (await window.SosPwa.db.sync_queue.toArray()).every((job) => job.status === 'done'), null, { timeout: 120_000 });

    const after = await page.evaluate(async (sales) => ({
        queue: (await window.SosPwa.db.sync_queue.toArray()).filter((job) => sales.some((sale) => job.payload?.operator_email === sale.operator)).slice(-9).map((job) => ({ op: job.op, retries: Number(job.retries || 0), error: job.last_error || null })),
        sales: await Promise.all(sales.map(async (sale) => {
            const row = await window.SosPwa.db.pos_sales.get(sale.uuid);
            return { operator: sale.operator, id: row?._server_id, number: row?._server_number, hash: row?._server_hash, synced: row?._synced };
        })),
    }), sales);
    if (after.queue.length !== 9 || after.queue.some((job) => job.retries !== 0 || job.error)) {
        throw new Error(`A sincronizacao nao concluiu limpa numa passagem: ${JSON.stringify(after.queue)}`);
    }
    if (after.sales.some((sale) => sale.synced !== 1 || !sale.id || !sale.number || !sale.hash)) {
        throw new Error(`Venda sem comprovativo do servidor: ${JSON.stringify(after.sales)}`);
    }
    console.log(`PASSOU SYNC UNICO | 9/9 | zero repeticoes | ${after.sales.map((sale) => sale.number).join(', ')}`);

    const server = await page.evaluate(async (tag) => {
        const response = await fetch('/api/v1/invoicing/react/turnos/historico?status=closed&page=1', { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(`Historico HTTP ${response.status}`);
        const json = await response.json();
        return (json.data || []).filter((shift) => String(shift.closing_notes || '').startsWith(tag)).map((shift) => ({
            id: shift.id,
            number: shift.shift_number,
            operator: shift.operador,
            closedBy: shift.fechado_por,
            invoices: shift.total_invoices,
            expected: Number(shift.expected_cash),
            actual: Number(shift.actual_cash),
            status: shift.status,
        }));
    }, tag);
    if (server.length !== 3 || server.some((shift) => shift.status !== 'closed' || shift.invoices !== 1 || shift.operator !== shift.closedBy || Math.abs(shift.expected - shift.actual) > 0.01)) {
        throw new Error(`Turnos do servidor invalidos: ${JSON.stringify(server)}`);
    }
    await page.screenshot({ path: `${evidenceDir}/${tag}-sincronizado.png` });
    console.log(`PASSOU SERVIDOR | ${server.map((shift) => `${shift.number}:${shift.operator}=${shift.closedBy}`).join(' | ')}`);
    console.log(`FINAL | tag=${tag} | vendas=3 | turnos=3 | fila=0`);
} finally {
    await network(false).catch(() => {});
    await browser.close().catch(() => {});
}
