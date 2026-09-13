import Dexie, { type Table } from 'dexie';

/**
 * A BASE DO APARELHO — IndexedDB, pelo Dexie.
 *
 * É a MESMA base do motor antigo (`SosErpInvoicing`) com as MESMAS versões,
 * pela mesma ordem. Não é pormenor: os aparelhos que já estão nas lojas têm
 * vendas por enviar nesta base, e uma versão fora de ordem obrigava o Dexie a
 * uma migração que não existe — a fila ficava inacessível.
 *
 * Cada versão nova tem de ser ADITIVA: nunca tocar em `sync_queue`,
 * `pos_sales` nem `draft_documents`.
 */

// Os registos vêm do servidor e mudam de forma com o tempo (campos de farmácia,
// de vestuário…). Tipam-se os campos que o motor lê; o resto passa.
export type Registo = Record<string, any>;

export interface Produto extends Registo {
    id: number | string;
    name?: string;
    sku?: string | null;
    barcode?: string | null;
    type?: string;
    category?: string | null;
    price?: number;
    tax_rate?: number | string;
    stock_quantity?: number | string;
    manage_stock?: boolean;
}

export interface Cliente extends Registo {
    id: number | string;
    local_uuid?: string | null;
    name?: string;
    nif?: string | null;
    _synced?: number;
}

export interface Trabalho extends Registo {
    id?: number;
    op: string;
    payload: Registo | null;
    tenant_id?: number | null;
    created_at: string;
    retries: number;
    status: 'pending' | 'failed' | 'done' | 'outra_empresa' | string;
    last_error?: string | null;
    synced_at?: string;
}

export interface Meta {
    key: string;
    value: any;
}

export class BaseDoPwa extends Dexie {
    products!: Table<Produto, number | string>;
    clients!: Table<Cliente, number | string>;
    series!: Table<Registo, number>;
    tax_rates!: Table<Registo, number>;
    draft_invoices!: Table<Registo, string>;
    sync_queue!: Table<Trabalho, number>;
    meta!: Table<Meta, string>;
    draft_documents!: Table<Registo, string>;
    pos_sales!: Table<Registo, string>;
    iec_pautais!: Table<Registo, string>;
    is_verbas!: Table<Registo, string>;
    employees!: Table<Registo, string>;
    rest_venues!: Table<Registo, number>;
    rest_areas!: Table<Registo, number>;
    rest_tables!: Table<Registo, number>;
    rest_orders!: Table<Registo, string>;

    constructor(nome = 'SosErpInvoicing') {
        super(nome);

        this.version(1).stores({
            products: 'id, name, sku, barcode, type, category',
            clients: 'id, local_uuid, name, nif, _synced',
            series: 'id, document_type',
            tax_rates: '++id, rate',
            draft_invoices: 'local_uuid, client_id, client_local_uuid, created_at, _synced, _status',
            sync_queue: '++id, op, created_at, retries, status',
            meta: 'key',
        });
        // v2 — draft_documents (4 tipos)
        this.version(2).stores({
            draft_documents: 'local_uuid, doc_type, client_id, client_local_uuid, created_at, _synced, _server_id',
        });
        // v3 — pos_sales (vendas POS offline → Fatura-Recibo real ao sincronizar)
        this.version(3).stores({
            pos_sales: 'local_uuid, created_at, _synced, _server_id, _server_number',
        });
        // v4 — as tabelas da AGT para o IEC e o Imposto de Selo
        this.version(4).stores({
            iec_pautais: 'pautal_code',
            is_verbas: 'verba_no',
        });
        // v5 — os funcionários do tenant para o login offline (PIN de turno)
        this.version(5).stores({
            employees: 'email, id',
        });
        // v6 — a sala do restaurante e as comandas feitas sem rede
        this.version(6).stores({
            rest_venues: 'id, name',
            rest_areas: 'id, venue_id',
            rest_tables: 'id, venue_id, area_id, status',
            rest_orders: 'local_uuid, table_id, venue_id, status, created_at, _synced, _server_id',
        });
    }
}

export const db = new BaseDoPwa();

/** Um valor de `meta`, ou `padrao` se não houver. */
export async function lerMeta<T = any>(chave: string, padrao: T | null = null): Promise<T | null> {
    const m = await db.meta.get(chave);

    return m ? (m.value as T) : padrao;
}
