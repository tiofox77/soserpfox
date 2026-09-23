import 'fake-indexeddb/auto';

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { db } from './base';
import { state } from './estado';
import { limparEntreguesAntigos } from './fila';
import { SosPwa } from './index';

/**
 * A SINCRONIZAÇÃO E A FILA, contra a base verdadeira (IndexedDB em memória) e
 * um servidor fingido.
 *
 * Cada caso aqui é uma avaria que já aconteceu num balcão: vendas contadas a
 * dobrar, clientes que perdiam o `local_uuid`, a venda de uma empresa a subir
 * com a sessão de outra, a subscrição expirada a gastar tentativas.
 */

type Resposta = { status?: number; corpo?: unknown };
type Rota = (url: string, init?: RequestInit) => Resposta | undefined;

let rotas: Rota[] = [];
const pedidos: Array<{ url: string; corpo: any }> = [];

function servidor(...novas: Rota[]) {
    rotas = novas;
}

function respostaDeSync(extra: Record<string, unknown> = {}) {
    return {
        server_time: '2026-09-13T10:00:00Z',
        tenant_id: 1,
        user: { id: 7, name: 'Ana', email: 'ana@x.ao' },
        data: { products: [], clients: [] },
        ...extra,
    };
}

beforeEach(async () => {
    await Promise.all(db.tables.map((t) => t.clear()));
    Object.assign(state, { syncing: false, pendingCount: 0, sessionExpired: false, subscriptionExpired: false, retidos: 0, erroDeSync: null });
    pedidos.length = 0;
    sessionStorage.clear();

    if (!('timeout' in AbortSignal)) {
        (AbortSignal as any).timeout = () => new AbortController().signal;
    }

    vi.stubGlobal('fetch', vi.fn(async (entrada: RequestInfo | URL, init?: RequestInit) => {
        const url = String(entrada);
        pedidos.push({ url, corpo: init?.body ? JSON.parse(String(init.body)) : null });

        for (const r of rotas) {
            const res = r(url, init);
            if (res) {
                return new Response(res.corpo === undefined ? '' : JSON.stringify(res.corpo), {
                    status: res.status ?? 200,
                    headers: { 'Content-Type': 'application/json' },
                });
            }
        }

        if (url.includes('/ping')) return new Response('{}', { status: 200 });
        if (url.includes('/molde/')) return new Response('', { status: 404 });

        return new Response('{}', { status: 404 });
    }));
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('a fila', () => {
    it('uma venda offline conta UMA vez (a fila é a fonte)', async () => {
        servidor((u) => (u.includes('/ping') ? { status: 503 } : undefined));

        await SosPwa.createPosSaleOffline({ items: [{ product_id: 1, product_name: 'Água', quantity: 2, unit_price: 150, tax_rate: 0 }] });

        expect(await db.pos_sales.count()).toBe(1);
        expect(await SosPwa.refreshPendingCount()).toBe(1);
        expect(state.pendingCount).toBe(1);
    });

    it('cada trabalho leva a empresa e o operador carimbados', async () => {
        servidor((u) => (u.includes('/ping') ? { status: 503 } : undefined));
        await db.meta.bulkPut([{ key: 'tenant_id', value: 3 }, { key: 'user', value: { id: 9, email: 'b@x.ao' } }]);

        await SosPwa.createPosSaleOffline({ items: [] });
        const [job] = await db.sync_queue.toArray();

        expect(job!.tenant_id).toBe(3);
        expect(job!.payload).toMatchObject({ operator_id: 9, operator_email: 'b@x.ao' });
    });

    it('FT, FR e proforma levam o operador que entrou por PIN', async () => {
        servidor((u) => (u.includes('/ping') ? { status: 503 } : undefined));
        await db.meta.bulkPut([{ key: 'tenant_id', value: 3 }, { key: 'user', value: { id: 9, email: 'b@x.ao' } }]);

        for (const doc_type of ['FT', 'FR', 'proforma']) {
            await SosPwa.createDraftOffline({
                doc_type,
                items: [{ product_name: 'Teste', quantity: 1, unit_price: 100, tax_rate: 0 }],
            });
        }

        const jobs = await db.sync_queue.where('op').equals('create_draft').toArray();
        expect(jobs).toHaveLength(3);
        expect(jobs.every((job) => job.payload?.operator_id === 9)).toBe(true);
        expect(jobs.every((job) => job.payload?.operator_email === 'b@x.ao')).toBe(true);
    });

    it('o fecho de um operador nao espera pelas vendas de outro operador', async () => {
        await db.meta.put({ key: 'tenant_id', value: 1 });
        await db.pos_sales.put({ local_uuid: 'venda-b', _synced: 0, created_at: new Date().toISOString() });
        await db.sync_queue.bulkAdd([
            { op: 'close_pos_shift', payload: { operator_id: 7, actual_cash: 0 }, tenant_id: 1, created_at: '2026-09-13T10:00:01Z', retries: 0, status: 'pending' },
            { op: 'create_pos_sale', payload: { operator_id: 8, local_uuid: 'venda-b', items: [] }, tenant_id: 1, created_at: '2026-09-13T10:00:02Z', retries: 0, status: 'pending' },
        ]);
        servidor(
            (u) => (u.includes('/shift/close') ? { corpo: { success: true, shift: { id: 1 } } } : undefined),
            (u) => (u.includes('/pos/sale') ? { corpo: { success: true, id: 10, invoice_number: 'FR T/1' } } : undefined),
            (u) => (u.includes('/invoicing/sync') ? { corpo: respostaDeSync() } : undefined),
        );

        await SosPwa.sync(false);

        const jobs = await db.sync_queue.toArray();
        expect(jobs.every((job) => job.status === 'done')).toBe(true);
        expect(jobs[0]!.retries).toBe(0);
    });

    it('o fecho nao espera por venda posterior do mesmo operador', async () => {
        await db.meta.put({ key: 'tenant_id', value: 1 });
        await db.sync_queue.bulkAdd([
            { op: 'close_pos_shift', payload: { operator_id: 7, actual_cash: 0 }, tenant_id: 1, created_at: '2026-09-13T10:00:01Z', retries: 0, status: 'pending' },
            { op: 'create_pos_sale', payload: { operator_id: 7, local_uuid: 'venda-seguinte', items: [] }, tenant_id: 1, created_at: '2026-09-13T10:00:02Z', retries: 0, status: 'pending' },
        ]);
        servidor(
            (u) => (u.includes('/shift/close') ? { corpo: { success: true, shift: { id: 1 } } } : undefined),
            (u) => (u.includes('/pos/sale') ? { corpo: { success: true, id: 11, invoice_number: 'FR T/2' } } : undefined),
            (u) => (u.includes('/invoicing/sync') ? { corpo: respostaDeSync() } : undefined),
        );

        await SosPwa.sync(false);

        const jobs = await db.sync_queue.toArray();
        expect(jobs.every((job) => job.status === 'done')).toBe(true);
        expect(jobs.every((job) => (job.retries || 0) === 0)).toBe(true);
    });

    it('um trabalho de OUTRA empresa fica retido e não sobe', async () => {
        await db.meta.put({ key: 'tenant_id', value: 2 });
        await db.sync_queue.add({ op: 'create_client', payload: { local_uuid: 'c1', name: 'X' }, tenant_id: 1, created_at: new Date().toISOString(), retries: 0, status: 'pending' });
        servidor((u) => (u.includes('/invoicing/sync') ? { corpo: respostaDeSync({ tenant_id: 2 }) } : undefined));

        await SosPwa.sync(false);

        const [job] = await db.sync_queue.toArray();
        expect(job!.status).toBe('outra_empresa');
        expect(pedidos.some((p) => p.url.includes('/invoicing/clients'))).toBe(false);
    });

    it('a subscrição expirada NÃO gasta tentativas e pára a fila', async () => {
        await db.sync_queue.add({ op: 'create_pos_sale', payload: { local_uuid: 'pos_1', items: [] }, created_at: new Date().toISOString(), retries: 0, status: 'pending' });
        servidor((u) => (u.includes('/pos/sale') ? { status: 402, corpo: {} } : undefined));

        await SosPwa.sync(false);

        const [job] = await db.sync_queue.toArray();
        expect(job!.status).toBe('pending');
        expect(job!.retries).toBe(0);
        expect(state.subscriptionExpired).toBe(true);
    });

    it('uma recusa definitiva (4xx) não se repete, e fica o motivo', async () => {
        await db.sync_queue.add({ op: 'create_client', payload: { local_uuid: 'c1' }, created_at: new Date().toISOString(), retries: 0, status: 'pending' });
        servidor((u) => (u.includes('/invoicing/clients') ? { status: 422, corpo: { error: 'NIF obrigatório' } } : undefined));

        await SosPwa.sync(false);

        const [job] = await db.sync_queue.toArray();
        expect(job!.status).toBe('failed');
        expect(job!.last_error).toContain('NIF obrigatório');
    });

    it('o 409 (cliente ainda por chegar) espera, não falha', async () => {
        await db.sync_queue.add({ op: 'create_pos_sale', payload: { local_uuid: 'pos_1', items: [] }, created_at: new Date().toISOString(), retries: 0, status: 'pending' });
        servidor((u) => (u.includes('/pos/sale') ? { status: 409, corpo: {} } : undefined));

        await SosPwa.sync(false);

        const [job] = await db.sync_queue.toArray();
        expect(job!.status).toBe('pending');
        expect(job!.retries).toBe(1);
    });

    it('a lista do que falta mostra nomes de gente, e não os entregues', async () => {
        // A fila lista pela ordem de chegada.
        const aos = (s: number) => new Date(Date.UTC(2026, 8, 13, 10, 0, s)).toISOString();
        await db.sync_queue.bulkAdd([
            { op: 'create_pos_sale', status: 'pending', retries: 0, created_at: aos(1), payload: { items: [{ quantity: 2, unit_price: 1500 }, { quantity: 1, unit_price: 700 }] } },
            { op: 'create_client', status: 'failed', retries: 3, created_at: aos(2), last_error: 'Cliente ainda não sincronizado', payload: { name: 'Ana Paula' } },
            { op: 'logout', status: 'done', retries: 0, created_at: aos(3), payload: {} },
            { op: 'coisa_nova', status: 'pending', retries: 0, created_at: aos(4), payload: null },
        ]);

        const fila = await SosPwa.getQueue();

        expect(fila.map((j) => j.tipo)).toEqual(['Venda', 'Cliente', 'coisa_nova']);
        expect(fila[0]!.detalhe).toBe('2 artigo(s) · 3700.00 Kz');
        expect(fila[1]).toMatchObject({ estado: 'failed', tentativas: 3, erro: 'Cliente ainda não sincronizado', detalhe: 'Ana Paula' });
    });

    it('fila vazia devolve lista vazia', async () => {
        expect(await SosPwa.getQueue()).toEqual([]);
    });

    it('os entregues antigos são limpos, para a tabela não crescer sem fim; os por enviar nunca', async () => {
        const haDias = (d: number) => new Date(Date.now() - d * 86400000).toISOString();
        await db.sync_queue.bulkAdd([
            { op: 'logout', status: 'done', retries: 0, created_at: haDias(30), synced_at: haDias(30), payload: {} },
            { op: 'logout', status: 'done', retries: 0, created_at: haDias(1), synced_at: haDias(1), payload: {} },
            { op: 'create_pos_sale', status: 'pending', retries: 0, created_at: haDias(60), payload: {} },
        ]);

        await limparEntreguesAntigos(7);

        const ficam = await db.sync_queue.toArray();
        expect(ficam.map((j) => j.status).sort()).toEqual(['done', 'pending']);
    });
});

describe('a descarga do catálogo', () => {
    it('o cliente acabado de subir NÃO perde o local_uuid na mesma sincronização', async () => {
        await db.clients.put({ id: 'local_c_1', local_uuid: 'c_1', name: 'Ana', _synced: 0 });
        await db.sync_queue.add({ op: 'create_client', payload: { local_uuid: 'c_1', name: 'Ana' }, created_at: new Date().toISOString(), retries: 0, status: 'pending' });

        servidor(
            (u) => (u.includes('/invoicing/clients') ? { corpo: { id: 55 } } : undefined),
            (u) => (u.includes('/invoicing/sync') ? { corpo: respostaDeSync({ data: { clients: [{ id: 55, name: 'Ana', nif: '5000' }] } }) } : undefined),
        );

        await SosPwa.sync(false);

        const c = await db.clients.get(55);
        expect(c).toMatchObject({ id: 55, local_uuid: 'c_1', _synced: 1, nif: '5000' });
        expect(await db.clients.get('local_c_1')).toBeUndefined();
    });

    it('o que saiu do catálogo no servidor sai do aparelho; as tabelas da AGT substituem-se', async () => {
        await db.products.bulkPut([{ id: 1, name: 'Fica' }, { id: 2, name: 'Sai' }]);
        await db.iec_pautais.put({ pautal_code: 'VELHO' });

        servidor((u) => (u.includes('/invoicing/sync')
            ? { corpo: respostaDeSync({ data: { products: [], removed_products: [2], iec_pautais: [{ pautal_code: 'NOVO' }], is_verbas: [] } }) }
            : undefined));

        await SosPwa.sync(false);

        expect((await db.products.toArray()).map((p) => p.id)).toEqual([1]);
        expect((await db.iec_pautais.toArray()).map((p) => p.pautal_code)).toEqual(['NOVO']);
    });

    it('ao mudar de EMPRESA limpa-se o catálogo e RETÉM-SE a fila, com aviso', async () => {
        await db.meta.put({ key: 'tenant_id', value: 1 });
        await db.products.put({ id: 1, name: 'Da empresa 1' });
        await db.sync_queue.add({ op: 'create_pos_sale', payload: { local_uuid: 'p' }, tenant_id: 1, created_at: new Date().toISOString(), retries: 0, status: 'outra_empresa' });

        servidor((u) => (u.includes('/invoicing/sync') ? { corpo: respostaDeSync({ tenant_id: 2 }) } : undefined));

        await SosPwa.sync(false);

        expect(await db.products.count()).toBe(0);
        expect(await db.sync_queue.count()).toBe(1);
        expect(state.retidos).toBe(1);
        expect((await db.meta.get('tenant_id'))!.value).toBe(2);
    });

    it('os funcionários substituem-se por inteiro (quem saiu deixa de entrar)', async () => {
        await db.employees.put({ email: 'saiu@x.ao', id: 1, pin_hash: 'h' });
        servidor((u) => (u.includes('/invoicing/sync')
            ? { corpo: respostaDeSync({ employees: [{ email: ' Novo@X.ao ', id: 2, name: 'Novo', pin_hash: '$2a$x' }], offline_valid_until: '2099-01-01' }) }
            : undefined));

        await SosPwa.sync(false);

        expect((await db.employees.toArray()).map((e) => e.email)).toEqual(['novo@x.ao']);
    });

    it('um turno aberto offline por subir não é pisado pelo do servidor', async () => {
        await db.meta.put({ key: 'shift', value: { open: true, number: 'LOCAL-1' } });
        await db.sync_queue.add({ op: 'open_pos_shift', payload: {}, created_at: new Date().toISOString(), retries: 5, status: 'failed' });
        servidor(
            (u) => (u.includes('/shift/open') ? { status: 500 } : undefined),
            (u) => (u.includes('/invoicing/sync') ? { corpo: respostaDeSync({ shift: { open: false } }) } : undefined),
        );

        await SosPwa.sync(false);

        expect((await db.meta.get('shift'))!.value).toMatchObject({ number: 'LOCAL-1' });
    });

    it('sem rede a sério (ping falha) não se toca em nada', async () => {
        servidor((u) => (u.includes('/ping') ? { status: 503 } : undefined));

        await SosPwa.sync(true);

        expect(pedidos.some((p) => p.url.includes('/invoicing/sync'))).toBe(false);
        expect(state.syncing).toBe(false);
    });
});

describe('emitir já', () => {
    it('com rede devolve a venda já com número fiscal', async () => {
        const venda = { local_uuid: 'pos_1', _synced: 1, _server_number: 'FR SOS/2026/41' };
        const r = await SosPwa.emitirJa('pos_1', 50, {
            navigator: { onLine: true } as Navigator,
            checkRealOnline: async () => true,
            sync: async () => {},
            db: { pos_sales: { get: async () => venda } } as any,
        });

        expect(r).toBe(venda);
    });

    it('sem rede não espera nem sincroniza; rede lenta não prende o balcão', async () => {
        const sync = vi.fn(async () => { await new Promise((ok) => setTimeout(ok, 300)); });

        expect(await SosPwa.emitirJa('p', 50, { navigator: { onLine: false } as Navigator, checkRealOnline: async () => true, sync, db: db })).toBeNull();
        expect(sync).not.toHaveBeenCalled();

        const t0 = Date.now();
        const r = await SosPwa.emitirJa('p', 50, {
            navigator: { onLine: true } as Navigator, checkRealOnline: async () => true, sync,
            db: { pos_sales: { get: async () => ({ _synced: 0 }) } } as any,
        });
        expect(r).toBeNull();
        expect(Date.now() - t0).toBeLessThan(250);
    });

    it('se a venda não chegou a subir segue provisória, e um erro a meio não rebenta a venda', async () => {
        const naoSubiu = await SosPwa.emitirJa('p', 50, {
            navigator: { onLine: true } as Navigator, checkRealOnline: async () => true, sync: async () => {},
            db: { pos_sales: { get: async () => ({ local_uuid: 'p', _synced: 0 }) } } as any,
        });
        expect(naoSubiu).toBeNull();

        const rebentou = await SosPwa.emitirJa('p', 50, {
            navigator: { onLine: true } as Navigator, checkRealOnline: async () => true,
            sync: async () => { throw new Error('HTTP 500'); },
            db: db,
        });
        expect(rebentou).toBeNull();
    });
});

describe('os documentos e a saída', () => {
    it('um objecto do ecrã com Proxy vai para a base como dados', async () => {
        servidor((u) => (u.includes('/ping') ? { status: 503 } : undefined));
        const reactivo = new Proxy({ doc_type: 'FT', items: [{ quantity: 2, unit_price: 1500, tax_rate: 0 }] }, { get: (a, p) => (a as any)[p] });

        const doc = await SosPwa.createDraftOffline(reactivo);

        expect((await db.draft_documents.get(doc.local_uuid))!.total).toBe(3000);
    });

    it('o documento leva os descontos, a entrega e a retenção só em serviços', async () => {
        servidor((u) => (u.includes('/ping') ? { status: 503 } : undefined));

        await SosPwa.createDraftOffline({ doc_type: 'FT', items: [], discount_commercial: 5, delivery_location: 'Luanda', withholding_percentage: 10 });
        await SosPwa.createDraftOffline({ doc_type: 'FT', items: [], is_service: true });

        const [a, b] = await db.sync_queue.toArray();
        expect(a!.payload).toMatchObject({ discount_commercial: 5, delivery_location: 'Luanda', withholding_percentage: null });
        expect(b!.payload).toMatchObject({ is_service: true, withholding_percentage: 6.5 });
    });

    it('um serviço com retenção de 0% vai com 0%, e não com os 6,5% por omissão', async () => {
        servidor((u) => (u.includes('/ping') ? { status: 503 } : undefined));

        await SosPwa.createDraftOffline({ doc_type: 'FT', items: [{ quantity: 1, unit_price: 1000, tax_rate: 0 }], is_service: true, withholding_percentage: 0 });

        const [j] = await db.sync_queue.toArray();
        expect(j!.payload!.withholding_percentage).toBe(0);
        expect((await db.draft_documents.toArray())[0]!.total).toBe(1000);
    });

    it('sair tranca o aparelho, vai na fila, e NÃO apaga o acesso offline', async () => {
        servidor((u) => (u.includes('/ping') ? { status: 503 } : undefined));
        sessionStorage.setItem('pwa_unlocked', '1');
        await db.meta.put({ key: 'auth_cache', value: { email: 'a@x.ao' } });

        await SosPwa.sair();

        expect(sessionStorage.getItem('pwa_unlocked')).toBeNull();
        expect((await db.sync_queue.toArray()).map((j) => j.op)).toEqual(['logout']);
        expect(await db.meta.get('auth_cache')).toBeTruthy();
    });
});

describe('o arranque', () => {
    it('uma sincronização pedida logo ao abrir não é apagada pela migração do catálogo', async () => {
        const { arrancarMotor } = await import('./arranque');
        // Sem temporizadores nem ouvintes a ficar para trás depois do ensaio.
        vi.spyOn(window, 'setInterval').mockReturnValue(0 as unknown as ReturnType<typeof setInterval>);

        // O catálogo desce UMA vez (as seguintes são incrementais e não trazem nada).
        let descargas = 0;
        servidor((u) => (u.includes('/invoicing/sync')
            ? { corpo: respostaDeSync({ data: { products: descargas++ === 0 ? [{ id: 1, name: 'Água' }, { id: 2, name: 'Pão' }] : [], clients: [] } }) }
            : undefined));

        // Um aparelho com o catálogo no formato antigo: o arranque vai apagá-lo.
        await db.products.put({ id: 99, name: 'Formato antigo' });
        vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(true);

        // Uma base lenta (um telemóvel de balcão ao abrir): a limpeza demora.
        const limpar = db.products.clear.bind(db.products);
        vi.spyOn(db.products, 'clear').mockImplementation((() => new Promise((ok) => setTimeout(ok, 150)).then(() => limpar())) as unknown as typeof limpar);

        // O ecrã (ou o service worker) pede uma sincronização no mesmo instante.
        const arranque = arrancarMotor();
        const pedida = SosPwa.sync(false);
        await Promise.all([arranque, pedida]);
        await vi.waitFor(() => expect(state.syncing).toBe(false));

        expect((await db.products.toArray()).map((p) => p.id).sort()).toEqual([1, 2]);
    });
});

/**
 * O ESPERADO NO FECHO OFFLINE conta o que a tesouraria tirou ou pôs na gaveta
 * durante o turno (23/09/2026): a recolha do gerente, a despesa paga da
 * gaveta, o reforço de troco. O servidor manda os dois números na
 * sincronização; sem eles o operador via uma falta que não era dele.
 */
describe('o dinheiro esperado no fecho do turno', () => {
    it('soma as entradas e tira as saídas da gaveta', async () => {
        const { dinheiroEsperado } = await import('./turno');
        const turno = { open: true, opened_at: new Date(Date.now() - 3600000).toISOString(), opening_balance: 20000, cash_sales: 100000, saidas_da_gaveta: 80000, entradas_na_gaveta: 2000 };

        expect(dinheiroEsperado(turno, [])).toBe(42000);
    });

    it('sem os números da gaveta (servidor antigo) fica como era', async () => {
        const { dinheiroEsperado } = await import('./turno');

        expect(dinheiroEsperado({ open: true, opened_at: null, opening_balance: 20000, cash_sales: 100000 }, [])).toBe(120000);
    });
});
