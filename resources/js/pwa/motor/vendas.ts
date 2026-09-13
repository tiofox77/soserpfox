import { db, lerMeta, type Cliente, type Produto, type Registo } from './base';
import { enqueue } from './fila';
import { emitirJa } from './sincronizar';
import { arredondar2, dataDeHoje, formasDeCodigo, idLocal, numero, soDados } from './util';

/**
 * O CATÁLOGO, OS CLIENTES E AS VENDAS DO BALCÃO.
 */

export async function getProducts(filtro: { search?: string } = {}): Promise<Produto[]> {
    const todos = await db.products.toArray();

    if (!filtro.search) return todos;

    const s = filtro.search.toLowerCase();
    // As formas equivalentes do código lido — o mesmo que o servidor faz em
    // App\Support\CodigoDeBarras. Offline não há alternativa se falhar.
    const formas = formasDeCodigo(filtro.search);

    return todos.filter((p) => (p.barcode && formas.includes(String(p.barcode).trim()))
        || String(p.name || '').toLowerCase().includes(s)
        || String(p.sku || '').toLowerCase().includes(s)
        || String(p.barcode || '').toLowerCase().includes(s)
        // Numa farmácia pergunta-se pela substância; numa loja de roupa, pelo tamanho.
        || String(p.active_ingredient || '').toLowerCase().includes(s)
        || String(p.size || '').toLowerCase().includes(s));
}

/** O artigo lido pelo leitor (ou escrito): código de barras em qualquer das formas, ou o SKU. */
export function produtoPeloCodigo(produtos: Produto[], lido: string): Produto | undefined {
    const formas = formasDeCodigo(lido);
    const s = lido.trim().toLowerCase();

    return produtos.find((p) => (p.barcode && formas.includes(String(p.barcode).trim()))
        || String(p.barcode || '').toLowerCase() === s
        || String(p.sku || '').toLowerCase() === s);
}

export async function getClients(filtro: { search?: string } = {}): Promise<Cliente[]> {
    const todos = await db.clients.toArray();

    if (!filtro.search) return todos;

    const s = filtro.search.toLowerCase();

    return todos.filter((c) => String(c.name || '').toLowerCase().includes(s) || String(c.nif || '').toLowerCase().includes(s));
}

export async function createClientOffline(dados: Registo): Promise<Cliente> {
    const limpos = soDados(dados);
    const local_uuid = idLocal('c');
    const registo: Cliente = {
        id: 'local_' + local_uuid,
        local_uuid,
        _synced: 0,
        created_offline_at: new Date().toISOString(),
        ...limpos,
    };

    await db.clients.put(registo);
    await enqueue('create_client', { local_uuid, ...limpos });

    return registo;
}

/** Os totais de uma venda do balcão: IVA por linha e o desconto comercial em % sobre a base. */
export function totaisDaVenda(itens: Registo[], descontoPct: unknown) {
    let subtotal = 0;
    let imposto = 0;

    for (const i of itens) {
        const liquido = numero(i.quantity) * numero(i.unit_price);
        subtotal += liquido;
        imposto += liquido * numero(i.tax_rate) / 100;
    }

    const pct = numero(descontoPct);
    const desconto = subtotal * pct / 100;
    const base = subtotal - desconto;
    const impostoDepois = imposto * (subtotal > 0 ? base / subtotal : 1);

    return { subtotal, desconto, imposto: impostoDepois, total: base + impostoDepois };
}

/**
 * Cria a venda do balcão no aparelho: número provisório, stock local baixado,
 * trabalho na fila — e, com rede, espera pelo número fiscal (`emitirJa`).
 */
export async function createPosSaleOffline(venda: Registo): Promise<Registo> {
    const sale = soDados(venda);
    const local_uuid = idLocal('pos');
    const criadaEm = new Date().toISOString();

    // O stock «pessimista» offline: só artigos que controlam stock. O servidor
    // é a verdade, e repõe-no na próxima sincronização.
    for (const item of sale.items || []) {
        const pid = Number.isInteger(item.product_id) ? item.product_id : null;
        if (!pid) continue;

        const prod = await db.products.get(pid);
        if (!prod || prod.type === 'servico' || !prod.manage_stock) continue;

        await db.products.update(pid, {
            stock_quantity: Math.max(0, numero(prod.stock_quantity) - numero(item.quantity)),
        });
    }

    const tot = totaisDaVenda(sale.items || [], sale.discount_commercial);
    const pct = numero(sale.discount_commercial);

    const registo: Registo = {
        local_uuid,
        provisional_number: 'PEND-' + dataDeHoje().replace(/-/g, '') + '-' + local_uuid.slice(-6).toUpperCase(),
        client_id: sale.client_id || null,
        client_local_uuid: sale.client_local_uuid || null,
        client_name: sale.client_name || 'Consumidor Final',
        client_nif: sale.client_nif || '999999999',
        payment_method: sale.payment_method || 'cash',
        // AS LINHAS DO PAGAMENTO DIVIDIDO ficam GUARDADAS: o talão reimpresso tem
        // de poder dizer quanto entrou em dinheiro e quanto a cartão.
        payments: Array.isArray(sale.payments) && sale.payments.length ? sale.payments : null,
        amount_received: numero(sale.amount_received) || tot.total,
        discount_commercial: pct,
        notes: sale.notes || '',
        items: sale.items || [],
        subtotal: arredondar2(tot.subtotal),
        discount_amount: arredondar2(tot.desconto),
        tax: arredondar2(tot.imposto),
        total: arredondar2(tot.total),
        created_at: criadaEm,
        created_at_local: criadaEm,
        _synced: 0,
        _server_id: null,
        _server_number: null,
        _server_atcud: null,
        _server_qr: null,
        _server_hash: null,
    };

    await db.pos_sales.put(registo);

    await enqueue('create_pos_sale', {
        local_uuid,
        client_id: sale.client_id || null,
        client_local_uuid: sale.client_local_uuid || null,
        payment_method: registo.payment_method,
        payments: registo.payments,
        amount_received: registo.amount_received,
        discount_commercial: pct,
        notes: registo.notes,
        created_at_local: criadaEm,
        items: (sale.items || []).map((i: Registo) => ({
            product_id: Number.isInteger(i.product_id) ? i.product_id : null,
            product_name: i.product_name,
            quantity: numero(i.quantity),
            unit_price: numero(i.unit_price),
            // NÃO usar «|| 14»: 0% (isento) é falsy e viraria 14%.
            tax_rate: Number.isFinite(parseFloat(i.tax_rate)) ? parseFloat(i.tax_rate) : 0,
            // A ESCOLHA, nunca o valor: quem apura é o servidor.
            iec_pautal: i.iec_pautal || null,
            is_verba: i.is_verba || null,
            is_service: !!i.is_service,
            unit: i.unit || 'UN',
        })),
    }, false);

    // Havendo rede, a factura sai já com número fiscal; senão segue o provisório.
    return (await emitirJa(local_uuid)) || registo;
}

export async function getPosSales(): Promise<Registo[]> {
    return db.pos_sales.orderBy('created_at').reverse().toArray();
}

/** As vendas JÁ sincronizadas com mais de 30 dias saem do aparelho (as por enviar nunca). */
export async function purgarVendasAntigas(dias = 30): Promise<void> {
    try {
        const corte = new Date();
        corte.setDate(corte.getDate() - dias);

        const velhas = await db.pos_sales
            .where('_synced').equals(1)
            .filter((s) => new Date(s.created_at) < corte)
            .toArray();

        if (velhas.length) await db.pos_sales.bulkDelete(velhas.map((s) => s.local_uuid));
    } catch (e) {
        console.warn('[POS] Purge falhou:', e);
    }
}

export const getCompany = () => lerMeta<Registo>('company');
export const getWarehouse = () => lerMeta<Registo>('warehouse');
export const getLastSyncDate = () => lerMeta<string>('last_sync');
export const getMeta = (chave: string) => lerMeta(chave);

export async function modulos(): Promise<string[]> {
    return (await lerMeta<string[]>('modules')) || [];
}

export async function temModulo(slug: string): Promise<boolean> {
    return (await modulos()).includes(slug);
}
