/**
 * O PWA de produção, de ponta a ponta, num Android.
 *
 * Percorre tudo o que um operador faz num turno: abrir a caixa, criar um
 * cliente, tirar uma proforma, emitir uma factura, vender ao balcão, abrir uma
 * mesa no restaurante e fechá-la. Com rede e sem rede.
 *
 * NÃO PÁRA NO PRIMEIRO ERRO. Um ensaio que aborta à primeira só conta um
 * defeito por corrida; aqui cada passo é apanhado, registado e o seguinte
 * continua — no fim há a lista inteira do que está partido, que é o que
 * permite decidir o que se corrige primeiro.
 *
 * Uso: EQUIPA='[...]' node scripts/ensaio_completo.mjs
 */
import { chromium } from '@playwright/test';

const BASE = 'https://soserp.vip';
const EQUIPA = JSON.parse(process.env.EQUIPA || '[]');

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20000 });
const contexto = browser.contexts()[0];
const pagina = contexto.pages().find((p) => p.url().includes('soserp.vip')) || contexto.pages()[0];

const resultados = [];

const registar = (passo, ok, detalhe) => {
    resultados.push({ passo, ok, detalhe });
    console.log(`${ok ? 'OK ' : 'FALHA'} | ${String(passo).padEnd(38)} | ${typeof detalhe === 'object' ? JSON.stringify(detalhe) : detalhe}`);
};

async function tentar(nome, fn) {
    try {
        const r = await fn();
        registar(nome, r?.ok !== false, r?.detalhe ?? r);
    } catch (e) {
        registar(nome, false, 'ERRO: ' + String(e.message).slice(0, 150));
    }
}

const esperarMotor = (p = pagina) => p.waitForFunction(
    () => window.SosPwa && window.SosPwa.db && window.SosPwa.db.isOpen(), null, { timeout: 45000 }
);

const sincronizar = async (p = pagina) => {
    await p.evaluate(() => window.SosPwa.sync(true));
    await p.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 });
};

// ── Preparação ───────────────────────────────────────────────────────────
await contexto.setOffline(false);
await pagina.goto(`${BASE}/invoicing/offline/pos`, { waitUntil: 'domcontentloaded' });
await esperarMotor();
await sincronizar();

const erros = [];
pagina.on('pageerror', (e) => erros.push(String(e.message).slice(0, 160)));
pagina.on('console', (m) => { if (m.type() === 'error') erros.push(String(m.text()).slice(0, 160)); });

// ── 1. TURNO ─────────────────────────────────────────────────────────────
await tentar('turno: abrir e subir ao servidor', async () => {
    const jaAberto = await pagina.evaluate(async () => {
        const t = (await window.SosPwa.db.meta.get('shift'))?.value;
        return !!(t?.open && !t._local);
    });

    if (!jaAberto) {
        await pagina.evaluate(() => window.SosPwa.openShiftOffline({ opening_balance: 0 }));
        await sincronizar();
    }

    const t = await pagina.evaluate(async () => (await window.SosPwa.db.meta.get('shift'))?.value);

    return { ok: !!(t?.open && !t._local), detalhe: t?.number ?? 'sem número' };
});

// ── 2. CLIENTES ──────────────────────────────────────────────────────────
const carimbo = Date.now();

await tentar('cliente: criar com rede e sincronizar', async () => {
    const nome = 'Cliente Ensaio ' + carimbo;

    const local = await pagina.evaluate(async (n) => {
        const c = await window.SosPwa.createClientOffline({ name: n, nif: '999999999', phone: '900111222' });
        return { uuid: c.local_uuid, id: String(c.id) };
    }, nome);

    await sincronizar();

    // PROCURA-SE PELO NOME, e não pelo local_uuid.
    //
    // A sincronização substitui a tabela de clientes pela lista do servidor, e
    // os registos do servidor não trazem `local_uuid`. Procurar por ele dava
    // "não encontrado" e fazia parecer que o cliente se tinha perdido — quando
    // o que se perdeu foi só a chave por onde eu o procurava.
    const depois = await pagina.evaluate(async (n) => {
        const t = await window.SosPwa.db.clients.toArray();
        const c = t.find((x) => x.name === n);
        const trabalho = (await window.SosPwa.db.sync_queue.toArray())
            .filter((j) => j.op === 'create_client').pop();

        return {
            encontrado: !!c,
            id: c?.id,
            local: String(c?.id ?? '').startsWith('local_'),
            fila: trabalho?.status,
        };
    }, nome);

    return {
        ok: depois.encontrado && !depois.local && depois.fila === 'done',
        detalhe: `id ${depois.id} · fila ${depois.fila}`,
    };
});

await tentar('cliente: criar SEM rede e repor', async () => {
    const nome = 'Cliente Offline ' + carimbo;

    await contexto.setOffline(true);

    const local = await pagina.evaluate(async (n) => {
        const c = await window.SosPwa.createClientOffline({ name: n, nif: '999999999' });
        return c.local_uuid;
    }, nome);

    await contexto.setOffline(false);
    await sincronizar();

    const depois = await pagina.evaluate(async (n) => {
        const t = await window.SosPwa.db.clients.toArray();
        const c = t.find((x) => x.name === n);
        const trabalho = (await window.SosPwa.db.sync_queue.toArray())
            .filter((j) => j.op === 'create_client').pop();

        return { encontrado: !!c, id: c?.id, local: String(c?.id ?? '').startsWith('local_'), fila: trabalho?.status };
    }, nome);

    return { ok: depois.encontrado && !depois.local && depois.fila === 'done', detalhe: `id ${depois.id} · fila ${depois.fila}` };
});

// ── 3. POS: venda ────────────────────────────────────────────────────────
const artigo = await pagina.evaluate(async () => {
    const t = await window.SosPwa.db.products.toArray();
    const a = t.find((p) => (parseFloat(p.price) || 0) > 0);
    return a ? { id: a.id, name: a.name, price: parseFloat(a.price), tax: parseFloat(a.tax_rate) || 0 } : null;
});

const vender = (p, a, nota, qtd = 1) => p.evaluate(async ({ a, nota, qtd }) => {
    const v = await window.SosPwa.createPosSaleOffline({
        client_name: 'Consumidor Final', payment_method: 'cash', notes: nota,
        items: [{ product_id: a.id, product_name: a.name, quantity: qtd, unit_price: a.price, tax_rate: a.tax, discount_percent: 0 }],
    });
    return v.local_uuid;
}, { a, nota, qtd });

const lerVenda = (p, u) => p.evaluate(async (u) => {
    const t = await window.SosPwa.db.pos_sales.toArray();
    const v = t.find((x) => x.local_uuid === u);
    return v ? { sincronizada: v._synced === 1, numero: v._server_number, hash: v._server_hash, total: v.total } : null;
}, u);

await tentar('POS: venda com rede', async () => {
    const u = await vender(pagina, artigo, 'ensaio completo online');
    await sincronizar();
    const v = await lerVenda(pagina, u);

    return { ok: !!(v?.sincronizada && v.numero && v.hash), detalhe: `${v?.numero} · hash ${v?.hash}` };
});

await tentar('POS: venda SEM rede, reposta depois', async () => {
    await contexto.setOffline(true);
    const u = await vender(pagina, artigo, 'ensaio completo offline', 3);
    const naFila = await pagina.evaluate(() => window.SosPwa.db.sync_queue.where('status').equals('pending').count());

    await contexto.setOffline(false);
    await sincronizar();

    const v = await lerVenda(pagina, u);

    return { ok: !!(v?.sincronizada && v.numero), detalhe: `fila ${naFila} → ${v?.numero}` };
});

await tentar('POS: sincronizar duas vezes não duplica', async () => {
    await contexto.setOffline(true);
    const u = await vender(pagina, artigo, 'ensaio idempotencia');
    await contexto.setOffline(false);

    await sincronizar();
    const a = await lerVenda(pagina, u);
    await sincronizar();
    const b = await lerVenda(pagina, u);

    const quantas = await pagina.evaluate(async (u) => {
        const t = await window.SosPwa.db.pos_sales.toArray();
        return t.filter((x) => x.local_uuid === u).length;
    }, u);

    return { ok: a?.numero === b?.numero && quantas === 1, detalhe: `${a?.numero} == ${b?.numero}, cópias ${quantas}` };
});

// ── 4. PROFORMA e FACTURA (rascunhos) ────────────────────────────────────
const rascunho = (tipo) => pagina.evaluate(async ({ tipo, a }) => {
    const d = await window.SosPwa.createDraftOffline({
        doc_type: tipo,
        client_id: null,
        items: [{ product_id: a.id, product_name: a.name, quantity: 2, unit_price: a.price, tax_rate: a.tax, discount_percent: 0 }],
        notes: 'ensaio ' + tipo,
        invoice_date: new Date().toISOString().slice(0, 10),
    });
    return d.local_uuid;
}, { tipo, a: artigo });

// Os tipos que o servidor aceita são FT, FR e proforma. Mandar "invoice"
// dava 422 — e o 422 estava certo: era o ensaio que inventava um tipo.
for (const [rotulo, tipo] of [['proforma', 'proforma'], ['factura', 'FT'], ['factura-recibo', 'FR']]) {
    await tentar(`${rotulo}: rascunho sobe ao servidor`, async () => {
        const u = await rascunho(tipo);
        await sincronizar();

        const d = await pagina.evaluate(async (u) => {
            const t = await window.SosPwa.db.draft_documents.toArray();
            const x = t.find((y) => y.local_uuid === u);
            return x ? { sincronizado: x._synced === 1, numero: x._server_number, servidor: x._server_id } : null;
        }, u);

        return { ok: !!d?.sincronizado, detalhe: d ? `${d.numero ?? 'sem número'} (id ${d.servidor})` : 'não encontrado' };
    });
}

// ── 5. RESTAURANTE ───────────────────────────────────────────────────────
await tentar('restaurante: sala chega ao aparelho', async () => {
    const sala = await pagina.evaluate(async () => ({
        modulo: await window.SosPwa.temModulo('restaurant'),
        salas: await window.SosPwa.db.rest_venues.count(),
        mesas: await window.SosPwa.db.rest_tables.count(),
        cozinha: (await window.SosPwa.db.meta.get('restaurant_settings'))?.value?.use_kitchen_workflow,
    }));

    return { ok: sala.modulo === true, detalhe: `${sala.salas} sala(s), ${sala.mesas} mesa(s), cozinha=${sala.cozinha}` };
});

await tentar('restaurante: comanda numa mesa, sem rede', async () => {
    const mesa = await pagina.evaluate(async () => {
        const m = await window.SosPwa.db.rest_tables.toArray();
        return m[0] ?? null;
    });

    if (!mesa) { return { ok: false, detalhe: 'não há mesas nesta empresa' }; }

    await contexto.setOffline(true);

    const r = await pagina.evaluate(async ({ mesa, a }) => {
        const c = await window.SosPwa.restaurante.abrir({ table_id: mesa.id, venue_id: mesa.venue_id, guest_count: 2 });
        await window.SosPwa.restaurante.juntar(c.local_uuid, {
            product_id: a.id, name: a.name, quantity: 2, unit_price: a.price, tax_rate: a.tax,
        });
        return { uuid: c.local_uuid, artigos: (await window.SosPwa.db.rest_orders.get(c.local_uuid))?.items?.length };
    }, { mesa, a: artigo });

    await contexto.setOffline(false);

    return { ok: !!r.uuid && r.artigos > 0, detalhe: `comanda ${r.uuid}, ${r.artigos} artigo(s)` };
});

await tentar('restaurante: receber e a comanda sobe', async () => {
    const comanda = await pagina.evaluate(async () => {
        const t = await window.SosPwa.db.rest_orders.toArray();
        return t.find((c) => !c._synced) ?? null;
    });

    if (!comanda) { return { ok: false, detalhe: 'não há comanda por fechar' }; }

    const metodo = await pagina.evaluate(async () => (await window.SosPwa.db.meta.get('payment_methods'))?.value?.[0] ?? null);

    const total = await pagina.evaluate(async (u) => {
        const c = await window.SosPwa.db.rest_orders.get(u);
        return (c?.items || []).reduce((s, i) => s + (parseFloat(i.unit_price) || 0) * (parseFloat(i.quantity) || 0), 0);
    }, comanda.local_uuid);

    await pagina.evaluate(async ({ uuid, metodo, total }) => {
        await window.SosPwa.restaurante.receber(uuid, {
            document_type: 'FR',
            // O SERVIDOR RECUSA UM PAGAMENTO DE ZERO, e faz bem: uma conta fechada
            // por zero e uma conta que ninguem pagou. Manda-se o total da comanda.
            payments: [{ payment_method_id: metodo?.id ?? null, amount: total }],
        });
    }, { uuid: comanda.local_uuid, metodo, total });

    await sincronizar();

    const depois = await pagina.evaluate(async (u) => {
        const c = await window.SosPwa.db.rest_orders.get(u);
        return c ? { sincronizada: c._synced === 1, numero: c._invoice_number, hash: c._server_hash } : null;
    }, comanda.local_uuid);

    return { ok: !!depois?.sincronizada, detalhe: depois ? `${depois.numero ?? 'sem número'} · hash ${depois.hash ?? '—'}` : 'sumiu' };
});

// ── 6. PIN de toda a equipa ──────────────────────────────────────────────
for (const quem of EQUIPA) {
    await tentar('PIN offline: ' + quem.email, async () => {
        const r = await pagina.evaluate(
            ([e, p]) => window.SosPwa.verifyOfflineAuth(e, p), [quem.email, quem.pin]
        );
        return { ok: r.ok === true, detalhe: r.ok ? 'aceite' : r.reason };
    });
}

// ── Resumo ───────────────────────────────────────────────────────────────
const falhas = resultados.filter((r) => !r.ok);

console.log('\n================ RESUMO ================');
console.log(`passos: ${resultados.length} | falhas: ${falhas.length}`);
falhas.forEach((f) => console.log(`  ✗ ${f.passo}: ${typeof f.detalhe === 'object' ? JSON.stringify(f.detalhe) : f.detalhe}`));

if (erros.length) {
    console.log('\nERROS DE CONSOLA (' + erros.length + '):');
    [...new Set(erros)].slice(0, 8).forEach((e) => console.log('  · ' + e));
}

await browser.close();
