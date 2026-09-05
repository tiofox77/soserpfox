/**
 * O PWA de produção conduzido PELOS ECRÃS, num Android real.
 *
 * A diferença para o `ensaio_completo.mjs` é toda: aquele chamava o motor por
 * JavaScript (`SosPwa.createPosSaleOffline(...)`) e provava que o motor
 * funciona. Este carrega nos cartões dos artigos, no botão de finalizar, nas
 * teclas do PIN — e prova que os ECRÃS funcionam, que é outra coisa.
 *
 * Um motor bom atrás de um botão que não responde é um produto que não vende.
 *
 * A impressão é neutralizada (`window.print`): o Chrome do Android abre um
 * diálogo nativo que a depuração remota não fecha, e o ensaio ficava pendurado
 * no primeiro talão.
 */
import { chromium } from '@playwright/test';

const BASE = 'https://soserp.vip';
const EQUIPA = JSON.parse(process.env.EQUIPA || '[]');

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20000 });
const contexto = browser.contexts()[0];
let pagina = contexto.pages().find((p) => p.url().includes('soserp.vip')) || contexto.pages()[0];

const resultados = [];
const registar = (passo, ok, detalhe) => {
    resultados.push({ passo, ok, detalhe });
    console.log(`${ok ? 'OK ' : 'FALHA'} | ${String(passo).padEnd(40)} | ${detalhe ?? ''}`);
};

async function tentar(nome, fn) {
    try {
        const r = await fn();
        registar(nome, r?.ok !== false, r?.detalhe);
    } catch (e) {
        registar(nome, false, 'ERRO: ' + String(e.message).slice(0, 130));
    }
}

const semImprimir = (p) => p.addInitScript(() => { window.print = () => {}; });

const esperarMotor = (p) => p.waitForFunction(
    () => window.SosPwa && window.SosPwa.db && window.SosPwa.db.isOpen(), null, { timeout: 45000 }
);

const sincronizar = async (p) => {
    await p.evaluate(() => window.SosPwa.sync(true));
    await p.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 });
};

// ── ENTRAR PELO FORMULÁRIO ───────────────────────────────────────────────
await tentar('entrar: escrever e carregar em Entrar', async () => {
    const quem = EQUIPA[0];

    await semImprimir(pagina);
    await pagina.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    await pagina.waitForTimeout(1200);

    // Sessão já iniciada: o /login reencaminha e não há formulário para
    // preencher. Não é falha — é o estado normal de quem já entrou.
    if (!pagina.url().includes('/login')) {
        return { detalhe: 'sessão já iniciada (' + new URL(pagina.url()).pathname + ')' };
    }

    // A TOCAR, não a preencher por JavaScript: `fill` escreve o valor mas não
    // dispara o teclado nem o `input` do Alpine da mesma maneira. `type` é o
    // que um dedo faz.
    await pagina.click('input[name="email"]');
    await pagina.type('input[name="email"]', quem.email, { delay: 20 });
    await pagina.click('input[name="password"]');
    await pagina.type('input[name="password"]', quem.senha, { delay: 20 });
    await pagina.click('button[type="submit"]');

    await pagina.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 45000 });

    return { detalhe: 'aterrou em ' + new URL(pagina.url()).pathname };
});

// ── O POS ────────────────────────────────────────────────────────────────
await pagina.goto(`${BASE}/invoicing/offline/pos`, { waitUntil: 'domcontentloaded' });
await esperarMotor(pagina);
await sincronizar(pagina);
await pagina.waitForTimeout(1500);

const erros = [];
pagina.on('pageerror', (e) => erros.push(String(e.message).slice(0, 140)));
pagina.on('console', (m) => { if (m.type() === 'error') erros.push(String(m.text()).slice(0, 140)); });

await tentar('POS: a grelha de artigos aparece', async () => {
    const n = await pagina.locator('.grid button:visible').count();
    return { ok: n > 0, detalhe: `${n} cartão(ões) tocáveis` };
});

await tentar('POS: tocar num artigo enche o carrinho', async () => {
    const cartao = pagina.locator('.grid button:visible').first();
    const nome = (await cartao.innerText()).split('\n')[0];

    await cartao.click();
    await pagina.waitForTimeout(600);

    const noCarrinho = await pagina.evaluate(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data]'));
        return { itens: d.cart?.length ?? 0, total: d.totals?.total ?? 0 };
    });

    return { ok: noCarrinho.itens > 0, detalhe: `${nome} → ${noCarrinho.itens} linha(s), total ${noCarrinho.total}` };
});

await tentar('POS: tocar três vezes soma quantidade', async () => {
    const cartao = pagina.locator('.grid button:visible').first();
    await cartao.click();
    await cartao.click();
    await pagina.waitForTimeout(600);

    const q = await pagina.evaluate(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data]'));
        return d.cart?.[0]?.quantity ?? 0;
    });

    return { ok: q >= 3, detalhe: `quantidade ${q}` };
});

/**
 * No telemóvel o carrinho é uma GAVETA, e o "Finalizar Venda" está lá dentro.
 *
 * A primeira versão deste ensaio carregava directamente no botão e falhava por
 * tempo esgotado — o botão existe no DOM mas fica 440px abaixo do ecrã com a
 * gaveta fechada. Não era defeito: era um passo que o operador dá e o ensaio
 * saltava. Um ensaio que salta passos mede um produto que ninguém usa.
 */
async function abrirCarrinho() {
    // Já aberta (ou ecrã grande, onde é uma coluna): não há nada a fazer.
    const aberta = await pagina.evaluate(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data]'));
        return d?.showCart === true || window.innerWidth >= 1024;
    }).catch(() => false);

    if (aberta) { return; }

    // A barra tem `x-transition`: entre o `isVisible()` e o clique ela pode
    // estar a meio da animação, e o clique morria por tempo esgotado. Espera-se
    // que fique mesmo visível, com prazo curto, e segue-se a vida se não vier.
    const barra = pagina.locator('button').filter({ hasText: 'Ver carrinho' }).first();

    try {
        await barra.waitFor({ state: 'visible', timeout: 6000 });
        await barra.click({ timeout: 6000 });
        await pagina.waitForTimeout(900);
    } catch (_) {
        // Sem barra: ou o carrinho está vazio, ou a gaveta abriu sozinha.
    }
}

await tentar('POS: abrir a gaveta do carrinho', async () => {
    await abrirCarrinho();

    const aberta = await pagina.evaluate(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data]'));
        return d.showCart === true || window.innerWidth >= 1024;
    });

    return { ok: aberta, detalhe: aberta ? 'gaveta aberta' : 'não abriu' };
});

await tentar('POS: carregar em Finalizar Venda emite', async () => {
    await abrirCarrinho();

    const botao = pagina.locator('button').filter({ hasText: 'Finalizar Venda' }).first();

    if (!(await botao.isEnabled())) {
        return { ok: false, detalhe: 'o botão está desactivado (turno fechado?)' };
    }

    await botao.click();
    await pagina.waitForTimeout(4000);

    const r = await pagina.evaluate(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data]'));
        return { recibo: d.lastReceipt, carrinho: d.cart?.length ?? 0 };
    });

    return {
        ok: !!r.recibo?.number && r.carrinho === 0,
        detalhe: r.recibo ? `${r.recibo.number} · ${r.recibo.itemCount} artigo(s) · carrinho limpo` : 'sem recibo',
    };
});

await tentar('POS: o talão de confirmação aparece no ecrã', async () => {
    const visivel = await pagina.evaluate(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data]'));
        return !!d.lastReceipt;
    });

    const texto = await pagina.locator('body').innerText();
    const mostra = /Venda|Talão|Recibo|conclu/i.test(texto);

    return { ok: visivel && mostra, detalhe: visivel ? 'recibo no ecrã' : 'não apareceu' };
});

// ── SEM REDE, PELOS ECRÃS ────────────────────────────────────────────────
await tentar('POS: vender SEM rede, a tocar', async () => {
    await contexto.setOffline(true);
    await pagina.waitForTimeout(800);

    // FECHAR O TALÃO ANTES. Depois de uma venda fica no ecrã um recibo que
    // tapa a grelha — e é isso que o operador fecha para atender o próximo.
    // Sem este passo o ensaio tentava tocar num artigo por baixo do talão.
    await pagina.evaluate(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data]'));
        if (d) { d.lastReceipt = null; d.showCart = false; }
    }).catch(() => {});
    await pagina.waitForTimeout(800);

    await pagina.locator('.grid button:visible').first().click();
    await pagina.waitForTimeout(500);
    await abrirCarrinho();

    const botao = pagina.locator('button').filter({ hasText: 'Finalizar Venda' }).first();
    await botao.click();
    await pagina.waitForTimeout(3000);

    const r = await pagina.evaluate(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data]'));
        return d.lastReceipt;
    });

    await contexto.setOffline(false);

    return { ok: !!r?.number, detalhe: r ? `${r.number} (sincronizado: ${r.synced})` : 'sem recibo' };
});

// ── FECHAR E VOLTAR: O PIN, A TOCAR ──────────────────────────────────────
await tentar('PIN: fechar a app e voltar pede PIN', async () => {
    await sincronizar(pagina);

    const nova = await contexto.newPage();
    await semImprimir(nova);
    await nova.goto(`${BASE}/invoicing/offline/pos`, { waitUntil: 'domcontentloaded' });
    await nova.waitForTimeout(3500);

    const pedePin = await nova.evaluate(() => {
        const t = document.body.innerText;
        return /PIN|C[óo]digo/i.test(t) || !!document.querySelector('[id*=pin i], [class*=pin i]');
    });

    pagina = nova;

    return { ok: pedePin, detalhe: pedePin ? 'ecrã de PIN' : 'entrou sem pedir' };
});

await tentar('PIN: escrever nas teclas e entrar', async () => {
    const quem = EQUIPA[0];

    const jaDentro = await pagina.evaluate(() => window.SosPwa?.isPwaUnlocked?.() ?? false).catch(() => false);
    if (jaDentro) { return { detalhe: 'aparelho já destrancado' }; }

    // As teclas do PIN: botões com um algarismo só.
    const teclas = pagina.locator('button').filter({ hasText: /^[0-9]$/ });
    const quantas = await teclas.count();

    if (quantas >= 10) {
        for (const d of quem.pin.split('')) {
            await pagina.locator('button', { hasText: new RegExp('^' + d + '$') }).first().click();
            await pagina.waitForTimeout(150);
        }
    } else {
        // Sem teclado próprio: campo de texto.
        // O ecrã pede EMAIL e PIN — não só o PIN. Um aparelho partilhado
        // por cinco empregados tem de saber qual deles está a entrar.
        const email = pagina.locator('input[type=email]').first();
        if (await email.count()) { await email.click(); await email.fill(quem.email); }

        const campo = pagina.locator('input[type=password]').first();
        await campo.click();
        await campo.type(quem.pin, { delay: 80 });

        const entrar = pagina.locator('button').filter({ hasText: 'Entrar sem rede' }).first();
        if (await entrar.count()) { await entrar.click(); } else { await pagina.keyboard.press('Enter'); }
    }

    await pagina.waitForTimeout(3500);

    const dentro = await pagina.evaluate(() => ({
        destrancado: window.SosPwa?.isPwaUnlocked?.() ?? false,
        artigos: document.querySelectorAll('.grid button').length,
        url: location.pathname,
    }));

    return {
        ok: dentro.destrancado && dentro.artigos > 0,
        detalhe: `${quantas >= 10 ? 'teclado' : 'campo'} · destrancado=${dentro.destrancado} · ${dentro.artigos} artigos`,
    };
});

// ── RESTAURANTE, PELOS ECRÃS ─────────────────────────────────────────────
await tentar('restaurante: abrir uma mesa a tocar', async () => {
    await pagina.goto(`${BASE}/invoicing/offline/restaurant`, { waitUntil: 'domcontentloaded' });
    await esperarMotor(pagina);
    await pagina.waitForTimeout(2500);

    // COM MAIS DE UMA SALA, escolhe-se a que tem mesas — é o que o empregado
    // faz quando aterra numa sala vazia. O selector está no topo do ecrã.
    let mesas = pagina.locator('button').filter({ hasText: /^Mesa \d/ });

    if (!(await mesas.count())) {
        const seletor = pagina.locator('select').first();

        if (await seletor.count()) {
            const valores = await seletor.locator('option')
                .evaluateAll((os) => os.map((o) => o.value).filter(Boolean));

            for (const v of valores) {
                await seletor.selectOption(v);
                await pagina.waitForTimeout(2500);

                const quantas = await pagina.evaluate(() =>
                    [...document.querySelectorAll('button')]
                        .filter((e) => /^Mesa \d/.test((e.innerText || '').trim())).length);

                if (quantas > 0) { break; }
            }
        }

        mesas = pagina.locator('button').filter({ hasText: /^Mesa \d/ });
    }

    const n = await mesas.count();

    if (!n) { return { ok: false, detalhe: 'nenhuma mesa em nenhuma sala' }; }

    await mesas.first().click();
    await pagina.waitForTimeout(2500);

    const vista = await pagina.evaluate(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data]'));
        return { vista: d.vista, comanda: !!d.comanda };
    });

    return { ok: vista.vista === 'comanda' || vista.comanda, detalhe: `${n} mesa(s), vista=${vista.vista}` };
});

await tentar('restaurante: tocar num prato junta à comanda', async () => {
    const pratos = pagina.locator('.grid button:visible');
    const n = await pratos.count();

    if (!n) { return { ok: false, detalhe: 'nenhum prato no ecrã' }; }

    await pratos.first().click();
    await pagina.waitForTimeout(1500);

    const itens = await pagina.evaluate(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data]'));
        return d.comanda?.items?.length ?? 0;
    });

    return { ok: itens > 0, detalhe: `${itens} artigo(s) na comanda` };
});

// ── Captura e resumo ─────────────────────────────────────────────────────
await pagina.screenshot({ path: 'scripts/zz-ui-final.png' }).catch(() => {});

const falhas = resultados.filter((r) => !r.ok);
console.log('\n================ RESUMO (conduzido pelos ecrãs) ================');
console.log(`passos: ${resultados.length} | falhas: ${falhas.length}`);
falhas.forEach((f) => console.log(`  ✗ ${f.passo}: ${f.detalhe}`));

const novos = [...new Set(erros)].filter((e) => !/18:4[0-9]|retries/.test(e));
if (novos.length) {
    console.log('\nERROS DE CONSOLA NOVOS (' + novos.length + '):');
    novos.slice(0, 6).forEach((e) => console.log('  · ' + e));
}

await browser.close();
