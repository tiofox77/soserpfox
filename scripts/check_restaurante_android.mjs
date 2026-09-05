/**
 * O restaurante do PWA, num Android, contra produção.
 *
 * Cinco perguntas, e só isso — cada uma é um sítio onde já se partiu alguma
 * coisa:
 *
 *   1. as MESAS descem para o aparelho?
 *   2. o TURNO manda no ecrã (sem turno não se abre comanda)?
 *   3. no PWA a venda é RÁPIDA, sem cozinha?
 *   4. o menu de pratos chega e dá para pôr na comanda?
 *   5. o que fica por sincronizar é contado e não se perde?
 *
 * Uso: node scripts/check_restaurante_android.mjs
 */
import { chromium } from '@playwright/test';

const BASE = 'https://soserp.vip';

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20000 });
const contexto = browser.contexts()[0];
const pagina =
    contexto.pages().find((p) => p.url().includes('soserp.vip')) || contexto.pages()[0];

const resultados = [];

function registar(pergunta, ok, detalhe) {
    resultados.push({ pergunta, ok, detalhe });
    console.log(`${ok ? 'OK   ' : 'FALHA'} │ ${String(pergunta).padEnd(46)} │ ${detalhe ?? ''}`);
}

async function passo(pergunta, fn) {
    try {
        const r = await fn();
        registar(pergunta, r?.ok !== false, r?.detalhe);
    } catch (e) {
        registar(pergunta, false, 'ERRO: ' + String(e.message).split('\n')[0].slice(0, 110));
    }
}

console.log('\nRESTAURANTE NO PWA — ANDROID, PRODUÇÃO');
console.log('URL de partida:', pagina.url(), '\n');

await pagina.goto(BASE + '/invoicing/offline/restaurant', {
    waitUntil: 'domcontentloaded',
    timeout: 60000,
});
await pagina.waitForTimeout(6000);

const dados = () =>
    pagina.evaluate(() => {
        const raiz = document.querySelector('[x-data]');
        return raiz && window.Alpine ? window.Alpine.$data(raiz) : null;
    });

await passo('o ecrã do restaurante abre no aparelho', async () => {
    const titulo = await pagina.title();
    const temAlpine = await pagina.evaluate(() => !!document.querySelector('[x-data]'));

    return { ok: temAlpine, detalhe: titulo.slice(0, 60) };
});

await passo('as mesas descem para o aparelho', async () => {
    const d = await dados();
    const mesas = d?.mesas?.length ?? 0;
    const zonas = d?.zonas?.length ?? 0;

    return { ok: mesas > 0, detalhe: `${mesas} mesas, ${zonas} zonas` };
});

await passo('o turno manda no ecrã', async () => {
    const d = await dados();
    const turno = d?.turno ?? {};

    // O botão do balcão fica desactivado sem turno: é a regra do servidor
    // repetida no ecrã, para o empregado não descobrir tarde.
    const balcaoDesactivado = await pagina.evaluate(() => {
        const b = [...document.querySelectorAll('button')].find((x) =>
            /balc[ãa]o/i.test(x.textContent || '')
        );
        return b ? b.disabled : null;
    });

    const coerente = turno.open ? balcaoDesactivado === false : balcaoDesactivado === true;

    return {
        ok: coerente,
        detalhe: `turno ${turno.open ? 'aberto (' + (turno.number ?? '?') + ')' : 'fechado'}, balcão ${balcaoDesactivado ? 'travado' : 'livre'}`,
    };
});

await passo('no PWA a venda é rápida, sem cozinha', async () => {
    const d = await dados();
    const def = d?.definicoes ?? {};

    return {
        ok: def.use_kitchen_workflow === false,
        detalhe: `cozinha no PWA: ${def.use_kitchen_workflow}, online: ${def.use_kitchen_workflow_online}`,
    };
});

await passo('o botão diz «confirmar», não «enviar à cozinha»', async () => {
    const texto = await pagina.evaluate(() => document.body.innerText);
    const temCozinha = /Enviar à cozinha/i.test(texto);

    return {
        ok: !temCozinha,
        detalhe: temCozinha ? 'ainda diz "Enviar à cozinha"' : 'sem circuito de cozinha no ecrã',
    };
});

await passo('o catálogo de pratos chegou', async () => {
    const n = await pagina.evaluate(async () => {
        if (!window.SosPwa) return -1;
        const db = window.SosPwa.db;
        return db ? await db.products.count() : -1;
    });

    return { ok: n > 0, detalhe: `${n} artigos em IndexedDB` };
});

await passo('nada fica por sincronizar em silêncio', async () => {
    const pendentes = await pagina.evaluate(async () => {
        if (!window.SosPwa?.db) return -1;
        return await window.SosPwa.db.sync_queue.where('status').notEqual('done').count();
    });

    return {
        ok: pendentes >= 0,
        detalhe: pendentes === 0 ? 'fila vazia' : `${pendentes} por subir (contados, não perdidos)`,
    };
});

/**
 * A versão que o aparelho corre contra a que o servidor serve.
 *
 * SEGUNDA LEITURA DE PROPÓSITO. O `buildVersion()` guarda-se em cache por 60
 * segundos: nos instantes a seguir a um deploy, a página e o `sw.js` podem ser
 * servidos de lados diferentes dessa janela e dizerem números diferentes sem
 * nada estar mal. Uma verificação que grita nesse minuto ensina a ignorá-la —
 * e no dia em que gritar a sério ninguém liga.
 */
await passo('a versão do aparelho é a do servidor', async () => {
    const noAparelho = () =>
        pagina.evaluate(() => {
            const s = [...document.querySelectorAll('script[src]')].find((x) =>
                x.src.includes('pwa-invoicing.js')
            );
            return s ? new URL(s.src).searchParams.get('v') : null;
        });

    const noServidor = () =>
        pagina.evaluate(async (base) => {
            const r = await fetch(base + '/sw.js?nocache=' + Math.random(), { cache: 'no-store' });
            const t = await r.text();
            return (t.match(/CACHE_VERSION = 'soserp-([^']+)'/) || [])[1] ?? null;
        }, BASE);

    let aparelho = await noAparelho();
    let servidor = await noServidor();

    if (aparelho !== servidor) {
        // Deixar a janela da cache fechar-se e voltar a perguntar.
        await pagina.waitForTimeout(65000);
        await pagina.reload({ waitUntil: 'domcontentloaded', timeout: 60000 });
        await pagina.waitForTimeout(3000);

        aparelho = await noAparelho();
        servidor = await noServidor();
    }

    return { ok: aparelho === servidor, detalhe: `${aparelho} vs ${servidor}` };
});

const falhas = resultados.filter((r) => !r.ok);

console.log('\n─────────────────────────────────────────');
console.log(`${resultados.length - falhas.length}/${resultados.length} perguntas respondidas bem.`);

if (falhas.length) {
    console.log('\nO que ficou por resolver:');
    falhas.forEach((f) => console.log('  ·', f.pergunta, '—', f.detalhe));
}

process.exit(falhas.length ? 1 : 0);
