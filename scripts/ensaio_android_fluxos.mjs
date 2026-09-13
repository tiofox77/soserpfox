/**
 * O PWA A TRABALHAR SEM REDE NUM ANDROID — cliente, documento e mesa.
 *
 * Em modo avião a sério (adb), pela interface onde ela é o que se testa:
 *
 *   1. cria um cliente no formulário «Novo cliente»;
 *   2. emite uma proforma em nome dele no «Novo documento»;
 *   3. senta uma mesa, pede dois pratos e fecha a conta com Factura-Recibo
 *      (pela API do restaurante do motor, como o ensaio `pwa-restaurante`);
 *   4. volta a rede e confirma que TUDO subiu: o cliente com id do servidor, a
 *      proforma com número, a comanda com número CMD- e a factura com número
 *      fiscal — e nada falhado na fila.
 *
 * Proforma e não factura no passo 2: o documento de ensaio não precisa de ser
 * fiscal. A conta da mesa é FR porque é assim que o restaurante fecha.
 *
 * Uso: ADB=<adb> FOTOS=<pasta> node scripts/ensaio_android_fluxos.mjs
 */
import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';

const ADB = process.env.ADB || 'adb';
const PASTA = process.env.FOTOS || '.';
const BASE = 'https://soserp.vip';

const adb = (...args) => execFileSync(ADB, args, { maxBuffer: 64 * 1024 * 1024 });
const foto = (nome) => writeFileSync(`${PASTA}/android-fluxo-${nome}.png`, adb('exec-out', 'screencap', '-p'));
const diz = (r, v) => console.log(String(r).padEnd(30), typeof v === 'object' ? JSON.stringify(v) : v);
const esperar = (ms) => new Promise((ok) => setTimeout(ok, ms));

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20000 });
const contexto = browser.contexts()[0];
const pagina = contexto.pages().find((p) => p.url().includes('soserp.vip'));
await pagina.bringToFront();

const erros = [];
pagina.on('pageerror', (e) => erros.push(e.message.slice(0, 200)));
pagina.on('console', (m) => { if (m.type() === 'error') erros.push(m.text().slice(0, 200)); });

const ir = async (caminho) => {
    await pagina.goto(BASE + caminho, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen() && document.querySelector('#pwa-raiz')?.children.length > 0, null, { timeout: 30000 });
    await esperar(1500);
};

const marca = Date.now().toString().slice(-6);
const nomeCliente = `Cliente Android ${marca}`;
const resultado = {};

adb('shell', 'cmd', 'connectivity', 'airplane-mode', 'enable');
await esperar(6000);

try {
    /* 1. O cliente, pelo formulário. */
    await ir('/invoicing/offline/clients/new');
    await pagina.locator('input[name="name"]').fill(nomeCliente);
    await pagina.locator('input[name="nif"]').fill('5' + marca + '00');
    await pagina.locator('input[name="phone"]').fill('923' + marca);
    await pagina.getByRole('button', { name: /Guardar Cliente/ }).click();
    await pagina.getByText(/Cliente guardado/).first().waitFor({ timeout: 15000 });
    foto('1-cliente');
    resultado.cliente = await pagina.evaluate(async (n) => {
        const c = (await window.SosPwa.db.clients.toArray()).find((x) => x.name === n);
        return c ? { local_uuid: c.local_uuid, sincronizado: !!c.id && c._synced === 1 } : null;
    }, nomeCliente);
    diz('cliente sem rede', resultado.cliente);

    /* 2. A proforma em nome dele. */
    await ir('/invoicing/offline/drafts/new');
    await pagina.locator('button', { hasText: /^\s*Proforma\s*$/ }).first().click();
    await pagina.getByRole('button', { name: /Selecionar cliente/ }).click();
    await pagina.locator('input[name="pesquisa-cliente"]').fill(nomeCliente);
    await pagina.locator('[data-ensaio="folha-cliente"] [data-ensaio="cliente"]', { hasText: nomeCliente }).first().click();
    await pagina.getByRole('button', { name: /Adicionar/ }).first().click();
    await pagina.locator('input[name="pesquisa-produto"]').waitFor({ timeout: 10000 });
    await pagina.locator('[data-ensaio="folha-produto"] [data-ensaio="produto"]').first().click();
    await esperar(800);
    foto('2-documento');
    await pagina.getByRole('button', { name: /Emitir Documento/ }).click();
    await pagina.getByText(/Documento guardado/).first().waitFor({ timeout: 15000 });
    resultado.documento = await pagina.evaluate(async () => {
        const d = (await window.SosPwa.db.draft_documents.toArray()).sort((a, b) => String(b.created_at).localeCompare(String(a.created_at)))[0];
        return d ? { local_uuid: d.local_uuid, tipo: d.document_type ?? d.type, cliente: d.client_name, total: d.total } : null;
    });
    diz('proforma sem rede', resultado.documento);

    /* 3. A mesa: sentar, pedir, fechar a conta. */
    await ir('/invoicing/offline/restaurant');
    resultado.comanda = await pagina.evaluate(async () => {
        const r = window.SosPwa.restaurante;
        // A primeira sala pode não ter mesas (a bancada tem uma assim): procura-se uma que tenha.
        const abertas = new Set((await r.comandas()).filter((c) => c.table_id).map((c) => c.table_id));
        let sala = null;
        let mesa = null;
        for (const s of await r.salas()) {
            mesa = (await r.mesas(s.id)).find((m) => m.status === 'available' && !abertas.has(m.id));
            if (mesa) { sala = s; break; }
        }
        if (!mesa) return { erro: 'sem mesa livre em nenhuma sala' };

        const comanda = await r.abrir({ venue_id: sala.id, table_id: mesa.id, guest_count: 2 });
        const pratos = (await window.SosPwa.db.products.toArray()).filter((p) => (parseFloat(p.price) || 0) > 0);
        for (const prato of pratos.slice(0, 2)) {
            await r.juntar(comanda.local_uuid, { product_id: prato.id, product_name: prato.name, quantity: 1, unit_price: prato.price, tax_rate: prato.tax_rate });
        }

        return { local_uuid: comanda.local_uuid, mesa: mesa.name ?? mesa.id };
    });
    diz('comanda aberta sem rede', resultado.comanda);

    await ir('/invoicing/offline/restaurant');
    foto('3-mesas');

    await pagina.evaluate(async (u) => {
        const metodo = ((await window.SosPwa.db.meta.get('payment_methods'))?.value || [])[0];
        await window.SosPwa.restaurante.receber(u, { document_type: 'FR', payment_method_id: metodo?.id || null });
    }, resultado.comanda.local_uuid);

    diz('fila antes da rede', await pagina.evaluate(async () => {
        const fila = await window.SosPwa.db.sync_queue.where('status').equals('pending').toArray();
        return fila.map((j) => j.type ?? j.action);
    }));
} finally {
    adb('shell', 'cmd', 'connectivity', 'airplane-mode', 'disable');
}

/* 4. A rede volta: tudo tem de subir. */
await pagina.waitForFunction(() => navigator.onLine, null, { timeout: 60000 });
await esperar(4000);

let fim = null;
for (let volta = 0; volta < 12; volta++) {
    await pagina.evaluate(() => window.SosPwa.sync(true)).catch(() => {});
    await pagina.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 }).catch(() => {});

    fim = await pagina.evaluate(async ({ nome, doc, cmd }) => {
        const db = window.SosPwa.db;
        const c = (await db.clients.toArray()).find((x) => x.name === nome);
        const d = doc ? await db.draft_documents.get(doc) : null;
        const k = cmd ? await window.SosPwa.restaurante.comanda(cmd) : null;

        return {
            pendentes: await db.sync_queue.where('status').equals('pending').count(),
            falhadas: await db.sync_queue.where('status').equals('failed').toArray().then((f) => f.map((j) => ({ tipo: j.type, erro: j.last_error ?? j.error }))),
            cliente: c ? { id: c.id ?? null, sincronizado: c._synced === 1 } : null,
            proforma: d ? { numero: d._server_number ?? d.server_number ?? null, sincronizada: d._synced === 1 } : null,
            comanda: k ? { numero: k._server_number ?? null, factura: k._invoice_number ?? null } : null,
        };
    }, { nome: nomeCliente, doc: resultado.documento?.local_uuid, cmd: resultado.comanda?.local_uuid });

    if (fim.pendentes === 0 && fim.comanda?.factura) break;
    await esperar(3000);
}

diz('depois da rede', fim);
await ir('/invoicing/offline/drafts');
foto('4-documentos-online');
diz('erros da consola', erros);

await browser.close().catch(() => {});
