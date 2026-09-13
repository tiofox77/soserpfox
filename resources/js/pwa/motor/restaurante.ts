import { t } from '@/i18n';

import { db, lerMeta, type Registo } from './base';
import { enqueue } from './fila';
import { getProducts, temModulo } from './vendas';
import { arredondar2, dataDeHoje, numero, soDados, uuidV4 } from './util';

/**
 * O POS DE RESTAURANTE, sem rede.
 *
 * A diferença para o balcão é a MESA: a venda fica aberta enquanto as pessoas
 * comem, recebe mais pratos, e só no fim vira documento. Nada aqui fala com o
 * servidor: o que sobe é sempre a comanda INTEIRA pela fila
 * (`sync_restaurant_order`), idempotente por identificador.
 */

async function guardar(comanda: Registo, itens: Registo[], extra: Registo = {}): Promise<Registo> {
    let subtotal = 0;
    let imposto = 0;

    for (const i of itens) {
        const base = numero(i.quantity) * numero(i.unit_price);
        subtotal += base;
        imposto += base * (numero(i.tax_rate) / 100);
    }

    await db.rest_orders.update(comanda.local_uuid, {
        items: soDados(itens),
        subtotal: arredondar2(subtotal),
        tax: arredondar2(imposto),
        total: arredondar2(subtotal + imposto),
        ...extra,
    });

    return (await db.rest_orders.get(comanda.local_uuid))!;
}

export const restaurante = {
    activo: () => temModulo('restaurant'),

    async definicoes(): Promise<Registo> {
        return (await lerMeta<Registo>('restaurant_settings')) || {};
    },

    salas: () => db.rest_venues.toArray(),

    async zonas(venueId: number | null = null): Promise<Registo[]> {
        const todas = await db.rest_areas.toArray();
        const filtradas = venueId ? todas.filter((z) => z.venue_id === venueId) : todas;

        return filtradas.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
    },

    /**
     * As mesas, com o que ESTE aparelho sabe por cima do que o servidor disse:
     * uma mesa aberta offline aparece ocupada antes de a comanda subir — senão
     * dois empregados sentavam gente na mesma.
     */
    async mesas(venueId: number | null = null, areaId: number | null = null): Promise<Registo[]> {
        let mesas = await db.rest_tables.toArray();
        if (venueId) mesas = mesas.filter((m) => m.venue_id === venueId);
        if (areaId) mesas = mesas.filter((m) => m.area_id === areaId);

        const abertas = await db.rest_orders.where('status').notEqual('fechada').toArray();
        const porMesa = new Map(abertas.filter((c) => c.table_id).map((c) => [c.table_id, c]));

        return mesas
            .map((m): Registo => {
                const c = porMesa.get(m.id);

                return {
                    ...m,
                    status: c ? 'occupied' : m.status,
                    comanda: c ? {
                        local_uuid: c.local_uuid,
                        numero: c._server_number || null,
                        total: c.total || 0,
                        artigos: (c.items || []).length,
                        enviada_cozinha: !!c.enviada_cozinha,
                    } : null,
                };
            })
            .sort((a, b) => String(a.code || a.name).localeCompare(String(b.code || b.name), undefined, { numeric: true }));
    },

    /**
     * Os pratos que se podem vender. Quando a empresa exige ficha técnica, o
     * servidor recusa o artigo sem ela — e uma comanda recusada depois de a
     * comida sair é dinheiro parado. Filtra-se aqui para nem ser oferecido.
     */
    async pratos(filtro: { search?: string } = {}): Promise<Registo[]> {
        const artigos = await getProducts(filtro);
        const permitidos = ((await lerMeta<Registo>('restaurant_settings')) || {}).recipe_product_ids;

        if (!Array.isArray(permitidos)) return artigos;

        const conjunto = new Set(permitidos);

        return artigos.filter((a) => conjunto.has(a.id));
    },

    async comandas(incluirFechadas = false): Promise<Registo[]> {
        const todas = await db.rest_orders.orderBy('created_at').reverse().toArray();

        return incluirFechadas ? todas : todas.filter((c) => c.status !== 'fechada');
    },

    comanda: (uuid: string) => db.rest_orders.get(uuid),

    /** Abre a comanda, só no aparelho: o número CMD- é do servidor. */
    async abrir({ venue_id, table_id = null, guest_count = 1, channel = null, notes = null }: Registo = {}): Promise<Registo> {
        if (!venue_id) throw new Error(t('Escolha o estabelecimento antes de abrir a comanda.'));

        if (table_id) {
            const jaAberta = (await db.rest_orders.where('status').notEqual('fechada').toArray()).find((c) => c.table_id === table_id);
            if (jaAberta) return jaAberta;   // a mesa já é dela: continua-se a mesma
        }

        const id = uuidV4();
        const comanda: Registo = {
            local_uuid: id,
            // O número do talão enquanto o verdadeiro não chega. Inventar uma
            // sequência CMD- daria dois aparelhos a produzir a mesma.
            provisional_number: 'MESA-' + dataDeHoje().replace(/-/g, '') + '-' + id.slice(-6).toUpperCase(),
            venue_id,
            table_id,
            channel: channel || (table_id ? 'table' : 'counter'),
            guest_count: Math.max(1, parseInt(String(guest_count), 10) || 1),
            notes,
            client_id: null,
            items: [],
            enviada_cozinha: false,
            checkout: null,
            status: 'aberta',
            subtotal: 0,
            tax: 0,
            total: 0,
            created_at: new Date().toISOString(),
            _synced: 0,
            _server_id: null,
            _server_number: null,
            _avisos: [],
        };

        await db.rest_orders.put(comanda);
        if (table_id) await db.rest_tables.update(table_id, { status: 'occupied' });

        return comanda;
    },

    async juntar(uuid: string, artigo: Registo): Promise<Registo> {
        const comanda = await db.rest_orders.get(uuid);
        if (!comanda || comanda.status === 'fechada') throw new Error(t('Esta comanda já não recebe artigos.'));

        const itens = (comanda.items || []).map((i: Registo) => ({ ...i }));

        // O mesmo prato pedido outra vez SOMA-SE, desde que ainda não tenha ido
        // para a cozinha e não leve observação própria.
        const igual = itens.find((i: Registo) => i.product_id === artigo.product_id && !i.enviado && !i.notes && !artigo.notes);

        if (igual) {
            igual.quantity = numero(igual.quantity) + (parseFloat(artigo.quantity) || 1);
        } else {
            itens.push({
                local_uuid: uuidV4(),
                product_id: artigo.product_id,
                product_name: artigo.product_name,
                quantity: parseFloat(artigo.quantity) || 1,
                unit_price: numero(artigo.unit_price),
                tax_rate: Number.isFinite(parseFloat(artigo.tax_rate)) ? parseFloat(artigo.tax_rate) : 0,
                notes: artigo.notes || null,
                enviado: false,
            });
        }

        return guardar(comanda, itens);
    },

    async alterarQuantidade(uuid: string, itemUuid: string, delta: number): Promise<Registo | null> {
        const comanda = await db.rest_orders.get(uuid);
        if (!comanda) return null;

        const itens = (comanda.items || []).map((i: Registo) => ({ ...i }));
        const item = itens.find((i: Registo) => i.local_uuid === itemUuid);
        if (!item) return comanda;

        // Já foi para a cozinha: foi cozinhado. Anular exige motivo e desperdício.
        if (item.enviado) throw new Error(t('O artigo já foi para a cozinha — anule-o com motivo.'));

        item.quantity = Math.round((numero(item.quantity) + delta) * 1000) / 1000;

        return guardar(comanda, item.quantity > 0 ? itens : itens.filter((i: Registo) => i.local_uuid !== itemUuid));
    },

    async removerArtigo(uuid: string, itemUuid: string): Promise<Registo | null> {
        const comanda = await db.rest_orders.get(uuid);
        if (!comanda) return null;

        const item = (comanda.items || []).find((i: Registo) => i.local_uuid === itemUuid);
        if (item?.enviado) throw new Error(t('O artigo já foi para a cozinha — anule-o com motivo.'));

        return guardar(comanda, (comanda.items || []).filter((i: Registo) => i.local_uuid !== itemUuid));
    },

    /**
     * Manda os artigos novos à cozinha. Sem rede a cozinha não recebe nada (é
     * outro aparelho): fixa-se a intenção, e a comanda sobe já confirmada.
     */
    async mandarParaCozinha(uuid: string): Promise<Registo | null> {
        const comanda = await db.rest_orders.get(uuid);
        if (!comanda) return null;

        if (!(comanda.items || []).some((i: Registo) => !i.enviado)) throw new Error(t('Não há artigos novos para enviar.'));

        await guardar(comanda, (comanda.items || []).map((i: Registo) => ({ ...i, enviado: true })), { enviada_cozinha: true, status: 'na_cozinha' });
        await enqueue('sync_restaurant_order', { local_uuid: uuid });

        return (await db.rest_orders.get(uuid)) ?? null;
    },

    /** Fecha a conta: guarda o pagamento e põe a comanda a caminho. Quem emite é o servidor. */
    async receber(uuid: string, pagamento: Registo = {}): Promise<Registo> {
        const comanda = await db.rest_orders.get(uuid);
        if (!comanda) throw new Error(t('Comanda não encontrada.'));
        if (!(comanda.items || []).length) throw new Error(t('Uma comanda vazia não se factura.'));

        await db.rest_orders.update(uuid, {
            status: 'fechada',
            // Foi servida e paga: sobe confirmada, para o servidor repor pela ordem certa.
            enviada_cozinha: true,
            items: (comanda.items || []).map((i: Registo) => ({ ...i, enviado: true })),
            checkout: {
                document_type: pagamento.document_type || 'FR',
                client_id: pagamento.client_id || null,
                payment_method_id: pagamento.payment_method_id || null,
                payments: pagamento.payments || null,
            },
            closed_at: new Date().toISOString(),
        });

        // A mesa fica em limpeza, como no POS online depois do fecho.
        if (comanda.table_id) await db.rest_tables.update(comanda.table_id, { status: 'cleaning' });

        await enqueue('sync_restaurant_order', { local_uuid: uuid });

        return (await db.rest_orders.get(uuid))!;
    },

    /**
     * A comanda na forma que o talão sabe imprimir — o mesmo papel do balcão,
     * com a mesa no cabeçalho. Antes de subir sai PROVISÓRIO; depois, com
     * número, ATCUD, QR, hash e os totais do servidor.
     */
    async talao(uuid: string): Promise<Registo | null> {
        const c = await db.rest_orders.get(uuid);
        if (!c) return null;

        const metodos = (await lerMeta<Registo[]>('payment_methods')) || [];
        const metodo = metodos.find((m) => m.id === c.checkout?.payment_method_id);
        const mesa = c.table_id ? await db.rest_tables.get(c.table_id) : null;

        return {
            _synced: c._synced ? 1 : 0,
            _server_number: c._invoice_number || null,
            provisional_number: c.provisional_number,
            _server_qr: c._server_qr || null,
            _server_atcud: c._server_atcud || null,
            _server_hash: c._server_hash || null,
            hash_control: c._hash_control || '1',
            doc_type: c.checkout?.document_type || 'FR',
            origem: mesa ? (mesa.name || mesa.code) : null,
            origem_numero: c._server_number || null,
            created_at: c.closed_at || c.created_at,
            client_name: c.client_name || null,
            client_nif: c.client_nif || null,
            payment_method: metodo?.code || metodo?.type || 'cash',
            amount_received: c.total,
            subtotal: c.subtotal,
            discount_amount: c.discount_amount || 0,
            tax: c.tax,
            total: c.total,
            notes: c.notes || null,
            // As linhas do SERVIDOR quando existem: se o preço mudou, é o documento que manda.
            items: (c.items_facturados || c.items || []).map((i: Registo) => ({
                product_name: i.product_name, quantity: i.quantity, unit_price: i.unit_price, tax_rate: i.tax_rate,
            })),
        };
    },

    /** Descarta uma comanda que nunca chegou a ter nada nem a subir. */
    async descartar(uuid: string): Promise<void> {
        const comanda = await db.rest_orders.get(uuid);
        if (!comanda) return;

        if ((comanda.items || []).length || comanda.enviada_cozinha) throw new Error(t('Só se descarta uma comanda vazia e por enviar.'));

        await db.rest_orders.delete(uuid);
        if (comanda.table_id) await db.rest_tables.update(comanda.table_id, { status: 'available' });
    },

    /** Compatibilidade com quem chamava o método interno. */
    _guardar: guardar,
};
