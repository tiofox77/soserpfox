/**
 * Matriz final do PWA de PRODUCAO no Chrome de um Android ligado por CDP.
 *
 * As credenciais/PIN nunca ficam no repositorio. Passe dois operadores em
 * EQUIPA. O guiao trabalha apenas sobre a empresa ja autenticada no aparelho.
 *
 *   EQUIPA='[{"email":"...","pin":"..."}]' node scripts/ensaio_pwa_producao_total.mjs
 */
import { chromium } from '@playwright/test';

const BASE = 'https://soserp.vip';
const EQUIPA = JSON.parse(process.env.EQUIPA || '[]');

if (EQUIPA.length < 2) {
    throw new Error('EQUIPA precisa de pelo menos dois operadores.');
}

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20_000 });
const contexto = browser.contexts()[0];
const pagina = contexto.pages().find((p) => p.url().includes('soserp.vip/invoicing/offline'));

if (!pagina) throw new Error('O PWA de producao nao esta aberto no Android.');

const resultados = [];
const erros = [];
const registar = (passo, ok, detalhe) => {
    resultados.push({ passo, ok, detalhe });
    console.log(`${ok ? 'OK ' : 'FALHA'} | ${passo.padEnd(50)} | ${typeof detalhe === 'object' ? JSON.stringify(detalhe) : detalhe}`);
};
const tentar = async (passo, fn) => {
    try {
        const r = await fn();
        registar(passo, r?.ok !== false, r?.detalhe ?? r);
    } catch (e) {
        registar(passo, false, String(e?.message || e).slice(0, 240));
    }
};

pagina.on('pageerror', (e) => erros.push('pageerror: ' + e.message));
pagina.on('console', (m) => {
    if (m.type() === 'error' && !m.text().includes('ERR_INTERNET_DISCONNECTED')) erros.push('console: ' + m.text());
});

async function esperarMotor() {
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 45_000 });
}

// Nao aguarda directamente a Promise do motor: uma regressao nela nao pode
// congelar o ensaio sem dizer em que estado ficou.
async function sincronizar() {
    await pagina.evaluate(() => {
        window.__ensaioSync = { terminou: false, erro: null };
        void window.SosPwa.sync(true)
            .then(() => { window.__ensaioSync.terminou = true; })
            .catch((e) => { window.__ensaioSync.erro = String(e?.message || e); });
    });
    await pagina.waitForFunction(
        () => window.__ensaioSync?.terminou || window.__ensaioSync?.erro,
        null,
        { timeout: 60_000 }
    );
    const estado = await pagina.evaluate(() => window.__ensaioSync);
    if (estado.erro) throw new Error(estado.erro);
}

async function ir(caminho) {
    await pagina.goto(BASE + caminho, { waitUntil: 'domcontentloaded', timeout: 45_000 }).catch(() => {});
    await esperarMotor();
    await pagina.waitForFunction(() => document.querySelector('#pwa-raiz')?.children.length > 0, null, { timeout: 30_000 });
}

await contexto.setOffline(false);
await ir('/invoicing/offline/pos');
await sincronizar();

const base = await pagina.evaluate(async () => ({
    tenant: (await window.SosPwa.db.meta.get('tenant_id'))?.value,
    empresa: (await window.SosPwa.db.meta.get('company'))?.value?.name,
    produtos: await window.SosPwa.db.products.count(),
    empregados: await window.SosPwa.db.employees.count(),
    pendentes: await window.SosPwa.db.sync_queue.where('status').equals('pending').count(),
    falhadas: await window.SosPwa.db.sync_queue.where('status').equals('failed').count(),
}));
registar('preparacao da empresa e cache', base.tenant && base.produtos > 0 && base.empregados >= 2, base);

// Login REAL pelo formulario React, num separador sem desbloqueio de sessao.
await contexto.setOffline(true);
for (const operador of EQUIPA.slice(0, 2)) {
    await tentar('login offline pela interface: ' + operador.email, async () => {
        await pagina.evaluate(() => {
            sessionStorage.removeItem('pwa_unlocked');
            sessionStorage.removeItem('pwa_unlocked_at');
        });
        await ir('/invoicing/offline/login');
        await pagina.waitForSelector('[data-ensaio="entrada"][data-estado="offline"]', { timeout: 20_000 });
        await pagina.locator('#entrada-email-local').fill(operador.email);
        await pagina.locator('#entrada-pin').fill(operador.pin);
        await pagina.getByRole('button', { name: /Entrar sem rede/ }).click();
        await pagina.waitForURL('**/invoicing/offline/pos', { timeout: 20_000 });
        await esperarMotor();
        const activo = await pagina.evaluate(async () => ({
            desbloqueado: window.SosPwa.isPwaUnlocked(),
            email: (await window.SosPwa.db.meta.get('user'))?.value?.email,
        }));
        return { ok: activo.desbloqueado && activo.email === operador.email, detalhe: activo };
    });
}
await contexto.setOffline(false);
await ir('/invoicing/offline/pos');
await sincronizar();

// Mantem o ultimo operador do teste como autor local dos documentos seguintes.
const carimbo = Date.now();
const nomeCliente = `Cliente Matriz Android ${carimbo}`;
let cliente = null;

await tentar('cliente online criado e confirmado no servidor', async () => {
    await pagina.evaluate(async (nome) => {
        await window.SosPwa.createClientOffline({
            name: nome,
            nif: '5' + String(Date.now()).slice(-9),
            phone: '923000321',
            type: 'pessoa_fisica',
            country: 'AO',
        });
    }, nomeCliente);
    await sincronizar();
    cliente = await pagina.evaluate(async (nome) =>
        (await window.SosPwa.db.clients.toArray()).find((c) => c.name === nome) || null,
    nomeCliente);
    return { ok: !!cliente && !String(cliente.id).startsWith('local_'), detalhe: { id: cliente?.id, nome: cliente?.name } };
});

const artigo = await pagina.evaluate(async () => {
    const produtos = await window.SosPwa.db.products.toArray();
    const p = produtos.find((x) => Number(x.price) > 0);
    return p ? { id: p.id, name: p.name, price: Number(p.price), tax: Number(p.tax_rate || 0) } : null;
});
if (!artigo) throw new Error('A bancada nao tem artigo vendavel.');

const documentos = [];
async function criarDocumento(tipo, comCliente, modo) {
    const localUuid = await pagina.evaluate(async ({ tipo, comCliente, cliente, artigo, modo }) => {
        const d = await window.SosPwa.createDraftOffline({
            doc_type: tipo,
            client_id: comCliente ? cliente.id : null,
            client_name: comCliente ? cliente.name : 'Consumidor Final',
            items: [{
                product_id: artigo.id,
                product_name: artigo.name,
                quantity: 1,
                unit_price: artigo.price,
                tax_rate: artigo.tax,
                discount_percent: 0,
            }],
            notes: `matriz Android ${modo} ${tipo} ${comCliente ? 'cliente' : 'final'}`,
            invoice_date: new Date().toISOString().slice(0, 10),
        });
        return d.local_uuid;
    }, { tipo, comCliente, cliente, artigo, modo });
    documentos.push({ tipo, comCliente, modo, localUuid });
}

for (const modo of ['online', 'offline']) {
    if (modo === 'offline') await contexto.setOffline(true);
    for (const tipo of ['FT', 'FR', 'proforma']) {
        for (const comCliente of [false, true]) {
            await tentar(`${tipo} ${modo} · ${comCliente ? 'cliente' : 'Consumidor Final'} · guardar`, async () => {
                await criarDocumento(tipo, comCliente, modo);
                return { ok: true, detalhe: 'guardado no IndexedDB' };
            });
        }
    }
    if (modo === 'offline') await contexto.setOffline(false);
    await sincronizar();

    for (const doc of documentos.filter((d) => d.modo === modo)) {
        await tentar(`${doc.tipo} ${modo} · ${doc.comCliente ? 'cliente' : 'Consumidor Final'} · sincronizar`, async () => {
            const d = await pagina.evaluate((id) => window.SosPwa.db.draft_documents.get(id), doc.localUuid);
            const fiscal = doc.tipo === 'FT' || doc.tipo === 'FR';
            return {
                ok: d?._synced === 1 && !!d?._server_id && (!fiscal || !!d?._server_number),
                detalhe: { id: d?._server_id, numero: d?._server_number, cliente: d?.client_name, total: d?.total },
            };
        });
    }
}

async function venderPos(modo, comCliente) {
    const id = await pagina.evaluate(async ({ artigo, cliente, comCliente, modo }) => {
        const v = await window.SosPwa.createPosSaleOffline({
            client_id: comCliente ? cliente.id : null,
            client_name: comCliente ? cliente.name : 'Consumidor Final',
            payment_method: 'cash',
            notes: `POS Android ${modo} ${comCliente ? 'cliente' : 'final'}`,
            items: [{ product_id: artigo.id, product_name: artigo.name, quantity: 1, unit_price: artigo.price, tax_rate: artigo.tax, discount_percent: 0 }],
        });
        return v.local_uuid;
    }, { artigo, cliente, comCliente, modo });
    return id;
}

for (const modo of ['online', 'offline']) {
    if (modo === 'offline') await contexto.setOffline(true);
    const vendas = [];
    for (const comCliente of [false, true]) vendas.push({ comCliente, id: await venderPos(modo, comCliente) });
    if (modo === 'offline') await contexto.setOffline(false);
    await sincronizar();
    for (const venda of vendas) {
        await tentar(`POS ${modo} · ${venda.comCliente ? 'cliente' : 'Consumidor Final'}`, async () => {
            const v = await pagina.evaluate((id) => window.SosPwa.db.pos_sales.get(id), venda.id);
            return { ok: v?._synced === 1 && !!v?._server_number, detalhe: { numero: v?._server_number, cliente: v?.client_name, hash: v?._server_hash } };
        });
    }
}

// PDF e preview: os mesmos bytes nos dois modos para um exemplar de cada tipo.
const idsPdf = documentos.filter((d) => d.modo === 'online' && d.comCliente).map((d) => d.localUuid);
const hashesOnline = {};

async function medirPapel(id) {
    return pagina.evaluate(async (id) => {
        const d = await window.SosPwa.db.draft_documents.get(id);
        const pdf = await window.SosPwa.pdfDe('documento', d);
        const bytes = new Uint8Array(await pdf.blob.arrayBuffer());
        const digest = await crypto.subtle.digest('SHA-256', bytes);
        const hash = Array.from(new Uint8Array(digest)).map((v) => v.toString(16).padStart(2, '0')).join('');
        let abriu = false;
        const antiga = window.open;
        window.open = () => ({ document: { open() {}, write() { abriu = true; }, close() {} } });
        try { await window.SosPwa.imprimirDocumento(id); } finally { window.open = antiga; }
        return {
            tipo: d.doc_type,
            numero: d._server_number,
            bytes: bytes.length,
            cabeca: String.fromCharCode(...bytes.slice(0, 5)),
            hash,
            preview: abriu,
            partilha: !!navigator.canShare?.({ files: [new File([pdf.blob], pdf.nome, { type: 'application/pdf' })] }),
        };
    }, id);
}

for (const id of idsPdf) {
    await tentar('PDF/preview online ' + id, async () => {
        const r = await medirPapel(id);
        hashesOnline[id] = r.hash;
        return { ok: r.cabeca === '%PDF-' && r.bytes > 1_000 && r.preview && r.partilha, detalhe: r };
    });
}

await contexto.setOffline(true);
for (const id of idsPdf) {
    await tentar('PDF/preview offline igual ' + id, async () => {
        const r = await medirPapel(id);
        return { ok: r.hash === hashesOnline[id] && r.cabeca === '%PDF-' && r.preview && r.partilha, detalhe: { ...r, igualAoOnline: r.hash === hashesOnline[id] } };
    });
}
await contexto.setOffline(false);

const fila = await pagina.evaluate(async () => ({
    pendentes: await window.SosPwa.db.sync_queue.where('status').equals('pending').count(),
    falhadas: await window.SosPwa.db.sync_queue.where('status').equals('failed').count(),
}));
registar('fila final', fila.pendentes === 0 && fila.falhadas === 0, fila);

const falhas = resultados.filter((r) => !r.ok);
console.log('\n================ RESUMO FINAL ================');
console.log(`passos: ${resultados.length} | falhas: ${falhas.length} | erros consola: ${[...new Set(erros)].length}`);
for (const f of falhas) console.log(`  X ${f.passo}: ${JSON.stringify(f.detalhe)}`);
for (const e of [...new Set(erros)].slice(0, 12)) console.log('  consola: ' + e.slice(0, 240));

await browser.close().catch(() => {});
process.exitCode = falhas.length ? 1 : 0;
