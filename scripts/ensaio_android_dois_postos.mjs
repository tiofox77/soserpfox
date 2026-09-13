/**
 * DOIS POSTOS NA MESMA MESA — UM SEM REDE, OUTRO COM REDE.
 *
 * O caso que parte uma sala de restaurante:
 *
 *   1. o tablet (Android, em modo avião) senta clientes na mesa X e pede;
 *   2. ao mesmo tempo, outro posto COM rede (um Chrome no computador, com a
 *      mesma empresa) abre uma comanda na mesma mesa X — para ele está livre;
 *   3. o tablet fecha a conta sem rede (a mesa X fica «em limpeza» nele);
 *   4. a rede volta.
 *
 * O que tem de acontecer: nada se perde (a comanda do tablet sobe, com
 * factura, aberta ao balcão com a mesa pedida anotada), a comanda do outro
 * posto continua na mesa X, e o tablet deixa de a desenhar como sua — a mesa
 * X aparece ocupada, não em limpeza.
 *
 * Uso: ADB=<adb> EQUIPA='[{"email":..,"senha":..}]' node scripts/ensaio_android_dois_postos.mjs
 */
import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const ADB = process.env.ADB || 'adb';
const BASE = 'https://soserp.vip';
const EQUIPA = JSON.parse(process.env.EQUIPA || '[]');
const adb = (...args) => execFileSync(ADB, args, { maxBuffer: 64 * 1024 * 1024 });
const diz = (r, v) => console.log(String(r).padEnd(34), typeof v === 'object' ? JSON.stringify(v) : v);
const esperar = (ms) => new Promise((ok) => setTimeout(ok, ms));

const tablet = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20000 });
const pagina = tablet.contexts()[0].pages().find((p) => p.url().includes('soserp.vip'));
await pagina.bringToFront();
await pagina.goto(`${BASE}/invoicing/offline/restaurant`, { waitUntil: 'domcontentloaded' });
await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 45000 });
await pagina.evaluate(() => window.SosPwa.sync(true));
await pagina.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 });

/* A mesa livre, e o tablet sem rede. */
const alvo = await pagina.evaluate(async () => {
    const r = window.SosPwa.restaurante;
    for (const s of await r.salas()) {
        const m = (await r.mesas(s.id)).find((x) => x.status === 'available');
        if (m) return { sala: s.id, mesa: m.id, nome: m.name };
    }
    return null;
});
if (!alvo) { diz('ERRO', 'não há mesa livre'); process.exit(1); }
diz('mesa escolhida', alvo);

adb('shell', 'cmd', 'connectivity', 'airplane-mode', 'enable');
await esperar(6000);

let uuid;
try {
    uuid = await pagina.evaluate(async ({ sala, mesa }) => {
        const r = window.SosPwa.restaurante;
        const c = await r.abrir({ venue_id: sala, table_id: mesa, guest_count: 2 });
        const prato = (await window.SosPwa.db.products.toArray()).find((p) => (parseFloat(p.price) || 0) > 0);
        await r.juntar(c.local_uuid, { product_id: prato.id, product_name: prato.name, quantity: 2, unit_price: prato.price, tax_rate: prato.tax_rate });
        return c.local_uuid;
    }, alvo);
    diz('1. tablet sem rede sentou', { comanda: uuid, online: await pagina.evaluate(() => navigator.onLine) });

    /* O outro posto, com rede, abre a mesma mesa. */
    const outro = await chromium.launch();
    const posto = await outro.newPage();
    await posto.goto(`${BASE}/login`);
    await posto.fill('input[name="email"]', EQUIPA[0].email);
    await posto.fill('input[name="password"]', EQUIPA[0].senha);
    await posto.click('button[type="submit"]');
    await posto.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 45000 });
    await posto.goto(`${BASE}/restaurant/tables`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    const resposta = await posto.evaluate(async (mesa) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const r = await fetch('/api/v1/invoicing/react/restaurant/sala/abrir', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ table_id: mesa, guest_count: 3, notes: 'ensaio: o outro posto, com rede' }),
        });
        return { estado: r.status, corpo: await r.json().catch(() => null) };
    }, alvo.mesa);
    diz('2. outro posto com rede abriu', resposta);
    await outro.close();

    /* O tablet fecha a conta, ainda sem rede. */
    await pagina.evaluate(async (u) => {
        const metodo = ((await window.SosPwa.db.meta.get('payment_methods'))?.value || [])[0];
        await window.SosPwa.restaurante.receber(u, { document_type: 'FR', payment_method_id: metodo?.id || null });
    }, uuid);
    diz('3. tablet fechou a conta sem rede', await pagina.evaluate(async (m) => (await window.SosPwa.db.rest_tables.get(m))?.status, alvo.mesa));
} finally {
    adb('shell', 'cmd', 'connectivity', 'airplane-mode', 'disable');
}

/* A rede volta. */
await pagina.waitForFunction(() => navigator.onLine, null, { timeout: 60000 });
await esperar(4000);

let fim;
for (let i = 0; i < 10; i++) {
    await pagina.evaluate(() => window.SosPwa.sync(true)).catch(() => {});
    await pagina.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 }).catch(() => {});
    fim = await pagina.evaluate(async ({ u, mesa }) => {
        const c = await window.SosPwa.restaurante.comanda(u);
        const vista = (await window.SosPwa.restaurante.mesas()).find((m) => m.id === mesa);
        return {
            pendentes: await window.SosPwa.db.sync_queue.where('status').equals('pending').count(),
            falhadas: await window.SosPwa.db.sync_queue.where('status').equals('failed').count(),
            comanda_do_tablet: { mesa: c.table_id ?? null, mesa_pedida: c.mesa_pedida ?? null, numero: c._server_number, factura: c._invoice_number, avisos: c._avisos },
            mesa_no_tablet: { estado: vista?.status, e_do_tablet: vista?.comanda?.local_uuid === u },
        };
    }, { u: uuid, mesa: alvo.mesa });
    if (fim.pendentes === 0 && fim.comanda_do_tablet.factura) break;
    await esperar(3000);
}
diz('4. depois da rede', fim);

await tablet.close().catch(() => {});
