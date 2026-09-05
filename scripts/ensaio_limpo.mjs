/**
 * O PWA de produção, pelos ecrãs, num Android — com o estado limpo entre passos.
 *
 * PORQUE O ESTADO É LIMPO. A versão anterior deste ensaio dava resultados
 * diferentes de corrida para corrida: um talão que ficava aberto tapava a
 * grelha do passo seguinte, uma gaveta que ficava aberta escondia a barra que
 * o passo seguinte procurava. O ensaio media a ordem por que corri os passos,
 * não o produto. Aqui cada passo começa de um ecrã conhecido.
 *
 * Três perguntas, e é só isso:
 *
 *   1. as MESAS chegam ao aparelho e abrem uma comanda a tocar?
 *   2. os PAGAMENTOS MÚLTIPLOS funcionam ao finalizar?
 *   3. o que se faz sem rede sobe correcto quando ela volta?
 *
 * Uso: EQUIPA='[...]' node scripts/ensaio_limpo.mjs
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
    console.log(`${ok ? 'OK   ' : 'FALHA'} │ ${String(passo).padEnd(42)} │ ${detalhe ?? ''}`);
};

async function passo(nome, fn) {
    try {
        const r = await fn();
        registar(nome, r?.ok !== false, r?.detalhe);
    } catch (e) {
        registar(nome, false, 'ERRO: ' + String(e.message).split('\n')[0].slice(0, 120));
    }
}

const alpine = (fn, arg) => pagina.evaluate(
    ([f, a]) => {
        const d = window.Alpine.$data(document.querySelector('[x-data]'));
        // eslint-disable-next-line no-new-func
        return new Function('d', 'a', `return (${f})(d, a)`)(d, a);
    },
    [fn.toString(), arg]
);

/**
 * O ecrã conhecido de onde cada passo parte.
 *
 * Recarregar é o mais barato e o mais fiável: apaga talões abertos, gavetas
 * meio fechadas e modais esquecidos, sem eu ter de adivinhar quais.
 */
async function doZero(rota) {
    await contexto.setOffline(false);
    await pagina.goto(`${BASE}${rota}`, { waitUntil: 'domcontentloaded' });
    await pagina.addInitScript(() => { window.print = () => {}; }).catch(() => {});
    await pagina.evaluate(() => { window.print = () => {}; });
    await pagina.waitForFunction(
        () => window.SosPwa?.db?.isOpen(), null, { timeout: 45000 }
    );
    await pagina.waitForTimeout(1800);
}

const sincronizar = async () => {
    await pagina.evaluate(() => window.SosPwa.sync(true));
    await pagina.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 });
};

const abrirCarrinho = async () => {
    const aberta = await alpine((d) => d?.showCart === true || window.innerWidth >= 1024).catch(() => false);
    if (aberta) { return; }

    const barra = pagina.locator('button').filter({ hasText: 'Ver carrinho' }).first();
    await barra.waitFor({ state: 'visible', timeout: 8000 });
    await barra.click({ timeout: 8000 });
    await pagina.waitForTimeout(900);
};

// ═══ 1. AS MESAS ═════════════════════════════════════════════════════════
await passo('mesas: chegam ao aparelho', async () => {
    await doZero('/invoicing/offline/restaurant');
    await sincronizar();
    await pagina.waitForTimeout(1500);

    const n = await pagina.evaluate(() => window.SosPwa.db.rest_tables.count());
    const salas = await pagina.evaluate(() => window.SosPwa.db.rest_venues.count());

    return { ok: n > 0, detalhe: `${n} mesa(s) em ${salas} sala(s), na base local` };
});

await passo('mesas: aparecem no ecrã da sala', async () => {
    const contar = () => pagina.evaluate(() =>
        [...document.querySelectorAll('button')].filter((e) => /^Mesa \d/.test((e.innerText || '').trim())).length);

    let n = await contar();

    // Com mais de uma sala, o ecrã abre na primeira. Escolhe-se a que tem
    // mesas — é o que o empregado faz.
    if (!n) {
        const seletor = pagina.locator('select').first();

        if (await seletor.count()) {
            const valores = await seletor.locator('option').evaluateAll((os) => os.map((o) => o.value).filter(Boolean));

            for (const v of valores) {
                await seletor.selectOption(v);
                await pagina.waitForTimeout(2500);
                n = await contar();
                if (n) { break; }
            }
        }
    }

    return { ok: n > 0, detalhe: `${n} mesa(s) tocáveis` };
});

await passo('mesas: tocar abre a comanda', async () => {
    const mesa = pagina.locator('button').filter({ hasText: /^Mesa \d/ }).first();
    const nome = (await mesa.innerText()).split('\n')[0];

    await mesa.click();
    await pagina.waitForTimeout(3000);

    const r = await alpine((d) => ({ vista: d.vista, comanda: !!d.comanda, numero: d.comanda?.order_number }));

    return { ok: r.vista === 'comanda' || r.comanda, detalhe: `${nome} → vista=${r.vista}` };
});

await passo('mesas: tocar num prato junta à comanda', async () => {
    const pratos = pagina.locator('.grid button:visible');

    if (!(await pratos.count())) { return { ok: false, detalhe: 'sem pratos no ecrã' }; }

    await pratos.first().click();
    await pagina.waitForTimeout(2000);

    const n = await alpine((d) => d.comanda?.items?.length ?? 0);

    return { ok: n > 0, detalhe: `${n} artigo(s) na comanda` };
});

await passo('mesas: a comanda sobe e a mesa fica ocupada', async () => {
    await sincronizar();
    await pagina.waitForTimeout(1500);

    const r = await pagina.evaluate(async () => {
        const comandas = await window.SosPwa.db.rest_orders.toArray();
        const abertas = comandas.filter((c) => c.status !== 'fechada');
        const ocupadas = (await window.SosPwa.db.rest_tables.toArray()).filter((m) => m.status !== 'available').length;

        return { comandas: comandas.length, abertas: abertas.length, mesasOcupadas: ocupadas };
    });

    return { ok: r.mesasOcupadas > 0, detalhe: `${r.comandas} comanda(s), ${r.mesasOcupadas} mesa(s) ocupada(s)` };
});

// ═══ 2. PAGAMENTOS MÚLTIPLOS ═════════════════════════════════════════════
await passo('pagamentos: o POS oferece mais do que um método?', async () => {
    await doZero('/invoicing/offline/pos');

    const r = await alpine((d) => ({
        metodos: (d.paymentMethods || []).map((m) => m.code),
        escolhido: d.payment,
        // O campo que o POS online usa para dividir o pagamento.
        temMulti: 'payments' in d || 'multiPagamento' in d,
    }));

    return {
        ok: r.temMulti,
        detalhe: `${r.metodos.length} método(s) [${r.metodos.join(', ')}] · divisão no ecrã: ${r.temMulti ? 'sim' : 'NÃO'}`,
    };
});

await passo('pagamentos: o motor envia payments[] na venda?', async () => {
    // Vê-se o que o aparelho põe na fila, que é o que o servidor vai receber.
    const enviado = await pagina.evaluate(async () => {
        const fila = await window.SosPwa.db.sync_queue.toArray();
        const venda = fila.filter((j) => j.op === 'create_pos_sale').pop();

        return venda ? {
            temPayments: Array.isArray(venda.payload?.payments),
            metodoUnico: venda.payload?.payment_method,
        } : null;
    });

    if (!enviado) { return { ok: false, detalhe: 'não há venda na fila para inspeccionar' }; }

    return {
        ok: enviado.temPayments,
        detalhe: enviado.temPayments
            ? 'envia payments[]'
            : `envia só payment_method="${enviado.metodoUnico}" — sem divisão`,
    };
});

await passo('pagamentos: o servidor aceita payments[]?', async () => {
    // O servidor é a outra metade: se ele aceita e o ecrã não oferece, a
    // funcionalidade existe e está por ligar — que é diferente de não existir.
    const r = await pagina.evaluate(async () => {
        const artigos = await window.SosPwa.db.products.toArray();
        const a = artigos.find((p) => (parseFloat(p.price) || 0) > 0);
        const total = Math.round((parseFloat(a.price) || 0) * 1.14 * 100) / 100;

        const resp = await fetch('/api/v1/invoicing/pos/sale', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json', 'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
            },
            body: JSON.stringify({
                local_uuid: 'misto_' + Date.now(),
                payment_method: 'cash',
                // Metade em dinheiro, metade a cartão — o caso real.
                payments: [
                    { method: 'cash', amount: Math.round(total / 2 * 100) / 100 },
                    { method: 'card', amount: total - Math.round(total / 2 * 100) / 100 },
                ],
                items: [{
                    product_id: a.id, product_name: a.name, quantity: 1,
                    unit_price: parseFloat(a.price), tax_rate: parseFloat(a.tax_rate) || 0, discount_percent: 0,
                }],
            }),
        });

        const corpo = await resp.json().catch(() => ({}));

        return { estado: resp.status, numero: corpo.invoice_number, erro: corpo.error || corpo.errors };
    });

    return {
        ok: r.estado >= 200 && r.estado < 300 && !!r.numero,
        detalhe: r.numero ? `${r.numero} (HTTP ${r.estado})` : `HTTP ${r.estado} · ${JSON.stringify(r.erro).slice(0, 90)}`,
    };
});

// ═══ 3. SEM REDE, PELOS ECRÃS ════════════════════════════════════════════
await passo('venda: finalizar a tocar, com rede', async () => {
    await doZero('/invoicing/offline/pos');

    await pagina.locator('.grid button:visible').first().click();
    await pagina.waitForTimeout(700);
    await abrirCarrinho();

    const botao = pagina.locator('button').filter({ hasText: 'Finalizar Venda' }).first();

    if (!(await botao.isEnabled())) { return { ok: false, detalhe: 'botão desactivado' }; }

    await botao.click();
    await pagina.waitForTimeout(5000);

    const r = await alpine((d) => ({ numero: d.lastReceipt?.number, carrinho: d.cart?.length ?? 0 }));

    return { ok: !!r.numero && r.carrinho === 0, detalhe: r.numero ?? 'sem recibo' };
});

await passo('venda: finalizar a tocar, SEM rede', async () => {
    await doZero('/invoicing/offline/pos');
    await contexto.setOffline(true);
    await pagina.waitForTimeout(800);

    await pagina.locator('.grid button:visible').first().click();
    await pagina.waitForTimeout(700);
    await abrirCarrinho();

    const botao = pagina.locator('button').filter({ hasText: 'Finalizar Venda' }).first();
    await botao.click();
    await pagina.waitForTimeout(4000);

    const r = await alpine((d) => ({ numero: d.lastReceipt?.number, sincronizado: d.lastReceipt?.synced }));

    await contexto.setOffline(false);

    return { ok: !!r.numero && r.sincronizado === false, detalhe: `${r.numero} (provisório)` };
});

await passo('venda: o que foi feito sem rede sobe assinado', async () => {
    await sincronizar();
    await pagina.waitForTimeout(1500);

    const r = await pagina.evaluate(async () => {
        const vendas = await window.SosPwa.db.pos_sales.toArray();
        const porSubir = vendas.filter((v) => !v._synced).length;
        const ultima = vendas.filter((v) => v._synced).pop();
        const fila = await window.SosPwa.db.sync_queue.where('status').equals('pending').count();

        return { porSubir, fila, numero: ultima?._server_number, hash: ultima?._server_hash };
    });

    return {
        ok: r.porSubir === 0 && r.fila === 0 && !!r.hash,
        detalhe: `${r.porSubir} por subir, fila ${r.fila} · última ${r.numero} hash ${r.hash}`,
    };
});

// ═══ Resumo ══════════════════════════════════════════════════════════════
const falhas = resultados.filter((r) => !r.ok);

console.log('\n═══════════════ RESUMO ═══════════════');
console.log(`passos: ${resultados.length} │ falhas: ${falhas.length}`);
falhas.forEach((f) => console.log(`  ✗ ${f.passo}: ${f.detalhe}`));

await browser.close();
