import { db, lerMeta, type Registo, type Trabalho } from './base';
import { anunciar, notificar, state } from './estado';
import { checkRealOnline, fetchJson, ErroDoServidor } from './rede';
import { motivoDoServidor } from './util';
import { t } from '@/i18n';

/**
 * A FILA — tudo o que foi feito no aparelho e ainda tem de subir.
 *
 * Regras que custaram caro, e que estão todas aqui:
 *  • a fila é a fonte da contagem (uma venda offline conta UMA vez);
 *  • a empresa fica carimbada no trabalho quando ele nasce, e um trabalho de
 *    outra empresa NUNCA sobe com esta sessão (fica retido, visível);
 *  • a subscrição expirada não gasta tentativas, a sessão expirada pára tudo;
 *  • uma recusa definitiva (4xx) não se repete, e o motivo fica legível.
 */

// Injectado pelo motor para evitar um ciclo de importações: a sincronização
// usa a fila e a fila, quando termina de enfileirar, pede uma sincronização.
let sincronizar: (forcar?: boolean) => Promise<void> = async () => {};
let depoisDeEmitirDocumento: (doc: Registo) => Promise<unknown> = async () => {};

export function ligarFila(ganchos: {
    sincronizar: (forcar?: boolean) => Promise<void>;
    depoisDeEmitirDocumento: (doc: Registo) => Promise<unknown>;
}): void {
    sincronizar = ganchos.sincronizar;
    depoisDeEmitirDocumento = ganchos.depoisDeEmitirDocumento;
}

/**
 * Quantas coisas faltam MESMO enviar. A fila é a fonte: cada venda, cliente ou
 * rascunho TEM um trabalho na fila, e somar os dois lados contava a mesma
 * venda duas vezes — o operador via dois números e não sabia em qual acreditar.
 */
export async function refreshPendingCount(base = db, estado = state): Promise<number> {
    estado.pendingCount = await base.sync_queue.where('status').equals('pending').count();
    notificar();

    return estado.pendingCount;
}

const OPS_COM_OPERADOR = ['create_pos_sale', 'open_pos_shift', 'close_pos_shift', 'sync_restaurant_order'];

/**
 * Põe um trabalho na fila.
 *
 * `autoSync` a false serve a quem quer ESPERAR pelo resultado: o disparo
 * automático marca a sincronização como a decorrer, e uma segunda chamada a
 * sync() sai logo sem esperar por nada. Quem espera, dispara.
 */
export async function enqueue(op: string, payload: Registo | null, autoSync = true): Promise<void> {
    // QUEM fez a operação offline, nas ops fiscais. O login por PIN é do lado
    // do cliente e a sessão do aparelho é a do último a sincronizar: sem isto
    // a venda de B (entrou por PIN) ia para a AGT em nome de A. O servidor
    // valida o operador (ResolveOperadorOffline).
    if (OPS_COM_OPERADOR.includes(op) && payload && payload.operator_id === undefined) {
        try {
            const u = await lerMeta<Registo>('user');
            if (u) payload = { ...payload, operator_id: u.id || null, operator_email: u.email || null };
        } catch { /* sem utilizador, o servidor decide */ }
    }

    // A EMPRESA CARIMBADA no trabalho, no momento em que nasce.
    let empresaDoTrabalho: number | null = null;
    try { empresaDoTrabalho = await lerMeta<number>('tenant_id'); } catch { /* ignora */ }

    await db.sync_queue.add({
        op,
        payload,
        tenant_id: empresaDoTrabalho,
        created_at: new Date().toISOString(),
        retries: 0,
        status: 'pending',
    });

    await refreshPendingCount();

    // Background Sync: sobe mesmo com a aplicação fechada, onde o browser deixa.
    try {
        navigator.serviceWorker?.controller?.postMessage({ type: 'REGISTER_SYNC' });
    } catch { /* sem service worker */ }

    if (autoSync && navigator.onLine) {
        void checkRealOnline().then((ok) => { if (ok) void sincronizar(false); });
    }
}

/** Os trabalhos entregues há mais de uma semana saem — a tabela não cresce sem fim. */
export async function limparEntreguesAntigos(dias = 7): Promise<void> {
    const limite = new Date(Date.now() - dias * 24 * 60 * 60 * 1000).toISOString();

    try {
        await db.sync_queue
            .where('status').equals('done')
            .filter((j) => (j.synced_at || j.created_at || '') < limite)
            .delete();
    } catch {
        // Falhar a limpar nunca pode impedir uma venda de subir.
    }
}

/**
 * O cliente a que um documento aponta, pronto a enviar. Três casos:
 *   1. já subiu e tem id do servidor → vai o `client_id`;
 *   2. ainda cá está, por subir → ESPERA-SE (emitir agora seria emitir em nome
 *      do Consumidor Final — foi a queixa);
 *   3. não há registo local (base limpa) → vai o `client_local_uuid` e o
 *      servidor resolve; se não souber, responde 409 e o documento espera.
 */
export async function resolverClienteLocal(payload: Registo): Promise<Registo> {
    if (!payload.client_local_uuid || payload.client_id) {
        delete payload.client_local_uuid;

        return payload;
    }

    const local = await db.clients.where('local_uuid').equals(payload.client_local_uuid).first();

    if (local && Number.isInteger(local.id)) {
        payload.client_id = local.id;
        delete payload.client_local_uuid;

        return payload;
    }

    if (local) throw new Error(t('Cliente ainda não sincronizado — a reagendar'));

    return payload;
}

/**
 * O cliente criado sem rede passa a ter o id do servidor.
 *
 * NÃO É UM `modify`: a chave primária é o `id` e o Dexie não deixa mudá-la —
 * rebentava com «Key already exists», o erro ficava enterrado na fila e o
 * cliente ficava local para sempre, com vendas que nunca subiam. Troca-se a
 * chave como se troca: apagar e escrever, numa transacção.
 */
export async function adoptarIdDoServidor(localUuid: string, idDoServidor: number): Promise<void> {
    await db.transaction('rw', db.clients, async () => {
        const local = await db.clients.where('local_uuid').equals(localUuid).first();
        if (!local) return;

        if (local.id === idDoServidor) {
            await db.clients.update(local.id, { _synced: 1 });

            return;
        }

        await db.clients.put({ ...local, id: idDoServidor, _synced: 1 });
        await db.clients.delete(local.id);
    });
}

/**
 * A comanda local traduzida para o que o servidor espera. Vai o preço que o
 * aparelho cobrou mesmo sabendo que o servidor usa o do catálogo: é a
 * comparação que produz o aviso de preço alterado.
 */
export function cargaDaComanda(comanda: Registo, doTrabalho: Registo = {}): Registo {
    return {
        local_uuid: comanda.local_uuid,
        venue_id: comanda.venue_id,
        table_id: comanda.table_id || null,
        channel: comanda.channel || (comanda.table_id ? 'table' : 'counter'),
        guest_count: comanda.guest_count || 1,
        client_id: comanda.client_id || null,
        notes: comanda.notes || null,
        confirmar: !!comanda.enviada_cozinha,
        operator_id: doTrabalho.operator_id ?? null,
        operator_email: doTrabalho.operator_email ?? null,
        items: (comanda.items || []).map((i: Registo) => ({
            local_uuid: i.local_uuid,
            product_id: i.product_id,
            quantity: parseFloat(i.quantity) || 0,
            unit_price: parseFloat(i.unit_price) || 0,
            notes: i.notes || null,
        })),
        checkout: comanda.checkout || null,
    };
}

async function executar(job: Trabalho): Promise<void> {
    const post = (url: string, corpo: unknown) => fetchJson(url, { method: 'POST', body: JSON.stringify(corpo) });

    switch (job.op) {
        case 'create_client': {
            const r = await post('/api/v1/invoicing/clients', job.payload);
            if (r.id && job.payload?.local_uuid) await adoptarIdDoServidor(job.payload.local_uuid, r.id);

            return;
        }

        case 'create_draft': {
            const payload = await resolverClienteLocal({ ...job.payload });
            const r = await post('/api/v1/invoicing/drafts', payload);

            if (r.id && job.payload?.local_uuid) {
                await db.draft_documents.where('local_uuid').equals(job.payload.local_uuid).modify((doc) => {
                    doc._server_id = r.id;
                    doc._synced = 1;
                    doc._server_number = r.invoice_number || r.proforma_number || doc._server_number || null;
                });

                // O papel definitivo guarda-se já — mas a entrega do documento não
                // pode falhar por o papel não ter descido.
                try {
                    const guardado = await db.draft_documents.get(job.payload.local_uuid);
                    if (guardado) await depoisDeEmitirDocumento(guardado);
                } catch (e) {
                    console.warn('[PWA] PDF definitivo ainda por guardar:', (e as Error).message);
                }
            }

            return;
        }

        case 'open_pos_shift': {
            const r = await post('/api/v1/invoicing/pos/shift/open', job.payload);

            if (r.success && r.shift) {
                await db.meta.put({ key: 'shift', value: {
                    open: true,
                    number: r.shift.number,
                    opened_at: r.shift.opened_at,
                    opening_balance: r.shift.opening_balance,
                    cash_sales: r.shift.cash_sales,
                    total_sales: r.shift.total_sales,
                } });
                anunciar('pwa:shift-synced', { action: 'open', shift: r.shift });
            }

            return;
        }

        case 'close_pos_shift': {
            // O FECHO só depois de TODAS as vendas offline subirem — senão não entram nele.
            const porSubir = await db.pos_sales.where('_synced').equals(0).count();
            if (porSubir > 0) throw new Error(t('Vendas por sincronizar — fecho de turno adiado'));

            const r = await post('/api/v1/invoicing/pos/shift/close', job.payload);

            if (r.success) {
                await db.meta.put({ key: 'shift', value: { open: false, number: null, opened_at: null } });
                anunciar('pwa:shift-synced', { action: 'close', shift: r.shift || null });
            }

            return;
        }

        case 'sync_restaurant_order': {
            // A COMANDA INTEIRA numa só viagem, no estado ACTUAL: o empregado
            // pode ter juntado pratos depois de ela entrar na fila. O servidor
            // repõe por identificador — mandar a mais nunca duplica.
            const comanda = await db.rest_orders.get(job.payload?.local_uuid);

            if (!comanda) {
                const sumiu = new ErroDoServidor(t('Comanda já não existe no aparelho'));
                sumiu.definitivo = true;
                throw sumiu;
            }

            const r = await post('/api/v1/restaurant/offline/comanda', cargaDaComanda(comanda, job.payload ?? {}));

            if (r.success) {
                const f = r.invoice;
                const mudanca: Registo = {
                    _synced: 1,
                    _server_id: r.id,
                    _server_number: r.order_number,
                    _server_status: r.status,
                    _avisos: r.avisos || [],
                    _invoice_number: f?.invoice_number || null,
                };

                // O QUE FAZ DO TALÃO UM COMPROVATIVO FISCAL: número, ATCUD, QR,
                // hash — e os TOTAIS do servidor. Número real com totais locais
                // dá um talão que não bate com os livros.
                if (f) {
                    mudanca._invoice_id = f.id;
                    mudanca._invoice_type = f.invoice_type || null;
                    mudanca._server_atcud = f.atcud || null;
                    mudanca._server_qr = f.qr_image || null;
                    mudanca._server_hash = f.hash_short || null;
                    mudanca._hash_control = f.hash_control || '1';
                    if (typeof f.total === 'number') mudanca.total = f.total;
                    if (typeof f.subtotal === 'number') mudanca.subtotal = f.subtotal;
                    if (typeof f.tax_amount === 'number') mudanca.tax = f.tax_amount;
                    if (typeof f.discount_amount === 'number') mudanca.discount_amount = f.discount_amount;
                    if (f.client_name) mudanca.client_name = f.client_name;
                    if (f.client_nif) mudanca.client_nif = f.client_nif;
                    if (Array.isArray(f.items) && f.items.length) mudanca.items_facturados = f.items;
                }

                // A MESA PODE TER MUDADO DE DONO NO SERVIDOR: outro posto sentou
                // lá gente enquanto este estava sem rede, e a comanda abriu ao
                // balcão. A comanda local LARGA a mesa — senão este aparelho
                // continuava a desenhá-la como sua, e fechar a conta punha «em
                // limpeza» uma mesa com clientes sentados. A mesa pedida fica
                // guardada para o talão dizer onde o cliente estava.
                const mudouDeMesa = !!comanda.table_id && (r.table_id ?? null) !== comanda.table_id;

                if (mudouDeMesa) {
                    mudanca.table_id = r.table_id ?? null;
                    mudanca.mesa_pedida = comanda.table_id;
                }

                await db.rest_orders.update(comanda.local_uuid, mudanca);

                if (mudouDeMesa) {
                    await db.rest_tables.update(comanda.table_id, { status: 'occupied' });
                }

                anunciar('pwa:comanda-sincronizada', {
                    local_uuid: comanda.local_uuid,
                    order_number: r.order_number,
                    invoice_number: r.invoice?.invoice_number || null,
                    avisos: r.avisos || [],
                });
            }

            return;
        }

        case 'repor_pin': {
            // Uma recusa do servidor é definitiva, e o motivo tem de chegar legível.
            try {
                await post('/api/v1/invoicing/pin/repor', job.payload);
            } catch (e) {
                if (e instanceof ErroDoServidor && e.definitivo) e.message = motivoDoServidor(e.message) || e.message;
                throw e;
            }

            return;
        }

        case 'logout':
            // A saída pedida sem rede; se a sessão já caiu, o servidor responde que está feito.
            await post('/invoicing/offline/sair', { da_fila: true });

            return;

        case 'create_pos_sale': {
            const payload = await resolverClienteLocal({ ...job.payload });
            const r = await post('/api/v1/invoicing/pos/sale', payload);

            if (r.success && job.payload?.local_uuid) {
                // Os TOTAIS do servidor também: o talão reimpresso misturava
                // número/QR/ATCUD reais com totais do aparelho. O servidor manda.
                const upd: Registo = {
                    _server_id: r.id,
                    _synced: 1,
                    _server_number: r.invoice_number || null,
                    _server_atcud: r.atcud || null,
                    _server_qr: r.qr_image || null,
                    _server_hash: r.hash_short || null,
                };
                if (typeof r.total === 'number') upd.total = r.total;
                if (typeof r.subtotal === 'number') upd.subtotal = r.subtotal;
                if (typeof r.tax_amount === 'number') upd.tax = r.tax_amount;
                if (typeof r.discount_amount === 'number') upd.discount_amount = r.discount_amount;
                if (Array.isArray(r.items) && r.items.length) upd.items = r.items;

                await db.pos_sales.where('local_uuid').equals(job.payload.local_uuid).modify(upd);
                anunciar('pwa:pos-sale-synced', {
                    local_uuid: job.payload.local_uuid,
                    invoice_number: r.invoice_number,
                    atcud: r.atcud,
                });
            }

            return;
        }

        default:
            // Uma operação que este motor não conhece: não se inventa nada.
            return;
    }
}

export async function processQueue(): Promise<void> {
    const pendentes = await db.sync_queue.where('status').equals('pending').sortBy('created_at');
    if (!pendentes.length) return;

    const empresaActual = await lerMeta<number>('tenant_id');

    for (const job of pendentes) {
        // A VENDA DE UMA EMPRESA NUNCA ENTRA NOS LIVROS DE OUTRA. Fica parada e
        // visível: apagar seria perder uma venda; enviar seria pior.
        if (job.tenant_id && empresaActual && job.tenant_id !== empresaActual) {
            console.warn('[PWA] Trabalho de outra empresa, retido:', job.op, job.tenant_id, '≠', empresaActual);
            await db.sync_queue.update(job.id!, { status: 'outra_empresa' });
            continue;
        }

        try {
            await executar(job);
            await db.sync_queue.update(job.id!, { status: 'done', synced_at: new Date().toISOString() });
            await limparEntreguesAntigos();
        } catch (err) {
            const e = err as ErroDoServidor;
            console.error('[PWA] Job falhou:', job, e);

            // A SUBSCRIÇÃO EXPIRADA NÃO GASTA TENTATIVAS: vendas cobradas não se
            // perdem por uma razão administrativa. Pára e espera pela renovação.
            if (e.message?.startsWith('SUBSCRIPTION_EXPIRED')) {
                await db.sync_queue.update(job.id!, { last_error: e.message });
                break;
            }

            await db.sync_queue.update(job.id!, { retries: (job.retries || 0) + 1, last_error: e.message });

            // Sessão expirada: não conta como tentativa útil — pára tudo.
            if (e.message?.startsWith('SESSION_EXPIRED')) break;

            // Recusa definitiva: não se repete, e não se põe à frente das vendas boas.
            if (e.definitivo) {
                console.warn('[PWA] Recusado pelo servidor, não se repete:', e.status, job.op);
                await db.sync_queue.update(job.id!, { status: 'failed' });
                continue;
            }

            if ((job.retries || 0) >= 5) await db.sync_queue.update(job.id!, { status: 'failed' });
        }
    }
}

const NOMES_DAS_OPERACOES: Record<string, string> = {
    create_pos_sale: 'Venda',
    create_client: 'Cliente',
    create_draft: 'Documento',
    open_pos_shift: 'Abertura de turno',
    close_pos_shift: 'Fecho de turno',
    sync_restaurant_order: 'Comanda',
    logout: 'Saída de sessão',
};

export interface ItemDaFila {
    id: number | undefined;
    tipo: string;
    detalhe: string;
    quando: string;
    estado: string;
    tentativas: number;
    erro: string | null;
}

/**
 * A fila em linguagem de quem está ao balcão. «3 por enviar» não se distingue
 * de «3 perdidos»: quando um fica preso, é preciso ver O QUÊ e porquê.
 *
 * SÓ o que ainda falta (pendentes e falhados). Os entregues ficam marcados
 * `done`; listá-los punha vendas JÁ ENTREGUES a aparecer como «à espera».
 */
export async function getQueue(base = db): Promise<ItemDaFila[]> {
    const itens = await base.sync_queue.where('status').anyOf('pending', 'failed').sortBy('created_at');

    const uuids = itens
        .filter((j) => j.op === 'sync_restaurant_order')
        .map((j) => j.payload?.local_uuid)
        .filter(Boolean);

    const comandasNaFila = new Map<string, Registo>(
        uuids.length && base.rest_orders
            ? (await base.rest_orders.bulkGet(uuids)).filter(Boolean).map((c) => [c!.local_uuid, c!])
            : [],
    );

    return itens.map((j) => {
        const p = j.payload || {};
        let detalhe = '';

        if (j.op === 'create_pos_sale') {
            const n = (p.items || []).length;
            const total = (p.items || []).reduce((s: number, i: Registo) => s + (parseFloat(i.quantity) || 0) * (parseFloat(i.unit_price) || 0), 0);
            detalhe = `${n} artigo(s) · ${total.toFixed(2)} Kz`;
        } else if (j.op === 'create_client') {
            detalhe = p.name || '';
        } else if (j.op === 'create_draft') {
            detalhe = String(p.doc_type || '').toUpperCase();
        } else if (j.op === 'sync_restaurant_order') {
            const c = comandasNaFila.get(p.local_uuid);
            detalhe = c ? `${(c.items || []).length} artigo(s) · ${(c.total || 0).toFixed(2)} Kz` : '';
        }

        return {
            id: j.id,
            tipo: NOMES_DAS_OPERACOES[j.op] || j.op,
            detalhe,
            quando: j.created_at,
            estado: j.status,
            tentativas: j.retries || 0,
            erro: j.last_error || null,
        };
    });
}

export async function getFailedJobs(): Promise<Trabalho[]> {
    return db.sync_queue.where('status').equals('failed').toArray();
}

/** Repete UM trabalho. O erro anterior fica: se voltar a falhar igual, isso é informação. */
export async function retryFailedJob(id: number): Promise<void> {
    await db.sync_queue.update(id, { status: 'pending', retries: 0 });
    await refreshPendingCount();
    if (navigator.onLine) void checkRealOnline().then((ok) => { if (ok) void sincronizar(false); });
}

export async function retryAllFailed(): Promise<void> {
    await db.sync_queue.where('status').equals('failed').modify({ status: 'pending', retries: 0, last_error: null });
    await refreshPendingCount();
    if (navigator.onLine) void checkRealOnline().then((ok) => { if (ok) void sincronizar(false); });
}
