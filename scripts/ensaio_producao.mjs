/**
 * O PWA de PRODUÇÃO num Android, com uma equipa de cinco.
 *
 * Aponta a https://soserp.vip — certificado a sério, portanto contexto seguro
 * sem túnel nenhum. É deliberadamente o oposto da bancada local: aqui não há
 * `adb reverse` a mascarar nada, e o que falhar falha como falharia no
 * telemóvel de um empregado.
 *
 * O que se mede, por esta ordem:
 *
 *   1. o aparelho entra, sincroniza e recebe a equipa toda;
 *   2. vende com rede;
 *   3. vende SEM rede e a venda fica na fila;
 *   4. o PIN de CADA UM dos cinco abre — e o de um não abre a conta de outro;
 *   5. a rede volta e tudo sobe assinado.
 *
 * Uso: node scripts/ensaio_producao.mjs <passo>
 */
import { chromium } from '@playwright/test';

const BASE = 'https://soserp.vip';

// As credenciais vêm do ambiente, nunca escritas aqui: este ficheiro vai para
// o repositório e as senhas de produção não vão.
const EQUIPA = JSON.parse(process.env.EQUIPA || '[]');

const passo = process.argv[2] || 'estado';

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20000 });
const contexto = browser.contexts()[0];
const pagina = contexto.pages().find((p) => p.url().includes('soserp.vip')) || contexto.pages()[0];

const diz = (r, v) => console.log(String(r).padEnd(34), typeof v === 'object' ? JSON.stringify(v) : v);

const esperarMotor = () => pagina.waitForFunction(
    () => window.SosPwa && window.SosPwa.db && window.SosPwa.db.isOpen(),
    null,
    { timeout: 45000 }
);

const sincronizar = async () => {
    await pagina.evaluate(() => window.SosPwa.sync(true));
    await pagina.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 });
};

async function estado() {
    diz('url', pagina.url());

    const info = await pagina.evaluate(async () => {
        const r = {
            seguro: window.isSecureContext,
            sw: !!navigator.serviceWorker?.controller,
            motor: typeof window.SosPwa,
        };

        if (window.SosPwa?.db?.isOpen()) {
            r.empresa = (await window.SosPwa.db.meta.get('tenant_id'))?.value;
            r.utilizador = (await window.SosPwa.db.meta.get('user'))?.value?.email;
            r.produtos = await window.SosPwa.db.products.count();
            r.funcionarios = await window.SosPwa.db.employees.count();
            r.fila = await window.SosPwa.db.sync_queue.where('status').equals('pending').count();
            r.vendas = await window.SosPwa.db.pos_sales.count();
            r.turno = (await window.SosPwa.db.meta.get('shift'))?.value?.open ?? false;
        }

        return r;
    });

    for (const [k, v] of Object.entries(info)) diz(k, v);
}

async function entrar() {
    const quem = EQUIPA[0];
    if (!quem) { console.log('sem EQUIPA no ambiente'); return; }

    await pagina.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    await pagina.fill('input[name="email"]', quem.email);
    await pagina.fill('input[name="password"]', quem.senha);
    await pagina.click('button[type="submit"]');
    await pagina.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 45000 });

    await pagina.goto(`${BASE}/invoicing/offline/pos`, { waitUntil: 'domcontentloaded' });
    await esperarMotor();
    await sincronizar();

    diz('entrou como', quem.email);
    await estado();
}

/** Abre turno se não houver, e confirma que subiu ao servidor. */
async function turno() {
    const aberto = await pagina.evaluate(async () => {
        const t = (await window.SosPwa.db.meta.get('shift'))?.value;
        return !!(t?.open && !t._local);
    });

    if (aberto) { diz('turno', 'ja aberto no servidor'); return; }

    await pagina.evaluate(() => window.SosPwa.openShiftOffline({ opening_balance: 0 }));
    await sincronizar();

    diz('turno', await pagina.evaluate(async () => {
        const t = (await window.SosPwa.db.meta.get('shift'))?.value;
        return t?.open ? (t._local ? 'aberto SO local' : 'aberto no servidor: ' + t.number) : 'NAO abriu';
    }));
}

/** Uma venda, com ou sem rede. */
async function vender(nota) {
    return pagina.evaluate(async (nota) => {
        const artigos = await window.SosPwa.db.products.toArray();
        const a = artigos.find((p) => (parseFloat(p.price) || 0) > 0);
        if (!a) return { erro: 'sem artigo com preco' };

        const v = await window.SosPwa.createPosSaleOffline({
            client_name: 'Consumidor Final',
            payment_method: 'cash',
            notes: nota,
            items: [{
                product_id: a.id, product_name: a.name, quantity: 1,
                unit_price: parseFloat(a.price), tax_rate: parseFloat(a.tax_rate) || 0,
                discount_percent: 0,
            }],
        });

        return { uuid: v.local_uuid, provisorio: v.provisional_number, artigo: a.name };
    }, nota);
}

async function vendaOnline() {
    await esperarMotor();
    await turno();

    const v = await vender('ensaio producao online');
    diz('venda criada', v);

    await sincronizar();

    const depois = await pagina.evaluate(async (u) => {
        const t = await window.SosPwa.db.pos_sales.toArray();
        const s = t.find((x) => x.local_uuid === u);
        return s ? { sincronizada: s._synced === 1, numero: s._server_number, hash: s._server_hash, atcud: s._server_atcud } : null;
    }, v.uuid);

    diz('apos sincronizar', depois);
}

async function vendaOffline() {
    await esperarMotor();

    await contexto.setOffline(true);
    const v = await vender('ensaio producao offline');
    diz('venda sem rede', v);

    const naFila = await pagina.evaluate(() => window.SosPwa.db.sync_queue.where('status').equals('pending').count());
    diz('fila pendente', naFila);

    await contexto.setOffline(false);
    await sincronizar();

    const depois = await pagina.evaluate(async (u) => {
        const t = await window.SosPwa.db.pos_sales.toArray();
        const s = t.find((x) => x.local_uuid === u);
        return s ? { sincronizada: s._synced === 1, numero: s._server_number, hash: s._server_hash } : null;
    }, v.uuid);

    diz('reposta no servidor', depois);
}

/**
 * O DESCONTO EM PERCENTAGEM SEM REDE.
 *
 * Era o erro #73 da produção: o aparelho fazia a conta com 10% e o servidor
 * lia o 10 como kwanzas — «as formas de pagamento somam X mas o total é Y», e
 * a venda voltava à fila 143 vezes. Tem de subir, com o total do aparelho.
 */
async function descontoOffline() {
    await esperarMotor();

    await contexto.setOffline(true);

    const v = await pagina.evaluate(async () => {
        const artigos = await window.SosPwa.db.products.toArray();
        const a = artigos.find((p) => (parseFloat(p.price) || 0) > 0);
        if (!a) return { erro: 'sem artigo com preco' };

        const venda = await window.SosPwa.createPosSaleOffline({
            client_name: 'Consumidor Final',
            payment_method: 'cash',
            discount_commercial: 10,
            notes: 'ensaio producao desconto 10% sem rede',
            items: [{
                product_id: a.id, product_name: a.name, quantity: 3,
                unit_price: parseFloat(a.price), tax_rate: parseFloat(a.tax_rate) || 0, discount_percent: 0,
            }],
        });

        const r = await window.SosPwa.db.pos_sales.get(venda.local_uuid);

        return { uuid: venda.local_uuid, artigo: a.name, subtotal: r.subtotal, desconto: r.discount_amount, total: r.total };
    });

    diz('venda com 10% sem rede', v);

    await contexto.setOffline(false);
    await sincronizar();

    const depois = await pagina.evaluate(async (u) => {
        const s = await window.SosPwa.db.pos_sales.get(u);
        const naFila = await window.SosPwa.db.sync_queue.toArray();
        const trabalho = naFila.find((j) => JSON.stringify(j.payload || j.data || {}).includes(u));

        return {
            sincronizada: s?._synced === 1,
            numero: s?._server_number,
            fila: trabalho ? { estado: trabalho.status, erro: trabalho.last_error || trabalho.error || null } : 'saiu da fila',
        };
    }, v.uuid);

    diz('depois de subir', depois);
}

/** O ensaio que interessa: os cinco PIN, e o de um a não abrir a conta de outro. */
async function pins() {
    await esperarMotor();
    await sincronizar();

    const equipaNoAparelho = await pagina.evaluate(() => window.SosPwa.db.employees.toArray());
    diz('equipa no aparelho', equipaNoAparelho.map((e) => e.email || e.id));

    for (const quem of EQUIPA) {
        const r = await pagina.evaluate(
            ([email, pin]) => window.SosPwa.verifyOfflineAuth(email, pin),
            [quem.email, quem.pin]
        );
        diz('PIN de ' + quem.email, r.ok ? 'ACEITE' : 'RECUSADO (' + r.reason + ')');
    }

    // O PIN do primeiro na conta do segundo: tem de ser recusado.
    if (EQUIPA.length >= 2) {
        const cruzado = await pagina.evaluate(
            ([email, pin]) => window.SosPwa.verifyOfflineAuth(email, pin),
            [EQUIPA[1].email, EQUIPA[0].pin]
        );
        diz('PIN trocado (deve recusar)', cruzado.ok ? '*** ACEITOU — DEFEITO ***' : 'recusado (' + cruzado.reason + ')');
    }
}

/**
 * O ensaio a sério: a aplicação fechada, o modo avião ligado, e o operador a
 * chegar ao balcão. É o único que prova que o PWA vale alguma coisa.
 *
 * O `setOffline` do Playwright corta ao nível do browser. O modo avião corta
 * ao nível do sistema — que é o que acontece a quem está a vender.
 */
async function offlineReal() {
    await esperarMotor();

    diz('antes do avião', await pagina.evaluate(async () => ({
        produtos: await window.SosPwa.db.products.count(),
        equipa: await window.SosPwa.db.employees.count(),
        online: navigator.onLine,
    })));

    // Separador NOVO: o sessionStorage morre, como quando se fecha a app.
    const nova = await contexto.newPage();
    await nova.goto(`${BASE}/invoicing/offline/pos`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await nova.waitForTimeout(3500);

    const r = await nova.evaluate(async () => {
        const out = { online: navigator.onLine, motor: typeof window.SosPwa, titulo: document.title };

        if (window.SosPwa?.db?.isOpen()) {
            out.produtos = await window.SosPwa.db.products.count();
            out.equipa = await window.SosPwa.db.employees.count();
            out.destrancado = window.SosPwa.isPwaUnlocked();
            out.estado = await window.SosPwa.estadoDaLigacao();
        }

        out.artigosNoEcra = document.querySelectorAll('.grid button').length;

        return out;
    }).catch((e) => ({ erro: e.message.slice(0, 140) }));

    for (const [k, v] of Object.entries(r)) diz('sem rede · ' + k, v);

    if (EQUIPA[2]) {
        const pin = await nova.evaluate(
            ([email, codigo]) => window.SosPwa.verifyOfflineAuth(email, codigo),
            [EQUIPA[2].email, EQUIPA[2].pin]
        ).catch((e) => ({ ok: false, reason: e.message.slice(0, 60) }));

        diz('PIN sem rede · ' + EQUIPA[2].email, pin.ok ? 'ACEITE' : 'RECUSADO — ' + pin.reason);
    }

    const venda = await nova.evaluate(async () => {
        const artigos = await window.SosPwa.db.products.toArray();
        const a = artigos.find((p) => (parseFloat(p.price) || 0) > 0);

        if (!a) { return { erro: 'sem artigo no catálogo local' }; }

        const v = await window.SosPwa.createPosSaleOffline({
            client_name: 'Consumidor Final',
            payment_method: 'cash',
            notes: 'venda em modo avião',
            items: [{
                product_id: a.id, product_name: a.name, quantity: 2,
                unit_price: parseFloat(a.price), tax_rate: parseFloat(a.tax_rate) || 0, discount_percent: 0,
            }],
        });

        return { uuid: v.local_uuid, provisorio: v.provisional_number };
    }).catch((e) => ({ erro: e.message.slice(0, 140) }));

    diz('venda em modo avião', venda);

    await nova.close().catch(() => {});
}

const passos = { estado, entrar, turno, vendaOnline, vendaOffline, descontoOffline, pins, offlineReal };

if (!passos[passo]) {
    console.log('passos: ' + Object.keys(passos).join(', '));
} else {
    await passos[passo]();
}

await browser.close();
