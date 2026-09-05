/**
 * O PWA num Android a sério, conduzido pelo Chrome do emulador.
 *
 * O `adb input` não serve para isto: um toque que caia dois pixels ao lado
 * escreve a palavra-passe no campo do email, e passa-se a depurar o teclado em
 * vez do produto. Ligamo-nos ao Chrome do aparelho por depuração remota
 * (`adb forward` + CDP) e conduzimo-lo como se fosse um browser local — mas é
 * mesmo o Chrome do Android, com o seu service worker e a sua cache.
 *
 * O servidor local chega ao aparelho por `adb reverse` como `localhost:8321`,
 * e `localhost` É contexto seguro — sem isso o Android nunca regista um
 * service worker e não há PWA nenhum para testar.
 *
 * Uso:  node scripts/pwa_android.mjs <passo>
 */
import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8321';
const EMAIL = 'bancada@pwa.local';
const PASSWORD = 'bancada-pwa-2026';
const PIN = '4321';

const passo = process.argv[2] || 'estado';

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222');
const contexto = browser.contexts()[0];
const pagina = contexto.pages().find((p) => p.url().includes('localhost:8321')) || contexto.pages()[0];

const diz = (rotulo, valor) => console.log(rotulo.padEnd(30), typeof valor === 'object' ? JSON.stringify(valor) : valor);

async function estado() {
    diz('url', pagina.url());
    diz('titulo', await pagina.title());

    const info = await pagina.evaluate(async () => {
        const r = {
            seguro: window.isSecureContext,
            sw: typeof navigator.serviceWorker !== 'undefined',
            controlador: !!navigator.serviceWorker?.controller,
            motor: typeof window.SosPwa,
        };

        if (window.SosPwa) {
            r.estadoDaLigacao = await window.SosPwa.estadoDaLigacao();
            r.destrancado = window.SosPwa.isPwaUnlocked();
            r.produtos = await window.SosPwa.db.products.count();
            r.funcionarios = await window.SosPwa.db.employees.count();
            r.fila = await window.SosPwa.db.sync_queue.where('status').equals('pending').count();
        }

        try {
            const c = await caches.open('dynamic-paginas');
            r.paginasGuardadas = (await c.keys()).map((k) => new URL(k.url).pathname);
        } catch (e) { r.paginasGuardadas = 'erro: ' + e.message; }

        return r;
    });

    for (const [k, v] of Object.entries(info)) diz(k, v);
}

async function entrar() {
    await pagina.goto(`${BASE}/invoicing/offline/login`, { waitUntil: 'domcontentloaded' });
    await pagina.waitForTimeout(2000);

    await pagina.fill('input[name="email"]', EMAIL);
    await pagina.fill('input[name="password"]', PASSWORD);
    await pagina.click('button[type="submit"]');
    await pagina.waitForTimeout(6000);

    diz('depois de entrar', pagina.url());
}

async function preparar() {
    // Sincronizar e passar pelo POS: e o que enche a cache e traz os PINs.
    await pagina.goto(`${BASE}/invoicing/offline`, { waitUntil: 'domcontentloaded' });
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 60000 });
    await pagina.evaluate(() => window.SosPwa.sync(true));
    await pagina.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 });

    await pagina.goto(`${BASE}/invoicing/offline/pos`, { waitUntil: 'domcontentloaded' });
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 60000 });
    await pagina.waitForTimeout(3000);

    await estado();
}

async function pin() {
    const r = await pagina.evaluate(async ([email, pin]) => {
        const res = await window.SosPwa.verifyOfflineAuth(email, pin);

        return { ok: res.ok, motivo: res.reason || null, destrancado: window.SosPwa.isPwaUnlocked() };
    }, [EMAIL, PIN]);

    diz('PIN', r);
}

/**
 * A QUEIXA, PASSO A PASSO: fechar a aplicação, cortar a rede, voltar a abrir,
 * pôr o PIN — e ter de aterrar no POS.
 *
 * Fechar o separador apaga o `sessionStorage`, que é onde vive o desbloqueio.
 * É por isso que volta a pedir o PIN, e isso está certo. O que se mede aqui é
 * o que acontece A SEGUIR.
 */
async function reabrir() {
    diz('1. antes de fechar', pagina.url());

    await pagina.close();

    const nova = await contexto.newPage();
    diz('2. aplicação fechada e reaberta', 'separador novo (sessionStorage vazio)');

    await nova.goto(`${BASE}/invoicing/offline/pos`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await nova.waitForTimeout(3000);

    const aoAbrir = await nova.evaluate(async () => ({
        titulo: document.title,
        caminho: location.pathname,
        semLigacao: document.body.innerText.includes('Sem Conexão'),
        pedePin: !!document.querySelector('#pwa-offline-login-password')?.offsetParent,
        destrancado: window.SosPwa?.isPwaUnlocked?.() ?? null,
        estado: window.SosPwa ? await window.SosPwa.estadoDaLigacao() : null,
    }));

    diz('3. ao reabrir', aoAbrir);

    const doPin = await nova.evaluate(async ([email, pin]) => {
        const r = await window.SosPwa.verifyOfflineAuth(email, pin);

        return { ok: r.ok, motivo: r.reason || null, destrancado: window.SosPwa.isPwaUnlocked() };
    }, [EMAIL, PIN]);

    diz('4. PIN', doPin);

    // O que faltava: chegar a algum lado depois do PIN.
    await nova.goto(`${BASE}/invoicing/offline/pos`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await nova.waitForTimeout(3000);

    const depois = await nova.evaluate(async () => ({
        titulo: document.title,
        semLigacao: document.body.innerText.includes('Sem Conexão'),
        aindaPedePin: !!document.querySelector('#pwa-offline-login-password')?.offsetParent,
        artigosNaGrelha: document.querySelectorAll('.grid button').length,
        produtos: window.SosPwa ? await window.SosPwa.db.products.count() : null,
    }));

    diz('5. depois do PIN', depois);
    diz('VEREDICTO', depois.artigosNaGrelha > 0 && !depois.aindaPedePin ? 'ENTROU NO POS' : 'NAO ENTROU');
}

/**
 * ESQUECI O PIN, SEM REDE. O caixa esqueceu o PIN; o gestor está ao lado e
 * autoriza um novo com o SEU PIN. Conduz-se a página verdadeira, com toques
 * do Playwright, e mede-se o que interessa: o caixa entra com o PIN novo.
 *
 * Corre-se com a rede cortada a sério (`adb reverse --remove` + modo avião):
 * a página tem de vir do cache e o bcrypt tem de correr no próprio aparelho.
 */
const EMAIL_CAIXA = 'caixa@pwa.local';
const PIN_CAIXA = '7391';
const PIN_NOVO = '8642';

async function pinEsquecido() {
    const url = `${BASE}/invoicing/offline/pin-esquecido?voltar=/invoicing/offline/login`;
    if (pagina.url() !== url) {
        await pagina.goto(url, { waitUntil: 'domcontentloaded' }).catch(() => {});
    }
    await pagina.waitForTimeout(2500);

    diz('1. página', { url: pagina.url(), titulo: await pagina.title() });

    const antes = await pagina.evaluate(async ([email, pin]) => {
        const r = await window.SosPwa.verifyOfflineAuth(email, pin);
        try { sessionStorage.removeItem('pwa_unlocked'); } catch (_) {}

        return { ok: r.ok, motivo: r.reason || null };
    }, [EMAIL_CAIXA, PIN_NOVO]);
    diz('2. PIN novo ANTES de repor', antes);

    await pagina.waitForSelector('form input[type="email"]', { timeout: 20000 });
    const campos = pagina.locator('form input');
    await campos.nth(0).fill(EMAIL_CAIXA);
    await campos.nth(1).fill(EMAIL);
    await campos.nth(2).fill(PIN);
    await campos.nth(3).fill(PIN_NOVO);
    await campos.nth(4).fill(PIN_NOVO);
    await pagina.click('form button[type="submit"]');

    // O bcrypt de custo 12 num telemóvel leva os seus segundos.
    await pagina.waitForFunction(
        () => /PIN reposto|não conferem|não pode|Escolha|Espere|não foi possível/i.test(document.body.innerText),
        null,
        { timeout: 30000 }
    ).catch(() => {});

    const texto = await pagina.evaluate(() => document.body.innerText);
    diz('3. resultado no ecrã', texto.includes('PIN reposto') ? 'PIN REPOSTO' : texto.replace(/\s+/g, ' ').slice(0, 220));

    const depois = await pagina.evaluate(async ([email, pinNovo, pinAntigo]) => {
        const novo = await window.SosPwa.verifyOfflineAuth(email, pinNovo);
        try { sessionStorage.removeItem('pwa_unlocked'); } catch (_) {}
        const antigo = await window.SosPwa.verifyOfflineAuth(email, pinAntigo);
        try { sessionStorage.removeItem('pwa_unlocked'); } catch (_) {}
        const fila = await window.SosPwa.db.sync_queue.filter((j) => j.op === 'repor_pin').toArray();

        return {
            entraComONovo: novo.ok,
            entraComOAntigo: antigo.ok,
            fila: fila.map((j) => ({ status: j.status, erro: j.last_error || null })),
        };
    }, [EMAIL_CAIXA, PIN_NOVO, PIN_CAIXA]);
    diz('4. depois de repor', depois);
    diz('VEREDICTO', depois.entraComONovo && !depois.entraComOAntigo ? 'O CAIXA ENTRA COM O PIN NOVO' : 'FALHOU');
}

/** A fila depois de a rede voltar: o servidor aceitou a reposição? */
async function fila() {
    await pagina.goto(`${BASE}/invoicing/offline`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 60000 });
    await pagina.evaluate(() => window.SosPwa.sync(true));
    await pagina.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 });
    // Segunda passagem: a primeira subiu a fila, esta traz a lista dos
    // funcionários já com o verificador que o servidor guardou.
    await pagina.evaluate(() => window.SosPwa.sync(true));
    await pagina.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 });

    const r = await pagina.evaluate(async ([email, pinNovo]) => {
        const fila = await window.SosPwa.db.sync_queue.filter((j) => j.op === 'repor_pin').toArray();
        const emp = await window.SosPwa.db.employees.get(email);
        const confere = emp?.pin_hash ? await window.SosPwa._bcryptCompare(pinNovo, emp.pin_hash) : null;

        return {
            fila: fila.map((j) => ({ status: j.status, erro: j.last_error || null })),
            verificadorDoServidorConfereComONovo: confere,
        };
    }, [EMAIL_CAIXA, PIN_NOVO]);

    for (const [k, v] of Object.entries(r)) diz(k, v);
}

/**
 * A QUEIXA DO CLIENTE OFFLINE, PASSO A PASSO, SEM REDE:
 *
 *   1. um cliente novo pelo formulário (com NIF, que o formulário exige);
 *   2. uma FACTURA pelo formulário de documentos, com esse cliente;
 *   3. um cliente rápido do POS SEM NIF e uma venda com ele;
 *   4. a lista de documentos mostra os dois, em nome dos clientes.
 *
 * Depois, com rede, o passo `filaDocumentos` mede se subiram e em nome de quem.
 */
const ANDROID_CARIMBO = Date.now().toString(36);

async function clienteEDocumento() {
    const nomeCliente = 'Cliente Android ' + ANDROID_CARIMBO;
    const nomeFregues = 'Freguês Android ' + ANDROID_CARIMBO;
    const nif = '5' + String(Date.now()).slice(-9);

    // 1) O cliente, pelo formulário.
    await pagina.goto(`${BASE}/invoicing/offline/clients/new`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 30000 });
    await pagina.waitForSelector('input[x-model="form.name"]', { timeout: 20000 });
    await pagina.fill('input[x-model="form.name"]', nomeCliente);
    await pagina.fill('input[x-model="form.nif"]', nif);
    await pagina.fill('input[x-model="form.phone"]', '923000123');
    // Submete-se o formulário como o toque faria — mas sem o toque: num ecrã
    // de telemóvel o botão fica debaixo da barra fixa do fundo e o Playwright
    // recusa-se a tocar em cima de outro elemento. O requestSubmit corre a
    // validação nativa (o NIF é `required`) e o @submit.prevent="save()".
    // O formulário do CLIENTE — não o primeiro da página, que é o de «Sair».
    await pagina.$eval('input[x-model="form.name"]', (el) => el.form.requestSubmit());
    await pagina.waitForTimeout(2500);
    diz('1. cliente guardado', { url: pagina.url(), aparece: (await pagina.evaluate(() => document.body.innerText)).includes(nomeCliente) });

    // 2) A factura, pelo formulário de documentos, com esse cliente.
    await pagina.goto(`${BASE}/invoicing/offline/drafts/new`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 30000 });
    await pagina.waitForSelector('button:has-text("Selecionar cliente")', { timeout: 20000 });
    // Os toques pelo próprio elemento (`el.click()`): no telemóvel os botões
    // ficam debaixo de barras fixas e o toque por coordenadas cai ao lado.
    const tocar = async (selector) => {
        await pagina.waitForSelector(selector, { timeout: 15000 });
        await pagina.$eval(selector, (el) => el.click());
        await pagina.waitForTimeout(400);
    };
    await tocar('button:has-text("Fatura"):not(:has-text("Recibo"))');
    await tocar('button:has-text("Selecionar cliente")');
    await pagina.fill('input[x-model="clientSearch"]', nomeCliente);
    await tocar(`[x-show="showClientPicker"] button[type="button"]:has-text("${nomeCliente}")`);
    await tocar('button:has-text("Adicionar")');
    await pagina.waitForSelector('input[x-model="productSearch"]', { timeout: 10000 });
    await tocar('[x-show="showProductPicker"] button[type="button"]');
    await tocar('button:has-text("Emitir Documento")');
    await pagina.waitForTimeout(1500);
    diz('2. factura guardada', (await pagina.evaluate(() => document.body.innerText)).includes('Documento guardado') ? 'sim' : 'NÃO');

    // 3) O cliente rápido do POS, sem NIF, e uma venda com ele.
    await pagina.goto(`${BASE}/invoicing/offline/pos`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 30000 });
    const venda = await pagina.evaluate(async (n) => {
        const cliente = await window.SosPwa.createClientOffline({ name: n, type: 'pessoa_fisica', nif: null, mobile: null, country: 'Angola', tax_regime: 'geral', is_iva_subject: false });
        const artigo = await window.SosPwa.db.products.toCollection().first();
        const v = await window.SosPwa.createPosSaleOffline({
            client_id: null, client_local_uuid: cliente.local_uuid, client_name: n,
            payment_method: 'cash', amount_received: Number(artigo.price),
            items: [{ product_id: artigo.id, product_name: artigo.name, quantity: 1, unit_price: Number(artigo.price), tax_rate: Number(artigo.tax_rate || 0) }],
        });
        return { local_uuid: v.local_uuid };
    }, nomeFregues);
    diz('3. venda do POS sem NIF', venda);

    // 4) A lista de documentos.
    await pagina.goto(`${BASE}/invoicing/offline/drafts`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 30000 });
    await pagina.waitForTimeout(1500);
    const lista = await pagina.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));
    diz('4. lista mostra a factura em nome do cliente', lista.includes(nomeCliente) ? 'SIM' : 'NÃO');

    const fila = await pagina.evaluate(() => window.SosPwa.db.sync_queue.where('status').equals('pending').count());
    diz('fila por subir', fila);
    diz('CARIMBO', ANDROID_CARIMBO);
}

/** Com rede: os documentos sobem em nome dos clientes? */
async function filaDocumentos() {
    await pagina.goto(`${BASE}/invoicing/offline`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 60000 });
    for (let i = 0; i < 3; i++) {
        await pagina.evaluate(() => window.SosPwa.sync(true));
        await pagina.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 });
    }

    const r = await pagina.evaluate(async () => {
        const fila = (await window.SosPwa.db.sync_queue.toArray())
            .filter((j) => ['create_client', 'create_draft', 'create_pos_sale'].includes(j.op))
            .slice(-6)
            .map((j) => ({ op: j.op, status: j.status, erro: (j.last_error || '').slice(0, 90) || null }));
        const docs = (await window.SosPwa.db.draft_documents.orderBy('created_at').reverse().limit(2).toArray())
            .map((d) => ({ cliente: d.client_name, sincronizado: d._synced, numero: d._server_number, id: d._server_id }));
        const vendas = (await window.SosPwa.db.pos_sales.orderBy('created_at').reverse().limit(1).toArray())
            .map((v) => ({ cliente: v.client_name, sincronizado: v._synced, numero: v._server_number, id: v._server_id }));
        return { fila, docs, vendas };
    });

    for (const [k, v] of Object.entries(r)) diz(k, v);
}

/**
 * A PROFORMA pelo formulário, e a IMPRESSÃO sem rede — dos dois documentos.
 *
 * O diálogo de impressão nativo do Android congela o renderer, e um
 * `window.open` sem gesto é bloqueado. Por isso não se deixa a impressão
 * chegar ao papel: substitui-se o `printDocument` por um que guarda o HTML
 * que ia para a impressora, e é ESSE HTML que se mede — o desenho A4 do
 * documento, com o nome do cliente e a faixa de provisório.
 */
async function proformaEImpressao() {
    await pagina.goto(`${BASE}/invoicing/offline/drafts/new`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 30000 });

    const cliente = await pagina.evaluate(async () => {
        const todos = await window.SosPwa.db.clients.toArray();
        return todos.filter((c) => (c.name || '').startsWith('Cliente Android')).sort((a, b) => String(b.created_offline_at || '').localeCompare(String(a.created_offline_at || '')))[0]?.name || null;
    });
    diz('cliente usado', cliente);

    const tocar = async (selector) => {
        await pagina.waitForSelector(selector, { timeout: 15000 });
        await pagina.$eval(selector, (el) => el.click());
        await pagina.waitForTimeout(400);
    };
    await tocar('button:has-text("Proforma")');
    await tocar('button:has-text("Selecionar cliente")');
    await pagina.fill('input[x-model="clientSearch"]', cliente);
    await tocar(`[x-show="showClientPicker"] button[type="button"]:has-text("${cliente}")`);
    await tocar('button:has-text("Adicionar")');
    await pagina.waitForSelector('input[x-model="productSearch"]', { timeout: 10000 });
    await tocar('[x-show="showProductPicker"] button[type="button"]');
    await tocar('button:has-text("Emitir Documento")');
    await pagina.waitForTimeout(1500);
    diz('1. proforma guardada', (await pagina.evaluate(() => document.body.innerText)).includes('Documento guardado') ? 'sim' : 'NÃO');

    const impressoes = await pagina.evaluate(async (nome) => {
        // O papel fica num balde em vez de ir para a impressora.
        const balde = [];
        window.PosOfflineTicket.printDocument = (doc, company) => {
            balde.push({ tipo: doc.doc_type, html: window.PosOfflineTicket.buildDocumentHtml(doc, company) });
        };
        window.open = () => ({ fechado: false });

        const docs = await window.SosPwa.db.draft_documents.orderBy('created_at').reverse().toArray();
        const ft = docs.find((d) => d.doc_type === 'FT' && d.client_name === nome);
        const pf = docs.find((d) => d.doc_type === 'proforma' && d.client_name === nome);

        const r = {};
        for (const [rotulo, d] of [['factura', ft], ['proforma', pf]]) {
            if (!d) { r[rotulo] = 'documento não encontrado'; continue; }
            balde.length = 0;
            try { await window.SosPwa.imprimirDocumento(d.local_uuid); } catch (e) { r[rotulo] = 'erro: ' + e.message; continue; }
            const p = balde[0];
            r[rotulo] = !p ? 'nada foi para a impressora' : {
                bytes: p.html.length,
                comNomeDoCliente: p.html.includes(nome),
                provisorio: p.html.includes('DOCUMENTO PROVISÓRIO'),
                naoServeDeFactura: p.html.includes('NÃO SERVE DE FACTURA'),
                refLocal: p.html.includes('REF. LOCAL'),
                sincronizado: !!d._synced,
            };
        }
        return r;
    }, cliente);

    for (const [k, v] of Object.entries(impressoes)) diz('2. impressão sem rede · ' + k, v);
}

/** Com rede: sincroniza e mede o que a impressão abre — a página de PDF do servidor. */
async function impressaoComRede() {
    await pagina.goto(`${BASE}/invoicing/offline/drafts`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 60000 });
    for (let i = 0; i < 2; i++) {
        await pagina.evaluate(() => window.SosPwa.sync(true));
        await pagina.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 });
    }

    const r = await pagina.evaluate(async () => {
        const aberturas = [];
        window.open = (url) => { aberturas.push(url); return { fechado: false }; };

        const docs = await window.SosPwa.db.draft_documents.orderBy('created_at').reverse().limit(2).toArray();
        const saida = {};
        for (const d of docs) {
            aberturas.length = 0;
            try { await window.SosPwa.imprimirDocumento(d.local_uuid); } catch (e) { saida[d.doc_type] = 'erro: ' + e.message; continue; }
            const url = aberturas[0] || null;
            let pagina = null;
            if (url) {
                const resp = await fetch(url, { credentials: 'same-origin' });
                const texto = (await resp.text()).replace(/\s+/g, ' ');
                pagina = { status: resp.status, comNomeDoCliente: texto.includes(d.client_name), comNumero: !!d._server_number && texto.includes(d._server_number), consumidorFinal: texto.includes('Consumidor Final') };
            }
            saida[d.doc_type] = { numero: d._server_number, abre: url, pdfDoServidor: pagina };
        }
        return saida;
    });

    for (const [k, v] of Object.entries(r)) diz('3. impressão com rede · ' + k, v);
}

/**
 * O PDF NO APARELHO, sem rede — e a folha de partilha do Android.
 *
 * Mede-se o PDF (é um PDF, tem bytes, veio do aparelho) e se o Chrome deste
 * Android sabe partilhar ficheiros. Depois dá-se um TOQUE VERDADEIRO no
 * botão da lista de documentos: a folha de partilha é uma janela do sistema
 * que o CDP não vê, por isso fotografa-se o ecrã com o adb (ver o passo
 * seguinte da cadeia, fora deste guião).
 */
async function pdfOffline() {
    await pagina.goto(`${BASE}/invoicing/offline/drafts`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen() && window.jspdf && window.html2canvas, null, { timeout: 30000 });

    const r = await pagina.evaluate(async () => {
        const cabeca = async (b) => String.fromCharCode(...new Uint8Array(await b.slice(0, 5).arrayBuffer()));
        const out = {
            online: navigator.onLine,
            partilhaDeFicheiros: !!(navigator.canShare && navigator.canShare({ files: [new File(['x'], 'x.pdf', { type: 'application/pdf' })] })),
        };
        const venda = await window.SosPwa.db.pos_sales.orderBy('created_at').reverse().first();
        const doc = await window.SosPwa.db.draft_documents.orderBy('created_at').reverse().first();
        if (venda) { const t0 = Date.now(); const p = await window.SosPwa.pdfDe('venda', venda); out.talao = { numero: venda._server_number, origem: p.origem, nome: p.nome, bytes: p.blob.size, cabeca: await cabeca(p.blob), ms: Date.now() - t0 }; }
        if (doc) { const t0 = Date.now(); const p = await window.SosPwa.pdfDe('documento', doc); out.documento = { tipo: doc.doc_type, numero: doc._server_number, origem: p.origem, nome: p.nome, bytes: p.blob.size, cabeca: await cabeca(p.blob), ms: Date.now() - t0 }; }
        return out;
    });
    for (const [k, v] of Object.entries(r)) diz(k, v);

    // O toque verdadeiro no botão: é ele que tem licença para abrir a folha.
    const botao = pagina.locator('[data-ensaio="partilhar-pdf"]').first();
    await botao.scrollIntoViewIfNeeded().catch(() => {});
    await botao.click({ timeout: 10000 }).catch((e) => diz('toque', 'falhou: ' + e.message.slice(0, 80)));
    diz('toque no botão', 'dado — fotografar o ecrã com o adb');
}

const passos = { estado, entrar, preparar, pin, reabrir, pinEsquecido, fila, clienteEDocumento, filaDocumentos, proformaEImpressao, impressaoComRede, pdfOffline };

if (!passos[passo]) {
    console.log('passos:', Object.keys(passos).join(', '));
} else {
    await passos[passo]();
}

await browser.close();
