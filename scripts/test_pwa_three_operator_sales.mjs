import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const base = 'https://soserp.vip';
const team = JSON.parse(process.env.EQUIPA || '[]');
if (team.length < 3) throw new Error('EQUIPA precisa de tres operadores.');

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20_000 });
const context = browser.contexts()[0];
const page = context.pages().find((candidate) => candidate.url().includes('soserp.vip'));
if (!page) throw new Error('SOSERP nao esta aberto no Chrome Android.');

const tag = `3OP-${Date.now()}`;
const sales = [];

async function network(offline) {
    execFileSync('adb', ['shell', 'cmd', 'connectivity', 'airplane-mode', offline ? 'enable' : 'disable']);
    execFileSync('adb', ['shell', 'svc', 'wifi', offline ? 'disable' : 'enable']);
    execFileSync('adb', ['shell', 'svc', 'data', offline ? 'disable' : 'enable']);
    await page.waitForFunction((expected) => navigator.onLine === !expected, offline, { timeout: 30_000 });
}

async function engine() {
    await page.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 45_000 });
}

async function go(path) {
    await page.goto(base + path, { waitUntil: 'domcontentloaded', timeout: 45_000 }).catch(() => {});
    await engine();
}

async function sync() {
    await page.evaluate(() => {
        window.__threeOpSync = { done: false, error: null };
        void window.SosPwa.sync(true)
            .then(() => { window.__threeOpSync.done = true; })
            .catch((error) => { window.__threeOpSync.error = String(error?.message || error); });
    });
    await page.waitForFunction(() => window.__threeOpSync?.done || window.__threeOpSync?.error, null, { timeout: 90_000 });
    const result = await page.evaluate(() => window.__threeOpSync);
    if (result.error) throw new Error(result.error);
}

async function pendingQueue() {
    return page.evaluate(async () => (await window.SosPwa.db.sync_queue.toArray())
        .filter((job) => job.status !== 'done')
        .map((job) => ({ id: job.id, op: job.op, status: job.status, operator: job.payload?.operator_email, retries: job.retries, error: job.last_error || null })));
}

try {
    await network(false);
    await go('/invoicing/offline/pos');
    await sync();

    const preparation = await page.evaluate(async () => ({
        tenant: (await window.SosPwa.db.meta.get('tenant_id'))?.value,
        employees: await window.SosPwa.db.employees.count(),
        pending: await window.SosPwa.db.sync_queue.where('status').equals('pending').count(),
        failed: await window.SosPwa.db.sync_queue.where('status').equals('failed').count(),
        product: (await window.SosPwa.db.products.toArray()).find((item) => Number(item.price) > 0) || null,
    }));
    if (preparation.tenant !== 96 || !preparation.product || preparation.pending || preparation.failed) {
        throw new Error(`Preparacao invalida: ${JSON.stringify({ ...preparation, product: preparation.product?.name })}`);
    }
    console.log(`PREPARACAO | tenant=${preparation.tenant} | operadores=${preparation.employees} | produto=${preparation.product.name}`);

    await network(true);

    for (const operator of team.slice(0, 3)) {
        await page.evaluate(() => {
            sessionStorage.removeItem('pwa_unlocked');
            sessionStorage.removeItem('pwa_unlocked_at');
        });
        await go('/invoicing/offline/login');
        await page.waitForSelector('[data-ensaio="entrada"][data-estado="offline"]', { timeout: 20_000 });
        await page.locator('#entrada-email-local').fill(operator.email);
        await page.locator('#entrada-pin').fill(operator.pin);
        await page.locator('#entrada-pin').press('Enter');
        await page.waitForURL('**/invoicing/offline/pos', { timeout: 20_000 });
        await engine();

        const result = await page.evaluate(async ({ operator, product, tag }) => {
            const user = (await window.SosPwa.db.meta.get('user'))?.value;
            if (String(user?.email).toLowerCase() !== operator.email.toLowerCase()) throw new Error('Operador activo incorrecto');

            const shift = await window.SosPwa.openShiftOffline({
                opening_balance: 0,
                opening_notes: `${tag} abertura ${operator.email}`,
            });
            const sale = await window.SosPwa.createPosSaleOffline({
                client_id: null,
                client_name: 'Consumidor Final',
                client_nif: '999999999',
                payment_method: 'cash',
                notes: `${tag} venda ${operator.email}`,
                items: [{
                    product_id: product.id,
                    product_name: product.name,
                    quantity: 1,
                    unit_price: Number(product.price),
                    tax_rate: Number(product.tax_rate || 0),
                    discount_percent: 0,
                }],
            });
            await window.SosPwa.closeShiftOffline({
                actual_cash: Number(sale.total),
                closing_notes: `${tag} fecho ${operator.email}`,
                difference_reason: 'Ensaio PWA offline com tres operadores',
            });

            const jobs = (await window.SosPwa.db.sync_queue.toArray())
                .filter((job) => job.status === 'pending' && job.payload?.operator_email === operator.email)
                .map((job) => job.op);
            return {
                user: user.email,
                shift: shift.number,
                saleUuid: sale.local_uuid,
                total: Number(sale.total),
                jobs,
                offline: !navigator.onLine,
            };
        }, { operator, product: preparation.product, tag });

        const expected = ['open_pos_shift', 'create_pos_sale', 'close_pos_shift'];
        const ok = result.offline && JSON.stringify(result.jobs.slice(-3)) === JSON.stringify(expected);
        console.log(`${ok ? 'PASSOU' : 'FALHOU'} OFFLINE | ${operator.email} | turno=${result.shift} | venda=${result.saleUuid} | total=${result.total} | fila=${result.jobs.slice(-3).join('>')}`);
        if (!ok) throw new Error(`Sequencia offline incorrecta para ${operator.email}`);
        sales.push({ email: operator.email, localUuid: result.saleUuid, total: result.total });
    }

    await page.screenshot({ path: 'storage/app/pwa-android-test/3-operadores-vendas-offline.png', fullPage: true });
    const before = await pendingQueue();
    console.log(`FILA OFFLINE | ${before.length} pendentes | ${before.map((job) => `${job.op}:${job.operator}`).join(' | ')}`);

    await network(false);
    for (let attempt = 1; attempt <= 3; attempt++) {
        await sync();
        const remaining = await pendingQueue();
        console.log(`SYNC ${attempt} | restantes=${remaining.length}${remaining.length ? ` | ${JSON.stringify(remaining)}` : ''}`);
        if (!remaining.length) break;
    }

    const localValidation = await page.evaluate(async (sales) => Promise.all(sales.map(async (sale) => {
        const record = await window.SosPwa.db.pos_sales.get(sale.localUuid);
        return {
            email: sale.email,
            localUuid: sale.localUuid,
            synced: record?._synced === 1,
            serverId: record?._server_id || null,
            number: record?._server_number || null,
            total: Number(record?.total || 0),
            hash: record?._server_hash || null,
        };
    })), sales);

    for (const sale of localValidation) {
        const ok = sale.synced && sale.serverId && sale.number && sale.hash && Math.abs(sale.total - sales.find((s) => s.email === sale.email).total) < 0.01;
        console.log(`${ok ? 'PASSOU' : 'FALHOU'} VENDA | ${sale.email} | numero=${sale.number} | id=${sale.serverId} | total=${sale.total} | hash=${sale.hash || '-'}`);
        if (!ok) throw new Error(`Venda nao validada para ${sale.email}`);
    }

    const serverValidation = await page.evaluate(async ({ tag, localValidation }) => {
        const response = await fetch('/api/v1/invoicing/react/turnos/historico?status=closed&page=1', { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(`Historico HTTP ${response.status}`);
        const history = await response.json();
        const shifts = (history.data || []).filter((shift) => String(shift.closing_notes || '').startsWith(tag));
        const details = [];
        for (const shift of shifts) {
            const detailResponse = await fetch(`/api/v1/invoicing/react/turnos/${shift.id}`, { headers: { Accept: 'application/json' } });
            const detail = await detailResponse.json();
            const numbers = (detail.turno?.movimentos || []).map((movement) => movement.reference_number).filter(Boolean);
            details.push({
                id: shift.id,
                number: shift.shift_number,
                operator: shift.operador,
                status: shift.status,
                movements: numbers,
                invoices: shift.total_invoices,
                expected: shift.expected_cash,
                actual: shift.actual_cash,
                containsSale: localValidation.some((sale) => numbers.includes(sale.number)),
            });
        }
        return details;
    }, { tag, localValidation });

    for (const shift of serverValidation) {
        console.log(`${shift.status === 'closed' && shift.containsSale ? 'PASSOU' : 'FALHOU'} TURNO | ${shift.operator} | ${shift.number} | movimentos=${shift.movements.join(',')} | esperado=${shift.expected} | contado=${shift.actual}`);
    }
    if (serverValidation.length !== 3 || serverValidation.some((shift) => shift.status !== 'closed' || !shift.containsSale)) {
        throw new Error(`Validacao dos turnos incompleta: ${JSON.stringify(serverValidation)}`);
    }

    const finalQueue = await pendingQueue();
    console.log(`FINAL | vendas=3 | turnos=3 | fila=${finalQueue.length} | tag=${tag}`);
} finally {
    await network(false).catch(() => {});
    await browser.close().catch(() => {});
}
