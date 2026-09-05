/**
 * A impressão de documentos no PWA, num Android, contra produção.
 *
 * A queixa: «não consigo imprimir a proforma ou factura após fazer a mesma».
 *
 * DUAS METADES, DOIS MÉTODOS DE PROVA:
 *
 *   · o MOTOR (esperar pelo número, ir buscar o registo fresco, escolher o
 *     papel certo) prova-se com um stub no lugar da janela de impressão — o
 *     `window.open` sem gesto humano é bloqueado pelo Chrome e o alerta de
 *     aviso congelava o guião; no uso real o toque no botão é o gesto.
 *   · o BOTÃO prova-se com um toque verdadeiro do Playwright na lista, que
 *     conta como gesto e deixa o popup abrir — e lê-se o papel de dentro.
 *
 * Uso: node scripts/check_imprimir_documentos.mjs
 */
import { chromium } from '@playwright/test';

const BASE = 'https://soserp.vip';

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20000 });
const contexto = browser.contexts()[0];
const pagina =
    contexto.pages().find((p) => p.url().includes('soserp.vip')) || contexto.pages()[0];

// Nenhum diálogo pode congelar o guião outra vez. O beforeunload (o aviso de
// "há documentos por subir") ACEITA-SE — dispensá-lo cancela a navegação e o
// goto fica pendurado até ao timeout; os outros dispensam-se.
const dialogos = [];
pagina.on('dialog', (d) => {
    dialogos.push(d.type() + ': ' + d.message().slice(0, 60));
    (d.type() === 'beforeunload' ? d.accept() : d.dismiss()).catch(() => {});
});

const resultados = [];

function registar(passo, ok, detalhe) {
    resultados.push({ passo, ok, detalhe });
    console.log(`${ok ? 'OK   ' : 'FALHA'} │ ${String(passo).padEnd(44)} │ ${detalhe ?? ''}`);
}

async function passo(nome, fn) {
    try {
        const r = await fn();
        registar(nome, r?.ok !== false, r?.detalhe);
    } catch (e) {
        registar(nome, false, 'ERRO: ' + String(e.message).split('\n')[0].slice(0, 110));
    }
}

console.log('\nIMPRIMIR DOCUMENTOS NO PWA — ANDROID, PRODUÇÃO\n');

await pagina.goto(BASE + '/invoicing/offline/drafts', { waitUntil: 'domcontentloaded', timeout: 60000 });
await pagina.waitForTimeout(6000);

await passo('o motor novo chegou ao aparelho', async () => {
    const pronto = await pagina.evaluate(
        () => !!(window.SosPwa?.imprimirDocumento && window.PosOfflineTicket?.printDocument && window.PosOfflineTicket?.buildDocumentHtml)
    );

    return { ok: pronto, detalhe: 'imprimirDocumento + printDocument + buildDocumentHtml' };
});

const artigo = await pagina.evaluate(async () => {
    const a = await window.SosPwa.db.products.toArray();
    return a[0] ? { id: a[0].id, nome: a[0].name ?? a[0].product_name, preco: Number(a[0].price) || 1000 } : null;
});

if (!artigo) {
    registar('há artigos no aparelho', false, 'catálogo vazio');
    process.exit(1);
}

/**
 * Corre o imprimirDocumento com a janela de impressão substituída por um
 * apanhador: devolve o documento e a empresa que CHEGARAM ao papel — depois
 * da espera pelo número, com o registo fresco. É o caminho verdadeiro do
 * motor; só a última linha (abrir a janela) é que fica de fora.
 */
const imprimirCapturado = (uuid) =>
    pagina.evaluate(async (id) => {
        const original = window.PosOfflineTicket.printDocument;
        let apanhado = null;

        window.PosOfflineTicket.printDocument = (doc, company) => { apanhado = { doc, company }; };

        try {
            await window.SosPwa.imprimirDocumento(id);
        } finally {
            window.PosOfflineTicket.printDocument = original;
        }

        if (!apanhado) return null;

        return {
            html: window.PosOfflineTicket.buildDocumentHtml(apanhado.doc, apanhado.company),
            synced: apanhado.doc._synced === 1,
            numero: apanhado.doc._server_number || null,
        };
    }, uuid);

// ── 1. A proforma ────────────────────────────────────────────────────
await passo('a proforma imprime e o papel diz o que é', async () => {
    const uuid = await pagina.evaluate(async (art) => {
        const r = await window.SosPwa.createDraftOffline({
            doc_type: 'proforma',
            client_name: 'Cliente do Ensaio',
            items: [{ product_id: art.id, product_name: art.nome, quantity: 2, unit_price: art.preco, tax_rate: 14, discount_percent: 0 }],
            notes: 'guião de impressão',
        });
        return r.local_uuid;
    }, artigo);

    const papel = await imprimirCapturado(uuid);

    if (!papel) return { ok: false, detalhe: 'nada chegou ao papel' };

    const ok = papel.html.includes('FACTURA PROFORMA')
        && papel.html.includes('NÃO SERVE DE FACTURA')
        && !papel.html.includes('DOCUMENTO PROVISÓRIO');

    return { ok, detalhe: 'título + aviso permanente, sem faixa de provisório' };
});

// ── 2. A factura com rede: espera e sai com o número ─────────────────
await passo('a factura com rede sai com o número fiscal', async () => {
    const uuid = await pagina.evaluate(async (art) => {
        const r = await window.SosPwa.createDraftOffline({
            doc_type: 'FT',
            client_name: 'Cliente do Ensaio',
            items: [{ product_id: art.id, product_name: art.nome, quantity: 1, unit_price: art.preco, tax_rate: 14, discount_percent: 0 }],
        });
        return r.local_uuid;
    }, artigo);

    const papel = await imprimirCapturado(uuid);

    if (!papel) return { ok: false, detalhe: 'nada chegou ao papel' };

    if (papel.synced && papel.numero) {
        const ok = papel.html.includes(papel.numero) && !papel.html.includes('DOCUMENTO PROVISÓRIO');

        return { ok, detalhe: `número no papel: ${papel.numero}` };
    }

    // Sem sincronizar a tempo, o papel tem de ser honesto.
    return {
        ok: papel.html.includes('DOCUMENTO PROVISÓRIO'),
        detalhe: 'não sincronizou no prazo — saiu marcado como provisório',
    };
});

// ── 3. O papel offline puro ──────────────────────────────────────────
await passo('sem número, o papel grita PROVISÓRIO', async () => {
    const html = await pagina.evaluate((art) =>
        window.PosOfflineTicket.buildDocumentHtml({
            local_uuid: 'd_ensaio_offline',
            doc_type: 'FT',
            client_name: 'Cliente Offline',
            items: [{ product_name: art.nome, quantity: 1, unit_price: art.preco, tax_rate: 14 }],
            subtotal: art.preco, tax: art.preco * 0.14, total: art.preco * 1.14,
            _synced: 0, _server_number: null,
            created_at: new Date().toISOString(),
        }, { name: 'Empresa do Ensaio', nif: '5000000000' }), artigo);

    const ok = html.includes('DOCUMENTO PROVISÓRIO')
        && html.includes('A numeração fiscal é atribuída na sincronização')
        && html.includes('REF. LOCAL:');

    return { ok, detalhe: 'faixa + referência local' };
});

// ── 4. O TOQUE verdadeiro no botão abre o papel ──────────────────────
await passo('o toque no botão da lista abre o papel', async () => {
    await pagina.reload({ waitUntil: 'domcontentloaded', timeout: 60000 });
    await pagina.waitForTimeout(4000);

    const botao = pagina.locator('button', { hasText: 'Imprimir' }).first();

    if (!(await botao.count())) return { ok: false, detalhe: 'sem botão na lista' };

    const popup = contexto.waitForEvent('page', { timeout: 20000 });

    await botao.click();

    const janela = await popup;
    await janela.waitForLoadState('domcontentloaded').catch(() => {});
    await janela.waitForTimeout(1500);

    const texto = await janela.evaluate(() => document.body.innerText).catch(() => '');
    await janela.close().catch(() => {});

    const ok = /FACTURA|NOTA DE CRÉDITO/.test(texto) && texto.includes('TOTAL');

    return { ok, detalhe: ok ? 'janela abriu com o documento' : 'janela sem conteúdo: ' + texto.slice(0, 60) };
});

if (dialogos.length) {
    console.log('\ndiálogos apanhados:', dialogos.map((d) => d.slice(0, 60)));
}

const falhas = resultados.filter((r) => !r.ok);

console.log('\n─────────────────────────────────────────');
console.log(`${resultados.length - falhas.length}/${resultados.length} passos bem.`);

process.exit(falhas.length ? 1 : 0);
