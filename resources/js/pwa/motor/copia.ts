import { db, lerMeta } from './base';
import { cargaDaComanda } from './fila';

/**
 * CÓPIA DE SEGURANÇA do que está por sincronizar.
 *
 * A fila vive no aparelho. Se o telemóvel se perde, se o browser limpa os
 * dados, ou se alguém carrega em «Apagar tudo» antes de sincronizar, as vendas
 * desaparecem — e já aconteceram, com dinheiro trocado e talão entregue. Vai
 * só o que está POR ENVIAR (o catálogo vem do servidor). Funciona sem rede.
 */
export async function exportarCopia() {
    const { fila, vendas, clientes, rascunhos, comandas } = await db.transaction(
        'r', [db.sync_queue, db.pos_sales, db.clients, db.draft_documents, db.rest_orders],
        async () => ({
            fila: await db.sync_queue.where('status').notEqual('done').toArray(),
            vendas: await db.pos_sales.where('_synced').equals(0).toArray(),
            clientes: await db.clients.where('_synced').equals(0).toArray(),
            rascunhos: await db.draft_documents.where('_synced').equals(0).toArray(),
            comandas: await db.rest_orders.filter((c) => !c._synced || c.status !== 'fechada').toArray(),
        }),
    );

    // A fila só guarda o UUID da comanda: a cópia leva a comanda INTEIRA,
    // incluindo as abertas que ainda nem entraram na fila.
    for (const comanda of comandas) {
        const job = fila.find((j) => j.op === 'sync_restaurant_order' && j.payload?.local_uuid === comanda.local_uuid);
        const carga = cargaDaComanda(comanda, job?.payload || {});
        if (job) job.payload = carga;
        else fila.push({ op: 'sync_restaurant_order', status: 'pending', payload: carga } as (typeof fila)[number]);
    }

    const meta: Record<string, unknown> = {};
    for (const chave of ['shift', 'last_sync', 'tenant_id', 'user']) {
        const v = await lerMeta(chave);
        if (v !== null) meta[chave] = v;
    }

    const copia = {
        formato: 'soserp.pwa.copia',
        versao: 2,
        gerado_em: new Date().toISOString(),
        tenant_id: window.SOS_TENANT_ID || meta.tenant_id || null,
        utilizador: {
            id: window.SOS_USER_ID || null,
            nome: window.SOS_USER_NAME || (meta.user as { name?: string } | undefined)?.name || null,
        },
        dispositivo: navigator.userAgent,
        contagens: {
            fila: fila.length,
            vendas: vendas.length,
            clientes: clientes.length,
            rascunhos: rascunhos.length,
            comandas: comandas.length,
        },
        dados: { sync_queue: fila, pos_sales: vendas, clients: clientes, draft_documents: rascunhos, rest_orders: comandas, meta },
    };

    const carimbo = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
    const nome = `sos-copia-offline_${carimbo}.json`;
    const url = URL.createObjectURL(new Blob([JSON.stringify(copia, null, 2)], { type: 'application/json' }));

    const a = document.createElement('a');
    a.href = url;
    a.download = nome;
    document.body.appendChild(a);
    a.click();
    a.remove();
    // Sem isto o Blob fica em memória até a página fechar.
    setTimeout(() => URL.revokeObjectURL(url), 1000);

    return { nome, contagens: copia.contagens };
}
